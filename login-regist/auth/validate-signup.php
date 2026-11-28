<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$field = $input['field'] ?? '';
$value = trim((string) ($input['value'] ?? ''));

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

try {
    if ($field === 'email') {
        $email = strtolower($value);

        if ($email === '') {
            echo json_encode(['valid' => false, 'message' => 'Email is required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['valid' => false, 'message' => 'Invalid email format (e.g., name@gmail.com).']);
            exit;
        }

        if (!isRealEmailDomain($email)) {
            echo json_encode(['valid' => false, 'message' => 'This email does not exist or its domain cannot receive emails.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT 1 FROM sooma_wellness.users WHERE LOWER(email) = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);

        if ($stmt->fetchColumn()) {
            echo json_encode(['valid' => false, 'message' => 'This email is already registered.']);
            exit;
        }

        echo json_encode(['valid' => true, 'message' => 'Email is valid and available.']);
        exit;
    }

    if ($field === 'phone') {
        if ($value === '') {
            echo json_encode(['valid' => false, 'message' => 'Phone number is required.']);
            exit;
        }

        if (preg_match('/[a-zA-Z]/', $value)) {
            echo json_encode(['valid' => false, 'message' => 'Phone number cannot contain letters. Use digits only.']);
            exit;
        }

        if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $value)) {
            echo json_encode(['valid' => false, 'message' => 'Invalid characters in phone number. Use digits only (e.g., 081234567890).']);
            exit;
        }

        $normalized = normalizePhoneNumber($value);
        $digitsOnly = ltrim($normalized, '+');

        if (strlen($digitsOnly) < 10) {
            echo json_encode(['valid' => false, 'message' => 'Phone number is too short (minimum 10 digits required).']);
            exit;
        }

        if (strlen($digitsOnly) > 15) {
            echo json_encode(['valid' => false, 'message' => 'Phone number is too long (maximum 15 digits allowed).']);
            exit;
        }

        if (!preg_match('/^(08[0-9]{8,12}|\+[1-9][0-9]{9,14})$/', $normalized)) {
            echo json_encode(['valid' => false, 'message' => 'Invalid phone number format. Start with 08... or +62...']);
            exit;
        }

        $stmt = $pdo->prepare(
            "SELECT 1 FROM sooma_wellness.users WHERE phone_number = :phone LIMIT 1"
        );
        $stmt->execute([':phone' => $normalized]);

        if ($stmt->fetchColumn()) {
            echo json_encode(['valid' => false, 'message' => 'This phone number is already registered.']);
            exit;
        }

        echo json_encode(['valid' => true, 'message' => 'Phone number is valid.']);
        exit;
    }

    echo json_encode(['valid' => false, 'message' => 'Unknown field.']);
} catch (Throwable $e) {
    error_log('VALIDATE SIGNUP ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Unable to verify right now.']);
}