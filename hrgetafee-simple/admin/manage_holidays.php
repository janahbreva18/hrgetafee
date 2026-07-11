<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';

// Add holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $holiday_name = $_POST['holiday_name'] ?? '';
    $holiday_date = $_POST['holiday_date'] ?? '';
    
    if (empty($holiday_name) || empty($holiday_date)) {
        $message = '<div class="alert alert-danger">All fields are required</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO holidays (holiday_name, holiday_date) VALUES (?, ?)");
        $stmt->bind_param("ss", $holiday_name, $holiday_date);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">✓ Holiday added successfully</div>';
        } else {
            $message = '<div class="alert alert-danger">Error adding holiday</div>';
        }
        $stmt->close();
    }
}

// Delete holiday
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM holidays WHERE holiday_id = $delete_id");
    header("Location: manage_holidays.php");
    exit;
}

// Get holidays
$holidays = $conn->query("SELECT * FROM holidays ORDER BY holiday_date DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Holidays - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>Holiday Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>

        <!-- Add Holiday Form -->
        <div class="card" style="max-width: 400px; margin-bottom: 30px;">
            <h3>➕ Add Holiday</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="holiday_name">Holiday Name</label>
                    <input type="text" name="holiday_name" id="holiday_name" placeholder="e.g., Christmas Day" required>
                </div>

                <div class="form-group">
                    <label for="holiday_date">Date</label>
                    <input type="date" name="holiday_date" id="holiday_date" required>
                </div>

                <button type="submit" name="add_holiday" class="btn btn-primary" style="width: 100%;">Add Holiday</button>
            </form>
        </div>

        <!-- Holidays List -->
        <div class="table-container">
            <h3>📅 All Holidays (<?php echo count($holidays); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Holiday Name</th>
                        <th>Date</th>
                        <th>Days Until</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($holidays as $holiday): ?>
                        <?php 
                            $holiday_time = strtotime($holiday['holiday_date']);
                            $today_time = strtotime(date('Y-m-d'));
                            $days_until = floor(($holiday_time - $today_time) / 86400);
                        ?>
                    <tr>
                        <td><?php echo htmlspecialchars($holiday['holiday_name']); ?></td>
                        <td><?php echo format_date($holiday['holiday_date']); ?></td>
                        <td>
                            <?php if ($days_until < 0): ?>
                                <span class="badge badge-danger">Passed</span>
                            <?php elseif ($days_until == 0): ?>
                                <span class="badge badge-warning">Today!</span>
                            <?php else: ?>
                                <span class="badge badge-success"><?php echo $days_until; ?> days</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="manage_holidays.php?delete_id=<?php echo $holiday['holiday_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Delete this holiday?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>