<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../login-regist/connection.php';

$purchasePageUrl  = 'packages-purchase.php';
$packagesListUrl  = 'packages.php';
$transactionUrl   = '../customer/profile-transaction.php';
$bankName         = 'BCA Sooma Wellness';
$bankAccount      = '5105555005';
$bankHolder       = 'RR VANESSA VIOLETA';
$maxBytes         = 5 * 1024 * 1024;
$paymentTtl       = 30 * 60;

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sw_uploaded_image_to_data_uri')) {
    function sw_uploaded_image_to_data_uri(
        array $file,
        int $maxDim = 900,
        int $quality = 75,
        int $maxBytes = 5242880
    ): string {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed or no file was selected.');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid uploaded file source.');
        }

        if (($file['size'] ?? 0) > $maxBytes) {
            $maxMb = (int) round($maxBytes / (1024 * 1024));
            throw new RuntimeException("Image size must be less than {$maxMb}MB.");
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } else {
            $imgInfo = @getimagesize($file['tmp_name']);
            $mime    = $imgInfo['mime'] ?? '';
        }

        if (!in_array($mime, $allowedTypes, true)) {
            throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
        }

        $binaryData = file_get_contents($file['tmp_name']);
        if ($binaryData === false || $binaryData === '') {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        if (function_exists('imagecreatefromstring')) {
            $srcImg = @imagecreatefromstring($binaryData);
            if ($srcImg !== false) {
                if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($file['tmp_name']);
                    $orientation = (int) ($exif['Orientation'] ?? 1);
                    if ($orientation === 3) {
                        $srcImg = imagerotate($srcImg, 180, 0);
                    } elseif ($orientation === 6) {
                        $srcImg = imagerotate($srcImg, -90, 0);
                    } elseif ($orientation === 8) {
                        $srcImg = imagerotate($srcImg, 90, 0);
                    }
                }

                $origW = imagesx($srcImg);
                $origH = imagesy($srcImg);

                if ($origW > $maxDim || $origH > $maxDim) {
                    $ratio = min($maxDim / $origW, $maxDim / $origH);
                    $newW  = max(1, (int) round($origW * $ratio));
                    $newH  = max(1, (int) round($origH * $ratio));
                } else {
                    $newW = $origW;
                    $newH = $origH;
                }

                $dstImg = imagecreatetruecolor($newW, $newH);
                $white = imagecolorallocate($dstImg, 255, 255, 255);
                imagefilledrectangle($dstImg, 0, 0, $newW, $newH, $white);
                imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

                ob_start();
                imagejpeg($dstImg, null, $quality);
                $compressed = ob_get_clean();

                imagedestroy($srcImg);
                imagedestroy($dstImg);

                if ($compressed !== false && $compressed !== '') {
                    $binaryData = $compressed;
                    $mime       = 'image/jpeg';
                }
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binaryData);
    }
}

if (!function_exists('mb_substr_safe')) {
    function mb_substr_safe(string $s, int $len): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]/', '', $s) ?? '';
        return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    }
}

if (!function_exists('pkg_has_status_column')) {
    function pkg_has_status_column(PDO $pdo): bool
    {
        static $hasStatus = null;
        if ($hasStatus !== null) {
            return $hasStatus;
        }
        try {
            $stmt = $pdo->prepare("
                SELECT 1
                  FROM information_schema.columns
                 WHERE table_schema = 'sooma_wellness'
                   AND table_name   = 'user_packages'
                   AND column_name  = 'status'
                 LIMIT 1
            ");
            $stmt->execute();
            $hasStatus = (bool) $stmt->fetchColumn();
        } catch (Throwable $ex) {
            $hasStatus = false;
        }
        return $hasStatus;
    }
}

$statusLabels = [
    'waiting'   => 'Waiting Payment',
    'verifying' => 'Waiting Verification',
    'expired'   => 'Payment Expired',
    'confirmed' => 'Package Activated',
];

$sessionUser = $_SESSION['user'] ?? [];
$userId      = (int) ($_SESSION['user_id'] ?? $sessionUser['user_id'] ?? $sessionUser['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['payment_method'] ?? '') === 'one_time') {
    $packageId     = (int) ($_POST['package_id'] ?? 0);
    $pkgSlug       = trim((string) ($_POST['pkg_slug'] ?? ''));
    $pkgName       = trim((string) ($_POST['package'] ?? 'Class Package'));
    $sessions      = trim((string) ($_POST['sessions'] ?? ''));
    $totalSessions = max(1, (int) ($_POST['total_sessions'] ?? 1));
    $activeDays    = max(1, (int) ($_POST['active_days'] ?? 30));
    $category      = trim((string) ($_POST['category'] ?? ''));
    $price         = (int) ($_POST['price'] ?? 0);

    $custName  = trim((string) ($_POST['customer_name'] ?? ''));
    $custPhone = trim((string) ($_POST['customer_phone'] ?? ''));
    $custEmail = trim((string) ($_POST['customer_email'] ?? ''));

    if ($price <= 0 || $packageId <= 0 || $userId <= 0) {
        header('Location: ' . $packagesListUrl);
        exit;
    }

    $paymentId     = 0;
    $userPackageId = 0;
    $redeemCode    = 'PKG-' . strtoupper(bin2hex(random_bytes(4)));

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmtCust = $pdo->prepare("
                SELECT customer_id
                  FROM sooma_wellness.customers
                 WHERE user_id = :uid
                 LIMIT 1
            ");
            $stmtCust->execute([':uid' => $userId]);
            $customerId = (int) ($stmtCust->fetchColumn() ?: 0);

            if ($customerId > 0) {
                $pdo->beginTransaction();

                $stmtPay = $pdo->prepare("
                    INSERT INTO sooma_wellness.payments
                        (gross_amount, payment_method, verification_status)
                    VALUES
                        (:amount, 'bank_transfer', 'pending')
                    RETURNING payment_id
                ");
                $stmtPay->execute([':amount' => $price]);
                $paymentId = (int) $stmtPay->fetchColumn();

                if (pkg_has_status_column($pdo)) {
                    $stmtPkg = $pdo->prepare("
                        INSERT INTO sooma_wellness.user_packages
                            (customer_id, package_id, remaining_sessions, expired_at, redeem_code, payment_id, status)
                        VALUES
                            (:customer_id, :package_id, :remaining_sessions, CURRENT_TIMESTAMP + (:active_days || ' days')::interval, :redeem_code, :payment_id, 'pending')
                        RETURNING user_package_id
                    ");
                } else {
                    $stmtPkg = $pdo->prepare("
                        INSERT INTO sooma_wellness.user_packages
                            (customer_id, package_id, remaining_sessions, expired_at, redeem_code, payment_id)
                        VALUES
                            (:customer_id, :package_id, :remaining_sessions, CURRENT_TIMESTAMP + (:active_days || ' days')::interval, :redeem_code, :payment_id)
                        RETURNING user_package_id
                    ");
                }

                $stmtPkg->execute([
                    ':customer_id'        => $customerId,
                    ':package_id'         => $packageId,
                    ':remaining_sessions' => $totalSessions,
                    ':active_days'        => $activeDays,
                    ':redeem_code'        => $redeemCode,
                    ':payment_id'         => $paymentId,
                ]);
                $userPackageId = (int) $stmtPkg->fetchColumn();

                $pdo->commit();
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[packages-payment-init] ' . $ex->getMessage());
            $_SESSION['pay_flash'] = ['type' => 'error', 'message' => 'Failed to create package order. Please try again.'];
            header('Location: ' . $packagesListUrl);
            exit;
        }
    }

    $_SESSION['package_draft'] = [
        'payment_id'      => $paymentId,
        'user_package_id' => $userPackageId,
        'order_code'      => $redeemCode,
        'pkg_slug'        => $pkgSlug,
        'package_name'    => $pkgName,
        'sessions'        => $sessions,
        'category'        => $category,
        'total_payment'   => $price,
        'customer_name'   => $custName,
        'customer_phone'  => $custPhone,
        'customer_email'  => $custEmail,
        'status'          => 'waiting',
        'expires_at'      => time() + $paymentTtl,
        'receipt_file'    => null,
        'receipt_orig'    => null,
    ];

    header('Location: ' . basename(__FILE__));
    exit;
}

$draft = &$_SESSION['package_draft'];

if (empty($draft) || !is_array($draft)) {
    header('Location: ' . $packagesListUrl);
    exit;
}

if (empty($draft['expires_at'])) {
    $draft['expires_at'] = time() + $paymentTtl;
}

$status = (string) ($draft['status'] ?? 'waiting');

if (!empty($draft['payment_id']) && isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmtChk = $pdo->prepare("
            SELECT verification_status, payment_proof
              FROM sooma_wellness.payments
             WHERE payment_id = :pid
             LIMIT 1
        ");
        $stmtChk->execute([':pid' => (int) $draft['payment_id']]);
        $payRow = $stmtChk->fetch(PDO::FETCH_ASSOC) ?: [];
        $dbVerifStatus = strtolower((string) ($payRow['verification_status'] ?? ''));

        if (!empty($payRow['payment_proof']) && empty($draft['receipt_file'])) {
            $draft['receipt_file'] = (string) $payRow['payment_proof'];
            $draft['receipt_orig'] = 'Payment-Receipt-' . ($draft['order_code'] ?? 'PKG') . '.jpg';
        }

        if (in_array($dbVerifStatus, ['verified', 'confirmed', 'approved', 'paid'], true)) {
            $status = 'confirmed';
            $draft['status'] = 'confirmed';
        } elseif ($dbVerifStatus === 'expired') {
            $status = 'expired';
            $draft['status'] = 'expired';
        } elseif ($dbVerifStatus === 'waiting_verification' && $status === 'waiting') {
            $status = 'verifying';
            $draft['status'] = 'verifying';
        }
    } catch (Throwable $ex) {
        error_log('[packages-payment-sync] ' . $ex->getMessage());
    }
}

if ($status === 'waiting' && time() >= (int) $draft['expires_at']) {
    $status = 'expired';
    $draft['status'] = 'expired';

    if (!empty($draft['payment_id']) && isset($pdo) && $pdo instanceof PDO) {
        try {
            $pdo->beginTransaction();

            $stmtExp = $pdo->prepare("
                UPDATE sooma_wellness.payments
                   SET verification_status = 'expired'
                 WHERE payment_id = :pid
                   AND verification_status = 'pending'
            ");
            $stmtExp->execute([':pid' => (int) $draft['payment_id']]);

            if (pkg_has_status_column($pdo)) {
                $stmtPkgExp = $pdo->prepare("
                    UPDATE sooma_wellness.user_packages
                       SET status = 'expired'
                     WHERE payment_id = :pid
                       AND status = 'pending'
                ");
                $stmtPkgExp->execute([':pid' => (int) $draft['payment_id']]);
            }

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[packages-payment-expire] ' . $ex->getMessage());
        }
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

    $reply = function (bool $ok, string $message, int $code = 200) use ($wantsJson, &$status, $statusLabels): void {
        if ($wantsJson) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => $ok,
                'message' => $message,
                'status'  => $status,
                'label'   => $statusLabels[$status] ?? '',
            ]);
            exit;
        }
        $_SESSION['pay_flash'] = ['type' => $ok ? 'success' : 'error', 'message' => $message];
        header('Location: ' . basename(__FILE__));
        exit;
    };

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $reply(false, 'Your session has expired. Please refresh the page.', 400);
    }
    if ($status === 'expired') {
        $reply(false, 'This payment has expired. Please create a new package purchase.', 410);
    }
    if ($status !== 'waiting') {
        $reply(false, 'This payment has already been processed.', 409);
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'upload_receipt') {
        $f = $_FILES['receipt'] ?? null;

        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $reply(false, 'Please choose a payment receipt first.');
        }

        try {
            $proofDataUri = sw_uploaded_image_to_data_uri($f, 900, 75, $maxBytes);
        } catch (RuntimeException $ex) {
            $reply(false, $ex->getMessage(), 422);
        }

        if (empty($draft['payment_id']) || !isset($pdo) || !($pdo instanceof PDO)) {
            $reply(false, 'Payment record was not found. Please recreate your package order.', 500);
        }

        try {
            $stmtUpd = $pdo->prepare("
                UPDATE sooma_wellness.payments
                   SET payment_proof       = :proof,
                       verification_status = 'waiting_verification',
                       uploaded_at         = CURRENT_TIMESTAMP
                 WHERE payment_id          = :pid
            ");
            $stmtUpd->execute([
                ':proof' => $proofDataUri,
                ':pid'   => (int) $draft['payment_id'],
            ]);
        } catch (Throwable $ex) {
            error_log('[packages-payment-proof] ' . $ex->getMessage());
            $reply(false, 'Could not save your receipt: ' . $ex->getMessage(), 500);
        }

        $orderCode    = (string) ($draft['order_code'] ?? 'PKG');
        $originalName = mb_substr_safe((string) ($f['name'] ?? ''), 120);
        if ($originalName === '') {
            $originalName = 'Payment-Receipt-' . $orderCode . '.jpg';
        }

        $status                = 'verifying';
        $draft['status']       = 'verifying';
        $draft['receipt_file'] = $proofDataUri;
        $draft['receipt_orig'] = $originalName;

        $reply(true, 'Receipt uploaded. Waiting for admin verification before your package becomes active.');
    }

    $reply(false, 'Invalid request.', 400);
}

$total       = (int) ($draft['total_payment'] ?? 0);
$orderId     = (string) ($draft['order_code'] ?? 'PKG-0001');
$remaining   = $status === 'waiting' ? max(0, (int) $draft['expires_at'] - time()) : 0;
$canUpload   = $status === 'waiting';
$receiptFile = (string) ($draft['receipt_file'] ?? '');

if ($receiptFile !== '') {
    $receiptLabel = (string) ($draft['receipt_orig'] ?? '');
    if ($receiptLabel === '') {
        $receiptLabel = str_starts_with($receiptFile, 'data:image/')
            ? 'Payment-Receipt-' . $orderId . '.jpg'
            : basename($receiptFile);
    }
    $receipt = ['original' => $receiptLabel];
} else {
    $receipt = null;
}

$flash = $_SESSION['pay_flash'] ?? null;
unset($_SESSION['pay_flash']);

$backUrl = $purchasePageUrl;
if (!empty($draft['pkg_slug'])) {
    $backUrl .= '?pkg=' . urlencode((string) $draft['pkg_slug']);
}

$steps = [
    ['Open Mobile Banking / ATM',     'Open your Mobile Banking app (BCA Mobile, myBCA, Mandiri, BRI, etc.) or visit the nearest ATM and select the Transfer menu'],
    ['Select Destination Bank',       'Choose Bank BCA as the destination bank and enter the account number ' . $bankAccount . ' (' . $bankHolder . ')'],
    ['Enter Exact Transfer Amount',   'Input the exact Total Payment amount displayed on this page so your payment can be verified quickly'],
    ['Confirm & Complete Transfer',   'Double-check the recipient name (' . $bankHolder . ') and amount, then authorize the transfer with your PIN'],
    ['Upload Transfer Receipt',       'Save or screenshot the transfer receipt, then upload it; your package will be activated after admin verification'],
];

$pageTitle  = 'Package Payment';
$bodyClass  = 'payment-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';

require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/packages-payment.css">
<noscript>
    <style>
        .pay-submit[hidden] {
            display: inline-flex !important
        }
    </style>
</noscript>

<main class="pay-page" id="pay-root"
    data-status="<?= e($status) ?>" data-remaining="<?= $remaining ?>"
    data-max="<?= $maxBytes ?>" data-mode="bank">

    <a href="<?= e($backUrl) ?>" class="pay-back">
        <span class="pay-back__icon"><i class="ph ph-caret-left"></i></span>
        <span>Purchase Class Packages</span>
    </a>

    <h1 class="pay-title">Payment Gateway</h1>

    <div class="pay-layout">

        <section class="pay-left" aria-label="Payment details and receipt upload">
            <div class="pay-package-box pay-bank-box">
                <i class="ph ph-bank"></i>
                <h2>Bank Transfer</h2>
                <p>Transfer the exact amount to the bank account below and upload your receipt.</p>

                <div class="pay-bank-card">
                    <div class="pay-bank-card__main">
                        <h3 class="pay-bank-card__bank"><?= e($bankName) ?></h3>
                        <p class="pay-bank-card__label">Account Number</p>
                        <div class="pay-bank-card__number-row">
                            <span class="pay-bank-card__account" id="pay-bank-account" title="Click to copy"><?= e(preg_replace('/\s+/', '', $bankAccount)) ?></span>
                            <button type="button" id="pay-copy-account" class="pay-copy-btn">
                                <i class="ph ph-copy"></i>
                                <span id="pay-copy-label">Copy</span>
                            </button>
                        </div>
                    </div>
                    <div class="pay-bank-card__foot">
                        <span class="pay-bank-card__holder">Account Name</span>
                        <strong><?= e($bankHolder) ?></strong>
                    </div>
                </div>

                <form id="pay-upload-form" class="pay-upload pay-bank-upload" method="post" action=""
                    enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="action" value="upload_receipt">
                    <input type="file" id="pay-receipt" name="receipt" class="pay-file"
                        accept="image/jpeg,image/png,image/webp" <?= $canUpload ? '' : 'disabled' ?>>

                    <label for="pay-receipt" id="pay-upload-btn"
                        class="pay-upload-btn <?= $canUpload ? '' : 'is-disabled' ?>"
                        role="button" tabindex="<?= $canUpload ? '0' : '-1' ?>" aria-disabled="<?= $canUpload ? 'false' : 'true' ?>">
                        <i class="ph ph-upload-simple"></i><span>Upload Payment Receipt</span>
                    </label>

                    <div class="pay-preview" id="pay-preview" <?= $receipt ? '' : 'hidden' ?>>
                        <div class="pay-preview__thumb" id="pay-thumb"><i class="ph ph-file"></i></div>
                        <div class="pay-preview__meta">
                            <span class="pay-preview__name" id="pay-file-name"><?= e($receipt['original'] ?? '') ?></span>
                            <span class="pay-preview__size" id="pay-file-size"><?= $receipt ? 'Submitted' : '' ?></span>
                        </div>
                        <button type="button" class="pay-preview__remove" id="pay-remove" aria-label="Remove file" <?= $receipt ? 'hidden' : '' ?>>
                            <i class="ph ph-x"></i>
                        </button>
                    </div>

                    <button type="submit" class="pay-submit" id="pay-submit" hidden>
                        <i class="ph ph-paper-plane-tilt"></i><span>Submit Receipt</span>
                    </button>

                    <p class="pay-message <?= $flash ? 'is-' . e($flash['type']) : '' ?>" id="pay-message" role="status" aria-live="polite"><?= e($flash['message'] ?? '') ?></p>
                </form>
            </div>
        </section>

        <section class="pay-right" aria-label="Payment details">
            <div class="pay-badge-row">
                <span class="pay-badge pay-badge--<?= e($status) ?>" id="pay-badge">
                    <i class="pay-dot" aria-hidden="true"></i>
                    <span id="pay-badge-text"><?= e($statusLabels[$status] ?? 'Waiting Payment') ?></span>
                </span>
            </div>

            <div class="pay-card">
                <div class="pay-card__main">
                    <h2 class="pay-card__bank"><?= e($bankName) ?></h2>
                    <p class="pay-card__label">Total Payment</p>
                    <p class="pay-card__amount"><em>Rp</em><?= number_format($total, 0, '.', ',') ?></p>
                </div>
                <div class="pay-card__foot">
                    <span>Order ID</span>
                    <strong>#<?= e($orderId) ?></strong>
                </div>
            </div>

            <div class="pay-countdown">
                <span class="pay-countdown__label">Payment Countdown</span>
                <span class="pay-countdown__time" id="pay-countdown" aria-live="off">
                    <?php if (in_array($status, ['verifying', 'confirmed'], true)): ?>--:--<?php elseif ($status === 'expired'): ?>00:00<?php else: ?><?= sprintf('%02d:%02d', intdiv($remaining, 60), $remaining % 60) ?><?php endif; ?>
                </span>
            </div>

            <?php $isFinishEnabled = in_array($status, ['verifying', 'confirmed'], true); ?>

            <a class="pay-link <?= $isFinishEnabled ? '' : 'is-disabled' ?>"
                id="pay-done-link"
                href="<?= e($transactionUrl) ?>"
                aria-disabled="<?= $isFinishEnabled ? 'false' : 'true' ?>"
                <?= $isFinishEnabled ? '' : 'tabindex="-1"' ?>>
                <span>Finish Payment</span>
                <span class="pay-link__icon" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </a>
        </section>
    </div>

    <section class="pay-steps" aria-labelledby="pay-steps-title">
        <h2 id="pay-steps-title" class="pay-steps__title">Payment Procedures</h2>
        <ol class="pay-steps__list">
            <?php foreach ($steps as $i => [$title, $text]): ?>
                <li class="pay-step">
                    <span class="pay-step__num" aria-hidden="true"><?= $i + 1 ?></span>
                    <div>
                        <h3 class="pay-step__title"><?= e($title) ?></h3>
                        <p class="pay-step__text"><?= e($text) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</main>

<script>
(function() {
    var copyBtn = document.getElementById('pay-copy-account');
    var accountEl = document.getElementById('pay-bank-account');
    var copyLabel = document.getElementById('pay-copy-label');

    function copyAccountNumber() {
        if (!accountEl) return;
        var acct = accountEl.textContent.trim();

        function showCopied() {
            if (copyBtn) copyBtn.classList.add('is-copied');
            if (copyLabel) copyLabel.textContent = 'Copied!';
            setTimeout(function() {
                if (copyBtn) copyBtn.classList.remove('is-copied');
                if (copyLabel) copyLabel.textContent = 'Copy';
            }, 1800);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(acct).then(showCopied).catch(fallbackCopy);
        } else {
            fallbackCopy();
        }

        function fallbackCopy() {
            var ta = document.createElement('textarea');
            ta.value = acct;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showCopied();
            } catch (e) {}
            document.body.removeChild(ta);
        }
    }

    if (copyBtn) copyBtn.addEventListener('click', copyAccountNumber);
    if (accountEl) accountEl.addEventListener('click', copyAccountNumber);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/payment.js"></script>