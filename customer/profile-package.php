<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active_packages = [];

$history_packages = [
    [
        'name'          => 'Monthly Reformer Bundle',
        'type'          => 'Group Class (4 Sessions)',
        'purchase_date' => '15 July 2026',
        'expired_date'  => '15 August 2026',
        'status'        => 'Expired',
        'price'         => 'Rp 750.000',
    ],
];

$user_avatar = $_SESSION['user']['avatar_url'] ?? 'assets/images/users/emilia-bonnita.png';

$profile_menu = [
    ['label' => 'My profile',     'href' => 'profile.php',        'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction', 'href' => 'profile-transaction.php', 'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',    'href' => 'profile-schedule.php',    'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',     'href' => 'profile-package.php',     'icon' => 'ph ph-package'],
];

$tabs = [
    'active'  => 'Active',
    'history' => 'History',
];

$empty_messages = [
    'active'  => 'There is no active package.',
    'history' => 'There is no package history.',
];

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_modifier(string $status): string
{
    $key = strtolower(trim($status));
    return in_array($key, ['active', 'expired', 'completed'], true) ? $key : 'expired';
}

$tab = $_GET['tab'] ?? 'active';
if (!is_string($tab) || !array_key_exists($tab, $tabs)) {
    $tab = 'active';
}

$counts = [
    'active'  => count($active_packages),
    'history' => count($history_packages),
];

$packages = $tab === 'active' ? $active_packages : $history_packages;

$sidebar_current = basename($_SERVER['SCRIPT_NAME'] ?? 'profile-package.php');

$current_page        = '';
$navbar_avatar       = $user_avatar;
$navbar_profile_href = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Package — Sooma Wellness</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Montserrat+Alternates:wght@400&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
      <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/profile-package.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="package-page">

    <aside class="menu-card" aria-labelledby="menu-title">
        <h1 class="menu-card__title" id="menu-title">My Package</h1>

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

    <div class="package-content">

        <nav class="pkg-tabs" aria-label="Package filter">
            <?php $i = 0; foreach ($tabs as $key => $label): ?>
                <?php if ($i++ > 0): ?><span class="pkg-tabs__sep" aria-hidden="true"></span><?php endif; ?>
                <a class="pkg-tabs__item<?= $key === $tab ? ' active' : '' ?>"
                   href="?tab=<?= e($key) ?>"
                   <?= $key === $tab ? 'aria-current="true"' : '' ?>>
                    <span class="pkg-tabs__label"><?= e($label) ?></span>
                    <span class="pkg-tabs__count" aria-label="<?= (int) $counts[$key] ?> packages"><?= (int) $counts[$key] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <section aria-label="<?= e($tabs[$tab]) ?> packages">
            <?php if (empty($packages)): ?>

                <p class="pkg-empty"><?= e($empty_messages[$tab]) ?></p>

            <?php else: ?>

                <ul class="pkg-list">
                    <?php foreach ($packages as $pkg):
                        $modifier = status_modifier($pkg['status']);
                    ?>
                        <li class="pkg-card">
                            <div class="pkg-card__head">
                                <div>
                                    <h2 class="pkg-card__name"><?= e($pkg['name']) ?></h2>
                                    <p class="pkg-card__type"><?= e($pkg['type']) ?></p>
                                </div>
                                <span class="pkg-badge pkg-badge--<?= e($modifier) ?>"><?= e($pkg['status']) ?></span>
                            </div>

                            <dl class="pkg-card__meta">
                                <div class="pkg-card__meta-item">
                                    <dt>Purchased</dt>
                                    <dd><?= e($pkg['purchase_date']) ?></dd>
                                </div>
                                <div class="pkg-card__meta-item">
                                    <dt>Expired</dt>
                                    <dd><?= e($pkg['expired_date']) ?></dd>
                                </div>
                                <?php if (!empty($pkg['remaining'])): ?>
                                    <div class="pkg-card__meta-item">
                                        <dt>Remaining</dt>
                                        <dd><?= e($pkg['remaining']) ?></dd>
                                    </div>
                                <?php endif; ?>
                                <div class="pkg-card__meta-item">
                                    <dt>Price</dt>
                                    <dd><?= e($pkg['price']) ?></dd>
                                </div>
                            </dl>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>
        </section>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>