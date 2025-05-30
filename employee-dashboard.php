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

// Lấy số lượng thông báo chưa đọc
$unread_count = 0;
$check_notifications = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notifications->num_rows > 0) {
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND recipient_type = 'employee' AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $employee_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_count = $unread_result->fetch_assoc()['count'];
}

// Lấy thông tin lương
$salary_data = [];
$current_month = date('m');
$current_year = date('Y');

// Kiểm tra bảng salary_records
$check_table = $conn->query("SHOW TABLES LIKE 'salary_records'");
if ($check_table->num_rows > 0) {
    // Lấy lương của nhân viên trong 6 tháng gần nhất
    $salary_query = "SELECT * FROM salary_records WHERE employee_id = ? ORDER BY salary_year DESC, salary_month DESC LIMIT 6";
    $salary_stmt = $conn->prepare($salary_query);
    $salary_stmt->bind_param("s", $employee_code);
    $salary_stmt->execute();
    $salary_result = $salary_stmt->get_result();
    
    if ($salary_result->num_rows > 0) {
        while ($row = $salary_result->fetch_assoc()) {
            $salary_data[] = $row;
        }
    }
}

// Lấy hoạt động gần đây của nhân viên
$activities = [];
$check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
if ($check_activities->num_rows > 0) {
    $activity_query = "SELECT * FROM activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $activity_stmt = $conn->prepare($activity_query);
    $activity_stmt->bind_param("s", $employee_code);
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    
    if ($activity_result->num_rows > 0) {
        while ($row = $activity_result->fetch_assoc()) {
            $activities[] = $row;
        }
    }
}

// Hàm định dạng thời gian hoạt động
function format_activity_time($timestamp) {
    $now = time();
    $activity_time = strtotime($timestamp);
    $diff = $now - $activity_time;
    
    if ($diff < 60) {
        return 'Vừa xong';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' phút trước';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' giờ trước';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' ngày trước';
    } else {
        return date('d/m/Y H:i', $activity_time);
    }
}

// Hàm lấy icon cho hoạt động
function get_activity_icon($action) {
    switch ($action) {
        case 'login':
            return '<i class="fas fa-sign-in-alt text-info"></i>';
        case 'logout':
            return '<i class="fas fa-sign-out-alt text-warning"></i>';
        case 'view_salary':
            return '<i class="fas fa-money-bill-wave text-success"></i>';
        case 'update_profile':
            return '<i class="fas fa-user-edit text-primary"></i>';
        default:
            return '<i class="fas fa-info-circle text-secondary"></i>';
    }
}

// Lấy danh sách tháng và năm
$months = [
    1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
    5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
    9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
];

// Lấy danh sách nhân viên có sinh nhật hôm nay
$today_month = date('m');
$today_day = date('d');
$birthday_employees = [];

try {
    // Lấy danh sách nhân viên có sinh nhật hôm nay
    $query = "SELECT id, full_name, birthdate 
             FROM employees 
             WHERE MONTH(birthdate) = ? 
             AND DAY(birthdate) = ?
             AND status = 'active'";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn: " . $conn->error);
    }
    
    $stmt->bind_param('ss', $today_month, $today_day);
    if (!$stmt->execute()) {
        throw new Exception("Lỗi thực thi truy vấn: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception("Lỗi lấy kết quả: " . $stmt->error);
    }
    
    // Xử lý kết quả
    while ($employee = $result->fetch_assoc()) {
        $birthday_employees[] = $employee;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách sinh nhật: " . $e->getMessage());
}





//HTML

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang Cá Nhân - <?php echo htmlspecialchars($employee_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/employee-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <div class="employee-dashboard">
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
                    <li class="active">
                        <a href="employee-dashboard.php">
                            <i class="fas fa-home"></i>
                            <span>Trang chủ</span>
                        </a>
                    </li>
                    <li>
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
                <h1 class="page-title">Xin chào, <?php echo htmlspecialchars($employee_name); ?>!</h1>
                
                <?php 
// Only show birthday alert if there are employees with birthdays today
if (!empty($birthday_employees) && !isset($birthday_employees[0]['is_test'])): ?>
                <div class="birthday-alert">
                    <div class="birthday-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <div class="birthday-content">
                        <h3>Chúc mừng sinh nhật!</h3>
                        <p>Hôm nay là sinh nhật của:</p>
                        <ul class="birthday-list">
                            <?php foreach ($birthday_employees as $employee): 
                                // Skip test employees
                                if (isset($employee['is_test'])) continue;
                                
                                $birth_date = date('d/m/Y', strtotime($employee['birthdate']));
                                $birth_year = date('Y', strtotime($employee['birthdate']));
                                $age = date('Y') - $birth_year;
                            ?>
                                <li>
                                    <span class="employee-name">
                                        <?php echo htmlspecialchars($employee['full_name']); ?>
                                        <span class="birthday-date">
                                            - <?php echo $birth_date; ?>
                                            <span class="age">(<?php echo $age; ?> tuổi)</span>
                                        </span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Info Cards -->
                <div class="employee-cards">
                    <div class="employee-card">
                        <div class="card-icon blue">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="card-text">
                            <h3>Mã nhân viên</h3>
                            <h2><?php echo htmlspecialchars($employee_code); ?></h2>
                        </div>
                    </div>
                    
                    <div class="employee-card">
                        <div class="card-icon green">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="card-text">
                            <h3>Phòng ban</h3>
                            <h2><?php echo htmlspecialchars($employee_department); ?></h2>
                        </div>
                    </div>
                    
                    <div class="employee-card">
                        <div class="card-icon purple">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="card-text">
                            <h3>Chức vụ</h3>
                            <h2><?php echo htmlspecialchars($employee_position); ?></h2>
                        </div>
                    </div>
                </div>
                
                <!-- Main Sections -->
                <div class="employee-sections">
                    <!-- Salary Section -->
                    <div class="employee-section">
                        <div class="section-header">
                            <h2>Lương gần đây</h2>
                            <a href="employee-salary.php" class="view-all">Xem tất cả</a>
                        </div>
                        
                        <?php if (count($salary_data) > 0): ?>
                            <div class="salary-cards">
                                <?php 
                                // Lấy thông tin lương tháng gần nhất
                                $latest_salary = $salary_data[0];
                                $month_year = $months[$latest_salary['salary_month']] . ' ' . $latest_salary['salary_year'];
                                ?>
                                
                                <div class="salary-card">
                                    <div class="salary-card-header">
                                        <span class="salary-month"><?php echo $month_year; ?></span>
                                        <span class="status-badge <?php echo $latest_salary['status']; ?>">
                                            <?php 
                                                switch($latest_salary['status']) {
                                                    case 'pending':
                                                        echo 'Chờ duyệt';
                                                        break;
                                                    case 'approved':
                                                        echo 'Đã duyệt';
                                                        break;
                                                    case 'paid':
                                                        echo 'Đã thanh toán';
                                                        break;
                                                    default:
                                                        echo $latest_salary['status'];
                                                }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="salary-details">
                                        <div class="salary-row">
                                            <span class="salary-label">Lương cơ bản:</span>
                                            <span class="salary-amount"><?php echo number_format($latest_salary['base_salary'], 0, ',', '.'); ?>đ</span>
                                        </div>
                                        <div class="salary-row">
                                            <span class="salary-label">Thưởng:</span>
                                            <span class="salary-amount text-success">+<?php echo number_format($latest_salary['bonus'], 0, ',', '.'); ?>đ</span>
                                        </div>
                                        <div class="salary-row">
                                            <span class="salary-label">Khấu trừ:</span>
                                            <span class="salary-amount text-danger">-<?php echo number_format($latest_salary['deductions'], 0, ',', '.'); ?>đ</span>
                                        </div>
                                        <div class="salary-divider"></div>
                                        <div class="salary-row total">
                                            <span class="salary-label">Thực lĩnh:</span>
                                            <span class="salary-amount total-amount"><?php echo number_format($latest_salary['total_salary'], 0, ',', '.'); ?>đ</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bảng lịch sử lương -->
                                <div class="salary-history">
                                    <h3>Lịch sử lương</h3>
                                    <table class="salary-table">
                                        <thead>
                                            <tr>
                                                <th>Tháng</th>
                                                <th>Lương cơ bản</th>
                                                <th>Thưởng</th>
                                                <th>Khấu trừ</th>
                                                <th>Tổng lương</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($salary_data as $index => $salary): ?>
                                                <tr class="<?php echo $index === 0 ? 'latest' : ''; ?>">
                                                    <td><?php echo $months[$salary['salary_month']] . ' ' . $salary['salary_year']; ?></td>
                                                    <td><?php echo number_format($salary['base_salary'], 0, ',', '.'); ?>đ</td>
                                                    <td class="text-success">+<?php echo number_format($salary['bonus'], 0, ',', '.'); ?>đ</td>
                                                    <td class="text-danger">-<?php echo number_format($salary['deductions'], 0, ',', '.'); ?>đ</td>
                                                    <td><strong><?php echo number_format($salary['total_salary'], 0, ',', '.'); ?>đ</strong></td>
                                                    <td>
                                                        <span class="status <?php echo $salary['status']; ?>">
                                                            <?php 
                                                                switch($salary['status']) {
                                                                    case 'pending':
                                                                        echo 'Chờ duyệt';
                                                                        break;
                                                                    case 'approved':
                                                                        echo 'Đã duyệt';
                                                                        break;
                                                                    case 'paid':
                                                                        echo 'Đã thanh toán';
                                                                        break;
                                                                    default:
                                                                        echo $salary['status'];
                                                                }
                                                            ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>Ấn xem tất cả để xem chi tiết Lương</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Activities Section -->
                    <div class="employee-section">
                        <div class="section-header">
                            <h2>Hoạt động gần đây</h2>
                        </div>
                        
                        <div class="activities-list">
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <?php echo get_activity_icon($activity['action']); ?>
                                        </div>
                                        <div class="activity-details">
                                            <h3><?php echo htmlspecialchars($activity['description']); ?></h3>
                                            <p>
                                                <span class="activity-time"><?php echo format_activity_time($activity['created_at']); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Chưa có hoạt động nào.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Section -->
                <div class="employee-profile">
                    <div class="section-header">
                        <h2>Thông tin cá nhân</h2>
                        <a href="employee-profile.php" class="view-all">Chỉnh sửa</a>
                    </div>
                    
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($employee_name); ?></h2>
                            <p><?php echo htmlspecialchars($employee_position); ?> - <?php echo htmlspecialchars($employee_department); ?></p>
                        </div>
                    </div>
                    
                    <?php
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
                    ?>
                    
                    <div class="profile-details">
                        <div class="profile-item">
                            <h3>Email</h3>
                            <p><?php echo htmlspecialchars($employee_details['email'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                        
                        <div class="profile-item">
                            <h3>Ngày sinh</h3>
                            <p><?php echo !empty($employee_details['birthdate']) ? date('d/m/Y', strtotime($employee_details['birthdate'])) : 'Chưa cập nhật'; ?></p>
                        </div>
                        
                        <div class="profile-item">
                            <h3>Giới tính</h3>
                            <p><?php echo htmlspecialchars($employee_details['gender'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                        
                        <div class="profile-item">
                            <h3>Thành phố</h3>
                            <p><?php echo htmlspecialchars($employee_details['city'] ?? 'Chưa cập nhật'); ?></p>
                        </div>
                        
                        <div class="profile-item">
                            <h3>Ngày tham gia</h3>
                            <p><?php echo !empty($employee_details['created_at']) ? date('d/m/Y', strtotime($employee_details['created_at'])) : 'Chưa cập nhật'; ?></p>
                        </div>
                        
                        <div class="profile-item">
                            <h3>Trạng thái</h3>
                            <p><?php echo $employee_details['status'] === 'active' ? 'Đang làm việc' : 'Đã nghỉ việc'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
