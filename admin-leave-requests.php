<?php
// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: home.php');
    exit;
}

// Database connection
require_once 'config/db.php';
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_role = $_SESSION['admin_role'];

// Initialize variables
$success_message = '';
$error_message = '';
$show_form = false;
$form_type = ''; // 'approve' or 'reject'
$current_leave_id = 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['show_form'])) {
        $show_form = true;
        $form_type = $_POST['form_type'] ?? '';
        $current_leave_id = isset($_POST['leave_id']) ? (int)$_POST['leave_id'] : 0;
    } elseif (isset($_POST['cancel_action'])) {
        $show_form = false;
    } elseif (isset($_POST['process_leave'])) {
        // Validate leave_id and action
        if (!isset($_POST['leave_id']) || !is_numeric($_POST['leave_id'])) {
            $error_message = "ID đơn nghỉ phép không hợp lệ.";
            $show_form = true;
            $form_type = $_POST['action'] ?? '';
            $current_leave_id = 0;
        } else {
            $leave_id = (int)$_POST['leave_id']; // Ensure it's an integer
            $action = $_POST['action'] ?? '';
            $comment = trim($_POST['comment'] ?? '');

            if ($leave_id <= 0) {
                $error_message = "ID đơn nghỉ phép không hợp lệ.";
                $show_form = true;
                $form_type = $action;
                $current_leave_id = $leave_id;
            } elseif ($action === 'reject' && empty($comment)) {
                $error_message = "Vui lòng nhập lý do từ chối.";
                $show_form = true;
                $form_type = 'reject';
                $current_leave_id = $leave_id;
            } else {
                // Update leave request status
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                $update_query = "UPDATE leave_requests SET status = ?, admin_comment = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                if ($stmt === false) {
                    $error_message = "Lỗi chuẩn bị câu lệnh: " . $conn->error;
                } else {
                    // Create references for bind_param
                    $bind_status = $status;
                    $bind_comment = $comment;
                    $bind_leave_id = $leave_id;
                    $stmt->bind_param("ssi", $bind_status, $bind_comment, $bind_leave_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        // Fetch leave request details
                        $leave_query = "SELECT * FROM leave_requests WHERE id = ?";
                        $leave_stmt = $conn->prepare($leave_query);
                        $leave_stmt->bind_param("i", $bind_leave_id);
                        $leave_stmt->execute();
                        $leave_result = $leave_stmt->get_result();
                        $leave_request = $leave_result->fetch_assoc();

                        if ($leave_request) {
                            // Send notification to employee
                            $notification_message = "Đơn xin nghỉ phép của bạn từ " . date('d/m/Y', strtotime($leave_request['start_date'])) .
                                                   " đến " . date('d/m/Y', strtotime($leave_request['end_date'])) .
                                                   " đã được " . ($action === 'approve' ? 'phê duyệt' : 'từ chối') . ".";
                            $insert_notification = "INSERT INTO notifications (recipient_id, recipient_type, sender_id, sender_name, type, message, reference_id) 
                                                   VALUES (?, 'employee', ?, ?, 'leave_response', ?, ?)";
                            $notify_stmt = $conn->prepare($insert_notification);
                            $recipient_id = $leave_request['employee_id'];
                            $notify_stmt->bind_param("iissi", $recipient_id, $admin_id, $admin_username, $notification_message, $bind_leave_id);
                            $notify_stmt->execute();
                            $notify_stmt->close();

                            // Log activity
                            $log_query = "INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)";
                            $log_stmt = $conn->prepare($log_query);
                            $log_description = ($action === 'approve' ? 'Phê duyệt' : 'Từ chối') . " đơn xin nghỉ phép của " . $leave_request['employee_name'];
                            $log_action = $action . '_leave';
                            $log_stmt->bind_param("sss", $admin_username, $log_action, $log_description);
                            $log_stmt->execute();
                            $log_stmt->close();

                            // Mark related notifications as read
                            $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE reference_id = ? AND type = 'leave_request' AND recipient_id = ?";
                            $mark_read_stmt = $conn->prepare($mark_read_query);
                            $mark_read_stmt->bind_param("ii", $bind_leave_id, $admin_id);
                            $mark_read_stmt->execute();
                            $mark_read_stmt->close();

                            $success_message = "Đã " . ($action === 'approve' ? 'phê duyệt' : 'từ chối') . " đơn xin nghỉ phép thành công.";
                        } else {
                            $error_message = "Không tìm thấy thông tin đơn nghỉ phép.";
                        }
                        $leave_stmt->close();
                    } else {
                        $error_message = "Không thể xử lý đơn xin nghỉ phép. Vui lòng thử lại.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch leave requests
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$leave_requests = [];
$query = "SELECT * FROM leave_requests";
$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " WHERE status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $leave_requests[] = $row;
}
$stmt->close();

// Count unread notifications
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND recipient_type = 'admin' AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $admin_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'];
$unread_stmt->close();

// Format status badge
function get_status_badge($status) {
    $badges = [
        'pending' => '<div class="status-badge status-pending">Đang chờ duyệt</div>',
        'approved' => '<div class="status-badge status-approved">Đã duyệt</div>',
        'rejected' => '<div class="status-badge status-rejected">Từ chối</div>',
        'cancelled' => '<div class="status-badge status-cancelled">Đã hủy</div>',
        'expired' => '<div class="status-badge status-expired">Đã hết hạn</div>',
    ];
    return $badges[$status] ?? '<div class="status-badge status-unknown">Không xác định</div>';
}

// Format leave type
function get_leave_type_text($type) {
    $types = [
        'annual' => 'Nghỉ phép năm',
        'sick' => 'Nghỉ ốm',
        'personal' => 'Nghỉ việc riêng',
        'maternity' => 'Nghỉ thai sản',
        'bereavement' => 'Nghỉ tang',
        'unpaid' => 'Nghỉ không lương',
    ];
    return $types[$type] ?? 'Khác';
}






//HTML



?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Đơn Nghỉ Phép - Hệ Thống Quản Lý Nhân Viên</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
                <h2>Quản Lý</h2>
            </div>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i><span>Tổng quan</span></a></li>
                <li><a href="nhan-vien.php"><i class="fas fa-users"></i><span>Nhân viên</span></a></li>
                <li><a href="tinh-luong.php"><i class="fas fa-money-bill-wave"></i><span>Tính lương</span></a></li>
                <li><a href="admin-leave-requests.php" class="active"><i class="fas fa-calendar-minus"></i><span>Đơn nghỉ phép</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i><span>Thông báo</span></a></li>
                <li><a href="cai-dat.php"><i class="fas fa-cog"></i><span>Cài đặt</span></a></li>
            </ul>
        </div>
        <div class="main-content">
            <div class="top-bar">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Tìm kiếm...">
                </div>
                <div class="user-info">
                    <div class="notification">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-profile">
                        <span>Xin chào, <?php echo htmlspecialchars($admin_username); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            <h1 class="page-title">Quản Lý Đơn Nghỉ Phép</h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <div class="action-form">
                    <h3><?php echo $form_type === 'approve' ? 'Phê duyệt đơn nghỉ phép' : 'Từ chối đơn nghỉ phép'; ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="leave_id" value="<?php echo $current_leave_id; ?>">
                        <input type="hidden" name="action" value="<?php echo $form_type; ?>">
                        <div class="form-group">
                            <label for="comment">Bình luận</label>
                            <textarea name="comment" id="comment" placeholder="Nhập bình luận (bắt buộc khi từ chối)"></textarea>
                        </div>
                        <div class="action-buttons">
                            <button type="submit" name="process_leave" class="btn btn-<?php echo $form_type === 'approve' ? 'approve' : 'reject'; ?>">
                                Xác nhận <?php echo $form_type === 'approve' ? 'phê duyệt' : 'từ chối'; ?>
                            </button>
                            <button type="submit" name="cancel_action" class="btn btn-cancel">Hủy</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            <style>
        

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            justify-content: flex-end; /* Căn các nút về bên phải */
        }

        .filter-btn {
            padding: 8px 16px;
            background-color: #f8f9fa;
            color: #495057;
            text-decoration: none;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .filter-btn.active, .filter-btn:hover {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }


    </style>
            <div class="filter-buttons">
                <a href="?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">Tất cả</a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Đang chờ duyệt</a>
                <a href="?status=approved" class="filter-btn <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">Đã duyệt</a>
                <a href="?status=rejected" class="filter-btn <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Từ chối</a>
            </div>

            <?php if (empty($leave_requests)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>Không có đơn nghỉ phép nào <?php echo $status_filter !== 'all' ? 'trong trạng thái này' : ''; ?>.</p>
                </div>
            <?php else: ?>
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Thông tin nghỉ phép</th>
                            <th>Ngày tạo</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $leave): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($leave['employee_name']); ?></strong>
                                    <div class="leave-details">
                                        <?php echo htmlspecialchars($leave['position']); ?> - <?php echo htmlspecialchars($leave['department']); ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo get_leave_type_text($leave['leave_type']); ?></strong>
                                    <div class="leave-details">
                                        <strong>Thời gian:</strong> <?php echo date('d/m/Y', strtotime($leave['start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($leave['end_date'])); ?> (<?php echo $leave['total_days']; ?> ngày)
                                    </div>
                                    <div class="leave-reason">
                                        <strong>Lý do:</strong> <?php echo htmlspecialchars($leave['reason']); ?>
                                    </div>
                                    <?php if (!empty($leave['admin_comment']) && in_array($leave['status'], ['approved', 'rejected'])): ?>
                                        <div class="leave-details">
                                            <strong>Phản hồi:</strong> <?php echo htmlspecialchars($leave['admin_comment']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($leave['created_at'])); ?></td>
                                <td><?php echo get_status_badge($leave['status']); ?></td>
                                <td>
                                    <?php if ($leave['status'] === 'pending'): ?>
                                        <div class="action-buttons">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                <input type="hidden" name="form_type" value="approve">
                                                <button type="submit" name="show_form" class="btn btn-approve">
                                                    <i class="fas fa-check"></i> Duyệt
                                                </button>
                                            </form>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                <input type="hidden" name="form_type" value="reject">
                                                <button type="submit" name="show_form" class="btn btn-reject">
                                                    <i class="fas fa-times"></i> Từ chối
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">Đã xử lý</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>