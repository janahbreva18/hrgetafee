<?php
// Check if logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Check user role
function check_role($required_role) {
    if ($_SESSION['role_id'] !== $required_role) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Get employee info
function get_employee_info($conn, $employee_id) {
    $result = $conn->query("SELECT * FROM employees WHERE employee_id = $employee_id");
    return $result->fetch_assoc();
}

// Format date
function format_date($date) {
    if (!$date) return '-';
    return date('M d, Y', strtotime($date));
}

// Format datetime
function format_datetime($datetime) {
    if (!$datetime) return '-';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Get role name
function get_role_name($role_id) {
    $roles = [
        1 => 'HR Administrator',
        2 => 'HR Staff',
        3 => 'Employee'
    ];
    return $roles[$role_id] ?? 'Unknown';
}

// Get employee name
function get_employee_name($conn, $employee_id) {
    $result = $conn->query("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM employees WHERE employee_id = $employee_id");
    $row = $result->fetch_assoc();
    return $row['full_name'] ?? 'Unknown';
}

// Calculate leave balance
function calculate_leave_balance($conn, $employee_id, $leave_type_id) {
    $current_year = date('Y');
    
    // Get max days
    $max_result = $conn->query("SELECT max_days_per_year FROM leave_types WHERE leave_type_id = $leave_type_id");
    $max_days = $max_result->fetch_assoc()['max_days_per_year'];
    
    // Count approved leaves
    $used_result = $conn->query("SELECT COALESCE(SUM(number_of_days), 0) as used_days FROM leave_requests WHERE employee_id = $employee_id AND leave_type_id = $leave_type_id AND YEAR(start_date) = $current_year AND status = 'approved'");
    $used_days = $used_result->fetch_assoc()['used_days'];
    
    return $max_days - $used_days;
}

// Count pending leaves
function count_pending_leaves($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    return $result->fetch_assoc()['count'];
}

// Get total employees
function get_total_employees($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    return $result->fetch_assoc()['count'];
}
?>