<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(3);

$employee_id = $_SESSION['employee_id'];
$employee = get_employee_info($conn, $employee_id);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type_id = $_POST['leave_type_id'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($leave_type_id) || empty($start_date) || empty($end_date) || empty($reason)) {
        $message = '<div class="alert alert-danger">All fields are required</div>';
    } else {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        
        if ($start > $end) {
            $message = '<div class="alert alert-danger">Start date cannot be after end date</div>';
        } else {
            $number_of_days = (int)(($end - $start) / 86400) + 1;
            $balance = calculate_leave_balance($conn, $employee_id, $leave_type_id);
            
            if ($number_of_days > $balance) {
                $message = '<div class="alert alert-danger">Not enough leave balance. Available: ' . $balance . ' days</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, number_of_days, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iissss", $employee_id, $leave_type_id, $start_date, $end_date, $number_of_days, $reason);
                
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">✓ Leave request submitted successfully</div>';
                    header("Refresh: 2; url=dashboard.php");
                } else {
                    $message = '<div class="alert alert-danger">Error submitting request</div>';
                }
                $stmt->close();
            }
        }
    }
}

$leave_types = $conn->query("SELECT * FROM leave_types")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>Apply for Leave</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($employee['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>

        <div class="card" style="max-width: 600px;">
            <h3>📋 Leave Application Form</h3>
            
            <form method="POST">
                <div class="form-group">
                    <label for="leave_type">Leave Type</label>
                    <select name="leave_type_id" id="leave_type" required>
                        <option value="">-- Select Leave Type --</option>
                        <?php foreach ($leave_types as $type): ?>
                            <?php $balance = calculate_leave_balance($conn, $employee_id, $type['leave_type_id']); ?>
                            <option value="<?php echo $type['leave_type_id']; ?>">
                                <?php echo htmlspecialchars($type['leave_type_name']); ?> (<?php echo $balance; ?> days available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" required>
                </div>

                <div class="form-group">
                    <label for="reason">Reason</label>
                    <textarea name="reason" id="reason" required placeholder="Please provide a reason for your leave"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Leave Request</button>
            </form>
        </div>
    </div>
</body>
</html>