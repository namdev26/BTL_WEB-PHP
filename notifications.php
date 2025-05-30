<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['employee_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Xác định loại người dùng và ID
$user_type = isset($_SESSION['admin_logged_in']) ? 'admin' : 'employee';
$user_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : $_SESSION['employee_id'];

// Xử lý đánh dấu đã đọc thông báo
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ? AND recipient_type = ?";
    $mark_stmt = $conn->prepare($mark_read_query);
    $mark_stmt->bind_param("iis", $notification_id, $user_id, $user_type);
    $mark_stmt->execute();
    
    // Chuyển hướng đến trang chi tiết nếu có
    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
        header('Location: ' . $_GET['redirect']);
        exit;
    }
}

// Xử lý đánh dấu tất cả đã đọc
if (isset($_GET['mark_all_read'])) {
    $mark_all_query = "UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND recipient_type = ?";
    $mark_all_stmt = $conn->prepare($mark_all_query);
    $mark_all_stmt->bind_param("is", $user_id, $user_type);
    $mark_all_stmt->execute();
    
    // Chuyển hướng về trang thông báo
    header('Location: notifications.php');
    exit;
}

// Lấy danh sách thông báo
$notifications = [];
$notification_query = "SELECT * FROM notifications WHERE recipient_id = ? AND recipient_type = ? ORDER BY created_at DESC";
$notification_stmt = $conn->prepare($notification_query);
$notification_stmt->bind_param("is", $user_id, $user_type);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();

if ($notification_result->num_rows > 0) {
    while ($row = $notification_result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Lấy số lượng thông báo chưa đọc
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("is", $user_id, $user_type);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];

// Hàm lấy icon cho loại thông báo
function get_notification_icon($type) {
    switch ($type) {
        case 'leave_request':
            return '<i class="fas fa-calendar-minus text-warning"></i>';
        case 'leave_response':
            return '<i class="fas fa-clipboard-check text-info"></i>';
        case 'salary':
            return '<i class="fas fa-money-bill-wave text-success"></i>';
        case 'attendance':
            return '<i class="fas fa-calendar-check text-primary"></i>';
        case 'system':
            return '<i class="fas fa-cog text-secondary"></i>';
        default:
            return '<i class="fas fa-bell text-info"></i>';
    }
}

// Hàm định dạng thời gian thông báo
function format_notification_time($timestamp) {
    $now = time();
    $notification_time = strtotime($timestamp);
    $diff = $now - $notification_time;
    
    if ($diff < 60) {
        return "vừa xong";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " phút trước";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " giờ trước";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " ngày trước";
    } else {
        return date("d/m/Y H:i", $notification_time);
    }
}

// Hàm lấy URL chuyển hướng khi click vào thông báo
function get_notification_redirect_url($notification) {
    $type = $notification['type'];
    $reference_id = $notification['reference_id'];
    
    if ($notification['recipient_type'] === 'admin') {
        switch ($type) {
            case 'leave_request':
                return "admin-leave-requests.php?status=pending";
            case 'attendance':
                return "admin-attendance.php";
            case 'salary':
                return "admin-salary.php";
            default:
                return "admin-dashboard.php";
        }
    } else { // employee
        switch ($type) {
            case 'leave_response':
                return "employee-leave.php";
            case 'salary':
                return "employee-salary.php";
            case 'attendance':
                return "employee-attendance.php";
            default:
                return "employee-dashboard.php";
        }
    }
}
















?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Báo - Hệ Thống Quản Lý Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <?php if ($user_type === 'employee'): ?>
    <link rel="stylesheet" href="css/employee-dashboard.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/thong_bao.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/nhan_vien.css">
</head>
<body>
    <?php if ($user_type === 'admin'): ?>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <h2>Quản Lý</h2>
            </div>
            <ul class="nav-links">
                <li >
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
                
                <li>
                    <a href="admin-leave-requests.php">
                        <i class="fas fa-calendar-minus"></i>
                        <span>Đơn nghỉ phép</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="active">
                    <a href="notifications.php">
                        <i class="fas fa-bell"></i>
                        <span>Thông báo</span>
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
            <div class="header">
                <h1 class="page-title">Thông Báo</h1>
                
                <div class="user-info">
                    <div class="user-details">
                        <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                        <a href="logout.php" class="logout" title="Đăng xuất">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
    <?php else: ?>
    <div class="employee-dashboard">
        <!-- Header -->
        <header class="employee-header">
            <div class="employee-logo">
                <i class="fas fa-chart-line"></i>
                <h1>Hệ Thống Quản Lý Nhân Viên</h1>
            </div>
            
            <div class="employee-user">
                <div class="notification">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($_SESSION['employee_name']); ?></span>
                    <small><?php echo htmlspecialchars($_SESSION['employee_position']); ?></small>
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
                    <li>
                        <a href="employee-profile.php">
                            <i class="fas fa-user"></i>
                            <span>Hồ sơ</span>
                        </a>
                    </li>
                    <li>
                        <a href="employee-salary.php">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Lương</span>
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
                <h1 class="page-title">Thông Báo</h1>
    <?php endif; ?>
                
                <div class="content-container">
                    <div class="notification-header">
                        <h2>Danh sách thông báo</h2>
                        
                        <?php if (!empty($notifications)): ?>
                            <a href="notifications.php?mark_all_read=1" class="mark-all-read">
                                <i class="fas fa-check-double"></i> Đánh dấu tất cả đã đọc
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>Bạn chưa có thông báo nào.</p>
                        </div>
                    <?php else: ?>
                        <ul class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>&redirect=<?php echo urlencode(get_notification_redirect_url($notification)); ?>" class="notification-link">
                                        <div class="notification-icon">
                                            <?php echo get_notification_icon($notification['type']); ?>
                                        </div>
                                        <div class="notification-content">
                                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <p class="notification-time">
                                                <i class="far fa-clock"></i> <?php echo format_notification_time($notification['created_at']); ?>
                                                <?php if ($notification['is_read'] == 0): ?>
                                                    <span style="color: #3498db; font-weight: 600; margin-left: 10px;">Mới</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
