<?php
$headerType = 'customer';
$headerLogo = 'black';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_name   = $_SESSION['user']['name']       ?? 'Emilia Bonnita';
$user_avatar = $_SESSION['user']['avatar_url'] ?? 'assets/images/users/emilia-bonnita.png';

$location = ['name' => 'Sooma Wellness', 'address' => 'Jl Ciliwung no. 62, Blitar'];

$transactions = [
    [
        'id'           => 11,
        'code'         => '#BK-011',
        'status'       => 'pending',
        'status_label' => 'Waiting Transaction',
        'date'         => '2026-09-19',
        'time_start'   => '11.00',
        'time_end'     => '12.00',
        'class_name'   => 'Reformer Pilates',
        'category'     => 'Pilates',
        'type'         => 'Public',
        'level'        => 'Beginner',
        'coach'        => 'Grace',
        'location'     => $location,
        'image'        => '../assets/images/gallery-main.png',
        'attendees'    => [['name' => $user_name, 'is_you' => true]],
    ],
    [
        'id'           => 10,
        'code'         => '#BK-010',
        'status'       => 'pending',
        'status_label' => 'Waiting Transaction',
        'date'         => '2026-09-18',
        'time_start'   => '14.00',
        'time_end'     => '15.00',
        'class_name'   => 'Reformer Pilates',
        'category'     => 'Pilates',
        'type'         => 'Public',
        'level'        => 'Beginner',
        'coach'        => 'Grace',
        'location'     => $location,
        'image'        => '../assets/images/gallery-main.png',
        'attendees'    => [['name' => $user_name, 'is_you' => true]],
    ],
    [
        'id'           => 5,
        'code'         => '#BK-005',
        'status'       => 'history',
        'status_label' => 'Successfull',
        'date'         => '2026-09-02',
        'time_start'   => '08.00',
        'time_end'     => '09.00',
        'class_name'   => 'Yoga',
        'category'     => 'Yoga',
        'type'         => 'Public',
        'level'        => 'All Level',
        'coach'        => 'Bunga',
        'location'     => $location,
        'image'        => '../assets/images/gallery-main.png',
        'attendees'    => [['name' => $user_name, 'is_you' => true]],
    ],
];

$tabs = [
    'unpaid'  => 'Unpaid',
    'pending' => 'Pending',
    'history' => 'History',
];

$profile_menu = [
    ['label' => 'My profile',     'href' => 'profile.php',        'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction', 'href' => 'profile-transaction.php', 'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',    'href' => 'profile-schedule.php',    'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',     'href' => 'profile-package.php',     'icon' => 'ph ph-package'],
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

function format_time_range(string $start, string $end): string
{
    return $start . ' - ' . $end . ' WIB';
}

$tab = $_GET['tab'] ?? 'pending';
if (!is_string($tab) || !array_key_exists($tab, $tabs)) {
    $tab = 'pending';
}

$counts = array_fill_keys(array_keys($tabs), 0);
foreach ($transactions as $t) {
    if (isset($counts[$t['status']])) {
        $counts[$t['status']]++;
    }
}

$rows = array_values(array_filter($transactions, fn(array $t): bool => $t['status'] === $tab));

$empty_messages = [
    'unpaid'  => 'You have no unpaid transactions.',
    'pending' => 'You have no pending transactions.',
    'history' => 'No transaction history yet.',
];

$modal_payload = [];
foreach ($transactions as $t) {
    $modal_payload[(string) $t['id']] = [
        'image'            => $t['image'],
        'class_name'       => $t['class_name'],
        'category'         => $t['category'] . ' Class',
        'code'             => $t['code'],
        'date_long'        => format_long_date($t['date']),
        'time_range'       => format_time_range($t['time_start'], $t['time_end']),
        'type'             => $t['type'],
        'level'            => $t['level'],
        'coach'            => $t['coach'],
        'location_name'    => $t['location']['name'],
        'location_address' => $t['location']['address'],
        'attendees'        => $t['attendees'],
    ];
}

$sidebar_current = basename($_SERVER['SCRIPT_NAME'] ?? 'profile-transaction.php');

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
                        <?= $key === $tab ? 'aria-current="true"' : '' ?>>
                        <span class="txn-tabs__label"><?= e($label) ?></span>
                        <span class="txn-tabs__count" aria-label="<?= (int) $counts[$key] ?> transactions"><?= (int) $counts[$key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section aria-label="<?= e($tabs[$tab]) ?> transactions">
                <?php if (empty($rows)): ?>
                    <p class="txn-empty"><?= e($empty_messages[$tab]) ?></p>
                <?php else: ?>
                    <ul class="txn-list">
                        <?php foreach ($rows as $t): ?>
                            <li class="txn-card">
                                <div class="txn-info">
                                    <p class="txn-field txn-field--date">
                                        <span class="visually-hidden">Date/Time: </span>
                                        <?= e(format_short_date($t['date'])) ?><br>
                                        <?= e(format_time_range($t['time_start'], $t['time_end'])) ?>
                                    </p>
                                    <p class="txn-field">
                                        <strong class="txn-field__label">Class</strong>
                                        <?= e($t['category']) ?>
                                    </p>
                                    <p class="txn-field">
                                        <strong class="txn-field__label">Status</strong>
                                        <?= e($t['status_label']) ?>
                                    </p>
                                </div>

                                <div class="txn-action">
                                    <strong class="txn-action__label">Class Details</strong>
                                    <button class="btn-show-details" type="button"
                                        data-id="<?= (int) $t['id'] ?>"
                                        aria-haspopup="dialog" aria-controls="bookingModal">
                                        Show Details
                                    </button>
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
                                    <span><strong data-field="type"></strong> Class</span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-chart-bar-horizontal info-row__icon"></i>
                                <div class="info-row__text">
                                    <span>Level <strong data-field="level"></strong></span>
                                </div>
                            </div>
                        </li>

                        <li class="info-row">
                            <div class="info-row__header">
                                <i class="ph-fill ph-identification-card info-row__icon"></i>
                                <div class="info-row__text">
                                    <span><strong>Coach</strong> <span data-field="coach"></span></span>
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
                                    <img src="assets/icons/modal-plus.svg" alt="" width="10" height="10">
                                    <span>-</span>
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