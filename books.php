<?php
require_once __DIR__ . '/includes/auth.inc.php';
require_once __DIR__ . '/includes/book-checker.inc.php';

require_login();

$isbn = null;
$available = null;
$book_info = null;
$error = null;

if (!empty($_POST)) {
    $isbn = trim($_POST["isbn"] ?? '');
    
    if (empty($isbn)) {
        $error = "Enter the book’s ISBN (the number on the back cover or barcode).";
    } else {
        $normalized_isbn = normalize_isbn($isbn);
        if (!is_valid_isbn($normalized_isbn)) {
            $error = "That doesn’t look like a valid ISBN. Use 10 or 13 digits (dashes are fine).";
        } else {
            $isbn = $normalized_isbn;
        }
    }

    if (!$error) {
        // Get book info from Open Library
        $book_info = get_book_info_from_openlibrary($isbn);
        
        if (!$book_info) {
            $error = "We couldn’t find that book in Open Library. Double-check the ISBN and try again.";
        } else {
            $aadl_url = get_aadl_record_url($isbn, $book_info['title'], $book_info['author']);
            $aadl_link = $aadl_url ?: "https://aadl.org/search/catalog/{$isbn}";
            // Check availability
            $available = check_book_availability($isbn);
            
            // Save to database
            $user_id = $_SESSION['user_id'];
            $search_id = save_or_update_search($user_id, $isbn, $book_info, $available, $aadl_url);
            
            // If book becomes available and wasn't before, create notification record
            if ($available) {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM notifications WHERE search_id = ?");
                $stmt->execute([$search_id]);
                if (!$stmt->fetch()) {
                    $stmt = $db->prepare("INSERT INTO notifications (search_id) VALUES (?)");
                    $stmt->execute([$search_id]);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Look up a book - AADL BookTracker</title>
    <link rel="stylesheet" href="assets/app-layout.css">
</head>
<body>
    <div class="page-shell">
    <header class="site-header">
        <h1 class="site-header__title"><a href="books.php">AADL BookTracker</a></h1>
        <nav>
            <ul class="site-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if (is_admin()): ?>
                    <li><a href="admin.php">Admin</a></li>
                <?php endif; ?>
                <li><a href="auth.php?action=logout">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main>
    <form class="search-panel" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="search-panel__row">
            <div>
                <label for="isbn">ISBN</label>
                <input type="text" id="isbn" name="isbn" value="<?= htmlspecialchars($isbn ?? '') ?>" placeholder="e.g. 9780399590504" autocomplete="off" aria-describedby="isbn-hint">
            </div>
            <button type="submit" class="btn btn--primary">Check AADL catalog</button>
        </div>
        <p class="search-panel__hint" id="isbn-hint">The number on the barcode or copyright page.</p>
    </form>

    <?php if ($error): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($isbn && $book_info): ?>
        <div class="result-panel <?= $available ? 'result-panel--ok' : 'result-panel--wait' ?>">
            <p class="result-panel__status"><?= $available ? 'In AADL catalog' : 'Not in catalog yet' ?></p>
            <div class="result-panel__body">
                <div class="result-panel__cover">
                    <img src="<?= htmlspecialchars($book_info['cover_url']) ?>" alt="Cover: <?= htmlspecialchars($book_info['title']) ?>">
                </div>
                <div class="result-panel__details">
                    <h3><?= htmlspecialchars($book_info['title']) ?></h3>
                    <p><?= htmlspecialchars($book_info['author']) ?> · <?= htmlspecialchars($isbn) ?></p>
                    <?php if ($available): ?>
                        <p><a class="btn btn--primary" href="<?= htmlspecialchars($aadl_link) ?>" target="_blank" rel="noopener">Open in catalog</a></p>
                    <?php else: ?>
                        <p class="book-card__hint">We check the catalog daily and email you when this book appears.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </main>
    </div>
</body>
</html>
