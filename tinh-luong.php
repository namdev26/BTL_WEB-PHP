<?php
session_start();
// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Lấy thông tin tháng năm hiện tại
$current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Tạo danh sách tháng và năm để hiển thị trong dropdown
$months = [
    1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
    5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
    9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
];

$years = range(date('Y') - 5, date('Y') + 1);

// Lấy danh sách nhân viên
$query = "SELECT id, employee_id, full_name, department, position, salary FROM employees WHERE status = 'active' ORDER BY department, full_name";
$result = $conn->query($query);
$employees = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Xử lý tính lương
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_salary'])) {
    // Lấy tháng và năm từ form
    $salary_month = isset($_POST['salary_month']) ? intval($_POST['salary_month']) : $current_month;
    $salary_year = isset($_POST['salary_year']) ? intval($_POST['salary_year']) : $current_year;
    
    // Tạo bảng lương nếu chưa có
    $create_table_query = "CREATE TABLE IF NOT EXISTS salary_records (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        employee_id INT(11) NOT NULL,
        salary_month INT(2) NOT NULL,
        salary_year INT(4) NOT NULL,
        base_salary DECIMAL(15,2) NOT NULL,
        bonus DECIMAL(15,2) DEFAULT 0,
        deductions DECIMAL(15,2) DEFAULT 0,
        total_salary DECIMAL(15,2) NOT NULL,
        status ENUM('pending', 'approved', 'paid') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY employee_month_year (employee_id, salary_month, salary_year)
    )";
    
    if ($conn->query($create_table_query) === FALSE) {
        $error_message = "Lỗi tạo bảng lương: " . $conn->error;
    } else {
        // Xử lý dữ liệu từ form
        foreach ($_POST['employee'] as $employee_id => $data) {
            $base_salary = floatval($data['base_salary']);
            $bonus = floatval($data['bonus']);
            $deductions = floatval($data['deductions']);
            $total_salary = $base_salary + $bonus - $deductions;
            $notes = $conn->real_escape_string($data['notes']);
            
            // Kiểm tra xem đã có bản ghi lương cho nhân viên trong tháng này chưa
            $check_query = "SELECT id FROM salary_records WHERE employee_id = ? AND salary_month = ? AND salary_year = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("iii", $employee_id, $salary_month, $salary_year);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Cập nhật bản ghi hiện có
                $update_query = "UPDATE salary_records SET 
                    base_salary = ?, 
                    bonus = ?, 
                    deductions = ?, 
                    total_salary = ?, 
                    notes = ? 
                    WHERE employee_id = ? AND salary_month = ? AND salary_year = ?";
                
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("dddssiii", 
                    $base_salary, 
                    $bonus, 
                    $deductions, 
                    $total_salary, 
                    $notes, 
                    $employee_id, 
                    $salary_month, 
                    $salary_year
                );
                
                if (!$update_stmt->execute()) {
                    $error_message = "Lỗi cập nhật lương: " . $update_stmt->error;
                    break;
                }
            } else {
                // Thêm bản ghi mới
                $insert_query = "INSERT INTO salary_records 
                    (employee_id, salary_month, salary_year, base_salary, bonus, deductions, total_salary, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("iiiiddds", 
                    $employee_id, 
                    $salary_month, 
                    $salary_year, 
                    $base_salary, 
                    $bonus, 
                    $deductions, 
                    $total_salary, 
                    $notes
                );
                
                if (!$insert_stmt->execute()) {
                    $error_message = "Lỗi thêm bản ghi lương: " . $insert_stmt->error;
                    break;
                }
            }
        }
        
        if (empty($error_message)) {
            $success_message = "Đã lưu thông tin lương thành công!";
            
            // Ghi lại hoạt động tính lương
            $month_name = $months[$salary_month];
            
            // Lấy tên của các nhân viên được tính lương
            $employee_names = [];
            foreach (array_keys($_POST['employee']) as $emp_id) {
                $name_query = "SELECT full_name FROM employees WHERE id = ?";
                $name_stmt = $conn->prepare($name_query);
                $name_stmt->bind_param("i", $emp_id);
                $name_stmt->execute();
                $name_result = $name_stmt->get_result();
                if ($name_result && $row = $name_result->fetch_assoc()) {
                    $employee_names[] = $row['full_name'];
                }
                $name_stmt->close();
            }
            
            // Tạo mô tả hoạt động
            if (count($employee_names) == 1) {
                $activity_description = "Tính lương {$month_name} năm {$salary_year} cho nhân viên: {$employee_names[0]}";
            } else {
                // Nếu có nhiều nhân viên, hiển thị tên của 2 người đầu tiên và số người còn lại
                $first_names = array_slice($employee_names, 0, 2);
                $remaining = count($employee_names) - 2;
                
                if ($remaining > 0) {
                    $activity_description = "Tính lương {$month_name} năm {$salary_year} cho nhân viên: " . implode(", ", $first_names) . " và {$remaining} người khác";
                } else {
                    $activity_description = "Tính lương {$month_name} năm {$salary_year} cho nhân viên: " . implode(", ", $employee_names);
                }
            }
            
            // Kiểm tra bảng activities đã tồn tại chưa
            $check_table = $conn->query("SHOW TABLES LIKE 'activities'");
            if ($check_table->num_rows == 0) {
                // Tạo bảng nếu chưa tồn tại
                $create_activities_table = "CREATE TABLE IF NOT EXISTS activities (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50),
                    action VARCHAR(50) NOT NULL,
                    description TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->query($create_activities_table);
            }
            
            // Thêm hoạt động
            $activity_query = "INSERT INTO activities (user_id, action, description, created_at) VALUES (?, 'calculate_salary', ?, NOW())"; 
            $activity_stmt = $conn->prepare($activity_query);
            $user_id = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'admin';
            $activity_stmt->bind_param("ss", $user_id, $activity_description);
            $activity_stmt->execute();
            $activity_stmt->close();
            
            // Cập nhật lại tháng và năm hiện tại
            $current_month = $salary_month;
            $current_year = $salary_year;
        }
    }
}

// Lấy dữ liệu lương đã tính (nếu có)
$salary_data = [];
$salary_query = "SELECT * FROM salary_records WHERE salary_month = ? AND salary_year = ?";
$salary_stmt = $conn->prepare($salary_query);

if ($salary_stmt) {
    $salary_stmt->bind_param("ii", $current_month, $current_year);
    $salary_stmt->execute();
    $salary_result = $salary_stmt->get_result();
    
    if ($salary_result && $salary_result->num_rows > 0) {
        while ($row = $salary_result->fetch_assoc()) {
            $salary_data[$row['employee_id']] = $row;
        }
    }
}



















?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tính Lương Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/tinh-luong.css">
    
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
                <li class="active">
                    <a href="tinh-luong.php" class="active">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Tính lương</span>
                    </a>
                </li>
                
                <li>
                    <a href="admin-leave-requests.php">
                        <i class="fas fa-calendar-minus"></i>
                        <span>Đơn nghỉ phép</span>
                        <?php 
                        // Lấy số lượng thông báo chưa đọc
                        $unread_count = 0;
                        $check_notifications = $conn->query("SHOW TABLES LIKE 'notifications'");
                        if ($check_notifications->num_rows > 0) {
                            $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND recipient_type = 'admin' AND is_read = 0";
                            $unread_stmt = $conn->prepare($unread_query);
                            $unread_stmt->bind_param("i", $_SESSION['admin_id']);
                            $unread_stmt->execute();
                            $unread_result = $unread_stmt->get_result();
                            $unread_count = $unread_result->fetch_assoc()['count'];
                        }
                        ?>
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
                    
                    <div class="user">
                        <img src="img/avatar.jpg" alt="User Avatar">
                        <span>Xin chào, Admin</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <h1 class="page-title">Tính Lương Nhân Viên</h1>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <div class="salary-container">
                    <form action="" method="GET">
                        <div class="month-selector">
                            <div>
                                <label for="month">Chọn tháng:</label>
                                <select id="month" name="month">
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($num == $current_month) ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="year">Năm:</label>
                                <select id="year" name="year">
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($year == $current_year) ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit"><i class="fas fa-search"></i> Xem bảng lương</button>
                        </div>
                    </form>
                    
                    <?php if (count($employees) > 0): ?>
                    <form action="" method="POST">
                        <input type="hidden" name="salary_month" value="<?php echo $current_month; ?>">
                        <input type="hidden" name="salary_year" value="<?php echo $current_year; ?>">
                        
                        <table class="salary-table">
                            <colgroup>
                                <col style="width: 7%;"> <!-- Mã NV -->
                                <col style="width: 16%;"> <!-- Họ và tên -->
                                <col style="width: 13%;"> <!-- Chức vụ -->
                                <col style="width: 15%;"> <!-- Lương cơ bản -->
                                <col style="width: 11%;"> <!-- Thưởng -->
                                <col style="width: 11%;"> <!-- Khấu trừ -->
                                <col style="width: 13%;"> <!-- Tổng lương -->
                                <col style="width: 14%;"> <!-- Ghi chú -->
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Mã NV</th>
                                    <th>Họ và tên</th>
                                    <th>Chức vụ</th>
                                    <th>Lương cơ bản</th>
                                    <th>Thưởng</th>
                                    <th>Khấu trừ</th>
                                    <th>Tổng lương</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $current_department = '';
                                $total_salary = 0;
                                
                                foreach ($employees as $employee): 
                                    // Hiển thị header phòng ban nếu khác với phòng ban trước đó
                                    if ($employee['department'] != $current_department):
                                        $current_department = $employee['department'];
                                ?>
                                <tr class="department-header">
                                    <td colspan="10"><?php echo htmlspecialchars($current_department); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php
                                // Lấy dữ liệu lương đã tính (nếu có)
                                $base_salary = $employee['salary'];
                                $bonus = 0;
                                $deductions = 0;
                                $notes = '';
                                
                                if (isset($salary_data[$employee['id']])) {
                                    $salary_record = $salary_data[$employee['id']];
                                    $base_salary = $salary_record['base_salary'];
                                    $bonus = $salary_record['bonus'];
                                    $deductions = $salary_record['deductions'];
                                    $notes = $salary_record['notes'];
                                }
                                
                                $total = $base_salary + $bonus - $deductions;
                                $total_salary += $total;
                                ?>
                                
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td>
                                        <input type="number" name="employee[<?php echo $employee['id']; ?>][base_salary]" value="<?php echo $base_salary; ?>" step="100000" class="base-salary" data-employee-id="<?php echo $employee['id']; ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="employee[<?php echo $employee['id']; ?>][bonus]" value="<?php echo $bonus; ?>" step="100000" class="bonus" data-employee-id="<?php echo $employee['id']; ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="employee[<?php echo $employee['id']; ?>][deductions]" value="<?php echo $deductions; ?>" step="10000" class="deductions" data-employee-id="<?php echo $employee['id']; ?>">
                                    </td>
                                    <td class="total-salary" data-employee-id="<?php echo $employee['id']; ?>">
                                        <?php echo number_format($total, 0, ',', '.'); ?>đ
                                    </td>
                                    <td>
                                        <textarea name="employee[<?php echo $employee['id']; ?>][notes]" rows="2"><?php echo htmlspecialchars($notes); ?></textarea>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <tr class="total-row">
                                    <td colspan="6" style="text-align: right;"><strong>Tổng cộng:</strong></td>
                                    <td id="grand-total"><?php echo number_format($total_salary, 0, ',', '.'); ?>đ</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="form-actions">
                            <button type="submit" name="calculate_salary" class="btn-calculate">
                                <i class="fas fa-save"></i> Lưu thông tin lương
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <p>Không có nhân viên nào trong hệ thống.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lấy tất cả các trường nhập liệu
            const bonusInputs = document.querySelectorAll('.bonus');
            const deductionsInputs = document.querySelectorAll('.deductions');
            const baseSalaryInputs = document.querySelectorAll('.base-salary');
            
            // Hàm tính lương tổng
            function calculateTotalSalary(employeeId) {
                const baseSalary = parseFloat(document.querySelector(`.base-salary[data-employee-id="${employeeId}"]`).value) || 0;
                const bonus = parseFloat(document.querySelector(`.bonus[data-employee-id="${employeeId}"]`).value) || 0;
                const deductions = parseFloat(document.querySelector(`.deductions[data-employee-id="${employeeId}"]`).value) || 0;
                
                const total = baseSalary + bonus - deductions;
                document.querySelector(`.total-salary[data-employee-id="${employeeId}"]`).textContent = formatCurrency(total);
                
                // Cập nhật tổng cộng
                updateGrandTotal();
            }
            
            // Hàm tính tổng cộng
            function updateGrandTotal() {
                let grandTotal = 0;
                document.querySelectorAll('.total-salary').forEach(cell => {
                    if (!cell.classList.contains('total-row')) {
                        const value = parseFloat(cell.textContent.replace(/\D/g, '')) || 0;
                        grandTotal += value;
                    }
                });
                
                document.getElementById('grand-total').textContent = formatCurrency(grandTotal);
            }
            
            // Hàm định dạng tiền tệ
            function formatCurrency(amount) {
                return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount)
                    .replace('₫', 'đ')
                    .replace(',', '.');
            }
            
            // Thêm sự kiện cho các trường nhập liệu
            const allInputs = [...bonusInputs, ...deductionsInputs, ...baseSalaryInputs];
            allInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const employeeId = this.getAttribute('data-employee-id');
                    calculateTotalSalary(employeeId);
                });
            });
            
            // Tính toán ban đầu
            baseSalaryInputs.forEach(input => {
                const employeeId = input.getAttribute('data-employee-id');
                calculateTotalSalary(employeeId);
            });
        });
    </script>
</body>
</html>
