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

// Định dạng ngày sinh
$birthdate = !empty($employee['birthdate']) ? date('d/m/Y', strtotime($employee['birthdate'])) : 'Chưa cập nhật';

// Định dạng ngày tạo
$created_at = date('d/m/Y H:i', strtotime($employee['created_at']));

// Định dạng lương
$formatted_salary = number_format($employee['salary'], 0, ',', '.');

// Trạng thái nhân viên
$status_text = ($employee['status'] == 'active') ? 'Đang làm việc' : 'Đã nghỉ việc';
$status_class = ($employee['status'] == 'active') ? 'active' : 'inactive';



















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Nhân Viên - <?php echo htmlspecialchars($employee['full_name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .employee-profile {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background-color: #f0f7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 30px;
            font-size: 50px;
            color: #3498db;
        }
        
        .profile-info h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            color: #333;
        }
        
        .profile-info p {
            margin: 0 0 5px 0;
            color: #666;
        }
        
        .profile-info .employee-id {
            background-color: #f8f9fa;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .profile-info .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 10px;
        }
        
        .profile-info .status.active {
            background-color: #e1f5e9;
            color: #2ecc71;
        }
        
        .profile-info .status.inactive {
            background-color: #f8d7da;
            color: #e74c3c;
        }
        
        .profile-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-group label {
            display: block;
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-group p {
            margin: 0;
            color: #333;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
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
                <h1 class="page-title">Thông Tin Nhân Viên</h1>
                
                <a href="nhan-vien.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Quay lại danh sách nhân viên
                </a>
                
                <div class="employee-profile">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                            <p><?php echo htmlspecialchars($employee['position']); ?> - <?php echo htmlspecialchars($employee['department']); ?></p>
                            <div class="employee-id">Mã NV: <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                            <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                    
                    <div class="profile-sections">
                        <div class="profile-section">
                            <h3>Thông tin cá nhân</h3>
                            <div class="info-group">
                                <label>Họ và tên</label>
                                <p><?php echo htmlspecialchars($employee['full_name']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Email</label>
                                <p><?php echo htmlspecialchars($employee['email']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Ngày sinh</label>
                                <p><?php echo $birthdate; ?></p>
                            </div>
                            <div class="info-group">
                                <label>Giới tính</label>
                                <p><?php echo htmlspecialchars($employee['gender']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Thành phố</label>
                                <p><?php echo !empty($employee['city']) ? htmlspecialchars($employee['city']) : 'Chưa cập nhật'; ?></p>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h3>Thông tin công việc</h3>
                            <div class="info-group">
                                <label>Phòng ban</label>
                                <p><?php echo htmlspecialchars($employee['department']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Chức vụ</label>
                                <p><?php echo htmlspecialchars($employee['position']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Lương</label>
                                <p><?php echo $formatted_salary; ?> VNĐ</p>
                            </div>
                            <div class="info-group">
                                <label>Trạng thái</label>
                                <p><?php echo $status_text; ?></p>
                            </div>
                            <div class="info-group">
                                <label>Ngày tham gia</label>
                                <p><?php echo $created_at; ?></p>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h3>Thông tin tài khoản</h3>
                            <div class="info-group">
                                <label>Tên đăng nhập</label>
                                <p><?php echo htmlspecialchars($employee['username']); ?></p>
                            </div>
                            <div class="info-group">
                                <label>Mật khẩu</label>
                                <p>••••••••</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="sua-nhan-vien.php?id=<?php echo $employee_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Sửa thông tin
                        </a>
                        <a href="xoa-nhan-vien.php?id=<?php echo $employee_id; ?>" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Xóa nhân viên
                        </a>
                        <a href="nhan-vien.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
