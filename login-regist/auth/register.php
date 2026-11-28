<?php
session_start();
require_once dirname(__DIR__) . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=register');
    exit;
}

function normalizePhoneNumber(string $raw): string {
    $clean = preg_replace('/[\s\-\(\)]+/', '', $raw);
    if (str_starts_with($clean, '+62')) {
        return '0' . substr($clean, 3);
    }
    if (str_starts_with($clean, '62') && strlen($clean) >= 11) {
        return '0' . substr($clean, 2);
    }
    return $clean;
}

function isRealEmailDomain(string $email): bool {
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return false;
    }

    $domain = strtolower(substr($email, $atPos + 1));
    if ($domain === '' || !str_contains($domain, '.')) {
        return false;
    }

    $blockedDomains = [
        'example.com', 'example.org', 'example.net',
        'test.com', 'invalid.com', 'mailinator.com',
        'tempmail.com', '10minutemail.com', 'guerrillamail.com'
    ];
    if (in_array($domain, $blockedDomains, true)) {
        return false;
    }

    $asciiDomain = function_exists('idn_to_ascii')
        ? (idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain)
        : $domain;

    return checkdnsrr($asciiDomain . '.', 'MX') || checkdnsrr($asciiDomain . '.', 'A');
}

$name            = trim($_POST['full_name'] ?? '');
$email           = strtolower(trim($_POST['email'] ?? ''));
$rawPhone        = trim($_POST['phone'] ?? '');
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$terms           = $_POST['terms'] ?? '';

$errors = [];

// 1. Validasi Nama
if ($name === '') {
    $errors['full_name'] = 'Full name is required.';
}

// 2. Validasi Format Email & Keaslian Domain Email
if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email format (e.g., name@gmail.com).';
} elseif (!isRealEmailDomain($email)) {
    $errors['email'] = 'This email does not exist or its domain cannot receive emails.';
}

$normalizedPhone = '';
if ($rawPhone === '') {
    $errors['phone'] = 'Phone number is required.';
} elseif (preg_match('/[a-zA-Z]/', $rawPhone)) {
    $errors['phone'] = 'Phone number cannot contain letters. Use digits only.';
} elseif (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $rawPhone)) {
    $errors['phone'] = 'Invalid characters in phone number. Use digits only (e.g., 081234567890).';
} else {
    $normalizedPhone = normalizePhoneNumber($rawPhone);
    $digitsOnly      = ltrim($normalizedPhone, '+');

    if (strlen($digitsOnly) < 10) {
        $errors['phone'] = 'Phone number is too short (minimum 10 digits required).';
    } elseif (strlen($digitsOnly) > 15) {
        $errors['phone'] = 'Phone number is too long (maximum 15 digits allowed).';
    } elseif (!preg_match('/^(08[0-9]{8,12}|\+[1-9][0-9]{9,14})$/', $normalizedPhone)) {
        $errors['phone'] = 'Invalid phone number format. Start with 08... or +62...';
    }
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters long.';
}

if ($passwordConfirm === '') {
    $errors['password_confirm'] = 'Please re-enter your password.';
} elseif ($password !== $passwordConfirm) {
    $errors['password_confirm'] = 'Passwords do not match.';
}

if ($terms !== 'on') {
    $errors['terms'] = 'You must agree to the Terms & Conditions and Privacy Policy.';
}

if (empty($errors['email']) || empty($errors['phone'])) {
    try {
        if (empty($errors['email'])) {
            $checkEmailStmt = $pdo->prepare(
                "SELECT 1 FROM sooma_wellness.users WHERE LOWER(email) = :email LIMIT 1"
            );
            $checkEmailStmt->execute([':email' => $email]);
            if ($checkEmailStmt->fetchColumn()) {
                $errors['email'] = 'This email is already registered.';
            }
        }

        if (empty($errors['phone'])) {
            $checkPhoneStmt = $pdo->prepare(
                "SELECT 1 FROM sooma_wellness.users WHERE phone_number = :phone LIMIT 1"
            );
            $checkPhoneStmt->execute([':phone' => $normalizedPhone]);
            if ($checkPhoneStmt->fetchColumn()) {
                $errors['phone'] = 'This phone number is already registered.';
            }
        }
    } catch (PDOException $e) {
        error_log('REGISTER CHECK ERROR: ' . $e->getMessage());
        header('Location: ../index.php?page=register&error=server');
        exit;
    }
}

if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old']    = [
        'full_name' => $name,
        'email'     => $email,
        'phone'     => $rawPhone,
    ];
    header('Location: ../index.php?page=register&error=validation');
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
        ':role_id'            => $role['role_id'],
        ':name'               => $name,
        ':email'              => $email,
        ':password'           => $hashedPassword,
        ':encrypted_password' => $encryptedPassword,
        ':phone'              => $normalizedPhone,
    ]);

    $userId = $userStmt->fetchColumn();

    $customerStmt = $pdo->prepare(
        "INSERT INTO sooma_wellness.customers (user_id)
         VALUES (:user_id)"
    );
    $customerStmt->execute([
        ':user_id' => $userId,
    ]);

    $pdo->commit();

    unset($_SESSION['register_errors'], $_SESSION['register_old']);
    header('Location: ../index.php?page=login&success=registered');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('REGISTER ERROR: ' . $e->getMessage());

    if ($e->getCode() === '23505') {
        $_SESSION['register_old'] = [
            'full_name' => $name,
            'email'     => $email,
            'phone'     => $rawPhone,
        ];
        if (str_contains($e->getMessage(), 'phone')) {
            $_SESSION['register_errors'] = ['phone' => 'This phone number is already registered.'];
        } else {
            $_SESSION['register_errors'] = ['email' => 'This email is already registered.'];
        }
        header('Location: ../index.php?page=register&error=validation');
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