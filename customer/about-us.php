<?php

declare(strict_types=1);

/* =========================================================
   Sooma Wellness — About Us
   Source: Figma node 17:107 (UI - About Us, 1440px frame)
   ========================================================= */

/* ---------- Helpers ---------- */

/** Escape output HTML untuk keamanan XSS */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Escape text, lalu ubah *teks* menjadi <em>teks</em> untuk aksen font italic khas Figma. */
function em_text(string $text): string
{
    return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($text));
}

/** Render logo Sooma dengan crop CSS persis spesifikasi Figma. */
function logo(string $file_path, array $crop, string $modifier = ''): string
{
    $style = sprintf('--h:%s;--l:%s;--t:%s;--w:%s', $crop['h'], $crop['l'], $crop['t'], $crop['w']);
    return sprintf(
        '<span class="logo %s" style="%s"><img src="%s" alt="Sooma Wellness"></span>',
        e($modifier),
        e($style),
        e($file_path)
    );
}

/* ---------- Page Meta ---------- */

$page = [
    'title'       => 'About Us — Sooma Wellness',
    'description' => 'Sooma Wellness helps you build a strong, balanced, and pain-free body through guided and mindful pilates and yoga.',
    'current'     => 'about',
    'book_url'    => 'booking.php',
];


$logo_crop_main   = ['h' => '388.53%', 'l' => '-15.51%', 't' => '-143.58%', 'w' => '130.11%'];
$logo_crop_footer = ['h' => '389.52%', 'l' => '-15.27%', 't' => '-138.74%', 'w' => '130.49%'];


$hero = [
    'title'   => 'About *Sooma Wellness*',
    'tagline' => '“Designing an approach that respects and understands the body as it is.”',
    'cta'     => 'Book Now',
    'image'   => '../assets/images/about-us.png',
];


$story = [
    'heading'    => 'Get to Know More About',
    'paragraphs' => [
        'Sooma Wellness helps you build a strong, balanced, and pain-free body through guided and mindful pilates and yoga. With classes for all levels led by certified instructors, Sooma Wellness is committed to helping you feel more supported, confident, and comfortable in your daily life.',
        'Sooma Wellness was born from a personal journey of living with scoliosis—an experience that highlighted how challenging it is to find the right body support. From this foundation, the vision of Sooma Wellness was formed: “Designing an approach that respects and understands the body as it is.”',
    ],
    'image'     => '../assets/images/about-story.png',
    'image_alt' => 'Instructor performing a pilates stretch',
];


$vision = [
    'heading'   => '*Harmonize* Your Body and Mind with Us',
    'text'      => 'Master your movement with our Pilates and Yoga sessions. Whether you’re strengthening your core on the reformer or finding your daily flow on the mat, our certified coaches are here to support every level of your wellness journey.',
    'image'     => '../assets/images/about-stats.png',
    'image_alt' => 'Reformer pilates session',
    'position'  => '73% 51.5%',
];

$stats = [
    ['value' => '2050+', 'label' => 'Pilates Classes'],
    ['value' => '550+',  'label' => 'Yoga Classes'],
];

$services_intro = [
    'heading'  => 'Our *Services*',
    'subtitle' => 'Mindful practices designed to support every step of your unique journey',
    'cta'      => 'See more',
    'cta_url'  => 'class.php',
];

$services = [
    [
        'title'       => 'Reformer Pilates',
        'description' => 'Practice for core strength, posture alignment, and controlled balance',
        'image'       => '../assets/images/about-reformer.png',
        'position'    => '50% 61.7%',
        'align'       => 'left',
    ],
    [
        'title'       => 'Regular Yoga',
        'description' => 'Mat practices to improve flexibility, relieve stress, and restore balance',
        'image'       => '../assets/images/about-yoga.png',
        'position'    => '50% 73.8%',
        'align'       => 'right',
    ],
    [
        'title'       => 'Prenatal Yoga',
        'description' => 'Gentle, safe movements for expecting mothers’ physical comfort and preparation',
        'image'       => '../assets/images/about-prenatal.png',
        'position'    => '50% 60.6%',
        'align'       => 'left',
    ],
    [
        'title'       => 'Mat Pilates',
        'description' => 'Mat-based exercises designed to enhance total body stability and posture',
        'image'       => '../assets/images/about-mat.png',
        'position'    => '50% 72.8%',
        'align'       => 'right',
    ],
];

$values_intro = [
    'heading'  => 'Our *Core Values*',
    'subtitle' => 'Everything we do is designed to help you feel supported and confident every day',
];

$core_values = [
    ['title' => 'Dynamic Event & Workshop',  'image' => '../assets/images/about-core1.png',   'position' => '5% 2%'],
    ['title' => 'Mindful & Guided Practice', 'image' => '../assets/images/about-core2.png', 'position' => '50% 50%'],
    ['title' => 'Certifies & Expert Coaches', 'image' => '../assets/images/about-core3.png',  'position' => '14% 23%'],
    ['title' => 'Flexible & Custom Classes', 'image' => '../assets/images/about-core4.png', 'position' => '50% 50%'],
];


$coaches_intro = [
    'heading'  => 'Get to Know Our *Coaches* in The Row',
    'subtitle' => 'Connect with our dedicated trainers, bringing mindful movement and expert care to every session',
];

$coaches = [
    ['name' => 'Coach Alifa',   'image' => '../assets/images/coach-alifa.jpg',   'position' => '53% 74%'],
    ['name' => 'Coach Bintari', 'image' => '../assets/images/coach-bintari.jpg', 'position' => '22% 61%'],
    ['name' => 'Coach Bunga',   'image' => '../assets/images/coach-bunga.jpg',   'position' => '55% 67%'],
    ['name' => 'Coach Grace',   'image' => '../assets/images/coach-grace.jpg',   'position' => '56% 2%'],
];

$experience = [
    'heading' => 'Experience Our *Space*',
    'text'    => 'Designed with warmth and calm to make every session feel like a daily retreat',
    'icon'    => '../assets/images/sooma-icon.png',
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page['title']) ?></title>
    <meta name="description" content="<?= e($page['description']) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caladea:ital,wght@0,400;0,700;1,400;1,700&family=Montserrat+Alternates:wght@400&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/about-us.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>
    <?php require_once '../includes/header.php'; ?>

    <section class="hero" aria-labelledby="hero-title">
        <div class="hero__media" aria-hidden="true">
            <img src="<?= e($hero['image']) ?>" alt="">
        </div>

        <div class="hero__content">
            <h1 class="hero__title" id="hero-title"><?= em_text($hero['title']) ?></h1>
            <p class="hero__tagline"><?= e($hero['tagline']) ?></p>
            <a class="btn btn--outline" href="<?= e($page['book_url']) ?>"><?= e($hero['cta']) ?></a>
        </div>
    </section>

    <main>

        <section class="section story" aria-labelledby="story-title">
            <div class="container story__grid">
                <div class="story__text">
                    <div class="story__heading">
                        <h2 id="story-title"><?= e($story['heading']) ?></h2>
                        <?= logo('../assets/images/sooma-logo-black.png', $logo_crop_main, 'logo--story') ?>
                    </div>
                    <?php foreach ($story['paragraphs'] as $paragraph): ?>
                        <p><?= e($paragraph) ?></p>
                    <?php endforeach; ?>
                </div>

                <figure class="frame story__media" style="--r:var(--radius-lg)">
                    <img src="<?= e($story['image']) ?>" alt="<?= e($story['image_alt']) ?>" loading="lazy">
                </figure>
            </div>
        </section>

        <section class="section vision" aria-labelledby="vision-title">
            <div class="container vision__grid">
                <figure class="frame vision__media" style="--r:var(--radius-lg)">
                    <img src="<?= e($vision['image']) ?>" alt="<?= e($vision['image_alt']) ?>"
                        style="object-position:<?= e($vision['position']) ?>" loading="lazy">
                </figure>

                <div class="vision__body">
                    <h2 class="vision__title" id="vision-title"><?= em_text($vision['heading']) ?></h2>
                    <p class="vision__text"><?= e($vision['text']) ?></p>

                    <dl class="stats">
                        <?php foreach ($stats as $stat): ?>
                            <div class="stats__item">
                                <dt class="stats__label"><?= e($stat['label']) ?></dt>
                                <dd class="stats__value"><?= e($stat['value']) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>
        </section>

        <section class="section services" aria-labelledby="services-title">
            <div class="container">
                <header class="section-head section-head--center services__head">
                    <h2 id="services-title"><?= em_text($services_intro['heading']) ?></h2>
                    <p><?= e($services_intro['subtitle']) ?></p>
                </header>

                <div class="services__grid">
                    <?php foreach ($services as $service): ?>
                        <article class="service-card service-card--<?= e($service['align']) ?>">
                            <img class="service-card__img" src="<?= e($service['image']) ?>" alt=""
                                style="object-position:<?= e($service['position']) ?>" loading="lazy">
                            <div class="service-card__body">
                                <h3><?= e($service['title']) ?></h3>
                                <p><?= e($service['description']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <a class="btn btn--light services__more" href="<?= e($services_intro['cta_url']) ?>">
                    <?= e($services_intro['cta']) ?>
                    <svg class="btn__arrow" width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                        <path d="M4 18 18 4M7 4h11v11" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </section>

        <section class="section values" aria-labelledby="values-title">
            <div class="container container--wide">
                <header class="section-head values__head">
                    <h2 id="values-title"><?= em_text($values_intro['heading']) ?></h2>
                    <p><?= e($values_intro['subtitle']) ?></p>
                </header>

                <ul class="values__grid">
                    <?php foreach ($core_values as $value): ?>
                        <li class="value-card">
                            <div class="value-card__media">
                                <img src="<?= e($value['image']) ?>" alt=""
                                    style="object-position:<?= e($value['position']) ?>" loading="lazy">
                            </div>
                            <h3 class="value-card__title"><?= e($value['title']) ?></h3>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="section coaches" aria-labelledby="coaches-title">
            <div class="container container--wide">
                <header class="section-head section-head--center coaches__head">
                    <h2 id="coaches-title"><?= em_text($coaches_intro['heading']) ?></h2>
                    <p><?= e($coaches_intro['subtitle']) ?></p>
                </header>

                <div class="coaches__panel">
                    <ul class="coaches__grid">
                        <?php foreach ($coaches as $coach): ?>
                            <li class="coach-card">
                                <a class="coach-card__link" href="#" aria-label="<?= e($coach['name']) ?>">
                                    <span class="coach-card__photo">
                                        <img src="<?= e($coach['image']) ?>" alt="<?= e($coach['name']) ?>"
                                            style="object-position:<?= e($coach['position']) ?>" loading="lazy">
                                    </span>
                                    <span class="coach-card__name"><?= e($coach['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ======================= EXPERIENCE ======================= -->
        <section class="section experience" aria-labelledby="experience-title">
            <div class="container experience__grid">
                <img class="experience__icon" src="<?= e($experience['icon']) ?>" alt="" loading="lazy">
                <div class="experience__body">
                    <h2 id="experience-title"><?= em_text($experience['heading']) ?></h2>
                    <p><?= e($experience['text']) ?></p>
                </div>
            </div>
        </section>

    </main>

    <?php require_once '../includes/footer.php'; ?>


</body>

</html>