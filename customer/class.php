<?php
$pageTitle  = 'Class';
$bodyClass  = 'class-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Menu navigasi (Header)
$nav_items = [
    ['label' => 'Home',       'href' => 'index.php',     'key' => 'home'],
    ['label' => 'About Us',   'href' => 'about.php',     'key' => 'about'],
    ['label' => 'Class',      'href' => 'class.php',     'key' => 'class'],
    ['label' => 'Packages',   'href' => 'packages.php',  'key' => 'packages'],
    ['label' => 'Timetable',  'href' => 'timetable.php', 'key' => 'timetable'],
    ['label' => 'Contact Us', 'href' => 'contact.php',   'key' => 'contact'],
];
$current_page = 'class';

// Tab filter
$tabs = [
    'all'     => 'All',
    'group'   => 'Group Classes',
    'private' => 'Private Classes',
];

// Daftar kelas
// 'types'  : dipakai untuk filter tab (ASUMSI — silakan sesuaikan)
// 'badge'  : opsional, kosongkan ('') bila tidak ada
// 'crop'   : true bila gambar di Figma di-crop/zoom (Prenatal Yoga & Mat Pilates)
$classes = [
    [
        'slug'        => 'reformer-pilates',
        'title'       => 'Reformer Pilates',
        'duration'    => '60 mins',
        'level'       => 'All levels',
        'badge'       => '',
        'image'       => '../assets/images/class-pilates.jpg',
        'crop'        => false,
        'types'       => ['group'],
        'description' => 'A precision workout using the Reformer machine, designed to strengthen the core, improve posture, and maximize flexibility and balance in a calm environment.',
    ],
    [
        'slug'        => 'yoga',
        'title'       => 'Yoga',
        'duration'    => '60 mins',
        'level'       => 'All levels',
        'badge'       => '',
        'image'       => '../assets/images/class-yoga.jpg',
        'crop'        => false,
        'types'       => ['group'],
        'description' => 'A mind-body practice that combines breathing exercises, structured poses, and light meditation. Designed to improve flexibility and strengthen the core.',
    ],
    [
        'slug'        => 'prenatal-yoga',
        'title'       => 'Prenatal Yoga',
        'duration'    => '60 mins',
        'level'       => 'All levels',
        'badge'       => '',
        'image'       => '../assets/images/class-prenatalyoga.jpg',
        'crop'        => true,
        'types'       => ['private'],
        'description' => 'Physical and breathing exercises specifically designed for pregnant women to relieve muscle aches, strengthen the pelvic floor muscles, and prepare both physically and mentally for childbirth.',
    ],
    [
        'slug'        => 'mat-pilates',
        'title'       => 'Mat Pilates',
        'duration'    => '60 mins',
        'level'       => 'All levels',
        'badge'       => 'Only For Event',
        'image'       => '../assets/images/class-matpilates.jpg',
        'crop'        => true,
        'types'       => ['group'],
        'description' => 'Mat-based exercises that focus on strengthening the core muscles, improving posture, and enhancing flexibility using controlled, natural body weight.',
    ],
];


$active_tab = $_GET['type'] ?? 'all';
if (!array_key_exists($active_tab, $tabs)) {
    $active_tab = 'all';
}

$visible_classes = array_filter(
    $classes,
    fn(array $c): bool => $active_tab === 'all' || in_array($active_tab, $c['types'], true)
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Class — Sooma Wellness</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Montserrat+Alternates:wght@400&display=swap" rel="stylesheet">
    <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
        <link rel="stylesheet" href="../assets/css/header.css">
        <link rel="stylesheet" href="../assets/css/class.css">
        <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>
        <?php require_once '../includes/header.php'; ?>
    <main class="page">

        <section class="page-head">
            <h1 class="page-title">Class</h1>

            <nav class="tabs" aria-label="Class filter">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="tabs__item<?= $key === $active_tab ? ' is-active' : '' ?>"
                        href="?type=<?= e($key) ?>"
                        <?= $key === $active_tab ? 'aria-current="true"' : '' ?>>
                        <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </section>

        <section class="class-grid" aria-label="Class list">
            <?php if (empty($visible_classes)): ?>
                <p class="class-grid__empty">No classes available in this category yet.</p>
            <?php endif; ?>

            <?php foreach ($visible_classes as $class): ?>
                <article class="class-card">
                    <div class="class-card__media">
                        <img class="<?= !empty($class['crop']) ? 'is-cropped' : '' ?>"
                            src="<?= e($class['image']) ?>"
                            alt="<?= e($class['title']) ?> class">
                    </div>

                    <div class="class-card__body">
                        <div class="class-card__title-row">
                            <h2 class="class-card__title"><?= e($class['title']) ?></h2>

                            <?php if (!empty($class['badge'])): ?>
                                <span class="badge"><?= e($class['badge']) ?></span>
                            <?php endif; ?>
                        </div>

                        <ul class="class-card__meta">
                            <li class="class-card__meta-item">
                                <i class="ph ph-hourglass" alt="" width="44" height="44"></i>
                                <span><?= e($class['duration']) ?></span>
                            </li>
                            <li class="class-card__meta-item">
                                <i class="ph ph-align-bottom" alt="" width="44" height="44"></i>
                                <span><?= e($class['level']) ?></span>
                            </li>
                        </ul>

                        <p class="class-card__desc"><?= e($class['description']) ?></p>
                        <a class="button button-sage" style="margin-top: 1rem;" href="class-detail.php?slug=<?= urlencode($class['slug']) ?>">
                            View More
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
        
    </main>
            <?php require_once '../includes/footer.php'; ?>

</body>
</html>