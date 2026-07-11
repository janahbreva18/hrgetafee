<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Get all roles
$roles_result = $conn->query("SELECT * FROM roles ORDER BY role_id");
$roles = $roles_result->fetch_all(MYSQLI_ASSOC);

// Get permissions grouped by role
$permissions_by_role = [];
foreach ($roles as $role) {
    $perms = $conn->query("SELECT * FROM role_permissions WHERE role_id = " . $role['role_id'] . " ORDER BY permission_name")->fetch_all(MYSQLI_ASSOC);
    $permissions_by_role[$role['role_id']] = $perms;
}

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_permission') {
        $role_id = intval($_POST['role_id']);
        $permission_name = sanitizeInput($_POST['permission_name']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($permission_name)) {
            $error = 'Permission name is required';
        } else {
            $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_name, description) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $role_id, $permission_name, $description);
            
            if ($stmt->execute()) {
                $message = 'Permission added successfully';
                logSecurityEvent($conn, $_SESSION['user_id'], 'ADD_PERMISSION', "Added permission '$permission_name' to role_id $role_id");
            } else {
                $error = 'Error adding permission';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete_permission') {
        $permission_id = intval($_POST['permission_id']);
        
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
        $stmt->bind_param('i', $permission_id);
        
        if ($stmt->execute()) {
            $message = 'Permission deleted successfully';
            logSecurityEvent($conn, $_SESSION['user_id'], 'DELETE_PERMISSION', "Deleted permission_id $permission_id");
            // Refresh permissions
            header('Location: manage_roles.php');
            exit;
        } else {
            $error = 'Error deleting permission';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Roles & Permissions - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .permission-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .permission-card { border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f9f9f9; }
        .permission-list { list-style: none; padding: 0; margin: 15px 0; }
        .permission-list li { padding: 10px; background: white; margin: 5px 0; border-left: 3px solid #667eea; display: flex; justify-content: space-between; align-items: center; }
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-delete:hover { background: #c82333; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.4); }
        .modal.show { display: block; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 80px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Manage Roles & Permissions</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <div class="permission-grid">
            <?php foreach ($roles as $role): ?>
                <div class="permission-card">
                    <h3><?php echo htmlspecialchars($role['role_name']); ?></h3>
                    <p><small><?php echo htmlspecialchars($role['description'] ?? ''); ?></small></p>
                    
                    <h4>Permissions:</h4>
                    <?php if (!empty($permissions_by_role[$role['role_id']])): ?>
                        <ul class="permission-list">
                            <?php foreach ($permissions_by_role[$role['role_id']] as $perm): ?>
                                <li>
                                    <div>
                                        <strong><?php echo htmlspecialchars($perm['permission_name']); ?></strong>
                                        <small style="display: block; color: #666;"><?php echo htmlspecialchars($perm['description'] ?? ''); ?></small>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this permission?');">
                                        <input type="hidden" name="action" value="delete_permission">
                                        <input type="hidden" name="permission_id" value="<?php echo $perm['permission_id']; ?>">
                                        <button type="submit" class="btn-delete">✕</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #999; font-style: italic;">No permissions assigned</p>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" onclick="openModal('modal-<?php echo $role['role_id']; ?>')">+ Add Permission</button>
                </div>

                <!-- Modal for adding permission -->
                <div id="modal-<?php echo $role['role_id']; ?>" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal('modal-<?php echo $role['role_id']; ?>')">&times;</span>
                        <h3>Add Permission to <?php echo htmlspecialchars($role['role_name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_permission">
                            <input type="hidden" name="role_id" value="<?php echo $role['role_id']; ?>">
                            
                            <div class="form-group">
                                <label>Permission Name *</label>
                                <input type="text" name="permission_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Add Permission</button>
                            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-<?php echo $role['role_id']; ?>')">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>