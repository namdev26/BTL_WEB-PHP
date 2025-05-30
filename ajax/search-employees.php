<?php
// Kết nối database
require_once '../config/db.php';

// Kiểm tra phiên đăng nhập
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Lấy tham số tìm kiếm
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Xây dựng câu truy vấn cơ bản
$query = "SELECT * FROM employees WHERE 1=1";

// Thêm điều kiện tìm kiếm nếu có
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (full_name LIKE '%{$search}%' OR employee_id LIKE '%{$search}%' OR email LIKE '%{$search}%')";
}

// Lọc theo phòng ban
if (!empty($department)) {
    $department = $conn->real_escape_string($department);
    $query .= " AND department = '{$department}'";
}

// Lọc theo trạng thái
if (!empty($status)) {
    $status = $conn->real_escape_string($status);
    $query .= " AND status = '{$status}'";
}

// Thêm sắp xếp
$query .= " ORDER BY id DESC";

// Thực hiện truy vấn
$result = $conn->query($query);
$employees = [];

if ($result && $result->num_rows > 0) {
    while ($employee = $result->fetch_assoc()) {
        // Định dạng trạng thái
        $status_class = ($employee['status'] == 'active') ? 'active' : 'inactive';
        $status_text = ($employee['status'] == 'active') ? 'Đang làm việc' : 'Đã nghỉ việc';
        
        // Thêm thông tin vào mảng kết quả
        $employees[] = [
            'id' => $employee['id'],
            'employee_id' => $employee['employee_id'],
            'full_name' => $employee['full_name'],
            'email' => $employee['email'],
            'department' => $employee['department'],
            'position' => $employee['position'],
            'status' => $employee['status'],
            'status_class' => $status_class,
            'status_text' => $status_text
        ];
    }
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'employees' => $employees,
    'count' => count($employees)
]);
?>
