<?php
require_once __DIR__ . '/db.php';
// Config is already loaded via db.php

function check_book_availability($isbn) {
    $isbn = trim($isbn);
    if (empty($isbn)) {
        return false;
    }
    
    $html = @file_get_contents("https://aadl.org/search/catalog/{$isbn}");
    if ($html === false) {
        error_log("Failed to fetch AADL catalog for ISBN: {$isbn}");
        return false;
    }
    
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();
    
    $searchResults = $doc->getElementById("search-results-container");
    if (!$searchResults) {
        return false;
    }
    
    $noResults = "Sorry, we didn't find any results for your search!";
    $searchResultsText = $searchResults->nodeValue;
    
    return !str_contains($searchResultsText, $noResults);
}

function get_book_info_from_openlibrary($isbn) {
    $isbn = trim($isbn);
    if (empty($isbn)) {
        return null;
    }
    
    $bookJson = @file_get_contents("https://openlibrary.org/search.json?q={$isbn}");
    if ($bookJson === false) {
        error_log("Failed to fetch Open Library data for ISBN: {$isbn}");
        return null;
    }
    
    $bookInfo = json_decode($bookJson);
    if (!$bookInfo || empty($bookInfo->docs)) {
        return null;
    }
    
    $doc = $bookInfo->docs[0];
    return [
        'title' => $doc->title ?? 'Unknown Title',
        'author' => $doc->author_name[0] ?? 'Unknown Author',
        'cover_url' => "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg"
    ];
}

function save_or_update_search($user_id, $isbn, $book_info, $available) {
    $db = getDB();
    
    // Check if search already exists
    $stmt = $db->prepare("SELECT id FROM searches WHERE user_id = ? AND isbn = ?");
    $stmt->execute([$user_id, $isbn]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing search
        $stmt = $db->prepare("
            UPDATE searches 
            SET title = ?, author = ?, cover_url = ?, available = ?, last_checked = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([
            $book_info['title'],
            $book_info['author'],
            $book_info['cover_url'],
            $available ? 1 : 0,
            $existing['id']
        ]);
        return $existing['id'];
    } else {
        // Insert new search
        $stmt = $db->prepare("
            INSERT INTO searches (user_id, isbn, title, author, cover_url, available) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $isbn,
            $book_info['title'],
            $book_info['author'],
            $book_info['cover_url'],
            $available ? 1 : 0
        ]);
        return $db->lastInsertId();
    }
}

function send_availability_notification($user_email, $book_info, $isbn) {
    $subject = "Book Available: {$book_info['title']}";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .book-info { border: 1px solid #ddd; padding: 20px; margin: 20px 0; }
            .cover { float: left; margin-right: 20px; }
            .cover img { max-width: 150px; }
            .details { overflow: hidden; }
            .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Great News! Your Book is Available</h2>
            <div class='book-info'>
                <div class='cover'>
                    <img src='{$book_info['cover_url']}' alt='{$book_info['title']}'>
                </div>
                <div class='details'>
                    <h3>{$book_info['title']}</h3>
                    <p><strong>Author:</strong> {$book_info['author']}</p>
                    <p><strong>ISBN:</strong> {$isbn}</p>
                    <a href='https://aadl.org/search/catalog/{$isbn}' class='button'>View on AADL Website</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    
    return mail($user_email, $subject, $message, $headers);
}

