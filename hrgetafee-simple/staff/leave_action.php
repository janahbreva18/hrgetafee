<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(2);

$leave_id = $_GET['leave_id'] ?? '';
$action = $_GET['action'] ?? '';
$staff_id = $_SESSION['user_id'];

if (!$leave_id || !in_array($action, ['approve', 'deny'])) {
    header("Location: dashboard.php");
    exit;
}

$status = ($action === 'approve') ? 'approved' : 'denied';

$stmt = $conn->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_date = NOW() WHERE leave_id = ?");
$stmt->bind_param("sii", $status, $staff_id, $leave_id);

if ($stmt->execute()) {
    $message = ($action === 'approve') ? 'Leave approved' : 'Leave denied';
    header("Location: dashboard.php?msg=" . urlencode($message));
} else {
    header("Location: dashboard.php?error=1");
}

$stmt->close();
?>