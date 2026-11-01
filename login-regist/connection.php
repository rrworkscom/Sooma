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

$debug       = ($_ENV['APP_DEBUG'] ?? '') === '1';
$databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');

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
    $pdo = new PDO($dsn, urldecode($parts['user']), urldecode($parts['pass']), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    die($debug ? 'Connection failed: ' . $e->getMessage() : 'Connection Failed.');
}