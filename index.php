<?php

session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'config/db.php';


$conn->query($create_activities_table);

// Get user data
$username = $_SESSION['admin_username'];
$user_role = $_SESSION['admin_role'];
$admin_id = $_SESSION['admin_id'];

// Lấy số lượng thông báo chưa đọc
$unread_count = 0;
$check_notifications = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_notifications->num_rows > 0) {
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND recipient_type = 'admin' AND is_read = 0";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bind_param("i", $admin_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result();
    $unread_count = $unread_result->fetch_assoc()['count'];
}

// Get employee count
$employee_count = 0;
$stmt = $conn->query("SELECT COUNT(*) as employee_count FROM employees");
if ($stmt && $result = $stmt->fetch_assoc()) {
    $employee_count = $result['employee_count'];
}

// Get previous month employee count for comparison
$prev_month = date('Y-m', strtotime('-1 month'));
$current_month = date('Y-m');
$employee_growth = 0;
$prev_month_count = 0;

$stmt = $conn->query("SELECT COUNT(*) as prev_count FROM employees WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$prev_month}'");
if ($stmt && $result = $stmt->fetch_assoc()) {
    $prev_month_count = $result['prev_count'];
    if ($prev_month_count > 0) {
        $employee_growth = round((($employee_count - $prev_month_count) / $prev_month_count) * 100, 1);
    }
}

// Get active employees today
$active_today = 0;
$on_leave_count = 0;
$stmt = $conn->query("SELECT COUNT(*) as active_today FROM employees WHERE status = 'active'");
if ($stmt && $result = $stmt->fetch_assoc()) {
    $active_today = $result['active_today'];
}

// Get employees on leave
$stmt = $conn->query("SELECT COUNT(*) as on_leave FROM employees WHERE status != 'active'");
if ($stmt && $result = $stmt->fetch_assoc()) {
    $on_leave_count = $result['on_leave'];
}

// Lấy danh sách hoạt động gần đây (chỉ 3 hoạt động gần nhất)
$recent_activities = [];
$activities_query = "SELECT * FROM activities ORDER BY created_at DESC LIMIT 3";
$activities_result = $conn->query($activities_query);

// Nếu chưa có hoạt động nào, thêm một số hoạt động mẫu
$sample_activities = [
    // [user_id, action, description, created_at]
    [1, 'login', 'Người dùng 1 đã đăng nhập', date('Y-m-d H:i:s')],
    [2, 'update', 'Người dùng 2 đã cập nhật hồ sơ', date('Y-m-d H:i:s')],
    [3, 'logout', 'Người dùng 3 đã đăng xuất', date('Y-m-d H:i:s')],
];
if ($activities_result && $activities_result->num_rows == 0) {
    foreach ($sample_activities as $activity) {
        $insert_query = "INSERT INTO activities (user_id, action, description, created_at) VALUES (?, ?, ?, ?)"; 
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssss", $activity[0], $activity[1], $activity[2], $activity[3]);
        $stmt->execute();
        $stmt->close();
    }
    
    // Lấy lại danh sách hoạt động
    $activities_result = $conn->query($activities_query);
}

if ($activities_result) {
    while ($activity = $activities_result->fetch_assoc()) {
        $recent_activities[] = $activity;
    }
}

// Hàm định dạng thời gian hoạt động
function format_activity_time($timestamp) {
    $now = time();
    $activity_time = strtotime($timestamp);
    $diff = $now - $activity_time;
    
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
        return date("d/m/Y H:i", $activity_time);
    }
}

// Hàm lấy biểu tượng cho từng loại hoạt động
function get_activity_icon($action) {
    switch ($action) {
        case 'add_employee':
            return '<i class="fas fa-user-plus text-success"></i>';
        case 'edit_employee':
            return '<i class="fas fa-user-edit text-primary"></i>';
        case 'delete_employee':
            return '<i class="fas fa-user-times text-danger"></i>';
        case 'calculate_salary':
            return '<i class="fas fa-money-bill-wave text-success"></i>';
        case 'approve_salary':
            return '<i class="fas fa-check-circle text-success"></i>';
        case 'login':
            return '<i class="fas fa-sign-in-alt text-info"></i>';
        case 'logout':
            return '<i class="fas fa-sign-out-alt text-warning"></i>';
        default:
            return '<i class="fas fa-info-circle text-secondary"></i>';
    }
}

// Get total salary
$total_salary = 0;

// Kiểm tra bảng salary_records đã tồn tại chưa
$check_salary_table = $conn->query("SHOW TABLES LIKE 'salary_records'");

// Nếu bảng salary_records tồn tại
if ($check_salary_table && $check_salary_table->num_rows > 0) {
    // Lấy tháng và năm hiện tại
    $current_month = date('m');
    $current_year = date('Y');
    
    // Lấy tổng lương từ bảng salary_records cho tháng hiện tại
    $salary_query = "SELECT SUM(total_salary) as total_salary FROM salary_records WHERE salary_month = ? AND salary_year = ?";
    $salary_stmt = $conn->prepare($salary_query);
    $salary_stmt->bind_param("ii", $current_month, $current_year);
    $salary_stmt->execute();
    $result = $salary_stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $total_salary = $row['total_salary'] ?? 0;
    }
    
    // Nếu không có dữ liệu cho tháng hiện tại, thử lấy tháng gần nhất
    if ($total_salary == 0) {
        $latest_salary_query = "SELECT SUM(total_salary) as total_salary FROM salary_records ORDER BY salary_year DESC, salary_month DESC LIMIT 1";
        $latest_result = $conn->query($latest_salary_query);
        
        if ($latest_result && $latest_row = $latest_result->fetch_assoc()) {
            $total_salary = $latest_row['total_salary'] ?? 0;
        }
    }
} else {
    // Nếu không có bảng salary_records, lấy tổng lương từ bảng employees
    $stmt = $conn->query("SELECT SUM(salary) as total_salary FROM employees");
    if ($stmt && $result = $stmt->fetch_assoc()) {
        $total_salary = $result['total_salary'] ?? 0;
    }
}

// Lấy dữ liệu lương theo tháng cho biểu đồ
$salary_data = [];
$salary_labels = [];
$salary_values = [];

// Kiểm tra bảng salary_records đã tồn tại chưa
$check_table = $conn->query("SHOW TABLES LIKE 'salary_records'");
if ($check_table->num_rows > 0) {
    // Lấy dữ liệu lương 6 tháng gần nhất
    $current_year = date('Y');
    $current_month = date('m');
    
    // Tạo mảng các tháng cần lấy dữ liệu (6 tháng gần nhất)
    $months_to_query = [];
    for ($i = 0; $i < 6; $i++) {
        $month = $current_month - $i;
        $year = $current_year;
        
        if ($month <= 0) {
            $month += 12;
            $year -= 1;
        }
        
        $months_to_query[] = [
            'month' => $month,
            'year' => $year,
            'label' => date('m/Y', mktime(0, 0, 0, $month, 1, $year))
        ];
    }
    
    // Đảo ngược mảng để hiển thị theo thứ tự thời gian
    $months_to_query = array_reverse($months_to_query);
    
    // Lấy dữ liệu từ cơ sở dữ liệu
    foreach ($months_to_query as $month_data) {
        $query = "SELECT SUM(total_salary) as month_total FROM salary_records WHERE salary_month = ? AND salary_year = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $month_data['month'], $month_data['year']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $month_total = $row['month_total'] ?? 0;
            $salary_labels[] = $month_data['label'];
            $salary_values[] = $month_total;
        } else {
            $salary_labels[] = $month_data['label'];
            $salary_values[] = 0;
        }
    }
} else {
    // Nếu chưa có dữ liệu, tạo dữ liệu mẫu
    $current_year = date('Y');
    $current_month = date('m');
    
    for ($i = 5; $i >= 0; $i--) {
        $month = $current_month - $i;
        $year = $current_year;
        
        if ($month <= 0) {
            $month += 12;
            $year -= 1;
        }
        
        $salary_labels[] = date('m/Y', mktime(0, 0, 0, $month, 1, $year));
        
        // Tạo dữ liệu mẫu ngẫu nhiên
        $sample_value = rand(80, 120) * 1000000; // 80-120 triệu
        $salary_values[] = $sample_value;
    }
}

// Chuyển đổi dữ liệu thành JSON để sử dụng trong JavaScript
$salary_labels_json = json_encode($salary_labels);
$salary_values_json = json_encode($salary_values);

// Get previous month total salary for comparison
$salary_growth = 0;
$prev_month_salary = 0;

$stmt = $conn->query("SELECT SUM(salary) as prev_salary FROM employees WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$prev_month}'");
if ($stmt && $result = $stmt->fetch_assoc()) {
    $prev_month_salary = $result['prev_salary'] ?? 0; // Sử dụng toán tử null coalescing
    if ($prev_month_salary > 0) {
        $salary_growth = round((($total_salary - $prev_month_salary) / $prev_month_salary) * 100, 1);
    }
}

// Get warning count (this could be from a warnings table in a real system)
$warning_count = 0;
$new_warnings = 0;

// Get warning count
$warning_count = 8; // Hardcoded for demo
$new_warnings = 3; // Hardcoded for demo

// Ensure database connection is working
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current date parts
$today = date('Y-m-d');
$today_month = date('m');
$today_day = date('d');
$birthday_employees = [];

try {
    // Check if employees table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'employees'");
    if ($check_table->num_rows == 0) {
        error_log("Employees table does not exist");
    } else {
        // Query to get employees with birthdays today
        $query = "SELECT id, full_name, birthdate 
                 FROM employees 
                 WHERE MONTH(birthdate) = ? 
                 AND DAY(birthdate) = ?
                 AND status = 'active'";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Chuẩn bị truy vấn thất bại: " . $conn->error);
        }
        
        $stmt->bind_param('ss', $today_month, $today_day);
        if (!$stmt->execute()) {
            throw new Exception("Thực thi truy vấn thất bại: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception("Lấy kết quả thất bại: " . $stmt->error);
        }
        
        // Process the results
        $birthday_employees = [];
        while ($employee = $result->fetch_assoc()) {
            $birthday_employees[] = $employee;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách sinh nhật: " . $e->getMessage());
    $birthday_employees = [];
}




















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    
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
                <li class="active">
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
                <li>
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
                        <span>Xin chào, Admin</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1 class="page-title">Tổng quan</h1>
                <?php if (!empty($birthday_employees)): ?>
                <div class="birthday-alert">
                    <div class="birthday-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <div class="birthday-content">
                        <h3>Chúc mừng sinh nhật!</h3>
                        <p>Hôm nay là sinh nhật của:</p>
                        <ul class="birthday-list">
                            <?php foreach ($birthday_employees as $employee): 
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

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="card">
                        <div class="card-info">
                            <div class="card-icon blue">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="card-text">
                                <h3>Nhân viên</h3>
                                <h2><?php echo number_format($employee_count, 0, ',', '.'); ?></h2>
                                <?php if ($employee_growth > 0): ?>
                                    <p class="positive">+<?php echo $employee_growth; ?>% so với tháng trước</p>
                                <?php elseif ($employee_growth < 0): ?>
                                    <p class="negative"><?php echo $employee_growth; ?>% so với tháng trước</p>
                                <?php else: ?>
                                    <p>Không thay đổi so với tháng trước</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-info">
                            <div class="card-icon green">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="card-text">
                                <h3>Đi làm hôm nay</h3>
                                <h2><?php echo number_format($active_today, 0, ',', '.'); ?></h2>
                                <p>Có <?php echo number_format($on_leave_count, 0, ',', '.'); ?> người nghỉ phép</p>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-info">
                            <div class="card-icon yellow">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="card-text">
                                <h3>Tổng lương tháng</h3>
                                <h2><?php echo number_format($total_salary, 0, ',', '.'); ?>đ</h2>
                                <?php if ($salary_growth > 0): ?>
                                    <p class="positive">+<?php echo $salary_growth; ?>% so với tháng trước</p>
                                <?php elseif ($salary_growth < 0): ?>
                                    <p class="negative"><?php echo $salary_growth; ?>% so với tháng trước</p>
                                <?php else: ?>
                                    <p>Không thay đổi so với tháng trước</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-info">
                            <div class="card-icon red">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="card-text">
                                <h3>Cảnh báo</h3>
                                <h2><?php echo number_format($warning_count, 0, ',', '.'); ?></h2>
                                <?php if ($new_warnings > 0): ?>
                                    <p class="negative"><?php echo $new_warnings; ?> cảnh báo mới</p>
                                <?php else: ?>
                                    <p>Không có cảnh báo mới</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="recent-activities">
                    <div class="section-header">
                        <h2>Hoạt động gần đây</h2>
                        <a href="#" class="view-all">Xem tất cả</a>
                    </div>
                    <div class="activities-list">
                        <?php if (count($recent_activities) > 0): ?>
                            <?php foreach ($recent_activities as $activity): ?>
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
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="activity-details">
                                    <h3>Chưa có hoạt động nào được ghi nhận</h3>
                                    <p>Các hoạt động sẽ được hiển thị tại đây khi có thay đổi trong hệ thống</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Biểu đồ lương -->
                <div class="salary-chart-container">
                    <div class="section-header">
                        <h2>Biểu đồ tổng lương theo tháng</h2>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="salaryChart"></canvas>
                    </div>
                </div>
                
                <!-- Top Salary Ranking -->
                <div class="salary-ranking">
                    <div class="section-header">
                        <h2>Xếp hạng nhân viên xuất sắc</h2>
                    </div>
                    <div class="ranking-table">
                        <table>
                            <thead>
                                <tr>
                                    <th width="80">Xếp hạng</th>
                                    <th>Họ và tên</th>
                                    <th>Phòng ban</th>
                                    <th>Chức vụ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Lấy 3 nhân viên có lương cao nhất, sắp xếp giảm dần (để hạng 1 ở trên cùng)
                                $top_salary_query = $conn->query("SELECT full_name, department, position, salary FROM employees WHERE status = 'active' ORDER BY salary DESC LIMIT 3");
                                
                                if ($top_salary_query && $top_salary_query->num_rows > 0) {
                                    $rank = 1;
                                    $rank_labels = [1 => 'Hạng 1', 2 => 'Hạng 2', 3 => 'Hạng 3'];
                                    $rank_icons = [1 => 'trophy', 2 => 'medal', 3 => 'medal'];
                                    
                                    while ($employee = $top_salary_query->fetch_assoc()) {
                                        echo "<tr class='rank-" . $rank . "'>";
                                        echo "<td class='rank-cell'><i class='fas fa-" . $rank_icons[$rank] . "'></i> " . $rank_labels[$rank] . "</td>";
                                        echo "<td>" . htmlspecialchars($employee['full_name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($employee['department']) . "</td>";
                                        echo "<td>" . htmlspecialchars($employee['position']) . "</td>";
                                        echo "</tr>";
                                        $rank++;
                                    }
                                } else {
                                    echo "<tr><td colspan='4' style='text-align: center;'>Chưa có dữ liệu</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions and Upcoming Events -->
                <div class="bottom-sections">
                    <div class="quick-actions">
                        <div class="section-header">
                            <h2>Thao tác nhanh</h2>
                        </div>
                        <div class="action-buttons">
                            <a href="them-nhan-vien.php" class="action-btn">
                                <i class="fas fa-user-plus"></i>
                                <span>Thêm nhân viên</span>
                            </a>
                            <a href="tinh-luong.php" class="action-btn">
                                <i class="fas fa-calculator"></i>
                                <span>Tính lương</span>
                            </a>
                            <a href="them-nghi-phep.php" class="action-btn">
                                <i class="fas fa-calendar-plus"></i>
                                <span>Thêm ngày nghỉ</span>
                            </a>
                            <a href="xuat-bao-cao.php" class="action-btn">
                                <i class="fas fa-file-export"></i>
                                <span>Xuất báo cáo</span>
                            </a>
                        </div>
                    </div>

                    <div class="upcoming-events">
                        <div class="section-header">
                            <h2>Sự kiện sắp tới</h2>
                            <a href="#" class="view-all">Xem lịch</a>
                        </div>
                        <div class="events-list">
                            <div class="event-item">
                                <div class="event-date">
                                    <h3>30</h3>
                                </div>
                                <div class="event-details">
                                    <h3>Hạn chốt bảng lương tháng 5</h3>
                                    <p><i class="far fa-clock"></i> Còn 1 ngày</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <!-- Thêm thư viện Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Script tạo biểu đồ lương -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lấy dữ liệu từ PHP
            const salaryLabels = <?php echo $salary_labels_json; ?>;
            const salaryValues = <?php echo $salary_values_json; ?>;
            
            // Tính toán dữ liệu cho biểu đồ đường xu hướng
            const trendData = [];
            for (let i = 0; i < salaryValues.length; i++) {
                if (i === 0) {
                    trendData.push(salaryValues[i]);
                } else {
                    // Sử dụng trung bình trượt để làm mịn đường xu hướng
                    const prevValue = trendData[i-1];
                    const currentValue = salaryValues[i];
                    trendData.push((prevValue * 0.3) + (currentValue * 0.7));
                }
            }
            
            // Tính toán màu sắc dựa trên sự thay đổi
            const gradientColors = salaryValues.map((value, index) => {
                if (index === 0) return 'rgba(52, 152, 219, 0.8)';
                
                const prevValue = salaryValues[index-1];
                const change = value - prevValue;
                
                if (change > 0) {
                    // Tăng - màu xanh lá
                    return 'rgba(46, 204, 113, 0.8)';
                } else if (change < 0) {
                    // Giảm - màu đỏ
                    return 'rgba(231, 76, 60, 0.8)';
                } else {
                    // Không đổi - màu xanh dương
                    return 'rgba(52, 152, 219, 0.8)';
                }
            });
            
            // Lấy context của canvas
            const ctx = document.getElementById('salaryChart').getContext('2d');
            
            // Tạo gradient cho nền biểu đồ
            const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
            gradientFill.addColorStop(0, 'rgba(52, 152, 219, 0.3)');
            gradientFill.addColorStop(1, 'rgba(52, 152, 219, 0.0)');
            
            // Tạo biểu đồ
            const salaryChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: salaryLabels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Tổng lương (VNĐ)',
                            data: salaryValues,
                            backgroundColor: gradientColors,
                            borderColor: gradientColors.map(color => color.replace('0.8', '1')),
                            borderWidth: 1,
                            borderRadius: 6,
                            barPercentage: 0.7,
                        },
                        {
                            type: 'line',
                            label: 'Xu hướng lương',
                            data: trendData,
                            borderColor: 'rgba(155, 89, 182, 1)',
                            borderWidth: 3,
                            pointBackgroundColor: 'rgba(155, 89, 182, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            fill: true,
                            backgroundColor: gradientFill,
                            tension: 0.4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                                color: 'rgba(200, 200, 200, 0.2)',
                            },
                            ticks: {
                                font: {
                                    family: 'Arial',
                                    size: 12,
                                },
                                color: '#666',
                                padding: 10,
                                // Định dạng số tiền
                                callback: function(value) {
                                    if (value >= 1000000) {
                                        return (value / 1000000).toLocaleString('vi-VN') + ' triệu';
                                    }
                                    return value.toLocaleString('vi-VN');
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false,
                            },
                            ticks: {
                                font: {
                                    family: 'Arial',
                                    size: 12,
                                },
                                color: '#666',
                                padding: 10,
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleFont: {
                                family: 'Arial',
                                size: 14,
                                weight: 'bold',
                            },
                            bodyFont: {
                                family: 'Arial',
                                size: 13,
                            },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    let label = context.dataset.label || '';
                                    
                                    if (label === 'Tổng lương (VNĐ)') {
                                        return 'Tổng lương: ' + value.toLocaleString('vi-VN') + ' VNĐ';
                                    } else if (label === 'Xu hướng lương') {
                                        return 'Xu hướng: ' + value.toLocaleString('vi-VN') + ' VNĐ';
                                    }
                                    return label + ': ' + value.toLocaleString('vi-VN') + ' VNĐ';
                                },
                                // Thêm thông tin % thay đổi so với tháng trước
                                afterBody: function(context) {
                                    const dataIndex = context[0].dataIndex;
                                    if (dataIndex > 0 && context[0].dataset.label === 'Tổng lương (VNĐ)') {
                                        const currentValue = salaryValues[dataIndex];
                                        const prevValue = salaryValues[dataIndex - 1];
                                        const percentChange = ((currentValue - prevValue) / prevValue * 100).toFixed(2);
                                        const changeText = percentChange >= 0 ? '+' + percentChange + '%' : percentChange + '%';
                                        const changeLabel = percentChange >= 0 ? 'Tăng ' : 'Giảm ';
                                        return [changeLabel + changeText + ' so với tháng trước'];
                                    }
                                    return [];
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    family: 'Arial',
                                    size: 12,
                                },
                                usePointStyle: true,
                                padding: 20,
                            }
                        },
                        title: {
                            display: true,
                            text: 'Tổng lương chi trả theo tháng',
                            font: {
                                family: 'Arial',
                                size: 16,
                                weight: 'bold',
                            },
                            color: '#2c3e50',
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        // Thêm hiệu ứng động
                        animation: {
                            duration: 2000,
                            easing: 'easeOutQuart'
                        }
                    }
                }
            });
        });
    </script>
    
    <!-- Phần bài báo cho người chưa đăng nhập -->
    <div class="articles-section">
        <div class="articles-wrapper">
            <h2 class="section-title">Bài viết mới nhất về quản lý nhân sự</h2>
            <div class="articles-container">
                <div class="article-card">
                    <div class="article-image">
                        <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" alt="Quản lý nhân sự hiệu quả">
                    </div>
                    <div class="article-content">
                        <h3>5 Chiến lược quản lý nhân sự hiệu quả trong năm 2025</h3>
                        <p class="article-meta">Đăng ngày: 25/05/2025 | Tác giả: Nguyễn Văn A</p>
                        <p class="article-excerpt">Trong thời đại số hóa, các chiến lược quản lý nhân sự cần được cập nhật liên tục để đáp ứng nhu cầu của thị trường lao động đang thay đổi nhanh chóng...</p>
                        <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="article-card">
                    <div class="article-image">
                        <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" alt="Phát triển nhân viên">
                    </div>
                    <div class="article-content">
                        <h3>Phát triển nhân viên: Chìa khóa để giữ chân nhân tài</h3>
                        <p class="article-meta">Đăng ngày: 20/05/2025 | Tác giả: Trần Thị B</p>
                        <p class="article-excerpt">Trong bối cảnh cạnh tranh ngày càng gay gắt, việc giữ chân nhân tài trở thành ưu tiên hàng đầu của các doanh nghiệp. Phát triển nhân viên không chỉ là đào tạo kỹ năng...</p>
                        <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="article-card">
                    <div class="article-image">
                        <img src="https://images.unsplash.com/photo-1573164713988-8665fc963095?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&q=80" alt="Công nghệ HR">
                    </div>
                    <div class="article-content">
                        <h3>Ứng dụng AI trong quản lý nhân sự: Xu hướng không thể bỏ qua</h3>
                        <p class="article-meta">Đăng ngày: 15/05/2025 | Tác giả: Lê Văn C</p>
                        <p class="article-excerpt">Trí tuệ nhân tạo (AI) đang dần thay đổi cách các doanh nghiệp quản lý nhân sự. Từ tuyển dụng, đánh giá hiệu suất đến dự đoán xu hướng nghỉ việc...</p>
                        <a href="#" class="read-more">Đọc tiếp <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="articles-footer">
                <a href="#" class="view-all-btn">Xem tất cả bài viết <i class="fas fa-long-arrow-alt-right"></i></a>
                <p class="articles-note">Đăng nhập để truy cập đầy đủ thư viện bài viết và tài liệu về quản lý nhân sự</p>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="css/index.css">
    
    <style>
        /* CSS cho phần bài báo */
        .articles-section {
            background-color: #f8f9fa;
            padding: 50px 0;
            margin-top: 40px;
            border-top: 1px solid #e9ecef;
            box-sizing: border-box;
            overflow: hidden;
            clear: both;
            position: relative;
            z-index: 1;
            margin-left: 250px; /* Khoảng cách từ sidebar bên trái */
            width: calc(100% - 280px); /* Chiều rộng trừ đi sidebar và thêm margin */
            float: left;
        }
        
        .articles-wrapper {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 20px;
            box-sizing: border-box;
            clear: both;
        }
        
        .section-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: #3498db;
        }
        
        .articles-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
            width: 100%;
            box-sizing: border-box;
        }
        
        @media (max-width: 768px) {
            .articles-container {
                grid-template-columns: 1fr;
            }
        }
        
        .article-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .article-image {
            height: 200px;
            overflow: hidden;
        }
        
        .article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .article-card:hover .article-image img {
            transform: scale(1.05);
        }
        
        .article-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .article-content h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 18px;
            line-height: 1.4;
        }
        
        .article-meta {
            color: #7f8c8d;
            font-size: 12px;
            margin-bottom: 12px;
        }
        
        .article-excerpt {
            color: #34495e;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 4.8em; /* 3 dòng x 1.6 line-height */
            text-overflow: ellipsis;
            word-wrap: break-word;
            flex-grow: 1;
        }
        
        .read-more {
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: color 0.3s ease;
            margin-top: auto;
            align-self: flex-start;
        }
        
        .read-more i {
            margin-left: 5px;
            transition: transform 0.3s ease;
        }
        
        .read-more:hover {
            color: #2980b9;
        }
        
        .read-more:hover i {
            transform: translateX(3px);
        }
        
        .articles-footer {
            text-align: center;
            margin-top: 40px;
        }
        
        .view-all-btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        
        .view-all-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .view-all-btn i {
            margin-left: 5px;
        }
        
        .articles-note {
            color: #7f8c8d;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</body>
</html>
