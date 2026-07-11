<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
check_role(1);

$admin = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Validate password strength
function validate_password($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    return $errors;
}

// Validate employee data
function validate_employee($data) {
    $errors = [];
    
    if (empty($data['first_name'])) {
        $errors[] = 'First name is required';
    }
    if (empty($data['last_name'])) {
        $errors[] = 'Last name is required';
    }
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } else if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($data['position'])) {
        $errors[] = 'Position is required';
    }
    if (empty($data['salary'])) {
        $errors[] = 'Salary is required';
    } else if (!is_numeric($data['salary']) || $data['salary'] < 0) {
        $errors[] = 'Salary must be a valid positive number';
    }
    if (empty($data['date_hired'])) {
        $errors[] = 'Date hired is required';
    }
    
    return $errors;
}

// Validate user data
function validate_user($data, $conn) {
    $errors = [];
    
    if (empty($data['username'])) {
        $errors[] = 'Username is required';
    } else if (strlen($data['username']) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    if (empty($data['password'])) {
        $errors[] = 'Password is required';
    } else {
        $pwd_errors = validate_password($data['password']);
        $errors = array_merge($errors, $pwd_errors);
    }
    
    if (empty($data['role_id'])) {
        $errors[] = 'Role is required';
    }
    
    return $errors;
}

// Hire new employee (create employee + user account)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hire_employee'])) {
    $emp_errors = validate_employee($_POST);
    $user_errors = validate_user($_POST, $conn);
    $all_errors = array_merge($emp_errors, $user_errors);
    
    if (!empty($all_errors)) {
        $error = '<div class="alert alert-danger"><strong>Validation Errors:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
        foreach ($all_errors as $err) {
            $error .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $error .= '</ul></div>';
    } else {
        // Check duplicate email
        $email = $conn->real_escape_string($_POST['email']);
        $check_email = $conn->query("SELECT employee_id FROM employees WHERE email = '$email'");
        if ($check_email->num_rows > 0) {
            $error = '<div class="alert alert-danger"><strong>Error:</strong> Email already exists in the system.</div>';
        } else {
            // Check duplicate username
            $username = $conn->real_escape_string($_POST['username']);
            $check_user = $conn->query("SELECT user_id FROM users WHERE username = '$username'");
            if ($check_user->num_rows > 0) {
                $error = '<div class="alert alert-danger"><strong>Error:</strong> Username already exists. Choose a different username.</div>';
            } else {
                // Generate employee code (GETAFE-YYYY-###)
                $year = date('Y');
                $last_emp = $conn->query("SELECT employee_code FROM employees WHERE employee_code LIKE 'GETAFE-$year-%' ORDER BY employee_code DESC LIMIT 1")->fetch_assoc();
                $next_num = 1;
                if ($last_emp) {
                    preg_match('/GETAFE-\d+-(\d+)/', $last_emp['employee_code'], $matches);
                    $next_num = intval($matches[1]) + 1;
                }
                $employee_code = 'GETAFE-' . $year . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert employee
                    $first_name = $conn->real_escape_string($_POST['first_name']);
                    $last_name = $conn->real_escape_string($_POST['last_name']);
                    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
                    $position = $conn->real_escape_string($_POST['position']);
                    $salary = floatval($_POST['salary']);
                    $date_hired = $conn->real_escape_string($_POST['date_hired']);
                    
                    $emp_insert = $conn->query("INSERT INTO employees (employee_code, first_name, last_name, email, phone, position, salary, date_hired, status) 
                                               VALUES ('$employee_code', '$first_name', '$last_name', '$email', '$phone', '$position', $salary, '$date_hired', 'active')");
                    
                    if (!$emp_insert) {
                        throw new Exception("Failed to create employee record");
                    }
                    
                    $employee_id = $conn->insert_id;
                    
                    // Insert user
                    $password = $conn->real_escape_string($_POST['password']);
                    $role_id = (int)$_POST['role_id'];
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, employee_id, status) VALUES (?, ?, ?, ?, 'active')");
                    $stmt->bind_param("ssii", $username, $password, $role_id, $employee_id);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to create user account");
                    }
                    
                    $stmt->close();
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $message = '<div class="alert alert-success">
                        <strong>✓ Success!</strong> Employee hired and user account created!<br>
                        <small>
                            <strong>Employee Code:</strong> ' . htmlspecialchars($employee_code) . '<br>
                            <strong>Username:</strong> ' . htmlspecialchars($username) . '<br>
                            <strong>Password:</strong> ' . htmlspecialchars($_POST['password']) . ' (Share securely with employee)
                        </small>
                    </div>';
                    
                    // Clear form
                    $_POST = [];
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = '<div class="alert alert-danger"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
        }
    }
}

// Handle user deactivation with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_user'])) {
    $user_id = (int)$_POST['user_id'];
    $reason = $conn->real_escape_string($_POST['deactivation_reason'] ?? 'No reason provided');
    
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive', deactivation_reason = ?, deactivated_at = NOW() WHERE user_id = ?");
    $stmt->bind_param("si", $reason, $user_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-warning"><strong>✓ User Deactivated!</strong> Access revoked.</div>';
    } else {
        $error = '<div class="alert alert-danger"><strong>Error:</strong> Failed to deactivate user.</div>';
    }
    $stmt->close();
}

// Handle user reactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET status = 'active', deactivation_reason = NULL, deactivated_at = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success"><strong>✓ User Reactivated!</strong> Access restored.</div>';
    } else {
        $error = '<div class="alert alert-danger"><strong>Error:</strong> Failed to reactivate user.</div>';
    }
    $stmt->close();
}

// Get data for users table
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'active';
$role_filter = $_GET['role'] ?? '';

$where = "WHERE u.status = '" . $conn->real_escape_string($status_filter) . "'";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where .= " AND (u.username LIKE '%$search%' OR e.first_name LIKE '%$search%' OR e.last_name LIKE '%$search%')";
}
if (!empty($role_filter)) {
    $role_filter = (int)$role_filter;
    $where .= " AND u.role_id = $role_filter";
}

$users = $conn->query("SELECT u.*, e.first_name, e.last_name, e.employee_code, r.role_name FROM users u JOIN employees e ON u.employee_id = e.employee_id JOIN roles r ON u.role_id = r.role_id $where ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Count active and inactive users
$active_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
$inactive_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hire New Employee - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .form-section h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .password-strength {
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            background: #f0f0f0;
        }
        
        .password-strength.weak { background: #ffebee; color: #c62828; }
        .password-strength.medium { background: #fff3e0; color: #e65100; }
        .password-strength.strong { background: #e8f5e9; color: #2e7d32; }
        
        .section-divider {
            border-top: 2px solid #f0f0f0;
            margin: 25px 0;
            padding-top: 25px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .button-group button {
            flex: 1;
        }
        
        .status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .status-tab {
            padding: 10px 20px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .status-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .status-tab.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .deactivation-reason {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin-top: 10px;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Hire New Employee</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($admin['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>

        <!-- Hire New Employee Form -->
        <div class="form-section">
            <h3>👨‍💼 Hire New Employee & Create User Account</h3>
            <p style="color: #666; margin-bottom: 20px;">
                This form creates both an employee record and a user login account. The employee code is auto-generated.
            </p>
            
            <form method="POST">
                <!-- Employee Information Section -->
                <div class="section-title">📋 Employee Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" name="first_name" id="first_name" placeholder="e.g., John" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" name="last_name" id="last_name" placeholder="e.g., Dela Cruz" required value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" id="email" placeholder="e.g., john@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="e.g., 09123456789" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="position">Position *</label>
                        <input type="text" name="position" id="position" placeholder="e.g., HR Officer" required value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="salary">Monthly Salary (₱) *</label>
                        <input type="number" name="salary" id="salary" placeholder="e.g., 25000" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['salary'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_hired">Date Hired *</label>
                        <input type="date" name="date_hired" id="date_hired" required value="<?php echo htmlspecialchars($_POST['date_hired'] ?? date('Y-m-d')); ?>">
                    </div>
                </div>

                <!-- User Account Section -->
                <div class="section-divider">
                    <div class="section-title">🔐 User Account Setup</div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" name="username" id="username" placeholder="e.g., john.delacruz" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        <small style="color: #666;">4+ characters, letters/numbers/underscores only</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" id="password" placeholder="Create strong password" required>
                        <div id="pwd-strength" class="password-strength" style="display:none;"></div>
                        <small style="color: #666;">Must: 8+ chars, 1 uppercase, 1 lowercase, 1 number</small>
                    </div>

                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select name="role_id" id="role" required>
                            <option value="">-- Select Role --</option>
                            <option value="1" <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == 1 ? 'selected' : ''); ?>>HR Administrator</option>
                            <option value="2" <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == 2 ? 'selected' : ''); ?>>HR Staff</option>
                            <option value="3" <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == 3 ? 'selected' : ''); ?>>Employee</option>
                        </select>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" name="hire_employee" class="btn btn-primary">✅ Hire Employee & Create Account</button>
                </div>
            </form>
        </div>

        <!-- Status Tabs -->
        <div class="status-tabs">
            <a href="?status=active" class="status-tab <?php echo ($status_filter === 'active' ? 'active' : ''); ?>">
                ✅ Active Users (<?php echo $active_count; ?>)
            </a>
            <a href="?status=inactive" class="status-tab <?php echo ($status_filter === 'inactive' ? 'active' : ''); ?>">
                🔒 Deactivated Users (<?php echo $inactive_count; ?>)
            </a>
        </div>

        <!-- Search & Filter -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <h3 style="margin-bottom: 15px;">🔍 Find Employees</h3>
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <input type="text" name="search" placeholder="Search by username or name..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role">
                    <option value="">-- All Roles --</option>
                    <option value="1" <?php echo ($role_filter == 1 ? 'selected' : ''); ?>>HR Administrator</option>
                    <option value="2" <?php echo ($role_filter == 2 ? 'selected' : ''); ?>>HR Staff</option>
                    <option value="3" <?php echo ($role_filter == 3 ? 'selected' : ''); ?>>Employee</option>
                </select>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <button type="submit" class="btn btn-primary">🔍 Search</button>
                <?php if (!empty($search) || !empty($role_filter)): ?>
                    <a href="manage_users.php?status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <h3>👥 <?php echo ($status_filter === 'active' ? 'Active Employees' : 'Deactivated Employees'); ?> (<?php echo count($users); ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <?php if ($status_filter === 'inactive'): ?>
                            <th>Deactivated Date</th>
                            <th>Reason</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['employee_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : '-'; ?></td>
                            <?php if ($status_filter === 'inactive'): ?>
                                <td><?php echo $user['deactivated_at'] ? format_datetime($user['deactivated_at']) : '-'; ?></td>
                                <td>
                                    <?php if ($user['deactivation_reason']): ?>
                                        <span class="deactivation-reason">📌 <?php echo htmlspecialchars($user['deactivation_reason']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($status_filter === 'active'): ?>
                                    <button class="btn btn-warning" onclick="openDeactivateModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        🔒 Deactivate
                                    </button>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="reactivate_user" class="btn btn-success">🔓 Reactivate</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 30px;">
                            <?php echo $status_filter === 'active' ? 'No active employees found' : 'No deactivated employees'; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Deactivation Modal -->
    <div id="deactivateModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeDeactivateModal()">&times;</span>
            <h3 style="margin-bottom: 20px;">🔒 Deactivate User</h3>
            
            <form method="POST">
                <input type="hidden" id="modal_user_id" name="user_id">
                
                <p style="margin-bottom: 15px; color: #666;">
                    <strong>Username:</strong> <span id="modal_username"></span>
                </p>
                
                <div class="form-group">
                    <label for="deactivation_reason">Reason for Deactivation *</label>
                    <textarea name="deactivation_reason" id="deactivation_reason" rows="4" placeholder="e.g., Resigned, On leave, Suspended, etc." required></textarea>
                    <small style="color: #666;">This will be recorded in the system for audit purposes</small>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" name="deactivate_user" class="btn btn-danger" style="flex: 1;">🔒 Confirm Deactivate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const pwd = this.value;
            const strengthEl = document.getElementById('pwd-strength');
            
            if (pwd.length === 0) {
                strengthEl.style.display = 'none';
                return;
            }
            
            let strength = 0;
            if (pwd.length >= 8) strength++;
            if (/[a-z]/.test(pwd)) strength++;
            if (/[A-Z]/.test(pwd)) strength++;
            if (/[0-9]/.test(pwd)) strength++;
            
            strengthEl.style.display = 'block';
            
            if (strength < 2) {
                strengthEl.className = 'password-strength weak';
                strengthEl.textContent = '⚠ Weak password';
            } else if (strength < 4) {
                strengthEl.className = 'password-strength medium';
                strengthEl.textContent = '⚡ Medium strength';
            } else {
                strengthEl.className = 'password-strength strong';
                strengthEl.textContent = '✓ Strong password';
            }
        });

        // Modal functions for deactivation
        function openDeactivateModal(userId, username) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_username').textContent = username;
            document.getElementById('deactivateModal').style.display = 'block';
        }

        function closeDeactivateModal() {
            document.getElementById('deactivateModal').style.display = 'none';
            document.getElementById('deactivation_reason').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deactivateModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
