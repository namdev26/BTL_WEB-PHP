<?php
session_start();

// Kiểm tra đăng nhập nhân viên
if (!isset($_SESSION['employee_logged_in'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Kết nối database
require_once 'config/db.php';

// Lấy ID của bản ghi lương
$salary_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$employee_code = $_SESSION['employee_code'];

if ($salary_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid salary ID']);
    exit;
}

// Kiểm tra xem bản ghi lương có thuộc về nhân viên này không
$check_query = "SELECT * FROM salary_records WHERE id = ? AND employee_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("is", $salary_id, $employee_code);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Salary record not found or not authorized']);
    exit;
}

$salary_record = $check_result->fetch_assoc();

// Tạo dữ liệu mẫu cho các khoản thưởng (từ trường bonus trong salary_records)
$bonuses = [];
if ($salary_record['bonus'] > 0) {
    // Phân tích bonus thành các khoản thưởng chi tiết
    $bonus_amount = $salary_record['bonus'];
    
    // Nếu bonus lớn, chia thành 2-3 khoản thưởng nhỏ hơn
    if ($bonus_amount >= 1000000) {
        $performance_bonus = round($bonus_amount * 0.6); // 60% là thưởng hiệu suất
        $attendance_bonus = round($bonus_amount * 0.3); // 30% là thưởng chuyên cần
        $other_bonus = $bonus_amount - $performance_bonus - $attendance_bonus; // 10% là thưởng khác
        
        $bonuses[] = [
            'bonus_name' => 'Thưởng hiệu suất',
            'bonus_amount' => $performance_bonus,
            'bonus_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Thưởng do hoàn thành xuất sắc công việc'
        ];
        
        $bonuses[] = [
            'bonus_name' => 'Thưởng chuyên cần',
            'bonus_amount' => $attendance_bonus,
            'bonus_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Thưởng do đi làm đầy đủ, đúng giờ'
        ];
        
        if ($other_bonus > 0) {
            $bonuses[] = [
                'bonus_name' => 'Thưởng khác',
                'bonus_amount' => $other_bonus,
                'bonus_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
                'description' => 'Các khoản thưởng khác'
            ];
        }
    } else {
        // Nếu bonus nhỏ, chỉ tạo một khoản thưởng
        $bonuses[] = [
            'bonus_name' => 'Thưởng tháng',
            'bonus_amount' => $bonus_amount,
            'bonus_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Thưởng thành tích tháng ' . $salary_record['salary_month'] . '/' . $salary_record['salary_year']
        ];
    }
}

// Tạo dữ liệu mẫu cho các khoản khấu trừ (từ trường deductions trong salary_records)
$deductions = [];
if ($salary_record['deductions'] > 0) {
    // Phân tích deductions thành các khoản khấu trừ chi tiết
    $deduction_amount = $salary_record['deductions'];
    
    // Nếu deductions lớn, chia thành 2-3 khoản khấu trừ nhỏ hơn
    if ($deduction_amount >= 500000) {
        $insurance_deduction = round($deduction_amount * 0.5); // 50% là bảo hiểm
        $tax_deduction = round($deduction_amount * 0.3); // 30% là thuế
        $other_deduction = $deduction_amount - $insurance_deduction - $tax_deduction; // 20% là khấu trừ khác
        
        $deductions[] = [
            'deduction_name' => 'Bảo hiểm xã hội',
            'deduction_amount' => $insurance_deduction,
            'deduction_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Khấu trừ bảo hiểm xã hội theo quy định'
        ];
        
        $deductions[] = [
            'deduction_name' => 'Thuế thu nhập cá nhân',
            'deduction_amount' => $tax_deduction,
            'deduction_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Thuế TNCN theo quy định'
        ];
        
        if ($other_deduction > 0) {
            $deductions[] = [
                'deduction_name' => 'Khấu trừ khác',
                'deduction_amount' => $other_deduction,
                'deduction_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
                'description' => 'Các khoản khấu trừ khác'
            ];
        }
    } else {
        // Nếu deductions nhỏ, chỉ tạo một khoản khấu trừ
        $deductions[] = [
            'deduction_name' => 'Khấu trừ tháng',
            'deduction_amount' => $deduction_amount,
            'deduction_date' => date('Y-m-d', strtotime($salary_record['created_at'])),
            'description' => 'Khấu trừ tháng ' . $salary_record['salary_month'] . '/' . $salary_record['salary_year']
        ];
    }
}

// Tạo dữ liệu mẫu cho ghi chú
$notes = [];
if (!empty($salary_record['notes'])) {
    $notes[] = [
        'note_title' => 'Ghi chú kỳ lương',
        'note_content' => $salary_record['notes'],
        'created_at' => $salary_record['created_at']
    ];
}

// Thêm một ghi chú mẫu về thông tin lương
$notes[] = [
    'note_title' => 'Thông tin kỳ lương ' . $salary_record['salary_month'] . '/' . $salary_record['salary_year'],
    'note_content' => 'Lương tháng ' . $salary_record['salary_month'] . '/' . $salary_record['salary_year'] . ' được tính dựa trên lương cơ bản và các khoản thưởng, khấu trừ. Vui lòng liên hệ phòng nhân sự nếu có thắc mắc.',
    'created_at' => $salary_record['created_at']
];

// Tạo dữ liệu trả về
$response = [
    'salary' => $salary_record,
    'bonuses' => $bonuses,
    'deductions' => $deductions,
    'notes' => $notes
];

// Trả về dữ liệu dạng JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
