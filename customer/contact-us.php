<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = [

    'delivery' => 'log',
    'to'       => 'soomawellness@gmail.com',
    'from'     => 'no-reply@soomawellness.com',
];

$page_intro = 'Do you have questions about classes, reservations, or need recommendations for the right package? Our team is here to help you.';

$contact_info = [
    ['label' => 'Hours',    'value' => '24 / 7 for emails', 'icon' => 'ph-fill ph-clock',    'icon_w' => 53, 'icon_h' => 53, 'href' => ''],
    ['label' => 'Location', 'value' => 'Jl. Ciliwung no.62, Blitar',      'icon' => 'ph-fill ph-map-pin', 'icon_w' => 47, 'icon_h' => 60, 'href' => ''],
    ['label' => 'Email',    'value' => 'soomawellness@gmail.com',         'icon' => 'ph-fill ph-envelope',    'icon_w' => 53, 'icon_h' => 42, 'href' => 'mailto:soomawellness@gmail.com'],
];

$socials = [
    ['name' => 'Instagram', 'href' => 'https://www.instagram.com/soomawellness', 'icon' => 'ph-fill ph-instagram-logo'],
    ['name' => 'TikTok',    'href' => 'https://www.tiktok.com/@soomawellness',   'icon' => 'ph-fill ph-tiktok-logo'],
    ['name' => 'WhatsApp',  'href' => 'https://wa.me/6200000000000',             'icon' => 'ph-fill ph-whatsapp-logo'],
];

$fields = [
    'fullname' => ['label' => 'Full Name',    'type' => 'text',     'placeholder' => 'Enter your full name',    'autocomplete' => 'name'],
    'email'    => ['label' => 'Email',        'type' => 'email',    'placeholder' => 'Enter your email',        'autocomplete' => 'email'],
    'phone'    => ['label' => 'Phone Number', 'type' => 'tel',      'placeholder' => 'Enter your phone number', 'autocomplete' => 'tel'],
    'message'  => ['label' => 'Message',      'type' => 'textarea', 'placeholder' => 'Enter your message',      'autocomplete' => 'off'],
];

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean_input(mixed $value, bool $multiline = false): string
{
    $value = is_string($value) ? $value : '';
    $pattern = $multiline ? '/[^\P{C}\n\r\t]+/u' : '/\p{C}+/u';
    return trim((string) preg_replace($pattern, '', $value));
}

function deliver_message(array $data, array $config): bool
{
    if ($config['delivery'] === 'mail') {
        $subject = 'New contact message from ' . str_replace(["\r", "\n"], ' ', $data['fullname']);
        $body    = "Name: {$data['fullname']}\nEmail: {$data['email']}\nPhone: {$data['phone']}\n\nMessage:\n{$data['message']}\n";
        return mail($config['to'], $subject, $body, [
            'From'         => $config['from'],
            'Reply-To'     => $data['email'],         
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    $dir = __DIR__ . '/storage';
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
        file_put_contents($dir . '/.htaccess', "Require all denied\n");   
    }
    $line = json_encode($data + ['received_at' => date('c')], JSON_UNESCAPED_UNICODE) . "\n";
    return file_put_contents($dir . '/contact-messages.log', $line, FILE_APPEND | LOCK_EX) !== false;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$old    = ['fullname' => '', 'email' => '', 'phone' => '', 'message' => ''];
$errors = [];          
$form_error = '';      

$success = !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    $token_ok = hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''));

    foreach ($old as $name => $_) {
        $old[$name] = clean_input($_POST[$name] ?? '', $name === 'message');
    }

    if (!$token_ok) {
        $form_error = 'Your session has expired. Please try again.';
    } elseif (!empty($_POST['website'])) {
        $_SESSION['contact_sent'] = true;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        if ($old['fullname'] === '') {
            $errors['fullname'] = 'Please enter your full name.';
        } elseif (strlen($old['fullname']) > 100) {
            $errors['fullname'] = 'Your name is too long (max 100 characters).';
        }

        if ($old['email'] === '') {
            $errors['email'] = 'Please enter your email.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($old['phone'] === '') {
            $errors['phone'] = 'Please enter your phone number.';
        } elseif (!preg_match('/^\+?[0-9\s\-()]{8,20}$/', $old['phone'])) {
            $errors['phone'] = 'Please enter a valid phone number.';
        }

        if ($old['message'] === '') {
            $errors['message'] = 'Please enter your message.';
        } elseif (strlen($old['message']) > 2000) {
            $errors['message'] = 'Your message is too long (max 2000 characters).';
        }

        if (!$errors) {
            if (deliver_message($old, $config)) {
                $_SESSION['contact_sent'] = true;
                $_SESSION['csrf'] = bin2hex(random_bytes(16));     
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '#contact-form');
                exit;
            }
            $form_error = 'Sorry, we could not send your message right now. Please try again later.';
        }
    }
}

$current_page = 'contact-us.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Us — Sooma Wellness</title>
    <meta name="description" content="<?= e($page_intro) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caladea:wght@700&family=Montserrat:wght@400;500;600;700;800&family=Montserrat+Alternates:wght@400&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/contact-us.css">
    <link rel="stylesheet" href="../assets/css/footer.css">
</head>

<body>

    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="contact-page">

        <section class="contact-info" aria-labelledby="contact-title">
            <h1 class="contact-info__title" id="contact-title">Contact us</h1>
            <p class="contact-info__intro"><?= e($page_intro) ?></p>

            <ul class="contact-list">
                <?php foreach ($contact_info as $item): ?>
                    <li class="contact-item">
                        <span class="contact-item__icon">
                            <i class="<?= e($item['icon']) ?>" aria-hidden="true"></i>
                        </span>

                        <div class="contact-item__text">
                            <strong class="contact-item__label"><?= e($item['label']) ?></strong>
                            <?php if ($item['href'] !== ''): ?>
                                <a class="contact-item__value" href="<?= e($item['href']) ?>"><?= e($item['value']) ?></a>
                            <?php else: ?>
                                <span class="contact-item__value"><?= e($item['value']) ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="socials">
                <h2 class="socials__title">Connect with us on social media</h2>
                <ul class="socials__list">
                    <?php foreach ($socials as $social): ?>
                        <li>
                            <a class="socials__link" href="<?= e($social['href']) ?>"
                                target="_blank" rel="noopener noreferrer"
                                aria-label="Sooma Wellness on <?= e($social['name']) ?>">
                                <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="contact-form-card" id="contact-form" aria-label="Send us a message">
            <form class="contact-form" action="" method="POST" novalidate>

                <?php if ($success): ?>
                    <div class="form-alert form-alert--success" role="status">
                        Thank you! Your message has been sent.
                    </div>
                <?php elseif ($form_error !== '' || $errors): ?>
                    <div class="form-alert form-alert--error" role="alert">
                        <?= e($form_error !== '' ? $form_error : 'Please check the highlighted fields and try again.') ?>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

                <div class="hp-field" aria-hidden="true">
                    <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <?php foreach ($fields as $name => $field):
                    $has_error = isset($errors[$name]);
                    $input_id  = 'field-' . $name;
                    $error_id  = $input_id . '-error';
                ?>
                    <div class="form-field<?= $has_error ? ' has-error' : '' ?>">
                        <label class="form-field__label" for="<?= e($input_id) ?>"><?= e($field['label']) ?></label>

                        <?php if ($field['type'] === 'textarea'): ?>
                            <textarea class="form-field__control" id="<?= e($input_id) ?>" name="<?= e($name) ?>"
                                placeholder="<?= e($field['placeholder']) ?>" required maxlength="2000"
                                <?= $has_error ? 'aria-invalid="true" aria-describedby="' . e($error_id) . '"' : '' ?>><?= e($old[$name]) ?></textarea>
                        <?php else: ?>
                            <input class="form-field__control" id="<?= e($input_id) ?>" name="<?= e($name) ?>"
                                type="<?= e($field['type']) ?>" value="<?= e($old[$name]) ?>"
                                placeholder="<?= e($field['placeholder']) ?>"
                                autocomplete="<?= e($field['autocomplete']) ?>" required
                                <?= $has_error ? 'aria-invalid="true" aria-describedby="' . e($error_id) . '"' : '' ?>>
                        <?php endif; ?>

                        <?php if ($has_error): ?>
                            <p class="form-field__error" id="<?= e($error_id) ?>"><?= e($errors[$name]) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button class="btn-submit" type="submit" name="submit" value="1">
                    Submit <i class="icon-arrow" aria-hidden="true"></i>
                </button>
            </form>
        </section>

    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>