<?php
require_once __DIR__ . '/includes/auth.inc.php';

require_admin();

$db = getDB();
$error = '';
$success = '';

// Handle delete user request
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $user_id = (int)$_POST['user_id'];
        $result = delete_user($user_id);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get all users
$users = get_all_users();

// Sort users: current user first, then by last_login (most recent first)
$current_user_id = $_SESSION['user_id'];
usort($users, function($a, $b) use ($current_user_id) {
    // Current user always first
    if ($a['id'] == $current_user_id) return -1;
    if ($b['id'] == $current_user_id) return 1;
    
    // Sort by last_login: most recent first (null values go to end)
    $a_login = $a['last_login'] ? strtotime($a['last_login']) : 0;
    $b_login = $b['last_login'] ? strtotime($b['last_login']) : 0;
    
    // Most recent first (higher timestamp = more recent)
    return $b_login - $a_login;
});

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Management - AADL BookTracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        h1 {
            margin: 0;
        }
        h1 a {
            color: inherit;
            text-decoration: none;
        }
        nav a {
            margin-left: 15px;
            color: #007bff;
            text-decoration: none;
        }
        nav a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-error {
            background-color: #ffe6e6;
            color: #c00;
            border: 1px solid #fcc;
        }
        .alert-success {
            background-color: #e6ffe6;
            color: #060;
            border: 1px solid #cfc;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-admin {
            background-color: #ff6b6b;
            color: white;
        }
        .badge-user {
            background-color: #6c757d;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 4px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="dashboard.php">AADL BookTracker</a> - Admin</h1>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="books.php">Search Books</a>
            <a href="auth.php?action=logout">Logout</a>
        </nav>
    </header>

    <h2>User Management</h2>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="number"><?= count($users) ?></div>
        </div>
        <div class="stat-card">
            <h3>Admin Users</h3>
            <div class="number"><?= count(array_filter($users, fn($u) => $u['is_admin'])) ?></div>
        </div>
        <div class="stat-card">
            <h3>Regular Users</h3>
            <div class="number"><?= count(array_filter($users, fn($u) => !$u['is_admin']))?></div>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">No users found.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['is_admin']): ?>
                                <span class="badge badge-admin">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-user">User</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['last_login']): ?>
                                <?= date('M j, Y g:i A', strtotime($user['last_login'])) ?>
                            <?php else: ?>
                                <em>Never</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete user <?= htmlspecialchars($user['email']) ?>? This will also delete all their book searches. This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                    <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                                </form>
                            <?php else: ?>
                                <em>Current user</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>

