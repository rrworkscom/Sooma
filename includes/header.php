<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle  = $pageTitle ?? 'Sooma Wellness';
$bodyClass  = $bodyClass ?? '';
$hideChrome = $hideChrome ?? false;

$headerType  = $headerType ?? $_SESSION['role'] ?? 'customer';
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('header_e')) {
    function header_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (isset($basePath)) {
    $assetBase = $basePath;
} else {
    $assetBase = file_exists('assets/css/header.css') ? 'assets/' : '../assets/';
}

$menus = [
    'home'       => 'Home',
    'about-us'   => 'About Us',
    'class'      => 'Class',
    'packages'   => 'Packages',
    'timetable'  => 'Timetable',
    'contact-us' => 'Contact Us',
];

$adminMenus = [
    'dashboard_admin.php' => 'Home',
    'scheduleclass.php'   => 'Schedule & Class',
    'booking.php'         => 'Booking',
    'instructors.php'     => 'Instructors',
];

$isAuthPage = (strpos($_SERVER['REQUEST_URI'], 'login') !== false || strpos($_SERVER['REQUEST_URI'], 'regist') !== false);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= header_e($pageTitle) ?> | Sooma Wellness</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php if ($headerType === 'admin'): ?>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= header_e($assetBase) ?>css/booking.css">
    <?php else: ?>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Montserrat+Alternates:wght@400&display=swap" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
        
        <?php if ($isAuthPage): ?>
            <link rel="stylesheet" href="<?= header_e($assetBase) ?>css/auth.css">
        <?php else: ?>
            <link rel="stylesheet" href="<?= header_e($assetBase) ?>css/header.css">
        <?php endif; ?>
    <?php endif; ?>
</head>

<body class="<?= header_e($bodyClass) ?>">

    <?php if (!$hideChrome): ?>

        <?php if ($headerType === 'admin'): ?>

            <nav class="main-nav admin-nav">
                <a href="dashboard_admin.php" class="nav-logo">
                    <img src="<?= header_e($assetBase) ?>images/sooma-logo-black.png" alt="Sooma Wellness">
                </a>

                <div class="nav-links">
                    <?php foreach ($adminMenus as $url => $label): ?>
                        <a href="<?= header_e($url) ?>"
                           class="<?= $currentPage === $url ? 'active' : '' ?>">
                            <?= header_e($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a href="../auth/logout.php" class="nav-logout">Logout</a>

                <a href="profile.php" class="nav-profile">
                    <img src="https://i.pinimg.com/736x/ad/25/f5/ad25f5146c840b01509b125c4e0009b8.jpg" alt="Admin Profile">
                </a>
            </nav>

        <?php else: ?>

            <header class="site-header" id="site-header">
                <a class="site-header__logo" href="index.php" aria-label="Sooma Wellness — Home">
                    <img src="<?= header_e($assetBase) ?>images/sooma-logo-white.png" alt="Sooma Wellness">
                </a>

                <input class="site-header__toggle" type="checkbox" id="nav-toggle" aria-label="Toggle menu">
                <label class="site-header__burger" for="nav-toggle" aria-hidden="true">
                    <span></span><span></span><span></span>
                </label>

                <nav class="site-nav" aria-label="Main navigation">
                    <ul class="site-nav__list">
                        <?php foreach ($menus as $slug => $label): 
                            $href = ($slug === 'home') ? 'index.php' : "{$slug}.php";
                            $isActive = (($_GET['page'] ?? '') === $slug) || ($currentPage === "{$slug}.php") || ($currentPage === 'index.php' && $slug === 'home');
                        ?>
                            <li class="site-nav__item">
                                <a class="site-nav__link<?= $isActive ? ' active' : '' ?>"
                                   href="<?= header_e($href) ?>"
                                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                                    <?= header_e($label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <a class="site-header__avatar" href="profile.php" aria-label="My profile">
                     <img src="/assets/images/profile.jpg" alt="Customer Profile">
                </a>
            </header>

        <?php endif; ?>

    <?php endif; ?>