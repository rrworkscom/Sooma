<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requireLogin = true;
$loginPage    = '../login-regist/auth/login.php';
$timetableUrl = 'timetable.php';
$paymentUrl   = 'payment.php';
$termsUrl     = '#';

if (!function_exists('e')) {
    function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}


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

    foreach (['instructor', 'className', 'category', 'level', 'type'] as $k) {
        if ($str($k) === '' || strlen($str($k)) > 60) {
            return null;
        }
    }

    $capacity = filter_var(
        $q['capacity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 10]]
    );

    $taken = filter_var(
        $q['slot'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if ($capacity === false || $taken === false || $taken >= $capacity) {
        return null;
    }

    $classImages = [
        'Reformer Pilates' => '../assets/images/class-pilates.jpg',
        'Regular Yoga'     => '../assets/images/class-yoga.jpg',
    ];

    $defaultImage = '../assets/images/class-pilates.jpg';
    $defaultPrice = 120000;

    $className = $str('className');

    return [
        'category'    => $str('category'),
        'class_name'  => $className,
        'image'       => $classImages[$className] ?? $defaultImage,
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
        'slots_left'  => $capacity - $taken,
        'slots_total' => $capacity,
        'price'       => $defaultPrice,

        'query' => [
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

function bd_get_packages(int $userId): array
{
    return [['id' => 1, 'name' => 'Reformer Pilates Bundle', 'type' => 'Group Class', 'remaining' => 2, 'total' => 4]];
}

function bd_attendee_card($idx, array $a, array $errors, bool $removable): void
{
    $i      = (string) $idx;
    $p      = "attendees.{$i}.";
    $title = 'Attendee ' . ((int) $idx + 1);
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
                        <?= !empty($a['not_pregnant']) ? 'checked' : '' ?>>

                    <span class="bd-check__box" aria-hidden="false">
                        <i class="ph ph-square"></i>
                        <i class="ph ph-check-square"></i>
                    </span>

                    <span>Participant is <strong>not</strong> currently pregnant</span>
                </label>

                <span>Participant is <strong>not</strong> currently pregnant</span>
                </label>
            </div>
        </div>
    </div>
<?php
}

$sessionUser = $_SESSION['user'] ?? [];
if ($requireLogin && !$sessionUser) {
    header('Location: /Sooma/login-regist/login.php?redirect=' . urlencode('/Sooma/customer/booking-detail.php'));
    exit;
}
$currentUser = [
    'id'    => (int) ($sessionUser['id'] ?? 0),
    'name'  => (string) (
        $sessionUser['full_name']
        ?? $sessionUser['name']
        ?? $sessionUser['nama']
        ?? ''
    ),
    'phone' => (string) (
        $sessionUser['phone']
        ?? $sessionUser['no_hp']
        ?? ''
    ),
    'email' => (string) ($sessionUser['email'] ?? ''),
];
$class = bd_class_from_request($_GET);

if (!$class) {
    header('Location: ' . $timetableUrl);
    exit;
}

$packages     = bd_get_packages($currentUser['id']);
$maxAttendees = max(1, (int) $class['slots_left']);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$old = [
    'attendees' => [[
        'name' => $currentUser['name'],
        'phone' => $currentUser['phone'],
        'email' => $currentUser['email'],
        'medical' => '',
        'not_pregnant' => '1',
    ]],
    'payment_method' => 'dropin',
    'package_id'     => (string) ($packages[0]['id'] ?? ''),
    'agree'          => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request token.');
    }

    $attendees = [];
    foreach ((array) ($_POST['attendees'] ?? []) as $row) {
        $attendees[] = [
            'name'         => trim((string) ($row['name'] ?? '')),
            'phone'        => preg_replace('/[^\d+]/', '', (string) ($row['phone'] ?? '')),
            'email'        => trim((string) ($row['email'] ?? '')),
            'medical'      => trim((string) ($row['medical'] ?? '')),
            'not_pregnant' => !empty($row['not_pregnant']) ? '1' : '',
        ];
    }
    $attendees     = array_slice($attendees, 0, $maxAttendees);
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['dropin', 'package'], true)
        ? $_POST['payment_method'] : 'dropin';
    $packageId     = (string) ($_POST['package_id'] ?? '');
    $agree         = !empty($_POST['agree']);

    $old = [
        'attendees'      => $attendees ?: $old['attendees'],
        'payment_method' => $paymentMethod,
        'package_id'     => $packageId,
        'agree'          => $agree ? '1' : '',
    ];

    if (!$attendees) {
        $errors['form'] = 'Please add at least one attendee.';
    }
    foreach ($attendees as $i => $a) {
        if ($a['name'] === '' || strlen($a['name']) > 150) {
            $errors["attendees.$i.name"] = 'Please enter the attendee name.';
        }
        if (!preg_match('/^(\+62|62|0)8\d{7,12}$/', $a['phone'])) {
            $errors["attendees.$i.phone"] = 'Enter a valid Indonesian phone number.';
        }
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
            $errors["attendees.$i.email"] = 'Enter a valid email address.';
        }
    }

    $selectedPackage = null;
    if ($paymentMethod === 'package') {
        foreach ($packages as $pkg) {
            if ((string) $pkg['id'] === $packageId) {
                $selectedPackage = $pkg;
            }
        }
        if (!$selectedPackage) {
            $errors['package'] = 'Please choose a package.';
        } elseif ($selectedPackage['remaining'] < count($attendees)) {
            $errors['package'] = 'Your package does not have enough remaining sessions.';
        }
    }
    if (!$agree) {
        $errors['agree'] = 'You must agree to the Terms & Conditions.';
    }

    if (!$errors) {
        $_SESSION['booking_draft'] = [
            'class'          => $class['query'],
            'attendees'      => $attendees,
            'payment_method' => $paymentMethod,
            'package_id'     => $selectedPackage['id'] ?? null,
            'subtotal'       => $paymentMethod === 'dropin' ? $class['price'] * count($attendees) : 0,
        ];
        header('Location: ' . $paymentUrl);
        exit;
    }
}

$pageTitle  = 'Book Class Session';
$bodyClass  = 'booking-detail-page';
$headerType = 'customer';
$headerLogo = 'black';
$basePath   = '../assets/';
$slotsText = $class['slots_left'] . ' / ' . $class['slots_total'] . ' Left';

require __DIR__ . '/../includes/header.php';

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caladea:ital@0;1&display=swap">
<link rel="stylesheet" href="../assets/css/booking-detail.css">

<main class="bd-page">
    <a href="<?= e($timetableUrl) ?>" class="bd-back">
        <span class="bd-back__icon"><i class="bi bi-chevron-left"></i></span>
        <span>Timetable</span>
    </a>

    <h1 class="bd-title">Book Class Session</h1>

    <form method="post" action="booking-detail.php?<?= e(http_build_query($class['query'])) ?>" id="bd-form" novalidate
        data-price="<?= (int) $class['price'] ?>" data-max="<?= $maxAttendees ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="bd-layout">

            <!-- Kolom kiri -->
            <div class="bd-left">
                <?php if (isset($errors['form'])): ?>
                    <div class="bd-alert"><?= e($errors['form']) ?></div>
                <?php endif; ?>

                <section class="bd-section">
                    <h2 class="bd-section__title">Attendee List</h2>
                    <p class="bd-section__hint">
                        You can only add <strong><?= $maxAttendees ?></strong>
                        participant<?= $maxAttendees > 1 ? 's' : '' ?> in this class
                    </p>

                    <div id="bd-attendee-list">
                        <?php foreach ($old['attendees'] as $i => $a) {
                            bd_attendee_card($i, $a, $errors, $i > 0);
                        } ?>
                    </div>

                    <button type="button" class="bd-add" id="bd-add-attendee">+ Add Attendee</button>
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
                        <?php if ($packages): ?>
                            <label class="bd-pay__opt">
                                <input type="radio" name="payment_method" value="package"
                                    <?= $old['payment_method'] === 'package' ? 'checked' : '' ?>>
                                <span class="bd-pay__card"><i class="bi bi-inbox-fill bd-pay__icon"></i> Package Plan</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <!-- Package Plan: My Packages -->
                    <div id="bd-packages" class="bd-packages" hidden>
                        <h3 class="bd-packages__title">My Packages</h3>
                        <?php foreach ($packages as $pkg): ?>
                            <label class="bd-package">
                                <input type="radio" name="package_id" value="<?= (int) $pkg['id'] ?>"
                                    data-remaining="<?= (int) $pkg['remaining'] ?>"
                                    <?= (string) $pkg['id'] === $old['package_id'] ? 'checked' : '' ?>>
                                <span class="bd-package__card">
                                    <span class="bd-package__info">
                                        <span class="bd-package__name"><?= e($pkg['name']) ?></span>
                                        <span class="bd-package__desc"><?= e($pkg['type']) ?> (<?= (int) $pkg['total'] ?> Session)</span>
                                    </span>
                                    <span class="bd-package__badge">Available<br><?= (int) $pkg['remaining'] ?> / <?= (int) $pkg['total'] ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <div class="bd-error" id="bd-package-error"><?= e($errors['package'] ?? '') ?></div>
                    </div>

                    <!-- Drop-in: Subtotal + Total Payment -->
                    <div id="bd-total-box" class="bd-total">
                        <div class="bd-total__row">
                            <span class="bd-total__label">Subtotal</span>
                            <span class="bd-price bd-price--sm" id="bd-subtotal"><em>Rp</em><?= number_format($class['price'], 0, '.', ',') ?></span>
                        </div>
                        <div class="bd-total__row bd-total__row--grand">
                            <span class="bd-total__label">Total Payment</span>
                            <span class="bd-price bd-price--lg" id="bd-total"><em>Rp</em><?= number_format($class['price'], 0, '.', ',') ?></span>
                        </div>
                    </div>
                </section>

                <section class="bd-section">
                    <h2 class="bd-section__title">Term &amp; Condition</h2>
                    <a href="<?= e($termsUrl) ?>" class="bd-terms" target="_blank" rel="noopener">
                        Sooma Wellness Term &amp; Condition <i class="bi bi-box-arrow-up-right"></i>
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

                    <!-- Class Image -->
                    <div class="bd-summary__hero"
                        style="background-image: url('<?= e($class['image']) ?>');">
                        <div class="bd-summary__hero-content">
                            <h2><?= e($class['class_name']) ?></h2>
                            <p><?= e($class['category']) ?> Class</p>
                        </div>
                    </div>

                    <!-- Class Information -->
                    <ul class="bd-summary__list">

                        <li>
                            <i class="ph-fill ph-clock"></i>
                            <div>
                                <strong><?= e($class['date']) ?></strong><br>
                                <?= e($class['time']) ?>
                            </div>
                        </li>

                        <li>
                            <i class="ph-fill <?= strtolower($class['type']) === 'public'
                                                    ? 'ph-users-four'
                                                    : 'ph-users' ?>"></i>
                            <div>
                                <strong><?= e($class['type']) ?></strong> Class
                            </div>
                        </li>

                        <li>
                            <i class="ph-fill ph-align-bottom"></i>
                            <div>
                                Level <strong><?= e($class['level']) ?></strong>
                            </div>
                        </li>

                        <li>
                            <i class="ph-fill ph-chalkboard-teacher"></i>
                            <div>
                                Coach <strong><?= e($class['coach']) ?></strong>
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

                    <!-- Available Slots -->
                    <div class="bd-summary__slots">
                        <span class="bd-badge"><?= e($slotsText) ?></span>
                    </div>

                </div>
            </aside>


        </div>
    </form>

    <template id="bd-attendee-template">
        <?php bd_attendee_card('__INDEX__', [], [], true); ?>
    </template>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<script>
    (function() {
        'use strict';
        var form = document.getElementById('bd-form');
        if (!form) return;

        var price = parseInt(form.dataset.price, 10) || 0;
        var max = parseInt(form.dataset.max, 10) || 1;
        var list = document.getElementById('bd-attendee-list');
        var addBtn = document.getElementById('bd-add-attendee');
        var tpl = document.getElementById('bd-attendee-template');
        var subtotalEl = document.getElementById('bd-subtotal');
        var totalEl = document.getElementById('bd-total');
        var totalBox = document.getElementById('bd-total-box');
        var packagesBox = document.getElementById('bd-packages');
        var packageError = document.getElementById('bd-package-error');
        var agree = document.getElementById('bd-agree');
        var continueBtn = document.getElementById('bd-continue');
        var nextIndex = list.querySelectorAll('[data-attendee]').length;

        function updateAttendeeTitles() {
            list.querySelectorAll('[data-attendee]').forEach(function(card, index) {
                var title = card.querySelector('.js-attendee-title');

                if (title) {
                    title.textContent = 'Attendee ' + (index + 1);
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

        function pkgRemaining() {
            var r = form.querySelector('input[name="package_id"]:checked');
            return r ? parseInt(r.dataset.remaining, 10) : 0;
        }

        function refresh() {
            var n = count(),
                isPackage = method() === 'package';
            var amount = '<em>Rp</em>' + (price * n).toLocaleString('en-US');
            subtotalEl.innerHTML = amount;
            totalEl.innerHTML = amount;
            totalBox.hidden = isPackage;
            packagesBox.hidden = !isPackage;
            if (isPackage) {
                packageError.textContent = pkgRemaining() < n ? 'Your package does not have enough remaining sessions.' : '';
            }
            addBtn.disabled = n >= max;
            addBtn.title = n >= max ? 'Maximum participants reached for this class' : '';
            continueBtn.disabled = !agree.checked;
        }

        function setOpen(card, open) {
            var btn = card.querySelector('[data-toggle-attendee]');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
        }

        addBtn.addEventListener('click', function() {
            if (count() >= max) return;

            list.querySelectorAll('[data-attendee]').forEach(function(c) {
                setOpen(c, false);
            });

            list.insertAdjacentHTML(
                'beforeend',
                tpl.innerHTML.split('__INDEX__').join(String(nextIndex++))
            );

            updateAttendeeTitles();
            refresh();
        });

        list.addEventListener('click', function(e) {
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
            }
        });

        list.addEventListener('input', function(e) {
            var input = e.target;

            if (input.type === 'tel') {
                input.value = input.value.replace(/[^\d+]/g, '');
            }
        });

        form.addEventListener('change', function(e) {
            if (e.target.matches('input[name="payment_method"], input[name="package_id"], #bd-agree')) refresh();
        });

        form.addEventListener('submit', function(e) {
            if (method() === 'package' && pkgRemaining() < count()) {
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
            }
        });

        updateAttendeeTitles();

        refresh();
    })();
</script>