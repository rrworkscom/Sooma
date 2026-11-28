<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timetableUrl   = 'timetable.php';
$packagesUrl    = 'packages.php';
$transactionUrl = '../customer/profile-transaction.php?tab=pending';
$bankName       = 'BCA Sooma Wellness';
$bankAccount    = '5105555005';
$bankHolder     = 'RR VANESSA VIOLETA';
$maxBytes       = 5 * 1024 * 1024;

require __DIR__ . '/../includes/booking-service.php';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function ppr_detect_type(string $path): ?array
{
    $h = @file_get_contents($path, false, null, 0, 12);
    if ($h === false || strlen($h) < 4) {
        return null;
    }
    if (strncmp($h, "\xFF\xD8\xFF", 3) === 0) {
        return ['ext' => 'jpg', 'mime' => 'image/jpeg'];
    }
    if (strncmp($h, "\x89PNG", 4) === 0) {
        return ['ext' => 'png', 'mime' => 'image/png'];
    }
    if (strncmp($h, 'RIFF', 4) === 0 && substr($h, 8, 4) === 'WEBP') {
        return ['ext' => 'webp', 'mime' => 'image/webp'];
    }
    return null;
}

function ppr_normalize_program(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '' || $v === 'all' || $v === 'all classes' || str_contains($v, 'all class')) {
        return 'all';
    }
    if (str_contains($v, 'pilates') || str_contains($v, 'reformer') || str_contains($v, 'mat')) {
        return 'reformer_pilates';
    }
    if (str_contains($v, 'yoga')) {
        return 'regular_yoga';
    }
    return $v;
}

function ppr_normalize_class_type(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '') {
        return '';
    }
    if (
        str_contains($v, 'private group') ||
        str_contains($v, 'private_group') ||
        str_contains($v, 'private-group') ||
        str_contains($v, 'semi private') ||
        str_contains($v, 'semi-private')
    ) {
        return 'private_group';
    }
    if (str_contains($v, 'private')) {
        return 'private';
    }
    if (str_contains($v, 'group')) {
        return 'group';
    }
    if ($v === 'all' || $v === 'all types' || str_contains($v, 'all type')) {
        return 'all';
    }
    return '';
}

function ppr_package_program_key(array $pkg): string
{
    foreach (['category', 'class_category', 'class_name', 'name', 'package_name'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = ppr_normalize_program((string) $pkg[$field]);
            if (in_array($norm, ['all', 'reformer_pilates', 'regular_yoga'], true)) {
                return $norm;
            }
        }
    }
    return 'all';
}

function ppr_package_type_key(array $pkg): string
{
    foreach (['pkg_class_type', 'class_type', 'package_type'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = ppr_normalize_class_type((string) $pkg[$field]);
            if ($norm !== '') {
                return $norm;
            }
        }
    }

    $combined = trim(implode(' ', array_filter([
        (string) ($pkg['name'] ?? ''),
        (string) ($pkg['package_name'] ?? ''),
        (string) ($pkg['category'] ?? ''),
        (string) ($pkg['class_category'] ?? ''),
        (string) ($pkg['type'] ?? ''),
        (string) ($pkg['class_name'] ?? ''),
    ])));

    $norm = ppr_normalize_class_type($combined);
    return $norm !== '' ? $norm : 'group';
}

function ppr_is_package_compatible(
    array $pkg,
    string $targetCategory,
    string $targetClassName = '',
    int $attendeeCount = 1
): bool {
    $pkgProg    = ppr_package_program_key($pkg);
    $targetProg = ppr_normalize_program($targetCategory);
    if (!in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true)) {
        $targetProg = ppr_normalize_program($targetClassName);
    }

    $progCompatible = ($pkgProg === 'all' || !in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true))
        ? true
        : ($pkgProg === $targetProg);

    if (!$progCompatible) {
        return false;
    }

    $pkgType    = ppr_package_type_key($pkg);
    $targetType = $attendeeCount > 1 ? 'private_group' : 'private';

    return $pkgType === 'all' || $pkgType === $targetType;
}

function ppr_is_private_booking(array $booking): bool
{
    if (filter_var($booking['has_private_request'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return true;
    }

    $classType = strtolower((string) ($booking['class_type'] ?? ''));
    $className = strtolower((string) ($booking['class_name'] ?? ''));
    $code      = strtoupper(ltrim((string) ($booking['booking_code'] ?? ''), '#'));
    $reqId     = trim((string) ($booking['private_request_id'] ?? ''));

    return str_contains($classType, 'private')
        || str_contains($className, 'private')
        || str_starts_with($code, 'REQ')
        || $reqId !== '';
}

function ppr_format_short_date(?string $raw): string
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
               b.payment_type_choice as raw_booking_payment_type,
               case
                   when rc_match.request_id is not null
                        and b.payment_id is null
                        and lower(coalesce(
                            nullif(trim(to_jsonb(rc_match)->>'payment_method'), ''),
                            nullif(trim(to_jsonb(rc_match)->>'payment_type_choice'), ''),
                            ''
                        )) like '%package%'
                   then 'package'
                   when lower(coalesce(
                       nullif(trim(b.payment_type_choice), ''),
                       nullif(trim(to_jsonb(rc_match)->>'payment_method'), ''),
                       'dropin'
                   )) like '%package%'
                   then 'package'
                   else 'dropin'
               end as payment_method,
               b.payment_id,
               coalesce(
                   b.user_package_id,
                   nullif(to_jsonb(rc_match)->>'user_package_id', '')::int,
                   nullif(to_jsonb(rc_match)->>'customer_package_id', '')::int,
                   nullif(to_jsonb(rc_match)->>'package_id', '')::int
               ) as user_package_id,
               b.expires_at,
               coalesce(
                   nullif(to_jsonb(b)->>'private_request_id', ''),
                   nullif(to_jsonb(b)->>'request_id', ''),
                   rc_match.request_id::text,
                   ''
               ) as private_request_id,
               (rc_match.request_id is not null) as has_private_request,
               c.customer_id,
               c.user_id,
               p.payment_proof,
               coalesce(mc.class_name, 'Private Session') as class_name,
               coalesce(cs.class_type, nullif(trim(to_jsonb(rc_match)->>'class_type'), ''), 'Private') as class_type,
               greatest(1, (select count(*) from sooma_wellness.booking_attendees ba where ba.booking_id = b.booking_id))::int as attendee_count,
               coalesce(
                   nullif(p.gross_amount, 0)::int,
                   (
                       greatest(1, (select count(*) from sooma_wellness.booking_attendees ba where ba.booking_id = b.booking_id))
                       * coalesce(
                           nullif(mc.price, 0),
                           case when lower(coalesce(mc.class_name, '')) like '%yoga%' then 65000 else 180000 end
                       )::int
                   )
               ) as total_payment,
               case
                   when b.expires_at is not null
                       then greatest(0, ceil(extract(epoch from (b.expires_at - clock_timestamp()))))::int
                   else 1800
               end as remaining
          from sooma_wellness.bookings b
          join sooma_wellness.customers c on c.customer_id = b.customer_id
          left join sooma_wellness.class_schedules cs on cs.schedule_id = coalesce(b.reschedule_schedule_id, b.schedule_id)
          left join sooma_wellness.master_classes mc on mc.class_id = cs.class_id
          left join sooma_wellness.payments p on p.payment_id = b.payment_id
          left join lateral (
              select rc.*
                from sooma_wellness.request_classes rc
               where rc.customer_id = b.customer_id
                 and (
                     to_jsonb(rc)->>'booking_id' = b.booking_id::text
                     or (cs.schedule_id is not null and to_jsonb(rc)->>'schedule_id' = cs.schedule_id::text)
                     or rc.request_id::text = coalesce(
                         nullif(to_jsonb(b)->>'private_request_id', ''),
                         nullif(to_jsonb(b)->>'request_id', ''),
                         '-1'
                     )
                     or (
                         cs.schedule_id is not null
                         and rc.class_id = cs.class_id
                         and rc.preferred_date = cs.schedule_date
                         and rc.preferred_time = cs.start_time
                     )
                 )
               order by
                 case
                     when to_jsonb(rc)->>'booking_id' = b.booking_id::text then 0
                     when rc.request_id::text = coalesce(nullif(to_jsonb(b)->>'private_request_id', ''), nullif(to_jsonb(b)->>'request_id', ''), '-1') then 1
                     when cs.schedule_id is not null and to_jsonb(rc)->>'schedule_id' = cs.schedule_id::text then 2
                     else 3
                 end,
                 case when lower(coalesce(rc.status, '')) in ('accepted', 'approved', 'confirmed', 'waiting_payment') then 0 else 1 end,
                 rc.request_id desc
               limit 1
          ) rc_match on true
         where b.booking_id = :id
         limit 1
    ");
    $st->execute([':id' => $dbId]);
    $booking = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($booking && (int) $booking['user_id'] !== $userId) {
        $booking = null;
    }

    if ($booking && !ppr_is_private_booking($booking)) {
        header('Location: payment.php?id=' . $dbId);
        exit;
    }

    // Sinkronkan payment_type_choice & user_package_id ke tabel bookings jika berasal dari private request package
    if (
        $booking &&
        $booking['payment_method'] === 'package' &&
        (
            strtolower(trim((string) ($booking['raw_booking_payment_type'] ?? ''))) !== 'package' ||
            (!empty($booking['user_package_id']) && empty($booking['raw_user_package_id']))
        )
    ) {
        try {
            $syncBook = $pdo->prepare("
                update sooma_wellness.bookings
                   set payment_type_choice = 'package',
                       user_package_id     = coalesce(user_package_id, :upid),
                       updated_at          = clock_timestamp()
                 where booking_id = :id
            ");
            $syncBook->execute([
                ':upid' => !empty($booking['user_package_id']) ? (int) $booking['user_package_id'] : null,
                ':id'   => $dbId,
            ]);
        } catch (Throwable $ignore) {
        }
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
                   coalesce(
                       nullif(trim(to_jsonb(pkg)->>'class_type'), ''),
                       nullif(trim(to_jsonb(pkg)->>'package_type'), ''),
                       ''
                   ) as pkg_class_type,
                   coalesce(mc.class_name, 'All Classes') as category,
                   up.purchased_at,
                   up.expired_at,
                   coalesce(up.redeem_code, '') as redeem_code
              from sooma_wellness.user_packages up
              join sooma_wellness.packages pkg on pkg.package_id = up.package_id
              left join sooma_wellness.master_classes mc on mc.class_id = pkg.class_id
             where up.customer_id = :cid
               and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
               and up.remaining_sessions > 0
               and (up.expired_at is null or up.expired_at > clock_timestamp())
             order by up.purchased_at asc, up.user_package_id asc
        ");
        $stPkgs->execute([':cid' => (int) $booking['customer_id']]);
        $userPackages = $stPkgs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($booking && $booking['status'] === 'waiting_payment' && $booking['expires_at'] !== null && (int) $booking['remaining'] === 0) {
        $pdo->prepare("
            update sooma_wellness.bookings
               set booking_status = 'expired',
                   updated_at = clock_timestamp()
             where booking_id = :id
               and booking_status = 'waiting_payment'
               and expires_at <= clock_timestamp()
        ")->execute([':id' => $dbId]);
        $booking['status'] = 'expired';
    }
} catch (Throwable $ex) {
    error_log('[payment-private-request] ' . $ex->getMessage());
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

    $reply = function (bool $ok, string $message, int $code = 200) use ($wantsJson, &$booking, $statusLabels, $dbId): void {
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
        header('Location: payment-private-request.php?id=' . $dbId);
        exit;
    };

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $reply(false, 'Your session has expired. Please refresh the page.', 400);
    }
    if ($booking['status'] === 'expired' || $booking['status'] === 'cancelled') {
        $reply(false, 'This payment has expired. Please submit a new private request.', 410);
    }
    if ($booking['status'] !== 'waiting_payment') {
        $reply(false, 'This private session booking has already been processed.', 409);
    }

    $action        = (string) ($_POST['action'] ?? '');
    $paymentMethod = (string) ($booking['payment_method'] ?? 'dropin');

    if ($action === 'payment_proof' && $paymentMethod === 'dropin') {
        $f = $_FILES['receipt'] ?? null;

        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $reply(false, 'Please choose a payment receipt first.');
        }
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > $maxBytes) {
            $reply(false, 'File is too large. Maximum size is 5 MB.');
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $reply(false, 'Upload failed. Please try again.', 500);
        }

        $detected = ppr_detect_type($f['tmp_name']);
        if ($detected === null) {
            $reply(false, 'Only JPG, PNG, or WEBP images are allowed.');
        }

        $binaryData = @file_get_contents($f['tmp_name']);
        if ($binaryData === false) {
            $reply(false, 'Could not read the uploaded file. Please try again.', 500);
        }

        $mime = $detected['mime'];

        if (function_exists('imagecreatefromstring')) {
            $srcImg = @imagecreatefromstring($binaryData);
            if ($srcImg !== false) {
                $origW  = imagesx($srcImg);
                $origH  = imagesy($srcImg);
                $maxDim = 900;

                if ($origW > $maxDim || $origH > $maxDim) {
                    $ratio  = min($maxDim / $origW, $maxDim / $origH);
                    $newW   = (int) round($origW * $ratio);
                    $newH   = (int) round($origH * $ratio);
                    $dstImg = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($srcImg);
                    $srcImg = $dstImg;
                }

                ob_start();
                imagejpeg($srcImg, null, 78);
                $binaryData = ob_get_clean();
                imagedestroy($srcImg);
                $mime = 'image/jpeg';
            }
        }

        $proofDataUri = 'data:' . $mime . ';base64,' . base64_encode($binaryData);
        $amount       = max(0, (int) $booking['total_payment']);
        $step         = 'init';

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
                       payment_type_choice = 'dropin',
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
            error_log("[payment-private-request upload][$step] " . $ex->getMessage());
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
                select b.booking_id, b.customer_id, b.booking_status, b.expires_at
                  from sooma_wellness.bookings b
                 where b.booking_id = :id
                 for update
            ");
            $stLockBook->execute([':id' => $dbId]);
            $lockedBook = $stLockBook->fetch(PDO::FETCH_ASSOC);

            if (
                !$lockedBook ||
                $lockedBook['booking_status'] !== 'waiting_payment' ||
                ($lockedBook['expires_at'] !== null && strtotime((string) $lockedBook['expires_at']) <= time())
            ) {
                $pdo->rollBack();
                $reply(false, 'This booking can no longer be confirmed. Please refresh the page.', 409);
            }

            $stLockPkgs = $pdo->prepare("
                select up.user_package_id,
                       up.remaining_sessions,
                       coalesce(pkg.package_name, 'Class Package') as name,
                       coalesce(
                           nullif(trim(to_jsonb(pkg)->>'variant_label'), ''),
                           coalesce(pkg.total_sessions, 1)::text || ' Sessions'
                       ) as type,
                       coalesce(
                           nullif(trim(to_jsonb(pkg)->>'class_type'), ''),
                           nullif(trim(to_jsonb(pkg)->>'package_type'), ''),
                           ''
                       ) as pkg_class_type,
                       coalesce(mc.class_name, 'All Classes') as category
                  from sooma_wellness.user_packages up
                  join sooma_wellness.packages pkg on pkg.package_id = up.package_id
                  left join sooma_wellness.master_classes mc on mc.class_id = pkg.class_id
                 where up.customer_id = :cid
                   and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
                   and up.remaining_sessions > 0
                   and (up.expired_at is null or up.expired_at > clock_timestamp())
                 order by up.purchased_at asc, up.user_package_id asc
                 for update of up
            ");
            $stLockPkgs->execute([':cid' => (int) $lockedBook['customer_id']]);
            $lockedPackages = $stLockPkgs->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $chosenPackage = null;
            if ($requestedUpId > 0) {
                foreach ($lockedPackages as $lp) {
                    if ((int) $lp['user_package_id'] === $requestedUpId) {
                        if (!ppr_is_package_compatible($lp, (string) $booking['class_type'], (string) $booking['class_name'], $requiredSessions)) {
                            $pdo->rollBack();
                            $reply(false, 'Selected package is not applicable for this private session.', 422);
                        }
                        if ((int) $lp['remaining_sessions'] < $requiredSessions) {
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
                    if (
                        ppr_is_package_compatible($lp, (string) $booking['class_type'], (string) $booking['class_name'], $requiredSessions) &&
                        (int) $lp['remaining_sessions'] >= $requiredSessions
                    ) {
                        $chosenPackage = $lp;
                        break;
                    }
                }
                if (!$chosenPackage) {
                    $pdo->rollBack();
                    $reply(false, 'You do not have an active compatible private package with enough remaining sessions.', 422);
                }
            }

            $targetUpId = (int) $chosenPackage['user_package_id'];

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

            $stmt = $pdo->prepare("
                update sooma_wellness.bookings b
                   set booking_status      = 'confirmed',
                       payment_type_choice = 'package',
                       user_package_id     = :upid,
                       updated_at          = clock_timestamp()
                  from sooma_wellness.customers c
                 where b.customer_id    = c.customer_id
                   and b.booking_id     = :id
                   and c.user_id        = :user_id
                   and b.booking_status = 'waiting_payment'
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
            $reply(true, 'Private session booking confirmed using ' . $chosenPackage['name'] . '.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[payment-private-request package] ' . $ex->getMessage());
            $reply(false, 'Could not confirm this package booking right now: ' . $ex->getMessage(), 500);
        }
    }

    $reply(false, 'Invalid request.', 400);
}

$status      = bd_ui_status((string) $booking['status']);
$isPackage   = $booking['payment_method'] === 'package';
$total       = $isPackage ? 0 : (int) $booking['total_payment'];
$bookingId   = (string) $booking['booking_code'];
$remaining   = $status === 'waiting' ? (int) $booking['remaining'] : 0;
$attendeeCnt = max(1, (int) $booking['attendee_count']);
$canUpload   = $status === 'waiting';
$receiptFile = (string) ($booking['payment_proof'] ?? '');

$compatiblePayPackages = [];
$selectablePackageCnt  = 0;
$earliestPayPackageId  = 0;

if ($isPackage) {
    foreach ($userPackages as $up) {
        $isCompat = ppr_is_package_compatible(
            $up,
            (string) $booking['class_type'],
            (string) $booking['class_name'],
            $attendeeCnt
        );
        if (!$isCompat) {
            continue;
        }
        $hasEnough = (int) $up['remaining'] >= $attendeeCnt;
        $up['is_compatible'] = true;
        $up['has_enough']    = $hasEnough;
        $compatiblePayPackages[] = $up;

        if ($hasEnough) {
            $selectablePackageCnt++;
            if ($earliestPayPackageId === 0) {
                $earliestPayPackageId = (int) $up['user_package_id'];
            }
        }
    }
    if ($earliestPayPackageId === 0 && !empty($compatiblePayPackages)) {
        $earliestPayPackageId = (int) $compatiblePayPackages[0]['user_package_id'];
    }
}

$preselectedUpId      = $savedPreselectedUpId;
$hasValidPreselection = false;
foreach ($compatiblePayPackages as $up) {
    if ((int) $up['user_package_id'] === $preselectedUpId && !empty($up['is_compatible']) && !empty($up['has_enough'])) {
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

$formAction       = 'payment-private-request.php?id=' . $dbId;
$privateTypeLabel = ($attendeeCnt > 1 ? 'Private Group ' : 'Private ') . trim((string) $booking['class_name']);

$steps = [
    ['Open Mobile Banking / ATM',   'Open your Mobile Banking app (BCA Mobile, myBCA, Mandiri, BRI, etc.) or visit the nearest ATM and select the Transfer menu'],
    ['Select Destination Bank',     'Choose Bank BCA as the destination bank and enter the account number ' . $bankAccount . ' (' . $bankHolder . ')'],
    ['Enter Exact Transfer Amount', 'Input the exact Total Payment amount displayed on this page so your payment can be verified quickly'],
    ['Confirm & Complete Transfer', 'Double-check the recipient name (' . $bankHolder . ') and amount, then authorize the transfer with your PIN'],
    ['Upload Transfer Receipt',     'Save or screenshot the transfer receipt, then upload it to the Upload Payment Receipt section on this page'],
];

$pageTitle  = 'Payment Gateway';
$bodyClass  = 'payment-page payment-private-request-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';

require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/payment-private-request.css">
<noscript>
    <style>
        .pay-submit[hidden] {
            display: inline-flex !important
        }
    </style>
</noscript>

<main class="pay-page" id="pay-root"
    data-status="<?= e($status) ?>" data-remaining="<?= $remaining ?>"
    data-max="<?= $maxBytes ?>" data-mode="<?= $isPackage ? 'package' : 'bank' ?>"
    data-booking-id="<?= $dbId ?>">

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

                        <?php if ($canUpload): ?>
                            <div class="pay-pkg-selector" id="pay-pkg-selector" data-single="<?= count($compatiblePayPackages) <= 1 ? '1' : '0' ?>">
                                <div class="pay-pkg-selector__head">
                                    <span class="pay-pkg-selector__title">Select Package</span>
                                    <?php if ($selectablePackageCnt > 1): ?>
                                        <button type="button" class="pay-pkg-selector__clear" id="pay-pkg-clear" <?= $preselectedUpId > 0 ? '' : 'hidden' ?>>
                                            Use Earliest (Default)
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($compatiblePayPackages)):
                                    $hasMultiplePackages = count($compatiblePayPackages) > 1;
                                ?>
                                    <?php foreach ($compatiblePayPackages as $up):
                                        $upId       = (int) $up['user_package_id'];
                                        $isCompat   = !empty($up['is_compatible']);
                                        $hasEnough  = !empty($up['has_enough']);
                                        $canPick    = $isCompat && $hasEnough;
                                        $isEarliest = ($upId === $earliestPayPackageId && $canPick);
                                        $isChecked  = ($canPick && ($upId === $preselectedUpId || ($selectablePackageCnt === 1 && $preselectedUpId === 0)));
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
                                                        <?php if ($isEarliest && $selectablePackageCnt > 1): ?>
                                                            <span class="pay-pkg-option__tag">Earliest Purchased</span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="pay-pkg-option__meta">
                                                        <?= e($up['type']) ?> &bull; Bought <?= e(ppr_format_short_date($up['purchased_at'] ?? null)) ?> &bull; Exp <?= e(ppr_format_short_date($up['expired_at'] ?? null)) ?>
                                                    </span>
                                                    <?php if (!$hasEnough): ?>
                                                        <span class="pay-pkg-option__meta pay-pkg-option__meta--error">
                                                            Not enough remaining sessions (<?= $attendeeCnt ?> needed)
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="pay-pkg-option__badge">
                                                    <?= (int) $up['remaining'] ?> / <?= (int) $up['total'] ?><br>Sessions
                                                </span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="pay-pkg-empty">
                                        <p class="pay-pkg-empty__title">No compatible package available</p>
                                        <p class="pay-pkg-empty__desc">
                                            You don't have an active package that matches <strong><?= e($privateTypeLabel) ?></strong>.
                                        </p>
                                        <a href="<?= e($packagesUrl) ?>" class="pay-pkg-empty__cta">Buy Packages</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php $canConfirmPackage = $canUpload && $selectablePackageCnt > 0; ?>
                        <button type="submit" class="pay-upload-btn" <?= $canConfirmPackage ? '' : 'disabled' ?>>
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

<?php require __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/payment-private-request.js"></script>