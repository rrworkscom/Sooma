<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? [
    'name'        => 'Emilia Bonnita',
    'email'       => 'emilia.bonnie@gmail.com',
    'phone'       => '082765XXXXXX',
    'joined_date' => '2026-09-17',
    'dob'         => '2000-01-02',
    'gender'      => 'Female',
    'address'     => '',
    'avatar_url'  => 'https://i.pinimg.com/736x/ad/25/f5/ad25f5146c840b01509b125c4e0009b8.jpg',
];

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_date(?string $iso): string
{
    $ts = $iso ? strtotime($iso) : false;
    return $ts ? date('j F Y', $ts) : '--';
}

function or_dash(?string $value): string
{
    $value = trim((string) $value);
    return $value === '' ? '--' : $value;
}

$account_fields = [
    ['label' => 'Email address',    'value' => $user['email'],        'edit' => 'profile-edit.php?field=email'],
    ['label' => 'Phone number',     'value' => $user['phone'],        'edit' => 'profile-edit.php?field=phone'],
    ['label' => 'Account Password', 'value' => '********',            'edit' => 'profile-edit.php?field=password'],
];

$personal_fields = [
    ['label' => 'Name',          'value' => or_dash($user['name'])],
    ['label' => 'Date of Birth', 'value' => format_date($user['dob'])],
    ['label' => 'Gender',        'value' => or_dash($user['gender'])],
    ['label' => 'Address',       'value' => or_dash($user['address'])],
];

$profile_menu = [
    ['label' => 'My profile',     'href' => 'profile.php',        'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction', 'href' => 'profile-transaction.php', 'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',    'href' => 'profile-schedule.php',    'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',     'href' => 'profile-package.php',     'icon' => 'ph ph-package'],
];

$current_page = basename($_SERVER['SCRIPT_NAME'] ?? 'profile.php');

$navbar_avatar       = $user['avatar_url'];
$navbar_profile_href = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile — Sooma Wellness</title>
    <meta name="robots" content="noindex">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caladea:wght@400&family=Montserrat:wght@400;500;600;700&family=Montserrat+Alternates:wght@400&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
      <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php
    $nav_current_backup = $current_page;
    $current_page = 'profile';
    include __DIR__ . '/../includes/header.php';
    $current_page = $nav_current_backup;
    ?>

    <main class="profile-page">

        <section class="profile-card" aria-labelledby="profile-name" style="margin-top: 100px;">
            <div class="profile-card__avatar">
                <img src="<?= e($user['avatar_url']) ?>" alt="Photo of <?= e($user['name']) ?>" width="200" height="200">
            </div>

            <h1 class="profile-card__name" id="profile-name"><?= e($user['name']) ?></h1>
            <p class="profile-card__joined">
                Joined since <time datetime="<?= e($user['joined_date']) ?>"><?= e(format_date($user['joined_date'])) ?></time>
            </p>

            <hr class="profile-card__divider">

            <dl class="account-grid">
                <?php foreach ($account_fields as $field): ?>
                    <div class="account-item">
                        <dt class="account-item__label"><?= e($field['label']) ?></dt>
                        <dd class="account-item__value">
                            <span><?= e($field['value']) ?></span>
                            <a class="edit-link" href="<?= e($field['edit']) ?>"
                                aria-label="Edit <?= e(strtolower($field['label'])) ?>">Edit</a>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <div class="profile-main" style="margin-bottom: 45px;">

            <section class="info-card" aria-labelledby="personal-title">
                <h2 class="info-card__title" id="personal-title">Personal Information</h2>
                <a class="info-card__edit" href="profile-edit.php">Edit</a>

                <dl class="info-list">
                    <?php foreach ($personal_fields as $field): ?>
                        <div class="info-list__item">
                            <dt><?= e($field['label']) ?></dt>
                            <dd><?= e($field['value']) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <aside class="menu-card" aria-labelledby="menu-title">
                <h2 class="menu-card__title" id="menu-title">My Profile</h2>

                <nav aria-label="Profile menu">
                    <ul class="menu-list">
                        <?php foreach ($profile_menu as $item):
                            $is_active = basename($item['href']) === $current_page;
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

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>