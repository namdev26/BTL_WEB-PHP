<?php
session_start();
// Simple authentication check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Xử lý tìm kiếm
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Xây dựng câu truy vấn cơ bản
$query = "SELECT * FROM employees WHERE 1=1";

// Thêm điều kiện tìm kiếm nếu có
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (full_name LIKE '%{$search}%' OR employee_id LIKE '%{$search}%' OR email LIKE '%{$search}%')";
}

// Lọc theo phòng ban
if (!empty($department_filter)) {
    $department_filter = $conn->real_escape_string($department_filter);
    $query .= " AND department = '{$department_filter}'";
}

// Lọc theo trạng thái
if (!empty($status_filter)) {
    $status_filter = $conn->real_escape_string($status_filter);
    $query .= " AND status = '{$status_filter}'";
}

// Thêm sắp xếp
$query .= " ORDER BY id DESC";

// Thực hiện truy vấn
$result = $conn->query($query);

// Lấy danh sách phòng ban để hiển thị trong bộ lọc
$dept_query = "SELECT DISTINCT department FROM employees ORDER BY department ASC";
$dept_result = $conn->query($dept_query);
$departments = [];
if ($dept_result && $dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}























?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Nhân Viên</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/nhan_vien.css">
    
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
                <li class="active">
                    <a href="nhan-vien.php" class="active">
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
                        <span>Thông báo </span>
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
                <h1 class="page-title">Quản Lý Nhân Viên</h1>
                
                <a href="them-nhan-vien.php" class="add-employee">
                    <i class="fas fa-plus"></i> Thêm Nhân Viên
                </a>
                
                <div class="search-filter">
                    <form action="" method="GET" id="searchForm">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" name="search" placeholder="Tìm kiếm nhân viên..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-options">
                            <select id="departmentFilter" name="department">
                                <option value="">Tất cả phòng ban</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="statusFilter" name="status">
                                <option value="">Tất cả trạng thái</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Đang làm việc</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Đã nghỉ việc</option>
                            </select>
                            <button type="button" id="searchButton" class="search-button">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="employeeTableContainer">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ và tên</th>
                                <th>Email</th>
                                <th>Phòng ban</th>
                                <th>Chức vụ</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="employeeTableBody">
                            <?php 
                            if ($result && $result->num_rows > 0) {
                                while ($employee = $result->fetch_assoc()) {
                                    $status_class = ($employee['status'] == 'active') ? 'active' : 'inactive';
                                    $status_text = ($employee['status'] == 'active') ? 'Đang làm việc' : 'Đã nghỉ việc';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td><span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td class="action-buttons">
                                    <a href="xem-nhan-vien.php?id=<?php echo $employee['id']; ?>" title="Xem"><i class="fas fa-eye"></i></a>
                                    <a href="sua-nhan-vien.php?id=<?php echo $employee['id']; ?>" title="Sửa"><i class="fas fa-edit"></i></a>
                                    <a href="xoa-nhan-vien.php?id=<?php echo $employee['id']; ?>" class="delete" title="Xóa" onclick="return confirm('Bạn có chắc muốn xóa nhân viên này?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                            ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Không tìm thấy nhân viên nào</td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lấy các phần tử DOM
            const searchInput = document.getElementById('searchInput');
            const departmentFilter = document.getElementById('departmentFilter');
            const statusFilter = document.getElementById('statusFilter');
            const searchButton = document.getElementById('searchButton');
            const employeeTableBody = document.getElementById('employeeTableBody');
            
            // Hàm tìm kiếm AJAX
            function searchEmployees() {
                // Lấy giá trị từ các trường tìm kiếm
                const search = searchInput.value;
                const department = departmentFilter.value;
                const status = statusFilter.value;
                
                // Tạo URL cho yêu cầu AJAX
                const url = `ajax/search-employees.php?search=${encodeURIComponent(search)}&department=${encodeURIComponent(department)}&status=${encodeURIComponent(status)}`;
                
                // Gửi yêu cầu AJAX
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Xóa nội dung hiện tại của bảng
                            employeeTableBody.innerHTML = '';
                            
                            // Kiểm tra số lượng kết quả
                            if (data.count > 0) {
                                // Hiển thị kết quả tìm kiếm
                                data.employees.forEach(employee => {
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td>${employee.employee_id}</td>
                                        <td>${employee.full_name}</td>
                                        <td>${employee.email}</td>
                                        <td>${employee.department}</td>
                                        <td>${employee.position}</td>
                                        <td><span class="status ${employee.status_class}">${employee.status_text}</span></td>
                                        <td class="action-buttons">
                                            <a href="xem-nhan-vien.php?id=${employee.id}" title="Xem"><i class="fas fa-eye"></i></a>
                                            <a href="sua-nhan-vien.php?id=${employee.id}" title="Sửa"><i class="fas fa-edit"></i></a>
                                            <a href="xoa-nhan-vien.php?id=${employee.id}" class="delete" title="Xóa" onclick="return confirm('Bạn có chắc muốn xóa nhân viên này?');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    `;
                                    employeeTableBody.appendChild(row);
                                });
                            } else {
                                // Hiển thị thông báo không tìm thấy kết quả
                                employeeTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Không tìm thấy nhân viên nào</td></tr>';
                            }
                        } else {
                            // Hiển thị thông báo lỗi
                            employeeTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;">Lỗi: ${data.error || 'Không thể tải dữ liệu'}</td></tr>`;
                        }
                    })
                    .catch(error => {
                        console.error('Lỗi AJAX:', error);
                        employeeTableBody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: red;">Lỗi kết nối máy chủ</td></tr>';
                    });
            }
            
            // Thêm sự kiện cho các trường tìm kiếm
            let searchTimeout;
            
            // Sự kiện input cho ô tìm kiếm (với debounce 300ms)
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(searchEmployees, 300);
            });
            
            // Sự kiện click cho nút tìm kiếm
            searchButton.addEventListener('click', searchEmployees);
            
            // Sự kiện change cho các dropdown
            departmentFilter.addEventListener('change', searchEmployees);
            statusFilter.addEventListener('change', searchEmployees);
            
            // Sự kiện submit form
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault(); // Ngăn chặn hành vi mặc định của form
                searchEmployees();
            });
            
            // Tự động tìm kiếm khi trang tải xong
            searchEmployees();
        });
    </script>
</body>
</html>
