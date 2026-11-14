<?php
$headerType = 'customer';
$headerLogo = 'black';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$schedules = [
    ['id' => 101, 'date_time' => '18 Sept 2026 14.00 WIB', 'coach' => 'Coach Grace',   'class_name' => 'Pilates Beginner', 'type' => 'Public',  'participant' => '4 / 4', 'status' => 'upcoming'],
    ['id' => 102, 'date_time' => '19 Sept 2026 11.00 WIB', 'coach' => 'Coach Bintari', 'class_name' => 'Pilates Beginner', 'type' => 'Private', 'participant' => '2 / 2', 'status' => 'upcoming'],
    ['id' => 103, 'date_time' => '22 Sept 2026 16.30 WIB', 'coach' => 'Coach Alifa',   'class_name' => 'Yoga All Level',   'type' => 'Public',  'participant' => '3 / 6', 'status' => 'upcoming'],

    ['id' => 91,  'date_time' => '4 Sept 2026 08.00 WIB',  'coach' => 'Coach Bunga',   'class_name' => 'Yoga All Level',   'type' => 'Public',  'participant' => '5 / 6', 'status' => 'history', 'result' => 'completed'],
    ['id' => 90,  'date_time' => '2 Sept 2026 14.00 WIB',  'coach' => 'Coach Grace',   'class_name' => 'Pilates Beginner', 'type' => 'Private', 'participant' => '2 / 2', 'status' => 'history', 'result' => 'cancelled'],
];

$user_avatar = $_SESSION['user']['avatar_url'] ?? 'assets/images/users/emilia-bonnita.png';


$profile_menu = [
    ['label' => 'My profile',     'href' => 'profile.php',        'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction', 'href' => 'profile-transaction.php', 'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',    'href' => 'profile-schedule.php',    'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',     'href' => 'profile-package.php',     'icon' => 'ph ph-package'],
];

$tabs = [
    'upcoming' => 'Upcoming',
    'history'  => 'History',
];

$per_page = 2;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function split_date_time(string $date_time): array
{
    if (preg_match('/^(.*\d{4})\s+(.+)$/u', trim($date_time), $m)) {
        return [$m[1], $m[2]];
    }
    return [$date_time, ''];
}

function schedule_url(string $tab, int $page = 1): string
{
    $query = ['tab' => $tab];
    if ($page > 1) {
        $query['page'] = $page;
    }
    return '?' . http_build_query($query);
}

$tab = $_GET['tab'] ?? 'upcoming';
if (!is_string($tab) || !array_key_exists($tab, $tabs)) {
    $tab = 'upcoming';
}

$filtered    = array_values(array_filter($schedules, fn(array $s): bool => $s['status'] === $tab));
$total_pages = max(1, (int) ceil(count($filtered) / $per_page));

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
$page = min((int) $page, $total_pages);

$rows = array_slice($filtered, ($page - 1) * $per_page, $per_page);

$sidebar_current = basename($_SERVER['SCRIPT_NAME'] ?? 'profile-schedule.php');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$current_page        = '';
$navbar_avatar       = $user_avatar;
$navbar_profile_href = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Schedule — Sooma Wellness</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Montserrat+Alternates:wght@400&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
       <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/profile-schedule.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="schedule-page">

        <aside class="menu-card" aria-labelledby="menu-title">
            <h1 class="menu-card__title" id="menu-title">My Schedule</h1>

            <nav aria-label="Profile menu">
                <ul class="menu-list">
                    <?php foreach ($profile_menu as $item):
                        $is_active = basename($item['href']) === $sidebar_current;
                    ?>
                        <li class="menu-list__item<?= $is_active ? ' active' : '' ?>">
                            <a href="<?= e($item['href']) ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                <span class="menu-list__icon">
                                    <span class="menu-list__icon">
                                        <i class="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                                    </span>
                                </span>
                                <span class="menu-list__label"><?= e($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="schedule-content">

            <nav class="schedule-tabs" aria-label="Schedule filter">
                <?php $i = 0;
                foreach ($tabs as $key => $label): ?>
                    <?php if ($i++ > 0): ?><span class="schedule-tabs__sep" aria-hidden="true"></span><?php endif; ?>
                    <a class="schedule-tabs__item<?= $key === $tab ? ' active' : '' ?>"
                        href="<?= e(schedule_url($key)) ?>"
                        <?= $key === $tab ? 'aria-current="true"' : '' ?>>
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <section class="schedule-card" aria-label="<?= e($tabs[$tab]) ?> classes">
                <div class="schedule-card__scroll">
                    <table class="schedule-table">
                        <colgroup>
                            <col style="width:164px">
                            <col style="width:130px">
                            <col style="width:186px">
                            <col style="width:184px">
                            <col style="width:95px">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Schedule</th>
                                <th scope="col">Coach</th>
                                <th scope="col">Class &amp; Level</th>
                                <th scope="col">Participant</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td class="schedule-table__empty" colspan="5">
                                        <?= $tab === 'upcoming' ? 'You have no upcoming classes.' : 'No class history yet.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($rows as $row):
                                [$date_part, $time_part] = split_date_time($row['date_time']);
                            ?>
                                <tr>
                                    <td>
                                        <?= e($date_part) ?><?php if ($time_part !== ''): ?><br><?= e($time_part) ?><?php endif; ?>
                                    </td>
                                    <td><?= e($row['coach']) ?></td>
                                    <td><?= e($row['class_name']) ?></td>
                                    <td>
                                        <?= e($row['type']) ?><br><?= e($row['participant']) ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'upcoming'): ?>
                                            <form class="action-cell" id="form-booking-<?= (int) $row['id'] ?>" method="post" action="timetable.php">
                                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="action" id="action-input-<?= (int) $row['id'] ?>" value="">

                                                <button class="btn-reschedule" type="submit" onclick="document.getElementById('action-input-<?= (int) $row['id'] ?>').value='reschedule';">
                                                    Reschedule
                                                </button>

                                                <button class="btn-cancel" type="button" onclick="confirmCancel('form-booking-<?= (int) $row['id'] ?>', 'action-input-<?= (int) $row['id'] ?>')">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-label status-label--<?= e($row['result'] ?? 'completed') ?>">
                                                <?= e(ucfirst($row['result'] ?? 'completed')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="pagination" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a class="pagination__arrow" href="<?= e(schedule_url($tab, $page - 1)) ?>" aria-label="Previous page">
                                <img src="assets/icons/pagination-prev.svg" alt="" width="8" height="15">
                            </a>
                        <?php else: ?>
                            <span class="pagination__arrow is-disabled" aria-disabled="true">
                                <img src="assets/icons/pagination-prev.svg" alt="" width="8" height="15">
                            </span>
                        <?php endif; ?>

                        <?php for ($n = 1; $n <= $total_pages; $n++): ?>
                            <?php if ($n === $page): ?>
                                <span class="pagination__page is-current" aria-current="page"><?= $n ?></span>
                            <?php else: ?>
                                <a class="pagination__page" href="<?= e(schedule_url($tab, $n)) ?>"
                                    aria-label="Page <?= $n ?>"><?= $n ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a class="pagination__arrow" href="<?= e(schedule_url($tab, $page + 1)) ?>" aria-label="Next page">
                                <img src="assets/icons/pagination-next.svg" alt="" width="8" height="15">
                            </a>
                        <?php else: ?>
                            <span class="pagination__arrow is-disabled" aria-disabled="true">
                                <img src="assets/icons/pagination-next.svg" alt="" width="8" height="15">
                            </span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>