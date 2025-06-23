<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';  // Default XAMPP username
$db_pass = '';      // Default XAMPP password (empty)
$db_name = 'admin_dashboard';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_name);

// Create users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Create salary_records table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS salary_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    salary_year INT NOT NULL,
    salary_month INT NOT NULL,
    total_salary DECIMAL(15,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Đảm bảo bảng salary_records có cột salary_month (tránh lỗi khi bảng đã tồn tại nhưng thiếu cột này)
$check_column = $conn->query("SHOW COLUMNS FROM salary_records LIKE 'salary_month'");
if ($check_column && $check_column->num_rows == 0) {
    $alter_sql = "ALTER TABLE salary_records ADD COLUMN salary_month INT NOT NULL AFTER salary_year";
    if ($conn->query($alter_sql) !== TRUE) {
        die("Error adding salary_month column: " . $conn->error);
    }
}

// Tạo bảng notifications nếu chưa tồn tại
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    recipient_type VARCHAR(20) NOT NULL,
    sender_id INT NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Create employees table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS employees (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    birthdate DATE,
    gender ENUM('Nam', 'Nữ', 'Khác') DEFAULT 'Nam',
    city VARCHAR(100),
    department VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL,
    salary DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) !== TRUE) {
    die("Error creating table: " . $conn->error);
}

// Tạo bảng activities 
$create_activities_table = "CREATE TABLE IF NOT EXISTS activities (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    action VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
// Insert default admin user if it doesn't exist
$admin_username = 'admin';
$admin_email = 'admin@example.com';
$admin_password = password_hash('123456`', PASSWORD_DEFAULT); // Hash the password

// Check if admin user exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $admin_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Admin user doesn't exist, insert it
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
    $stmt->bind_param("sss", $admin_username, $admin_email, $admin_password);
    $stmt->execute();
}


// Tạo bảng lưu thông tin nghỉ phép
$create_leave_table = "CREATE TABLE IF NOT EXISTS employee_leaves (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL,
    leave_type ENUM('annual', 'sick', 'unpaid', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT(11) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approved_by VARCHAR(50) DEFAULT NULL,
    approved_date DATETIME DEFAULT NULL,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";

// Tạo bảng lưu thông tin số ngày nghỉ phép của nhân viên
$create_leave_balance_table = "CREATE TABLE IF NOT EXISTS leave_balances (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) NOT NULL,
    year INT(4) NOT NULL,
    annual_leave_total INT(11) NOT NULL DEFAULT 12,
    annual_leave_used INT(11) NOT NULL DEFAULT 0,
    sick_leave_total INT(11) NOT NULL DEFAULT 30,
    sick_leave_used INT(11) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (employee_id, year),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
)";
// Tạo bảng leave_requests nếu chưa tồn tại
$create_leave_table = "CREATE TABLE IF NOT EXISTS leave_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    department VARCHAR(255) NOT NULL,
    position VARCHAR(255) NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT(11) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (employee_id),
    INDEX (status)
)";

$conn->query($create_leave_table);

// Tạo bảng notifications nếu chưa tồn tại
$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT(11) NOT NULL,
    recipient_type ENUM('admin', 'employee') NOT NULL,
    sender_id INT(11) NOT NULL,
    sender_name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT(11),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (recipient_id),
    INDEX (recipient_type),
    INDEX (is_read)
)";



$stmt->close();
?>
