<?php
$cancellation_default = 'Appointment cancelled within 12 hours of start time will be considered as late cancellation and credit(s) will not be refunded.';

$classes_db = [
    'reformer-pilates' => [
        'title'               => 'Reformer Pilates',
        'slug'                => 'reformer-pilates',
        'image'               => '../assets/images/class-pilates.jpg',
        'duration'            => '60 mins',
        'difficulty'          => 'All Level',
        'about'               => 'Precision training using a specialized reformer machine to strengthen the core muscles, improve posture, and enhance flexibility and balance in a targeted manner. It is highly effective for achieving a more slender physique and relieving daily muscle tension in a calm environment.',
        'what_you_get'        => ['Water Bottle', 'Towel', 'Grip Socks'],
        'cancellation_policy' => $cancellation_default,
    ],
    'yoga' => [
        'title'               => 'Yoga',
        'slug'                => 'yoga',
        'image'               => '../assets/images/class-yoga.jpg',
        'duration'            => '60 mins',
        'difficulty'          => 'All Level',
        'about'               => 'A mind-body practice that combines breathing exercises, structured poses, and light meditation. Designed to improve flexibility, strengthen the core, and bring a sense of calm to your day.',
        'what_you_get'        => ['Water Bottle', 'Towel', 'Yoga Mat'],
        'cancellation_policy' => $cancellation_default,
    ],
    'prenatal-yoga' => [
        'title'               => 'Prenatal Yoga',
        'slug'                => 'prenatal-yoga',
        'image'               => '../assets/images/class-prenatalyoga.jpg',
        'duration'            => '60 mins',
        'difficulty'          => 'All Level',
        'about'               => 'A gentle yoga class tailored for expectant mothers, focusing on safe movement, breathing techniques, and relaxation to support your body and mind throughout pregnancy.',
        'what_you_get'        => ['Water Bottle', 'Towel', 'Yoga Mat'],
        'cancellation_policy' => $cancellation_default,
    ],
    'mat-pilates' => [
        'title'               => 'Mat Pilates',
        'slug'                => 'mat-pilates',
        'image'               => '../assets/images/class-matpilates.jpg',
        'duration'            => '60 mins',
        'difficulty'          => 'All Level',
        'about'               => 'Floor-based Pilates that builds core strength, stability, and body awareness through controlled movements. Available only during special events.',
        'what_you_get'        => ['Water Bottle', 'Towel', 'Pilates Mat'],
        'cancellation_policy' => $cancellation_default,
    ],
];

const DEFAULT_SLUG = 'reformer-pilates';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$raw_slug = $_GET['slug'] ?? '';
$slug     = is_string($raw_slug)
    ? strtolower(trim(htmlspecialchars($raw_slug, ENT_QUOTES, 'UTF-8')))
    : '';

if ($slug === '') {
    $slug = DEFAULT_SLUG;
}

$class = (preg_match('/^[a-z0-9-]+$/', $slug) && array_key_exists($slug, $classes_db))
    ? $classes_db[$slug]
    : null;

if ($class === null) {
    http_response_code(404);
}

$page_title = $class ? $class['title'] . ' — Sooma Wellness' : 'Class Not Found — Sooma Wellness';
$page_desc  = $class ? $class['about'] : 'The class you are looking for could not be found.';

$current_page = 'class.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <meta name="description" content="<?= e($page_desc) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Montserrat+Alternates:wght@400&display=swap" rel="stylesheet">
      <link
        rel="stylesheet"
        type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/class-detail.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="class-page" >

        <?php if ($class === null): ?>

            <section class="class-notfound">
                <h1 class="class-notfound__title">Class not found</h1>
                <p class="class-notfound__text">
                    Sorry, we couldn't find the class you were looking for. It may have been moved or no longer available.
                </p>
                <a class="btn btn--primary" href="class.php">Back to Classes</a>
            </section>

        <?php else: ?>

            <section class="class-hero" aria-labelledby="class-title" >
                <div class="class-hero__info" >
                    <h1 class="class-hero__title" id="class-title"><?= e($class['title']) ?></h1>

                    <ul class="class-meta">
                        <li class="class-meta__item">
                            <i class="ph ph-hourglass" style="font-size: 30px;"></i>
                            <div>
                                <strong class="class-meta__label">Duration</strong>
                                <span class="class-meta__value"><?= e($class['duration']) ?></span>
                            </div>
                        </li>
                        <li class="class-meta__item class-meta__item--level">
                            <i class="ph ph-align-bottom" style="font-size: 30px;"></i>
                            <div>
                                <strong class="class-meta__label">Difficulty Level</strong>
                                <span class="class-meta__value"><?= e($class['difficulty']) ?></span>
                            </div>
                        </li>
                    </ul>

                    <a class="btn btn--primary class-hero__cta"
                        href="packages.php?class=<?= urlencode($class['slug']) ?>">Book Now</a>
                </div>

                <figure class="class-hero__media" style="margin-top: 25px;">
                    <img src="<?= e($class['image']) ?>" alt="<?= e($class['title']) ?> class at Sooma Wellness">
                </figure>
            </section>

            <div class="class-detail">

                <section class="detail-block detail-block--about" aria-labelledby="about-title">
                    <h2 class="detail-block__title" id="about-title">About the Class</h2>
                    <p class="detail-block__text"><?= e($class['about']) ?></p>
                </section>

                <section class="detail-block detail-block--perks" aria-labelledby="perks-title">
                    <h2 class="detail-block__title" id="perks-title">What Will You Get</h2>
                    <ul class="perk-list">
                        <?php foreach ($class['what_you_get'] as $perk): ?>
                            <li class="perk-list__item"><?= e($perk) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <hr class="class-detail__divider">

                <section class="detail-block detail-block--policy" aria-labelledby="policy-title" style="margin-bottom: 25px;">
                    <h2 class="detail-block__title" id="policy-title">Cancellation policy</h2>
                    <p class="detail-block__text"><?= e($class['cancellation_policy']) ?></p>
                </section>

            </div>

        <?php endif; ?>

    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>