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

// Lấy thông tin lương
$salary_data = [];
$current_month = date('m');
$current_year = date('Y');

// Lọc theo năm và tháng nếu có
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; // 0 = tất cả các tháng

// Kiểm tra bảng salary_records
$check_table = $conn->query("SHOW TABLES LIKE 'salary_records'");
if ($check_table->num_rows > 0) {
    // Tạo query dựa trên bộ lọc
    $salary_query = "SELECT * FROM salary_records WHERE employee_id = ?";
    $params = [$employee_code];
    $types = "s";
    
    if ($filter_month > 0) {
        $salary_query .= " AND salary_month = ?";
        $params[] = $filter_month;
        $types .= "i";
    }
    
    $salary_query .= " AND salary_year = ? ORDER BY salary_year DESC, salary_month DESC";
    $params[] = $filter_year;
    $types .= "i";
    
    $salary_stmt = $conn->prepare($salary_query);
    if (!$salary_stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $salary_stmt->bind_param($types, ...$params);
    $salary_stmt->execute();
    $salary_result = $salary_stmt->get_result();
    
    if ($salary_result->num_rows > 0) {
        while ($row = $salary_result->fetch_assoc()) {
            $salary_data[] = $row;
        }
    }
}

// Tính tổng lương trong năm
$yearly_total = 0;
foreach ($salary_data as $salary) {
    $yearly_total += $salary['total_salary'];
}

// Lấy danh sách các năm có dữ liệu lương
$years = [];
$years_query = "SELECT DISTINCT salary_year FROM salary_records WHERE employee_id = ? ORDER BY salary_year DESC";
$years_stmt = $conn->prepare($years_query);
if (!$years_stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$years_stmt->bind_param("s", $employee_code);
$years_stmt->execute();
$years_result = $years_stmt->get_result();

if ($years_result->num_rows > 0) {
    while ($row = $years_result->fetch_assoc()) {
        $years[] = $row['salary_year'];
    }
} else {
    // Nếu không có dữ liệu, thêm năm hiện tại
    $years[] = $current_year;
}

// Lấy danh sách tháng
$months = [
    1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
    5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
    9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
];

// Ghi log hoạt động xem lương
$check_activities = $conn->query("SHOW TABLES LIKE 'activities'");
if ($check_activities->num_rows > 0) {
    $action = 'view_salary';
    $description = 'Xem thông tin lương';
    $user_id = $employee_code;
    
    $log_activity = $conn->prepare("INSERT INTO activities (user_id, action, description) VALUES (?, ?, ?)");
    $log_activity->bind_param("sss", $user_id, $action, $description);
    $log_activity->execute();
}














?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lương & Thưởng - <?php echo htmlspecialchars($employee_name); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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
                            <span>Thông tin cá nhân</span>
                        </a>
                    </li>
                    <li class="active">
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
                <h1 class="page-title">Lương & Thưởng</h1>
                
                <div class="salary-container">
                    <div class="salary-header">
                        <h2 class="salary-title"><i class="fas fa-money-bill-wave"></i> Bảng lương của bạn</h2>
                        
                        <form class="salary-filters" method="GET" action="employee-salary.php">
                            <div class="filter-group">
                                <label for="year">Năm:</label>
                                <select id="year" name="year">
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $filter_year == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="month">Tháng:</label>
                                <select id="month" name="month">
                                    <option value="0" <?php echo $filter_month == 0 ? 'selected' : ''; ?>>Tất cả</option>
                                    <?php foreach ($months as $month_num => $month_name): ?>
                                        <option value="<?php echo $month_num; ?>" <?php echo $filter_month == $month_num ? 'selected' : ''; ?>>
                                            <?php echo $month_name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <button type="submit"><i class="fas fa-filter"></i> Lọc</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="salary-summary">
                        
                        
                        <div class="summary-card primary">
                            <h3>Lương cơ bản</h3>
                            <div class="amount"><?php echo number_format($employee_details['salary'] ?? 0, 0, ',', '.'); ?> VNĐ</div>
                            <div class="period">Theo hợp đồng</div>
                        </div>
                        <div class="summary-card warning">
                            <h3>Khấu trừ</h3>
                            <div class="amount"><?php echo number_format($employee_details['deductions'] ?? 1000000, 0, ',', '.'); ?> VNĐ</div>
                            <div class="period">Theo hợp đồng</div>
                        </div>
                        <div class="summary-card success">
                            <h3>Thưởng</h3>
                            <div class="amount"><?php echo number_format($employee_details['deductions'] ?? 3000000, 0, ',', '.'); ?> VNĐ</div>
                            <div class="period">Theo hợp đồng</div>
                        </div>
                        <div class="summary-card success">
                            <h3>Thực nhận</h3>
                            <div class="amount"><?php echo number_format($employee_details['deductions'] ?? 24000000, 0, ',', '.'); ?> VNĐ</div>
                            <div class="period">Theo hợp đồng</div>
                        </div>
                    </div>
                    
                    <?php if (count($salary_data) > 0): ?>
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>Kỳ lương</th>
                                    <th>Lương cơ bản</th>
                                    <th>Thưởng</th>
                                    <th>Khấu trừ</th>
                                    <th>Tổng lương</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salary_data as $salary): ?>
                                    <tr class="salary-row" data-id="<?php echo $salary['id']; ?>">
                                        <td><?php echo $months[$salary['salary_month']] . ' ' . $salary['salary_year']; ?></td>
                                        <td><?php echo number_format($salary['base_salary'], 0, ',', '.'); ?> VNĐ</td>
                                        <td><?php echo number_format($salary['bonus'], 0, ',', '.'); ?> VNĐ</td>
                                        <td><?php echo number_format($salary['deductions'], 0, ',', '.'); ?> VNĐ</td>
                                        <td><strong><?php echo number_format($salary['total_salary'], 0, ',', '.'); ?> VNĐ</strong></td>
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
                                        <td>
                                            <button class="btn-view-details" data-id="<?php echo $salary['id']; ?>">
                                                <i class="fas fa-eye"></i> Chi tiết
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-search"></i>
                            <p>Không có dữ liệu lương cho thời gian đã chọn.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($salary_data) > 0): ?>
                        <div class="salary-details">
                            <h3><i class="fas fa-info-circle"></i> Thông tin chi tiết</h3>
                            <div class="details-grid">
                                <div class="detail-item">
                                    <h4>Mã nhân viên</h4>
                                    <p><?php echo htmlspecialchars($employee_code); ?></p>
                                </div>
                                
                                <div class="detail-item">
                                    <h4>Họ và tên</h4>
                                    <p><?php echo htmlspecialchars($employee_name); ?></p>
                                </div>
                                
                                <div class="detail-item">
                                    <h4>Phòng ban</h4>
                                    <p><?php echo htmlspecialchars($employee_department); ?></p>
                                </div>
                                
                                <div class="detail-item">
                                    <h4>Chức vụ</h4>
                                    <p><?php echo htmlspecialchars($employee_position); ?></p>
                                </div>
                                
                                <div class="detail-item">
                                    <h4>Ngày tham gia</h4>
                                    <p><?php echo !empty($employee_details['created_at']) ? date('d/m/Y', strtotime($employee_details['created_at'])) : 'Chưa cập nhật'; ?></p>
                                </div>
                                
                                <div class="detail-item">
                                    <h4>Trạng thái</h4>
                                    <p><?php echo isset($employee_details['status']) && $employee_details['status'] === 'active' ? 'Đang làm việc' : 'Đã nghỉ việc'; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Chi tiết lương -->
    <div id="salaryDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Chi tiết kỳ lương</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="detail-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Đang tải dữ liệu...</p>
                </div>
                
                <div class="detail-content" style="display: none;">
                    <div class="detail-header">
                        <div class="detail-period"></div>
                        <div class="detail-status"></div>
                    </div>
                    
                    <div class="detail-tabs">
                        <button class="tab-button active" data-tab="bonuses">Thưởng</button>
                        <button class="tab-button" data-tab="deductions">Khấu trừ</button>
                        <button class="tab-button" data-tab="notes">Ghi chú</button>
                    </div>
                    
                    <div class="tab-content">
                        <div id="bonuses" class="tab-pane active">
                            <div class="bonus-list"></div>
                            <div class="no-bonus-data" style="display: none;">
                                <i class="fas fa-award"></i>
                                <p>Không có dữ liệu thưởng cho kỳ lương này.</p>
                            </div>
                        </div>
                        
                        <div id="deductions" class="tab-pane">
                            <div class="deduction-list"></div>
                            <div class="no-deduction-data" style="display: none;">
                                <i class="fas fa-minus-circle"></i>
                                <p>Không có dữ liệu khấu trừ cho kỳ lương này.</p>
                            </div>
                        </div>
                        
                        <div id="notes" class="tab-pane">
                            <div class="note-list"></div>
                            <div class="no-note-data" style="display: none;">
                                <i class="fas fa-sticky-note"></i>
                                <p>Không có ghi chú cho kỳ lương này.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-summary">
                        <div class="summary-item">
                            <span class="summary-label">Lương cơ bản:</span>
                            <span class="summary-value base-salary"></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tổng thưởng:</span>
                            <span class="summary-value total-bonus"></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tổng khấu trừ:</span>
                            <span class="summary-value total-deduction"></span>
                        </div>
                        <div class="summary-item total">
                            <span class="summary-label">Thực lĩnh:</span>
                            <span class="summary-value total-salary"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        // Xử lý modal chi tiết lương
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('salaryDetailModal');
            const closeBtn = modal.querySelector('.close');
            const detailButtons = document.querySelectorAll('.btn-view-details');
            const salaryRows = document.querySelectorAll('.salary-row');
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            // Hàm mở modal
            function openModal(salaryId) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Hiển thị loading
                modal.querySelector('.detail-loading').style.display = 'block';
                modal.querySelector('.detail-content').style.display = 'none';
                
                // Lấy dữ liệu chi tiết lương
                fetch(`get-salary-details.php?id=${salaryId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            closeModal();
                            return;
                        }
                        
                        // Cập nhật dữ liệu vào modal
                        updateModalContent(data);
                        
                        // Ẩn loading, hiển thị nội dung
                        modal.querySelector('.detail-loading').style.display = 'none';
                        modal.querySelector('.detail-content').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Đã xảy ra lỗi khi tải dữ liệu. Vui lòng thử lại sau.');
                        closeModal();
                    });
            }
            
            // Hàm đóng modal
            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            // Hàm cập nhật nội dung modal
            function updateModalContent(data) {
                const salary = data.salary;
                const bonuses = data.bonuses;
                const deductions = data.deductions;
                const notes = data.notes;
                
                // Cập nhật thông tin kỳ lương
                const months = {
                    1: 'Tháng 1', 2: 'Tháng 2', 3: 'Tháng 3', 4: 'Tháng 4',
                    5: 'Tháng 5', 6: 'Tháng 6', 7: 'Tháng 7', 8: 'Tháng 8',
                    9: 'Tháng 9', 10: 'Tháng 10', 11: 'Tháng 11', 12: 'Tháng 12'
                };
                
                modal.querySelector('.detail-period').textContent = `${months[salary.salary_month]} ${salary.salary_year}`;
                
                // Cập nhật trạng thái
                let statusText = '';
                let statusClass = '';
                
                switch(salary.status) {
                    case 'pending':
                        statusText = 'Chờ duyệt';
                        statusClass = 'pending';
                        break;
                    case 'approved':
                        statusText = 'Đã duyệt';
                        statusClass = 'approved';
                        break;
                    case 'paid':
                        statusText = 'Đã thanh toán';
                        statusClass = 'paid';
                        break;
                    default:
                        statusText = salary.status;
                        statusClass = '';
                }
                
                modal.querySelector('.detail-status').innerHTML = `<span class="status ${statusClass}">${statusText}</span>`;
                
                // Cập nhật danh sách thưởng
                const bonusList = modal.querySelector('.bonus-list');
                bonusList.innerHTML = '';
                
                if (bonuses.length > 0) {
                    bonuses.forEach(bonus => {
                        const bonusDate = new Date(bonus.bonus_date);
                        const formattedDate = `${bonusDate.getDate()}/${bonusDate.getMonth() + 1}/${bonusDate.getFullYear()}`;
                        
                        bonusList.innerHTML += `
                            <div class="detail-item">
                                <div class="detail-item-header">
                                    <div class="detail-item-title">${bonus.bonus_name}</div>
                                    <div class="detail-item-amount bonus">+${Number(bonus.bonus_amount).toLocaleString('vi-VN')} VNĐ</div>
                                </div>
                                <div class="detail-item-date">Ngày: ${formattedDate}</div>
                                <div class="detail-item-description">${bonus.description || 'Không có mô tả'}</div>
                            </div>
                        `;
                    });
                    
                    modal.querySelector('.no-bonus-data').style.display = 'none';
                } else {
                    modal.querySelector('.no-bonus-data').style.display = 'block';
                }
                
                // Cập nhật danh sách khấu trừ
                const deductionList = modal.querySelector('.deduction-list');
                deductionList.innerHTML = '';
                
                if (deductions.length > 0) {
                    deductions.forEach(deduction => {
                        const deductionDate = new Date(deduction.deduction_date);
                        const formattedDate = `${deductionDate.getDate()}/${deductionDate.getMonth() + 1}/${deductionDate.getFullYear()}`;
                        
                        deductionList.innerHTML += `
                            <div class="detail-item">
                                <div class="detail-item-header">
                                    <div class="detail-item-title">${deduction.deduction_name}</div>
                                    <div class="detail-item-amount deduction">-${Number(deduction.deduction_amount).toLocaleString('vi-VN')} VNĐ</div>
                                </div>
                                <div class="detail-item-date">Ngày: ${formattedDate}</div>
                                <div class="detail-item-description">${deduction.description || 'Không có mô tả'}</div>
                            </div>
                        `;
                    });
                    
                    modal.querySelector('.no-deduction-data').style.display = 'none';
                } else {
                    modal.querySelector('.no-deduction-data').style.display = 'block';
                }
                
                // Cập nhật danh sách ghi chú
                const noteList = modal.querySelector('.note-list');
                noteList.innerHTML = '';
                
                if (notes.length > 0) {
                    notes.forEach(note => {
                        const noteDate = new Date(note.created_at);
                        const formattedDate = `${noteDate.getDate()}/${noteDate.getMonth() + 1}/${noteDate.getFullYear()} ${noteDate.getHours()}:${noteDate.getMinutes()}`;
                        
                        noteList.innerHTML += `
                            <div class="note-item">
                                <div class="note-title">${note.note_title}</div>
                                <div class="note-date">Ngày tạo: ${formattedDate}</div>
                                <div class="note-content">${note.note_content}</div>
                            </div>
                        `;
                    });
                    
                    modal.querySelector('.no-note-data').style.display = 'none';
                } else {
                    modal.querySelector('.no-note-data').style.display = 'block';
                }
                
                // Cập nhật tổng kết
                modal.querySelector('.base-salary').textContent = Number(salary.base_salary).toLocaleString('vi-VN') + ' VNĐ';
                modal.querySelector('.total-bonus').textContent = Number(salary.bonus).toLocaleString('vi-VN') + ' VNĐ';
                modal.querySelector('.total-deduction').textContent = Number(salary.deductions).toLocaleString('vi-VN') + ' VNĐ';
                modal.querySelector('.total-salary').textContent = Number(salary.total_salary).toLocaleString('vi-VN') + ' VNĐ';
            }
            
            // Sự kiện click vào nút xem chi tiết
            detailButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const salaryId = this.getAttribute('data-id');
                    openModal(salaryId);
                });
            });
            
            // Sự kiện click vào hàng lương
            salaryRows.forEach(row => {
                row.addEventListener('click', function() {
                    const salaryId = this.getAttribute('data-id');
                    openModal(salaryId);
                });
            });
            
            // Sự kiện click vào nút đóng modal
            closeBtn.addEventListener('click', closeModal);
            
            // Sự kiện click bên ngoài modal để đóng
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });
            
            // Sự kiện chuyển tab
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Xóa active class từ tất cả các tab
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));
                    
                    // Thêm active class cho tab được chọn
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
