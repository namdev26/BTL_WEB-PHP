<?php
session_start();
// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Kiểm tra ID nhân viên
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: nhan-vien.php');
    exit;
}

$employee_id = $_GET['id'];
$confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';

// Lấy thông tin nhân viên
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: nhan-vien.php?error=not_found');
    exit;
}

$employee = $result->fetch_assoc();

// Xử lý xóa nhân viên
if ($confirm === 'yes') {
    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();
    
    try {
        // Lấy employee_id (mã nhân viên) trước khi xóa
        $employee_code = $employee['employee_id'];
        
        // 1. Xóa dữ liệu lương của nhân viên từ bảng salary_records
        $check_table = $conn->query("SHOW TABLES LIKE 'salary_records'");
        if ($check_table->num_rows > 0) {
            // Xóa dữ liệu lương dựa trên ID của nhân viên (số nguyên)
            $delete_salary = $conn->prepare("DELETE FROM salary_records WHERE employee_id = ?");
            $delete_salary->bind_param("i", $employee_id); // Sử dụng ID số nguyên
            $delete_salary->execute();
            
            // Ghi log về việc xóa lương
            error_log("Xóa lương cho nhân viên ID: {$employee_id}, Mã: {$employee_code}");
        }
        
        // 2. Ghi lại hoạt động xóa nhân viên
        $check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
        if ($check_activities->num_rows > 0) {
            $action = 'delete_employee';
            $description = 'Xóa nhân viên: ' . $employee['full_name'] . ' (Mã: ' . $employee_code . ')';
            $user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'admin';
            
            $log_activity = $conn->prepare("INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)");
            $log_activity->bind_param("sss", $user_id, $action, $description);
            $log_activity->execute();
        }
        
        // 3. Xóa nhân viên từ bảng employees
        $delete_stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $delete_stmt->bind_param("i", $employee_id);
        $delete_stmt->execute();
        
        // Commit transaction nếu tất cả thành công
        $conn->commit();
        
        header('Location: nhan-vien.php?success=deleted');
        exit;
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        header('Location: nhan-vien.php?error=delete_failed&message=' . urlencode($e->getMessage()));
        exit;
    }
}




















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirm-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .confirm-icon {
            font-size: 60px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        
        .confirm-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .confirm-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .employee-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .employee-info p {
            margin: 5px 0;
        }
        
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-secondary {
            background-color: #7f8c8d;
            color: white;
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
                <h1 class="page-title">Xóa Nhân Viên</h1>
                
                <a href="nhan-vien.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách nhân viên
                </a>
                
                <div class="confirm-container">
                    <div class="confirm-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="confirm-title">Xác nhận xóa nhân viên</h2>
                    <p class="confirm-message">Bạn có chắc chắn muốn xóa nhân viên này? Hành động này không thể hoàn tác.</p>
                    
                    <div class="employee-info">
                        <p><strong>Mã nhân viên:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                        <p><strong>Họ và tên:</strong> <?php echo htmlspecialchars($employee['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                        <p><strong>Phòng ban:</strong> <?php echo htmlspecialchars($employee['department']); ?></p>
                        <p><strong>Chức vụ:</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
                    </div>
                    
                    <div class="btn-group">
                        <a href="xoa-nhan-vien.php?id=<?php echo $employee_id; ?>&confirm=yes" class="btn btn-danger">Xác nhận xóa</a>
                        <a href="nhan-vien.php" class="btn btn-secondary">Hủy bỏ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
