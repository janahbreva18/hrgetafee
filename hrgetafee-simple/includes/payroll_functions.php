<?php
// Enhanced Payroll Calculation Functions for HRGetafe
// Includes: CPF, CBIF, MPL deductions and leave compensation based on AMS

/**
 * Calculate AMS (Anniversary of Month Service)
 * @param $conn Database connection
 * @param $employee_id Employee ID
 * @return float AMS value
 */
function calculate_ams($conn, $employee_id) {
    $employee = get_employee_info($conn, $employee_id);
    $date_hired = $employee['date_hired'];
    $today = date('Y-m-d');
    
    $hired = new DateTime($date_hired);
    $current = new DateTime($today);
    $interval = $hired->diff($current);
    
    // AMS = Years + (Months/12) + (Days/30)
    $years = $interval->y;
    $months = $interval->m;
    $days = $interval->d;
    
    $ams = $years + ($months / 12) + ($days / 30);
    
    return round($ams, 1);
}

/**
 * Get Leave Compensation Value based on AMS
 * @param $conn Database connection
 * @param $ams Anniversary of Month Service
 * @return float Leave compensation factor
 */
function get_leave_compensation_factor($conn, $ams) {
    // Find closest AMS value
    $query = "SELECT leave_earned_amount FROM leave_compensation 
              WHERE ams_value <= $ams 
              ORDER BY ams_value DESC LIMIT 1";
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['leave_earned_amount'];
    }
    
    // Default fallback
    return 1.0;
}

/**
 * Calculate Basic Deductions (SSS, PhilHealth, Pag-IBIG, BIR)
 * @param $gross_salary Gross salary amount
 * @return array Array of deductions
 */
function calculate_basic_deductions($gross_salary) {
    $deductions = [
        'sss' => $gross_salary * 0.0363,  // 3.63%
        'philhealth' => $gross_salary * 0.0275,  // 2.75%
        'pagibig' => $gross_salary * 0.02,  // 2.00%
        'bir' => 0  // Will be calculated based on net
    ];
    
    return $deductions;
}

/**
 * Get CPF, CBIF, MPL fixed deductions for employee
 * @param $conn Database connection
 * @param $employee_id Employee ID
 * @return array Fixed deductions
 */
function get_fixed_deductions($conn, $employee_id) {
    $fixed_deductions = [
        'cpf' => 971.75,  // CPF - Consolidated Police Fund
        'cbif' => 0.00,   // CBIF - Comprehensive Benefit Insurance Fund
        'mpl' => 0.00     // MPL - Magna Carta for Public Employees Levy
    ];
    
    // Check if employee has custom deduction amounts
    $query = "SELECT ed.amount, dt.deduction_code FROM employee_deductions ed
              JOIN deduction_types dt ON ed.deduction_id = dt.deduction_id
              WHERE ed.employee_id = $employee_id AND ed.status = 'active'
              AND dt.deduction_code IN ('CPF', 'CBIF', 'MPL')";
    
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $code = strtolower($row['deduction_code']);
            $fixed_deductions[$code] = floatval($row['amount']);
        }
    }
    
    return $fixed_deductions;
}

/**
 * Calculate Complete Payroll with all deductions
 * @param $conn Database connection
 * @param $employee_id Employee ID
 * @param $month Month (1-12)
 * @param $year Year
 * @return array Detailed payroll breakdown
 */
function calculate_enhanced_payroll($conn, $employee_id, $month, $year) {
    $employee = get_employee_info($conn, $employee_id);
    $gross_salary = floatval($employee['salary']);
    
    // Get attendance data for the month
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $attendance = $conn->query(
        "SELECT status FROM attendance WHERE employee_id = $employee_id AND attendance_date BETWEEN '$start_date' AND '$end_date'"
    )->fetch_all(MYSQLI_ASSOC);
    
    // Count attendance
    $absences = 0;
    $lates = 0;
    $present_days = 0;
    
    foreach ($attendance as $record) {
        if ($record['status'] === 'absent') {
            $absences++;
        } elseif ($record['status'] === 'late') {
            $lates++;
        } elseif ($record['status'] === 'present') {
            $present_days++;
        }
    }
    
    // Get approved leaves
    $leaves = $conn->query(
        "SELECT COALESCE(SUM(number_of_days), 0) as leave_days FROM leave_requests 
         WHERE employee_id = $employee_id AND status = 'approved' 
         AND start_date <= '$end_date' AND end_date >= '$start_date'"
    )->fetch_assoc()['leave_days'];
    
    // Calculate AMS and leave compensation
    $ams = calculate_ams($conn, $employee_id);
    $leave_compensation_factor = get_leave_compensation_factor($conn, $ams);
    
    // BASIC DEDUCTIONS (Percentage-based)
    $basic_deductions = calculate_basic_deductions($gross_salary);
    
    // FIXED DEDUCTIONS
    $fixed_deductions = get_fixed_deductions($conn, $employee_id);
    
    // VARIABLE DEDUCTIONS
    $absence_deduction = $absences * 500;  // ₱500 per absence
    $late_deduction = $lates * 100;        // ₱100 per late
    
    // Calculate total deductions (without BIR)
    $subtotal_deductions = 
        $basic_deductions['sss'] + 
        $basic_deductions['philhealth'] + 
        $basic_deductions['pagibig'] + 
        $fixed_deductions['cpf'] + 
        $fixed_deductions['cbif'] + 
        $fixed_deductions['mpl'] + 
        $absence_deduction + 
        $late_deduction;
    
    // Calculate BIR (after other deductions)
    $taxable_income = $gross_salary - $subtotal_deductions;
    $bir = max(0, $taxable_income * 0.12);  // 12% on taxable income
    
    $total_deductions = $subtotal_deductions + $bir;
    $net_salary = $gross_salary - $total_deductions;
    
    // Ensure net salary doesn't go negative
    $net_salary = max(0, $net_salary);
    
    return [
        'gross_salary' => $gross_salary,
        'ams' => $ams,
        'leave_compensation_factor' => $leave_compensation_factor,
        
        // Attendance
        'absences' => $absences,
        'lates' => $lates,
        'approved_leaves' => $leaves,
        'present_days' => $present_days,
        
        // Basic Deductions (Percentage)
        'sss_deduction' => $basic_deductions['sss'],
        'philhealth_deduction' => $basic_deductions['philhealth'],
        'pagibig_deduction' => $basic_deductions['pagibig'],
        
        // Fixed Deductions
        'cpf_deduction' => $fixed_deductions['cpf'],
        'cbif_deduction' => $fixed_deductions['cbif'],
        'mpl_deduction' => $fixed_deductions['mpl'],
        
        // Variable Deductions
        'absence_deduction' => $absence_deduction,
        'late_deduction' => $late_deduction,
        
        // Tax
        'bir_deduction' => $bir,
        
        // Totals
        'total_deductions' => $total_deductions,
        'net_salary' => $net_salary
    ];
}

/**
 * Calculate Leave Earned for employee based on AMS
 * @param $conn Database connection
 * @param $employee_id Employee ID
 * @param $month Month to calculate for
 * @param $year Year to calculate for
 * @return array Leave earned information
 */
function calculate_leave_earned($conn, $employee_id, $month, $year) {
    $ams = calculate_ams($conn, $employee_id);
    $compensation_factor = get_leave_compensation_factor($conn, $ams);
    
    // Get employee salary for leave computation
    $employee = get_employee_info($conn, $employee_id);
    $monthly_salary = floatval($employee['salary']);
    
    // Leave earned per month = monthly_salary * compensation_factor
    $leave_earned_days = $compensation_factor;
    $leave_earned_amount = $monthly_salary * $compensation_factor;
    
    return [
        'ams' => $ams,
        'compensation_factor' => $compensation_factor,
        'leave_earned_days' => $leave_earned_days,
        'leave_earned_amount' => $leave_earned_amount,
        'monthly_salary' => $monthly_salary
    ];
}

/**
 * Save Payroll Details to payroll_details table
 * @param $conn Database connection
 * @param $payroll_id Payroll record ID
 * @param $payroll_data Payroll calculation data
 * @return bool Success status
 */
function save_payroll_details($conn, $payroll_id, $payroll_data) {
    $details = [
        // Earnings
        ['earning', 'Gross Salary', $payroll_data['gross_salary']],
        
        // Deductions
        ['deduction', 'SSS Contribution', $payroll_data['sss_deduction']],
        ['deduction', 'PhilHealth Premium', $payroll_data['philhealth_deduction']],
        ['deduction', 'Pag-IBIG Contribution', $payroll_data['pagibig_deduction']],
        ['deduction', 'CPF (Consolidated Police Fund)', $payroll_data['cpf_deduction']],
        ['deduction', 'CBIF (Comprehensive Benefit Insurance)', $payroll_data['cbif_deduction']],
        ['deduction', 'MPL (Magna Carta for Public Employees)', $payroll_data['mpl_deduction']],
        ['deduction', 'Absence Deduction (' . $payroll_data['absences'] . ' × ₱500)', $payroll_data['absence_deduction']],
        ['deduction', 'Late Deduction (' . $payroll_data['lates'] . ' × ₱100)', $payroll_data['late_deduction']],
        ['deduction', 'BIR Income Tax (12%)', $payroll_data['bir_deduction']],
    ];
    
    foreach ($details as $detail) {
        $type = $detail[0];
        $name = $conn->real_escape_string($detail[1]);
        $amount = $detail[2];
        
        $stmt = $conn->prepare(
            "INSERT INTO payroll_details (payroll_id, detail_type, detail_name, amount) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("issd", $payroll_id, $type, $name, $amount);
        
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        $stmt->close();
    }
    
    return true;
}

/**
 * Format currency for display
 * @param $amount Amount to format
 * @return string Formatted currency
 */
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get all payroll details for a specific payroll record
 * @param $conn Database connection
 * @param $payroll_id Payroll ID
 * @return array Payroll details
 */
function get_payroll_details($conn, $payroll_id) {
    $query = "SELECT detail_type, detail_name, amount FROM payroll_details 
              WHERE payroll_id = $payroll_id 
              ORDER BY detail_type DESC, detail_name";
    
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
?>
