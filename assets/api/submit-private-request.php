<?php

declare(strict_types=1);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse(int $statusCode, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function getTableColumns(PDO $pdo, string $schema, string $table): array
{
    $stmt = $pdo->prepare("
        select column_name
          from information_schema.columns
         where table_schema = :schema
           and table_name = :table
    ");
    $stmt->execute([
        ':schema' => $schema,
        ':table'  => $table,
    ]);

    return array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sprNormalizeProgram(string $raw): string
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

function sprNormalizeClassType(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === '') {
        return '';
    }
    if (
        str_contains($v, 'private group') ||
        str_contains($v, 'private_group') ||
        str_contains($v, 'private-group') ||
        str_contains($v, 'semi private') ||
        str_contains($v, 'semi-private')
    ) {
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

function sprIsPackageCompatible(array $pkg, string $targetCategory, string $targetClassName, int $attendeeCount): bool
{
    $pkgProg = 'all';
    foreach (['category', 'name'] as $field) {
        if (!empty($pkg[$field])) {
            $norm = sprNormalizeProgram((string) $pkg[$field]);
            if (in_array($norm, ['all', 'reformer_pilates', 'regular_yoga'], true)) {
                $pkgProg = $norm;
                break;
            }
        }
    }

    $targetProg = sprNormalizeProgram($targetCategory);
    if (!in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true)) {
        $targetProg = sprNormalizeProgram($targetClassName);
    }

    if ($pkgProg !== 'all' && in_array($targetProg, ['reformer_pilates', 'regular_yoga'], true) && $pkgProg !== $targetProg) {
        return false;
    }

    $pkgType = '';
    if (!empty($pkg['pkg_class_type'])) {
        $pkgType = sprNormalizeClassType((string) $pkg['pkg_class_type']);
    }
    if ($pkgType === '') {
        $combined = trim(implode(' ', array_filter([
            (string) ($pkg['name'] ?? ''),
            (string) ($pkg['category'] ?? ''),
            (string) ($pkg['type'] ?? ''),
        ])));
        $pkgType = sprNormalizeClassType($combined);
        if ($pkgType === '') {
            $pkgType = 'group';
        }
    }

    $targetType = $attendeeCount > 1 ? 'private_group' : 'private';
    return $pkgType === 'all' || $pkgType === $targetType;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$connPath = __DIR__ . '/../../login-regist/connection.php';
if (!file_exists($connPath)) {
    sendJsonResponse(500, ['success' => false, 'message' => 'Database connection file not found.']);
}

require_once __DIR__ . '/../../login-regist/connection.php';

/** @var PDO $pdo */
if (!isset($pdo) || !$pdo instanceof PDO) {
    sendJsonResponse(500, ['success' => false, 'message' => 'Database connection not available.']);
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? $_SESSION['id'] ?? 0);
if ($userId <= 0) {
    sendJsonResponse(401, ['success' => false, 'message' => 'Please log in before submitting a private class request.']);
}

$classIndexRaw = trim((string) ($_POST['class_index'] ?? ''));
$classIdRaw    = trim((string) ($_POST['class_id'] ?? ''));
$className     = trim((string) ($_POST['class_name'] ?? ''));
$category      = trim((string) ($_POST['category'] ?? ''));

$allowedLevels = ['Beginner', 'Intermediate', 'Advanced'];
$rawLevel      = trim((string) ($_POST['level'] ?? $_POST['class_level'] ?? ''));
$normLevel     = ucfirst(strtolower($rawLevel));
$level         = in_array($normLevel, $allowedLevels, true) ? $normLevel : 'Beginner';

$date          = trim((string) ($_POST['date'] ?? $_POST['preferred_date'] ?? ''));
$time          = trim((string) ($_POST['time'] ?? $_POST['start_time'] ?? ''));
$coach         = trim((string) ($_POST['coach'] ?? $_POST['preferred_coach'] ?? ''));
$specialNotes  = trim((string) ($_POST['special_notes'] ?? $_POST['notes'] ?? $_POST['session_notes'] ?? ''));
$paymentMethod = strtolower(trim((string) ($_POST['payment_method'] ?? 'dropin')));
$packageId     = (int) ($_POST['user_package_id'] ?? $_POST['package_id'] ?? 0);

if (!in_array($paymentMethod, ['dropin', 'package'], true)) {
    $paymentMethod = 'dropin';
}

if (($classIdRaw === '' && $className === '') || $date === '' || $time === '') {
    sendJsonResponse(422, ['success' => false, 'message' => 'Please complete class, date, and time fields.']);
}

try {
    $studioTz = new DateTimeZone('Asia/Jakarta');
    $nowWib   = new DateTimeImmutable('now', $studioTz);
    $todayWib = new DateTimeImmutable('today', $studioTz);
    $maxWib   = $todayWib->modify('+7 days');

    $startDt  = new DateTimeImmutable($date . ' ' . $time, $studioTz);
} catch (Throwable $dtErr) {
    sendJsonResponse(422, ['success' => false, 'message' => 'Invalid preferred date or time format.']);
}

$reqDay = new DateTimeImmutable($startDt->format('Y-m-d'), $studioTz);
if ($reqDay < $todayWib || $reqDay > $maxWib) {
    sendJsonResponse(422, [
        'success' => false,
        'message' => 'Preferred date must be between today and 7 days from now.',
    ]);
}

if ($startDt <= $nowWib) {
    sendJsonResponse(422, [
        'success' => false,
        'message' => 'Selected time slot for today has already passed.',
    ]);
}

$endDt = $startDt->modify('+1 hour');

$attendees = [];
$medicalSummaryParts = [];

foreach ((array) ($_POST['attendees'] ?? []) as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name  = trim((string) ($row['name'] ?? ''));
    $phone = (string) preg_replace('/[^\d+]/', '', (string) ($row['phone'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    $med   = trim((string) ($row['medical'] ?? ''));
    $np    = !empty($row['not_pregnant']) && $row['not_pregnant'] !== '0';

    if ($name === '' || $phone === '' || $email === '') {
        continue;
    }

    $attendees[] = [
        'name'         => $name,
        'phone'        => $phone,
        'email'        => $email,
        'medical'      => $med,
        'not_pregnant' => $np,
    ];

    if ($med !== '') {
        $medicalSummaryParts[] = $med;
    }
}

if (empty($attendees)) {
    sendJsonResponse(422, ['success' => false, 'message' => 'Please fill in at least one valid participant.']);
}

if (count($attendees) > 4) {
    sendJsonResponse(422, ['success' => false, 'message' => 'Maximum 4 participants per private request.']);
}

if ($paymentMethod === 'package' && $packageId <= 0) {
    sendJsonResponse(422, ['success' => false, 'message' => 'Please select an active package before submitting.']);
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('set search_path to sooma_wellness, public');

    $availableCols = getTableColumns($pdo, 'sooma_wellness', 'request_classes');
    $neededCols = [
        'level'           => 'varchar(50)',
        'class_type'      => 'varchar(50)',
        'payment_method'  => 'varchar(32)',
        'user_package_id' => 'int',
    ];
    $addedAnyCol = false;
    foreach ($neededCols as $colName => $colDef) {
        if (!isset($availableCols[$colName])) {
            try {
                $pdo->exec("alter table sooma_wellness.request_classes add column if not exists {$colName} {$colDef}");
                $addedAnyCol = true;
            } catch (Throwable $ignore) {
                // Lanjut jika role database tidak memiliki izin ALTER TABLE
            }
        }
    }
    if ($addedAnyCol) {
        $availableCols = getTableColumns($pdo, 'sooma_wellness', 'request_classes');
    }

    $pdo->beginTransaction();

    // 1. Cari class_id di master_classes
    $masterClass = null;
    $hasExplicitClassId = ($classIdRaw !== '' && ctype_digit($classIdRaw) && $classIdRaw !== $classIndexRaw);

    if ($hasExplicitClassId) {
        $st = $pdo->prepare('select class_id, class_name from sooma_wellness.master_classes where class_id = :cid limit 1');
        $st->execute([':cid' => (int) $classIdRaw]);
        $masterClass = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$masterClass && $className !== '') {
        $normalizedName = trim((string) preg_replace('/^private\s+/i', '', $className));

        $st = $pdo->prepare("
            select class_id, class_name
              from sooma_wellness.master_classes
             where lower(trim(class_name)) in (lower(:exact_name_1), lower(:norm_name))
             order by
               case when lower(trim(class_name)) = lower(:exact_name_2) then 0 else 1 end,
               class_id asc
             limit 1
        ");
        $st->execute([
            ':exact_name_1' => $className,
            ':exact_name_2' => $className,
            ':norm_name'    => $normalizedName,
        ]);
        $masterClass = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$masterClass && $normalizedName !== '') {
            $stLike = $pdo->prepare("
                select class_id, class_name
                  from sooma_wellness.master_classes
                 where lower(class_name) like '%' || lower(:norm_name) || '%'
                    or (:cat_1 <> '' and lower(class_name) like '%' || lower(:cat_2) || '%')
                 order by class_id asc
                 limit 1
            ");
            $stLike->execute([
                ':norm_name' => $normalizedName,
                ':cat_1'     => $category,
                ':cat_2'     => $category,
            ]);
            $masterClass = $stLike->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if (!$masterClass) {
        $pdo->rollBack();
        sendJsonResponse(404, [
            'success' => false,
            'message' => 'Selected class (' . ($className !== '' ? $className : $classIdRaw) . ') was not found in master classes.',
        ]);
    }

    // 2. Ambil atau buat customer_id di sooma_wellness.customers
    $stCust = $pdo->prepare('select customer_id from sooma_wellness.customers where user_id = :uid limit 1');
    $stCust->execute([':uid' => $userId]);
    $customerId = $stCust->fetchColumn();

    if ($customerId === false) {
        $insCust = $pdo->prepare('insert into sooma_wellness.customers (user_id) values (:uid) returning customer_id');
        $insCust->execute([':uid' => $userId]);
        $customerId = (int) $insCust->fetchColumn();
    } else {
        $customerId = (int) $customerId;
    }

    // 3. Validasi paket aktif di sooma_wellness.user_packages (kuota baru dipotong di payment-private-request.php setelah Admin ACC)
    $qty = count($attendees);
    $resolvedClassName = $className !== '' ? $className : (string) $masterClass['class_name'];
    $resolvedCategory  = $category !== '' ? $category : trim((string) preg_replace('/^private\s+/i', '', $resolvedClassName));
    $resolvedClassType = $qty > 1 ? 'Private Group' : 'Private';

    if ($paymentMethod === 'package' && $packageId > 0) {
        $stPkg = $pdo->prepare("
            select up.user_package_id,
                   up.remaining_sessions as rem,
                   coalesce(pkg.package_name, 'Class Package') as name,
                   coalesce(
                       nullif(trim(to_jsonb(pkg)->>'variant_label'), ''),
                       coalesce(pkg.total_sessions, 1)::text || ' Sessions'
                   ) as type,
                   coalesce(
                       nullif(trim(to_jsonb(pkg)->>'class_type'), ''),
                       nullif(trim(to_jsonb(pkg)->>'package_type'), ''),
                       ''
                   ) as pkg_class_type,
                   coalesce(mc.class_name, 'All Classes') as category
              from sooma_wellness.user_packages up
              join sooma_wellness.packages pkg on pkg.package_id = up.package_id
              left join sooma_wellness.master_classes mc on mc.class_id = pkg.class_id
             where up.user_package_id = :upid
               and up.customer_id = :cid
               and coalesce(nullif(trim(to_jsonb(up)->>'status'), ''), 'active') = 'active'
               and (up.expired_at is null or up.expired_at > clock_timestamp())
             limit 1
        ");
        $stPkg->execute([
            ':upid' => $packageId,
            ':cid'  => $customerId,
        ]);
        $pkgRow = $stPkg->fetch(PDO::FETCH_ASSOC);

        if (!$pkgRow) {
            $pdo->rollBack();
            sendJsonResponse(404, [
                'success' => false,
                'message' => 'Selected active package was not found on your account.',
            ]);
        }

        if (!sprIsPackageCompatible($pkgRow, $resolvedCategory, $resolvedClassName, $qty)) {
            $pdo->rollBack();
            sendJsonResponse(422, [
                'success' => false,
                'message' => 'Selected package is not applicable for ' . $resolvedClassType . ' ' . $resolvedCategory . '.',
            ]);
        }

        if ((int) $pkgRow['rem'] < $qty) {
            $pdo->rollBack();
            sendJsonResponse(422, [
                'success' => false,
                'message' => 'Your package does not have enough remaining sessions (' . $qty . ' required).',
            ]);
        }
    }

    // 4. Siapkan payload kolom untuk sooma_wellness.request_classes
    $medicalNotes  = !empty($medicalSummaryParts) ? implode('; ', $medicalSummaryParts) : null;
    $coachText     = $coach !== '' ? $coach : 'Any Available Coach';
    $resolvedLevel = $level;

    $columns = ['customer_id', 'class_id', 'preferred_date', 'preferred_time', 'medical_notes', 'status'];
    $holders = [':customer_id', ':class_id', 'cast(:preferred_date as date)', 'cast(:preferred_time as time)', ':medical_notes', ':status'];
    $params  = [
        ':customer_id'    => $customerId,
        ':class_id'       => (int) $masterClass['class_id'],
        ':preferred_date' => $startDt->format('Y-m-d'),
        ':preferred_time' => $startDt->format('H:i:s'),
        ':medical_notes'  => $medicalNotes,
        ':status'         => 'pending',
    ];

    if (isset($availableCols['preferred_coach'])) {
        $columns[] = 'preferred_coach';
        $holders[] = ':preferred_coach';
        $params[':preferred_coach'] = $coachText;
    }
    if (isset($availableCols['level'])) {
        $columns[] = 'level';
        $holders[] = ':level';
        $params[':level'] = $resolvedLevel;
    }
    if (isset($availableCols['class_type'])) {
        $columns[] = 'class_type';
        $holders[] = ':class_type';
        $params[':class_type'] = $resolvedClassType;
    }
    if (isset($availableCols['attendee_count'])) {
        $columns[] = 'attendee_count';
        $holders[] = ':attendee_count';
        $params[':attendee_count'] = $qty;
    }
    if (isset($availableCols['attendees_data'])) {
        $columns[] = 'attendees_data';
        $holders[] = 'cast(:attendees_data as jsonb)';
        $params[':attendees_data'] = json_encode($attendees, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    if (isset($availableCols['special_notes'])) {
        $columns[] = 'special_notes';
        $holders[] = ':special_notes';
        $params[':special_notes'] = $specialNotes !== '' ? $specialNotes : null;
    }
    if (isset($availableCols['payment_method'])) {
        $columns[] = 'payment_method';
        $holders[] = ':payment_method';
        $params[':payment_method'] = $paymentMethod;
    }
    if (isset($availableCols['user_package_id'])) {
        $columns[] = 'user_package_id';
        $holders[] = ':user_package_id';
        $params[':user_package_id'] = ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null;
    }
    if (isset($availableCols['package_id'])) {
        $columns[] = 'package_id';
        $holders[] = ':package_id';
        $params[':package_id'] = ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null;
    }
    if (isset($availableCols['customer_package_id'])) {
        $columns[] = 'customer_package_id';
        $holders[] = ':customer_package_id';
        $params[':customer_package_id'] = ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null;
    }

    $sql = sprintf(
        'insert into sooma_wellness.request_classes (%s) values (%s) returning request_id',
        implode(', ', $columns),
        implode(', ', $holders)
    );

    $insReq = $pdo->prepare($sql);
    $insReq->execute($params);
    $requestId   = (int) $insReq->fetchColumn();
    $requestCode = sprintf('REQ-%04d', $requestId);

    $pdo->commit();

    // 5. Simpan ringkasan pengajuan terakhir ke session
    $defaultImage = stripos($resolvedCategory . ' ' . $resolvedClassName, 'yoga') !== false
        ? '../assets/images/class-yoga.jpg'
        : '../assets/images/class-pilates.jpg';

    $summarySessionData = [
        'request_id'      => $requestId,
        'booking_code'    => $requestCode,
        'class_name'      => $resolvedClassName,
        'category'        => $resolvedCategory,
        'class_type'      => $resolvedClassType,
        'image'           => $defaultImage,
        'date_readable'   => $startDt->format('l, j F Y'),
        'time_readable'   => $startDt->format('H.i') . ' - ' . $endDt->format('H.i') . ' WIB',
        'coach'           => $coachText,
        'level'           => $resolvedLevel,
        'payment_method'  => $paymentMethod,
        'user_package_id' => ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null,
        'package_id'      => ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null,
        'people_text'     => $qty . ($qty === 1 ? ' Person' : ' People'),
        'participants'    => implode(', ', array_map(static fn(array $a): string => $a['name'], $attendees)),
        'lead_phone'      => $attendees[0]['phone'] ?? '-',
    ];

    $_SESSION['rp_last_submitted']    = $summarySessionData;
    $_SESSION['last_private_request'] = $summarySessionData;

    sendJsonResponse(200, [
        'success'         => true,
        'request_id'      => $requestId,
        'booking_code'    => $requestCode,
        'class_type'      => $resolvedClassType,
        'level'           => $resolvedLevel,
        'payment_method'  => $paymentMethod,
        'user_package_id' => ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null,
        'package_id'      => ($paymentMethod === 'package' && $packageId > 0) ? $packageId : null,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('submit-private-request error: ' . $e->getMessage());
    sendJsonResponse(500, [
        'success' => false,
        'message' => 'Failed to save private request: ' . $e->getMessage(),
    ]);
}