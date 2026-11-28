<?php

declare(strict_types=1);

$headerType = 'customer';
$headerLogo = 'black';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../login-regist/connection.php';

$sessionUser = $_SESSION['user'] ?? [];
$userId      = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: ../login-regist/login.php');
    exit;
}

$user_name   = $sessionUser['name']       ?? 'Member';
$user_avatar = $sessionUser['avatar_url'] ?? 'assets/images/users/emilia-bonnita.png';
$location    = ['name' => 'Sooma Wellness', 'address' => 'Jl Ciliwung no. 62, Blitar'];

$stmt = $pdo->prepare("
    select rc.request_id as id,
           rc.preferred_date as date,
           to_char(rc.preferred_time, 'HH24.MI') as time_start,
           to_char(rc.preferred_time + interval '1 hour', 'HH24.MI') as time_end,
           coalesce(rc.medical_notes, '') as medical_notes,
           coalesce(rc.status, 'pending') as status,
           coalesce(nullif(trim(to_jsonb(rc)->>'preferred_coach'), ''), 'Any Available Coach') as preferred_coach,
           coalesce((to_jsonb(rc)->>'attendee_count')::int, 1) as attendee_count,
           coalesce(to_jsonb(rc)->>'admin_note', '') as admin_note,
           mc.class_name,
           mc.price,
           b_link.booking_id as linked_booking_id,
           b_link.booking_code as linked_booking_code,
           lower(coalesce(b_link.booking_status, '')) as linked_booking_status,
           lower(coalesce(b_link.verification_status, '')) as linked_verif_status,
           b_link.payment_proof as linked_payment_proof
      from sooma_wellness.request_classes rc
      join sooma_wellness.customers c on c.customer_id = rc.customer_id
      join sooma_wellness.master_classes mc on mc.class_id = rc.class_id
      left join lateral (
          select b.booking_id,
                 coalesce(b.booking_code, 'BK-' || lpad(b.booking_id::text, 4, '0')) as booking_code,
                 b.booking_status,
                 p.verification_status,
                 p.payment_proof
            from sooma_wellness.bookings b
            join sooma_wellness.class_schedules cs on cs.schedule_id = coalesce(b.reschedule_schedule_id, b.schedule_id)
            left join sooma_wellness.payments p on p.payment_id = b.payment_id
           where b.customer_id = rc.customer_id
             and (
                 to_jsonb(rc)->>'booking_id' = b.booking_id::text
                 or to_jsonb(rc)->>'schedule_id' = cs.schedule_id::text
                 or to_jsonb(b)->>'private_request_id' = rc.request_id::text
                 or to_jsonb(b)->>'request_id' = rc.request_id::text
                 or (
                     cs.class_id = rc.class_id
                     and cs.schedule_date = rc.preferred_date
                     and cs.start_time = rc.preferred_time
                 )
             )
           order by b.booking_id desc
           limit 1
      ) b_link on true
     where c.user_id = :user_id
     order by rc.request_id desc
");
$stmt->execute([':user_id' => $userId]);
$dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requests = [];
foreach ($dbRows as $r) {
    $rawStatus    = strtolower(trim((string) ($r['status'] ?? 'pending')));
    $linkedStatus = strtolower(trim((string) ($r['linked_booking_status'] ?? '')));
    $linkedVerif  = strtolower(trim((string) ($r['linked_verif_status'] ?? '')));
    $hasProof     = !empty($r['linked_payment_proof']);
    $attendees    = max(1, (int) ($r['attendee_count'] ?? 1));
    $totalPrice   = (float) $r['price'] * $attendees;
    $isApproved   = in_array($rawStatus, ['approved', 'accepted', 'confirmed'], true);
    $showTxCta    = false;

    if ($rawStatus === 'pending') {
        $tabKey = 'pending';
        $label  = 'Waiting Approval';
    } else {
        $tabKey = 'history';
        if ($isApproved) {
            $hasPaid = $hasProof
                || in_array($linkedStatus, ['waiting_verification', 'confirmed', 'completed'], true)
                || in_array($linkedVerif, ['pending', 'verified', 'approved', 'confirmed', 'paid'], true);

            $isRejectedPayment = ($linkedStatus === 'rejected' || $linkedVerif === 'rejected');

            if ($hasPaid && !$isRejectedPayment) {
                $label     = 'Approved';
                $showTxCta = false;
            } elseif ($linkedStatus === 'expired') {
                $label     = 'Approved (Payment Expired)';
                $showTxCta = false;
            } else {
                $label     = 'Approved (Waiting Payment — Complete payment in My Transaction)';
                $showTxCta = true;
            }
        } elseif ($rawStatus === 'rejected') {
            $label = 'Rejected';
        } elseif ($rawStatus === 'cancelled') {
            $label = 'Cancelled';
        } else {
            $label = ucfirst($rawStatus);
        }
    }

    $requests[] = [
        'id'                => (int) $r['id'],
        'code'              => '#REQ-' . str_pad((string) $r['id'], 4, '0', STR_PAD_LEFT),
        'booking_code'      => !empty($r['linked_booking_code']) ? '#' . ltrim((string) $r['linked_booking_code'], '#') : '',
        'linked_booking_id' => (int) ($r['linked_booking_id'] ?? 0),
        'status'            => $tabKey,
        'status_label'      => $label,
        'show_tx_cta'       => $showTxCta,
        'date'              => (string) $r['date'],
        'time_start'        => (string) $r['time_start'],
        'time_end'          => (string) $r['time_end'],
        'class_name'        => (string) $r['class_name'],
        'coach'             => (string) $r['preferred_coach'],
        'attendee_text'     => $attendees . ($attendees === 1 ? ' Person' : ' People'),
        'price'             => 'Rp ' . number_format($totalPrice, 0, ',', '.'),
        'medical_notes'     => trim((string) $r['medical_notes']) !== '' ? trim((string) $r['medical_notes']) : '-',
        'admin_note'        => trim((string) ($r['admin_note'] ?? '')),
        'location'          => $location,
        'image'             => '../assets/images/gallery-main.png',
    ];
}

$tabs = [
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

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

$counts = array_fill_keys(array_keys($tabs), 0);
foreach ($requests as $req) {
    if (isset($counts[$req['status']])) {
        $counts[$req['status']]++;
    }
}

$tab = $_GET['tab'] ?? 'pending';
if (!is_string($tab) || !array_key_exists($tab, $tabs)) {
    $tab = 'pending';
}

$rows = array_values(array_filter($requests, fn(array $req): bool => $req['status'] === $tab));

$empty_messages = [
    'pending' => 'You have no pending private class requests.',
    'history' => 'No private class request history yet.',
];

$modal_payload = [];
foreach ($requests as $req) {
    $modal_payload[(string) $req['id']] = [
        'image'            => $req['image'],
        'class_name'       => $req['class_name'],
        'category'         => 'Private Class Request',
        'code'             => $req['booking_code'] !== '' ? $req['code'] . ' • ' . $req['booking_code'] : $req['code'],
        'date_long'        => format_long_date($req['date']),
        'time_range'       => $req['time_start'] . ' - ' . $req['time_end'] . ' WIB',
        'session_meta'     => 'Private Class • ' . $req['attendee_text'] . ' • Coach: ' . $req['coach'],
        'price'            => $req['price'],
        'status_label'     => $req['status_label'],
        'medical_notes'    => $req['medical_notes'],
        'admin_note'       => $req['admin_note'],
        'show_tx_cta'      => $req['show_tx_cta'],
        'tx_url'           => 'profile-transaction.php?tab=unpaid',
        'location_name'    => $req['location']['name'],
        'location_address' => $req['location']['address'],
    ];
}

$sidebar_current     = basename($_SERVER['SCRIPT_NAME'] ?? 'profile-private-request.php');
$current_page        = '';
$navbar_avatar       = $user_avatar;
$navbar_profile_href = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Private Request — Sooma Wellness</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Montserrat+Alternates:wght@400&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/profile-private-request.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="transaction-page">

        <aside class="menu-card" aria-labelledby="menu-title">
            <h1 class="menu-card__title" id="menu-title">My Request</h1>

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

            <nav class="txn-tabs" aria-label="Private request filter">
                <?php $i = 0;
                foreach ($tabs as $key => $label): ?>
                    <?php if ($i++ > 0): ?><span class="txn-tabs__sep" aria-hidden="true"></span><?php endif; ?>
                    <a class="txn-tabs__item<?= $key === $tab ? ' active' : '' ?>"
                        href="?tab=<?= e($key) ?>"
                        <?= $key === $tab ? 'aria-current="true"' : '' ?>>
                        <span class="txn-tabs__label"><?= e($label) ?></span>
                        <span class="txn-tabs__count" aria-label="<?= (int) $counts[$key] ?> requests"><?= (int) $counts[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section aria-label="<?= e($tabs[$tab]) ?> private requests">
                <?php if (empty($rows)): ?>
                    <p class="txn-empty"><?= e($empty_messages[$tab]) ?></p>
                <?php else: ?>
                    <ul class="txn-list">
                        <?php foreach ($rows as $req): ?>
                            <li class="txn-card">
                                <div class="txn-info">
                                    <div class="txn-field txn-field--date">
                                        <span class="visually-hidden">Preferred Date/Time: </span>
                                        <span><?= e(format_short_date($req['date'])) ?></span>
                                        <span><?= e($req['time_start']) ?> - <?= e($req['time_end']) ?> WIB</span>
                                    </div>

                                    <div class="txn-field">
                                        <strong class="txn-field__label">Class</strong>
                                        <span><?= e($req['class_name']) ?></span>
                                    </div>

                                    <div class="txn-field">
                                        <strong class="txn-field__label">Status</strong>
                                        <span><?= e($req['status_label']) ?></span>
                                    </div>
                                </div>

                                <div class="txn-action">
                                    <span class="txn-action__label">Action</span>

                                    <div class="txn-action__buttons">
                                        <?php if ($req['show_tx_cta']): ?>
                                            <a href="profile-transaction.php?tab=unpaid"
                                                class="btn-show-details"
                                                style="text-decoration: none;">
                                                My Transaction
                                            </a>
                                        <?php endif; ?>

                                        <button type="button"
                                            class="btn-show-details js-show-request-details"
                                            data-id="<?= (int) $req['id'] ?>">
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

    <div class="modal-overlay" id="requestModal" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="requestModalTitle" tabindex="-1">

            <header class="modal__banner">
                <img class="modal__banner-img" data-field="image" src="" alt="">

                <button class="modal__close" type="button" aria-label="Close request details">
                    <i class="ph-bold ph-x"></i>
                </button>

                <div class="modal__heading">
                    <h2 class="modal__title" id="requestModalTitle" data-field="class_name"></h2>
                    <p class="modal__meta">
                        <span class="modal__category" data-field="category"></span>
                        <strong class="modal__code" data-field="code"></strong>
                    </p>
                </div>
            </header>

            <div class="modal-body" role="region" aria-label="Private request details" tabindex="0">
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
                                <i class="ph-fill ph-user info-row__icon"></i>
                                <div class="info-row__text">
                                    <span data-field="session_meta"><strong>Private</strong> Class</span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-tag info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong>Total Price</strong>
                                    <span data-field="price"></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-info info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong>Status</strong>
                                    <span data-field="status_label"></span>
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

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-note-pencil info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong>Medical Notes</strong>
                                    <span data-field="medical_notes">-</span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row" id="requestAdminNoteRow" hidden>
                            <div class="info-row__header">
                                <i class="ph-fill ph-chat-text info-row__icon"></i>
                                <div class="info-row__text">
                                    <strong>Admin Note</strong>
                                    <span data-field="admin_note">-</span>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div id="requestTxCta" hidden style="margin-top: 18px; justify-content: flex-end;">
                        <a id="requestTxCtaLink" href="profile-transaction.php?tab=unpaid" class="btn-show-details" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px;">
                            Go to My Transaction
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="requests-data">
        <?=
        json_encode($modal_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        ?>
    </script>

    <script>
        (function () {
            'use strict';

            var dataEl       = document.getElementById('requests-data');
            var overlay      = document.getElementById('requestModal');
            if (!dataEl || !overlay) return;

            var payload      = JSON.parse(dataEl.textContent || '{}');
            var dialog       = overlay.querySelector('.modal');
            var closeBtn     = overlay.querySelector('.modal__close');
            var adminNoteRow = document.getElementById('requestAdminNoteRow');
            var txCta        = document.getElementById('requestTxCta');
            var txCtaLink    = document.getElementById('requestTxCtaLink');
            var lastTrigger  = null;

            function openModal(id, trigger) {
                var item = payload[String(id)];
                if (!item) return;

                lastTrigger = trigger || null;

                overlay.querySelectorAll('[data-field]').forEach(function (el) {
                    var key = el.getAttribute('data-field');
                    if (key === 'image') {
                        el.src = item.image || '';
                        el.alt = item.class_name || '';
                    } else {
                        el.textContent = item[key] !== undefined ? item[key] : '-';
                    }
                });

                if (adminNoteRow) {
                    adminNoteRow.hidden = !item.admin_note;
                }
                if (txCta) {
                    txCta.hidden = !item.show_tx_cta;
                    txCta.style.display = item.show_tx_cta ? 'flex' : 'none';
                }
                if (txCtaLink && item.tx_url) {
                    txCtaLink.href = item.tx_url;
                }

                overlay.classList.add('is-open');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                if (dialog) dialog.focus();
            }

            function closeModal() {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
                if (lastTrigger) lastTrigger.focus();
            }

            document.querySelectorAll('.js-show-request-details').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(btn.getAttribute('data-id'), btn);
                });
            });

            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>

</body>

</html>