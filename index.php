<?php
require_once 'includes/auth.php';

if (function_exists('isLoggedIn') && isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover skilled trainers, learn new technologies, and grow through meaningful mentorship with SkillConnect.">
    <title>SkillConnect | Learn. Connect. Grow.</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing-page">

<header class="lp-header">
    <div class="lp-container lp-nav">
        <a class="lp-brand" href="index.php" aria-label="SkillConnect home">
            <span class="lp-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 64 64" focusable="false">
                    <g fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 47 L29 35 L44 13"/>
                        <path d="M29 35 L13 22"/>
                        <path d="M29 35 L47 42"/>
                    </g>
                    <circle cx="14" cy="47" r="10" fill="currentColor"/>
                    <circle cx="13" cy="22" r="10" fill="currentColor"/>
                    <circle cx="44" cy="13" r="10" fill="currentColor"/>
                    <circle cx="47" cy="42" r="10" fill="currentColor"/>
                    <circle cx="29" cy="35" r="8" fill="currentColor"/>
                </svg>
            </span>
            <span class="lp-brand-word"><span>Skill</span><b>Connect</b></span>
        </a>

        <nav class="lp-nav-links" aria-label="Main navigation">
            <a class="is-active" href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#about">About</a>
        </nav>

        <div class="lp-nav-actions">
            <a class="lp-button lp-button-outline lp-button-small" href="login.php">Sign In</a>
            <a class="lp-button lp-button-primary lp-button-small" href="login.php?mode=signup">Get Started</a>
        </div>
        <details class="lp-mobile-menu">
            <summary aria-label="Open navigation menu"><span></span><span></span><span></span></summary>
            <nav aria-label="Mobile navigation">
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#about">About</a>
                <a href="login.php">Sign In</a>
                <a href="login.php?mode=signup">Get Started</a>
            </nav>
        </details>
    </div>
</header>

<main>
    <section class="lp-hero" id="home">
        <div class="lp-container lp-hero-grid">
            <div class="lp-hero-copy">
                <div class="lp-eyebrow"><span>Learn</span><b>•</b><span>Connect</span><b>•</b><span>Grow</span></div>
                <h1>Find the Right Trainers. Build a <span>Better You.</span></h1>
                <p class="lp-hero-description">
                    SkillConnect helps you discover skilled trainers, learn new technologies and grow through mentorship.
                    A simple, focused platform for learners and trainers.
                </p>
                <div class="lp-hero-actions">
                    <a class="lp-button lp-button-primary" href="login.php?mode=signup">Get Started <span aria-hidden="true">→</span></a>
                    <a class="lp-button lp-button-outline" href="login.php">Sign In</a>
                </div>
                <div class="lp-benefits">
                    <div class="lp-benefit"><span class="lp-benefit-icon" aria-hidden="true">♧</span><span>Learn from<br>experienced trainers</span></div>
                    <div class="lp-benefit"><span class="lp-benefit-icon" aria-hidden="true">▤</span><span>Improve your skills<br>with mentorship</span></div>
                    <div class="lp-benefit"><span class="lp-benefit-icon" aria-hidden="true">▥</span><span>Grow in your<br>learning journey</span></div>
                </div>
            </div>

            <div class="lp-hero-visual">
                <div class="lp-visual-glow"></div>
                <img src="assets/images/skillconnect-hero.jpeg" alt="A learner coding on a laptop with trainer profiles and technology skills around them" class="lp-hero-image">
                <div class="lp-floating-tag lp-tag-java">Java</div>
                <div class="lp-floating-tag lp-tag-dsa">Data Structures</div>
                <div class="lp-floating-tag lp-tag-web">Web Development</div>
                <div class="lp-floating-tag lp-tag-ml">Machine Learning</div>
            </div>
        </div>
    </section>

    <section class="lp-section lp-features" id="features">
        <div class="lp-container">
            <div class="lp-section-heading">
                <span class="lp-section-label">Features</span>
                <h2>Everything you need to grow your skills</h2>
                <p>Simple tools to help you find the right guidance and stay on track.</p>
            </div>
            <div class="lp-feature-grid">
                <article class="lp-feature-card">
                    <div class="lp-feature-icon lp-icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg></div>
                    <h3>Find Skilled Trainers</h3>
                    <p>Discover trainers based on the skills and technologies you want to learn.</p>
                </article>
                <article class="lp-feature-card">
                    <div class="lp-feature-icon lp-icon-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5c3.2-1.2 5.9-.8 8 1.2v13c-2.1-2-4.8-2.4-8-1.2z"/><path d="M20 5.5c-3.2-1.2-5.9-.8-8 1.2v13c2.1-2 4.8-2.4 8-1.2z"/></svg></div>
                    <h3>Build Your Skill Profile</h3>
                    <p>Showcase your skills, interests and learning goals to connect with the right people.</p>
                </article>
                <article class="lp-feature-card">
                    <div class="lp-feature-icon lp-icon-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 5.5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-7l-4.5 3v-3H5a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z"/><path d="M7.5 10h.1m4.4 0h.1m4.4 0h.1" stroke-linecap="round" stroke-width="2.8"/></svg></div>
                    <h3>Request Mentorship</h3>
                    <p>Send mentorship requests and start learning through a clear, structured process.</p>
                </article>
                <article class="lp-feature-card">
                    <div class="lp-feature-icon lp-icon-rose" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V12h4v8zM10 20V7h4v13zM16 20V3h4v17z"/></svg></div>
                    <h3>Learn and Grow</h3>
                    <p>Get guidance, strengthen your skills and keep track of your learning journey.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section lp-how" id="how-it-works">
        <div class="lp-container">
            <div class="lp-section-heading">
                <span class="lp-section-label">How It Works</span>
                <h2>Get started in just a few steps</h2>
                <p>Join SkillConnect and begin your learning journey today.</p>
            </div>
            <div class="lp-steps lp-steps-image">
                <img
                    src="assets/images/skillconnect-how-it-works.jpeg"
                    alt="How SkillConnect works: create your account, find the right trainer, and request mentorship."
                    class="lp-how-image"
                    loading="lazy"
                >
            </div>
        </div>
    </section>

    <section class="lp-cta-section lp-about-image-section" id="about">
        <div class="lp-container">
            <div class="lp-about-banner">
                <img
                    src="assets/images/skillconnect-about.jpeg"
                    alt="Learn, share, and grow together with SkillConnect."
                    class="lp-about-banner-image"
                    loading="lazy"
                >
                <div class="lp-about-banner-actions" aria-label="Get started with SkillConnect">
                    <a class="lp-button lp-button-primary" href="login.php?mode=signup">
                        Get Started <span aria-hidden="true">→</span>
                    </a>
                    <a class="lp-button lp-button-banner-outline" href="login.php">Sign In</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-footer-main">
            <div class="lp-footer-brand">
                <a class="lp-brand" href="index.php">
                    <span class="lp-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 64 64" focusable="false">
                    <g fill="none" stroke="currentColor" stroke-width="11" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 47 L29 35 L44 13"/>
                        <path d="M29 35 L13 22"/>
                        <path d="M29 35 L47 42"/>
                    </g>
                    <circle cx="14" cy="47" r="10" fill="currentColor"/>
                    <circle cx="13" cy="22" r="10" fill="currentColor"/>
                    <circle cx="44" cy="13" r="10" fill="currentColor"/>
                    <circle cx="47" cy="42" r="10" fill="currentColor"/>
                    <circle cx="29" cy="35" r="8" fill="currentColor"/>
                </svg>
            </span>
            <span class="lp-brand-word"><span>Skill</span><b>Connect</b></span>
                </a>
                <p>A platform to learn, connect and grow together.</p>
            </div>
            <div class="lp-footer-column">
                <h3>Quick Links</h3>
                <a href="#home">Home</a><a href="#features">Features</a><a href="#how-it-works">How It Works</a><a href="#about">About</a>
            </div>
            <div class="lp-footer-column">
                <h3>Support</h3>
                <a href="login.php">Help</a><a href="login.php">Contact</a><a href="#about">Platform Overview</a><a href="#how-it-works">Getting Started</a>
            </div>
            <div class="lp-footer-column lp-social-column">
                <h3>Follow Us</h3>
                <div class="lp-social-links">
                    <a href="https://github.com/" aria-label="GitHub">GH</a>
                    <a href="https://www.linkedin.com/" aria-label="LinkedIn">in</a>
                    <a href="https://www.instagram.com/" aria-label="Instagram">◎</a>
                </div>
            </div>
        </div>
        <div class="lp-footer-bottom">
            <span>© <?= date('Y') ?> SkillConnect. All rights reserved.</span>
            <span>Built for learners, by learners <b aria-hidden="true">♥</b></span>
        </div>
    </div>
</footer>

<script>
document.querySelectorAll('.landing-page a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        var targetId = this.getAttribute('href');
        if (!targetId || targetId === '#') return;
        var target = document.querySelector(targetId);
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (history.replaceState) history.replaceState(null, '', targetId);
        var mobileMenu = document.querySelector('.lp-mobile-menu');
        if (mobileMenu) mobileMenu.removeAttribute('open');
    });
});
</script>
</body>
</html>
