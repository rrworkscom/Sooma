<?php

$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, " \t\"'");
    }
}

$debug              = ($_ENV['APP_DEBUG'] ?? '') === '1';
$databaseUrl        = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
$googleClientId     = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: '';
$googleClientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: '';
$googleRedirectUri  = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8000/login-regist/index.php';

if (!$databaseUrl) {
    die('DATABASE_URL belum diatur. Cek file .env');
}

$parts = parse_url($databaseUrl);
if ($parts === false || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
    die('Format DATABASE_URL tidak valid.');
}

$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
    $parts['host'],
    $parts['port'] ?? 5432,
    ltrim($parts['path'], '/')
);


try {
    $pdo = new PDO(
        $dsn,
        urldecode($parts['user']),
        urldecode($parts['pass']),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die($debug ? 'Connection failed: ' . $e->getMessage() : 'Connection Failed.');
}

function sw_encryption_key(): string
{
    $key = $_ENV['APP_ENCRYPTION_KEY'] ?? getenv('APP_ENCRYPTION_KEY');

    if (!is_string($key) || strlen($key) !== 32) {
        throw new RuntimeException('APP_ENCRYPTION_KEY belum diatur atau panjangnya bukan 32 karakter. Cek file .env');
    }

    return $key;
}

function sw_encrypt_password(string $plainPassword): string
{
    $cipher = 'aes-256-cbc';
    $key    = sw_encryption_key();
    $ivLen  = openssl_cipher_iv_length($cipher);

    $iv = openssl_random_pseudo_bytes($ivLen, $strong);
    if ($iv === false || $strong !== true) {
        throw new RuntimeException('Gagal membuat IV acak yang aman.');
    }

    $ciphertext = openssl_encrypt($plainPassword, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        throw new RuntimeException('Gagal mengenkripsi password.');
    }

    return base64_encode($iv . $ciphertext);
}

function sw_decrypt_password(?string $storedPayload): string
{
    if ($storedPayload === null || trim($storedPayload) === '') {
        return '';
    }

    $cipher = 'aes-256-cbc';

    try {
        $key = sw_encryption_key();
    } catch (Throwable $e) {
        error_log('DECRYPT PASSWORD: ' . $e->getMessage());
        return '';
    }

    $raw   = base64_decode(trim($storedPayload), true);
    $ivLen = openssl_cipher_iv_length($cipher);

    if ($raw === false || strlen($raw) <= $ivLen) {
        return '';
    }

    $iv         = substr($raw, 0, $ivLen);
    $ciphertext = substr($raw, $ivLen);

    $plain = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    if ($plain === false || preg_match('//u', $plain) !== 1) {
        return '';
    }

    return $plain;
}