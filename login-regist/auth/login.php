<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=login');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: ../index.php?page=login&error=invalid');
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, name, email, password, phone, role
         FROM users
         WHERE lower(email) = :email
         LIMIT 1"
    );

    $stmt->execute([
        ':email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: ../index.php?page=login&error=invalid');
        exit;
    }

    $allowedRoles = ['admin', 'customer', 'coach'];
    $role = $user['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        header('Location: ../index.php?page=login&error=invalid');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['phone']   = $user['phone'];
    $_SESSION['role']    = $role;

    switch ($role) {
        case 'admin':
            header('Location: ../../admin_dashboard.php');
            break;

        case 'coach':
            header('Location: ../../coach/index.php');
            break;

        case 'customer':
            header('Location: ../../customer/homepage.php');
            break;
    }
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());
    header('Location: ../index.php?page=login&error=server');
    exit;
}
