<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$bookingUrl     = 'booking-detail.php';
$timetableUrl   = 'timetable.php';
$transactionUrl = '../customer/profile-transaction.php?tab=pending';
$bankName       = 'BCA Sooma Wellness';
$bankAccount    = '5105555005';
$bankHolder     = 'RR VANESSA VIOLETA';
$maxBytes       = 5 * 1024 * 1024;

require __DIR__ . '/../includes/booking-service.php';

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

function pay_normalize_category(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '' || $v === 'all' || $v === 'all classes' || str_contains($v, 'all class')) {
        return 'all';
    }
    if (str_contains($v, 'pilates') || str_contains($v, 'reformer')) {
        return 'pilates';
    }
    if (str_contains($v, 'yoga')) {
        return 'yoga';
    }
    return 'unknown';
}

function pay_normalize_session_scope(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '' || str_contains($v, 'all')) {
        return 'all';
    }
    if (str_contains($v, 'private group') || str_contains($v, 'semi private') || str_contains($v, 'semi-private')) {
        return 'private_group';
    }
    if (str_contains($v, 'private')) {
        return 'private';
    }
    if (str_contains($v, 'group')) {
        return 'group';
    }
    return 'all';
}

function pay_is_package_compatible(array $pkg, string $targetCategory, string $targetClassName = ''): bool
{
    $pkgCat = 'all';
    foreach (['category', 'class_category', 'class_name', 'name'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = pay_normalize_category((string) $pkg[$field]);
            if (in_array($norm, ['all', 'pilates', 'yoga'], true)) {
                $pkgCat = $norm;
                break;
            }
        }
    }

    $targetCat = pay_normalize_category($targetCategory);
    if (!in_array($targetCat, ['pilates', 'yoga'], true)) {
        $targetCat = pay_normalize_category($targetClassName);
    }

    if ($pkgCat !== 'all' && in_array($targetCat, ['pilates', 'yoga'], true) && $pkgCat !== $targetCat) {
        return false;
    }

    $pkgScope = 'all';
    foreach (['name', 'category', 'type'] as $field) {
        if (!empty($pkg[$field])) {
            $scope = pay_normalize_session_scope((string) $pkg[$field]);
            if ($scope !== 'all') {
                $pkgScope = $scope;
                break;
            }
        }
    }

    $targetScope = pay_normalize_session_scope($targetCategory . ' ' . $targetClassName);

    if ($pkgScope !== 'all' && $targetScope !== 'all' && $pkgScope !== $targetScope) {
        return false;
    }

    return true;
}

function pay_format_short_date(?string $raw): string
{
    if (!$raw) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($raw))->format('j M Y');
    } catch (Throwable $e) {
        return substr($raw, 0, 10);
    }
}

$statusLabels = [
    'waiting'   => 'Waiting Payment',
    'verifying' => 'Waiting Verification',
    'expired'   => 'Payment Expired',
    'confirmed' => 'Booking Confirmed',
];

$sessionUser = $_SESSION['user'] ?? [];
$userId      = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
$draft       = $_SESSION['booking_draft'] ?? [];
$dbId        = (int) ($_GET['id'] ?? $_GET['booking_id'] ?? $draft['db_id'] ?? $draft['id'] ?? 0);
$isPrivateQs = (($_GET['type'] ?? '') === 'private');

if ($dbId <= 0 || $userId <= 0) {
    header('Location: ' . $timetableUrl);
    exit;
}

$userPackages = [];

try {
    $pdo = bd_pdo();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('set search_path to sooma_wellness, public');

    $st = $pdo->prepare("
        select b.booking_id as id,
               coalesce(b.booking_code, 'BK-' || lpad(b.booking_id::text, 4, '0')) as booking_code,
               b.booking_status as status,
               coalesce(nullif(trim(b.payment_type_choice), ''), 'dropin') as payment_method,
               b.payment_id,
               b.user_package_id,
               coalesce(b.expires_at, b.booked_at + interval '30 minutes') as expires_at,
               c.customer_id,
               c.user_id,
               p.payment_proof,
               coalesce(mc.class_name, '') as class_name,
               coalesce(cs.class_type, '') as class_type,
               greatest(
                   1,
                   (select count(*) from sooma_wellness.booking_attendees ba where ba.booking_id = b.booking_id)
               )::int as attendee_count,
               (
                   greatest(
                       1,
                       (select count(*) from sooma_wellness.booking_attendees ba where ba.booking_id = b.booking_id)
                   ) * coalesce(mc.price, 120000)::int
               ) as total_payment,
               greatest(
                   0,
                   ceil(extract(epoch from (coalesce(b.expires_at, b.booked_at + interval '30 minutes') - clock_timestamp())))
               )::int as remaining
          from sooma_wellness.bookings b
          join sooma_wellness.customers c on c.customer_id = b.customer_id
          left join sooma_wellness.class_schedules cs on cs.schedule_id = coalesce(b.reschedule_schedule_id, b.schedule_id)
          left join sooma_wellness.master_classes mc on mc.class_id = cs.class_id
          left join sooma_wellness.payments p on p.payment_id = b.payment_id
         where b.booking_id = :id
         limit 1
    ");
    $st->execute([':id' => $dbId]);
    $booking = $st->fetch() ?: null;

    if ($booking && (int) $booking['user_id'] !== $userId) {
        $booking = null;
    }

    if ($booking && $booking['payment_method'] === 'package') {
        $stPkgs = $pdo->prepare("
            select up.user_package_id,
                   up.package_id,
                   up.remaining_sessions as remaining,
                   coalesce(pkg.total_sessions, up.remaining_sessions, 1) as total,
                   coalesce(pkg.package_name, 'Class Package') as name,
                   coalesce(
                       nullif(trim(to_jsonb(pkg)->>'variant_label'), ''),
                       coalesce(pkg.total_sessions, 1)::text || ' Sessions'
                   ) as type,
                   coalesce(mc.class_name, 'All Classes') as category,
                   up.purchased_at,
                   up.expired_at,
                   coalesce(up.redeem_code, '') as redeem_code
              from sooma_wellness.user_packages up
              join sooma_wellness.packages pkg on pkg.package_id = up.package_id
              left join sooma_wellness.master_classes mc on mc.class_id = pkg.class_id
             where up.customer_id = :cid
               and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
               and (up.remaining_sessions > 0 or up.user_package_id = :held_upid)
               and (up.expired_at is null or up.expired_at > clock_timestamp())
             order by up.purchased_at asc, up.user_package_id asc
        ");
        $stPkgs->execute([
            ':cid'       => (int) $booking['customer_id'],
            ':held_upid' => (int) ($booking['user_package_id'] ?? 0),
        ]);
        $userPackages = $stPkgs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $appliedVoucher = null;
    if ($booking && $booking['payment_method'] === 'dropin') {
        try {
            $pdo->exec("
                create table if not exists sooma_wellness.booking_vouchers (
                    booking_id      int primary key,
                    voucher_code    varchar(32) not null,
                    discount_amount int not null default 0,
                    final_total     int not null,
                    created_at      timestamptz not null default clock_timestamp()
                )
            ");
            $stVoucher = $pdo->prepare("
                select voucher_code, discount_amount, final_total
                  from sooma_wellness.booking_vouchers
                 where booking_id = :id
                 limit 1
            ");
            $stVoucher->execute([':id' => $dbId]);
            $vRow = $stVoucher->fetch(PDO::FETCH_ASSOC);
            if ($vRow) {
                $booking['total_payment'] = (int) $vRow['final_total'];
                $appliedVoucher = $vRow;
            } elseif (!empty($_SESSION['booking_vouchers'][$dbId])) {
                $vSess = $_SESSION['booking_vouchers'][$dbId];
                $booking['total_payment'] = (int) $vSess['final_total'];
                $appliedVoucher = $vSess;
            }
        } catch (Throwable $ignore) {
            if (!empty($_SESSION['booking_vouchers'][$dbId])) {
                $vSess = $_SESSION['booking_vouchers'][$dbId];
                $booking['total_payment'] = (int) $vSess['final_total'];
                $appliedVoucher = $vSess;
            }
        }
    }

    if ($booking && $booking['status'] === 'waiting_payment' && $booking['expires_at'] !== null && (int) $booking['remaining'] === 0) {
        $pdo->prepare("
            update sooma_wellness.bookings
               set booking_status = 'expired',
                   updated_at = clock_timestamp()
             where booking_id = :id
               and booking_status = 'waiting_payment'
               and coalesce(expires_at, booked_at + interval '30 minutes') <= clock_timestamp()
        ")->execute([':id' => $dbId]);
        $booking['status'] = 'expired';
    }
} catch (Throwable $ex) {
    error_log('[payment] ' . $ex->getMessage());
    http_response_code(503);
    exit('Payment error: ' . htmlspecialchars($ex->getMessage()));
}

if (!$booking) {
    unset($_SESSION['booking_draft']);
    header('Location: ' . $timetableUrl);
    exit;
}

$savedPreselectedUpId = (int) (
    $booking['user_package_id']
    ?? $_SESSION['booking_selected_package'][$dbId]
    ?? $_SESSION['booking_draft']['selected_user_package_id']
    ?? 0
);
unset($_SESSION['booking_draft']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

    $reply = function (bool $ok, string $message, int $code = 200) use ($wantsJson, &$booking, $statusLabels, $dbId, $isPrivateQs): void {
        $status = bd_ui_status((string) $booking['status']);
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
        $redir = 'payment.php?id=' . $dbId . ($isPrivateQs ? '&type=private' : '');
        header('Location: ' . $redir);
        exit;
    };

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $reply(false, 'Your session has expired. Please refresh the page.', 400);
    }
    if ($booking['status'] === 'expired' || $booking['status'] === 'cancelled') {
        $reply(false, 'This payment has expired. Please create a new booking.', 410);
    }
    if ($booking['status'] !== 'waiting_payment') {
        $reply(false, 'This booking has already been processed.', 409);
    }

    $action        = (string) ($_POST['action'] ?? '');
    $paymentMethod = trim((string) ($booking['payment_method'] ?? ''));
    if ($paymentMethod === '') {
        $paymentMethod = 'dropin';
    }

    if ($action === 'payment_proof' && $paymentMethod !== 'package') {
        $f = $_FILES['receipt'] ?? null;

        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $reply(false, 'Please choose a payment receipt first.');
        }

        try {
            $proofDataUri = sw_uploaded_image_to_data_uri($f, 900, 75, $maxBytes);
        } catch (RuntimeException $ex) {
            $reply(false, $ex->getMessage(), 422);
        }

        $amount = max(0, (int) $booking['total_payment']);
        $step   = 'init';

        try {
            $pdo->beginTransaction();
            $existingPaymentId = (int) ($booking['payment_id'] ?? 0);

            if ($existingPaymentId > 0) {
                $step = 'update_payment';
                $upPay = $pdo->prepare("
                    update sooma_wellness.payments
                       set gross_amount = :amount,
                           payment_method = 'bank_transfer',
                           payment_proof = :proof,
                           verification_status = 'pending',
                           uploaded_at = clock_timestamp()
                     where payment_id = :pid
                ");
                $upPay->execute([
                    ':amount' => $amount,
                    ':proof'  => $proofDataUri,
                    ':pid'    => $existingPaymentId,
                ]);
                $paymentId = $existingPaymentId;
            } else {
                $step = 'insert_payment';
                $insPay = $pdo->prepare("
                    insert into sooma_wellness.payments (
                        gross_amount,
                        payment_method,
                        payment_proof,
                        verification_status,
                        uploaded_at
                    ) values (
                        :amount,
                        'bank_transfer',
                        :proof,
                        'pending',
                        clock_timestamp()
                    )
                    returning payment_id
                ");
                $insPay->execute([
                    ':amount' => $amount,
                    ':proof'  => $proofDataUri,
                ]);
                $paymentId = (int) $insPay->fetchColumn();
            }

            $step = 'update_booking';
            $st = $pdo->prepare("
                update sooma_wellness.bookings
                   set booking_status = 'waiting_verification',
                       payment_id = :payment_id,
                       updated_at = clock_timestamp()
                 where booking_id = :id
                   and booking_status = 'waiting_payment'
            ");
            $st->execute([
                ':payment_id' => $paymentId,
                ':id'         => $dbId,
            ]);

            if ($st->rowCount() !== 1) {
                $pdo->rollBack();
                $reply(false, 'This booking has expired or has already been processed. Please refresh the page.', 409);
            }

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[payment upload][$step] " . $ex->getMessage());
            $reply(false, "Could not save your receipt [$step]: " . $ex->getMessage(), 500);
        }

        $booking['status']        = 'waiting_verification';
        $booking['payment_proof'] = $proofDataUri;

        $reply(true, 'Receipt uploaded. Click Finish Payment to view your transaction.');
    }

    if ($action === 'confirm_package' && $paymentMethod === 'package') {
        $requiredSessions = max(1, (int) $booking['attendee_count']);
        $postedUpId       = (int) ($_POST['user_package_id'] ?? $_POST['package_id'] ?? 0);
        $fallbackUpId     = $savedPreselectedUpId;
        $requestedUpId    = $postedUpId > 0 ? $postedUpId : $fallbackUpId;

        try {
            $pdo->beginTransaction();

            $stLockBook = $pdo->prepare("
                select b.booking_id, b.customer_id, b.booking_status, b.expires_at, b.user_package_id
                  from sooma_wellness.bookings b
                 where b.booking_id = :id
                 for update
            ");
            $stLockBook->execute([':id' => $dbId]);
            $lockedBook = $stLockBook->fetch(PDO::FETCH_ASSOC);

            if (!$lockedBook || $lockedBook['booking_status'] !== 'waiting_payment' || ($lockedBook['expires_at'] !== null && strtotime((string) $lockedBook['expires_at']) <= time())) {
                $pdo->rollBack();
                $reply(false, 'This booking can no longer be confirmed. Please refresh the page.', 409);
            }

            $alreadyDeductedUpId = (int) ($lockedBook['user_package_id'] ?? 0);

            $stLockPkgs = $pdo->prepare("
                select up.user_package_id,
                       up.remaining_sessions,
                       coalesce(pkg.package_name, 'Class Package') as name,
                       coalesce(
                           nullif(trim(to_jsonb(pkg)->>'variant_label'), ''),
                           coalesce(pkg.total_sessions, 1)::text || ' Sessions'
                       ) as type,
                       coalesce(mc.class_name, 'All Classes') as category
                  from sooma_wellness.user_packages up
                  join sooma_wellness.packages pkg on pkg.package_id = up.package_id
                  left join sooma_wellness.master_classes mc on mc.class_id = pkg.class_id
                 where up.customer_id = :cid
                   and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
                   and (up.remaining_sessions > 0 or up.user_package_id = :held_upid)
                   and (up.expired_at is null or up.expired_at > clock_timestamp())
                 order by up.purchased_at asc, up.user_package_id asc
                 for update of up
            ");
            $stLockPkgs->execute([
                ':cid'       => (int) $lockedBook['customer_id'],
                ':held_upid' => $alreadyDeductedUpId,
            ]);
            $lockedPackages = $stLockPkgs->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $chosenPackage = null;
            if ($requestedUpId > 0) {
                foreach ($lockedPackages as $lp) {
                    if ((int) $lp['user_package_id'] === $requestedUpId) {
                        if (!pay_is_package_compatible($lp, (string) $booking['class_type'], (string) $booking['class_name'])) {
                            $pdo->rollBack();
                            $reply(false, 'Selected package is not applicable for this class.', 422);
                        }
                        $effectiveRem = (int) $lp['remaining_sessions'] + ($requestedUpId === $alreadyDeductedUpId ? $requiredSessions : 0);
                        if ($effectiveRem < $requiredSessions) {
                            $pdo->rollBack();
                            $reply(false, 'Selected package does not have enough remaining sessions.', 422);
                        }
                        $chosenPackage = $lp;
                        break;
                    }
                }
                if (!$chosenPackage) {
                    $pdo->rollBack();
                    $reply(false, 'Selected package is no longer available. Please choose another package.', 422);
                }
            } else {
                foreach ($lockedPackages as $lp) {
                    $lpId = (int) $lp['user_package_id'];
                    $effectiveRem = (int) $lp['remaining_sessions'] + ($lpId === $alreadyDeductedUpId ? $requiredSessions : 0);
                    if (
                        pay_is_package_compatible($lp, (string) $booking['class_type'], (string) $booking['class_name'])
                        && $effectiveRem >= $requiredSessions
                    ) {
                        $chosenPackage = $lp;
                        break;
                    }
                }
                if (!$chosenPackage) {
                    $pdo->rollBack();
                    $reply(false, 'You do not have an active compatible package with enough remaining sessions.', 422);
                }
            }

            $targetUpId = (int) $chosenPackage['user_package_id'];

            if ($alreadyDeductedUpId !== $targetUpId) {
                if ($alreadyDeductedUpId > 0) {
                    $pdo->prepare("
                        update sooma_wellness.user_packages
                           set remaining_sessions = remaining_sessions + :qty
                         where user_package_id    = :old_upid
                    ")->execute([
                        ':qty'      => $requiredSessions,
                        ':old_upid' => $alreadyDeductedUpId,
                    ]);
                }

                $stDeduct = $pdo->prepare("
                    update sooma_wellness.user_packages
                       set remaining_sessions = remaining_sessions - :qty
                     where user_package_id    = :upid
                       and remaining_sessions >= :qty
                ");
                $stDeduct->execute([
                    ':qty'  => $requiredSessions,
                    ':upid' => $targetUpId,
                ]);

                if ($stDeduct->rowCount() !== 1) {
                    $pdo->rollBack();
                    $reply(false, 'Failed to deduct package sessions. Please try again.', 409);
                }
            }

            $stmt = $pdo->prepare("
                update sooma_wellness.bookings b
                   set booking_status  = 'confirmed',
                       user_package_id = :upid,
                       updated_at      = clock_timestamp()
                  from sooma_wellness.customers c
                 where b.customer_id         = c.customer_id
                   and b.booking_id          = :id
                   and c.user_id             = :user_id
                   and b.payment_type_choice = 'package'
                   and b.booking_status      = 'waiting_payment'
            ");
            $stmt->execute([
                ':upid'    => $targetUpId,
                ':id'      => $dbId,
                ':user_id' => $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $reply(false, 'This booking can no longer be confirmed. Please refresh the page.', 409);
            }

            $pdo->commit();
            unset($_SESSION['booking_selected_package'][$dbId]);

            $booking['status']          = 'confirmed';
            $booking['user_package_id'] = $targetUpId;
            $reply(true, 'Booking confirmed using ' . $chosenPackage['name'] . '.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[payment package] ' . $ex->getMessage());
            $reply(false, 'Could not confirm this package booking right now: ' . $ex->getMessage(), 500);
        }
    }

    $reply(false, 'Invalid request.', 400);
}

$status      = bd_ui_status((string) $booking['status']);
$isPackage   = $booking['payment_method'] === 'package';
$total       = (int) $booking['total_payment'];
$bookingId   = (string) $booking['booking_code'];
$remaining   = $status === 'waiting' ? (int) $booking['remaining'] : 0;
$attendeeCnt = (int) $booking['attendee_count'];
$canUpload   = $status === 'waiting';
$receiptFile = (string) ($booking['payment_proof'] ?? '');

$compatiblePayPackages = [];
$earliestPayPackageId  = 0;
if ($isPackage) {
    foreach ($userPackages as $up) {
        $isCompat = pay_is_package_compatible($up, (string) $booking['class_type'], (string) $booking['class_name']);
        if (!$isCompat) {
            continue;
        }
        $up['is_compatible'] = true;
        $compatiblePayPackages[] = $up;
        $effRem = (int) $up['remaining'] + ((int) $up['user_package_id'] === $savedPreselectedUpId ? $attendeeCnt : 0);
        if ($earliestPayPackageId === 0 && $effRem >= $attendeeCnt) {
            $earliestPayPackageId = (int) $up['user_package_id'];
        }
    }
    if ($earliestPayPackageId === 0 && !empty($compatiblePayPackages)) {
        $earliestPayPackageId = (int) $compatiblePayPackages[0]['user_package_id'];
    }
}

$preselectedUpId = $savedPreselectedUpId;
$hasValidPreselection = false;
foreach ($compatiblePayPackages as $up) {
    if ((int) $up['user_package_id'] === $preselectedUpId && !empty($up['is_compatible'])) {
        $hasValidPreselection = true;
        break;
    }
}
if (!$hasValidPreselection) {
    $preselectedUpId = 0;
}

if ($receiptFile !== '') {
    $receiptLabel = str_starts_with($receiptFile, 'data:image/')
        ? 'Payment-Receipt-' . $bookingId . '.jpg'
        : basename($receiptFile);
    $receipt = ['original' => $receiptLabel];
} else {
    $receipt = null;
}

$flash = $_SESSION['pay_flash'] ?? null;
unset($_SESSION['pay_flash']);

$formAction = 'payment.php?id=' . $dbId . ($isPrivateQs ? '&type=private' : '');

$steps = [
    ['Open Mobile Banking / ATM',     'Open your Mobile Banking app (BCA Mobile, myBCA, Mandiri, BRI, etc.) or visit the nearest ATM and select the Transfer menu'],
    ['Select Destination Bank',       'Choose Bank BCA as the destination bank and enter the account number ' . $bankAccount . ' (' . $bankHolder . ')'],
    ['Enter Exact Transfer Amount',   'Input the exact Total Payment amount displayed on this page so your payment can be verified quickly'],
    ['Confirm & Complete Transfer',   'Double-check the recipient name (' . $bankHolder . ') and amount, then authorize the transfer with your PIN'],
    ['Upload Transfer Receipt',       'Save or screenshot the transfer receipt, then upload it to the Upload Payment Receipt section on this page'],
];

$pageTitle  = 'Payment Gateway';
$bodyClass  = 'payment-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';

require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/payment.css">
<noscript>
    <style>
        .pay-submit[hidden] {
            display: inline-flex !important
        }
    </style>
</noscript>

<main class="pay-page" id="pay-root"
    data-status="<?= e($status) ?>" data-remaining="<?= $remaining ?>"
    data-max="<?= $maxBytes ?>" data-mode="<?= $isPackage ? 'package' : 'bank' ?>">

    <h1 class="pay-title">Payment Gateway</h1>

    <div class="pay-layout">

        <section class="pay-left" aria-label="Payment details and receipt upload">
            <?php if ($isPackage): ?>
                <div class="pay-package-box">
                    <i class="ph ph-package"></i>
                    <h2>Pay with Package Plan</h2>
                    <p>
                        <?= $attendeeCnt ?> session(s) will be deducted from your package.
                    </p>
                    <form method="post" action="<?= e($formAction) ?>" id="pay-package-form">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="confirm_package">

                        <?php if (!empty($compatiblePayPackages) && $canUpload):
                            $hasMultiplePackages = count($compatiblePayPackages) > 1;
                        ?>
                            <div class="pay-pkg-selector" id="pay-pkg-selector" data-single="<?= $hasMultiplePackages ? '0' : '1' ?>">
                                <div class="pay-pkg-selector__head">
                                    <span class="pay-pkg-selector__title">Select Package</span>
                                    <?php if ($hasMultiplePackages): ?>
                                        <button type="button" class="pay-pkg-selector__clear" id="pay-pkg-clear" <?= $preselectedUpId > 0 ? '' : 'hidden' ?>>
                                            Use Earliest (Default)
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasMultiplePackages): ?>
                                    <button type="button" class="pay-pkg-selector__clear" id="pay-pkg-clear" <?= $preselectedUpId > 0 ? '' : 'hidden' ?>>
                                        Use Earliest Package
                                    </button>
                                <?php endif; ?>
                                <?php foreach ($compatiblePayPackages as $up):
                                    $upId       = (int) $up['user_package_id'];
                                    $isCompat   = !empty($up['is_compatible']);
                                    $effRem     = (int) $up['remaining'] + ($upId === $savedPreselectedUpId ? $attendeeCnt : 0);
                                    $hasEnough  = $effRem >= $attendeeCnt;
                                    $canPick    = $isCompat && $hasEnough;
                                    $isEarliest = ($upId === $earliestPayPackageId);
                                    $isChecked  = ($canPick && ($upId === $preselectedUpId || !$hasMultiplePackages));
                                ?>
                                    <label class="pay-pkg-option<?= !$canPick ? ' is-disabled' : '' ?>">
                                        <input type="radio"
                                            name="user_package_id"
                                            value="<?= $upId ?>"
                                            data-earliest="<?= $isEarliest ? '1' : '0' ?>"
                                            <?= !$canPick ? 'disabled' : '' ?>
                                            <?= $isChecked ? 'checked' : '' ?>>
                                        <span class="pay-pkg-option__card">
                                            <span>
                                                <span class="pay-pkg-option__name">
                                                    <?= e($up['name']) ?>
                                                    <?php if ($isEarliest && $hasMultiplePackages): ?>
                                                        <span class="pay-pkg-option__tag">Earliest Purchased</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="pay-pkg-option__meta">
                                                    <?= e($up['type']) ?> &bull; Bought <?= e(pay_format_short_date($up['purchased_at'] ?? null)) ?> &bull; Exp <?= e(pay_format_short_date($up['expired_at'] ?? null)) ?>
                                                </span>
                                                <?php if (!$isCompat): ?>
                                                    <span class="pay-pkg-option__meta pay-pkg-option__meta--error">Not applicable for this class</span>
                                                <?php elseif (!$hasEnough): ?>
                                                    <span class="pay-pkg-option__meta pay-pkg-option__meta--error">Not enough remaining sessions (<?= $attendeeCnt ?> needed)</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="pay-pkg-option__badge">
                                                <?= (int) $up['remaining'] ?> / <?= (int) $up['total'] ?><br>Sessions
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="pay-upload-btn" <?= $canUpload ? '' : 'disabled' ?>>
                            <i class="ph ph-check-circle"></i><span><?= $status === 'confirmed' ? 'Processed' : 'Confirm Booking' ?></span>
                        </button>
                    </form>
                    <p class="pay-message <?= $flash ? 'is-' . e($flash['type']) : '' ?>" id="pay-message" role="status" aria-live="polite"><?= e($flash['message'] ?? '') ?></p>
                </div>
            <?php else: ?>
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

                    <form id="pay-upload-form" class="pay-upload pay-bank-upload" method="post" action="<?= e($formAction) ?>"
                        enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="payment_proof">
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
            <?php endif; ?>
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
                    <h2 class="pay-card__bank"><?= e($isPackage ? 'Package Plan' : $bankName) ?></h2>
                    <p class="pay-card__label">Total Payment</p>
                    <p class="pay-card__amount"><em>Rp</em><?= number_format($total, 0, '.', ',') ?></p>
                </div>
                <div class="pay-card__foot">
                    <span>Booking ID</span>
                    <strong>#<?= e($bookingId) ?></strong>
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
                href="<?= e($status === 'confirmed' ? '../customer/profile-transaction.php?tab=history' : $transactionUrl) ?>"
                aria-disabled="<?= $isFinishEnabled ? 'false' : 'true' ?>"
                <?= $isFinishEnabled ? '' : 'tabindex="-1"' ?>>
                <span>Finish Payment</span>
                <span class="pay-link__icon" aria-hidden="true"><i class="ph ph-caret-right"></i></span>
            </a>
        </section>
    </div>

    <?php if (!$isPackage): ?>
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
    <?php endif; ?>
</main>

<script>
    (function() {
        var locked = false;

        function lockHistory() {
            if (!window.history || !window.history.pushState) return;
            window.history.pushState({
                paymentLocked: true
            }, '', window.location.href);
            locked = true;
        }

        lockHistory();
        ['pointerdown', 'keydown', 'touchstart'].forEach(function(evt) {
            window.addEventListener(evt, function once() {
                if (!locked) lockHistory();
            }, {
                once: true,
                passive: true
            });
        });

        window.addEventListener('popstate', function() {
            window.history.pushState({
                paymentLocked: true
            }, '', window.location.href);
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });

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

        var pkgForm = document.getElementById('pay-package-form');
        if (!pkgForm) return;
        var clearBtn = document.getElementById('pay-pkg-clear');
        var radios = Array.prototype.slice.call(pkgForm.querySelectorAll('input[name="user_package_id"]'));
        var isSinglePackage = radios.length <= 1;

        function syncPayPackageHighlight() {
            var checked = radios.find(function(r) {
                return r.checked && !r.disabled;
            });
            var earliest = radios.find(function(r) {
                    return !r.disabled && r.dataset.earliest === '1';
                }) ||
                radios.find(function(r) {
                    return !r.disabled;
                });

            radios.forEach(function(r) {
                var wrap = r.closest('.pay-pkg-option');
                if (wrap) {
                    wrap.classList.toggle('is-auto-default', !checked && r === earliest);
                }
            });
            if (clearBtn) {
                clearBtn.hidden = isSinglePackage || !checked;
            }
        }

        radios.forEach(function(r) {
            r.addEventListener('change', syncPayPackageHighlight);
        });

        if (clearBtn && !isSinglePackage) {
            clearBtn.addEventListener('click', function() {
                radios.forEach(function(r) {
                    r.checked = false;
                });
                syncPayPackageHighlight();
            });
        }

        syncPayPackageHighlight();
    })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/payment.js"></script>