<?php
$page = $_GET['page'] ?? 'login';
if (!in_array($page, ['login', 'register'], true)) {
    $page = 'login';
}
$isRegister = $page === 'register';
$pageTitle = $isRegister ? 'Create an Account' : 'Welcome Back';

require __DIR__ . '/includes/header.php';
?>
<main class="auth-layout">
    <section class="visual-panel" aria-label="Sooma Wellness introduction">
        <a class="brand" href="index.php?page=login" aria-label="Sooma Wellness home">
            <span class="brand-name"></span>
            <span class="brand-subtitle"></span>
        </a>

        <div class="visual-copy">
            <?php if ($isRegister): ?>
                <h2>BEGIN YOUR<br>WELLNESS JOURNEY.</h2>
                <p>Join Sooma Wellness to easily book Pilates and yoga classes, enjoy exclusive promotions, and track your workout plans—all in one place.</p>
            <?php else: ?>
                <h2>YOUR MINDFUL<br>JOURNEY CONTINUES.</h2>
                <p>Log in to manage your class schedule, check your remaining sessions, and continue your journey toward a more balanced body.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="form-panel">
        <div class="form-content <?= $isRegister ? 'register-content' : 'login-content' ?>">
            <?php if ($isRegister): ?>
                <h1>Create an Account</h1>
                <p class="subtitle">Create your account to start a more integrated<br class="desktop-break"> training experience.</p>

                <form action="#" method="post">
                    <div class="field">
                        <label for="full-name">Full Name</label>
                        <input id="full-name" name="full_name" type="text" placeholder="Enter your full name" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="register-email">Email</label>
                        <input id="register-email" name="email" type="email" placeholder="Enter your email" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone Number</label>
                        <input id="phone" name="phone" type="tel" placeholder="Enter your phone number" autocomplete="tel" required>
                    </div>
                    <div class="field">
                        <label for="register-password">Password</label>
                        <input id="register-password" name="password" type="password" placeholder="Min. 8 Character" minlength="8" autocomplete="new-password" required>
                    </div>

                    <label class="check-row terms-row">
                        <input type="checkbox" name="terms" required>
                        <span>I agree to Sooma Wellness's Terms &amp; Conditions and Privacy Policy.</span>
                    </label>

                    <button class="primary-button" type="submit">Sign up</button>
                </form>
                <div class="separator"></div>
                <button class="google-button" type="button"><span class="google-mark">G</span> Or sign up with Google</button>
                <p class="switch-copy">Already have an account? <a href="index.php?page=login">Sign in now</a></p>
            <?php else: ?>
                <h1>Welcome Back !</h1>
                <p class="subtitle">Welcome back! Please enter your details.</p>

                <form action="#" method="post">
                    <div class="field">
                        <label for="login-email">Email</label>
                        <input id="login-email" name="email" type="email" placeholder="Enter your email" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="login-password">Password</label>
                        <input id="login-password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
                    </div>

                    <div class="options-row">
                        <label class="check-row">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button class="primary-button" type="submit">Sign in</button>
                </form>
                <div class="separator"></div>
                <button class="google-button" type="button"><span class="google-mark">G</span> Or sign in with Google</button>
                <p class="switch-copy">Dont have an account? <a href="index.php?page=register">Sign up now</a></p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>