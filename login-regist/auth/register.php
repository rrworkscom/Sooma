<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';

// hanya menerima pengiriman form POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=register');
    exit;
}

// data user di form registrasi
$name     = trim($_POST['full_name'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$phone    = trim($_POST['phone'] ?? '');
$terms    = $_POST['terms'] ?? '';

// validasi field yg wajib diisi
if ($name === '' || $email === '' || $password === '' || $phone === '') {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

// validasi persetujuan tnc
if ($terms !== 'on') {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

// validasi format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

// validasi panjang password
if (strlen($password) < 8) {
    header('Location: ../index.php?page=register&error=invalid');
    exit;
}

// hash password sebelum disimpan
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (name, email, password, phone, role)
         VALUES
            (:name, :email, :password, :phone, 'customer')"
    );

    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':password' => $hashedPassword,
        ':phone'    => $phone
    ]);

    // saat regist berhasil, masuk halaman login
    header('Location: ../index.php?page=login&success=registered');
    exit;

} catch (PDOException $e) {
    // PostgreSQL: jika email duplikat (unique_violation)
    if ($e->getCode() === '23505') {
        header('Location: ../index.php?page=register&error=email_exists');
        exit;
    }

    // detail error di log
    error_log($e->getMessage());

    header('Location: ../index.php?page=register&error=server');
    exit;
}
