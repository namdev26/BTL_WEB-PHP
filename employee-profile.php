<?php
session_start();

// Kiểm tra đăng nhập nhân viên
if (!isset($_SESSION['employee_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Lấy thông tin nhân viên
$employee_id = $_SESSION['employee_id'];
$employee_code = $_SESSION['employee_code'];
$employee_name = $_SESSION['employee_name'];
$employee_department = $_SESSION['employee_department'];
$employee_position = $_SESSION['employee_position'];

// Lấy thông tin chi tiết nhân viên
$employee_details = [];
$details_query = "SELECT * FROM employees WHERE id = ?";
$details_stmt = $conn->prepare($details_query);
$details_stmt->bind_param("i", $employee_id);
$details_stmt->execute();
$details_result = $details_stmt->get_result();

if ($details_result->num_rows > 0) {
    $employee_details = $details_result->fetch_assoc();
}

// Xử lý cập nhật thông tin
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Lấy dữ liệu từ form
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $city = $_POST['city'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($full_name) || empty($email)) {
        $error_message = 'Vui lòng điền đầy đủ họ tên và email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email không hợp lệ';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error_message = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error_message = 'Mật khẩu xác nhận không khớp';
    } elseif (!empty($new_password) && empty($current_password)) {
        $error_message = 'Vui lòng nhập mật khẩu hiện tại để thay đổi mật khẩu';
    } else {
        // Kiểm tra mật khẩu hiện tại nếu muốn đổi mật khẩu
        $password_verified = true;
        
        if (!empty($new_password)) {
            // Kiểm tra mật khẩu hiện tại
            $password_verified = password_verify($current_password, $employee_details['password']);
            
            if (!$password_verified) {
                $error_message = 'Mật khẩu hiện tại không đúng';
            }
        }
        
        if (empty($error_message)) {
            // Cập nhật thông tin
            if (!empty($new_password)) {
                // Cập nhật cả mật khẩu
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE employees SET 
                    full_name = ?, 
                    email = ?, 
                    birthdate = ?, 
                    gender = ?, 
                    city = ?,
                    password = ?
                    WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssssi", $full_name, $email, $birthdate, $gender, $city, $hashed_password, $employee_id);
            } else {
                // Chỉ cập nhật thông tin cơ bản
                $update_query = "UPDATE employees SET 
                    full_name = ?, 
                    email = ?, 
                    birthdate = ?, 
                    gender = ?, 
                    city = ?
                    WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssi", $full_name, $email, $birthdate, $gender, $city, $employee_id);
            }
            
            if ($update_stmt->execute()) {
                $success_message = 'Cập nhật thông tin thành công!';
                
                // Cập nhật session
                $_SESSION['employee_name'] = $full_name;
                
                // Ghi log hoạt động
                $check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
                if ($check_activities->num_rows > 0) {
                    $action = 'update_profile';
                    $description = 'Cập nhật thông tin cá nhân';
                    $user_id = $employee_code;
                    
                    $log_activity = $conn->prepare("INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)");
                    $log_activity->bind_param("sss", $user_id, $action, $description);
                    $log_activity->execute();
                }
                
                // Cập nhật lại thông tin chi tiết
                $details_stmt->execute();
                $details_result = $details_stmt->get_result();
                if ($details_result->num_rows > 0) {
                    $employee_details = $details_result->fetch_assoc();
                }
            } else {
                $error_message = 'Đã xảy ra lỗi: ' . $update_stmt->error;
            }
        }
    }
}








//HTML


// Lấy danh sách thành phố
$cities = ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ', 'Nha Trang', 'Huế', 'Khác'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Cá Nhân - <?php echo htmlspecialchars($employee_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/employee-profile.css">
</head>
<body>
    <div class="employee-dashboard">
        <!-- Header -->
        <header class="employee-header">
            <div class="employee-logo">
                <i class="fas fa-chart-line"></i>
                <h1>Hệ Thống Quản Lý Nhân Viên</h1>
            </div>
            <div class="employee-user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($employee_name); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars($employee_position); ?></span>
                </div>
                <a href="logout.php" class="logout" title="Đăng xuất">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>
        
        <!-- Main Content -->
        <div class="employee-content">
            <!-- Sidebar -->
            <div class="employee-sidebar">
                <ul>
                    <li>
                        <a href="employee-dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Trang chủ</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="employee-profile.php">
                            <i class="fas fa-user"></i>
                            <span>Thông tin cá nhân</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-salary.php">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Lương & Thưởng</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-attendance.php">
                            <i class="fas fa-calendar-check"></i>
                            <span>Chấm công</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-leave.php">
                            <i class="fas fa-calendar-minus"></i>
                            <span>Nghỉ phép</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-documents.php">
                            <i class="fas fa-file-alt"></i>
                            <span>Tài liệu</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Cài đặt</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Main Content Area -->
            <div class="employee-main">
                <h1 class="page-title">Thông Tin Cá Nhân</h1>
                
                <div class="profile-container">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p><?php echo htmlspecialchars($employee_position); ?> - <?php echo htmlspecialchars($employee_department); ?></p>
                            <span class="employee-id"><?php echo htmlspecialchars($employee_code); ?></span>
                        </div>
                    </div>
                    
                    <form class="profile-form" method="POST" action="employee-profile.php">
                        <div class="form-section">
                            <h3><i class="fas fa-user-circle"></i> Thông tin cơ bản</h3>
                            
                            <div class="form-group">
                                <label for="full_name" class="required">Họ và tên</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($employee_details['full_name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="required">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee_details['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Tên đăng nhập</label>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($employee_details['username'] ?? ''); ?>" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Thông tin khác</h3>
                            
                            <div class="form-group">
                                <label for="birthdate">Ngày sinh</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($employee_details['birthdate'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Giới tính</label>
                                <select id="gender" name="gender">
                                    <option value="Nam" <?php echo ($employee_details['gender'] ?? '') === 'Nam' ? 'selected' : ''; ?>>Nam</option>
                                    <option value="Nữ" <?php echo ($employee_details['gender'] ?? '') === 'Nữ' ? 'selected' : ''; ?>>Nữ</option>
                                    <option value="Khác" <?php echo ($employee_details['gender'] ?? '') === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="city">Thành phố</label>
                                <select id="city" name="city">
                                    <option value="">-- Chọn thành phố --</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo $city; ?>" <?php echo ($employee_details['city'] ?? '') === $city ? 'selected' : ''; ?>>
                                            <?php echo $city; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="password-section">
                            <h3><i class="fas fa-lock"></i> Thay đổi mật khẩu</h3>
                            <p>Chỉ điền thông tin này nếu bạn muốn thay đổi mật khẩu.</p>
                            
                            <div class="form-group">
                                <label for="current_password">Mật khẩu hiện tại</label>
                                <div class="password-toggle">
                                    <input type="password" id="current_password" name="current_password">
                                    <span class="toggle-icon" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Mật khẩu mới</label>
                                <div class="password-toggle">
                                    <input type="password" id="new_password" name="new_password">
                                    <span class="toggle-icon" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Xác nhận mật khẩu mới</label>
                                <div class="password-toggle">
                                    <input type="password" id="confirm_password" name="confirm_password">
                                    <span class="toggle-icon" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="employee-dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
