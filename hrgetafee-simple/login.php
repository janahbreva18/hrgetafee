<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role_id = $_SESSION['role_id'];
    $redirect_map = [
        1 => 'admin/dashboard.php',
        2 => 'staff/dashboard.php',
        3 => 'employee/dashboard.php'
    ];
    header("Location: " . ($redirect_map[$role_id] ?? 'login.php'));
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter username and password';
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, role_id, employee_id, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check password (for testing purposes)
            if ($password === 'admin123' || $password === 'password123' || password_verify($password, $user['password'])) {
                // Set Session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                
                // Update last login
                $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update->bind_param("i", $user['user_id']);
                $update->execute();
                
                // Redirect
                $redirect_map = [
                    1 => 'admin/dashboard.php',
                    2 => 'staff/dashboard.php',
                    3 => 'employee/dashboard.php'
                ];
                header("Location: " . $redirect_map[$user['role_id']]);
                exit;
            } else {
                $error_message = 'Invalid password';
            }
        } else {
            $error_message = 'User not found or inactive';
        }
        $stmt->close();
    }
    $conn->close();
}

// Get background image if it exists in assets
$bg_image = 'assets/images/login-bg.jpg';
$bg_style = file_exists($bg_image) ? "background-image: url('$bg_image');" : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRGetafe - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            <?php echo $bg_style; ?>
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }
        
        .login-wrapper {
            position: relative;
            z-index: 2;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>HRGetafe</h1>
                <p>Human Resources Information System<br>Getafe LGU</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>