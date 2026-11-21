<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$timetableUrl = 'timetable.php';

$sessionUser = $_SESSION['user'] ?? [];
$currentUser = [
    'name'  => (string) ($sessionUser['full_name'] ?? $sessionUser['name'] ?? 'Tiara Pasha'),
    'phone' => (string) ($sessionUser['phone'] ?? '081234567890'),
    'email' => (string) ($sessionUser['email'] ?? 'tiara@example.com'),
];

$classesList = [
    [
        'class_name' => 'Private Reformer Pilates',
        'category'   => 'Reformer Pilates',
        'level'      => 'All Levels (Customized)',
        'price'      => 180000,
        'image'      => '../assets/images/class-pilates.jpg',
    ],
    [
        'class_name' => 'Private Regular Yoga',
        'category'   => 'Regular Yoga',
        'level'      => 'All Levels (Customized)',
        'price'      => 65000,
        'image'      => '../assets/images/class-yoga.jpg',
    ],
    [
        'class_name' => 'Private Mat Pilates',
        'category'   => 'Mat Pilates',
        'level'      => 'Beginner - Intermediate',
        'price'      => 250000,
        'image'      => '../assets/images/class-pilates.jpg',
    ],
];

$coachesList = [
    'Alifa',
    'Bintari',
    'Bunga',
    'Grace',
    'Ulung',
    'Vanessa',
];

function rp_attendee_card($idx, array $a, bool $removable): void
{
    $i = (string) $idx;
    if ($i === '0') {
        $userName = trim((string) ($a['name'] ?? ''));
        $title = $userName !== '' ? $userName : 'Attendee 1';
    } else {
        $number = ctype_digit($i) ? (int) $idx + 1 : '';
        $title = $number ? 'Attendee ' . $number : 'Attendee';
    }
    $bodyId = "rp-attendee-body-{$i}";
?>
    <div class="bd-attendee" data-attendee>
        <div class="bd-attendee__head">
            <button type="button" class="bd-attendee__toggle" data-toggle-attendee
                aria-expanded="true" aria-controls="<?= e($bodyId) ?>">
                <i class="ph ph-user"></i>
                <span class="js-attendee-title"><?= e($title) ?></span>
            </button>
            <?php if ($removable): ?>
                <button type="button" class="bd-attendee__remove" data-remove-attendee aria-label="Remove attendee">
                    <i class="bi bi-x-lg"></i>
                </button>
            <?php endif; ?>
        </div>

        <div id="<?= e($bodyId) ?>" class="bd-attendee__panel">
            <div class="bd-attendee__body">
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-name">Name</label>
                    <input type="text" class="bd-input"
                        id="a<?= e($i) ?>-name" name="attendees[<?= e($i) ?>][name]"
                        value="<?= e($a['name'] ?? '') ?>" required data-attendee-name>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-phone">Phone number</label>
                    <input type="tel" inputmode="tel" class="bd-input"
                        id="a<?= e($i) ?>-phone" name="attendees[<?= e($i) ?>][phone]"
                        value="<?= e($a['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-email">Email</label>
                    <input type="email" class="bd-input"
                        id="a<?= e($i) ?>-email" name="attendees[<?= e($i) ?>][email]"
                        value="<?= e($a['email'] ?? '') ?>" required>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-medical">Medical Condition</label>
                    <textarea class="bd-input bd-input--area" id="a<?= e($i) ?>-medical"
                        name="attendees[<?= e($i) ?>][medical]" placeholder="(Optional)"><?= e($a['medical'] ?? '') ?></textarea>
                </div>
                <label class="bd-check">
                    <input type="checkbox"
                        name="attendees[<?= e($i) ?>][not_pregnant]"
                        value="1"
                        <?= !empty($a['not_pregnant']) ? 'checked' : '' ?> required>
                    <span class="bd-check__box" aria-hidden="true">
                        <i class="ph ph-square"></i>
                        <i class="ph ph-check-square"></i>
                    </span>
                    <span>Participant is <strong>not</strong> currently pregnant</span>
                </label>
            </div>
        </div>
    </div>
<?php
}

$defaultAttendees = [[
    'name'         => $currentUser['name'],
    'phone'        => $currentUser['phone'],
    'email'        => $currentUser['email'],
    'medical'      => '',
    'not_pregnant' => '1',
]];

$minDate     = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$activeClass = $classesList[0];
$initDateObj = new DateTimeImmutable($minDate);

$pageTitle  = 'Request Private Session';
$bodyClass  = 'booking-detail-page request-private-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/booking-detail.css">
<link rel="stylesheet" href="../assets/css/request-private.css">

<main class="bd-page">
    <a href="<?= e($timetableUrl) ?>" class="bd-back">
        <span class="bd-back__icon"><i class="bi bi-chevron-left"></i></span>
        <span>Timetable</span>
    </a>

    <h1 class="bd-title">Request Private Session</h1>

    <div class="bd-layout" id="rp-success-view" hidden>
        <div class="bd-left">
            <div class="bd-alert rp-alert--success">
                <strong>Private Request Submitted!</strong> Your private session request is now waiting for studio admin confirmation.
            </div>

            <div class="rp-status-card">
                <div class="rp-status-card__head">
                    <div>
                        <div class="rp-status-card__label">Request Code</div>
                        <strong class="rp-status-card__code" id="rp-res-code">PRV-20261121-89A2F</strong>
                    </div>
                    <span class="rp-status-badge">
                        <i class="ph-fill ph-hourglass-medium"></i>
                        Pending Review
                    </span>
                </div>

                <p class="rp-status-card__desc">
                    Our studio team is checking coach &amp; studio room availability for your preferred day and time.
                    Once approved, you will be notified to complete your booking and payment.
                </p>

                <div class="rp-note-box" id="rp-res-details"></div>

                <div class="bd-actions rp-actions--mt">
                    <button type="button" class="bd-add rp-btn-edit" id="rp-edit-again">
                        Make Another Request
                    </button>
                    <a href="<?= e($timetableUrl) ?>" class="bd-continue rp-btn-link">
                        <span>Back to Timetable</span>
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </a>
                </div>
            </div>
        </div>

        <aside class="bd-right">
            <div class="bd-summary">
                <div class="bd-summary__hero" id="rp-res-hero"
                    style="background-image: url('<?= e($activeClass['image']) ?>');">
                    <div class="bd-summary__hero-content">
                        <h2 id="rp-res-title"><?= e($activeClass['class_name']) ?></h2>
                        <p id="rp-res-category"><?= e($activeClass['category']) ?> Class</p>
                    </div>
                </div>

                <ul class="bd-summary__list">
                    <li>
                        <i class="ph-fill ph-clock"></i>
                        <div>
                            <strong id="rp-res-date"><?= e($initDateObj->format('l, j F Y')) ?></strong><br>
                            <span id="rp-res-time">10.00 - 11.00 WIB</span>
                        </div>
                    </li>
                    <li>
                        <i class="ph-fill ph-chalkboard-teacher"></i>
                        <div>
                            Coach <strong id="rp-res-coach">Any Available Coach</strong>
                        </div>
                    </li>
                    <li>
                        <i class="ph-fill ph-align-bottom"></i>
                        <div>
                            Level <strong id="rp-res-level"><?= e($activeClass['level']) ?></strong>
                        </div>
                    </li>
                    <li>
                        <i class="bi bi-geo-alt-fill"></i>
                        <div>
                            <strong>Sooma Wellness</strong><br>
                            Jl Ciliwung no. 62, Blitar
                        </div>
                    </li>
                </ul>

                <div class="bd-summary__slots">
                    <span class="bd-badge" id="rp-res-badge">1 Person &bull; Private</span>
                </div>
            </div>
        </aside>
    </div>

    <form id="rp-form" data-max="8" novalidate>
        <div class="bd-layout">
            <div class="bd-left">

                <section class="bd-section">
                    <h2 class="bd-section__title">Session &amp; Schedule Preference</h2>
                    <p class="bd-section__hint">
                        Choose your preferred class, day &amp; date, time slot, and coach for your private session.
                    </p>

                    <div class="bd-attendee" data-rp-card>
                        <div class="bd-attendee__head">
                            <button type="button" class="bd-attendee__toggle" data-toggle-panel
                                aria-expanded="true" aria-controls="rp-panel-session">
                                <i class="ph ph-calendar-blank"></i>
                                <span id="rp-card-session-title"><?= e($activeClass['class_name']) ?> &bull; <?= e($initDateObj->format('D, j M')) ?></span>
                            </button>
                        </div>

                        <div id="rp-panel-session" class="bd-attendee__panel">
                            <div class="bd-attendee__body">
                                <div class="bd-field">
                                    <label class="bd-label" for="rp-class">Class Program</label>
                                    <select class="bd-input rp-select" id="rp-class" name="class_index" required>
                                        <?php foreach ($classesList as $idx => $cls): ?>
                                            <option value="<?= (int) $idx ?>"
                                                data-name="<?= e($cls['class_name']) ?>"
                                                data-category="<?= e($cls['category']) ?>"
                                                data-level="<?= e($cls['level']) ?>"
                                                data-price="<?= (int) $cls['price'] ?>"
                                                data-image="<?= e($cls['image']) ?>">
                                                <?= e($cls['class_name']) ?> (Rp<?= number_format((int) $cls['price'], 0, '.', ',') ?> / person)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="rp-grid-2">
                                    <div class="bd-field">
                                        <label class="bd-label" for="rp-date">Preferred Day &amp; Date</label>
                                        <input type="date"
                                            class="bd-input"
                                            id="rp-date"
                                            name="preferred_date"
                                            min="<?= e($minDate) ?>"
                                            value="<?= e($minDate) ?>"
                                            required>
                                        <div class="rp-day-hint" id="rp-day-display">
                                            Day: <strong><?= e($initDateObj->format('l')) ?></strong>
                                        </div>
                                    </div>

                                    <div class="bd-field">
                                        <label class="bd-label" for="rp-time">Preferred Time (WIB)</label>
                                        <select class="bd-input rp-select" id="rp-time" name="start_time" required>
                                            <?php
                                            $timeSlots = ['07:00', '08:30', '10:00', '11:30', '13:00', '14:30', '16:00', '17:30', '19:00'];
                                            foreach ($timeSlots as $slot):
                                            ?>
                                                <option value="<?= e($slot) ?>" <?= $slot === '10:00' ? 'selected' : '' ?>>
                                                    <?= e(str_replace(':', '.', $slot)) ?> WIB (60 Mins)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="bd-field">
                                    <label class="bd-label" for="rp-coach">Preferred Coach</label>
                                    <select class="bd-input rp-select" id="rp-coach" name="preferred_coach">
                                        <option value="">Any Available Coach</option>
                                        <?php foreach ($coachesList as $coachName): ?>
                                            <option value="<?= e($coachName) ?>">
                                                Coach <?= e($coachName) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="bd-field">
                                    <label class="bd-label" for="rp-notes">Session Focus / Special Notes</label>
                                    <textarea class="bd-input bd-input--area"
                                        id="rp-notes"
                                        name="notes"
                                        placeholder="(Optional) e.g. Beginner friendly, posture correction, or rehab focus"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bd-section">
                    <h2 class="bd-section__title">Attendee List</h2>
                    <p class="bd-section__hint">
                        You can add max <strong>4</strong> participants for your private session.
                    </p>

                    <div id="rp-attendee-list">
                        <?php foreach ($defaultAttendees as $i => $a) {
                            rp_attendee_card($i, $a, $i > 0);
                        } ?>
                    </div>

                    <button type="button" class="bd-add" id="rp-add-attendee">
                        + Add Attendee
                    </button>
                </section>

                <section class="bd-section">
                    <h2 class="bd-section__title">Estimated Session Fee</h2>
                    <p class="bd-section__hint">
                        No payment is required now. Payment will be available after your private schedule is approved.
                    </p>

                    <div class="bd-total">
                        <div class="bd-total__row">
                            <span class="bd-total__label" id="rp-rate-label">
                                Subtotal (1 &times; Rp<?= number_format((int) $activeClass['price'], 0, '.', ',') ?>)
                            </span>
                            <span class="bd-price bd-price--sm" id="rp-subtotal">
                                <em>Rp</em><?= number_format((int) $activeClass['price'], 0, '.', ',') ?>
                            </span>
                        </div>

                        <div class="bd-total__row bd-total__row--grand">
                            <span class="bd-total__label">Estimated Total</span>
                            <span class="bd-price bd-price--lg" id="rp-total">
                                <em>Rp</em><?= number_format((int) $activeClass['price'], 0, '.', ',') ?>
                            </span>
                        </div>
                    </div>
                </section>

                <section class="bd-section">
                    <h2 class="bd-section__title">Term &amp; Condition</h2>
                    <a href="#" class="bd-terms" id="bd-open-tnc">
                        Sooma Wellness Term &amp; Condition
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <label class="bd-check bd-check--mt">
                        <input type="checkbox" name="agree" value="1" id="rp-agree">
                        <span class="bd-check__box" aria-hidden="true">
                            <i class="ph ph-square"></i>
                            <i class="ph ph-check-square"></i>
                        </span>
                        <span>I agree to the T&amp;C</span>
                    </label>
                </section>

                <div class="bd-actions">
                    <button type="submit" class="bd-continue" id="rp-submit" disabled>
                        <span>Submit Private Request</span>
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </button>
                </div>
            </div>

            <aside class="bd-right">
                <div class="bd-summary">
                    <div class="bd-summary__hero" id="rp-sum-hero"
                        style="background-image: url('<?= e($activeClass['image']) ?>');">
                        <div class="bd-summary__hero-content">
                            <h2 id="rp-sum-title"><?= e($activeClass['class_name']) ?></h2>
                            <p id="rp-sum-category"><?= e($activeClass['category']) ?> Class</p>
                        </div>
                    </div>

                    <ul class="bd-summary__list">
                        <li>
                            <i class="ph-fill ph-clock"></i>
                            <div>
                                <strong id="rp-sum-date"><?= e($initDateObj->format('l, j F Y')) ?></strong><br>
                                <span id="rp-sum-time">10.00 - 11.00 WIB</span>
                            </div>
                        </li>
                        <li>
                            <i class="ph-fill ph-chalkboard-teacher"></i>
                            <div>
                                Coach <strong id="rp-sum-coach">Any Available Coach</strong>
                            </div>
                        </li>
                        <li>
                            <i class="ph-fill ph-align-bottom"></i>
                            <div>
                                Level <strong id="rp-sum-level"><?= e($activeClass['level']) ?></strong>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <strong>Sooma Wellness</strong><br>
                                Jl Ciliwung no. 62, Blitar
                            </div>
                        </li>
                    </ul>

                    <div class="bd-summary__slots">
                        <span class="bd-badge" id="rp-slots-badge">1 Person &bull; Private</span>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <template id="rp-attendee-template">
        <?php rp_attendee_card('__INDEX__', ['not_pregnant' => '1'], true); ?>
    </template>
</main>

<div class="tnc-overlay" id="tnc-modal" aria-hidden="true">
    <div class="tnc-modal" role="dialog" aria-modal="true" aria-labelledby="tnc-title">
        <div class="tnc-header">
            <h2 id="tnc-title">Sooma Wellness Terms &amp; Conditions</h2>
            <p>
                Please read and understand the studio's<br>
                rules before submitting your request
            </p>
        </div>

        <div class="tnc-content" id="tnc-content">
            <section class="tnc-section">
                <h3>Reservation &amp; Payment</h3>
                <ul>
                    <li>Private session requests are subject to studio room and coach availability.</li>
                    <li>Payment must be made via BCA bank transfer after admin approval and is non-refundable.</li>
                    <li>Rescheduling must be done at least 12 hours before class (limited to one time).</li>
                </ul>
            </section>

            <section class="tnc-section">
                <h3>Cancellation &amp; Rescheduling</h3>
                <ul>
                    <li>Cancellations made less than 12 hours in advance are considered a no-show, and the session is forfeited.</li>
                </ul>
            </section>

            <section class="tnc-section">
                <h3>Participant Guidelines</h3>
                <ul>
                    <li>Arrive at least 10 minutes before class for preparation.</li>
                    <li>It is mandatory to wear grip socks and comfortable activewear.</li>
                    <li>Participants must inform the instructor if they have any injuries or medical conditions.</li>
                    <li>Bringing or consuming food inside the studio is prohibited.</li>
                </ul>
            </section>
        </div>

        <div class="tnc-footer">
            <button type="button" id="tnc-accept">I Understand</button>
        </div>
    </div>
</div>

<script src="../assets/js/request-private.js" defer></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>