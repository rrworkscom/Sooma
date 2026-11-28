<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requireLogin = true;
$loginPage    = '/login-regist/index.php?page=login';
$timetableUrl = 'timetable.php';
$paymentUrl   = 'payment.php';
$packagesUrl  = 'packages.php';
$termsUrl     = '#';

require __DIR__ . '/../includes/booking-service.php';

function bd_class_from_request(array $q): ?array
{
    $str = static fn(string $k): string => trim((string) ($q[$k] ?? ''));

    $date = DateTime::createFromFormat('!Y-m-d', $str('date'));
    if (!$date || $date->format('Y-m-d') !== $str('date')) {
        return null;
    }

    foreach (['start', 'end'] as $k) {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $str($k))) {
            return null;
        }
    }

    if ($str('end') <= $str('start')) {
        return null;
    }

    foreach (['instructor', 'className', 'category', 'level'] as $k) {
        if ($str($k) === '' || strlen($str($k)) > 60) {
            return null;
        }
    }

    $capacity = filter_var(
        $q['capacity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 20]]
    );

    $taken = filter_var(
        $q['slot'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if ($capacity === false || $taken === false) {
        return null;
    }

    $classImages = [
        'Reformer Pilates' => '../assets/images/class-pilates.jpg',
        'Regular Yoga'     => '../assets/images/class-yoga.jpg',
    ];

    $defaultImage = '../assets/images/class-pilates.jpg';
    $defaultPrice = 120000;
    $className    = $str('className');

    return [
        'category'    => $str('category'),
        'class_name'  => $className,
        'image'       => $classImages[$str('category')] ?? $defaultImage,
        'date'        => $date->format('l, j F Y'),
        'time'        => str_replace(':', '.', $str('start'))
            . ' - '
            . str_replace(':', '.', $str('end'))
            . ' WIB',
        'type'        => $str('type'),
        'level'       => $str('level'),
        'coach'       => $str('instructor'),
        'venue'       => 'Sooma Wellness',
        'address'     => 'Jl Ciliwung no. 62, Blitar',
        'slots_left'  => max(0, $capacity - $taken),
        'slots_total' => $capacity,
        'price'       => $defaultPrice,

        'query' => [
            'scheduleId' => (int) ($q['scheduleId'] ?? 0),
            'date'       => $str('date'),
            'start'      => $str('start'),
            'end'        => $str('end'),
            'instructor' => $str('instructor'),
            'className'  => $className,
            'category'   => $str('category'),
            'level'      => $str('level'),
            'type'       => $str('type'),
            'slot'       => $taken,
            'capacity'   => $capacity,
        ],
    ];
}

function bd_normalize_program(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '' || $v === 'all' || $v === 'all classes' || str_contains($v, 'all class')) {
        return 'all';
    }
    if (str_contains($v, 'pilates') || str_contains($v, 'reformer')) {
        return 'reformer_pilates';
    }
    if (str_contains($v, 'yoga')) {
        return 'regular_yoga';
    }
    return $v;
}

function bd_normalize_class_type(string $raw): string
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

function bd_resolve_target_class_type(string $rawType, string $rawClassName = '', int $attendeeCount = 1): string
{
    $baseType = bd_normalize_class_type($rawType);
    if ($baseType === '') {
        $baseType = bd_normalize_class_type($rawClassName);
    }
    if ($baseType === 'private') {
        return $attendeeCount > 1 ? 'private_group' : 'private';
    }
    if ($baseType === 'private_group') {
        return 'private_group';
    }
    return 'group';
}

function bd_package_program_key(array $pkg): string
{
    foreach (['category', 'class_category', 'class_name', 'name', 'package_name'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = bd_normalize_program((string) $pkg[$field]);
            if (in_array($norm, ['all', 'reformer_pilates', 'regular_yoga'], true)) {
                return $norm;
            }
        }
    }
    return 'all';
}

function bd_package_type_key(array $pkg): string
{
    foreach (['pkg_class_type', 'class_type', 'package_type'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = bd_normalize_class_type((string) $pkg[$field]);
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

    $norm = bd_normalize_class_type($combined);
    return $norm !== '' ? $norm : 'group';
}

function bd_is_package_compatible(
    array $pkg,
    string $targetCategory,
    string $targetClassName = '',
    string $targetType = 'group',
    int $attendeeCount = 1
): bool {
    $pkgProg    = bd_package_program_key($pkg);
    $targetProg = bd_normalize_program($targetCategory);
    if (!in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true)) {
        $targetProg = bd_normalize_program($targetClassName);
    }

    $progCompatible = ($pkgProg === 'all' || !in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true))
        ? true
        : ($pkgProg === $targetProg);

    if (!$progCompatible) {
        return false;
    }

    $pkgType          = bd_package_type_key($pkg);
    $resolvedTargetTp = bd_resolve_target_class_type($targetType, $targetClassName, $attendeeCount);

    return $pkgType === 'all' || $pkgType === $resolvedTargetTp;
}

function bd_target_full_label(string $targetCategory, string $targetClassName, string $resolvedType): string
{
    $progKey = bd_normalize_program($targetCategory);
    if (!in_array($progKey, ['reformer_pilates', 'regular_yoga'], true)) {
        $progKey = bd_normalize_program($targetClassName);
    }

    $progLabel = match ($progKey) {
        'reformer_pilates' => 'Reformer Pilates',
        'regular_yoga'     => 'Regular Yoga',
        default            => trim($targetCategory !== '' ? $targetCategory : $targetClassName),
    };

    $typeLabel = match ($resolvedType) {
        'private_group' => 'Private Group',
        'private'       => 'Private',
        default         => 'Group',
    };

    return trim($typeLabel . ' ' . $progLabel);
}

function bd_fetch_sorted_user_packages(PDO $pdo, int $userId, array $fallback = []): array
{
    if ($userId <= 0) {
        return $fallback;
    }

    try {
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

function bd_pick_earliest_compatible_package(
    array $packages,
    string $targetCategory,
    string $targetClassName,
    int $requiredSessions = 1,
    string $targetType = 'group'
): ?array {
    $firstCompat = null;
    foreach ($packages as $pkg) {
        if (!bd_is_package_compatible($pkg, $targetCategory, $targetClassName, $targetType, $requiredSessions)) {
            continue;
        }
        if ($firstCompat === null) {
            $firstCompat = $pkg;
        }
        if ((int) ($pkg['remaining'] ?? 0) >= $requiredSessions) {
            return $pkg;
        }
    }
    return $firstCompat;
}

function bd_format_short_date(?string $raw): string
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

function bd_attendee_card(
    $idx,
    array $a,
    array $errors,
    bool $removable,
    array $currentUser = []
): void {
    $i = (string) $idx;
    $p = "attendees.{$i}.";
    if ((string) $idx === '0') {
        $userName = trim((string) ($currentUser['name'] ?? ''));
        $title = $userName !== '' ? $userName : 'Attendee 1';
    } else {
        $number = ctype_digit((string) $idx) ? (int) $idx + 1 : '';
        $title = $number ? 'Attendee ' . $number : 'Attendee';
    }
    $bodyId = "bd-attendee-body-{$i}";
    $err    = static fn(string $f): ?string => $errors[$p . $f] ?? null;
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
                    <input type="text" class="bd-input <?= $err('name') ? 'is-invalid' : '' ?>"
                        id="a<?= e($i) ?>-name" name="attendees[<?= e($i) ?>][name]"
                        value="<?= e($a['name'] ?? '') ?>" required data-attendee-name>
                    <?php if ($err('name')): ?><div class="bd-error"><?= e($err('name')) ?></div><?php endif; ?>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-phone">Phone number</label>
                    <input type="tel" inputmode="tel" class="bd-input <?= $err('phone') ? 'is-invalid' : '' ?>"
                        id="a<?= e($i) ?>-phone" name="attendees[<?= e($i) ?>][phone]"
                        value="<?= e($a['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                    <?php if ($err('phone')): ?><div class="bd-error"><?= e($err('phone')) ?></div><?php endif; ?>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-email">Email</label>
                    <input type="email" class="bd-input <?= $err('email') ? 'is-invalid' : '' ?>"
                        id="a<?= e($i) ?>-email" name="attendees[<?= e($i) ?>][email]"
                        value="<?= e($a['email'] ?? '') ?>" required>
                    <?php if ($err('email')): ?><div class="bd-error"><?= e($err('email')) ?></div><?php endif; ?>
                </div>
                <div class="bd-field">
                    <label class="bd-label" for="a<?= e($i) ?>-medical">Medical Condition</label>
                    <textarea class="bd-input bd-input--area" id="a<?= e($i) ?>-medical"
                        name="attendees[<?= e($i) ?>][medical]" placeholder="(Optional)"><?= e($a['medical'] ?? '') ?></textarea>
                </div>
                <label class="bd-check">
                    <input
                        type="checkbox"
                        name="attendees[<?= e($i) ?>][not_pregnant]"
                        value="1"
                        <?= !empty($a['not_pregnant']) ? 'unchecked' : '' ?>required>
                    <span class="bd-check__box" aria-hidden="false">
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

$sessionUser = $_SESSION['user'] ?? [];

if ($requireLogin && empty($sessionUser)) {
    $redirectUrl = '/customer/booking-detail.php?' . http_build_query($_GET);
    header('Location: ' . $loginPage . '&redirect=' . urlencode($redirectUrl));
    exit;
}

$currentUser = [
    'id'        => (int) ($sessionUser['id'] ?? $sessionUser['user_id'] ?? 0),
    'name'      => (string) ($sessionUser['full_name'] ?? $sessionUser['name'] ?? $sessionUser['nama'] ?? ''),
    'phone'     => (string) ($sessionUser['phone'] ?? $sessionUser['no_hp'] ?? ''),
    'email'     => (string) ($sessionUser['email'] ?? ''),
    'birthdate' => (string) ($sessionUser['birthdate'] ?? $sessionUser['birth_date'] ?? $sessionUser['date_of_birth'] ?? $sessionUser['dob'] ?? $sessionUser['tanggal_lahir'] ?? ''),
];

$class = bd_class_from_request($_GET);
if (!$class) {
    header('Location: ' . $timetableUrl);
    exit;
}

$packages = bd_get_packages($currentUser['id']);

$scheduleId          = 0;
$classFull           = false;
$slotNotice          = null;
$maxAttendees        = 1;
$heldQty             = 1;
$hasAcceptedPrivate  = false;
$customerBirthdate   = $currentUser['birthdate'];
$maxBookingLimit     = 2;

try {
    $pdo = bd_pdo();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('set search_path to sooma_wellness, public');

    $packages = bd_fetch_sorted_user_packages($pdo, $currentUser['id'], $packages);

    $pdo->exec("
        create table if not exists sooma_wellness.slot_holds (
            hold_id     serial primary key,
            schedule_id int not null,
            user_id     int not null,
            qty         int not null default 1,
            expires_at  timestamptz not null,
            unique (schedule_id, user_id)
        )
    ");

    $pdo->exec("delete from sooma_wellness.slot_holds where expires_at <= clock_timestamp()");
    $pdo->exec("
        update sooma_wellness.bookings
           set booking_status = 'expired', updated_at = clock_timestamp()
         where booking_status = 'waiting_payment'
           and expires_at is not null
           and expires_at <= clock_timestamp()
    ");

    $lookupQuery = $class['query'];
    $lookupQuery['schedule_id'] = $lookupQuery['scheduleId'];
    $schedule = bd_find_or_create_schedule($pdo, $lookupQuery);

    if (!$schedule) {
        header('Location: ' . $timetableUrl);
        exit;
    }

    $scheduleId = (int) $schedule['id'];
    $class['query']['scheduleId'] = $scheduleId;

    $customerId = bd_get_or_create_customer_id($pdo, $currentUser['id']);

    if ($customerBirthdate === '' && ($currentUser['id'] > 0 || $customerId > 0 || $currentUser['email'] !== '')) {
        try {
            $stBirth = $pdo->prepare("
                select coalesce(
                    nullif(to_jsonb(c)->>'birthdate', ''),
                    nullif(to_jsonb(c)->>'birth_date', ''),
                    nullif(to_jsonb(c)->>'date_of_birth', ''),
                    nullif(to_jsonb(c)->>'dob', ''),
                    nullif(to_jsonb(c)->>'tanggal_lahir', ''),
                    nullif(to_jsonb(u)->>'birthdate', ''),
                    nullif(to_jsonb(u)->>'birth_date', ''),
                    nullif(to_jsonb(u)->>'date_of_birth', ''),
                    nullif(to_jsonb(u)->>'dob', ''),
                    nullif(to_jsonb(u)->>'tanggal_lahir', '')
                ) as dob
                  from sooma_wellness.users u
                  left join sooma_wellness.customers c on c.user_id = u.user_id
                 where u.user_id = :uid
                    or c.customer_id = :cid
                    or (:email <> '' and lower(u.email) = lower(:email))
                 limit 1
            ");
            $stBirth->execute([
                ':uid'   => $currentUser['id'],
                ':cid'   => $customerId,
                ':email' => $currentUser['email'],
            ]);
            $dbBirth = $stBirth->fetchColumn();
            if ($dbBirth) {
                $customerBirthdate = (string) $dbBirth;
            }
        } catch (Throwable $ignore) {
        }
    }

    try {
        $stPriv = $pdo->prepare("
            select 1
              from sooma_wellness.private_requests pr
             where (
                    nullif(to_jsonb(pr)->>'customer_id', '')::int = :cid
                 or nullif(to_jsonb(pr)->>'user_id', '')::int = :uid
             )
               and lower(coalesce(
                    nullif(to_jsonb(pr)->>'status', ''),
                    nullif(to_jsonb(pr)->>'request_status', ''),
                    nullif(to_jsonb(pr)->>'approval_status', ''),
                    ''
               )) in ('accepted', 'approved')
             limit 1
        ");
        $stPriv->execute([
            ':cid' => $customerId,
            ':uid' => $currentUser['id'],
        ]);
        $hasAcceptedPrivate = (bool) $stPriv->fetchColumn();
    } catch (Throwable $ignore) {
        $hasAcceptedPrivate = false;
    }

    $isPrivateSession = stripos($class['type'], 'private') !== false
        || stripos($class['class_name'], 'private') !== false
        || $hasAcceptedPrivate;
    $maxBookingLimit = $isPrivateSession ? (int) $class['slots_total'] : 2;

    $stmtExisting = $pdo->prepare("
        select booking_id as id, booking_code, booking_status as status
          from sooma_wellness.bookings
         where schedule_id = :sid
           and customer_id = :cid
           and booking_status in ('waiting_payment', 'waiting_verification', 'confirmed')
         order by booking_id desc
         limit 1
    ");
    $stmtExisting->execute([':sid' => $scheduleId, ':cid' => $customerId]);
    $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existing) {
        $classFull  = true;
        $slotNotice = $existing['status'] === 'waiting_payment'
            ? 'You already have an unpaid booking for this class session. Please complete it in My Transaction.'
            : 'You already have an active booking for this class session.';
    } else {
        $stSched = $pdo->prepare("
            select cs.member_slot, mc.price
              from sooma_wellness.class_schedules cs
              join sooma_wellness.master_classes mc on mc.class_id = cs.class_id
             where cs.schedule_id = :sid
        ");
        $stSched->execute([':sid' => $scheduleId]);
        $schedInfo = $stSched->fetch(PDO::FETCH_ASSOC);

        if ($schedInfo) {
            $class['slots_total'] = (int) $schedInfo['member_slot'];
            $class['price']       = (int) $schedInfo['price'];

            $stBooked = $pdo->prepare("
                select coalesce(count(ba.attendee_id), 0)
                  from sooma_wellness.bookings b
                  join sooma_wellness.booking_attendees ba on ba.booking_id = b.booking_id
                 where b.schedule_id = :sid
                   and (
                       b.booking_status in ('confirmed', 'waiting_verification')
                       or (b.booking_status = 'waiting_payment' and (b.expires_at is null or b.expires_at > clock_timestamp()))
                   )
            ");
            $stBooked->execute([':sid' => $scheduleId]);
            $bookedCount = (int) $stBooked->fetchColumn();

            $stOtherHolds = $pdo->prepare("
                select coalesce(sum(qty), 0)
                  from sooma_wellness.slot_holds
                 where schedule_id = :sid
                   and user_id <> :uid
                   and expires_at > clock_timestamp()
            ");
            $stOtherHolds->execute([':sid' => $scheduleId, ':uid' => $currentUser['id']]);
            $otherHeldCount = (int) $stOtherHolds->fetchColumn();

            $availableForUser = max(0, $class['slots_total'] - $bookedCount - $otherHeldCount);

            if ($availableForUser < 1) {
                $classFull  = true;
                $slotNotice = 'Oops, sorry! The last spot in your selected class has just been booked. Kindly choose another available class.';
                $class['slots_left'] = 0;
            } else {
                $maxBookingLimit = $isPrivateSession ? (int) $class['slots_total'] : 2;
                $maxAttendees    = min($maxBookingLimit, $availableForUser);

                $stMyHold = $pdo->prepare("
                    select qty from sooma_wellness.slot_holds
                     where schedule_id = :sid and user_id = :uid and expires_at > clock_timestamp()
                ");
                $stMyHold->execute([':sid' => $scheduleId, ':uid' => $currentUser['id']]);
                $prevQty = (int) $stMyHold->fetchColumn();
                $heldQty = min($maxAttendees, max(1, $prevQty ?: 1));

                $pdo->prepare("
                    insert into sooma_wellness.slot_holds (schedule_id, user_id, qty, expires_at)
                    values (:sid, :uid, :qty, clock_timestamp() + interval '15 minutes')
                    on conflict (schedule_id, user_id)
                    do update set qty = excluded.qty, expires_at = excluded.expires_at
                ")->execute([
                    ':sid' => $scheduleId,
                    ':uid' => $currentUser['id'],
                    ':qty' => $heldQty,
                ]);

                $class['slots_left'] = max(0, $availableForUser - $heldQty);
            }
        }
    }
} catch (Throwable $ex) {
    error_log('[booking-detail] ' . $ex->getMessage());
    $classFull  = true;
    $slotNotice = 'Booking error: ' . $ex->getMessage();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$defaultAttendees = [[
    'name'         => $currentUser['name'],
    'phone'        => $currentUser['phone'],
    'email'        => $currentUser['email'],
    'medical'      => '',
    'not_pregnant' => '1',
]];

for ($k = 1; $k < $heldQty; $k++) {
    $defaultAttendees[] = [
        'name'         => '',
        'phone'        => '',
        'email'        => '',
        'medical'      => '',
        'not_pregnant' => '1',
    ];
}

$old = [
    'attendees'      => $defaultAttendees,
    'payment_method' => 'dropin',
    'package_id'     => '',
    'agree'          => '',
];
$errors = [];

function bd_validate_voucher_condition(
    string $voucherCode,
    int $attendeeCount,
    int $unitPrice,
    string $startTime,
    string $birthdate,
    bool $hasAcceptedPrivate
): ?string {
    $code = strtoupper(trim($voucherCode));
    if ($code === '') {
        return null;
    }

    if ($code === 'SUMMER400') {
        if (!$hasAcceptedPrivate) {
            return 'Voucher Summer Day Out hanya dapat digunakan jika Anda sudah melakukan Request Private dan telah di-accept oleh admin.';
        }
        if ($attendeeCount < 4 || ($attendeeCount % 4) !== 0) {
            return 'Voucher Summer Day Out hanya berlaku untuk total peserta (termasuk attendee) berjumlah 4 atau kelipatan 4.';
        }
        return null;
    }

    if ($code === 'GENZ100') {
        $birthYear = 0;
        if (preg_match('/(19\d{2}|20\d{2})/', trim($birthdate), $m)) {
            $birthYear = (int) $m[1];
        }
        if ($birthYear < 1997 || $birthYear > 2012) {
            return 'Voucher Calling All Gen Z hanya berlaku untuk customer dengan tahun lahir 1997 - 2012.';
        }
        if ($unitPrice < 100000) {
            return 'Voucher Calling All Gen Z hanya berlaku untuk sesi kelas dengan harga Rp100.000 ke atas.';
        }
        return null;
    }

    if ($code === 'HAPPYHOUR135') {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $startTime, $tm)) {
            return 'Waktu mulai sesi kelas tidak valid untuk pengecekan Happy Hour.';
        }
        $startMinutes = ((int) $tm[1]) * 60 + ((int) $tm[2]);
        $inMorningSlot   = ($startMinutes >= 11 * 60 && $startMinutes <= 14 * 60);
        $inAfternoonSlot = ($startMinutes >= 15 * 60 && $startMinutes <= 17 * 60);
        if (!$inMorningSlot && !$inAfternoonSlot) {
            return 'Voucher Happy Hour hanya dapat digunakan untuk reservasi kelas pada pukul 11.00 - 14.00 atau 15.00 - 17.00.';
        }
        return null;
    }

    return 'Kode voucher tidak valid atau tidak berlaku.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request token.');
    }

    $submittedMethod       = strtolower(trim((string) ($_POST['payment_method'] ?? 'dropin')));
    $userChosenPackageId   = trim((string) ($_POST['package_id'] ?? $_POST['user_package_id'] ?? ''));
    $postedAttendeesCount  = max(1, count((array) ($_POST['attendees'] ?? [])));

    if ($submittedMethod === 'package' && $userChosenPackageId === '') {
        $autoPkg = bd_pick_earliest_compatible_package(
            $packages,
            (string) $class['category'],
            (string) $class['class_name'],
            $postedAttendeesCount,
            (string) $class['type']
        );
        if ($autoPkg !== null) {
            $_POST['package_id'] = (string) $autoPkg['id'];
        }
    }

    $data        = bd_parse_submission($_POST);
    $errors      = bd_validate_submission($data, $packages);
    $voucherCode = strtoupper(trim((string) ($_POST['voucher_code'] ?? '')));

    if ($data['payment_method'] === 'package') {
        $attendeeCnt = count($data['attendees']);
        $resolvedTp  = bd_resolve_target_class_type((string) $class['type'], (string) $class['class_name'], $attendeeCnt);
        $fullLabel   = bd_target_full_label((string) $class['category'], (string) $class['class_name'], $resolvedTp);

        $selectedPkg = null;
        foreach ($packages as $pkg) {
            if ((string) ($pkg['id'] ?? '') === (string) ($data['package_id'] ?? '')) {
                $selectedPkg = $pkg;
                break;
            }
        }
        if (!$selectedPkg) {
            $errors['package'] = 'No compatible active package available for ' . $fullLabel . '.';
        } elseif (!bd_is_package_compatible($selectedPkg, (string) $class['category'], (string) $class['class_name'], (string) $class['type'], $attendeeCnt)) {
            $errors['package'] = 'Selected package is not applicable for ' . $fullLabel . '.';
        } elseif ((int) ($selectedPkg['remaining'] ?? 0) < $attendeeCnt) {
            $errors['package'] = 'Your package does not have enough remaining sessions.';
        }
    }

    if ($data['payment_method'] === 'dropin' && $voucherCode !== '') {
        $voucherErr = bd_validate_voucher_condition(
            $voucherCode,
            count($data['attendees']),
            (int) $class['price'],
            (string) $class['query']['start'],
            $customerBirthdate,
            $hasAcceptedPrivate
        );
        if ($voucherErr !== null) {
            $errors['form'] = $voucherErr;
        }
    }

    $old = [
        'attendees'      => $data['attendees'] ?: $old['attendees'],
        'payment_method' => $data['payment_method'],
        'package_id'     => $userChosenPackageId,
        'agree'          => $data['agree'] ? '1' : '',
    ];

    if (!$errors && $scheduleId > 0 && count($data['attendees']) <= $maxBookingLimit) {
        try {
            $result = bd_reserve_slot(
                $pdo,
                $scheduleId,
                $currentUser['id'],
                $data,
                $class['query'],
                (int) ($_SESSION['booking_draft']['db_id'] ?? 0)
            );
        } catch (Throwable $ex) {
            error_log('[booking-detail] ' . $ex->getMessage());
            $result = ['status' => 'error', 'message' => 'We could not verify slot availability right now. Please try again.'];
        }

        if (in_array($result['status'], ['available', 'existing'], true)) {
            bd_store_booking_session($result['booking'], $class['query']);

            $bookingDbId = (int) (
                $result['booking']['db_id']
                ?? $result['booking']['id']
                ?? $_SESSION['booking_draft']['db_id']
                ?? 0
            );

            if ($bookingDbId > 0 && $data['payment_method'] === 'package') {
                $resolvedUserPkgId = (int) ($data['package_id'] ?? 0);
                if ($resolvedUserPkgId > 0) {
                    try {
                        $pdo->prepare("
                            update sooma_wellness.bookings
                               set user_package_id = :upid,
                                   updated_at      = clock_timestamp()
                             where booking_id      = :bid
                        ")->execute([
                            ':upid' => $resolvedUserPkgId,
                            ':bid'  => $bookingDbId,
                        ]);
                    } catch (Throwable $ignore) {
                    }
                    $_SESSION['booking_draft']['selected_user_package_id'] = $resolvedUserPkgId;
                    $_SESSION['booking_selected_package'][$bookingDbId]    = $resolvedUserPkgId;
                }
            }

            if ($bookingDbId > 0 && $data['payment_method'] === 'dropin' && $voucherCode !== '') {
                $pdo->exec("
                    create table if not exists sooma_wellness.booking_vouchers (
                        booking_id      int primary key,
                        voucher_code    varchar(32) not null,
                        discount_amount int not null default 0,
                        final_total     int not null,
                        created_at      timestamptz not null default clock_timestamp()
                    )
                ");
                $attendeeCnt = count($data['attendees']);
                $subtotal    = (int) $class['price'] * $attendeeCnt;
                $finalTotal  = $subtotal;
                if ($voucherCode === 'GENZ100') {
                    $finalTotal = 100000 * $attendeeCnt;
                } elseif ($voucherCode === 'HAPPYHOUR135') {
                    $finalTotal = 135000 * $attendeeCnt;
                } elseif ($voucherCode === 'SUMMER400') {
                    $finalTotal = (int) (($attendeeCnt / 4) * 400000);
                }
                $discountAmt = max(0, $subtotal - $finalTotal);

                $pdo->prepare("
                    insert into sooma_wellness.booking_vouchers (booking_id, voucher_code, discount_amount, final_total)
                    values (:bid, :code, :disc, :total)
                    on conflict (booking_id)
                    do update set voucher_code = excluded.voucher_code, discount_amount = excluded.discount_amount, final_total = excluded.final_total
                ")->execute([
                    ':bid'   => $bookingDbId,
                    ':code'  => $voucherCode,
                    ':disc'  => $discountAmt,
                    ':total' => $finalTotal,
                ]);
                $_SESSION['booking_vouchers'][$bookingDbId] = [
                    'voucher_code'    => $voucherCode,
                    'discount_amount' => $discountAmt,
                    'final_total'     => $finalTotal,
                ];
            }

            header('Location: ' . ($bookingDbId > 0 ? $paymentUrl . '?id=' . $bookingDbId : $paymentUrl));
            exit;
        }
        $errors['form'] = $result['message'] ?? 'Unable to continue. Please try again.';
        if ($result['status'] === 'full') {
            $classFull = true;
        }
    } elseif (count($data['attendees']) > $maxBookingLimit) {
        $errors['form'] = "Maximum {$maxBookingLimit} attendees allowed per booking.";
    } elseif (!$errors) {
        $errors['form'] = $slotNotice ?? 'Booking is temporarily unavailable. Please try again.';
    }
}

$initAttendeesCount = count($old['attendees']);
$initResolvedType   = bd_resolve_target_class_type((string) $class['type'], (string) $class['class_name'], $initAttendeesCount);
$initTargetLabel    = bd_target_full_label((string) $class['category'], (string) $class['class_name'], $initResolvedType);

$compatiblePackagesCount = 0;
$earliestCompatiblePkgId = '';
foreach ($packages as $pkg) {
    if (bd_is_package_compatible($pkg, (string) $class['category'], (string) $class['class_name'], (string) $class['type'], $initAttendeesCount)) {
        $compatiblePackagesCount++;
        if ($earliestCompatiblePkgId === '') {
            $earliestCompatiblePkgId = (string) $pkg['id'];
        }
    }
}
$hasMultipleCompatiblePackages = $compatiblePackagesCount > 1;

$pageTitle  = 'Timetable';
$bodyClass  = 'booking-detail-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';
$slotsText  = $class['slots_left'] . ' / ' . $class['slots_total'] . ' Left';

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/booking-detail.css">

<main class="bd-page">
    <a href="<?= e($timetableUrl) ?>" class="bd-back" id="bd-back-btn">
        <span class="bd-back__icon"><i class="bi bi-chevron-left"></i></span>
        <span>Timetable</span>
    </a>

    <h1 class="bd-title">Book Class Session</h1>

    <form method="post" action="booking-detail.php?<?= e(http_build_query($class['query'])) ?>" id="bd-form" novalidate
        data-price="<?= (int) $class['price'] ?>"
        data-max="<?= $maxAttendees ?>"
        data-limit="<?= $maxBookingLimit ?>"
        data-full="<?= $classFull ? '1' : '0' ?>"
        data-class-category="<?= e($class['category']) ?>"
        data-class-name="<?= e($class['class_name']) ?>"
        data-class-type="<?= e($class['type']) ?>"
        data-start-time="<?= e($class['query']['start']) ?>"
        data-birthdate="<?= e($customerBirthdate) ?>"
        data-private-accepted="<?= $hasAcceptedPrivate ? '1' : '0' ?>"
        data-endpoint="verify-slot.php">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="schedule_id" value="<?= (int) $scheduleId ?>">
        <input type="hidden" name="class_query" value="<?= e(http_build_query($class['query'])) ?>">
        <input type="hidden" name="voucher_code" id="bd-voucher-code" value="">

        <div class="bd-layout">

            <div class="bd-left">
                <?php if (isset($errors['form'])): ?>
                    <div class="bd-alert"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <?php if ($slotNotice && !isset($errors['form'])): ?>
                    <div class="bd-alert" role="alert">
                        <?= e($slotNotice) ?>
                        <a href="<?= e($timetableUrl) ?>" class="bd-link-btn">Choose another class</a>
                    </div>
                <?php endif; ?>

                <section class="bd-section">
                    <h2 class="bd-section__title">Attendee List</h2>
                    <p class="bd-section__hint">
                        <?php if ($maxAttendees === 1): ?>
                            Only <strong>1</strong> spot left in this class (reserved for you).
                        <?php else: ?>
                            You can add up to <strong><?= $maxAttendees ?></strong> participants in this class.
                        <?php endif; ?>
                    </p>

                    <div id="bd-attendee-list">
                        <?php foreach ($old['attendees'] as $i => $a) {
                            bd_attendee_card($i, $a, $errors, $i > 0, $currentUser);
                        } ?>
                    </div>

                    <button type="button" class="bd-add" id="bd-add-attendee"
                        <?= ($classFull || count($old['attendees']) >= $maxAttendees) ? 'disabled' : '' ?>>
                        + Add Attendee
                    </button>
                </section>

                <section class="bd-section">
                    <h2 class="bd-section__title">Payment Method</h2>
                    <div class="bd-pay" role="radiogroup" aria-label="Payment method">
                        <label class="bd-pay__opt">
                            <input type="radio" name="payment_method" value="dropin"
                                <?= $old['payment_method'] === 'dropin' ? 'checked' : '' ?>>
                            <span class="bd-pay__card">
                                <svg class="bd-pay__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17" />
                                    <path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9" />
                                    <path d="m2 16 6 6" />
                                    <circle cx="16" cy="9" r="2.9" />
                                    <circle cx="6" cy="5" r="3" />
                                </svg>
                                Drop-in Payment
                            </span>
                        </label>

                        <label class="bd-pay__opt">
                            <input type="radio" name="payment_method" value="package"
                                <?= $old['payment_method'] === 'package' ? 'checked' : '' ?>>
                            <span class="bd-pay__card"><i class="bi bi-inbox-fill bd-pay__icon"></i> Package Plan</span>
                        </label>
                    </div>

                    <div id="bd-packages" class="bd-packages" hidden>
                        <div class="bd-packages__header">
                            <h3 class="bd-packages__title">My Packages</h3>
                            <button type="button" class="bd-packages__clear" id="bd-clear-package" hidden>
                                Use Earliest Package
                            </button>
                        </div>
                        <?php if ($packages): ?>
                            <p class="bd-packages__hint" id="bd-packages-hint">
                                <?php if ($hasMultipleCompatiblePackages): ?>
                                    Select the package you want to use, or leave unselected to automatically use your earliest purchased package.
                                <?php else: ?>
                                    Your compatible package is selected automatically.
                                <?php endif; ?>
                            </p>
                            <?php foreach ($packages as $pkg):
                                $pkgIdStr    = (string) ($pkg['id'] ?? '');
                                $pkgProgKey  = bd_package_program_key($pkg);
                                $pkgTypeKey  = bd_package_type_key($pkg);
                                $isCompat    = bd_is_package_compatible($pkg, (string) $class['category'], (string) $class['class_name'], (string) $class['type'], $initAttendeesCount);
                                $isEarliest  = $hasMultipleCompatiblePackages && $isCompat && ($pkgIdStr === $earliestCompatiblePkgId);
                                $isChecked   = $isCompat && (
                                    ($pkgIdStr !== '' && $pkgIdStr === $old['package_id'])
                                    || !$hasMultipleCompatiblePackages
                                );
                                $boughtStr   = bd_format_short_date($pkg['purchased_at'] ?? null);
                                $expStr      = bd_format_short_date($pkg['expired_at'] ?? null);
                            ?>
                                <label class="bd-package<?= !$isCompat ? ' is-incompatible' : '' ?>">
                                    <input type="radio" name="package_id" value="<?= (int) $pkg['id'] ?>"
                                        data-remaining="<?= (int) $pkg['remaining'] ?>"
                                        data-program="<?= e($pkgProgKey) ?>"
                                        data-class-type="<?= e($pkgTypeKey) ?>"
                                        data-pkg-label="<?= e((string) ($pkg['name'] ?? $pkg['category'] ?? $pkg['type'] ?? '')) ?>"
                                        <?= !$isCompat ? 'disabled' : '' ?>
                                        <?= $isChecked ? 'checked' : '' ?>>
                                    <span class="bd-package__card">
                                        <span class="bd-package__info">
                                            <span class="bd-package__name">
                                                <?= e($pkg['name']) ?>
                                                <span class="bd-package__tag" data-earliest-tag <?= $isEarliest ? '' : 'hidden' ?>>Earliest Purchased</span>
                                            </span>
                                            <span class="bd-package__desc"><?= e($pkg['type']) ?> (<?= (int) $pkg['total'] ?> Session)</span>
                                            <span class="bd-package__meta">Purchased <?= e($boughtStr) ?> &bull; Valid until <?= e($expStr) ?></span>
                                            <span class="bd-package__lock" <?= $isCompat ? 'hidden' : '' ?>>Not applicable for <?= e($initTargetLabel) ?></span>
                                        </span>
                                        <span class="bd-package__badge">Available<br><?= (int) $pkg['remaining'] ?> / <?= (int) $pkg['total'] ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="bd-package__card bd-package__card--empty">
                                <div class="bd-package__info">
                                    <span class="bd-package__name">You have no package yet</span>
                                    <span class="bd-package__desc">Purchase a class package to book sessions with your plan</span>
                                </div>
                                <a href="<?= e($packagesUrl) ?>" class="bd-package__cta">Buy Packages</a>
                            </div>
                        <?php endif; ?>
                        <div class="bd-error" id="bd-package-error"><?= e($errors['package'] ?? '') ?></div>
                    </div>

                    <div id="bd-total-box" class="bd-total">
                        <div class="bd-total__row bd-total__row--voucher">
                            <label class="bd-total__label" for="bd-voucher-input">Input voucher:</label>
                            <div class="bd-voucher__controls">
                                <input type="text"
                                    class="bd-voucher__input"
                                    id="bd-voucher-input"
                                    placeholder="Kode voucher (cth: GENZ100)"
                                    autocomplete="off">
                                <button type="button" class="bd-voucher__btn" id="bd-apply-voucher">Apply</button>
                                <button type="button" class="bd-voucher__btn bd-voucher__btn--remove" id="bd-remove-voucher" hidden>Hapus</button>
                            </div>
                            <div class="bd-voucher__feedback" id="bd-voucher-feedback" aria-live="polite"></div>
                        </div>

                        <div class="bd-total__row">
                            <span class="bd-total__label">Subtotal</span>
                            <span class="bd-price bd-price--sm" id="bd-subtotal"><em>Rp</em><?= number_format($class['price'] * count($old['attendees']), 0, '.', ',') ?></span>
                        </div>

                        <div class="bd-total__row bd-total__row--applied" id="bd-voucher-applied" hidden>
                            <div class="bd-voucher-applied__info">
                                <span class="bd-voucher-applied__badge" id="bd-voucher-badge"></span>
                                <span class="bd-voucher-applied__desc" id="bd-voucher-desc"></span>
                            </div>
                            <span class="bd-price bd-price--sm bd-voucher-applied__amount" id="bd-voucher-discount"></span>
                        </div>

                        <div class="bd-total__row bd-total__row--grand">
                            <span class="bd-total__label">Total Payment</span>
                            <span class="bd-price bd-price--lg" id="bd-total"><em>Rp</em><?= number_format($class['price'] * count($old['attendees']), 0, '.', ',') ?></span>
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
                        <input
                            type="checkbox"
                            name="agree"
                            value="1"
                            id="bd-agree"
                            <?= $old['agree'] ? 'checked' : '' ?>>

                        <span class="bd-check__box" aria-hidden="true">
                            <i class="ph ph-square"></i>
                            <i class="ph ph-check-square"></i>
                        </span>

                        <span>I agree to the T&amp;C</span>
                    </label>
                    <?php if (isset($errors['agree'])): ?>
                        <div class="bd-error"><?= e($errors['agree']) ?></div>
                    <?php endif; ?>
                </section>

                <div class="bd-actions">
                    <button type="submit" class="bd-continue" id="bd-continue">
                        <span>Continue Payment</span>
                        <i class="bi bi-arrow-right-circle-fill"></i>
                    </button>
                </div>
            </div>

            <aside class="bd-right">
                <div class="bd-summary">
                    <div class="bd-summary__hero"
                        style="background-image: url('<?= e($class['image']) ?>');">
                        <div class="bd-summary__hero-content">
                            <h2><?= e($class['class_name']) ?></h2>
                            <p><?= e($class['category']) ?> Class</p>
                        </div>
                    </div>

                    <ul class="bd-summary__list">
                        <li>
                            <i class="ph-fill ph-clock"></i>
                            <div>
                                <strong><?= e($class['date']) ?></strong><br>
                                <?= e($class['time']) ?>
                            </div>
                        </li>
                        <li>
                            <i class="ph-fill ph-chalkboard-teacher"></i>
                            <div>
                                Coach <strong><?= e($class['coach']) ?></strong>
                            </div>
                        </li>
                        <li>
                            <i class="ph-fill ph-align-bottom"></i>
                            <div>
                                Level <strong><?= e($class['level']) ?></strong>
                            </div>
                        </li>
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <div>
                                <strong><?= e($class['venue']) ?></strong><br>
                                <?= e($class['address']) ?>
                            </div>
                        </li>
                    </ul>

                    <div class="bd-summary__slots">
                        <span class="bd-badge" id="bd-slots-badge"><?= e($slotsText) ?></span>
                    </div>
                </div>
            </aside>

        </div>
    </form>

    <template id="bd-attendee-template">
        <?php bd_attendee_card('__INDEX__', [], [], true, []); ?>
    </template>
</main>

<div class="tnc-overlay" id="tnc-modal" aria-hidden="true">
    <div class="tnc-modal" role="dialog" aria-modal="true" aria-labelledby="tnc-title">
        <div class="tnc-header">
            <h2 id="tnc-title">Sooma Wellness Terms &amp; Conditions</h2>
            <p>
                Please read and understand the studio's<br>
                rules before proceeding with payment
            </p>
        </div>

        <div class="tnc-content" id="tnc-content">
            <section class="tnc-section">
                <h3>Reservation &amp; Payment</h3>
                <ul>
                    <li>Payment must be made via BCA bank transfer and is non-refundable.</li>
                    <li>This package is for personal use only (non-transferable).</li>
                    <li>Rescheduling must be done at least 12 hours before class (limited to one time).</li>
                </ul>
            </section>

            <section class="tnc-section">
                <h3>Cancellation &amp; Rescheduling</h3>
                <ul>
                    <li>Cancellations made less than 12 hours in advance are considered a no-show, and the session is forfeited.</li>
                    <li>A minimum of 2 participants is required per class. If there are fewer than 2 participants, the studio reserves the right to cancel the class 12 hours in advance.</li>
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

            <section class="tnc-section">
                <h3>Package Validity Period</h3>
                <ul>
                    <li>The package validity period is calculated from the first session, not from the date of payment, except for certain promotions.</li>
                    <li>Packages must be activated within 2 weeks of purchase; otherwise, the package will automatically activate and the validity period will begin.</li>
                </ul>
            </section>
        </div>

        <div class="tnc-footer">
            <button type="button" id="tnc-accept">I Understand</button>
        </div>
    </div>
</div>

<div class="bd-slot-overlay" id="bd-slot-modal" aria-hidden="true" hidden>
    <div class="bd-slot-modal" role="alertdialog" aria-modal="true"
        aria-labelledby="bd-slot-title" aria-describedby="bd-slot-text">
        <div class="bd-slot-modal__icon" aria-hidden="true"><i class="ph ph-warning-circle"></i></div>
        <h2 id="bd-slot-title">This slot is no longer available</h2>
        <p id="bd-slot-text"></p>
        <div class="bd-slot-modal__actions">
            <a class="bd-slot-btn bd-slot-btn--ghost" id="bd-slot-back" href="<?= e($timetableUrl) ?>">Back to Timetable</a>
            <button type="button" class="bd-slot-btn bd-slot-btn--primary" id="bd-slot-refresh">Refresh Availability</button>
        </div>
    </div>
</div>

<script>
    (function() {
        'use strict';
        var form = document.getElementById('bd-form');
        if (!form) return;

        var price = parseInt(form.dataset.price, 10) || 0;
        var bookingLimit = parseInt(form.dataset.limit, 10) || 2;
        var max = Math.min(bookingLimit, parseInt(form.dataset.max, 10) || 1);
        var list = document.getElementById('bd-attendee-list');
        var addBtn = document.getElementById('bd-add-attendee');
        var backBtn = document.getElementById('bd-back-btn');
        var slotsBadge = document.getElementById('bd-slots-badge');
        var totalSlots = <?= (int) $class['slots_total'] ?>;
        var scheduleId = form.querySelector('input[name="schedule_id"]').value;
        var tpl = document.getElementById('bd-attendee-template');
        var subtotalEl = document.getElementById('bd-subtotal');
        var totalEl = document.getElementById('bd-total');
        var totalBox = document.getElementById('bd-total-box');
        var packagesBox = document.getElementById('bd-packages');
        var packagesHint = document.getElementById('bd-packages-hint');
        var clearPkgBtn = document.getElementById('bd-clear-package');
        var packageError = document.getElementById('bd-package-error');
        var agree = document.getElementById('bd-agree');
        var continueBtn = document.getElementById('bd-continue');
        var continueLabel = continueBtn.querySelector('span');
        var continueText = continueLabel ? continueLabel.textContent : 'Continue Payment';
        var endpoint = form.dataset.endpoint;
        var checking = false;

        var slotModal = document.getElementById('bd-slot-modal');
        var slotTitle = document.getElementById('bd-slot-title');
        var slotText = document.getElementById('bd-slot-text');
        var slotBack = document.getElementById('bd-slot-back');
        var slotRefresh = document.getElementById('bd-slot-refresh');
        var lastFocus = null;
        var nextIndex = list.querySelectorAll('[data-attendee]').length;

        function normalizeProgram(raw) {
            var v = String(raw || '').toLowerCase().trim();
            if (!v || v === 'all' || v === 'all classes' || v.indexOf('all class') > -1) return 'all';
            if (v.indexOf('pilates') > -1 || v.indexOf('reformer') > -1) return 'reformer_pilates';
            if (v.indexOf('yoga') > -1) return 'regular_yoga';
            return v;
        }

        function normalizeClassType(raw) {
            var v = String(raw || '').toLowerCase().trim();
            if (!v) return '';
            if (v.indexOf('private group') > -1 || v.indexOf('private_group') > -1 || v.indexOf('private-group') > -1) {
                return 'private_group';
            }
            if (v.indexOf('private') > -1) return 'private';
            if (v.indexOf('group') > -1) return 'group';
            if (v === 'all' || v === 'all types' || v.indexOf('all type') > -1) return 'all';
            return '';
        }

        function resolveTargetClassType(attendeeQty) {
            var baseType = normalizeClassType(form.dataset.classType || '');
            if (!baseType) {
                baseType = normalizeClassType(form.dataset.className || '');
            }
            if (baseType === 'private') {
                return attendeeQty > 1 ? 'private_group' : 'private';
            }
            if (baseType === 'private_group') {
                return 'private_group';
            }
            return 'group';
        }

        function getTargetFullLabel(resolvedType) {
            var progKey = normalizeProgram(form.dataset.classCategory || form.dataset.className || '');
            var progLabel = progKey === 'reformer_pilates' ?
                'Reformer Pilates' :
                (progKey === 'regular_yoga' ? 'Regular Yoga' : (form.dataset.classCategory || form.dataset.className || ''));
            var typeLabel = resolvedType === 'private_group' ?
                'Private Group' :
                (resolvedType === 'private' ? 'Private' : 'Group');
            return (typeLabel + ' ' + progLabel).trim();
        }

        function syncPackageCompatibility(requiredSessions) {
            var targetProg = normalizeProgram(form.dataset.classCategory || form.dataset.className || '');
            var targetType = resolveTargetClassType(requiredSessions);
            var targetLabel = getTargetFullLabel(targetType);
            var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="package_id"]'));
            if (!radios.length) return null;

            var checkedRadio = null;
            var compatibleRadios = [];
            var firstCompatible = null;
            var firstEnoughCompatible = null;

            radios.forEach(function(r) {
                var pkgProg = normalizeProgram(r.dataset.program || r.dataset.pkgLabel || '');
                var pkgType = normalizeClassType(r.dataset.classType || r.dataset.pkgLabel || '') || 'group';

                var progCompat = (pkgProg === 'all' || !targetProg || targetProg === 'all' || pkgProg === targetProg);
                var typeCompat = (pkgType === 'all' || pkgType === targetType);
                var compatible = progCompat && typeCompat;

                var rem = parseInt(r.dataset.remaining, 10) || 0;
                r.disabled = !compatible;

                var wrap = r.closest('.bd-package');
                if (wrap) {
                    wrap.classList.toggle('is-incompatible', !compatible);
                    wrap.classList.remove('is-auto-default');
                    var lockEl = wrap.querySelector('.bd-package__lock');
                    if (lockEl) {
                        lockEl.textContent = 'Not applicable for ' + targetLabel;
                        lockEl.hidden = compatible;
                    }
                    var tagEl = wrap.querySelector('[data-earliest-tag]');
                    if (tagEl) {
                        tagEl.hidden = true;
                    }
                }

                if (compatible) {
                    compatibleRadios.push(r);
                    if (!firstCompatible) firstCompatible = r;
                    if (!firstEnoughCompatible && rem >= requiredSessions) firstEnoughCompatible = r;
                }

                if (r.checked) {
                    if (!compatible) {
                        r.checked = false;
                    } else {
                        checkedRadio = r;
                    }
                }
            });

            var hasMultipleSamePackages = compatibleRadios.length > 1;
            if (hasMultipleSamePackages && compatibleRadios[0]) {
                var firstWrap = compatibleRadios[0].closest('.bd-package');
                var firstTag = firstWrap ? firstWrap.querySelector('[data-earliest-tag]') : null;
                if (firstTag) {
                    firstTag.hidden = false;
                }
            } else if (compatibleRadios.length === 1) {
                compatibleRadios[0].checked = true;
                checkedRadio = compatibleRadios[0];
            }

            if (packagesHint) {
                packagesHint.textContent = hasMultipleSamePackages ?
                    'Select the package you want to use, or leave unselected to automatically use your earliest purchased package.' :
                    'Your compatible package is selected automatically.';
            }

            var effectiveRadio = checkedRadio || firstEnoughCompatible || firstCompatible;
            if (!checkedRadio && effectiveRadio) {
                var defWrap = effectiveRadio.closest('.bd-package');
                if (defWrap) defWrap.classList.add('is-auto-default');
            }
            if (clearPkgBtn) {
                clearPkgBtn.textContent = 'Use Earliest Package';
                clearPkgBtn.hidden = !hasMultipleSamePackages || !checkedRadio;
            }
            return effectiveRadio;
        }

        function updateAttendeeTitles() {
            list.querySelectorAll('[data-attendee]').forEach(function(card, index) {
                var title = card.querySelector('.js-attendee-title');
                var nameInput = card.querySelector('[data-attendee-name]');
                if (title) {
                    var attendeeName = nameInput ? nameInput.value.trim() : '';
                    title.textContent = attendeeName || 'Attendee ' + (index + 1);
                }
            });
        }

        function method() {
            var r = form.querySelector('input[name="payment_method"]:checked');
            return r ? r.value : 'dropin';
        }

        function count() {
            return list.querySelectorAll('[data-attendee]').length;
        }

        function resolveEffectivePackageRadio() {
            return syncPackageCompatibility(count());
        }

        function pkgRemaining() {
            var r = resolveEffectivePackageRadio();
            return r ? parseInt(r.dataset.remaining, 10) : 0;
        }

        function hasCompatiblePackage() {
            return form.querySelectorAll('input[name="package_id"]:not(:disabled)').length > 0;
        }

        function refresh() {
            var n = count(),
                isPackage = method() === 'package';
            var effectivePkg = syncPackageCompatibility(n);
            var targetLabel = getTargetFullLabel(resolveTargetClassType(n));
            var amount = '<em>Rp</em>' + (price * n).toLocaleString('en-US');
            subtotalEl.innerHTML = amount;
            totalEl.innerHTML = amount;
            totalBox.hidden = isPackage;
            packagesBox.hidden = !isPackage;

            if (typeof window.bdRefreshVoucher === 'function') {
                window.bdRefreshVoucher(price, n, isPackage);
            }

            var pkgInvalid = false;
            if (isPackage) {
                if (!hasCompatiblePackage() || !effectivePkg) {
                    packageError.textContent = 'No compatible active package available for ' + targetLabel + '.';
                    pkgInvalid = true;
                } else if (pkgRemaining() < n) {
                    packageError.textContent = 'Your package does not have enough remaining sessions.';
                    pkgInvalid = true;
                } else {
                    packageError.textContent = '';
                }
            } else {
                packageError.textContent = '';
            }

            addBtn.disabled = form.dataset.full === '1' || n >= max || n >= bookingLimit;
            if (max === 1) {
                addBtn.title = 'Only 1 spot left in this class';
            } else if (n >= bookingLimit) {
                addBtn.title = 'Maximum ' + bookingLimit + ' participants per booking';
            } else {
                addBtn.title = '';
            }

            var isVoucherInvalid = !isPackage && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid();
            continueBtn.disabled = checking || !agree.checked || form.dataset.full === '1' || isVoucherInvalid || pkgInvalid;
        }

        if (clearPkgBtn) {
            clearPkgBtn.addEventListener('click', function() {
                form.querySelectorAll('input[name="package_id"]').forEach(function(r) {
                    r.checked = false;
                });
                refresh();
            });
        }

        function setOpen(card, open) {
            var btn = card.querySelector('[data-toggle-attendee]');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
        }

        async function syncHoldQuantity(newQty) {
            var fd = new FormData();
            fd.append('action', 'update_count');
            fd.append('schedule_id', scheduleId);
            fd.append('qty', String(newQty));

            var res = await fetch('api-hold-slot.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            return await res.json();
        }

        addBtn.addEventListener('click', async function() {
            var nextQty = count() + 1;
            if (count() >= max || nextQty > bookingLimit || addBtn.disabled) return;

            addBtn.disabled = true;
            var origText = addBtn.textContent;
            addBtn.textContent = 'Reserving spot...';

            try {
                var result = await syncHoldQuantity(nextQty);
                if (result.status !== 'ok') {
                    alert(result.message || 'No more spots available in this class.');
                    max = count();
                    addBtn.textContent = origText;
                    refresh();
                    return;
                }

                if (slotsBadge) {
                    slotsBadge.textContent = result.remaining_others + ' / ' + totalSlots + ' Left';
                }

                list.querySelectorAll('[data-attendee]').forEach(function(c) {
                    setOpen(c, false);
                });

                list.insertAdjacentHTML(
                    'beforeend',
                    tpl.innerHTML.split('__INDEX__').join(String(nextIndex++))
                );

                updateAttendeeTitles();
            } catch (err) {
                console.error(err);
                alert('Failed to reserve additional spot. Please try again.');
            } finally {
                addBtn.textContent = origText;
                refresh();
            }
        });

        list.addEventListener('click', async function(e) {
            var toggle = e.target.closest('[data-toggle-attendee]');
            if (toggle) {
                setOpen(toggle.closest('[data-attendee]'), toggle.getAttribute('aria-expanded') !== 'true');
                return;
            }
            var rm = e.target.closest('[data-remove-attendee]');
            if (rm) {
                rm.closest('[data-attendee]').remove();
                updateAttendeeTitles();
                refresh();

                try {
                    var result = await syncHoldQuantity(count());
                    if (result.status === 'ok' && slotsBadge) {
                        slotsBadge.textContent = result.remaining_others + ' / ' + totalSlots + ' Left';
                    }
                } catch (err) {
                    console.error('Failed to release hold:', err);
                }
            }
        });

        if (backBtn) {
            backBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                var targetHref = backBtn.getAttribute('href');
                try {
                    var fd = new FormData();
                    fd.append('action', 'release');
                    await fetch('api-hold-slot.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        keepalive: true
                    });
                } catch (err) {
                    console.error(err);
                }
                window.location.href = targetHref;
            });
        }

        list.addEventListener('input', function(e) {
            var input = e.target;
            if (input.type === 'tel') {
                input.value = input.value.replace(/[^\d+]/g, '');
            }
            if (input.matches('[data-attendee-name]')) {
                updateAttendeeTitles();
            }
        });

        form.addEventListener('change', function(e) {
            if (e.target.matches('input[name="payment_method"], input[name="package_id"], #bd-agree')) refresh();
        });

        document.addEventListener('bd:voucher-changed', function() {
            refresh();
        });

        function setChecking(on) {
            checking = on;
            continueBtn.classList.toggle('is-loading', on);
            continueBtn.setAttribute('aria-busy', on ? 'true' : 'false');

            var spinner = continueBtn.querySelector('.bd-spinner');
            if (on) {
                if (!spinner) {
                    spinner = document.createElement('span');
                    spinner.className = 'bd-spinner';
                    spinner.setAttribute('aria-hidden', 'true');
                    continueBtn.insertBefore(spinner, continueBtn.firstChild);
                }
                if (continueLabel) continueLabel.textContent = 'Verifying slot & price...';
            } else {
                if (spinner) spinner.remove();
                if (continueLabel) continueLabel.textContent = continueText;
            }
            var isInvalidPackage = method() === 'package' && (!resolveEffectivePackageRadio() || pkgRemaining() < count());
            var isVoucherInvalid = method() !== 'package' && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid();
            continueBtn.disabled = on || !agree.checked || form.dataset.full === '1' || isInvalidPackage || isVoucherInvalid;
        }

        function showSlotModal(mode, text) {
            var isFull = mode === 'full';
            lastFocus = document.activeElement;
            slotTitle.textContent = isFull ? 'This slot is no longer available' : 'We could not verify your booking';
            slotText.textContent = text || (isFull ?
                'Oops, sorry! The last spot in your selected class has just been booked. Kindly choose another available class.' :
                'Please try again in a moment.');
            slotBack.hidden = !isFull;
            slotRefresh.textContent = isFull ? 'Refresh Availability' : 'Try Again';
            slotRefresh.dataset.mode = mode;
            slotModal.hidden = false;
            slotModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('bd-modal-open');
            slotRefresh.focus();
        }

        function hideSlotModal() {
            slotModal.hidden = true;
            slotModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('bd-modal-open');
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        slotRefresh.addEventListener('click', function() {
            if (slotRefresh.dataset.mode === 'full') {
                window.location.reload();
            } else {
                hideSlotModal();
            }
        });
        slotModal.addEventListener('click', function(e) {
            if (e.target === slotModal) hideSlotModal();
        });
        document.addEventListener('keydown', function(e) {
            if (slotModal.hidden) return;
            if (e.key === 'Escape') {
                hideSlotModal();
            } else if (e.key === 'Tab') {
                var items = [slotBack, slotRefresh].filter(function(el) {
                    return !el.hidden;
                });
                var first = items[0],
                    last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        window.addEventListener('pageshow', function(e) {
            if (e.persisted) setChecking(false);
        });

        function checkSlotAndContinue() {
            if (checking) return;

            if (method() === 'dropin' && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid()) {
                return;
            }

            setChecking(true);

            var fd = new FormData(form);
            var n = count();
            var finalTotal = (method() === 'dropin' && typeof window.bdGetFinalTotal === 'function') ?
                window.bdGetFinalTotal(price, n) :
                (price * n);

            if (method() === 'package') {
                var effPkg = resolveEffectivePackageRadio();
                if (effPkg && effPkg.value) {
                    fd.set('package_id', effPkg.value);
                    fd.set('user_package_id', effPkg.value);
                }
            }

            fd.append('attendee_count', String(n));
            fd.append('expected_total', String(finalTotal));

            var ctrl = window.AbortController ? new AbortController() : null;
            var timer = setTimeout(function() {
                if (ctrl) ctrl.abort();
            }, 15000);

            fetch(endpoint, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'fetch',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    signal: ctrl ? ctrl.signal : undefined
                })
                .then(function(res) {
                    return res.json().catch(function() {
                        return {
                            status: 'error'
                        };
                    });
                })
                .then(function(d) {
                    clearTimeout(timer);
                    d = d || {};

                    if (d.status === 'available' && d.redirect) {
                        if (continueLabel) continueLabel.textContent = 'Slot & price verified! Redirecting...';
                        window.location.href = d.redirect;
                        return;
                    }

                    setChecking(false);

                    if (d.status === 'full') {
                        showSlotModal('full', d.message);
                    } else if (d.status === 'unauthenticated' && d.redirect) {
                        window.location.href = d.redirect + (d.redirect.indexOf('?') > -1 ? '&' : '?') +
                            'redirect=' + encodeURIComponent(location.pathname + location.search);
                    } else if (d.status === 'invalid') {
                        form.submit();
                    } else {
                        showSlotModal('error', d.message);
                    }
                })
                .catch(function() {
                    clearTimeout(timer);
                    setChecking(false);
                    showSlotModal('error', 'We could not reach the server. Please check your connection and try again.');
                });
        }

        form.addEventListener('submit', function(e) {
            if (method() === 'package' && (!resolveEffectivePackageRadio() || pkgRemaining() < count())) {
                e.preventDefault();
                packagesBox.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }
            if (!form.checkValidity()) {
                e.preventDefault();
                list.querySelectorAll('[data-attendee]').forEach(function(c) {
                    if (c.querySelector(':invalid')) setOpen(c, true);
                });
                form.reportValidity();
                return;
            }
            if (!agree.checked) {
                e.preventDefault();
                agree.focus();
                return;
            }

            e.preventDefault();
            if (!window.fetch || !window.FormData) {
                form.submit();
                return;
            }
            checkSlotAndContinue();
        });

        updateAttendeeTitles();
        refresh();
    })();
</script>

<script src="../assets/js/booking-detail.js" defer></script>

<?php require __DIR__ . '/../includes/footer.php'; ?>