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
        $error = "Please enter an ISBN";
    } else {
        $normalized_isbn = normalize_isbn($isbn);
        if (!is_valid_isbn($normalized_isbn)) {
            $error = "Please enter a valid ISBN-10 or ISBN-13";
        } else {
            $isbn = $normalized_isbn;
        }
    }

    if (!$error) {
        // Get book info from Open Library
        $book_info = get_book_info_from_openlibrary($isbn);
        
        if (!$book_info) {
            $error = "Could not find book information for ISBN: " . htmlspecialchars($isbn);
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
<html lang="en" class="quiet-cloak">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AADL BookTracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        form {
            margin: 30px 0;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }
        input[type="text"] {
            padding: 8px;
            font-size: 16px;
            width: 300px;
        }
        button {
            padding: 8px 20px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            margin-left: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffe6e6;
            border-radius: 4px;
            margin: 20px 0;
        }
        .book-result {
            margin: 30px 0;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }
        .available {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        .not-available {
            border-color: #dc3545;
            background-color: #fff0f0;
        }
        .book-info {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        .book-info img {
            max-width: 150px;
            height: auto;
        }
        .book-details h3 {
            margin-top: 0;
        }
        .status {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .available .status {
            color: #28a745;
        }
        .not-available .status {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="books.php">AADL BookTracker</a></h1>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <?php if (is_admin()): ?>
                <a href="admin.php">Admin</a>
            <?php endif; ?>
            <a href="auth.php?action=logout">Logout</a>
        </nav>
    </header>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="isbn">ISBN:</label>
        <input type="text" id="isbn" name="isbn" value="<?= htmlspecialchars($isbn ?? '') ?>" placeholder="Enter ISBN number">
        <button type="submit">Check Availability</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($isbn && $book_info): ?>
        <div class="book-result <?= $available ? 'available' : 'not-available' ?>">
            <div class="status">
                <?php if ($available): ?>
                    ✓ Your book is available!
                <?php else: ?>
                    ✗ Your book is not available
                <?php endif; ?>
            </div>
            <div class="book-info">
                <img src="<?= htmlspecialchars($book_info['cover_url']) ?>" alt="<?= htmlspecialchars($book_info['title']) ?>">
                <div class="book-details">
                    <h3><?= htmlspecialchars($book_info['title']) ?></h3>
                    <p><strong>Author:</strong> <?= htmlspecialchars($book_info['author']) ?></p>
                    <p><strong>ISBN:</strong> <?= htmlspecialchars($isbn) ?></p>
                    <?php if ($available): ?>
                        <p><a href="<?= htmlspecialchars($aadl_link) ?>" target="_blank">View on AADL Website →</a></p>
                    <?php else: ?>
                        <p>We'll check daily and notify you when it becomes available!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
