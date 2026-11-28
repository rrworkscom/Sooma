<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once dirname(__DIR__) . '/connection.php';

$pollToken = $_SESSION['reset_poll_token'] ?? '';

if ($pollToken === '' || !ctype_xdigit($pollToken) || strlen($pollToken) !== 64) {
    echo json_encode(['verified' => false]);
    exit;
}

try {
    $pollHash = hash('sha256', $pollToken);

    $stmt = $pdo->prepare(
        "select reset_id, verified_at
         from sooma_wellness.password_resets
         where poll_hash = :poll_hash
           and used_at is null
           and expires_at > now()
         order by reset_id desc
         limit 1"
    );
    $stmt->execute([':poll_hash' => $pollHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['verified_at'])) {
        $_SESSION['verified_reset_id'] = (int) $row['reset_id'];

        echo json_encode([
            'verified'     => true,
            'redirect_url' => 'index.php?page=reset_password'
        ]);
        exit;
    }

    echo json_encode(['verified' => false]);
} catch (Throwable $e) {
    error_log('CHECK RESET STATUS ERROR: ' . $e->getMessage());
    echo json_encode(['verified' => false]);
}