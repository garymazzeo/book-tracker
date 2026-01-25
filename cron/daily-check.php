<?php
/**
 * Daily Book Availability Checker
 * 
 * This script should be run daily via cron:
 * 0 9 * * * /usr/bin/php /path/to/book-tracker/cron/daily-check.php
 * 
 * This will check all unavailable books at 9 AM daily
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/book-checker.inc.php';

// Get the absolute path for logging
$script_path = __DIR__;
$log_file = __DIR__ . '/../cron/log.txt';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

log_message("Starting daily book availability check");

$db = getDB();

// Get all searches where book is unavailable and hasn't been notified yet
// We check books where available = false OR where available = true but notification hasn't been sent
$stmt = $db->prepare("
    SELECT s.*, u.email 
    FROM searches s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.available = 0 
    AND NOT EXISTS (
        SELECT 1 FROM notifications n 
        WHERE n.search_id = s.id AND n.notified_at IS NOT NULL
    )
    ORDER BY s.last_checked ASC
");
$stmt->execute();
$searches = $stmt->fetchAll();

log_message("Found " . count($searches) . " books to check");

$checked = 0;
$became_available = 0;
$notifications_sent = 0;
$errors = 0;

foreach ($searches as $search) {
    $checked++;
    $isbn = $search['isbn'];
    $user_id = $search['user_id'];
    $user_email = $search['email'];
    
    $normalized_isbn = normalize_isbn($isbn);
    if (!is_valid_isbn($normalized_isbn)) {
        $errors++;
        log_message("ERROR: Invalid ISBN '{$isbn}' for user {$user_email}");
        continue;
    }
    if ($normalized_isbn !== $isbn) {
        $isbn = $normalized_isbn;
        $fix_stmt = $db->prepare("UPDATE searches SET isbn = ? WHERE id = ?");
        $fix_stmt->execute([$isbn, $search['id']]);
    }
    
    log_message("Checking ISBN {$isbn} for user {$user_email}");
    
    try {
        // Check availability
        $available = check_book_availability($isbn);
        if (!empty($search['manual_unavailable'])) {
            $available = false;
        }
        
        // Get book info (in case it's missing or updated)
        $book_info = get_book_info_from_openlibrary($isbn);
        
        if (!$book_info) {
            log_message("WARNING: Could not fetch book info for ISBN {$isbn}");
            // Still update the search with availability status
            $book_info = [
                'title' => $search['title'] ?? 'Unknown',
                'author' => $search['author'] ?? 'Unknown',
                'cover_url' => $search['cover_url'] ?? ''
            ];
        }
        
        $aadl_url = get_aadl_record_url($isbn, $book_info['title'], $book_info['author']);

        // Update search record
        $update_stmt = $db->prepare("
            UPDATE searches 
            SET title = ?, author = ?, cover_url = ?, aadl_url = ?, available = ?, manual_unavailable = IF(?, 0, manual_unavailable), last_checked = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([
            $book_info['title'],
            $book_info['author'],
            $book_info['cover_url'],
            $aadl_url,
            $available ? 1 : 0,
            $available ? 1 : 0,
            $search['id']
        ]);
        
        // If book became available, send notification
        if ($available) {
            $became_available++;
            log_message("Book {$isbn} is now AVAILABLE - sending notification to {$user_email}");
            
            // Send email notification
            $email_sent = send_availability_notification($user_email, $book_info, $isbn, $aadl_url);
            
            if ($email_sent) {
                $notifications_sent++;
                log_message("Notification sent successfully to {$user_email}");
                
                // Record notification
                $notify_stmt = $db->prepare("
                    INSERT INTO notifications (search_id, notified_at) 
                    VALUES (?, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE notified_at = CURRENT_TIMESTAMP
                ");
                $notify_stmt->execute([$search['id']]);
            } else {
                $errors++;
                log_message("ERROR: Failed to send notification to {$user_email}");
            }
        } else {
            log_message("Book {$isbn} is still unavailable");
        }
        
        // Small delay to avoid hammering the API
        usleep(500000); // 0.5 second delay
        
    } catch (Exception $e) {
        $errors++;
        log_message("ERROR checking ISBN {$isbn}: " . $e->getMessage());
    }
}

log_message("Daily check completed: {$checked} checked, {$became_available} became available, {$notifications_sent} notifications sent, {$errors} errors");

