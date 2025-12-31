<?php
require_once __DIR__ . '/includes/auth.inc.php';
require_once __DIR__ . '/includes/db.php';

require_login();

$db = getDB();
$user_id = $_SESSION['user_id'];

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $search_id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM searches WHERE id = ? AND user_id = ?");
    $stmt->execute([$search_id, $user_id]);
    header('Location: dashboard.php');
    exit;
}

// Get all searches for this user, ordered by available first, then by created_at
$stmt = $db->prepare("
    SELECT s.*, n.notified_at 
    FROM searches s 
    LEFT JOIN notifications n ON s.id = n.search_id 
    WHERE s.user_id = ? 
    ORDER BY s.available DESC, s.created_at DESC
");
$stmt->execute([$user_id]);
$searches = $stmt->fetchAll();

// Separate available and unavailable books
$available_books = [];
$unavailable_books = [];

foreach ($searches as $search) {
    if ($search['available']) {
        $available_books[] = $search;
    } else {
        $unavailable_books[] = $search;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AADL BookTracker</title>
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
        .section {
            margin: 40px 0;
        }
        .section h2 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }
        .available-section h2 {
            color: #28a745;
            border-bottom-color: #28a745;
        }
        .unavailable-section h2 {
            color: #dc3545;
            border-bottom-color: #dc3545;
        }
        .book-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .book-card {
            border: 2px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            display: flex;
            gap: 15px;
            position: relative;
        }
        .book-card.available {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        .book-card.unavailable {
            border-color: #dc3545;
            background-color: #fff0f0;
        }
        .book-card img {
            max-width: 100px;
            height: auto;
            flex-shrink: 0;
        }
        .book-details {
            flex: 1;
        }
        .book-details h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .book-details p {
            margin: 5px 0;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .status-badge.available {
            background-color: #28a745;
            color: white;
        }
        .status-badge.unavailable {
            background-color: #dc3545;
            color: white;
        }
        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .last-checked {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .notified {
            font-size: 12px;
            color: #28a745;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="dashboard.php">AADL BookTracker</a></h1>
        <nav>
            <a href="books.php">Search Books</a>
            <?php if (is_admin()): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="auth.php?action=logout">Logout</a>
        </nav>
    </header>

    <div class="section available-section">
        <h2>Available Books (<?= count($available_books) ?>)</h2>
        <?php if (empty($available_books)): ?>
            <div class="empty-state">No available books yet. Check for books using the search above!</div>
        <?php else: ?>
            <div class="book-list">
                <?php foreach ($available_books as $book): ?>
                    <div class="book-card available">
                        <button class="delete-btn" onclick="if(confirm('Remove this book from your list?')) window.location='dashboard.php?delete=<?= $book['id'] ?>'">×</button>
                        <?php if ($book['cover_url']): ?>
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                        <?php endif; ?>
                        <div class="book-details">
                            <span class="status-badge available">Available</span>
                            <h3><?= htmlspecialchars($book['title']) ?></h3>
                            <p><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
                            <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
                            <p><a href="https://aadl.org/search/catalog/<?= urlencode($book['isbn']) ?>" target="_blank">View on AADL Website →</a></p>
                            <?php if ($book['notified_at']): ?>
                                <p class="notified">✓ Notified: <?= date('M j, Y g:i A', strtotime($book['notified_at'])) ?></p>
                            <?php endif; ?>
                            <p class="last-checked">Last checked: <?= date('M j, Y g:i A', strtotime($book['last_checked'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section unavailable-section">
        <h2>Unavailable Books - Checking Daily (<?= count($unavailable_books) ?>)</h2>
        <?php if (empty($unavailable_books)): ?>
            <div class="empty-state">No unavailable books being tracked.</div>
        <?php else: ?>
            <div class="book-list">
                <?php foreach ($unavailable_books as $book): ?>
                    <div class="book-card unavailable">
                        <button class="delete-btn" onclick="if(confirm('Stop tracking this book?')) window.location='dashboard.php?delete=<?= $book['id'] ?>'">×</button>
                        <?php if ($book['cover_url']): ?>
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                        <?php endif; ?>
                        <div class="book-details">
                            <span class="status-badge unavailable">Not Available</span>
                            <h3><?= htmlspecialchars($book['title']) ?></h3>
                            <p><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
                            <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></p>
                            <p>We'll check daily and email you when available!</p>
                            <p class="last-checked">Last checked: <?= date('M j, Y g:i A', strtotime($book['last_checked'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($available_books) && empty($unavailable_books)): ?>
        <div class="section">
            <div class="empty-state">
                <p>You haven't searched for any books yet.</p>
                <p><a href="books.php">Start searching for books →</a></p>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>

