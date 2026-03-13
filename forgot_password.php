<?php
require_once 'functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$show_question = false;
$user = null;

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_email'])) {
    $email = clean($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        $stmt = $pdo->prepare("SELECT u.*, sq.question_text FROM users u 
                               LEFT JOIN security_questions sq ON u.security_question_id = sq.question_id 
                               WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $show_question = true;
        } else {
            $error = "Email address not found in our records";
        }
    }
}

// Handle answer verification and password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $email = clean($_POST['email']);
    $answer = clean($_POST['security_answer']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($answer)) {
        $error = "Please answer the security question";
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Get user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify(strtolower(trim($answer)), $user['security_answer'])) {
            // Answer correct - update password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            
            if ($update->execute([$hashed, $email])) {
                $success = "Password reset successfully! You can now login with your new password.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = "Incorrect answer to security question";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="auth-container">
        <div class="auth-card" style="max-width: 500px;">
            <div class="auth-header">
                <i class="fas fa-key"></i>
                <h2>Forgot Password</h2>
                <p>Reset your password using security question</p>
            </div>
            
            <div class="auth-body">
                <?php if($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <br><br>
                        <a href="login.php" style="color: #2e7d32; font-weight: bold;">Click here to login</a>
                    </div>
                <?php else: ?>
                
                <?php if(!$show_question): ?>
                    <!-- Step 1: Enter Email -->
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" required placeholder="Enter your registered email">
                        </div>
                        
                        <button type="submit" name="check_email" class="btn btn-primary btn-block">
                            <i class="fas fa-arrow-right"></i> Continue
                        </button>
                    </form>
                    
                <?php else: ?>
                    <!-- Step 2: Answer Question & Reset Password -->
                    <form method="POST">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        
                        <div class="info-box" style="margin-bottom: 20px;">
                            <i class="fas fa-user"></i>
                            <div>
                                <strong>Account:</strong> <?php echo htmlspecialchars($user['email']); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-question-circle"></i> Security Question</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['question_text']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Your Answer *</label>
                            <input type="text" name="security_answer" class="form-control" required placeholder="Enter your answer">
                        </div>
                        
                        <hr style="margin: 20px 0; border-color: #e0e0e0;">
                        
                        <div class="form-group">
                            <label><i class="fas fa-key"></i> New Password *</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Enter new password" minlength="6">
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-check-circle"></i> Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                        </div>
                        
                        <button type="submit" name="reset_password" class="btn btn-primary btn-block">
                            <i class="fas fa-sync-alt"></i> Reset Password
                        </button>
                    </form>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" style="color: #666;"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>