<?php
require_once 'config.php';

// ========== AUTHENTICATION FUNCTIONS ==========

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect to a page
function redirect($url) {
    header("Location: $url");
    exit();
}

// Sanitize input data
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

// Check if faculty is approved
function isFacultyApproved() {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'faculty') {
        return false;
    }
    return isset($_SESSION['faculty_status']) && $_SESSION['faculty_status'] == 'approved';
}

// ========== NOTICE FUNCTIONS ==========

// Auto move expired notices to history
function moveExpiredNoticesToHistory($pdo) {
    // Get expired notices
    $stmt = $pdo->query("SELECT n.*, c.cat_name, u.name as posted_by_name 
                         FROM notices n 
                         LEFT JOIN categories c ON n.category_id = c.cat_id 
                         LEFT JOIN users u ON n.posted_by = u.user_id 
                         WHERE n.expiry_date < CURDATE() AND n.expiry_date IS NOT NULL");
    $expired = $stmt->fetchAll();
    
    foreach ($expired as $notice) {
        // Insert into history
        $insert = $pdo->prepare("INSERT INTO notice_history 
                                 (original_notice_id, title, description, category_name, posted_by_name, attachment, priority, expiry_year, expiry_date) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, YEAR(?), ?)");
        $insert->execute([
            $notice['notice_id'],
            $notice['title'],
            $notice['description'],
            $notice['cat_name'],
            $notice['posted_by_name'],
            $notice['attachment'],
            $notice['priority'],
            $notice['expiry_date'],
            $notice['expiry_date']
        ]);
        
        // Delete from notices
        $delete = $pdo->prepare("DELETE FROM notices WHERE notice_id = ?");
        $delete->execute([$notice['notice_id']]);
    }
}

// Run expiry check
moveExpiredNoticesToHistory($pdo);

// ========== FORMATTING FUNCTIONS ==========

// Format date
function formatDate($date) {
    return date('d M Y, h:i A', strtotime($date));
}

// Get time ago
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}
?>