<?php 
$hideChrome = $hideChrome ?? false; 
?>

<?php if (!$hideChrome): ?>
    <footer class="site-footer">
        <div class="footer-main">

            <div class="footer-brand">
                <a class="brand footer-logo" href="index.php">
                    <img src="../assets/images/sooma-logo-white.png" alt="Sooma Wellness">
                </a>

                <p>
                    <strong>Location</strong><br>
                    Jl. Ciliwung No.62, Blitar
                </p>

                <p>
                    <strong>Operating Hours</strong><br>
                    Everyday | 07.00 - 19.00<br>
                </p>
            </div>

            <div class="footer-links">
                <h3>Quick Link</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="class.php">Class</a>
                <a href="packages.php">Package</a>
                <a href="timetable.php">Timetable</a>
            </div>

            <div class="footer-cta">
                <h3>Ready To Start?</h3>
                <p>Join our community and transform your body and mind into a healthy self.</p>
                <a class="button button-light" href="packages.php">Book Class Now</a>
            </div>

        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Sooma Wellness. All rights reserved.</span>

            <div>
                <a href="#privacy">Privacy Policy</a>
                <a href="#terms">Terms of Service</a>
                <a href="#cookies">Cookie Settings</a>
            </div>
        </div>
    </footer>
<?php endif; ?>

</body>
</html>