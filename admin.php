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
        $error = 'This page expired. Refresh and try again.';
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
    <link rel="stylesheet" href="assets/app-layout.css">
</head>
<body>
    <div class="page-shell page-shell--wide admin-page">
    <header class="site-header">
        <h1 class="site-header__title"><a href="dashboard.php">AADL BookTracker</a> <span class="site-header__suffix">Admin</span></h1>
        <nav>
            <ul class="site-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="books.php">Look up</a></li>
                <li><a href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
    <h2>User management</h2>

    <?php if ($error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert--success" role="status" aria-live="polite"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="admin-stat">
            <h3>Total users</h3>
            <p class="number"><?= count($users) ?></p>
        </div>
        <div class="admin-stat">
            <h3>Admins</h3>
            <p class="number"><?= count(array_filter($users, fn($u) => $u['is_admin'])) ?></p>
        </div>
        <div class="admin-stat">
            <h3>Members</h3>
            <p class="number"><?= count(array_filter($users, fn($u) => !$u['is_admin']))?></p>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">No accounts yet.</div>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <caption><span class="sr-only">Registered accounts</span></caption>
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col">Created</th>
                    <th scope="col">Last login</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td class="admin-table__cell--email" title="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($user['email']) ?></td>
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
                                <form class="admin-table__delete-form" method="post" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Are you sure you want to delete user ' . $user['email'] . '? This will also delete all their book searches. This action cannot be undone.'), ENT_QUOTES, 'UTF-8') ?>);">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                    <button type="submit" name="delete_user" class="btn btn--danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <em>Current user</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </main>
    </div>
</body>
</html>

