<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$total_employees = get_total_employees($conn);

// Get users
$users = $conn->query("SELECT u.*, e.first_name, e.last_name, e.employee_code, r.role_name FROM users u JOIN employees e ON u.employee_id = e.employee_id JOIN roles r ON u.role_id = r.role_id ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Admin Dashboard - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>HR Administrator Dashboard</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="dashboard">
            <div class="stat-box">
                <h4>👥 Total Users</h4>
                <div class="stat-number"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-box">
                <h4>✅ Active Users</h4>
                <div class="stat-number"><?php echo $active_users; ?></div>
            </div>
            <div class="stat-box">
                <h4>👨‍💼 Employees</h4>
                <div class="stat-number"><?php echo $total_employees; ?></div>
            </div>
        </div>

        <!-- System Management -->
        <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <h3>⚙️ System Management</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">
                <a href="manage_users.php" class="btn btn-primary" style="text-align: center;">👤 Manage Users</a>
                <a href="manage_holidays.php" class="btn btn-primary" style="text-align: center;">📅 Holidays</a>
                <a href="manage_leaves.php" class="btn btn-primary" style="text-align: center;">📋 Leave Types</a>
                
            </div>
        </div>

        <!-- User Management Table -->
        <div class="table-container">
            <h3>👥 System Users</h3>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Employee ID</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo $user['employee_code']; ?></td>
                        <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : '-'; ?></td>
                        <td>
                            <a href="user_status.php?user_id=<?php echo $user['user_id']; ?>&status=<?php echo ($user['status'] === 'active' ? 'inactive' : 'active'); ?>" class="btn <?php echo ($user['status'] === 'active' ? 'btn-danger' : 'btn-success'); ?>" style="padding: 5px 10px; font-size: 12px;">
                                <?php echo ($user['status'] === 'active' ? 'Deactivate' : 'Activate'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>