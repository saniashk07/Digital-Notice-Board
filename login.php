<?php
require_once 'functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    if (hasRole('admin')) {
        redirect('admin.php');
    } elseif (hasRole('faculty')) {
        redirect('faculty.php');
    } else {
        redirect('student.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if faculty is approved
            if ($user['role'] == 'faculty' && $user['status'] != 'approved') {
                $error = "Your faculty account is pending approval.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                
                if ($user['role'] == 'admin') {
                    redirect('admin.php');
                } elseif ($user['role'] == 'faculty') {
                    $_SESSION['faculty_status'] = $user['status'];
                    redirect('faculty.php');
                } else {
                    redirect('student.php');
                }
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <i class="fas fa-bullhorn"></i>
                <h2>Welcome Back!</h2>
                <p>Sign in to access your dashboard</p>
            </div>
            
            <div class="auth-body">
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <!-- Forgot Password Link -->
                    <div style="text-align: right; margin-bottom: 15px;">
                        <a href="forgot_password.php" style="color: #2a5298; font-size: 0.9rem; text-decoration: none;">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    Don't have an account? <a href="register.php" style="color: #2a5298; font-weight: 600;">Register here</a>
                </div>
                
                <div style="text-align: center; margin-top: 15px;">
                    <a href="index.php" style="color: #666;"><i class="fas fa-home"></i> Back to Home</a>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>