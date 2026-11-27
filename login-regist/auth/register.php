<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=register');
    exit;
}

$name     = trim($_POST['full_name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$phone    = trim($_POST['phone'] ?? '');
$terms    = $_POST['terms'] ?? '';

if ($name === '' || $email === '' || $password === '' || $phone === '') {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

if ($terms !== 'on' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $encryptedPassword = sw_encrypt_password($password);

    $pdo->beginTransaction();

    $roleStmt = $pdo->prepare(
        "SELECT role_id
         FROM sooma_wellness.roles
         WHERE role_name = :role
         LIMIT 1"
    );

    $roleStmt->execute([':role' => 'customer']);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        throw new Exception('Role customer tidak ditemukan.');
    }

    $userStmt = $pdo->prepare(
        "INSERT INTO sooma_wellness.users
            (role_id, name, email, password, encrypted_password, phone_number)
         VALUES
            (:role_id, :name, :email, :password, :encrypted_password, :phone)
         RETURNING user_id"
    );

    $userStmt->execute([
        ':role_id'  => $role['role_id'],
        ':name'     => $name,
        ':email'    => $email,
        ':password' => $hashedPassword,
        ':encrypted_password' => $encryptedPassword,
        ':phone'    => $phone
    ]);

    $userId = $userStmt->fetchColumn();

    $customerStmt = $pdo->prepare(
        "INSERT INTO sooma_wellness.customers (user_id)
         VALUES (:user_id)"
    );

    $customerStmt->execute([
        ':user_id' => $userId
    ]);

    $pdo->commit();

    header('Location: ../index.php?page=login&success=registered');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('REGISTER ERROR: ' . $e->getMessage());

    if ($e->getCode() === '23505') {
        header('Location: ../index.php?page=register&error=email_exists');
        exit;
    }

    header('Location: ../index.php?page=register&error=server');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('REGISTER ERROR: ' . $e->getMessage());

    header('Location: ../index.php?page=register&error=server');
    exit;
}