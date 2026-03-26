<?php
require_once __DIR__ . '/includes/auth.inc.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

$registered = isset($_GET['registered']);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - AADL BookTracker</title>
    <link rel="stylesheet" href="assets/app-layout.css">
</head>
<body>
    <main class="auth-page">
    <div class="auth-card">
    <h1>AADL BookTracker</h1>
    <h2>Log in</h2>
    
    <?php if ($registered): ?>
        <div class="alert alert--success" role="status" aria-live="polite">Account created. Log in with your email and password.</div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form action="auth.php" method="post">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <label>
            Email
            <input type="email" name="email" id="login-email" required autocomplete="username">
        </label>
        
        <label>
            Password
            <input type="password" name="password" id="login-password" required autocomplete="current-password">
        </label>
        
        <button type="submit">Log in</button>
    </form>
    
    <div class="links">
        <a href="register.php">Create an account</a>
    </div>
    </div>
    </main>
</body>
</html>

