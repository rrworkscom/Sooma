<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$possibleAutoloads = [
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__) . '/login-regist/vendor/autoload.php',
];

foreach ($possibleAutoloads as $autoloadPath) {
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
        break;
    }
}

function sw_crop_logo_png(string $filePath): string
{
    if (!function_exists('imagecreatefrompng') || !is_file($filePath)) {
        return '';
    }

    $src = @imagecreatefrompng($filePath);
    if ($src === false) {
        return '';
    }

    $width  = imagesx($src);
    $height = imagesy($src);

    $minX = $width;
    $minY = $height;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba  = imagecolorat($src, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            $r     = ($rgba >> 16) & 0xFF;
            $g     = ($rgba >> 8) & 0xFF;
            $b     = $rgba & 0xFF;

            $isVisibleInk = ($alpha < 110) && ($r < 235 || $g < 235 || $b < 235);
            if ($isVisibleInk) {
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
            }
        }
    }

    if ($maxX < $minX || $maxY < $minY) {
        imagedestroy($src);
        return '';
    }

    $pad   = 2;
    $cropX = max(0, $minX - $pad);
    $cropY = max(0, $minY - $pad);
    $cropW = min($width - $cropX, ($maxX - $minX + 1) + ($pad * 2));
    $cropH = min($height - $cropY, ($maxY - $minY + 1) + ($pad * 2));

    $dest = imagecreatetruecolor($cropW, $cropH);
    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
    imagefilledrectangle($dest, 0, 0, $cropW, $cropH, $transparent);

    imagecopy($dest, $src, 0, 0, $cropX, $cropY, $cropW, $cropH);

    ob_start();
    imagepng($dest);
    $pngData = (string) ob_get_clean();

    imagedestroy($dest);
    imagedestroy($src);

    return $pngData;
}

function sw_send_reset_email(
    string $recipientEmail,
    string $recipientName,
    string $resetLink,
    string &$errorDetail = ''
): bool {
    $envPath = dirname(__DIR__) . '/login-regist/.env';
    if (!isset($_ENV['SMTP_USER']) && is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v, " \t\"'");
        }
    }

    if (!class_exists(PHPMailer::class)) {
        $errorDetail = 'PHPMailer tidak ditemukan. Jalankan "composer require phpmailer/phpmailer" di folder Sooma.';
        error_log('MAILER ERROR: ' . $errorDetail);
        return false;
    }

    $smtpUser  = trim($_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '');
    $smtpPass  = str_replace(' ', '', trim($_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: ''));
    $fromEmail = trim($_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: '');
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $smtpUser;
    }
    $fromName  = trim($_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Sooma Wellness');

    if ($smtpUser === '' || $smtpPass === '') {
        $errorDetail = 'SMTP_USER atau SMTP_PASS belum terbaca. Pastikan sudah diisi di login-regist/.env.';
        error_log('MAILER ERROR: ' . $errorDetail);
        return false;
    }

    $possibleLogos = [
        dirname(__DIR__) . '/assets/images/sooma-logo-black.png',
        dirname(__DIR__) . '/assets/img/sooma-logo-black.png',
        dirname(__DIR__) . '/assets/sooma-logo-black.png',
    ];
    $logoPath = '';
    foreach ($possibleLogos as $candidate) {
        if (is_file($candidate)) {
            $logoPath = $candidate;
            break;
        }
    }

    $croppedLogoData = $logoPath !== '' ? sw_crop_logo_png($logoPath) : '';
    $hasLogo         = ($croppedLogoData !== '' || $logoPath !== '');

    $logoMarkup = $hasLogo
        ? '<img src="cid:sooma_logo" alt="Sooma Wellness" width="150" style="display:block;width:150px;height:auto;border:0;margin:0;padding:0;">'
        : '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:32px;font-weight:700;color:#1b1715;letter-spacing:-1px;line-height:1;">Sooma<span style="display:block;font-family:Arial,sans-serif;font-size:9px;letter-spacing:3.5px;font-weight:400;margin-top:4px;color:#2e2622;">WELLNESS</span></div>';

    $subject  = 'Reset Your Sooma Wellness Password';
    $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Sooma Wellness</title>
</head>
<body style="margin:0;padding:0;background-color:#A3B2A1;font-family:'Helvetica Neue',Arial,Helvetica,sans-serif;color:#2e2622;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#A3B2A1;padding:36px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background-color:#F6F4EE;border-radius:20px;padding:32px 36px;box-shadow:0 8px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="padding:0 0 18px 0;line-height:1;">
                            {$logoMarkup}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;">
                            <h1 style="font-size:24px;font-weight:700;margin:0 0 14px;color:#2e2622;letter-spacing:-0.3px;">
                                Reset Your Password
                            </h1>

                            <p style="font-size:15px;line-height:1.6;margin:0 0 14px;color:#3d3531;">
                                Hi {$safeName},
                            </p>

                            <p style="font-size:15px;line-height:1.6;margin:0 0 26px;color:#3d3531;">
                                We received a request to reset the password for your Sooma Wellness account. Click the button below to choose a new password:
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 26px;">
                                <tr>
                                    <td align="center">
                                        <a href="{$safeLink}" style="display:inline-block;background-color:#A3B2A1;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 36px;border-radius:999px;letter-spacing:0.2px;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:13.5px;line-height:1.6;color:#5c534e;margin:0 0 20px;">
                                This link will expire in <strong>5 minutes</strong>. If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <hr style="border:none;border-top:1px solid #dfd9ce;margin:0 0 18px;">

                            <p style="font-size:12px;line-height:1.6;color:#6e655f;margin:0;word-break:break-all;">
                                If the button does not work, copy and paste this link into your browser:<br>
                                <a href="{$safeLink}" style="color:#1a73e8;text-decoration:underline;">{$safeLink}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    $textBody = "Hi {$recipientName},\n\n"
        . "Open the following link to reset your Sooma Wellness password (valid for 5 minutes):\n"
        . "{$resetLink}\n\n"
        . "If you did not request this, please ignore this email.";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);

        $secure = strtolower($_ENV['SMTP_SECURE'] ?? getenv('SMTP_SECURE') ?: 'tls');
        $mail->SMTPSecure = ($secure === 'ssl')
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);

        if ($croppedLogoData !== '') {
            $mail->addStringEmbeddedImage($croppedLogoData, 'sooma_logo', 'sooma-logo-black.png', 'base64', 'image/png');
        } elseif ($logoPath !== '') {
            $mail->addEmbeddedImage($logoPath, 'sooma_logo', 'sooma-logo-black.png');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody;

        return $mail->send();
    } catch (Exception $e) {
        $errorDetail = $mail->ErrorInfo ?: $e->getMessage();
        error_log('MAILER ERROR: ' . $errorDetail);
        return false;
    }
}