<?php
require_once __DIR__ . '/includes/auth.inc.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$error = '';
$success = '';

if ($action === 'register') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $result = register_user($email, $password);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if ($success) {
        header('Location: login.php?registered=1');
        exit;
    } else {
        $_SESSION['register_error'] = $error;
        header('Location: register.php');
        exit;
    }
}

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = login_user($email, $password);
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
    
    $_SESSION['login_error'] = $error;
    header('Location: login.php');
    exit;
}

if ($action === 'logout') {
    logout_user();
    header('Location: login.php');
    exit;
}

header('Location: login.php');
exit;

