<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=login');
    exit;
}

$token           = trim($_POST['token'] ?? '');
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
    header('Location: ../index.php?page=reset_password&error=expired');
    exit;
}

if (strlen($password) < 8) {
    header('Location: ../index.php?page=reset_password&token=' . urlencode($token) . '&error=weak');
    exit;
}

if (!hash_equals($password, $passwordConfirm)) {
    header('Location: ../index.php?page=reset_password&token=' . urlencode($token) . '&error=mismatch');
    exit;
}

try {
    $tokenHash = hash('sha256', $token);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "select reset_id, user_id
         from sooma_wellness.password_resets
         where token_hash = :token_hash
           and used_at is null
           and expires_at > now()
         limit 1
         for update"
    );
    $stmt->execute([':token_hash' => $tokenHash]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRow) {
        $pdo->rollBack();
        header('Location: ../index.php?page=reset_password&error=expired');
        exit;
    }

    $hashedPassword    = password_hash($password, PASSWORD_DEFAULT);
    $encryptedPassword = sw_encrypt_password($password);

    $updateUserStmt = $pdo->prepare(
        "update sooma_wellness.users
         set password = :password,
             encrypted_password = :encrypted_password
         where user_id = :user_id"
    );
    $updateUserStmt->execute([
        ':password'           => $hashedPassword,
        ':encrypted_password' => $encryptedPassword,
        ':user_id'            => $resetRow['user_id'],
    ]);

    $markUsedStmt = $pdo->prepare(
        "update sooma_wellness.password_resets
         set used_at = now()
         where reset_id = :reset_id"
    );
    $markUsedStmt->execute([':reset_id' => $resetRow['reset_id']]);

    $pdo->commit();

    header('Location: ../index.php?page=login&success=password_reset');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('RESET PASSWORD ERROR: ' . $e->getMessage());
    header('Location: ../index.php?page=reset_password&token=' . urlencode($token) . '&error=server');
    exit;
}