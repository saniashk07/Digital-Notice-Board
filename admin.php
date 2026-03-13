<?php
require_once 'functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    redirect('index.php');
}

$success = '';
$error = '';

// ============================================
// Handle Admin Actions
// ============================================

// Approve/Reject Faculty
if (isset($_POST['action']) && $_POST['action'] == 'faculty_approval') {
    $faculty_id = $_POST['faculty_id'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'faculty'");
    if ($stmt->execute([$status, $faculty_id])) {
        $log = $pdo->prepare("INSERT INTO faculty_approvals (faculty_id, approved_by) VALUES (?, ?)");
        $log->execute([$faculty_id, $_SESSION['user_id']]);
        $success = "Faculty " . ($status == 'approved' ? 'approved' : 'rejected') . " successfully!";
    }
}

// Post Notice (Admin can post directly approved)
if (isset($_POST['post_notice'])) {
    $title = clean($_POST['title']);
    $description = clean($_POST['description']);
    $category_id = clean($_POST['category_id']);
    $priority = clean($_POST['priority']);
    $schedule_date = !empty($_POST['schedule_date']) ? $_POST['schedule_date'] : null;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $is_scheduled = !empty($schedule_date) ? 1 : 0;
    
    // Handle file upload
    $attachment = '';
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        $filename = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = time() . '_' . $filename;
            $upload_path = 'uploads/' . $new_filename;
            
            // Create uploads directory if not exists
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                $attachment = $new_filename;
            }
        }
    }
    
    // Status is approved for admin posts (unless scheduled)
    $status = $is_scheduled ? 'pending' : 'approved';
    
    $stmt = $pdo->prepare("INSERT INTO notices (title, description, category_id, posted_by, attachment, priority, status, schedule_date, expiry_date, is_scheduled) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$title, $description, $category_id, $_SESSION['user_id'], $attachment, $priority, $status, $schedule_date, $expiry_date, $is_scheduled])) {
        $success = $is_scheduled ? "Notice scheduled successfully!" : "Notice posted successfully!";
    } else {
        $error = "Failed to post notice";
    }
}

// Approve Notice
if (isset($_POST['approve_notice'])) {
    $notice_id = $_POST['notice_id'];
    $stmt = $pdo->prepare("UPDATE notices SET status = 'approved' WHERE notice_id = ?");
    if ($stmt->execute([$notice_id])) {
        $success = "Notice approved successfully!";
    }
}

// Reject Notice
if (isset($_POST['reject_notice'])) {
    $notice_id = $_POST['notice_id'];
    $stmt = $pdo->prepare("UPDATE notices SET status = 'rejected' WHERE notice_id = ?");
    if ($stmt->execute([$notice_id])) {
        $success = "Notice rejected!";
    }
}

// Delete Notice
if (isset($_POST['delete_notice'])) {
    $notice_id = $_POST['notice_id'];
    
    // Get attachment to delete file
    $stmt = $pdo->prepare("SELECT attachment FROM notices WHERE notice_id = ?");
    $stmt->execute([$notice_id]);
    $notice = $stmt->fetch();
    
    if ($notice && $notice['attachment']) {
        $file_path = 'uploads/' . $notice['attachment'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id = ?");
    if ($stmt->execute([$notice_id])) {
        $success = "Notice deleted successfully!";
    }
}

// Add Category
if (isset($_POST['add_category'])) {
    $cat_name = clean($_POST['cat_name']);
    $cat_icon = clean($_POST['cat_icon']);
    
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (cat_name, cat_icon) VALUES (?, ?)");
        if ($stmt->execute([$cat_name, $cat_icon])) {
            $success = "Category added successfully!";
        }
    }
}

// Delete Category
if (isset($_POST['delete_category'])) {
    $cat_id = $_POST['cat_id'];
    
    // Check if category has notices
    $check = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE category_id = ?");
    $check->execute([$cat_id]);
    $count = $check->fetchColumn();
    
    if ($count > 0) {
        $error = "Cannot delete category with existing notices!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE cat_id = ?");
        if ($stmt->execute([$cat_id])) {
            $success = "Category deleted successfully!";
        }
    }
}

// Delete User
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "User deleted successfully!";
        }
    }
}

// ============================================
// Fetch Data
// ============================================

// Get pending faculty
$pending_faculty = $pdo->query("SELECT * FROM users WHERE role = 'faculty' AND status = 'pending' ORDER BY created_at DESC")->fetchAll();

// Get pending notices (including scheduled)
$pending = $pdo->query("SELECT n.*, c.cat_name, u.name as posted_by_name, u.role as poster_role 
                        FROM notices n 
                        LEFT JOIN categories c ON n.category_id = c.cat_id 
                        LEFT JOIN users u ON n.posted_by = u.user_id 
                        WHERE n.status = 'pending' 
                        ORDER BY 
                            CASE WHEN n.is_scheduled = 1 THEN 0 ELSE 1 END,
                            n.schedule_date ASC,
                            n.created_at DESC")->fetchAll();

// Get all notices
$all_notices = $pdo->query("SELECT n.*, c.cat_name, u.name as posted_by_name, u.role as poster_role 
                            FROM notices n 
                            LEFT JOIN categories c ON n.category_id = c.cat_id 
                            LEFT JOIN users u ON n.posted_by = u.user_id 
                            ORDER BY n.created_at DESC")->fetchAll();

// Get notice history (expired notices)
$history = $pdo->query("SELECT * FROM notice_history ORDER BY expiry_year DESC, archived_at DESC")->fetchAll();

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY cat_name")->fetchAll();

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// Get counts - Removed users and categories count
$total_faculty = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn();
$total_notices = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
$total_pending = count($pending);
$total_pending_faculty = count($pending_faculty);
$years = $pdo->query("SELECT DISTINCT expiry_year FROM notice_history ORDER BY expiry_year DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-page">
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php" class="logo">
            <h1 class="college-name-hero">Admin Panel</h1>
        </a>
        <div class="navbar-user">
            <span class="user-badge">
                <i class="fas fa-user-shield"></i> <?php echo $_SESSION['user_name']; ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <!-- Success/Error Messages -->
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards - Removed Users and Categories -->
        <div class="stats-grid">
            <div class="stat-card" onclick="scrollToSection('pending-faculty')">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3><?php echo $total_pending_faculty; ?></h3>
                <p>Faculty Pending</p>
            </div>
            <div class="stat-card" onclick="scrollToSection('pending')">
                <i class="fas fa-clock"></i>
                <h3><?php echo $total_pending; ?></h3>
                <p>Notices Pending</p>
            </div>
            <div class="stat-card" onclick="scrollToSection('post')">
                <i class="fas fa-plus-circle"></i>
                <h3>Post</h3>
                <p>New Notice</p>
            </div>
            <div class="stat-card" onclick="scrollToSection('notices')">
                <i class="fas fa-bullhorn"></i>
                <h3><?php echo $total_notices; ?></h3>
                <p>Total Notices</p>
            </div>
            <div class="stat-card" onclick="scrollToSection('history')">
                <i class="fas fa-history"></i>
                <h3><?php echo count($history); ?></h3>
                <p>History</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="scrollToSection('pending-faculty')">
                <i class="fas fa-chalkboard-teacher"></i> Faculty Approval
            </button>
            <button class="tab" onclick="scrollToSection('post')">
                <i class="fas fa-plus-circle"></i> Post Notice
            </button>
            <button class="tab" onclick="scrollToSection('pending')">
                <i class="fas fa-clock"></i> Pending Approvals
            </button>
            <button class="tab" onclick="scrollToSection('notices')">
                <i class="fas fa-bullhorn"></i> All Notices
            </button>
            <button class="tab" onclick="scrollToSection('history')">
                <i class="fas fa-history"></i> Notice History
            </button>
            <button class="tab" onclick="scrollToSection('categories')">
                <i class="fas fa-tags"></i> Categories
            </button>
            <button class="tab" onclick="scrollToSection('users')">
                <i class="fas fa-users"></i> Users
            </button>
        </div>
        
        <!-- Pending Faculty Panel -->
        <div id="pending-faculty-section" class="content-panel active">
            <div class="panel-header">
                <h3><i class="fas fa-chalkboard-teacher"></i> Faculty Approval Requests</h3>
            </div>
            
            <?php if(count($pending_faculty) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Registration No.</th>
                                <th>Phone</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_faculty as $faculty): ?>
                                <tr>
                                    <td><strong><?php echo $faculty['name']; ?></strong></td>
                                    <td><?php echo $faculty['email']; ?></td>
                                    <td><?php echo $faculty['department']; ?></td>
                                    <td><?php echo $faculty['registration_no'] ?: '-'; ?></td>
                                    <td><?php echo $faculty['phone'] ?: '-'; ?></td>
                                    <td><?php echo date('d M Y', strtotime($faculty['created_at'])); ?></td>
                                    <td>
                                        <div class="action-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="faculty_approval">
                                                <input type="hidden" name="faculty_id" value="<?php echo $faculty['user_id']; ?>">
                                                <input type="hidden" name="status" value="approved">
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this faculty member?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="faculty_approval">
                                                <input type="hidden" name="faculty_id" value="<?php echo $faculty['user_id']; ?>">
                                                <input type="hidden" name="status" value="rejected">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this faculty member?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle"></i>
                    <h3>No pending faculty requests</h3>
                    <p>All faculty accounts have been processed</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Post Notice Panel -->
        <div id="post-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-plus-circle"></i> Post New Notice</h3>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Category *</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['cat_id']; ?>"><?php echo $cat['cat_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-exclamation-triangle"></i> Priority</label>
                            <select name="priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Schedule Date/Time</label>
                            <input type="datetime-local" name="schedule_date" class="form-control">
                            <small>Leave empty to post immediately</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-hourglass-end"></i> Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                            <small>Notice will auto-expire after this date</small>
                        </div>
                    </div>
                    
                    <div class="form-col">
                        <div class="form-group">
                            <label><i class="fas fa-file"></i> Attachment</label>
                            <input type="file" name="attachment" class="form-control">
                            <small>PDF, DOC, JPG, PNG, TXT</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description *</label>
                    <textarea name="description" class="form-control" rows="6" required></textarea>
                </div>
                
                <button type="submit" name="post_notice" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> <?php echo isset($_POST['schedule_date']) && !empty($_POST['schedule_date']) ? 'Schedule Notice' : 'Post Notice'; ?>
                </button>
            </form>
        </div>
        
        <!-- Pending Notices Panel -->
        <div id="pending-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-clock"></i> Notices Pending Approval</h3>
            </div>
            
            <?php if(count($pending) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Posted By</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Priority</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending as $notice): ?>
                                <tr>
                                    <td><strong><?php echo $notice['title']; ?></strong></td>
                                    <td><?php echo $notice['cat_name']; ?></td>
                                    <td><?php echo $notice['posted_by_name']; ?> (<?php echo $notice['poster_role']; ?>)</td>
                                    <td>
                                        <?php if($notice['is_scheduled']): ?>
                                            <span class="badge badge-scheduled">Scheduled</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($notice['is_scheduled']): ?>
                                            <?php echo date('d M Y H:i', strtotime($notice['schedule_date'])); ?>
                                        <?php else: ?>
                                            <?php echo date('d M Y', strtotime($notice['created_at'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($notice['priority'] == 'urgent'): ?>
                                            <span class="badge badge-urgent">Urgent</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e9ecef;">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="notice_id" value="<?php echo $notice['notice_id']; ?>">
                                                <button type="submit" name="approve_notice" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="notice_id" value="<?php echo $notice['notice_id']; ?>">
                                                <button type="submit" name="reject_notice" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle"></i>
                    <h3>No pending notices</h3>
                    <p>All notices have been processed</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- All Notices Panel -->
        <div id="notices-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-bullhorn"></i> All Notices</h3>
            </div>
            
            <?php if(count($all_notices) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Posted By</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($all_notices as $notice): ?>
                                <tr>
                                    <td><strong><?php echo $notice['title']; ?></strong></td>
                                    <td><?php echo $notice['cat_name']; ?></td>
                                    <td><?php echo $notice['posted_by_name']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($notice['created_at'])); ?></td>
                                    <td>
                                        <?php if($notice['status'] == 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif($notice['status'] == 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($notice['priority'] == 'urgent'): ?>
                                            <span class="badge badge-urgent">Urgent</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e9ecef;">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Delete this notice?');">
                                            <input type="hidden" name="notice_id" value="<?php echo $notice['notice_id']; ?>">
                                            <button type="submit" name="delete_notice" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No notices yet</h3>
                    <p>Post your first notice to get started</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- History Panel -->
        <div id="history-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-history"></i> Notice History (Expired Notices)</h3>
            </div>
            
            <?php if(count($history) > 0): ?>
                <?php if(count($years) > 0): ?>
                <div class="year-filter">
                    <label><i class="fas fa-filter"></i> Filter by Year:</label>
                    <div class="year-buttons">
                        <button class="year-btn active" onclick="filterHistory('all')">All</button>
                        <?php foreach($years as $year): ?>
                            <button class="year-btn" onclick="filterHistory('<?php echo $year['expiry_year']; ?>')"><?php echo $year['expiry_year']; ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table id="history-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Posted By</th>
                                <th>Expiry Date</th>
                                <th>Year</th>
                                <th>Priority</th>
                                <th>Archived On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $item): ?>
                                <tr data-year="<?php echo $item['expiry_year']; ?>">
                                    <td><strong><?php echo $item['title']; ?></strong></td>
                                    <td><?php echo $item['category_name']; ?></td>
                                    <td><?php echo $item['posted_by_name']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($item['expiry_date'])); ?></td>
                                    <td><span class="badge" style="background: #e9ecef;"><?php echo $item['expiry_year']; ?></span></td>
                                    <td>
                                        <?php if($item['priority'] == 'urgent'): ?>
                                            <span class="badge badge-urgent">Urgent</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e9ecef;">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($item['archived_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-archive"></i>
                    <h3>No notice history yet</h3>
                    <p>Expired notices will appear here automatically</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Categories Panel -->
        <div id="categories-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-tags"></i> Manage Categories</h3>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-plus-circle"></i> Add New Category
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label>Category Name</label>
                                    <input type="text" name="cat_name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Icon Class</label>
                                    <input type="text" name="cat_icon" class="form-control" value="fa-bullhorn">
                                    <small>Font Awesome icon class (e.g., fa-book, fa-calendar)</small>
                                </div>
                                <button type="submit" name="add_category" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Category
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="form-col">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-list"></i> Existing Categories
                        </div>
                        <div class="card-body">
                            <?php if(count($categories) > 0): ?>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Icon</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($categories as $cat): ?>
                                                <tr>
                                                    <td><?php echo $cat['cat_name']; ?></td>
                                                    <td><i class="fas <?php echo $cat['cat_icon']; ?>"></i></td>
                                                    <td>
                                                        <form method="POST" onsubmit="return confirm('Delete this category?');">
                                                            <input type="hidden" name="cat_id" value="<?php echo $cat['cat_id']; ?>">
                                                            <button type="submit" name="delete_category" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No categories added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Panel -->
        <div id="users-section" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-users"></i> Manage Users</h3>
            </div>
            
            <?php if(count($users) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td><strong><?php echo $user['name']; ?></strong></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td>
                                        <span class="badge" style="background: 
                                            <?php 
                                                if($user['role'] == 'admin') echo '#2a5298';
                                                elseif($user['role'] == 'faculty') echo '#28a745';
                                                else echo '#ffc107';
                                            ?>; color: white;">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['department']; ?></td>
                                    <td>
                                        <?php if($user['status'] == 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif($user['status'] == 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if($user['user_id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this user?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge" style="background: #6c757d; color: white;">Current</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>