<?php
require_once __DIR__ . '/includes/auth.inc.php';

$error = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account - AADL BookTracker</title>
    <link rel="stylesheet" href="assets/app-layout.css">
</head>
<body>
    <main class="auth-page">
    <div class="auth-card">
    <h1>AADL BookTracker</h1>
    <h2>Create an account</h2>
    
    <?php if ($error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form action="auth.php" method="post">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <label>
            Email
            <input type="email" name="email" id="reg-email" required autocomplete="email">
        </label>
        
        <label>
            Password (8+ characters)
            <input type="password" name="password" id="reg-password" required minlength="8" autocomplete="new-password">
        </label>
        
        <label>
            Confirm password
            <input type="password" name="confirm_password" id="reg-confirm" required minlength="8" autocomplete="new-password">
        </label>
        
        <button type="submit">Create account</button>
    </form>
    
    <div class="links">
        <a href="login.php">Already have an account? Log in</a>
    </div>
    </div>
    </main>
</body>
</html>

