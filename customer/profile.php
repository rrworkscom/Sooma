<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../login-regist/connection.php';
require_once __DIR__ . '/../login-regist/auth/profile-password-notif.php';

if (empty($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user']['user_id'] ?? $_SESSION['user']['id'] ?? null;
if (!$userId) {
    header('Location: ../login.php');
    exit;
}

$headerType = 'customer';
$headerLogo = 'black';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_date(?string $iso): string
{
    $ts = $iso ? strtotime($iso) : false;
    return $ts ? date('j F Y', $ts) : '';
}

$flash_success = $_SESSION['profile_flash_success'] ?? '';
$flash_error   = $_SESSION['profile_flash_error'] ?? '';
unset($_SESSION['profile_flash_success'], $_SESSION['profile_flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['profile_flash_error'] = 'Invalid session token. Please try again.';
        header('Location: profile.php');
        exit;
    }

    $action = $_POST['action'];

    if ($action === 'update_account' && $userId) {
        $field = $_POST['field'] ?? '';

        if ($field === 'email') {
            $newEmail = trim((string) ($_POST['email'] ?? ''));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['profile_flash_error'] = 'Please enter a valid email address.';
            } else {
                $stmt = $pdo->prepare("UPDATE sooma_wellness.users SET email = :email WHERE user_id = :id");
                $stmt->execute(['email' => $newEmail, 'id' => $userId]);

                $_SESSION['user']['email'] = $newEmail;
                $_SESSION['profile_flash_success'] = 'Email address updated successfully.';
            }
        } elseif ($field === 'phone_number') {
            $newPhone = trim((string) ($_POST['phone_number'] ?? ''));
            if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $newPhone)) {
                $_SESSION['profile_flash_error'] = 'Please enter a valid phone number.';
            } else {
                $stmt = $pdo->prepare("UPDATE sooma_wellness.users SET phone_number = :phone WHERE user_id = :id");
                $stmt->execute(['phone' => $newPhone, 'id' => $userId]);

                $_SESSION['user']['phone_number'] = $newPhone;
                $_SESSION['profile_flash_success'] = 'Phone number updated successfully.';
            }
        } elseif ($field === 'password') {
            $newPass = (string) ($_POST['password'] ?? '');
            if (strlen($newPass) < 8) {
                $_SESSION['profile_flash_error'] = 'Password must be at least 8 characters long.';
            } else {
                try {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $enc  = sw_encrypt_password($newPass);

                    $stmt = $pdo->prepare(
                        "UPDATE sooma_wellness.users
                      SET password = :hash, encrypted_password = :enc
                      WHERE user_id = :id
                      RETURNING email, name"
                    );
                    $stmt->execute(['hash' => $hash, 'enc' => $enc, 'id' => $userId]);
                    $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$updatedUser) {
                        throw new RuntimeException('User record not found during password update.');
                    }

                    $targetEmail = trim((string) ($updatedUser['email'] ?? $_SESSION['user']['email'] ?? ''));
                    $targetName  = trim((string) ($updatedUser['name'] ?? $_SESSION['user']['name'] ?? 'Member'));

                    $mailSent = ($targetEmail !== '')
                        ? sw_send_password_changed_email($targetEmail, $targetName)
                        : false;

                    if ($mailSent) {
                        $_SESSION['profile_flash_success'] = 'Password updated successfully. A confirmation notification has been sent to ' . $targetEmail . '.';
                    } else {
                        $_SESSION['profile_flash_success'] = 'Password updated successfully, but we could not send the email notification right now.';
                    }
                } catch (Throwable $e) {
                    error_log('PROFILE PASSWORD UPDATE ERROR: ' . $e->getMessage());
                    $_SESSION['profile_flash_error'] = 'Unable to update the password right now. Please try again.';
                }
            }
        }
    }

    if ($action === 'update_personal' && $userId) {
        $name    = trim((string) ($_POST['name'] ?? ''));
        $dob     = trim((string) ($_POST['dob'] ?? ''));
        $gender  = trim((string) ($_POST['gender'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($name === '') {
            $_SESSION['profile_flash_error'] = 'Name cannot be empty.';
        } else {
            $stmt = $pdo->prepare("UPDATE sooma_wellness.users SET name = :name, dob = :dob, gender = :gender, address = :address WHERE user_id = :id");
            $stmt->execute([
                'name'    => $name,
                'dob'     => $dob !== '' ? $dob : null,
                'gender'  => $gender !== '' ? $gender : null,
                'address' => $address !== '' ? $address : null,
                'id'      => $userId,
            ]);

            $_SESSION['user']['name']    = $name;
            $_SESSION['user']['dob']     = $dob;
            $_SESSION['user']['gender']  = $gender;
            $_SESSION['user']['address'] = $address;
            $_SESSION['profile_flash_success'] = 'Personal information updated successfully.';
        }
    }

    if ($action === 'update_avatar' && isset($_FILES['avatar']) && $userId) {
        $file = $_FILES['avatar'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            } else {
                $imgInfo = @getimagesize($file['tmp_name']);
                $mime    = $imgInfo['mime'] ?? '';
            }

            if (!in_array($mime, $allowedTypes, true)) {
                $_SESSION['profile_flash_error'] = 'Only JPG, PNG, or WEBP images are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $_SESSION['profile_flash_error'] = 'Image size must be less than 2MB.';
            } else {
                $binaryData = file_get_contents($file['tmp_name']);

                if (function_exists('imagecreatefromstring')) {
                    $srcImg = @imagecreatefromstring($binaryData);
                    if ($srcImg !== false) {
                        $origW  = imagesx($srcImg);
                        $origH  = imagesy($srcImg);
                        $maxDim = 150;

                        if ($origW > $maxDim || $origH > $maxDim) {
                            $ratio  = min($maxDim / $origW, $maxDim / $origH);
                            $newW   = (int) round($origW * $ratio);
                            $newH   = (int) round($origH * $ratio);
                            $dstImg = imagecreatetruecolor($newW, $newH);
                            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                            imagedestroy($srcImg);
                            $srcImg = $dstImg;
                        }

                        ob_start();
                        imagejpeg($srcImg, null, 70);
                        $binaryData = ob_get_clean();
                        imagedestroy($srcImg);
                        $mime = 'image/jpeg';
                    }
                }

                $base64DataUri = 'data:' . $mime . ';base64,' . base64_encode($binaryData);

                $stmt = $pdo->prepare("UPDATE sooma_wellness.users SET avatar_url = :avatar WHERE user_id = :id");
                $stmt->execute(['avatar' => $base64DataUri, 'id' => $userId]);

                $_SESSION['user']['avatar_url'] = $base64DataUri;
                $_SESSION['profile_flash_success'] = 'Profile photo updated successfully.';
            }
        }
    }

    header('Location: profile.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sooma_wellness.users WHERE user_id = :id");
$stmt->execute(['id' => $userId]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

$encryptedPayload = null;
if (is_array($dbUser)) {
    $encryptedPayload = $dbUser['encrypted_password'] ?? null;
    unset($dbUser['encrypted_password']);
    $_SESSION['user'] = array_merge($_SESSION['user'], $dbUser);
}
$user = $_SESSION['user'];

$peekPassword = sw_decrypt_password(is_string($encryptedPayload) ? $encryptedPayload : null);

$avatarUrl = trim((string) ($user['avatar_url'] ?? ''));

$account_fields = [
    [
        'key'   => 'email',
        'label' => 'Email address',
        'value' => $user['email'] ?? '',
        'type'  => 'email',
        'raw'   => $user['email'] ?? '',
    ],
    [
        'key'   => 'phone_number',
        'label' => 'Phone number',
        'value' => $user['phone_number'] ?? '',
        'type'  => 'tel',
        'raw'   => $user['phone_number'] ?? '',
    ],
    [
        'key'   => 'password',
        'label' => 'Account Password',
        'value' => '••••••••',
        'secret' => $peekPassword,
        'type'  => 'password',
        'raw'   => '',
    ],
];

$dobFormatted = format_date($user['dob'] ?? '');
$personal_fields = [
    [
        'label'    => 'Name',
        'value'    => trim((string) ($user['name'] ?? '')),
        'is_empty' => trim((string) ($user['name'] ?? '')) === '',
    ],
    [
        'label'    => 'Date of Birth',
        'value'    => $dobFormatted,
        'is_empty' => $dobFormatted === '',
    ],
    [
        'label'    => 'Gender',
        'value'    => trim((string) ($user['gender'] ?? '')),
        'is_empty' => trim((string) ($user['gender'] ?? '')) === '',
    ],
    [
        'label'    => 'Address',
        'value'    => trim((string) ($user['address'] ?? '')),
        'is_empty' => trim((string) ($user['address'] ?? '')) === '',
    ],
];

$profile_menu = [
    ['label' => 'My profile',         'href' => 'profile.php',                 'icon' => 'ph ph-user-circle'],
    ['label' => 'My transaction',     'href' => 'profile-transaction.php',     'icon' => 'ph ph-receipt'],
    ['label' => 'My schedule',        'href' => 'profile-schedule.php',        'icon' => 'ph ph-calendar-blank'],
    ['label' => 'My package',         'href' => 'profile-package.php',         'icon' => 'ph ph-package'],
    ['label' => 'My request',         'href' => 'profile-private-request.php', 'icon' => 'ph ph-paper-plane-tilt'],
];

$current_page        = basename($_SERVER['SCRIPT_NAME'] ?? 'profile.php');
$navbar_avatar       = $avatarUrl;
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

        <?php if ($flash_success !== ''): ?>
            <div class="profile-alert profile-alert--success" role="status">
                <i class="ph-fill ph-check-circle" aria-hidden="true"></i>
                <span><?= e($flash_success) ?></span>
            </div>
        <?php elseif ($flash_error !== ''): ?>
            <div class="profile-alert profile-alert--error" role="alert">
                <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
                <span><?= e($flash_error) ?></span>
            </div>
        <?php endif; ?>

        <section class="profile-card" aria-labelledby="profile-name">
            <div class="profile-card__avatar-wrap">
                <div class="profile-card__avatar<?= $avatarUrl === '' ? ' is-empty' : '' ?>">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?= e($avatarUrl) ?>" alt="Photo of <?= e($user['name'] ?? 'Member') ?>" width="200" height="200">
                    <?php else: ?>
                        <i class="ph-fill ph-user" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>

                <form method="POST" action="profile.php" enctype="multipart/form-data" id="avatarUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_avatar">
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp" hidden>

                    <button type="button"
                        class="avatar-edit-btn"
                        id="btnEditAvatar"
                        title="Change profile photo"
                        aria-label="Change profile photo">
                        <i class="ph-fill ph-camera" aria-hidden="true"></i>
                    </button>
                </form>
            </div>

            <h1 class="profile-card__name" id="profile-name"><?= e($user['name'] ?? '') ?></h1>
            <p class="profile-card__joined">
                Joined since <time datetime="<?= e($user['joined_date'] ?? '') ?>"><?= e(format_date($user['joined_date'] ?? '')) ?></time>
            </p>

            <hr class="profile-card__divider">

            <dl class="account-grid">
                <?php foreach ($account_fields as $field): ?>
                    <div class="account-item" data-account-field="<?= e($field['key']) ?>">
                        <dt class="account-item__label"><?= e($field['label']) ?></dt>

                        <dd class="account-item__value account-view">
                            <?php if ($field['key'] === 'password'): ?>
                                <span class="account-value-text account-value-text--secret"
                                    data-password-view
                                    data-mask="<?= e($field['value']) ?>"
                                    data-secret="<?= e($field['secret'] ?? '') ?>"
                                    data-fallback="Not available (edit password once to enable)"><?= e($field['value']) ?></span>
                                <button type="button"
                                    class="btn-peek js-peek-view"
                                    aria-pressed="false"
                                    aria-label="Show password"
                                    title="Show password">
                                    <i class="ph ph-eye" aria-hidden="true"></i>
                                </button>
                            <?php else: ?>
                                <span><?= e($field['value']) ?></span>
                            <?php endif; ?>
                            <button type="button"
                                class="btn-edit-pill js-edit-account"
                                aria-label="Edit <?= e(strtolower($field['label'])) ?>">
                                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                                Edit
                            </button>
                        </dd>

                        <dd class="account-item__edit account-form-wrap" hidden>
                            <form method="POST" action="profile.php" class="inline-account-form">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="update_account">
                                <input type="hidden" name="field" value="<?= e($field['key']) ?>">

                                <?php if ($field['key'] === 'password'): ?>
                                    <div class="password-field">
                                        <input type="password"
                                            name="password"
                                            class="inline-input"
                                            value=""
                                            placeholder="Min. 8 characters"
                                            minlength="8"
                                            autocomplete="new-password"
                                            required>
                                        <button type="button"
                                            class="btn-peek js-peek-input"
                                            aria-pressed="false"
                                            aria-label="Show password"
                                            title="Show password">
                                            <i class="ph ph-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <input type="<?= e($field['type']) ?>"
                                        name="<?= e($field['key']) ?>"
                                        class="inline-input"
                                        value="<?= e($field['raw']) ?>"
                                        placeholder=""
                                        required>
                                <?php endif; ?>

                                <div class="inline-actions">
                                    <button type="submit" class="btn-save-sm">Save</button>
                                    <button type="button" class="btn-cancel-sm js-cancel-account">Cancel</button>
                                </div>
                            </form>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <div class="profile-main">

            <section class="info-card" aria-labelledby="personal-title">
                <div class="info-card__header">
                    <h2 class="info-card__title" id="personal-title">Personal Information</h2>
                    <button type="button" class="btn-edit-pill" id="btnEditPersonal">
                        <i class="ph ph-pencil-simple" aria-hidden="true"></i>
                        Edit
                    </button>
                </div>

                <dl class="info-list" id="personalViewMode">
                    <?php foreach ($personal_fields as $field): ?>
                        <div class="info-list__item">
                            <dt><?= e($field['label']) ?></dt>
                            <dd>
                                <?php if ($field['is_empty']): ?>
                                    <span class="field-warning" title="Please complete this information">
                                        <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
                                        <span>Not set yet</span>
                                    </span>
                                <?php else: ?>
                                    <?= e($field['value']) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>

                <form method="POST" action="profile.php" class="personal-edit-form" id="personalEditMode" hidden>
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_personal">

                    <div class="personal-form-grid">
                        <div class="personal-form-group">
                            <label for="inputName">Name</label>
                            <input type="text" id="inputName" name="name" class="inline-input"
                                value="<?= e($user['name'] ?? '') ?>" required>
                        </div>

                        <div class="personal-form-group">
                            <label for="inputDob">Date of Birth</label>
                            <input type="date" id="inputDob" name="dob" class="inline-input"
                                value="<?= e($user['dob'] ?? '') ?>">
                        </div>

                        <div class="personal-form-group">
                            <label for="inputGender">Gender</label>
                            <select id="inputGender" name="gender" class="inline-input">
                                <option value="" <?= ($user['gender'] ?? '') === '' ? 'selected' : '' ?>>-- Select Gender --</option>
                                <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            </select>
                        </div>

                        <div class="personal-form-group">
                            <label for="inputAddress">Address</label>
                            <input type="text" id="inputAddress" name="address" class="inline-input"
                                value="<?= e($user['address'] ?? '') ?>" placeholder="Enter your address">
                        </div>
                    </div>

                    <div class="personal-form-actions">
                        <button type="submit" class="btn-save-sm">Save Changes</button>
                        <button type="button" class="btn-cancel-sm" id="btnCancelPersonal">Cancel</button>
                    </div>
                </form>
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

    <script src="../assets/js/profile.js" defer></script>

</body>

</html>