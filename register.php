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
    <title>Register - AADL BookTracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        label {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        input[type="email"],
        input[type="password"],
        input[type="text"] {
            padding: 8px;
            font-size: 16px;
        }
        button {
            padding: 10px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffe6e6;
            border-radius: 4px;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <h1>AADL BookTracker</h1>
    <h2>Register</h2>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form action="auth.php" method="post">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <label>
            Email:
            <input type="email" name="email" required>
        </label>
        
        <label>
            Password:
            <input type="password" name="password" required minlength="8">
        </label>
        
        <label>
            Confirm Password:
            <input type="password" name="confirm_password" required minlength="8">
        </label>
        
        <button type="submit">Register</button>
    </form>
    
    <div class="links">
        <a href="login.php">Already have an account? Login</a>
    </div>
</body>
</html>

