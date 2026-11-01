<?php
$pageTitle  = $pageTitle ?? 'Sooma Wellness';
$bodyClass  = $bodyClass ?? '';
$hideChrome = $hideChrome ?? false;

$headerType = $headerType ?? 'customer';
$currentPage = basename($_SERVER['PHP_SELF']);

$menus = [
    'home'       => 'Home',
    'about-us'   => 'About Us',
    'class'      => 'Class',
    'packages'   => 'Packages',
    'timetable'  => 'Timetable',
    'contact-us' => 'Contact Us',
];

$adminMenus = [
    'dashboard_admin.php'   => 'Home',
    'scheduleclass.php'     => 'Schedule & Class',
    'booking.php'           => 'Booking',
    'instructors.php'       => 'Instructors',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | Sooma Wellness</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <?php if ($headerType === 'admin'): ?>
        <link
            href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/booking.css">
    <?php else: ?>
        <link
            href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/auth.css">
    <?php endif; ?>
</head>

<body class="<?= htmlspecialchars($bodyClass) ?>">

    <?php if (!$hideChrome): ?>

        <?php if ($headerType === 'admin'): ?>

            <nav class="main-nav admin-nav">
                <a href="dashboard_admin.php" class="nav-logo">
                    <img src="/assets/images/sooma-logo-black.png" alt="Sooma Wellness">
                </a>

                <div class="nav-links">
                    <?php foreach ($adminMenus as $url => $label): ?>
                        <a
                            href="<?= htmlspecialchars($url) ?>"
                            class="<?= $currentPage === $url ? 'active' : '' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a href="../auth/logout.php" class="nav-logout">Logout</a>

                <a href="profile.php" class="nav-profile">
                    <img
                        src="https://i.pinimg.com/736x/ad/25/f5/ad25f5146c840b01509b125c4e0009b8.jpg"
                        alt="Admin Profile">
                </a>
            </nav>

        <?php else: ?>

            <nav class="main-nav customer-nav">
                <a href="index.php?page=home" class="nav-logo">
                    <img src="/assets/images/sooma-logo-white.png" alt="Sooma Wellness">
                </a>

                <div class="nav-links">
                    <?php foreach ($menus as $slug => $label): ?>
                        <a
                            href="index.php?page=<?= htmlspecialchars($slug) ?>"
                            class="<?= ($_GET['page'] ?? 'home') === $slug ? 'active' : '' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <a href="profile.php" class="nav-profile">
                    <img src="/assets/images/profile.jpg" alt="Customer Profile">
                </a>
            </nav>

        <?php endif; ?>

    <?php endif; ?>