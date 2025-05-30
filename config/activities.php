<?php
// Hàm thêm hoạt động mới vào hệ thống
function add_activity($conn, $action, $description, $user_id = null) {
    // Nếu user_id không được cung cấp, sử dụng ID của người dùng hiện tại (nếu đã đăng nhập)
    if ($user_id === null && isset($_SESSION['admin_username'])) {
        $user_id = $_SESSION['admin_username'];
    }
    
    // Chuẩn bị câu lệnh SQL
    $stmt = $conn->prepare("INSERT INTO activities (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
    
    if ($stmt) {
        $stmt->bind_param("sss", $user_id, $action, $description);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    return false;
}

// Hàm lấy danh sách hoạt động gần đây
function get_recent_activities($conn, $limit = 10) {
    $activities = [];
    
    $query = "SELECT * FROM activities ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        $stmt->close();
    }
    
    return $activities;
}

// Hàm định dạng thời gian hoạt động thành văn bản dễ đọc
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
?>
