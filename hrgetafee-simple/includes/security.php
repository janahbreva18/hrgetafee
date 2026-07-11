<?php
/**
 * Security Functions - Input Validation, Sanitization, CSRF Protection
 */

// Define a secret key for CSRF and encryption
if (!defined('SECRET_KEY')) {
    define('SECRET_KEY', 'your-secret-key-change-in-production');
}

// Generate CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize Input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Escape Output for Safe Display
function escapeOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

// Validate Email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate Username
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
}

// Validate Password
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*])(.{8,})$/', $password) === 1;
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Require Login
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// Require Role
function requireRole($required_roles) {
    requireLogin();
    $roles = is_array($required_roles) ? $required_roles : [$required_roles];
    if (!in_array($_SESSION['role_id'], $roles)) {
        http_response_code(403);
        die('Access Denied: You do not have permission to access this page.');
    }
}

// Log Security Event
function logSecurityEvent($connection, $user_id, $action, $description = '') {
    try {
        $stmt = $connection->prepare(
            "INSERT INTO security_logs (user_id, action, description, ip_address, timestamp) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $stmt->bind_param('isss', $user_id, $action, $description, $ip);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail - log table may not exist yet
    }
}

// Rate Limiting
function checkRateLimit($key, $limit = 5, $window = 300) {
    $cache_key = 'rate_limit:' . $key;
    
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    if (time() > $_SESSION[$cache_key]['reset_time']) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    $_SESSION[$cache_key]['count']++;
    
    return $_SESSION[$cache_key]['count'] <= $limit;
}
?>