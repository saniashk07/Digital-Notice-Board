<?php
require_once 'functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (hasRole('admin')) {
        redirect('admin.php');
    } elseif (hasRole('faculty')) {
        // Check if faculty is approved
        $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user['status'] == 'approved') {
            $_SESSION['faculty_status'] = 'approved';
            redirect('faculty.php');
        } else {
            session_destroy();
            $error = "Your faculty account is pending approval.";
        }
    } else {
        redirect('student.php');
    }
}

// Get counts for hero section
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_faculty = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty' AND status='approved'")->fetchColumn();
$total_notices = $pdo->query("SELECT COUNT(*) FROM notices WHERE status='approved'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMCOE Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Quick fix for missing college-bg.jpg */
        .hero {
            background: linear-gradient(135deg, rgba(128, 0, 0, 0.05), white);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="logo-container">
            <!-- Replace with your college logo -->
            <img src="logo.png" alt="MMCOE Logo" class="college-logo" onerror="this.style.display='none'">
            <div class="logo-text">
                <span class="college-name">MMCOE</span>
                <span class="college-tagline">Marathwada Mitra Mandal's College of Engineering</span>
                <span class="portal-name">Digital Notice Board</span>
            </div>
        </div>
        <div class="nav-links">
            <a href="login.php" class="nav-link login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="nav-link register"><i class="fas fa-user-plus"></i> Register</a>
        </div>
    </div>
    
    <!-- Hero Section -->
    <div class="hero">
        <div class="hero-content">
            <div class="college-hero-logo">
                <!-- Replace with your college logo 
                <img src="logo.png" alt="MMCOE Logo" onerror="this.style.display='none'">-->
            </div>
            <h1 class="hero-title">
                Welcome to <span class="college-name-hero">MMCOE</span>
            </h1>
            <p class="hero-subtitle">
                Marathwada Mitra Mandal's College of Engineering · Digital Notice Board
                <br>
                Stay connected with real-time announcements, events, and important updates.
            </p>
            
            <div class="hero-stats">
                <div class="hero-stat-item">
                    <div class="stat-number"><?php echo $total_students; ?>+</div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="hero-stat-item">
                    <div class="stat-number"><?php echo $total_faculty; ?>+</div>
                    <div class="stat-label">Faculty</div>
                </div>
                <div class="hero-stat-item">
                    <div class="stat-number"><?php echo $total_notices; ?>+</div>
                    <div class="stat-label">Notices</div>
                </div>
            </div>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-bell"></i>
                    <h3>Real-time Updates</h3>
                </div>
                <div class="feature-item">
                    <i class="fas fa-filter"></i>
                    <h3>Smart Filtering</h3>
                </div>
                <div class="feature-item">
                    <i class="fas fa-calendar"></i>
                    <h3>Event Scheduling</h3>
                </div>
                <div class="feature-item">
                    <i class="fas fa-history"></i>
                    <h3>Notice History</h3>
                </div>
            </div>
            
            <div class="hero-buttons">
                <a href="login.php" class="hero-btn primary">
                    <i class="fas fa-sign-in-alt"></i> Get Started
                </a>
                <a href="register.php" class="hero-btn secondary">
                    <i class="fas fa-user-plus"></i> Create Account
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4><i class="fas fa-university"></i> MMCOE</h4>
                <p>Marathwada Mitra Mandal's College of Engineering<br>
                Pune - 411052, Maharashtra, India</p>
                <p><i class="fas fa-phone"></i> +91 20 2543 2100<br>
                <i class="fas fa-envelope"></i> info@mmcoe.edu.in</p>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-link"></i> Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-chevron-right"></i> About College</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Academics</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Departments</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-clock"></i> Portal Info</h4>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-chevron-right"></i> About Portal</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Help & Support</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Terms of Use</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4><i class="fas fa-share-alt"></i> Connect With Us</h4>
                <div style="display: flex; gap: 1rem;">
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-facebook"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-twitter"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-linkedin"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Marathwada Mitra Mandal's College of Engineering. All rights reserved.</p>
            <p style="margin-top: 0.5rem; font-size: 0.8rem;">Digital Notice Board v2.0 | Developed by MMCOE Department of Computer Engineering</p>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>