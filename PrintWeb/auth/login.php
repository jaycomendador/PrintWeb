<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(in_array($_SESSION['user_role'], ADMIN_ROLES, true)
        ? '/admin/dashboard.php'
        : '/user/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $query = [];
    if (isset($_GET['registered'])) {
        $query['registered'] = '1';
    }
    if (isset($_GET['timeout'])) {
        $query['timeout'] = '1';
    }
    $query['auth'] = 'login';
    redirect('/?' . http_build_query($query));
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$errors = [];

if ($email === '' || $password === '') {
    $errors[] = 'Please enter your email and password.';
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_uid'] = $user['user_id'];

        redirect(in_array($user['role'], ADMIN_ROLES, true)
            ? '/admin/dashboard.php'
            : '/user/dashboard.php');
    }

    $errors[] = 'Invalid email or password. Please try again.';
}

$_SESSION['auth_modal_flash'] = [
    'type' => 'login',
    'errors' => $errors,
    'email' => $email,
];
redirect('/?auth=login');
