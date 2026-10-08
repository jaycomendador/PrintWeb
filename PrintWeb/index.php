<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$authModal = in_array($_GET['auth'] ?? '', ['login', 'register'], true) ? $_GET['auth'] : '';
$authFlash = $_SESSION['auth_modal_flash'] ?? [];
unset($_SESSION['auth_modal_flash']);
$isLoggedIn = isLoggedIn();
$dashboardUrl = in_array($_SESSION['user_role'] ?? '', ADMIN_ROLES, true)
    ? BASE_URL . '/admin/dashboard.php'
    : BASE_URL . '/user/dashboard.php';
$bwPrice = getPricePerPage('bw', 'A4');
$colorPrice = getPricePerPage('color', 'A4');
$printerStats = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'available'), 0) AS available,
        COALESCE(SUM(status = 'printing'), 0) AS printing
    FROM printers
    WHERE enabled = 1
")->fetch();
$queueStats = $pdo->query("
    SELECT
        COALESCE(SUM(print_status IN ('queued', 'waiting')), 0) AS waiting,
        COALESCE(SUM(print_status = 'printing'), 0) AS printing
    FROM print_jobs
")->fetch();
$printerStatus = (int)$printerStats['total'] === 0
    ? ['label' => 'Not configured', 'class' => 'unconfigured']
    : ((int)$printerStats['available'] > 0
        ? ['label' => 'Available', 'class' => 'available']
        : ((int)$printerStats['printing'] > 0
            ? ['label' => 'Printing', 'class' => 'printing']
            : ['label' => 'Unavailable', 'class' => 'unavailable']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PrintWeb | Smart Printing, Made Simple</title>
    <meta name="description" content="Upload a document, choose your print settings, pay, and track your job with PrintWeb.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/landing.css">
</head>
<body class="landing-page">
    <nav class="navbar navbar-expand-lg navbar-dark landing-nav">
        <div class="container">
            <a class="navbar-brand landing-brand" href="<?= BASE_URL ?>/">
                <span class="landing-brand-icon"><i class="bi bi-printer-fill"></i></span>
                <span>Print<span class="landing-brand-accent">Web</span></span>
            </a>
            <button class="navbar-toggler landing-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item ms-lg-1"><a class="btn landing-btn-primary" href="<?= h($dashboardUrl) ?>">Go to dashboard <i class="bi bi-arrow-right"></i></a></li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-1"><a class="btn landing-btn-login" href="#loginModal" data-bs-toggle="modal"><i class="bi bi-box-arrow-in-right"></i> Log in</a></li>
                        <li class="nav-item"><a class="btn landing-btn-primary" href="#registerModal" data-bs-toggle="modal">Create account</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main>
        <section class="landing-hero">
            <div class="landing-hero-glow landing-hero-glow-one"></div>
            <div class="landing-hero-glow landing-hero-glow-two"></div>
            <div class="container landing-hero-content">
                <div class="row align-items-center g-5">
                    <div class="col-lg-7">
                        <div class="landing-eyebrow"><span></span> Smart printing payment system</div>
                        <h1>Print it.<br><span>Pay for it.</span><br>Track every step.</h1>
                        <p class="landing-hero-copy">Send your document from anywhere. Choose your print settings, see the price upfront, and follow your job from payment to pickup.</p>
                        <div class="d-flex flex-wrap gap-3 landing-hero-actions">
                            <?php if ($isLoggedIn): ?>
                                <a class="btn landing-btn-primary landing-btn-large" href="<?= h($dashboardUrl) ?>">Open dashboard <i class="bi bi-arrow-right"></i></a>
                            <?php else: ?>
                                <a class="btn landing-btn-primary landing-btn-large" href="#loginModal" data-bs-toggle="modal">Log in to print <i class="bi bi-arrow-right"></i></a>
                                <a class="btn landing-btn-outline landing-btn-large" href="#registerModal" data-bs-toggle="modal">Create an account</a>
                            <?php endif; ?>
                        </div>
                        <div class="landing-trust-row">
                            <span><i class="bi bi-check-circle-fill"></i> Upfront pricing</span>
                            <span><i class="bi bi-check-circle-fill"></i> Live job status</span>
                            <span><i class="bi bi-check-circle-fill"></i> Simple pickup</span>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="landing-preview">
                            <div class="landing-preview-top">
                                <div>
                                    <span class="landing-preview-label">SYSTEM STATUS</span>
                                    <h2>Current print capacity</h2>
                                </div>
                                <span class="landing-printer-status <?= h($printerStatus['class']) ?>"><i></i> <?= h($printerStatus['label']) ?></span>
                            </div>
                            <div class="landing-status-list">
                                <div class="landing-status-row">
                                    <span><i class="bi bi-printer"></i> Printers available</span>
                                    <strong><?= number_format((int)$printerStats['available']) ?> / <?= number_format((int)$printerStats['total']) ?></strong>
                                </div>
                                <div class="landing-status-row">
                                    <span><i class="bi bi-hourglass-split"></i> Jobs waiting</span>
                                    <strong><?= number_format((int)$queueStats['waiting']) ?></strong>
                                </div>
                                <div class="landing-status-row">
                                    <span><i class="bi bi-printer-fill"></i> Jobs printing</span>
                                    <strong><?= number_format((int)$queueStats['printing']) ?></strong>
                                </div>
                            </div>
                            <div class="landing-preview-foot"><i class="bi bi-database-check"></i> Counts are read from the current system records.</div>
                        </div>
                    </div>
                </div>
                <div class="landing-scroll-cue"><span></span> Scroll to explore</div>
            </div>
        </section>

        <section id="how-it-works" class="landing-section landing-how">
            <div class="container">
                <div class="landing-section-heading">
                    <span class="landing-section-kicker">A better way to print</span>
                    <h2>From document to done in <span>four simple steps.</span></h2>
                    <p>No guessing, no wondering where your job is. PrintWeb keeps the whole process clear.</p>
                </div>
                <div class="row g-4">
                    <div class="col-6 col-lg-3">
                        <article class="landing-step-card">
                            <span class="landing-step-number">01</span>
                            <div class="landing-step-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                            <h3>Upload</h3>
                            <p>Choose your document and check the file details.</p>
                        </article>
                    </div>
                    <div class="col-6 col-lg-3">
                        <article class="landing-step-card">
                            <span class="landing-step-number">02</span>
                            <div class="landing-step-icon"><i class="bi bi-sliders"></i></div>
                            <h3>Set it up</h3>
                            <p>Choose pages, copies, color, and paper size.</p>
                        </article>
                    </div>
                    <div class="col-6 col-lg-3">
                        <article class="landing-step-card">
                            <span class="landing-step-number">03</span>
                            <div class="landing-step-icon"><i class="bi bi-wallet2"></i></div>
                            <h3>Pay</h3>
                            <p>Review the calculated cost and pay securely through Stripe Checkout.</p>
                        </article>
                    </div>
                    <div class="col-6 col-lg-3">
                        <article class="landing-step-card">
                            <span class="landing-step-number">04</span>
                            <div class="landing-step-icon"><i class="bi bi-printer"></i></div>
                            <h3>Track &amp; collect</h3>
                            <p>Watch your queue status and collect when printing is complete.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="landing-section landing-features">
            <div class="container">
                <div class="row align-items-end g-4 landing-feature-heading">
                    <div class="col-lg-8">
                        <span class="landing-section-kicker">Everything in one place</span>
                        <h2>Printing that keeps you <span>in control.</span></h2>
                    </div>
                    <div class="col-lg-4"><p>Useful tools for the whole print journey, from your first upload to your print history.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon purple"><i class="bi bi-calculator"></i></div><h3>Know the price first</h3><p>See your print cost calculated from selected pages, copies, color, and paper size.</p></article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon blue"><i class="bi bi-diagram-3"></i></div><h3>Follow your print job</h3><p>See payment, queue, printing, and completion status from your dashboard.</p></article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon green"><i class="bi bi-clock-history"></i></div><h3>Keep a clear history</h3><p>Review past documents, print settings, charges, and job statuses at any time.</p></article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon orange"><i class="bi bi-printer-fill"></i></div><h3>Check printer availability</h3><p>View whether your printer is available, printing, offline, or needs attention.</p></article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon pink"><i class="bi bi-bell"></i></div><h3>Get useful updates</h3><p>Receive notifications when payment succeeds or your print status changes.</p></article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="landing-feature-card"><div class="landing-feature-icon teal"><i class="bi bi-person-check"></i></div><h3>Your own account</h3><p>Manage your profile and access your print activity securely after signing in.</p></article>
                    </div>
                </div>
            </div>
        </section>

        <section id="pricing" class="landing-price-section">
            <div class="container">
                <div class="landing-price-panel">
                    <div>
                        <span class="landing-section-kicker">Clear, upfront pricing</span>
                        <h2>Only pay for what<br>you need to print.</h2>
                        <p>Choose your print options and get the total before you confirm. The final price depends on paper size, page selection, copies, and color.</p>
                        <a class="btn landing-btn-light" href="<?= $isLoggedIn ? h($dashboardUrl) : '#loginModal' ?>" <?= $isLoggedIn ? '' : 'data-bs-toggle="modal"' ?>>
                            <?= $isLoggedIn ? 'Go to dashboard' : 'Log in to get started' ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="landing-price-cards">
                        <div class="landing-price-card"><span><i class="bi bi-circle-half"></i> Black &amp; White</span><strong><?= formatCurrency($bwPrice) ?><small> / page</small></strong><em>Rate per printed page</em></div>
                        <div class="landing-price-card"><span><i class="bi bi-palette"></i> Color</span><strong><?= formatCurrency($colorPrice) ?><small> / page</small></strong><em>Calculated before you pay</em></div>
                        <p class="landing-price-note"><i class="bi bi-info-circle"></i> Example A4 rates; other paper sizes and settings may change the total.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-final-cta">
            <div class="container">
                <span class="landing-section-kicker">Ready when you are</span>
                <h2>Make your next print job<br><span>the easy one.</span></h2>
                <p>Create an account or log in to upload a document and get started.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <?php if ($isLoggedIn): ?>
                        <a class="btn landing-btn-primary landing-btn-large" href="<?= h($dashboardUrl) ?>">Go to your dashboard <i class="bi bi-arrow-right"></i></a>
                    <?php else: ?>
                        <a class="btn landing-btn-primary landing-btn-large" href="#registerModal" data-bs-toggle="modal">Create your account <i class="bi bi-arrow-right"></i></a>
                        <a class="btn landing-btn-outline-dark landing-btn-large" href="#loginModal" data-bs-toggle="modal">Log in</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <a class="landing-brand" href="<?= BASE_URL ?>/"><span class="landing-brand-icon"><i class="bi bi-printer-fill"></i></span><span>Print<span class="landing-brand-accent">Web</span></span></a>
            <p class="mb-0">Smart printing, made simple.</p>
            <span>© <span id="currentYear"></span> PrintWeb</span>
        </div>
    </footer>

    <?php if (!$isLoggedIn): ?>
    <div class="modal fade landing-auth-modal" id="loginModal" tabindex="-1" aria-labelledby="loginModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="landing-auth-kicker">Welcome back</span>
                        <h2 class="modal-title" id="loginModalTitle">Log in to PrintWeb</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($authFlash['type']) && $authFlash['type'] === 'login' && !empty($authFlash['errors'])): ?>
                        <div class="alert alert-danger" role="alert"><?= implode('<br>', array_map('h', $authFlash['errors'])) ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['registered'])): ?>
                        <div class="alert alert-success" role="status">Account created successfully. Please log in.</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['timeout'])): ?>
                        <div class="alert alert-warning" role="status">Your session expired. Please log in again.</div>
                    <?php endif; ?>
                    <form method="POST" action="<?= BASE_URL ?>/auth/login.php">
                        <input type="hidden" name="modal_return" value="1">
                        <div class="mb-3">
                            <label class="form-label" for="modal-login-email">Email address</label>
                            <input class="form-control" type="email" id="modal-login-email" name="email" value="<?= h(($authFlash['type'] ?? '') === 'login' ? ($authFlash['email'] ?? '') : '') ?>" autocomplete="email" placeholder="you@example.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="modal-login-password">Password</label>
                            <div class="landing-password-field">
                                <input class="form-control" type="password" id="modal-login-password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                                <button class="landing-password-toggle" type="button" data-password-toggle="modal-login-password" aria-label="Show password" aria-pressed="false">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <label class="form-check-label" for="modal-login-remember"><input class="form-check-input me-1" type="checkbox" id="modal-login-remember" name="remember"> Remember me</label>
                            <a href="<?= BASE_URL ?>/auth/forgot_password.php">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn landing-btn-primary w-100">Log in</button>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <span>New to PrintWeb?</span>
                    <button type="button" class="btn btn-link p-0" data-bs-target="#registerModal" data-bs-toggle="modal">Create an account</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade landing-auth-modal" id="registerModal" tabindex="-1" aria-labelledby="registerModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="landing-auth-kicker">Get started</span>
                        <h2 class="modal-title" id="registerModalTitle">Create your account</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($authFlash['type']) && $authFlash['type'] === 'register' && !empty($authFlash['errors'])): ?>
                        <div class="alert alert-danger" role="alert"><?= implode('<br>', array_map('h', $authFlash['errors'])) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="<?= BASE_URL ?>/auth/register.php">
                        <input type="hidden" name="modal_return" value="1">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="modal-register-name">Full name</label>
                                <input class="form-control" type="text" id="modal-register-name" name="full_name" value="<?= h(($authFlash['type'] ?? '') === 'register' ? ($authFlash['formData']['full_name'] ?? '') : '') ?>" autocomplete="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-email">Email address</label>
                                <input class="form-control" type="email" id="modal-register-email" name="email" value="<?= h(($authFlash['type'] ?? '') === 'register' ? ($authFlash['formData']['email'] ?? '') : '') ?>" autocomplete="email" placeholder="you@example.com" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-role">User type</label>
                                <select class="form-select" id="modal-register-role" name="role" required>
                                    <?php foreach (ROLES as $roleKey => $roleLabel): ?>
                                        <?php if (!in_array($roleKey, ADMIN_ROLES, true)): ?>
                                            <option value="<?= h($roleKey) ?>" <?= (($authFlash['formData']['role'] ?? 'student') === $roleKey) ? 'selected' : '' ?>><?= h($roleLabel) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-contact">Contact number</label>
                                <input class="form-control" type="text" id="modal-register-contact" name="contact_number" value="<?= h(($authFlash['type'] ?? '') === 'register' ? ($authFlash['formData']['contact_number'] ?? '') : '') ?>" autocomplete="tel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-department">Department / section</label>
                                <input class="form-control" type="text" id="modal-register-department" name="department" value="<?= h(($authFlash['type'] ?? '') === 'register' ? ($authFlash['formData']['department'] ?? '') : '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-password">Password</label>
                                <div class="landing-password-field">
                                    <input class="form-control" type="password" id="modal-register-password" name="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" required>
                                    <button class="landing-password-toggle" type="button" data-password-toggle="modal-register-password" aria-label="Show password" aria-pressed="false">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="modal-register-confirm">Confirm password</label>
                                <div class="landing-password-field">
                                    <input class="form-control" type="password" id="modal-register-confirm" name="confirm_password" autocomplete="new-password" minlength="8" required>
                                    <button class="landing-password-toggle" type="button" data-password-toggle="modal-register-confirm" aria-label="Show password" aria-pressed="false">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn landing-btn-primary w-100 mt-4">Create account</button>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <span>Already have an account?</span>
                    <button type="button" class="btn btn-link p-0" data-bs-target="#loginModal" data-bs-toggle="modal">Log in</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
    <script>
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);
            const icon = button.querySelector('i');
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            icon.classList.toggle('bi-eye', !reveal);
            icon.classList.toggle('bi-eye-slash', reveal);
        });
    });
    </script>
    <?php if (!$isLoggedIn && $authModal !== ''): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('<?= $authModal === 'register' ? 'registerModal' : 'loginModal' ?>');
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    </script>
    <?php endif; ?>
</body>
</html>
