<?php
require_once 'functions.php';

// Check if user is logged in and is faculty
if (!isLoggedIn() || $_SESSION['user_role'] != 'faculty') {
    redirect('index.php');
}

// Check if faculty is approved
$check_status = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
$check_status->execute([$_SESSION['user_id']]);
$faculty_data = $check_status->fetch();

if ($faculty_data['status'] != 'approved') {
    session_destroy();
    redirect('index.php?error=pending');
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle new notice submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'post_notice') {
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
                
                if (!file_exists('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    $attachment = $new_filename;
                }
            }
        }
        
        // Insert notice (status = 'pending' for admin approval)
        $stmt = $pdo->prepare("INSERT INTO notices (title, description, category_id, posted_by, attachment, priority, status, schedule_date, expiry_date, is_scheduled) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        
        if ($stmt->execute([$title, $description, $category_id, $user_id, $attachment, $priority, $schedule_date, $expiry_date, $is_scheduled])) {
            if ($is_scheduled) {
                $success = "Notice scheduled successfully! It will be posted on " . date('d M Y H:i', strtotime($schedule_date));
            } else {
                $success = "Notice submitted successfully! Waiting for admin approval.";
            }
        } else {
            $error = "Failed to post notice. Please try again.";
        }
    }
    
    // Handle notice deletion
    if ($_POST['action'] == 'delete_notice') {
        $notice_id = clean($_POST['notice_id']);
        
        // Get attachment to delete file
        $stmt = $pdo->prepare("SELECT attachment FROM notices WHERE notice_id = ? AND posted_by = ?");
        $stmt->execute([$notice_id, $user_id]);
        $notice = $stmt->fetch();
        
        if ($notice && $notice['attachment']) {
            $file_path = 'uploads/' . $notice['attachment'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete notice
        $stmt = $pdo->prepare("DELETE FROM notices WHERE notice_id = ? AND posted_by = ?");
        if ($stmt->execute([$notice_id, $user_id])) {
            $success = "Notice deleted successfully!";
        }
    }
}

// Get faculty's notices (including scheduled)
$stmt = $pdo->prepare("SELECT n.*, c.cat_name, 
                       CASE 
                           WHEN n.is_scheduled = 1 AND n.schedule_date > NOW() THEN 'scheduled'
                           WHEN n.status = 'pending' THEN 'pending'
                           WHEN n.status = 'approved' THEN 'approved'
                           WHEN n.status = 'rejected' THEN 'rejected'
                       END as display_status
                       FROM notices n 
                       LEFT JOIN categories c ON n.category_id = c.cat_id 
                       WHERE n.posted_by = ? 
                       ORDER BY 
                           CASE 
                               WHEN n.is_scheduled = 1 AND n.schedule_date > NOW() THEN 0 
                               ELSE 1 
                           END,
                           n.schedule_date ASC,
                           n.created_at DESC");
$stmt->execute([$user_id]);
$my_notices = $stmt->fetchAll();

// Get faculty's notice history (expired notices)
$history = $pdo->prepare("SELECT * FROM notice_history WHERE posted_by_name = (SELECT name FROM users WHERE user_id = ?) ORDER BY expiry_year DESC, archived_at DESC");
$history->execute([$user_id]);
$my_history = $history->fetchAll();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY cat_name")->fetchAll();

// Count notices by status
$pending_count = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE posted_by = ? AND status = 'pending' AND (is_scheduled = 0 OR (is_scheduled = 1 AND schedule_date <= NOW()))");
$pending_count->execute([$user_id]);
$pending = $pending_count->fetchColumn();

$scheduled_count = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE posted_by = ? AND is_scheduled = 1 AND schedule_date > NOW()");
$scheduled_count->execute([$user_id]);
$scheduled = $scheduled_count->fetchColumn();

$approved_count = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE posted_by = ? AND status = 'approved'");
$approved_count->execute([$user_id]);
$approved = $approved_count->fetchColumn();

$rejected_count = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE posted_by = ? AND status = 'rejected'");
$rejected_count->execute([$user_id]);
$rejected = $rejected_count->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="faculty-page">
    <!-- Navbar -->
    <div class="navbar">
        <a href="index.php" class="logo">
            <h1 class="college-name-hero">Faculty Panel</h1>
        </a>
        <div class="navbar-user">
            <span class="user-badge">
                <i class="fas fa-chalkboard-teacher"></i> <?php echo $_SESSION['user_name']; ?>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="container">
        <!-- Welcome Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-graduate"></i> Welcome, <?php echo $_SESSION['user_name']; ?>!
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-col">
                        <p><i class="fas fa-check-circle" style="color: #28a745;"></i> Your faculty account is approved</p>
                    </div>
                    <div class="form-col" style="text-align: right;">
                        <div class="stats" style="display: flex; gap: 20px; justify-content: flex-end;">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $scheduled; ?></div>
                                <div class="stat-label">Scheduled</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $pending; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $approved; ?></div>
                                <div class="stat-label">Approved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $rejected; ?></div>
                                <div class="stat-label">Rejected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
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
        
        <!-- Info Message -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Note:</strong> Regular notices need admin approval. Scheduled notices will be sent for approval on the scheduled date.
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('post')">
                <i class="fas fa-plus-circle"></i> Post Notice
            </button>
            <button class="tab" onclick="showTab('my')">
                <i class="fas fa-list"></i> My Notices
            </button>
            <button class="tab" onclick="showTab('history')">
                <i class="fas fa-history"></i> History
            </button>
        </div>
        
        <!-- Post Notice Panel -->
        <div id="post-panel" class="content-panel active">
            <div class="panel-header">
                <h3><i class="fas fa-plus-circle"></i> Post New Notice</h3>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="post_notice">
                
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
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Notice
                </button>
            </form>
        </div>
        
        <!-- My Notices Panel -->
        <div id="my-panel" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-list"></i> My Notices</h3>
            </div>
            
            <?php if(count($my_notices) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Posted On</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Attachment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($my_notices as $notice): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($notice['title']); ?></strong></td>
                                    <td><?php echo $notice['cat_name'] ?: 'Uncategorized'; ?></td>
                                    <td>
                                        <?php if($notice['is_scheduled'] && strtotime($notice['schedule_date']) > time()): ?>
                                            <i class="fas fa-clock" style="color: #004085;"></i> 
                                            <?php echo date('d M Y H:i', strtotime($notice['schedule_date'])); ?>
                                        <?php else: ?>
                                            <?php echo date('d M Y', strtotime($notice['created_at'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($notice['is_scheduled'] && strtotime($notice['schedule_date']) > time()): ?>
                                            <span class="badge badge-scheduled">Scheduled</span>
                                        <?php elseif($notice['status'] == 'pending'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif($notice['status'] == 'approved'): ?>
                                            <span class="badge badge-approved">Approved</span>
                                        <?php elseif($notice['status'] == 'rejected'): ?>
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
                                        <?php if($notice['attachment']): ?>
                                            <a href="uploads/<?php echo $notice['attachment']; ?>" class="attachment-link" target="_blank">
                                                <i class="fas fa-paperclip"></i> View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this notice?');">
                                            <input type="hidden" name="action" value="delete_notice">
                                            <input type="hidden" name="notice_id" value="<?php echo $notice['notice_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
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
                    <p>Post your first notice using the Post Notice tab</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- History Panel -->
        <div id="history-panel" class="content-panel">
            <div class="panel-header">
                <h3><i class="fas fa-history"></i> Notice History (Expired Notices)</h3>
            </div>
            
            <?php 
            // Get distinct years from history
            $years = $pdo->prepare("SELECT DISTINCT expiry_year FROM notice_history WHERE posted_by_name = (SELECT name FROM users WHERE user_id = ?) ORDER BY expiry_year DESC");
            $years->execute([$user_id]);
            $history_years = $years->fetchAll();
            ?>
            
            <?php if(count($my_history) > 0): ?>
                <?php if(count($history_years) > 0): ?>
                <div class="year-filter">
                    <label><i class="fas fa-filter"></i> Filter by Year:</label>
                    <div class="year-buttons">
                        <button class="year-btn active" onclick="filterHistory('all')">All</button>
                        <?php foreach($history_years as $year): ?>
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
                                <th>Expiry Date</th>
                                <th>Year</th>
                                <th>Priority</th>
                                <th>Archived On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($my_history as $item): ?>
                                <tr data-year="<?php echo $item['expiry_year']; ?>">
                                    <td><strong><?php echo $item['title']; ?></strong></td>
                                    <td><?php echo $item['category_name']; ?></td>
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
    </div>

    <script src="script.js"></script>
</body>
</html>