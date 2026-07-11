<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';

// Add leave type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $leave_type_name = $_POST['leave_type_name'] ?? '';
    $max_days = $_POST['max_days'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($leave_type_name) || empty($max_days)) {
        $message = '<div class="alert alert-danger">Name and max days are required</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_types (leave_type_name, max_days_per_year, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $leave_type_name, $max_days, $description);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">✓ Leave type added successfully</div>';
        } else {
            $message = '<div class="alert alert-danger">Error adding leave type</div>';
        }
        $stmt->close();
    }
}

// Delete leave type
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $conn->query("DELETE FROM leave_types WHERE leave_type_id = $delete_id");
    header("Location: manage_leaves.php");
    exit;
}

// Get leave types
$leave_types = $conn->query("SELECT * FROM leave_types ORDER BY leave_type_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>Leave Type Management</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>

        <!-- Add Leave Type Form -->
        <div class="card" style="max-width: 500px; margin-bottom: 30px;">
            <h3>➕ Add Leave Type</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="leave_type_name">Leave Type Name</label>
                    <input type="text" name="leave_type_name" id="leave_type_name" placeholder="e.g., Vacation Leave" required>
                </div>

                <div class="form-group">
                    <label for="max_days">Max Days Per Year</label>
                    <input type="number" name="max_days" id="max_days" min="1" placeholder="e.g., 15" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" placeholder="Describe this leave type..."></textarea>
                </div>

                <button type="submit" name="add_leave" class="btn btn-primary" style="width: 100%;">Add Leave Type</button>
            </form>
        </div>

        <!-- Leave Types List -->
        <div class="table-container">
            <h3>📋 All Leave Types (<?php echo count($leave_types); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Max Days/Year</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leave_types as $leave): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($leave['leave_type_name']); ?></strong></td>
                        <td><span class="badge badge-success"><?php echo $leave['max_days_per_year']; ?> days</span></td>
                        <td><?php echo htmlspecialchars($leave['description']); ?></td>
                        <td>
                            <a href="manage_leaves.php?delete_id=<?php echo $leave['leave_type_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Delete this leave type?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>