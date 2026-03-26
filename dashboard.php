<?php
require_once __DIR__ . '/includes/auth.inc.php';
require_once __DIR__ . '/includes/db.php';

require_login();

$db = getDB();
$user_id = $_SESSION['user_id'];
$csrf_token = generate_csrf_token();

$status_message = '';
if (isset($_GET['status']) && $_GET['status'] === 'marked') {
    $status_message = 'Moved to your waiting list. We’ll email you when it shows up in the catalog.';
}
if (isset($_GET['status']) && $_GET['status'] === 'resumed') {
    $status_message = 'Daily checks are on again—we’ll email you when this book is in the catalog.';
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $search_id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM searches WHERE id = ? AND user_id = ?");
    $stmt->execute([$search_id, $user_id]);
    header('Location: dashboard.php');
    exit;
}

// Handle manual unavailable override
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_unavailable'], $_POST['search_id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $search_id = (int)$_POST['search_id'];
        $stmt = $db->prepare("UPDATE searches SET available = 0, manual_unavailable = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$search_id, $user_id]);
        $stmt = $db->prepare("UPDATE notifications SET notified_at = NULL WHERE search_id = ?");
        $stmt->execute([$search_id]);
        header('Location: dashboard.php?status=marked');
        exit;
    }
}

// Handle resume auto-check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resume_autocheck'], $_POST['search_id'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $search_id = (int)$_POST['search_id'];
        $stmt = $db->prepare("UPDATE searches SET manual_unavailable = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$search_id, $user_id]);
        header('Location: dashboard.php?status=resumed');
        exit;
    }
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
    <link rel="stylesheet" href="assets/app-layout.css">
</head>
<body>
    <div class="page-shell page-shell--wide">
    <header class="site-header">
        <h1 class="site-header__title"><a href="dashboard.php">AADL BookTracker</a></h1>
        <nav>
            <ul class="site-nav">
                <li><a href="books.php">Look up</a></li>
                <?php if (is_admin()): ?>
                    <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
                <li><a href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
    <?php if ($status_message): ?>
        <div class="status-banner" role="status" aria-live="polite"><?= htmlspecialchars($status_message) ?></div>
    <?php endif; ?>

    <section class="section section--available" aria-labelledby="heading-available">
        <h2 id="heading-available" class="section__heading">Available (<?= count($available_books) ?>)</h2>
        <?php if (empty($available_books)): ?>
            <div class="empty-state">Nothing here yet. <a href="books.php">Look up a book by ISBN</a> to add one.</div>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($available_books as $book): ?>
                    <?php $aadl_link = $book['aadl_url'] ?: "https://aadl.org/search/catalog/{$book['isbn']}"; ?>
                    <article class="book-card book-card--available">
                        <button type="button" class="book-card__remove" title="Remove" aria-label="Remove from list" onclick="if(confirm('Remove this book from your list?')) window.location='dashboard.php?delete=<?= (int)$book['id'] ?>'">×</button>
                        <div class="book-card__main">
                            <?php if ($book['cover_url']): ?>
                                <div class="book-card__cover">
                                    <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                                </div>
                            <?php endif; ?>
                            <div class="book-card__body">
                                <h3 class="book-card__title"><?= htmlspecialchars($book['title']) ?></h3>
                                <p class="book-card__meta"><?= htmlspecialchars($book['author']) ?> · <?= htmlspecialchars($book['isbn']) ?></p>
                                <p class="book-card__meta-muted"><?php
                                    $parts = ['Checked ' . date('M j, g:i A', strtotime($book['last_checked']))];
                                    if ($book['notified_at']) {
                                        $parts[] = 'Emailed ' . date('M j', strtotime($book['notified_at']));
                                    }
                                    echo htmlspecialchars(implode(' · ', $parts));
                                ?></p>
                            </div>
                        </div>
                        <div class="book-card__actions book-card__actions--inline">
                            <a class="btn btn--primary" href="<?= htmlspecialchars($aadl_link) ?>" target="_blank" rel="noopener">Open in catalog</a>
                            <form method="post" class="book-card__inline-form" onsubmit="return confirm('Move this book to your waiting list? We’ll keep checking the catalog for you.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="search_id" value="<?= htmlspecialchars($book['id']) ?>">
                                <button type="submit" name="mark_unavailable" class="btn btn--ghost">Move to waiting list</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="section section--unavailable" aria-labelledby="heading-unavailable">
        <h2 id="heading-unavailable" class="section__heading">Waiting (<?= count($unavailable_books) ?>)</h2>
        <?php if (empty($unavailable_books)): ?>
            <div class="empty-state">You’re not waiting on any books yet.</div>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($unavailable_books as $book): ?>
                    <article class="book-card book-card--unavailable">
                        <button type="button" class="book-card__remove" title="Remove" aria-label="Stop tracking" onclick="if(confirm('Stop tracking this book and remove it from your list?')) window.location='dashboard.php?delete=<?= (int)$book['id'] ?>'">×</button>
                        <div class="book-card__main">
                            <?php if ($book['cover_url']): ?>
                                <div class="book-card__cover">
                                    <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="" loading="lazy" decoding="async">
                                </div>
                            <?php endif; ?>
                            <div class="book-card__body">
                                <h3 class="book-card__title"><?= htmlspecialchars($book['title']) ?></h3>
                                <p class="book-card__meta"><?= htmlspecialchars($book['author']) ?> · <?= htmlspecialchars($book['isbn']) ?></p>
                                <p class="book-card__meta-muted">Checked <?= date('M j, g:i A', strtotime($book['last_checked'])) ?></p>
                                <?php if (!empty($book['manual_unavailable'])): ?>
                                    <p class="book-card__hint">Paused — resume to keep checking.</p>
                                <?php else: ?>
                                    <p class="book-card__hint">We’ll email you when it appears in the catalog.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($book['manual_unavailable'])): ?>
                            <div class="book-card__actions book-card__actions--inline">
                                <form method="post" onsubmit="return confirm('Turn daily catalog checks back on for this book?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="search_id" value="<?= htmlspecialchars($book['id']) ?>">
                                    <button type="submit" name="resume_autocheck" class="btn btn--secondary">Resume checks</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    </main>
    </div>
</body>
</html>

