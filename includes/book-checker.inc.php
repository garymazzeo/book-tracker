<?php
require_once __DIR__ . '/db.php';
// Config is already loaded via db.php

function normalize_isbn($isbn) {
    $isbn = strtoupper(trim($isbn));
    // Remove hyphens, spaces, and any non ISBN characters
    $isbn = preg_replace('/[^0-9X]/', '', $isbn);
    return $isbn;
}

function is_valid_isbn($isbn) {
    $isbn = normalize_isbn($isbn);
    $len = strlen($isbn);

    if ($len === 10) {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $char = $isbn[$i];
            if ($i === 9 && $char === 'X') {
                $value = 10;
            } elseif (ctype_digit($char)) {
                $value = (int)$char;
            } else {
                return false;
            }
            $sum += ($value * (10 - $i));
        }
        return $sum % 11 === 0;
    }

    if ($len === 13) {
        if (!ctype_digit($isbn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $digit = (int)$isbn[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        return $sum % 10 === 0;
    }

    return false;
}

function normalize_text($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/[^a-z0-9 ]+/', '', $text);
    return trim($text);
}

function get_aadl_record_url($isbn, $title, $author) {
    $isbn = normalize_isbn($isbn);
    if (empty($isbn) || !is_valid_isbn($isbn)) {
        return null;
    }

    $html = @file_get_contents("https://aadl.org/search/catalog/{$isbn}");
    if ($html === false) {
        error_log("Failed to fetch AADL catalog for ISBN: {$isbn}");
        return null;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $resultNode = $xpath->query("//div[@id='search-results']//div[contains(@class,'search-result')][1]")->item(0);
    if (!$resultNode) {
        return null;
    }

    $titleNode = $xpath->query(".//a[contains(@class,'result-title')]", $resultNode)->item(0);
    $authorNode = $xpath->query(".//a[contains(@href,'/search/catalog/author')]", $resultNode)->item(0);

    if (!$titleNode || !$authorNode) {
        error_log("AADL parse: missing title/author node for ISBN: {$isbn}");
        return null;
    }

    $aadlTitle = normalize_text($titleNode->textContent);
    $aadlAuthor = normalize_text($authorNode->textContent);
    $expectedTitle = normalize_text($title);
    $expectedAuthor = normalize_text($author);

    if ($aadlTitle !== $expectedTitle || $aadlAuthor !== $expectedAuthor) {
        error_log("AADL parse: title/author mismatch for ISBN {$isbn}. AADL='{$aadlTitle}'/'{$aadlAuthor}', expected='{$expectedTitle}'/'{$expectedAuthor}'");
        return null;
    }

    $href = $titleNode->getAttribute('href');
    if (!$href) {
        return null;
    }

    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
        return $href;
    }

    return 'https://aadl.org' . $href;
}

function check_book_availability($isbn) {
    $isbn = normalize_isbn($isbn);
    if (empty($isbn) || !is_valid_isbn($isbn)) {
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
    $isbn = normalize_isbn($isbn);
    if (empty($isbn) || !is_valid_isbn($isbn)) {
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

function save_or_update_search($user_id, $isbn, $book_info, $available, $aadl_url = null) {
    $db = getDB();
    $isbn = normalize_isbn($isbn);
    
    // Check if search already exists
    $stmt = $db->prepare("SELECT id FROM searches WHERE user_id = ? AND isbn = ?");
    $stmt->execute([$user_id, $isbn]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing search
        $stmt = $db->prepare("
            UPDATE searches 
            SET title = ?, author = ?, cover_url = ?, aadl_url = ?, available = ?, manual_unavailable = IF(?, 0, manual_unavailable), last_checked = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([
            $book_info['title'],
            $book_info['author'],
            $book_info['cover_url'],
            $aadl_url,
            $available ? 1 : 0,
            $available ? 1 : 0,
            $existing['id']
        ]);
        return $existing['id'];
    } else {
        // Insert new search
        $stmt = $db->prepare("
            INSERT INTO searches (user_id, isbn, title, author, cover_url, aadl_url, available) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $isbn,
            $book_info['title'],
            $book_info['author'],
            $book_info['cover_url'],
            $aadl_url,
            $available ? 1 : 0
        ]);
        return $db->lastInsertId();
    }
}

function send_availability_notification($user_email, $book_info, $isbn, $aadl_url = null) {
    $subject = "Book Available: {$book_info['title']}";
    $link_url = $aadl_url ?: "https://aadl.org/search/catalog/{$isbn}";
    
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
                    <a href='{$link_url}' class='button'>View on AADL Website</a>
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

