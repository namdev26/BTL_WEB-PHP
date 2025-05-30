<?php
session_start();
// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Danh sách phòng ban
$departments = ['Phòng IT', 'Phòng Marketing', 'Phòng Nhân sự', 'Phòng Tài chính', 'Phòng Kinh doanh'];

// Danh sách chức vụ theo phòng ban
$positions = [
    'Phòng IT' => ['Developer', 'Tester', 'System Admin', 'Project Manager', 'Trưởng phòng IT'],
    'Phòng Marketing' => ['Marketing Executive', 'Content Creator', 'SEO Specialist', 'Trưởng phòng Marketing'],
    'Phòng Nhân sự' => ['HR Executive', 'Recruiter', 'Training Specialist', 'Trưởng phòng Nhân sự'],
    'Phòng Tài chính' => ['Kế toán', 'Tài chính viên', 'Kiểm toán viên', 'Trưởng phòng Tài chính'],
    'Phòng Kinh doanh' => ['Sales Executive', 'Business Analyst', 'Account Manager', 'Trưởng phòng Kinh doanh']
];

// Danh sách thành phố
$cities = ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ', 'Nha Trang', 'Huế', 'Khác'];

// Kiểm tra ID nhân viên
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: nhan-vien.php');
    exit;
}

$employee_id = $_GET['id'];
$error_message = '';
$success_message = '';

// Lấy thông tin nhân viên
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: nhan-vien.php');
    exit;
}

$employee = $result->fetch_assoc();

// Xử lý form cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $city = trim($_POST['city']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $salary = floatval($_POST['salary']);
    $status = $_POST['status'];
    
    // Kiểm tra mật khẩu mới (nếu được cung cấp)
    $password_update = false;
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $password_update = true;
    }
    
    // Kiểm tra dữ liệu
    if (empty($full_name) || empty($email) || empty($department) || empty($position)) {
        $error_message = "Vui lòng điền đầy đủ thông tin bắt buộc";
    } else {
        // Kiểm tra email và username đã tồn tại chưa (trừ nhân viên hiện tại)
        $check_query = "SELECT id FROM employees WHERE (email = ? OR username = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ssi", $email, $username, $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Email hoặc tên đăng nhập đã tồn tại";
        } else {
            // Cập nhật thông tin nhân viên
            if ($password_update) {
                $update_query = "UPDATE employees SET 
                    full_name = ?, username = ?, password = ?, email = ?, 
                    birthdate = ?, gender = ?, city = ?, department = ?, 
                    position = ?, salary = ?, status = ? 
                    WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssssssssdsi", 
                    $full_name, $username, $password, $email, 
                    $birthdate, $gender, $city, $department, 
                    $position, $salary, $status, $employee_id
                );
            } else {
                $update_query = "UPDATE employees SET 
                    full_name = ?, username = ?, email = ?, 
                    birthdate = ?, gender = ?, city = ?, department = ?, 
                    position = ?, salary = ?, status = ? 
                    WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("ssssssssdsi", 
                    $full_name, $username, $email, 
                    $birthdate, $gender, $city, $department, 
                    $position, $salary, $status, $employee_id
                );
            }
            
            if ($update_stmt->execute()) {
                $success_message = "Cập nhật thông tin nhân viên thành công";
                // Cập nhật lại thông tin nhân viên sau khi cập nhật
                $stmt->execute();
                $employee = $stmt->get_result()->fetch_assoc();
            } else {
                $error_message = "Lỗi: " . $conn->error;
            }
        }
    }
}
















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Thông Tin Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .btn-submit {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-submit:hover {
            background-color: #2980b9;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        
        .btn-back i {
            margin-right: 5px;
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
                    <span>Admin</span>
                    <small>Quản trị viên</small>
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
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user">
                        <img src="img/avatar.jpg" alt="User Avatar">
                        <span>Xin chào, Admin</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1 class="page-title">Sửa Thông Tin Nhân Viên</h1>
                
                <a href="nhan-vien.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách nhân viên
                </a>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Họ và tên <span class="required">*</span></label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="employee_id">Mã nhân viên</label>
                                <input type="text" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Tên đăng nhập <span class="required">*</span></label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($employee['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Mật khẩu (để trống nếu không thay đổi)</label>
                                <input type="password" id="password" name="password">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthdate">Ngày sinh</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo $employee['birthdate']; ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Giới tính</label>
                                <select id="gender" name="gender">
                                    <option value="Nam" <?php echo ($employee['gender'] == 'Nam') ? 'selected' : ''; ?>>Nam</option>
                                    <option value="Nữ" <?php echo ($employee['gender'] == 'Nữ') ? 'selected' : ''; ?>>Nữ</option>
                                    <option value="Khác" <?php echo ($employee['gender'] == 'Khác') ? 'selected' : ''; ?>>Khác</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="city">Thành phố</label>
                            <select id="city" name="city">
                                <option value="">-- Chọn thành phố --</option>
                                <?php foreach ($cities as $city_option): ?>
                                    <option value="<?php echo $city_option; ?>" <?php echo ($employee['city'] == $city_option) ? 'selected' : ''; ?>>
                                        <?php echo $city_option; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="department">Phòng ban <span class="required">*</span></label>
                                <select id="department" name="department" required>
                                    <option value="">-- Chọn phòng ban --</option>
                                    <?php foreach ($departments as $dept_option): ?>
                                        <option value="<?php echo $dept_option; ?>" <?php echo ($employee['department'] == $dept_option) ? 'selected' : ''; ?>>
                                            <?php echo $dept_option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="position">Chức vụ <span class="required">*</span></label>
                                <select id="position" name="position" required>
                                    <option value="">-- Chọn chức vụ --</option>
                                    <?php 
                                    $current_department = $employee['department'];
                                    if (!empty($current_department) && isset($positions[$current_department])) {
                                        foreach ($positions[$current_department] as $pos_option) {
                                            $selected = ($employee['position'] == $pos_option) ? 'selected' : '';
                                            echo "<option value=\"$pos_option\" $selected>$pos_option</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="salary">Lương (VNĐ) <span class="required">*</span></label>
                                <input type="number" id="salary" name="salary" value="<?php echo $employee['salary']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng thái</label>
                                <select id="status" name="status">
                                    <option value="active" <?php echo ($employee['status'] == 'active') ? 'selected' : ''; ?>>Đang làm việc</option>
                                    <option value="inactive" <?php echo ($employee['status'] == 'inactive') ? 'selected' : ''; ?>>Đã nghỉ việc</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-submit">Cập nhật thông tin</button>
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
