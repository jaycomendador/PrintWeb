<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(in_array($_SESSION['user_role'], ADMIN_ROLES, true)
        ? '/admin/dashboard.php'
        : '/user/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/?auth=register');
}

$formData = [
    'full_name' => trim($_POST['full_name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'role' => trim($_POST['role'] ?? 'student'),
    'contact_number' => trim($_POST['contact_number'] ?? ''),
    'department' => trim($_POST['department'] ?? ''),
];
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$errors = [];

if ($formData['full_name'] === '') {
    $errors[] = 'Full name is required.';
}
if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}
if (!array_key_exists($formData['role'], ROLES) || in_array($formData['role'], ADMIN_ROLES, true)) {
    $errors[] = 'Invalid role selected.';
}

if (!$errors) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$formData['email']]);
    if ($stmt->fetch()) {
        $errors[] = 'This email is already registered. Please use a different email or sign in.';
    } else {
        $userId = generateUserId($formData['role']);
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, full_name, email, password_hash, role, contact_number, department)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $formData['full_name'],
            $formData['email'],
            $passwordHash,
            $formData['role'],
            $formData['contact_number'],
            $formData['department'],
        ]);
        redirect('/?auth=login&registered=1');
    }
}

$_SESSION['auth_modal_flash'] = [
    'type' => 'register',
    'errors' => $errors,
    'formData' => $formData,
];
redirect('/?auth=register');
