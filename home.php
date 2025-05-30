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
    <link rel="stylesheet" href="css/home.css">
    <style>
        .notification-badge {
            position: absolute;
            right: 10px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .nav-links a {
            position: relative;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            margin-left: auto;
        }
        
        .auth-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-login, .btn-register {
            display: inline-flex;
            align-items: center;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            margin-left: 10px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-login {
            background-color: #3498db;
            color: white;
            border: 1px solid #2980b9;
        }
        
        .btn-register {
            background-color: #2ecc71;
            color: white;
            border: 1px solid #27ae60;
        }
        
        .btn-login i, .btn-register i {
            margin-right: 5px;
        }
        
        .btn-login:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-register:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .birthday-alert {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            color: #2c3e50;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f5f6fa;
            transition: all 0.3s ease;
            margin-left: 0;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .main-content.full-width {
            padding: 20px 40px;
        }
        
        .dashboard-content {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }
        
        @media (max-width: 1200px) {
            .main-content {
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .user-info {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Main Content -->
        <div class="main-content full-width">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Tìm kiếm...">
                </div>
                <div class="user-info">
                    <div class="auth-buttons">
                        <a href="login.php" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Đăng nhập
                        </a>
                        <a href="register.php" class="btn-register">
                            <i class="fas fa-user-plus"></i> Đăng ký
                        </a>
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

                
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <!-- Thêm thư viện Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Script tạo biểu đồ lương -->
    <style>
        /* Articles Section Styles */
        .articles-section {
            width: 100%;
            padding: 0 40px 40px;
            box-sizing: border-box;
        }
        
        .articles-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 22px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #3498db;
            border-radius: 3px;
        }
        
        .articles-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .article-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .article-image {
            height: 180px;
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
        }
        
        .article-content h3 {
            color: #2c3e50;
            margin: 0 0 10px;
            font-size: 18px;
            line-height: 1.4;
        }
        
        .article-meta {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 12px;
        }
        
        .article-excerpt {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .read-more {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            transition: color 0.3s ease;
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
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .view-all-btn {
            display: inline-flex;
            align-items: center;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-bottom: 15px;
        }
        
        .view-all-btn i {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }
        
        .view-all-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .view-all-btn:hover i {
            transform: translateX(5px);
        }
        
        .articles-note {
            color: #7f8c8d;
            font-size: 14px;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .articles-section {
                padding: 0 20px 30px;
            }
            
            .articles-wrapper {
                padding: 20px;
            }
            
            .articles-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
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
    
    
</body>
</html>
