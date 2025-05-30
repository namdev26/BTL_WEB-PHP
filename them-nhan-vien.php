<?php
session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config/db.php';

// Initialize variables
$error = '';
$success = '';
$full_name = '';
$username = '';
$email = '';
$birthdate = '';
$gender = 'Nam';
$city = '';
$department = '';
$position = '';
$salary = 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = $_POST['full_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_POST['email'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $gender = $_POST['gender'] ?? 'Nam';
    $city = $_POST['city'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    $salary = $_POST['salary'] ?? 0;
    
    // Validate input
    if (empty($full_name) || empty($username) || empty($password) || empty($confirm_password) || 
        empty($email) || empty($department) || empty($position)) {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } else {
        // Kiểm tra xem bảng employees đã tồn tại chưa
        $check_table = $conn->query("SHOW TABLES LIKE 'employees'");
        
        
        // Kiểm tra cấu trúc bảng employees
        $check_username_column = $conn->query("SHOW COLUMNS FROM employees LIKE 'username'");
        if ($check_username_column->num_rows == 0) {
            // Thêm cột username nếu chưa tồn tại
            $alter_query = "ALTER TABLE employees 
                ADD COLUMN username VARCHAR(50) NOT NULL AFTER full_name,
                ADD COLUMN password VARCHAR(255) NOT NULL AFTER username,
                ADD COLUMN birthdate DATE AFTER email,
                ADD COLUMN gender ENUM('Nam', 'Nữ', 'Khác') DEFAULT 'Nam' AFTER birthdate,
                ADD COLUMN city VARCHAR(100) AFTER gender";
            $conn->query($alter_query);
            
            // Thêm ràng buộc UNIQUE cho username
            $conn->query("ALTER TABLE employees ADD UNIQUE (username)");
        }
        
        // Kiểm tra cột salary
        $check_salary_column = $conn->query("SHOW COLUMNS FROM employees LIKE 'salary'");
        if ($check_salary_column->num_rows == 0) {
            // Thêm cột salary nếu chưa tồn tại
            $conn->query("ALTER TABLE employees ADD COLUMN salary DECIMAL(15,2) DEFAULT 0 AFTER position");
        }
        
        // Kiểm tra xem username đã tồn tại chưa
        $check_username = $conn->prepare("SELECT id FROM employees WHERE username = ?");
        if ($check_username === false) {
            $error = 'Lỗi truy vấn: ' . $conn->error;
        } else {
            $check_username->bind_param("s", $username);
            $check_username->execute();
            $result = $check_username->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Tên đăng nhập đã tồn tại';
                $check_username->close();
            } else {
                $check_username->close();
                
                // Kiểm tra xem email đã tồn tại chưa
                $check_email = $conn->prepare("SELECT id FROM employees WHERE email = ?");
                if ($check_email === false) {
                    $error = 'Lỗi truy vấn: ' . $conn->error;
                } else {
                    $check_email->bind_param("s", $email);
                    $check_email->execute();
                    $result = $check_email->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = 'Email đã được sử dụng';
                        $check_email->close();
                    } else {
                        $check_email->close();
                        
                        // Generate employee ID
                        $id_query = $conn->query("SELECT MAX(CAST(SUBSTRING(employee_id, 3) AS UNSIGNED)) as max_id FROM employees WHERE employee_id LIKE 'NV%'");
                        if ($id_query) {
                            $result = $id_query->fetch_assoc();
                            $next_id = ($result['max_id'] ?? 0) + 1;
                        } else {
                            $next_id = 1;
                        }
                        $employee_id = "NV" . str_pad($next_id, 3, '0', STR_PAD_LEFT);
                        
                        // Hash the password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new employee
                        $insert = $conn->prepare("INSERT INTO employees (employee_id, full_name, username, password, email, birthdate, gender, city, department, position, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        
                        if ($insert === false) {
                            $error = 'Lỗi khi chuẩn bị truy vấn: ' . $conn->error;
                        } else {
                            $insert->bind_param("ssssssssssd", $employee_id, $full_name, $username, $hashed_password, $email, $birthdate, $gender, $city, $department, $position, $salary);
                            
                            if ($insert->execute()) {
                                $success = 'Thêm nhân viên thành công';
                                
                                // Ghi lại hoạt động thêm nhân viên
                                $activity_query = "INSERT INTO activities (user_id, action, description, created_at) 
                                                 VALUES (?, 'add_employee', ?, NOW())"; 
                                $activity_stmt = $conn->prepare($activity_query);
                                $activity_description = "Thêm nhân viên mới: {$full_name} (Mã NV: {$employee_id})";
                                $activity_stmt->bind_param("ss", $username, $activity_description);
                                $activity_stmt->execute();
                                $activity_stmt->close();
                                
                                // Reset form
                                $full_name = '';
                                $username = '';
                                $email = '';
                                $birthdate = '';
                                $gender = 'Nam';
                                $city = '';
                                $department = '';
                                $position = '';
                                $salary = 0;
                            } else {
                                $error = 'Đã xảy ra lỗi: ' . $insert->error;
                            }
                            
                            $insert->close();
                        }
                    }
                }
            }
        }
    }
}

// Get list of departments for dropdown
$departments = ['Phòng IT', 'Phòng Marketing', 'Phòng Nhân sự', 'Phòng Tài chính', 'Phòng Kinh doanh'];

// Get list of positions for dropdown
$positions = [
    'Phòng IT' => ['Lập trình viên', 'Quản trị hệ thống', 'Tester', 'Trưởng phòng IT'],
    'Phòng Marketing' => ['Marketing Executive', 'Content Creator', 'SEO Specialist', 'Trưởng phòng Marketing'],
    'Phòng Nhân sự' => ['HR Executive', 'Recruiter', 'Training Specialist', 'Trưởng phòng Nhân sự'],
    'Phòng Tài chính' => ['Kế toán', 'Tài chính viên', 'Kiểm toán viên', 'Trưởng phòng Tài chính'],
    'Phòng Kinh doanh' => ['Sales Executive', 'Business Analyst', 'Account Manager', 'Trưởng phòng Kinh doanh']
];

// Get list of cities for dropdown
$cities = ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ', 'Nha Trang', 'Huế', 'Khác'];
















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Nhân Viên - Hệ Thống Quản Lý</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-title {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .form-group {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            flex: 0 0 100%;
            max-width: 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-group .radio-group {
            display: flex;
            gap: 15px;
        }
        
        .form-group .radio-item {
            display: flex;
            align-items: center;
        }
        
        .form-group .radio-item input {
            width: auto;
            margin-right: 5px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-secondary {
            background-color: #f5f6fa;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <h2>Quản Lý</h2>
            </div>
            <ul class="nav-links">
                <li>
                    <a href="index.php">
                        <i class="fas fa-home"></i>
                        <span>Tổng quan</span>
                    </a>
                </li>
                <li class="active">
                    <a href="nhan-vien.php">
                        <i class="fas fa-users"></i>
                        <span>Nhân viên</span>
                    </a>
                </li>
                <li>
                    <a href="tinh-luong.php">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Tính lương</span>
                    </a>
                </li>
                <li>
                    <a href="tai-khoan.php">
                        <i class="fas fa-user-circle"></i>
                        <span>Tài khoản</span>
                    </a>
                </li>
                <li>
                    <a href="cham-cong.php">
                        <i class="fas fa-clipboard-check"></i>
                        <span>Chấm công</span>
                    </a>
                </li>
                <li>
                    <a href="bao-cao.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Báo cáo</span>
                    </a>
                </li>
                <li>
                    <a href="cai-dat.php">
                        <i class="fas fa-cog"></i>
                        <span>Cài đặt</span>
                    </a>
                </li>
            </ul>
            <div class="admin-info">
                <div class="admin-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="admin-details">
                    <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <small><?php echo $_SESSION['admin_role'] === 'admin' ? 'Quản trị viên' : 'Người dùng'; ?></small>
                </div>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Tìm kiếm...">
                </div>
                <div class="user-info">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <span>Xin chào, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1 class="page-title">Thêm Nhân Viên Mới</h1>
                
                <div class="form-container">
                    <?php if ($error): ?>
                        <div class="error-message">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="success-message">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="them-nhan-vien.php">
                        <h2 class="form-title">Thông tin cá nhân</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="required">Họ và tên</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="required">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthdate">Ngày sinh</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($birthdate); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Giới tính</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <input type="radio" id="gender-male" name="gender" value="Nam" <?php echo $gender === 'Nam' ? 'checked' : ''; ?>>
                                        <label for="gender-male">Nam</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="gender-female" name="gender" value="Nữ" <?php echo $gender === 'Nữ' ? 'checked' : ''; ?>>
                                        <label for="gender-female">Nữ</label>
                                    </div>
                                    <div class="radio-item">
                                        <input type="radio" id="gender-other" name="gender" value="Khác" <?php echo $gender === 'Khác' ? 'checked' : ''; ?>>
                                        <label for="gender-other">Khác</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">Thành phố</label>
                                <select id="city" name="city">
                                    <option value="">-- Chọn thành phố --</option>
                                    <?php foreach ($cities as $city_option): ?>
                                        <option value="<?php echo $city_option; ?>" <?php echo $city === $city_option ? 'selected' : ''; ?>>
                                            <?php echo $city_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="department" class="required">Phòng ban</label>
                                <select id="department" name="department" required>
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($departments as $dept_option): ?>
                                        <option value="<?php echo $dept_option; ?>" <?php echo $department === $dept_option ? 'selected' : ''; ?>>
                                            <?php echo $dept_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="position" class="required">Chức vụ</label>
                                <select id="position" name="position" required>
                                    <option value="">-- Chọn chức vụ --</option>
                                    <?php if (!empty($department) && isset($positions[$department])): ?>
                                        <?php foreach ($positions[$department] as $pos_option): ?>
                                            <option value="<?php echo $pos_option; ?>" <?php echo $position === $pos_option ? 'selected' : ''; ?>>
                                                <?php echo $pos_option; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="salary">Lương (VNĐ)</label>
                                <input type="number" id="salary" name="salary" min="0" step="100000" value="<?php echo htmlspecialchars($salary); ?>">
                            </div>
                        </div>
                        
                        <h2 class="form-title">Thông tin tài khoản</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username" class="required">Tên đăng nhập</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="required">Mật khẩu</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="confirm_password" class="required">Xác nhận mật khẩu</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="form-group">
                                <!-- Placeholder for balance -->
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="nhan-vien.php" class="btn btn-secondary">Hủy bỏ</a>
                            <button type="submit" class="btn btn-primary">Thêm nhân viên</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        // Dynamic position dropdown based on selected department
        document.addEventListener('DOMContentLoaded', function() {
            const departmentSelect = document.getElementById('department');
            const positionSelect = document.getElementById('position');
            const positions = <?php echo json_encode($positions); ?>;
            
            departmentSelect.addEventListener('change', function() {
                const department = this.value;
                
                // Clear current options
                positionSelect.innerHTML = '<option value="">-- Chọn chức vụ --</option>';
                
                // Add new options based on selected department
                if (department && positions[department]) {
                    positions[department].forEach(function(position) {
                        const option = document.createElement('option');
                        option.value = position;
                        option.textContent = position;
                        positionSelect.appendChild(option);
                    });
                }
            });
        });
    </script>
</body>
</html>
