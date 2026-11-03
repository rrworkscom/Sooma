<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?page=login');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../index.php?page=login&error=unauthorized');
    exit;
}

$pageTitle = 'Sooma Wellness';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?> | Thoughtful Movement for Body &amp; Soul</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600;1,700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="../assets/css/footer.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/homepage.css">
</head>

<body>
            <?php require_once '../includes/header.php'; ?>
    <main>

        <section class="hero" id="home">
            <div class="hero-overlay"></div>

            <div class="hero-content">
                <p class="eyebrow hero-eyebrow">Move with intention</p>

                <h1>
                    Redefine Your Movement.<br>
                    <em>Align Your Mind.</em>
                </h1>

                <p class="hero-copy">
                    Find balance between body and mind through Pilates sessions
                    specifically designed for your commitment and strength.
                </p>

                <div class="hero-actions">
                    <a class="button button-sage" href="#contact">Book Your Class</a>
                    <a class="button button-outline" href="#classes">Explore Classes</a>
                </div>
            </div>
        </section>

        <section class="intro section-wrap" id="about">
            <div class="intro-copy">
                <p class="eyebrow">Welcome to Sooma Wellness</p>

                <h2>
                    Thoughtful<br>
                    Movement for <em>Body<br>&amp; Soul</em>
                </h2>

                <p>At Sooma Wellness, movement is a journey back to yourself.</p>

                <p>
                    Discover a workout space where you can improve your core stability,
                    muscle flexibility, and balance—with experienced instructors
                    guiding you every step of the way.
                </p>
            </div>

            <div class="mind-card">
                <img src="../assets/images/about-mind-body.png" alt="Woman practicing mindful movement">
                <div class="mind-shade"></div>
                <span class="mind-logo">Sooma</span>
                <span class="mind-word word-body">Body</span>
                <span class="mind-word word-mind">Mind</span>
                <span class="mind-word word-soul">Soul</span>
                <div class="mind-circle circle-one"></div>
                <div class="mind-circle circle-two"></div>
            </div>
        </section>

        <section class="coaches section-wrap">
            <p class="eyebrow">Meet the team</p>
            <h2 class="section-heading">Guided by Our Expert Instructors</h2>

            <div class="coach-list">
                <article class="coach">
                    <img src="../assets/images/coach-alifa.jpg" alt="Coach Alifa">
                    <span>Coach Alifa</span>
                </article>

                <article class="coach">
                    <img src="../assets/images/coach-bintari.jpg" alt="Coach Bintari">
                    <span>Coach Bintari</span>
                </article>

                <article class="coach">
                    <img src="../assets/images/coach-bunga.jpg" alt="Coach Bunga">
                    <span>Coach Bunga</span>
                </article>

                <article class="coach">
                    <img src="../assets/images/coach-grace.jpg" alt="Coach Grace">
                    <span>Coach Grace</span>
                </article>

                <article class="coach">
                    <img src="../assets/images/coach-ulung.jpg" alt="Coach Ulung">
                    <span>Coach Ulung</span>
                </article>
            </div>
        </section>

        <section class="offers section-wrap" id="offers">
            <p class="eyebrow">Exclusive offers</p>

            <h2 class="section-heading">
                Limited Deals for Your<br>
                Mindful Journey
            </h2>

            <div class="offer-list">

                <article class="offer-card">
                    <img src="../assets/images/promo-summer.jpg" alt="Summer Day Out offer artwork">

                    <div class="offer-details">
                        <h3>Summer Day Out</h3>
                        <p class="offer-date">June - August 2026</p>

                        <p>
                            Make your Pilates sessions even more memorable with your
                            closest friends. With the Summer Day Out package, you
                            and three friends can enjoy a full session led by a
                            professional instructor in a comfortable, private studio.
                        </p>

                        <strong class="price">
                            <img src="../assets/images/pricetag.svg" alt="">
                            Only 400.000 / 4 Person
                        </strong>

                        <a href="#contact" class="button button-sage">Claim Offer Now</a>
                    </div>
                </article>

                <article class="offer-card">
                    <img src="../assets/images/promo-gen-z.jpg" alt="Calling All Gen Z offer artwork">

                    <div class="offer-details">
                        <h3>Calling All Gen Z</h3>
                        <p class="offer-date">December 2026</p>

                        <p>
                            Start living a healthy lifestyle and achieve your ideal
                            physique today. Especially for you, Gen Z, enjoy super
                            affordable drop-in session rates to experience Sooma
                            Wellness at your studio.
                        </p>

                        <strong class="price">
                            <img src="../assets/images/pricetag.svg" alt="">
                            100.000 / Session
                        </strong>

                        <a href="#contact" class="button button-sage">Claim Offer Now</a>
                    </div>
                </article>

                <article class="offer-card">
                    <img src="../assets/images/promo-happy-hour.jpg" alt="Happy Hour Promo offer artwork">

                    <div class="offer-details">
                        <h3>Happy Hour Promo</h3>

                        <p class="offer-date">
                            Sesi Single: 11.00 - 14.00<br>
                            Sesi Sore: 15.00 - 17.00
                        </p>

                        <p>
                            Take a moment to relax amid your busy daily routine.
                            With our Happy Hour Promo, you can enjoy a Pilates
                            class at a much lower price during selected hours every day.
                        </p>

                        <strong class="price">
                            <img src="../assets/images/pricetag.svg" alt="">
                            135.000 / Session
                        </strong>

                        <a href="#contact" class="button button-sage">Claim Offer Now</a>
                    </div>
                </article>

            </div>
        </section>

        <section class="packages section-wrap" id="classes">
            <p class="eyebrow">Our packages</p>
            <h2 class="section-heading">
                Choose the Practice<br>
                That Fits You
            </h2>

            <div class="package-grid">

                <article class="package-card">
                    <img src="../assets/images/package-reformer.png" alt="Reformer Pilates">

                    <div class="package-body">
                        <h3>Reformer Pilates</h3>

                        <p>
                            A dynamic workout session using the Reformer machine
                            to strengthen the core, improve posture, and increase
                            flexibility and balance in a supportive environment.
                            Suitable for all levels.
                        </p>

                        <div class="tags">
                            <span>Core Strength</span>
                            <span>Posture Correction</span>
                            <span>Flexibility</span>
                        </div>
                    </div>
                </article>

                <article class="package-card">
                    <img src="../assets/images/package-yoga.png" alt="Regular Yoga">

                    <div class="package-body">
                        <h3>Regular Yoga</h3>

                        <p>
                            A harmonious combination of breathing and mindful
                            movement, designed to increase mobility, flexibility,
                            and relaxation. Suitable for all levels and experience.
                        </p>

                        <div class="tags">
                            <span>Stress Relief</span>
                            <span>Joint Mobility</span>
                            <span>Inner Peace</span>
                        </div>
                    </div>
                </article>

            </div>
        </section>

        <section class="studio-gallery" id="timetable" aria-label="Sooma studio gallery">
            <button class="gallery-arrow gallery-prev" aria-label="Previous image">‹</button>

            <img class="gallery-side gallery-left" src="../assets/images/gallery-left.png" alt="Pilates equipment">
            <img class="gallery-main" src="../assets/images/gallery-main.png" alt="Sooma Pilates studio">
            <img class="gallery-side gallery-right" src="../assets/images/gallery-right.png" alt="Yoga studio">

            <button class="gallery-arrow gallery-next" aria-label="Next image">›</button>
        </section>

        <section class="contact section-wrap" id="contact">
            <div class="contact-info">
                <span class="contact-label">Contact</span>
                <h2>Get In Touch</h2>

                <p>
                    Do you have questions about classes, reservations, or need
                    recommendations for the right package? Our team is here to help you.
                </p>

                <div class="contact-item">
                    <span class="contact-icon">◷</span>
                    <div>
                        <strong>Hours</strong>
                        <p>24/7 for emails and Live chat</p>
                    </div>
                </div>

                <div class="contact-item">
                    <span class="contact-icon">
                        <img src="../assets/images/loc.svg" alt="">
                    </span>
                    <div>
                        <strong>Location</strong>
                        <p>Jl. Ciliwung No.62, Blitar</p>
                    </div>
                </div>

                <div class="contact-item">
                    <span class="contact-icon">
                        <img src="../assets/images/mail.svg" alt="">
                    </span>
                    <div>
                        <strong>Email</strong>
                        <p>soomawellness@gmail.com</p>
                    </div>
                </div>
            </div>

            <form class="contact-form" action="#" method="post">
                <label>
                    Full Name
                    <input type="text" name="full_name" placeholder="Enter your name" required>
                </label>

                <label>
                    Email
                    <input type="email" name="email" placeholder="Enter your email" required>
                </label>

                <label>
                    Phone Number
                    <input type="tel" name="phone" placeholder="Enter your phone number">
                </label>

                <label>
                    Message
                    <textarea name="message" placeholder="Enter your message" rows="4" required></textarea>
                </label>

                <button class="button button-sage submit-button" type="submit">
                    Submit <span>➤</span>
                </button>
            </form>
        </section>

    </main>

     <?php require_once '../includes/footer.php'; ?>

    <script src="../assets/js/homepage.js"></script>

</body>
</html>