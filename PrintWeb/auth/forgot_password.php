<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) redirect('/user/dashboard.php');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        // Always show success to prevent email enumeration
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card fade-in">
        <div class="auth-logo">
            <div class="logo-icon"><i class="fas fa-print"></i></div>
            <div>
                <div class="logo-text"><?= APP_NAME ?></div>
                <div class="logo-sub"><?= APP_TAGLINE ?></div>
            </div>
        </div>

        <h1 class="auth-title">Forgot Password</h1>
        <p class="auth-sub">Enter your email to reset your password</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>If an account with that email exists, a password reset link has been sent. Please check your inbox.</div>
            </div>
            <a href="<?= BASE_URL ?>/?auth=login" class="btn btn-primary" style="width:100%;justify-content:center">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= implode('<br>', array_map('h', $errors)) ?></div>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            <hr class="divider">
            <p style="text-align:center;font-size:.85rem;color:var(--gray-500)">
                <a href="<?= BASE_URL ?>/?auth=login" style="color:var(--primary);font-weight:600"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
