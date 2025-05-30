<?php
session_start();

// Include database connection
require_once 'config/db.php';

// Initialize variables
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu';
    } else {
        // Kiểm tra trong bảng users trước
        $user_found = false;
        $stmt = $conn->prepare("SELECT id, username, password, 'admin' as role FROM users WHERE username = ?");
        
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    $user_found = true;
                    // Password is correct, create session
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_role'] = $user['role'];
                    
                    // Set cookie if remember me is checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/');
                    }
                    
                    // Ghi log hoạt động đăng nhập
                    $check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
                    if ($check_activities->num_rows > 0) {
                        $action = 'login';
                        $description = 'Đăng nhập vào hệ thống quản lý';
                        $user_id = $user['id'];
                        
                        $log_activity = $conn->prepare("INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)");
                        $log_activity->bind_param("sss", $user_id, $action, $description);
                        $log_activity->execute();
                    }
                    
                    header('Location: index.php');
                    exit;
                }
            }
            
            // Đóng statement sau khi sử dụng xong
            $stmt->close();
        }
        
        // Nếu không tìm thấy trong bảng users, kiểm tra trong bảng employees
        if (!$user_found) {
            // Kiểm tra xem bảng employees có tồn tại không
            $check_table = $conn->query("SHOW TABLES LIKE 'employees'");
            if ($check_table->num_rows > 0) {
                // Kiểm tra xem cột username và password có tồn tại không
                $check_columns = $conn->query("SHOW COLUMNS FROM employees WHERE Field IN ('username', 'password')");
                if ($check_columns->num_rows == 2) {
                    // Tìm kiếm nhân viên với username tương ứng
                    $emp_stmt = $conn->prepare("SELECT id, employee_id, full_name, username, password, department, position FROM employees WHERE username = ? AND status = 'active'");
                    $emp_stmt->bind_param("s", $username);
                    $emp_stmt->execute();
                    $emp_result = $emp_stmt->get_result();
                    
                    if ($emp_result->num_rows === 1) {
                        $employee = $emp_result->fetch_assoc();
                        
                        // Xác thực mật khẩu
                        if (password_verify($password, $employee['password'])) {
                            // Mật khẩu đúng, tạo phiên
                            $_SESSION['employee_logged_in'] = true;
                            $_SESSION['employee_username'] = $employee['username'];
                            $_SESSION['employee_id'] = $employee['id'];
                            $_SESSION['employee_code'] = $employee['employee_id'];
                            $_SESSION['employee_name'] = $employee['full_name'];
                            $_SESSION['employee_department'] = $employee['department'];
                            $_SESSION['employee_position'] = $employee['position'];
                            $_SESSION['user_role'] = 'employee';
                            
                            // Ghi nhớ đăng nhập nếu được chọn
                            if ($remember) {
                                $token = bin2hex(random_bytes(32));
                                setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/');
                            }
                            
                            // Ghi log hoạt động đăng nhập
                            $check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
                            if ($check_activities->num_rows > 0) {
                                $action = 'login';
                                $description = 'Nhân viên ' . $employee['full_name'] . ' đăng nhập vào hệ thống';
                                $user_id = $employee['employee_id'];
                                
                                $log_activity = $conn->prepare("INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)");
                                $log_activity->bind_param("sss", $user_id, $action, $description);
                                $log_activity->execute();
                            }
                            
                            // Chuyển hướng đến trang dành cho nhân viên
                            header('Location: employee-dashboard.php');
                            exit;
                        }
                    }
                    $emp_stmt->close();
                }
            }
            
            // Nếu vẫn không tìm thấy hoặc mật khẩu không đúng
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng';
        }
    }
}




















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Quản Lý</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-chart-line"></i>
            <h1>Hệ Thống Quản Lý</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Tên đăng nhập</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Ghi nhớ đăng nhập</label>
            </div>
            
            <button type="submit">Đăng Nhập</button>
        </form>
        
        <div class="register-link" style="text-align: center; margin-top: 20px; font-size: 14px; color: #555;">
            Chưa có tài khoản? <a href="register.php" style="color: #3498db; text-decoration: none;">Đăng ký ngay</a>
        </div>
    </div>
</body>
</html>
