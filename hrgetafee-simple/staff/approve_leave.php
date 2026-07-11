<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(2);

$staff = get_employee_info($conn, $_SESSION['employee_id']);

// Get pending leaves
$pending = $conn->query("SELECT lr.*, lt.leave_type_name, e.first_name, e.last_name, e.employee_code FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id JOIN employees e ON lr.employee_id = e.employee_id WHERE lr.status = 'pending' ORDER BY lr.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Leave - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>Approve Leave Requests</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <div class="table-container">
            <h3>📮 Leave Requests for Approval (<?php echo count($pending); ?>)</h3>
            
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
                        <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                        <td>
                            <a href="leave_action.php?leave_id=<?php echo $leave['leave_id']; ?>&action=approve" class="btn btn-success" style="padding: 8px 12px; font-size: 12px;">✓ Approve</a>
                            <a href="leave_action.php?leave_id=<?php echo $leave['leave_id']; ?>&action=deny" class="btn btn-danger" style="padding: 8px 12px; font-size: 12px;">✗ Deny</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-center" style="padding: 30px;">✓ No pending leave requests</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>