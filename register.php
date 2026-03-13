<?php
require_once 'functions.php';

// If already logged in, redirect
if (isLoggedIn()) {
    redirect('index.php');
}

// Fetch security questions for dropdown
$questions = $pdo->query("SELECT * FROM security_questions ORDER BY question_text")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean($_POST['name']);
    $email = clean($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $role = clean($_POST['role']);
    $department = clean($_POST['department']);
    $security_question_id = clean($_POST['security_question']);
    $security_answer = clean($_POST['security_answer']);
    
    // Handle optional fields - set to NULL instead of empty string
    $registration_no = !empty($_POST['registration_no']) ? clean($_POST['registration_no']) : null;
    $phone = !empty($_POST['phone']) ? clean($_POST['phone']) : null;
    $admin_code = clean($_POST['admin_code'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm) $errors[] = "Passwords do not match";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    if (empty($security_question_id)) $errors[] = "Please select a security question";
    if (empty($security_answer)) $errors[] = "Security answer is required";
    
    // Email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Admin code check
    if ($role == 'admin' && $admin_code !== 'ADMIN@2024') {
        $errors[] = "Invalid admin registration code";
    }
    
    // Check if email exists
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $errors[] = "Email already registered";
        }
    }
    
    // If registration number is provided, check if it's unique
    if (!empty($registration_no)) {
        $check_reg = $pdo->prepare("SELECT user_id FROM users WHERE registration_no = ?");
        $check_reg->execute([$registration_no]);
        if ($check_reg->rowCount() > 0) {
            $errors[] = "Registration number already exists";
        }
    }
    
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $hashed_answer = password_hash(strtolower(trim($security_answer)), PASSWORD_DEFAULT);
        
        // Set status: admin & students auto-approved, faculty pending
        $status = ($role == 'faculty') ? 'pending' : 'approved';
        
        $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, department, registration_no, phone, status, security_question_id, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($insert->execute([$name, $email, $hashed, $role, $department, $registration_no, $phone, $status, $security_question_id, $hashed_answer])) {
            if ($role == 'faculty') {
                $success = "Registration successful! Your faculty account is pending admin approval.";
            } else {
                $success = "Registration successful! You can now login.";
            }
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Digital Notice Board</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="register-page">
    <div class="auth-container">
        <div class="auth-card" style="max-width: 700px;">
            <div class="auth-header">
                <i class="fas fa-user-plus"></i>
                <h2>Create Account</h2>
                <p>Join the Digital Notice Board</p>
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
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div><strong>Note:</strong> Faculty accounts require admin approval.</div>
                </div>
                
                <form method="POST" onsubmit="return validateForm()">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name *</label>
                                <input type="text" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email *</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password *</label>
                                <input type="password" name="password" class="form-control" required id="password">
                                <small>Minimum 6 characters</small>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-check-circle"></i> Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required id="confirm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Register As *</label>
                                <select name="role" class="form-control" required id="role" onchange="toggleAdminField()">
                                    <option value="">Select Role</option>
                                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                                    <option value="faculty" <?php echo (isset($_POST['role']) && $_POST['role'] == 'faculty') ? 'selected' : ''; ?>>Faculty</option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Department *</label>
                                <select name="department" class="form-control" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Information Technology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Mechanical" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                                    <option value="Civil" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                    <option value="Electrical" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Question Section - Just added one field row -->
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-question-circle"></i> Security Question *</label>
                                <select name="security_question" class="form-control" required>
                                    <option value="">Select a security question</option>
                                    <?php foreach($questions as $q): ?>
                                        <option value="<?php echo $q['question_id']; ?>" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == $q['question_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($q['question_text']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Your Answer *</label>
                                <input type="text" name="security_answer" class="form-control" required value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>">
                                <small>For password recovery</small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="admin-field" style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'display: block;' : 'display: none;'; ?>">
                        <div class="secret-field">
                            <div class="form-group">
                                <label><i class="fas fa-key"></i> Admin Secret Code</label>
                                <input type="password" name="admin_code" class="form-control" placeholder="Enter admin code" value="<?php echo isset($_POST['admin_code']) ? htmlspecialchars($_POST['admin_code']) : ''; ?>">
                                <small>Use code: ADMIN@2024</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-id-card"></i> ID Number (Optional)</label>
                                <input type="text" name="registration_no" class="form-control" value="<?php echo isset($_POST['registration_no']) ? htmlspecialchars($_POST['registration_no']) : ''; ?>">
                                <small>Leave empty if not applicable</small>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone (Optional)</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 20px;">
                    Already have an account? <a href="login.php" style="color: #2a5298; font-weight: 600;">Login here</a>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>