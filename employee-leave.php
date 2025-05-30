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


// Xử lý form gửi đơn nghỉ phép
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Tính số ngày nghỉ
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $total_days = $interval->days + 1; // +1 vì tính cả ngày bắt đầu
    
    // Kiểm tra ngày hợp lệ
    if ($end < $start) {
        $error_message = "Ngày kết thúc phải sau ngày bắt đầu";
    } else {
        // Lưu đơn nghỉ phép vào database
        $insert_query = "INSERT INTO leave_requests (employee_id, employee_name, department, position, leave_type, start_date, end_date, total_days, reason) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("issssssis", $employee_id, $employee_name, $employee_department, $employee_position, $leave_type, $start_date, $end_date, $total_days, $reason);
        
        if ($stmt->execute()) {
            $leave_id = $stmt->insert_id;
            
            // Gửi thông báo đến admin
            $notification_message = "Nhân viên $employee_name đã gửi đơn xin nghỉ phép từ " . date('d/m/Y', strtotime($start_date)) . " đến " . date('d/m/Y', strtotime($end_date)) . ".";
            
            // Lấy danh sách admin để gửi thông báo
            $admin_query = "SELECT id FROM users WHERE role = 'admin'";
            $admin_result = $conn->query($admin_query);
            
            if ($admin_result && $admin_result->num_rows > 0) {
                while ($admin = $admin_result->fetch_assoc()) {
                    $insert_notification = "INSERT INTO notifications (recipient_id, recipient_type, sender_id, sender_name, type, message, reference_id) 
                                           VALUES (?, 'admin', ?, ?, 'leave_request', ?, ?)";
                    
                    $notify_stmt = $conn->prepare($insert_notification);
                    $notify_stmt->bind_param("iissi", $admin['id'], $employee_id, $employee_name, $notification_message, $leave_id);
                    $notify_stmt->execute();
                }
            }
            
            // Ghi log hoạt động
            $log_query = "INSERT INTO activities (user_id, action, description) VALUES (?, 'leave_request', ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_description = "Đã gửi đơn xin nghỉ phép từ " . date('d/m/Y', strtotime($start_date)) . " đến " . date('d/m/Y', strtotime($end_date));
            $log_stmt->bind_param("ss", $employee_code, $log_description);
            $log_stmt->execute();
            
            $success_message = "Đơn xin nghỉ phép đã được gửi thành công và đang chờ phê duyệt.";
        } else {
            $error_message = "Có lỗi xảy ra khi gửi đơn xin nghỉ phép. Vui lòng thử lại.";
        }
    }
}

// Lấy danh sách đơn nghỉ phép của nhân viên
$leave_query = "SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC";
$leave_stmt = $conn->prepare($leave_query);
$leave_stmt->bind_param("i", $employee_id);
$leave_stmt->execute();
$leave_result = $leave_stmt->get_result();
$leave_requests = [];

while ($row = $leave_result->fetch_assoc()) {
    $leave_requests[] = $row;
}

// Hàm định dạng loại nghỉ phép
function get_leave_type_text($type) {
    switch ($type) {
        case 'annual':
            return 'Nghỉ phép năm';
        case 'sick':
            return 'Nghỉ ốm';
        case 'personal':
            return 'Nghỉ việc riêng';
        case 'maternity':
            return 'Nghỉ thai sản';
        case 'bereavement':
            return 'Nghỉ tang';
        case 'unpaid':
            return 'Nghỉ không lương';
        default:
            return 'Khác';
    }
}

// Hàm định dạng trạng thái
function get_status_badge($status) {
    switch ($status) {
        case 'pending':
            return '<div class="status-badge status-pending">Đang chờ duyệt</div>';
        case 'approved':
            return '<div class="status-badge status-approved">Đã duyệt</div>';
        case 'rejected':
            return '<div class="status-badge status-rejected">Từ chối</div>';
        case 'cancelled':
            return '<div class="status-badge status-cancelled">Đã hủy</div>';
        case 'expired':
            return '<div class="status-badge status-expired">Đã hết hạn</div>';
        default:
            return '<div class="status-badge status-unknown">Không xác định</div>';
    }
}







//HTML
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nghỉ Phép - Hệ Thống Quản Lý Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/employee-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <base href="/PHP/">
    <link rel="stylesheet" href="css/employee-leave.css">
    
</head>
<body>
    <div class="employee-dashboard">
        <!-- Header -->
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
                    <li class="active">
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
                <h1 class="page-title">Quản Lý Nghỉ Phép</h1>
                
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
                
                <div class="leave-container">
                    <!-- Form đăng ký nghỉ phép -->
                    <div class="leave-form-container">
                        <div class="section-header">
                            <h2>Đăng Ký Nghỉ Phép</h2>
                        </div>
                        
                        <form class="leave-form" method="POST" action="">
                            <div class="form-group">
                                <label for="leave_type">Loại nghỉ phép</label>
                                <select id="leave_type" name="leave_type" required>
                                    <option value="annual">Nghỉ phép năm</option>
                                    <option value="sick">Nghỉ ốm</option>
                                    <option value="personal">Nghỉ việc riêng</option>
                                    <option value="maternity">Nghỉ thai sản</option>
                                    <option value="bereavement">Nghỉ tang</option>
                                    <option value="unpaid">Nghỉ không lương</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date">Ngày bắt đầu</label>
                                <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">Ngày kết thúc</label>
                                <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="reason">Lý do</label>
                                <textarea id="reason" name="reason" required placeholder="Nhập lý do xin nghỉ phép..."></textarea>
                            </div>
                            
                            <button type="submit" name="submit_leave" class="submit-btn">Gửi đơn xin nghỉ phép</button>
                        </form>
                    </div>
                    
                    <!-- Danh sách đơn nghỉ phép -->
                    <div class="leave-list-container">
                        <div class="section-header">
                            <h2>Lịch Sử Nghỉ Phép</h2>
                        </div>
                        
                        <?php if (empty($leave_requests)): ?>
                            <p>Bạn chưa có đơn xin nghỉ phép nào.</p>
                        <?php else: ?>
                            <table class="leave-table">
                                <thead>
                                    <tr>
                                        <th>Loại</th>
                                        <th>Thời gian</th>
                                        <th>Ngày tạo</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leave_requests as $leave): ?>
                                        <tr>
                                            <td><?php echo get_leave_type_text($leave['leave_type']); ?></td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($leave['start_date'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($leave['end_date'])); ?>
                                                <div class="leave-details">
                                                    <strong>Số ngày:</strong> <?php echo $leave['total_days']; ?> ngày
                                                </div>
                                                <div class="leave-reason">
                                                    <strong>Lý do:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                                                </div>
                                                <?php if (!empty($leave['admin_comment']) && ($leave['status'] == 'approved' || $leave['status'] == 'rejected')): ?>
                                                    <div class="admin-comment">
                                                        <strong>Phản hồi:</strong> <?php echo htmlspecialchars($leave['admin_comment']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($leave['created_at'])); ?></td>
                                            <td style="text-align: center;"><div style="display: inline-block; width: 100%;"><?php echo get_status_badge($leave['status']); ?></div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // JavaScript để tính số ngày nghỉ
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Đảm bảo ngày kết thúc luôn sau ngày bắt đầu
            startDateInput.addEventListener('change', function() {
                if (endDateInput.value && new Date(endDateInput.value) < new Date(startDateInput.value)) {
                    endDateInput.value = startDateInput.value;
                }
                endDateInput.min = startDateInput.value;
            });
        });
    </script>
</body>
</html>
