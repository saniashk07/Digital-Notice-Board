-- Create Database
CREATE DATABASE IF NOT EXISTS notice_board;
USE notice_board;

-- ============================================
-- Table 1: users (with status for faculty approval)
-- ============================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'student') NOT NULL DEFAULT 'student',
    department VARCHAR(50),
    registration_no VARCHAR(50) UNIQUE,
    phone VARCHAR(15),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Table 2: categories
-- ============================================
CREATE TABLE categories (
    cat_id INT PRIMARY KEY AUTO_INCREMENT,
    cat_name VARCHAR(50) NOT NULL,
    cat_icon VARCHAR(50) DEFAULT 'fa-bullhorn',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (cat_name, cat_icon) VALUES
('Academic', 'fa-book'),
('Events', 'fa-calendar'),
('Examinations', 'fa-pencil-alt'),
('Achievements', 'fa-trophy'),
('Holidays', 'fa-umbrella-beach'),
('General', 'fa-bullhorn'),
('Placements', 'fa-briefcase'),
('Sports', 'fa-futbol');

-- ============================================
-- Table 3: notices (with scheduling and expiry)
-- ============================================
CREATE TABLE notices (
    notice_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    category_id INT,
    posted_by INT,
    attachment VARCHAR(255),
    priority ENUM('normal', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    schedule_date DATETIME NULL,
    expiry_date DATE NULL,
    is_scheduled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(cat_id) ON DELETE SET NULL,
    FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL
);


-- ============================================
-- Table 4: notice_history (for expired notices)
-- ============================================
CREATE TABLE notice_history (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    original_notice_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category_name VARCHAR(50),
    posted_by_name VARCHAR(100),
    attachment VARCHAR(255),
    priority VARCHAR(20),
    expiry_year INT,
    expiry_date DATE,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- Table 5: faculty_approvals (for tracking)
-- ============================================
CREATE TABLE faculty_approvals (
    approval_id INT PRIMARY KEY AUTO_INCREMENT,
    faculty_id INT,
    approved_by INT,
    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- ============================================
-- Insert default admin (password: Admin@123)
-- ============================================
INSERT INTO users (name, email, password, role, status) VALUES 
('Administrator', 'admin@noticeboard.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'approved');

-- ============================================
-- Success Message
-- ============================================
SELECT 'Database created successfully!' as 'Message';
SELECT 'Default Admin: admin@noticeboard.com / Admin@123' as 'Info';







-- Add security questions table
CREATE TABLE IF NOT EXISTS security_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    question_text VARCHAR(255) NOT NULL
);

-- Insert default security questions
INSERT INTO security_questions (question_text) VALUES
('What was the name of your first pet?'),
('What was your childhood nickname?'),
('What is your mother\'s maiden name?'),
('What was the name of your elementary school?'),
('What city were you born in?'),
('What is your favorite book?'),
('What is your favorite movie?'),
('What was your first car?'),
('What is the name of your best friend?'),
('What was your dream job as a child?');

-- Add security question fields to users table
ALTER TABLE users 
ADD COLUMN security_question_id INT NULL AFTER phone,
ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question_id,
ADD FOREIGN KEY (security_question_id) REFERENCES security_questions(question_id);