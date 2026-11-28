<?php

declare(strict_types=1);

$headerType = 'customer';
$headerLogo = 'black';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../login-regist/connection.php';
require_once __DIR__ . '/../includes/sync-booking-statuses.php';

$sessionUser = $_SESSION['user'] ?? [];
$userId      = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: ../login-regist/login.php');
    exit;
}

$user_name   = $sessionUser['name']       ?? 'Member';
$user_avatar = $sessionUser['avatar_url'] ?? 'assets/images/users/emilia-bonnita.png';
$location    = ['name' => 'Sooma Wellness', 'address' => 'Jl Ciliwung no. 62, Blitar'];

try {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('set search_path to sooma_wellness, public');
    syncUserBookingStatuses($pdo, $userId);
} catch (Throwable $e) {
    error_log('[profile-transaction sync] ' . $e->getMessage());
}

$expireStmt = $pdo->prepare("
    update sooma_wellness.bookings b
       set booking_status = 'expired',
           updated_at = clock_timestamp()
      from sooma_wellness.customers c
     where b.customer_id = c.customer_id
       and c.user_id = :user_id
       and lower(trim(b.booking_status)) = 'waiting_payment'
       and coalesce(b.expires_at, b.booked_at + interval '30 minutes') <= clock_timestamp()
");
$expireStmt->execute([':user_id' => $userId]);

if (isset($_GET['sync_expire']) && $_GET['sync_expire'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'updated' => $expireStmt->rowCount()]);
    exit;
}

$stmt = $pdo->prepare("
    select b.booking_id as id,
        coalesce(b.expires_at, b.booked_at + interval '30 minutes') as effective_expires_at,
        greatest(
            0,
            ceil(extract(epoch from (coalesce(b.expires_at, b.booked_at + interval '30 minutes') - clock_timestamp())))
        )::int as remaining_seconds,
        coalesce(nullif(to_jsonb(b)->>'private_request_id', ''), nullif(to_jsonb(b)->>'request_id', ''), '') as private_request_id,
        (
            lower(coalesce(nullif(to_jsonb(b)->>'class_type', ''), cs.class_type, '')) like '%private%'
            or lower(coalesce(mc.class_name, '')) like '%private%'
            or upper(ltrim(coalesce(b.booking_code, ''), '#')) like 'REQ%'
            or coalesce(nullif(to_jsonb(b)->>'private_request_id', ''), nullif(to_jsonb(b)->>'request_id', ''), '') <> ''
            or exists (
                select 1
                from sooma_wellness.request_classes rc
                where rc.customer_id = b.customer_id
                    and (
                        to_jsonb(rc)->>'booking_id' = b.booking_id::text
                        or to_jsonb(rc)->>'schedule_id' = cs.schedule_id::text
                        or (
                            rc.class_id = cs.class_id
                            and rc.preferred_date = cs.schedule_date
                            and rc.preferred_time = cs.start_time
                        )
                    )
            )
        ) as is_private_req,
        p.payment_proof,
           p.verification_status,
           coalesce(nullif(to_jsonb(p)->>'admin_note', ''), nullif(to_jsonb(p)->>'rejection_reason', ''), '') as admin_note,
           cs.schedule_date as date,
           to_char(cs.start_time, 'HH24.MI') as time_start,
           to_char(cs.end_time, 'HH24.MI') as time_end,
           mc.class_name,
           coalesce(cs.class_type, 'Public') as type,
           coalesce(cs.level, 'All Level') as level,
           coalesce(u_inst.name, 'Instructor') as coach
      from sooma_wellness.bookings b
      join sooma_wellness.customers c on c.customer_id = b.customer_id
      join sooma_wellness.class_schedules cs on cs.schedule_id = coalesce(b.reschedule_schedule_id, b.schedule_id)
      join sooma_wellness.master_classes mc on mc.class_id = cs.class_id
      left join sooma_wellness.instructors inst on inst.instructor_id = cs.instructor_id
      left join sooma_wellness.users u_inst on u_inst.user_id = inst.user_id
      left join sooma_wellness.payments p on p.payment_id = b.payment_id
     where c.user_id = :user_id
     order by b.booked_at desc, b.booking_id desc
");
$stmt->execute([':user_id' => $userId]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attStmt = $pdo->prepare("
    select attendee_name, user_id
      from sooma_wellness.booking_attendees
     where booking_id = :bid
     order by attendee_id asc
");

$transactions = [];
foreach ($dbRows as $r) {
    $bStatus      = strtolower(trim((string) $r['booking_status']));
    $vStatus      = strtolower(trim((string) ($r['verification_status'] ?? '')));
    $hasProof     = !empty($r['payment_proof']);
    $remainingSec = (int) ($r['remaining_seconds'] ?? 0);

    if ($vStatus === 'rejected' || $bStatus === 'rejected') {
        $tabKey = 'history';
        $label  = 'Rejected';
    } elseif ($bStatus === 'expired' || ($bStatus === 'waiting_payment' && $remainingSec <= 0)) {
        $tabKey = 'history';
        $label  = 'Expired';
    } elseif ($bStatus === 'cancelled') {
        $tabKey = 'history';
        $label  = 'Cancelled';
    } elseif (in_array($bStatus, ['confirmed', 'completed'], true) || in_array($vStatus, ['verified', 'approved', 'confirmed', 'paid'], true)) {
        $tabKey = 'history';
        $label  = 'Successful';
    } elseif ($bStatus === 'waiting_verification' || ($hasProof && $vStatus === 'pending')) {
        $tabKey = 'pending';
        $label  = 'Waiting Verification';
    } else {
        $tabKey = 'unpaid';
        $label  = 'Waiting Payment';
    }

    $attStmt->execute([':bid' => $r['id']]);
    $attendees = [];
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $attendees[] = [
            'name'   => $att['attendee_name'],
            'is_you' => ((int) $att['user_id'] === $userId),
        ];
    }

    if (empty($attendees)) {
        $attendees[] = ['name' => $user_name, 'is_you' => true];
    }

    $isPrivate = filter_var($r['is_private_req'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $transactions[] = [
        'id'                => (int) $r['id'],
        'code'              => '#' . ltrim((string) $r['code'], '#'),
        'status'            => $tabKey,
        'status_label'      => $label,
        'remaining_seconds' => ($tabKey === 'unpaid') ? $remainingSec : null,
        'admin_note'        => (string) ($r['admin_note'] ?? ''),
        'date'              => $r['date'],
        'time_start'        => $r['time_start'],
        'time_end'          => $r['time_end'],
        'class_name'        => $r['class_name'],
        'category'          => $r['class_name'],
        'type'              => $isPrivate ? 'Private' : $r['type'],
        'level'             => $r['level'],
        'coach'             => $r['coach'],
        'location'          => $location,
        'image'             => '../assets/images/gallery-main.png',
        'attendees'         => $attendees,
        'is_package'        => false,
        'is_private'        => $isPrivate,
    ];
}

$pkgStmt = $pdo->prepare("
    select up.user_package_id,
           coalesce(nullif(trim(to_jsonb(up)->>'redeem_code'), ''), 'PKO-' || up.user_package_id) as code,
           coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'pending') as pkg_status,
           coalesce(
               nullif(to_jsonb(up)->>'purchased_at', ''),
               nullif(to_jsonb(up)->>'created_at', ''),
               nullif(to_jsonb(pay)->>'uploaded_at', ''),
               clock_timestamp()::text
           ) as txn_date,
           coalesce(pk.package_name, 'Class Package') as package_name,
           coalesce(pk.total_sessions, 1) as total_sessions,
           coalesce(pk.active_days, 30) as active_days,
           coalesce(mc.class_name, 'Package') as class_name,
           coalesce(pay.gross_amount, pk.package_price, 0)::bigint as amount,
           pay.payment_proof,
           lower(coalesce(pay.verification_status, 'pending')) as verif_status,
           coalesce(nullif(to_jsonb(pay)->>'rejection_reason', ''), nullif(to_jsonb(pay)->>'admin_note', ''), '') as admin_note
      from sooma_wellness.user_packages up
      join sooma_wellness.customers c on c.customer_id = up.customer_id
      left join sooma_wellness.packages pk on pk.package_id = up.package_id
      left join sooma_wellness.master_classes mc on mc.class_id = pk.class_id
      left join sooma_wellness.payments pay on pay.payment_id = up.payment_id
     where c.user_id = :user_id
     order by up.user_package_id desc
");
$pkgStmt->execute([':user_id' => $userId]);
foreach ($pkgStmt->fetchAll(PDO::FETCH_ASSOC) as $pr) {
    $pkgStatus = strtolower((string) ($pr['pkg_status'] ?? 'pending'));
    $vStatus   = strtolower((string) ($pr['verif_status'] ?? 'pending'));
    $hasProof  = !empty($pr['payment_proof']);

    if ($pkgStatus === 'rejected' || in_array($vStatus, ['rejected', 'declined', 'failed'], true)) {
        $tabKey = 'history';
        $label  = 'Rejected';
    } elseif ($pkgStatus === 'active' || in_array($vStatus, ['verified', 'approved', 'confirmed', 'paid'], true)) {
        $tabKey = 'history';
        $label  = 'Successful';
    } elseif ($hasProof || $vStatus === 'waiting_verification') {
        $tabKey = 'pending';
        $label  = 'Waiting Verification';
    } else {
        $tabKey = 'pending';
        $label  = 'Pending';
    }

    $rawDate = (string) ($pr['txn_date'] ?? '');
    $isoDate = substr($rawDate, 0, 10) ?: date('Y-m-d');
    $timeStr = strlen($rawDate) >= 16 ? substr($rawDate, 11, 5) : '00.00';
    $timeStr = str_replace(':', '.', $timeStr);

    $transactions[] = [
        'id'                => 900000 + (int) $pr['user_package_id'],
        'code'              => '#' . ltrim((string) $pr['code'], '#'),
        'status'            => $tabKey,
        'status_label'      => $label,
        'remaining_seconds' => null,
        'admin_note'        => (string) ($pr['admin_note'] ?? ''),
        'date'              => $isoDate,
        'time_start'        => $timeStr,
        'time_end'          => 'WIB',
        'class_name'        => (string) $pr['package_name'],
        'category'          => (string) $pr['package_name'] . ' (' . (int) $pr['total_sessions'] . ' Sessions)',
        'type'              => 'Package',
        'level'             => (int) $pr['active_days'] . ' Days',
        'coach'             => 'Rp ' . number_format((float) $pr['amount'], 0, ',', '.'),
        'location'          => $location,
        'image'             => '../assets/images/gallery-main.png',
        'attendees'         => [['name' => $user_name, 'is_you' => true]],
        'is_package'        => true,
        'is_private'        => false,
    ];
}

$tabs = [
    'unpaid'  => 'Unpaid',
    'pending' => 'Pending',
    'history' => 'History',
];

$profile_menu = [
    ['label' => 'My profile',         'href' => 'profile.php',                 'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction',     'href' => 'profile-transaction.php',     'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',        'href' => 'profile-schedule.php',        'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',         'href' => 'profile-package.php',         'icon' => 'ph ph-package'],
    ['label' => 'My request',         'href' => 'profile-private-request.php', 'icon' => 'ph ph-paper-plane-tilt'],
];

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function format_short_date(string $iso): string
{
    static $months = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];
    $ts = strtotime($iso);
    return $ts ? date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts) : '--';
}

function format_long_date(string $iso): string
{
    $ts = strtotime($iso);
    return $ts ? date('l, j F Y', $ts) : '--';
}

function format_time_range(string $start, string $end, bool $isPackage = false): string
{
    if ($isPackage) {
        return $start . ' WIB';
    }
    return $start . ' - ' . $end . ' WIB';
}

function format_countdown_text(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours   = intdiv($seconds, 3600);
    $mins    = intdiv($seconds % 3600, 60);
    $secs    = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }
    return sprintf('%02d:%02d', $mins, $secs);
}

$counts = array_fill_keys(array_keys($tabs), 0);
foreach ($transactions as $t) {
    if (isset($counts[$t['status']])) {
        $counts[$t['status']]++;
    }
}

$tab = $_GET['tab'] ?? ($counts['unpaid'] > 0 ? 'unpaid' : 'pending');
if (!is_string($tab) || !array_key_exists($tab, $tabs)) {
    $tab = 'pending';
}

$rows = array_values(array_filter($transactions, fn(array $t): bool => $t['status'] === $tab));

$empty_messages = [
    'unpaid'  => 'You have no unpaid transactions.',
    'pending' => 'You have no pending transactions.',
    'history' => 'No transaction history yet.',
];

// Sesuaikan path payment.php jika berbeda folder (misalnya '../booking/payment.php')
$paymentBasePath = file_exists(__DIR__ . '/payment.php')
    ? './payment.php'
    : (file_exists(__DIR__ . '/../booking/payment.php') ? '../booking/payment.php' : '../payment.php');

$modal_payload = [];
foreach ($transactions as $t) {
    $payUrl = $paymentBasePath . '?id=' . urlencode((string) $t['id']);
    if (!empty($t['is_private'])) {
        $payUrl .= '&type=private';
    }

    $modal_payload[(string) $t['id']] = [
        'image'            => $t['image'],
        'class_name'       => $t['class_name'],
        'category'         => $t['is_package'] ? $t['category'] : ($t['category'] . ' Class'),
        'code'             => $t['code'],
        'date_long'        => format_long_date($t['date']),
        'time_range'       => format_time_range($t['time_start'], $t['time_end'], (bool) $t['is_package']),
        'type'             => $t['type'],
        'level'            => $t['level'],
        'coach'            => $t['coach'],
        'location_name'    => $t['location']['name'],
        'location_address' => $t['location']['address'],
        'attendees'        => $t['attendees'],
        'admin_note'       => $t['admin_note'] !== '' ? $t['admin_note'] : '-',
        'status_label'     => $t['status_label'],
        'is_private'       => !empty($t['is_private']),
        'pay_url'          => $payUrl,
    ];
}
$sidebar_current     = basename($_SERVER['SCRIPT_NAME'] ?? 'profile-transaction.php');
$current_page        = '';
$navbar_avatar       = $user_avatar;
$navbar_profile_href = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Transaction — Sooma Wellness</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Montserrat+Alternates:wght@400&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/profile-transaction.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="transaction-page">

        <aside class="menu-card" aria-labelledby="menu-title">
            <h1 class="menu-card__title" id="menu-title">My Transaction</h1>

            <nav aria-label="Profile menu">
                <ul class="menu-list">
                    <?php foreach ($profile_menu as $item):
                        $is_active = basename($item['href']) === $sidebar_current;
                    ?>
                        <li class="menu-list__item<?= $is_active ? ' active' : '' ?>">
                            <a href="<?= e($item['href']) ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                <span class="menu-list__icon">
                                    <i class="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                                </span>
                                <span class="menu-list__label"><?= e($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="transaction-content">

            <nav class="txn-tabs" aria-label="Transaction filter">
                <?php $i = 0;
                foreach ($tabs as $key => $label): ?>
                    <?php if ($i++ > 0): ?><span class="txn-tabs__sep" aria-hidden="true"></span><?php endif; ?>
                    <a class="txn-tabs__item<?= $key === $tab ? ' active' : '' ?>"
                        href="?tab=<?= e($key) ?>"
                        data-tab-key="<?= e($key) ?>"
                        <?= $key === $tab ? 'aria-current="true"' : '' ?>>
                        <span class="txn-tabs__label"><?= e($label) ?></span>
                        <span class="txn-tabs__count" data-tab-count="<?= e($key) ?>" aria-label="<?= (int) $counts[$key] ?> transactions"><?= (int) $counts[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section aria-label="<?= e($tabs[$tab]) ?> transactions" id="txnListSection" data-active-tab="<?= e($tab) ?>">
                <?php if (empty($rows)): ?>
                    <p class="txn-empty"><?= e($empty_messages[$tab]) ?></p>
                <?php else: ?>
                    <ul class="txn-list">
                        <?php foreach ($rows as $t): ?>
                            <li class="txn-card" data-card-id="<?= (int) $t['id'] ?>">
                                <div class="txn-info">
                                    <div class="txn-field txn-field--date">
                                        <span class="visually-hidden">Date/Time: </span>
                                        <span><?= e(format_short_date($t['date'])) ?></span>
                                        <span><?= e(format_time_range($t['time_start'], $t['time_end'], (bool) $t['is_package'])) ?></span>
                                    </div>

                                    <div class="txn-field">
                                        <strong class="txn-field__label"><?= $t['is_package'] ? 'Package' : 'Class' ?></strong>
                                        <span><?= e($t['category']) ?></span>
                                    </div>

                                    <div class="txn-field">
                                        <strong class="txn-field__label">Status</strong>
                                        <span class="js-txn-status-label"><?= e($t['status_label']) ?></span>

                                        <?php if ($tab === 'unpaid' && $t['remaining_seconds'] !== null): ?>
                                            <small class="txn-countdown js-txn-countdown"
                                                data-remaining="<?= (int) $t['remaining_seconds'] ?>"
                                                style="display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; color: #b42318; font-weight: 600; font-variant-numeric: tabular-nums;">
                                                <i class="ph ph-clock" aria-hidden="true"></i>
                                                <span>Pay within <strong class="js-txn-countdown-time"><?= e(format_countdown_text((int) $t['remaining_seconds'])) ?></strong></span>
                                            </small>
                                        <?php endif; ?>

                                        <?php if ($t['status_label'] === 'Rejected' && $t['admin_note'] !== ''): ?>
                                            <small class="txn-note">Note: <?= e($t['admin_note']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="txn-action">
                                    <span class="txn-action__label">Action</span>

                                    <div class="txn-action__buttons">
                                        <?php if ($tab === 'unpaid' && !$t['is_package']): ?>
                                            <?php
                                            $payUrl = $paymentBasePath . '?id=' . urlencode((string) $t['id']);
                                            if (!empty($t['is_private'])) {
                                                $payUrl .= '&type=private';
                                            }
                                            ?>
                                            <a href="<?= e($payUrl) ?>"
                                                class="btn-show-details js-paid-now-btn"
                                                data-pay-url="<?= e($payUrl) ?>"
                                                data-is-private="<?= !empty($t['is_private']) ? '1' : '0' ?>">
                                                Paid Now
                                            </a>
                                        <?php endif; ?>

                                        <button type="button"
                                            class="btn-show-details js-show-details"
                                            data-id="<?= (int) $t['id'] ?>"
                                            data-txn-id="<?= (int) $t['id'] ?>"
                                            data-booking-id="<?= (int) $t['id'] ?>">
                                            Show Details
                                        </button>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <div class="modal-overlay" id="bookingModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle" tabindex="-1">

            <header class="modal__banner">
                <img class="modal__banner-img" data-field="image" src="" alt="">

                <button class="modal__close" type="button" aria-label="Close booking details">
                    <i class="ph-bold ph-x"></i>
                </button>

                <div class="modal__heading">
                    <h2 class="modal__title" id="bookingModalTitle" data-field="class_name"></h2>
                    <p class="modal__meta">
                        <span class="modal__category" data-field="category"></span>
                        <strong class="modal__code" data-field="code"></strong>
                    </p>
                </div>
            </header>

            <div class="modal-body" role="region" aria-label="Booking details" tabindex="0">

                <div class="booking-info">
                    <ul class="booking-info__list">
                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-clock info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong data-field="date_long"></strong>
                                    <span data-field="time_range"></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-users-three info-row__icon"></i>
                                <div class="info-row__text">
                                    <span><strong data-field="type"></strong></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-chart-bar-horizontal info-row__icon"></i>
                                <div class="info-row__text">
                                    <span><strong data-field="level"></strong></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-identification-card info-row__icon"></i>
                                <div class="info-row__text">
                                    <span><strong data-field="coach"></strong></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-map-pin info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong data-field="location_name"></strong>
                                    <span data-field="location_address"></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row info-row--attendee">
                            <div class="info-row__header">
                                <i class="ph-fill ph-user-list info-row__icon"></i>
                                <div class="info-row__text">
                                    <span>Registered <strong>Attendee</strong></span>
                                </div>
                            </div>

                            <div class="attendee-box">
                                <div class="attendee-box__list" data-list="attendees"></div>

                                <div class="attendee-row attendee-row--add">
                                    <img src="../assets/icons/modal-plus.svg" alt="" width="10" height="10">
                                    <span>-</span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row" id="modalAdminNoteRow">
                            <div class="info-row__header">
                                <i class="ph-fill ph-note-pencil info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong>Admin Note</strong>
                                    <span data-field="admin_note">-</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <script type="application/json" id="transactions-data">
        <?=
        json_encode($modal_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        ?>
    </script>
    <script src="../assets/js/transaction.js" defer></script>

</body>

</html>