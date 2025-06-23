<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config/db.php';

// Initialize variables
$success = '';
$error = '';
$user = null;

// Get user information
$user_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    $error = 'Không thể tải thông tin người dùng';
}

$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($email)) {
        $error = 'Vui lòng nhập email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } elseif (!empty($new_password) && empty($current_password)) {
        $error = 'Vui lòng nhập mật khẩu hiện tại';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email đã được sử dụng bởi người dùng khác';
        } else {
            // If changing password, verify current password
            if (!empty($new_password)) {
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
                if (!password_verify($current_password, $user_data['password'])) {
                    $error = 'Mật khẩu hiện tại không đúng';
                } else {
                    // Update email and password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $email, $hashed_password, $user_id);
                }
            } else {
                // Update email only
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $email, $user_id);
            }
            
            if ($stmt->execute()) {
                $success = 'Cập nhật thông tin thành công';
                
                // Update user data
                $user['email'] = $email;
            } else {
                $error = 'Đã xảy ra lỗi: ' . $stmt->error;
            }
        }
        
        $stmt->close();
    }
}










?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Tài Khoản - Hệ Thống Quản Lý</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background-color: #f0f7ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        
        .profile-avatar i {
            font-size: 40px;
            color: #3498db;
        }
        
        .profile-info h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #888;
            font-size: 14px;
        }
        
        .profile-form .form-group {
            margin-bottom: 20px;
        }
        
        .profile-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .profile-form input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .profile-form input:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .profile-form button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .profile-form button:hover {
            background-color: #2980b9;
        }
        
        .password-section {
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 30px;
        }
        
        .password-section h3 {
            margin-bottom: 20px;
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
                <li>
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
                <li class="active">
                    <a href="profile.php">
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
                <h1 class="page-title">Thông Tin Tài Khoản</h1>
                
                <div class="profile-container">
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
                    
                    <?php if ($user): ?>
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info">
                                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                                <p><?php echo $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng'; ?></p>
                            </div>
                        </div>
                        
                        <form class="profile-form" method="POST" action="profile.php">
                            <div class="form-group">
                                <label for="username">Tên đăng nhập</label>
                                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="password-section">
                                <h3>Đổi mật khẩu</h3>
                                
                                <div class="form-group">
                                    <label for="current_password">Mật khẩu hiện tại</label>
                                    <input type="password" id="current_password" name="current_password">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">Mật khẩu mới</label>
                                    <input type="password" id="new_password" name="new_password">
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Xác nhận mật khẩu mới</label>
                                    <input type="password" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <button type="submit">Cập nhật thông tin</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
