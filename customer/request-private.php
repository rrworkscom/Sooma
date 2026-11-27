<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/booking-service.php';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function rp_normalize_program(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '' || $v === 'all' || $v === 'all classes' || str_contains($v, 'all class')) {
        return 'all';
    }
    if (str_contains($v, 'pilates') || str_contains($v, 'reformer') || str_contains($v, 'mat')) {
        return 'reformer_pilates';
    }
    if (str_contains($v, 'yoga')) {
        return 'regular_yoga';
    }
    return $v;
}

function rp_normalize_class_type(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '') {
        return '';
    }
    if (str_contains($v, 'private group') || str_contains($v, 'private_group') || str_contains($v, 'private-group')) {
        return 'private_group';
    }
    if (str_contains($v, 'private')) {
        return 'private';
    }
    if (str_contains($v, 'group')) {
        return 'group';
    }
    if ($v === 'all' || $v === 'all types' || str_contains($v, 'all type')) {
        return 'all';
    }
    return '';
}

function rp_package_program_key(array $pkg): string
{
    foreach (['category', 'class_category', 'class_name', 'name', 'package_name'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = rp_normalize_program((string) $pkg[$field]);
            if (in_array($norm, ['all', 'reformer_pilates', 'regular_yoga'], true)) {
                return $norm;
            }
        }
    }
    return 'all';
}

function rp_package_type_key(array $pkg): string
{
    foreach (['pkg_class_type', 'class_type', 'package_type'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = rp_normalize_class_type((string) $pkg[$field]);
            if ($norm !== '') {
                return $norm;
            }
        }
    }

    $combined = trim(implode(' ', array_filter([
        (string) ($pkg['name'] ?? ''),
        (string) ($pkg['package_name'] ?? ''),
        (string) ($pkg['category'] ?? ''),
        (string) ($pkg['class_category'] ?? ''),
        (string) ($pkg['type'] ?? ''),
        (string) ($pkg['class_name'] ?? ''),
    ])));

    $norm = rp_normalize_class_type($combined);
    return $norm !== '' ? $norm : 'group';
}

function rp_is_package_compatible(
    array $pkg,
    string $targetCategory,
    string $targetClassName = '',
    int $attendeeCount = 1
): bool {
    $pkgProg    = rp_package_program_key($pkg);
    $targetProg = rp_normalize_program($targetCategory);
    if (!in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true)) {
        $targetProg = rp_normalize_program($targetClassName);
    }

    $progCompatible = ($pkgProg === 'all' || !in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true))
        ? true
        : ($pkgProg === $targetProg);

    if (!$progCompatible) {
        return false;
    }

    $pkgType    = rp_package_type_key($pkg);
    $targetType = $attendeeCount > 1 ? 'private_group' : 'private';

    return $pkgType === 'all' || $pkgType === $targetType;
}

function rp_fetch_sorted_user_packages(int $userId, array $fallback = []): array
{
    if ($userId <= 0) {
        return $fallback;
    }

    try {
        $pdo = bd_pdo();
        $stmt = $pdo->prepare("
            select up.user_package_id as id,
                   up.user_package_id,
                   up.package_id,
                   up.remaining_sessions as remaining,
                   coalesce(p.total_sessions, up.remaining_sessions, 1) as total,
                   coalesce(p.package_name, 'Class Package') as name,
                   coalesce(
                       nullif(trim(to_jsonb(p)->>'variant_label'), ''),
                       coalesce(p.total_sessions, 1)::text || ' Sessions'
                   ) as type,
                   coalesce(
                       nullif(trim(to_jsonb(p)->>'class_type'), ''),
                       nullif(trim(to_jsonb(p)->>'package_type'), ''),
                       ''
                   ) as pkg_class_type,
                   coalesce(mc.class_name, 'All Classes') as category,
                   up.purchased_at,
                   up.expired_at,
                   coalesce(up.redeem_code, '') as redeem_code
              from sooma_wellness.user_packages up
              join sooma_wellness.customers c on c.customer_id = up.customer_id
              join sooma_wellness.packages p on p.package_id = up.package_id
              left join sooma_wellness.master_classes mc on mc.class_id = p.class_id
             where c.user_id = :uid
               and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
               and up.remaining_sessions > 0
               and (up.expired_at is null or up.expired_at > clock_timestamp())
             order by up.purchased_at asc, up.user_package_id asc
        ");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $ignore) {
    }

    return $fallback;
}

function rp_format_short_date(?string $raw): string
{
    if (!$raw) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($raw))->format('j M Y');
    } catch (Throwable $e) {
        return substr($raw, 0, 10);
    }
}

$timetableUrl = 'timetable.php';
$packagesUrl  = 'packages.php';

$sessionUser = $_SESSION['user'] ?? [];
$userId      = (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0);
$currentUser = [
    'name'  => (string) ($sessionUser['full_name'] ?? $sessionUser['name'] ?? 'Tiara Pasha'),
    'phone' => (string) ($sessionUser['phone'] ?? '081234567890'),
    'email' => (string) ($sessionUser['email'] ?? 'tiara@example.com'),
];

$packages = [];
if ($userId > 0) {
    $fallbackPkgs = [];
    if (function_exists('bd_get_packages')) {
        try {
            $fallbackPkgs = bd_get_packages($userId);
        } catch (Throwable $ignore) {
            $fallbackPkgs = [];
        }
    }
    $packages = rp_fetch_sorted_user_packages($userId, $fallbackPkgs);
}

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
                        <?= !empty($a['not_pregnant']) ? 'unchecked' : '' ?> required>
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

$studioTz    = new DateTimeZone('Asia/Jakarta');
$nowWib      = new DateTimeImmutable('now', $studioTz);
$todayObj    = new DateTimeImmutable('today', $studioTz);
$maxDateObj  = $todayObj->modify('+7 days');
$minDate     = $todayObj->format('Y-m-d');
$maxDate     = $maxDateObj->format('Y-m-d');
$activeClass = $classesList[0];
$initDateObj = $todayObj;

$timeSlots   = ['07:00', '08:30', '10:00', '11:30', '13:00', '14:30', '16:00', '17:30', '19:00'];
$defaultSlot = '10:00';
foreach ($timeSlots as $slot) {
    $slotDt = new DateTimeImmutable($minDate . ' ' . $slot, $studioTz);
    if ($slotDt > $nowWib) {
        $defaultSlot = $slot;
        break;
    }
}
$defaultEndDt  = (new DateTimeImmutable($minDate . ' ' . $defaultSlot, $studioTz))->modify('+1 hour');
$initTimeRange = str_replace(':', '.', $defaultSlot) . ' - ' . $defaultEndDt->format('H.i') . ' WIB';

$initAttendeeCount = count($defaultAttendees);
$compatiblePackagesCount = 0;
$earliestCompatiblePkgId = '';
foreach ($packages as $pkg) {
    if (rp_is_package_compatible($pkg, (string) $activeClass['category'], (string) $activeClass['class_name'], $initAttendeeCount)) {
        $compatiblePackagesCount++;
        if ($earliestCompatiblePkgId === '') {
            $earliestCompatiblePkgId = (string) $pkg['id'];
        }
    }
}
$hasMultipleCompatiblePackages = $compatiblePackagesCount > 1;

$lastSubmitted     = is_array($_SESSION['rp_last_submitted'] ?? null) ? $_SESSION['rp_last_submitted'] : [];
$showSuccessOnLoad = (($_GET['submitted'] ?? '') === '1' && !empty($lastSubmitted));

$rawResCat   = trim((string) ($lastSubmitted['category'] ?? $activeClass['category']));
$resCategory = $rawResCat !== ''
    ? (str_ends_with(strtolower($rawResCat), 'class') ? $rawResCat : $rawResCat . ' Class')
    : ($activeClass['category'] . ' Class');

$resCode      = (string) ($lastSubmitted['booking_code'] ?? 'REQ-0000');
$resHero      = (string) ($lastSubmitted['image'] ?? $activeClass['image']);
$resTitle     = (string) ($lastSubmitted['class_name'] ?? $activeClass['class_name']);
$resDate      = (string) ($lastSubmitted['date_readable'] ?? $initDateObj->format('l, j F Y'));
$resTime      = (string) ($lastSubmitted['time_readable'] ?? $initTimeRange);
$resCoach     = (string) ($lastSubmitted['coach'] ?? 'Any Available Coach');
$resLevel     = (string) ($lastSubmitted['level'] ?? 'Beginner');
$resPeople    = (string) ($lastSubmitted['people_text'] ?? '1 Person');
$resParts     = (string) ($lastSubmitted['participants'] ?? $currentUser['name']);
$resLeadPhone = (string) ($lastSubmitted['lead_phone'] ?? $currentUser['phone']);

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

    <div class="bd-layout" id="rp-success-view"<?= $showSuccessOnLoad ? '' : ' hidden' ?>>
        <div class="bd-left">
            <div class="bd-alert rp-alert--success">
                <strong>Private Request Submitted!</strong> Your private session request is now waiting for studio admin confirmation.
            </div>

            <div class="rp-status-card">
                <div class="rp-status-card__head">
                    <div>
                        <div class="rp-status-card__label">Request Code</div>
                        <strong class="rp-status-card__code" id="rp-res-code"><?= e($resCode) ?></strong>
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

                <div class="rp-note-box" id="rp-res-details">
                    <?php if ($showSuccessOnLoad): ?>
                        <strong>Submitted Details:</strong><br>
                        &bull; Request ID: <strong><?= e($resCode) ?></strong><br>
                        &bull; Class: <?= e($resTitle) ?> (<?= e($resPeople) ?>)<br>
                        &bull; Level: <?= e($resLevel) ?><br>
                        &bull; Schedule: <?= e($resDate) ?>, <?= e($resTime) ?><br>
                        &bull; Coach Preference: <?= e($resCoach) ?><br>
                        &bull; Participants: <?= e($resParts) ?><br>
                        &bull; Lead Contact Phone: <?= e($resLeadPhone) ?>
                    <?php endif; ?>
                </div>

                <div class="bd-actions rp-actions--mt">
                    <button type="button" class="bd-btn-outline rp-btn-edit" id="rp-edit-again">
                        Make Another Request
                    </button>

                    <a href="profile-private-request.php" class="bd-next-link rp-btn-link">
                        <span>Finish My Request</span>
                        <span class="bd-next-circle" aria-hidden="true">
                            <i class="ph-bold ph-arrow-right"></i>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <aside class="bd-right">
            <div class="bd-summary">
                <div class="bd-summary__hero" id="rp-res-hero"
                    style="background-image: url('<?= e($resHero) ?>');">
                    <div class="bd-summary__hero-content">
                        <h2 id="rp-res-title"><?= e($resTitle) ?></h2>
                        <p id="rp-res-category"><?= e($resCategory) ?></p>
                    </div>
                </div>

                <ul class="bd-summary__list">
                    <li>
                        <i class="ph-fill ph-clock"></i>
                        <div>
                            <strong id="rp-res-date"><?= e($resDate) ?></strong><br>
                            <span id="rp-res-time"><?= e($resTime) ?></span>
                        </div>
                    </li>
                    <li>
                        <i class="ph-fill ph-chalkboard-teacher"></i>
                        <div>
                            Coach <strong id="rp-res-coach"><?= e($resCoach) ?></strong>
                        </div>
                    </li>
                    <li>
                        <i class="ph-fill ph-align-bottom"></i>
                        <div>
                            Level <strong id="rp-res-level"><?= e($resLevel) ?></strong>
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
                    <span class="bd-badge" id="rp-res-badge"><?= e($resPeople) ?> &bull; Private</span>
                </div>
            </div>
        </aside>
    </div>

    <form id="rp-form" data-max="4" data-endpoint="../assets/api/submit-private-request.php"<?= $showSuccessOnLoad ? ' hidden' : '' ?>>
        <div class="bd-layout">
            <div class="bd-left">

                <section class="bd-section">
                    <h2 class="bd-section__title">Session &amp; Schedule Preference</h2>
                    <p class="bd-section__hint">
                        Choose your preferred class, day &amp; date, time slot, coach, and level for your private session.
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
                                        <div class="rp-label-row">
                                            <label class="bd-label" for="rp-date">Preferred Day &amp; Date</label>
                                            <span class="rp-date-range-hint">Today &ndash; <?= e($maxDateObj->format('j M Y')) ?></span>
                                        </div>
                                        <input type="date"
                                            class="bd-input"
                                            id="rp-date"
                                            name="preferred_date"
                                            min="<?= e($minDate) ?>"
                                            max="<?= e($maxDate) ?>"
                                            value="<?= e($minDate) ?>"
                                            required>
                                        <div class="rp-day-hint" id="rp-day-display">
                                            Day: <strong><?= e($initDateObj->format('l')) ?></strong>
                                        </div>
                                    </div>

                                    <div class="bd-field">
                                        <label class="bd-label" for="rp-time">Preferred Time (WIB)</label>
                                        <select class="bd-input rp-select" id="rp-time" name="start_time" required>
                                            <?php foreach ($timeSlots as $slot):
                                                $slotDt     = new DateTimeImmutable($minDate . ' ' . $slot, $studioTz);
                                                $isPastSlot = ($slotDt <= $nowWib);
                                            ?>
                                                <option value="<?= e($slot) ?>"
                                                    <?= $slot === $defaultSlot ? 'selected' : '' ?>
                                                    <?= $isPastSlot ? 'disabled' : '' ?>>
                                                    <?= e(str_replace(':', '.', $slot)) ?> WIB (60 Mins)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="rp-grid-2">
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
                                        <label class="bd-label" for="rp-level">Level</label>
                                        <select class="bd-input rp-select" id="rp-level" name="level" required>
                                            <option value="Beginner" selected>Beginner</option>
                                            <option value="Intermediate">Intermediate</option>
                                            <option value="Advanced">Advanced</option>
                                        </select>
                                    </div>
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
                    <h2 class="bd-section__title">Payment Method</h2>
                    <div class="bd-pay" role="radiogroup" aria-label="Payment method">
                        <label class="bd-pay__opt">
                            <input type="radio" name="payment_method" value="dropin" checked>
                            <span class="bd-pay__card">
                                <i class="bi bi-cash-stack bd-pay__icon"></i>
                                Drop-in / After Approval
                            </span>
                        </label>

                        <label class="bd-pay__opt">
                            <input type="radio" name="payment_method" value="package">
                            <span class="bd-pay__card">
                                <i class="bi bi-inbox-fill bd-pay__icon"></i>
                                Package Plan
                            </span>
                        </label>
                    </div>

                    <div id="rp-packages" class="bd-packages" hidden>
                        <div class="bd-packages__header">
                            <h3 class="bd-packages__title">My Packages</h3>
                            <button type="button" class="bd-packages__clear" id="rp-clear-package" hidden>
                                <?= $hasMultipleCompatiblePackages ? 'Use Earliest (Default)' : 'Use Default Package' ?>
                            </button>
                        </div>
                        <?php if ($packages): ?>
                            <p class="bd-packages__hint" id="rp-packages-hint">
                                <?php if ($hasMultipleCompatiblePackages): ?>
                                    Select the package you want to use, or leave unselected to automatically use your earliest purchased package.
                                <?php else: ?>
                                    Select the package you want or leave unselected to automatically use your package.
                                <?php endif; ?>
                            </p>
                            <?php foreach ($packages as $pkg):
                                $pkgIdStr    = (string) ($pkg['id'] ?? '');
                                $pkgProgKey  = rp_package_program_key($pkg);
                                $pkgTypeKey  = rp_package_type_key($pkg);
                                $isCompat    = rp_is_package_compatible($pkg, (string) $activeClass['category'], (string) $activeClass['class_name'], $initAttendeeCount);
                                $isEarliest  = $hasMultipleCompatiblePackages && $isCompat && ($pkgIdStr === $earliestCompatiblePkgId);
                                $boughtStr   = rp_format_short_date($pkg['purchased_at'] ?? null);
                                $expStr      = rp_format_short_date($pkg['expired_at'] ?? null);
                            ?>
                                <label class="bd-package<?= !$isCompat ? ' is-incompatible' : '' ?>">
                                    <input type="radio" name="package_id" value="<?= (int) $pkg['id'] ?>"
                                        data-remaining="<?= (int) $pkg['remaining'] ?>"
                                        data-program="<?= e($pkgProgKey) ?>"
                                        data-class-type="<?= e($pkgTypeKey) ?>"
                                        data-pkg-label="<?= e((string) ($pkg['name'] ?? $pkg['category'] ?? $pkg['type'] ?? '')) ?>"
                                        <?= !$isCompat ? 'disabled' : '' ?>>
                                    <span class="bd-package__card">
                                        <span class="bd-package__info">
                                            <span class="bd-package__name">
                                                <?= e($pkg['name']) ?>
                                                <span class="bd-package__tag" data-earliest-tag <?= $isEarliest ? '' : 'hidden' ?>>Earliest Purchased</span>
                                            </span>
                                            <span class="bd-package__desc"><?= e($pkg['type']) ?> (<?= (int) $pkg['total'] ?> Session)</span>
                                            <span class="bd-package__meta">Purchased <?= e($boughtStr) ?> &bull; Valid until <?= e($expStr) ?></span>
                                            <span class="bd-package__lock" <?= $isCompat ? 'hidden' : '' ?>>Not applicable for Private <?= e($activeClass['category']) ?></span>
                                        </span>
                                        <span class="bd-package__badge">Available<br><?= (int) $pkg['remaining'] ?> / <?= (int) $pkg['total'] ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="bd-package__card bd-package__card--empty">
                                <div class="bd-package__info">
                                    <span class="bd-package__name">You have no package yet</span>
                                    <span class="bd-package__desc">Purchase a class package to use sessions for your private request</span>
                                </div>
                                <a href="<?= e($packagesUrl) ?>" class="bd-package__cta">Buy Packages</a>
                            </div>
                        <?php endif; ?>
                        <div class="bd-error" id="rp-package-error"></div>
                    </div>
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
                                <span id="rp-sum-time"><?= e($initTimeRange) ?></span>
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
                                Level <strong id="rp-sum-level">Beginner</strong>
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