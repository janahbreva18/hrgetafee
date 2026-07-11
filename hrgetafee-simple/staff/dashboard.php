<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(2);

$staff_id = $_SESSION['employee_id'];
$staff = get_employee_info($conn, $staff_id);

// Get statistics
$total_employees = get_total_employees($conn);
$pending_leaves = count_pending_leaves($conn);
$today_present = $conn->query("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetch_assoc()['count'];

// Get pending leave requests
$pending = $conn->query("SELECT lr.*, lt.leave_type_name, e.first_name, e.last_name, e.employee_code FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id JOIN employees e ON lr.employee_id = e.employee_id WHERE lr.status = 'pending' ORDER BY lr.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Staff Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HR Staff Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="dashboard">
            <div class="stat-box">
                <h4>👥 Total Employees</h4>
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <p>Active staff</p>
            </div>
            <div class="stat-box">
                <h4>⏳ Pending Leaves</h4>
                <div class="stat-number"><?php echo $pending_leaves; ?></div>
                <p>Awaiting approval</p>
            </div>
            <div class="stat-box">
                <h4>✅ Present Today</h4>
                <div class="stat-number"><?php echo $today_present; ?></div>
                <p>Clocked in</p>
            </div>
        </div>

        <!-- Main Features -->
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3>🎯 Core HR Management</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <a href="employee_list.php" class="btn btn-primary" style="text-align: center;">👥 Employee List</a>
                <a href="approve_leave.php" class="btn btn-primary" style="text-align: center;">📋 Approve Leaves</a>
                <a href="payroll.php" class="btn btn-primary" style="text-align: center;">💰 Payroll</a>
            </div>
        </div>

        <!-- Pending Leave Requests -->
        <div class="table-container">
            <h3>📮 Pending Leave Requests (<?php echo count($pending); ?>)</h3>
            <?php if (!empty($pending)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $leave): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?><br>
                            <small><?php echo $leave['employee_code']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($leave['leave_type_name']); ?></td>
                        <td><?php echo format_date($leave['start_date']); ?></td>
                        <td><?php echo format_date($leave['end_date']); ?></td>
                        <td><?php echo $leave['number_of_days']; ?></td>
                        <td><?php echo htmlspecialchars(substr($leave['reason'], 0, 30)); ?></td>
                        <td>
                            <a href="leave_action.php?leave_id=<?php echo $leave['leave_id']; ?>&action=approve" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">Approve</a>
                            <a href="leave_action.php?leave_id=<?php echo $leave['leave_id']; ?>&action=deny" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">Deny</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center">✓ No pending leave requests</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>