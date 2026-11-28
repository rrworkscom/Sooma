<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';
require_once dirname(__DIR__, 2) . '/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=forgot_password');
    exit;
}

$isResend = ($_POST['resend'] ?? '') === '1';
$email    = strtolower(trim($_POST['email'] ?? ($_SESSION['reset_email'] ?? '')));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../index.php?page=forgot_password&error=invalid_email');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "select user_id, name, email
         from sooma_wellness.users
         where lower(email) = :email
         limit 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $pollToken = bin2hex(random_bytes(32));
    $pollHash  = hash('sha256', $pollToken);

    $_SESSION['reset_email']      = $email;
    $_SESSION['reset_poll_token'] = $pollToken;
    unset($_SESSION['verified_reset_id']);

    // Tetap arahkan ke halaman konfirmasi meski email tidak terdaftar (keamanan)
    if (!$user) {
        $statusParam = $isResend ? '&resent=1' : '';
        header('Location: ../index.php?page=reset_link_sent' . $statusParam);
        exit;
    }

    $pdo->beginTransaction();

    // Nonaktifkan token lama yang belum terpakai
    $invalidateStmt = $pdo->prepare(
        "update sooma_wellness.password_resets
         set used_at = now()
         where user_id = :user_id
           and used_at is null"
    );
    $invalidateStmt->execute([':user_id' => $user['user_id']]);

    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $insertStmt = $pdo->prepare(
        "insert into sooma_wellness.password_resets
            (user_id, token_hash, poll_hash, expires_at)
         values
            (:user_id, :token_hash, :poll_hash, now() + interval '5 minutes')"
    );
    $insertStmt->execute([
        ':user_id'    => $user['user_id'],
        ':token_hash' => $tokenHash,
        ':poll_hash'  => $pollHash,
    ]);

    $pdo->commit();

    $baseUrl = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '', '/');
    if ($baseUrl === '') {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir     = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
        $baseUrl = $scheme . '://' . $host . $dir;
    }

    $baseUrl = preg_replace('#/index\.php$#i', '', $baseUrl);
    $resetLink = $baseUrl . '/index.php?page=reset_password&token=' . urlencode($rawToken);
    
    $errorDetail = '';
    $sent = sw_send_reset_email($user['email'], $user['name'], $resetLink, $errorDetail);

    if (!$sent) {
        $_SESSION['mail_debug_error'] = $errorDetail !== '' ? $errorDetail : 'Unknown mailer error';
        $backPage = $isResend ? 'reset_link_sent' : 'forgot_password';
        header('Location: ../index.php?page=' . $backPage . '&error=mail_failed');
        exit;
    }

    $statusParam = $isResend ? '&resent=1' : '';
    header('Location: ../index.php?page=reset_link_sent' . $statusParam);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('FORGOT PASSWORD ERROR: ' . $e->getMessage());
    $backPage = $isResend ? 'reset_link_sent' : 'forgot_password';
    header('Location: ../index.php?page=' . $backPage . '&error=server');
    exit;
}
