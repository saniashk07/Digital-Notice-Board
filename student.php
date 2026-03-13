<?php
require_once 'functions.php';

// Check if user is logged in and is student
if (!isLoggedIn() || $_SESSION['user_role'] != 'student') {
    redirect('index.php');
}

// Get filter parameters
$category = isset($_GET['category']) ? clean($_GET['category']) : '';
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

// Build query for approved notices only (not scheduled, not expired)
$sql = "SELECT n.*, c.cat_name, c.cat_icon, u.name as posted_by_name, u.department as faculty_dept
        FROM notices n 
        LEFT JOIN categories c ON n.category_id = c.cat_id 
        LEFT JOIN users u ON n.posted_by = u.user_id 
        WHERE n.status = 'approved' 
        AND (n.expiry_date IS NULL OR n.expiry_date >= CURDATE())
        AND (n.is_scheduled = 0 OR (n.is_scheduled = 1 AND n.schedule_date <= NOW()))";

$params = [];

if (!empty($category)) {
    $sql .= " AND n.category_id = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (n.title LIKE ? OR n.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY 
          CASE WHEN n.priority = 'urgent' THEN 0 ELSE 1 END,
          n.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notices = $stmt->fetchAll();

// Get all categories for filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY cat_name")->fetchAll();

// Get urgent notices count for banner
$urgent_count = $pdo->query("SELECT COUNT(*) FROM notices WHERE priority='urgent' AND status='approved' 
                              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                              AND (is_scheduled = 0 OR (is_scheduled = 1 AND schedule_date <= NOW()))")->fetchColumn();

// Get recent notices for sidebar
$recent = $pdo->query("SELECT title, created_at FROM notices WHERE status='approved' 
                        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                        AND (is_scheduled = 0 OR (is_scheduled = 1 AND schedule_date <= NOW()))
                        ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get popular categories
$popular_cats = $pdo->query("SELECT c.cat_name, c.cat_icon, COUNT(n.notice_id) as count 
                              FROM categories c 
                              LEFT JOIN notices n ON c.cat_id = n.category_id AND n.status='approved'
                              GROUP BY c.cat_id 
                              ORDER BY count DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="student-page">
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php" class="logo">
            <h1 class="college-name-hero">Student Portal</h1>
        </a>
        <div class="navbar-user">
            <span class="user-badge">
                <i class="fas fa-user-graduate"></i> <?php echo $_SESSION['user_name']; ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Urgent Banner -->
    <?php if($urgent_count > 0): ?>
    <div class="urgent-banner" style="background: #dc3545; color: white; padding: 12px; text-align: center; animation: slideDown 0.5s;">
        <marquee behavior="scroll" direction="left" scrollamount="5">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>URGENT:</strong> <?php echo $urgent_count; ?> urgent notice(s) available. Check immediately!
            <i class="fas fa-exclamation-triangle"></i>
        </marquee>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <!-- Welcome Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bullhorn"></i> Latest Announcements
            </div>
            <div class="card-body">
                <p>Stay updated with the latest notices from your college</p>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 25px;">
            <!-- Main Content -->
            <div>
                <!-- Filter Section -->
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <i class="fas fa-filter"></i> Filter Notices
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <select name="category" style="flex: 1; min-width: 200px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px;">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['cat_id']; ?>" 
                                        <?php echo $category == $cat['cat_id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['cat_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="text" name="search" placeholder="Search notices..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   style="flex: 1; min-width: 200px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px;">
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply
                            </button>
                            
                            <?php if(!empty($category) || !empty($search)): ?>
                                <a href="student.php" class="btn btn-danger">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Notices List -->
                <?php if(count($notices) > 0): ?>
                    <?php foreach($notices as $notice): ?>
                        <div class="notice-card <?php echo $notice['priority'] == 'urgent' ? 'urgent' : ''; ?>">
                            <div class="notice-header">
                                <h4 class="notice-title">
                                    <i class="fas <?php echo $notice['cat_icon'] ?: 'fa-bullhorn'; ?>" style="color: #2a5298;"></i>
                                    <?php echo htmlspecialchars($notice['title']); ?>
                                </h4>
                                <?php if($notice['priority'] == 'urgent'): ?>
                                    <span class="badge badge-urgent"><i class="fas fa-exclamation-circle"></i> URGENT</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notice-meta">
                                <span><i class="fas fa-tag"></i> <?php echo $notice['cat_name']; ?></span>
                                <span><i class="fas fa-user-tie"></i> <?php echo $notice['posted_by_name']; ?></span>
                                <span><i class="fas fa-building"></i> <?php echo $notice['faculty_dept']; ?></span>
                                <span><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($notice['created_at'])); ?></span>
                            </div>
                            
                            <div class="notice-description">
                                <?php echo nl2br(htmlspecialchars(substr($notice['description'], 0, 200))); ?>...
                            </div>
                            
                            <div class="notice-footer">
                                <span class="badge" style="background: #e9ecef;">
                                    <i class="fas <?php echo $notice['cat_icon']; ?>"></i> 
                                    <?php echo $notice['cat_name']; ?>
                                </span>
                                <a href="#" class="btn btn-primary btn-sm" onclick="showNotice(<?php echo htmlspecialchars(json_encode($notice)); ?>); return false;">
                                    Read More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox"></i>
                        <h3>No notices found</h3>
                        <p>Try adjusting your filters or check back later</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div>
                <!-- Recent Notices -->
                <div class="card" style="margin-bottom: 25px;">
                    <div class="card-header">
                        <i class="fas fa-clock"></i> Recent Notices
                    </div>
                    <div class="card-body">
                        <?php foreach($recent as $item): ?>
                            <div style="padding: 10px 0; border-bottom: 1px solid #f0f2f5;">
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div style="font-size: 11px; color: #999;"><?php echo date('d M Y', strtotime($item['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Popular Categories -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> Popular Categories
                    </div>
                    <div class="card-body">
                        <?php foreach($popular_cats as $cat): ?>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f2f5;">
                                <span><i class="fas <?php echo $cat['cat_icon']; ?>" style="color: #2a5298;"></i> <?php echo $cat['cat_name']; ?></span>
                                <span class="badge" style="background: #e9ecef;"><?php echo $cat['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notice Modal -->
    <div id="noticeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"></h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-meta" id="modalMeta"></div>
                <div class="modal-description" id="modalDescription"></div>
                <div style="margin-top: 20px;" id="modalAttachment"></div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>