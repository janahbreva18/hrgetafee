<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/payroll_functions.php';

require_login();
check_role(2);

$staff = get_employee_info($conn, $_SESSION['employee_id']);
$message = '';
$error = '';

// Handle preview payroll (for review before processing)
$payroll_preview = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_payroll'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    if ($employee_id && $month && $year) {
        $payroll_preview = calculate_enhanced_payroll($conn, $employee_id, $month, $year);
        $payroll_preview['employee_id'] = $employee_id;
        $payroll_preview['month'] = $month;
        $payroll_preview['year'] = $year;
        $payroll_preview['employee_name'] = get_employee_name($conn, $employee_id);
        
        // Get leave earned information
        $leave_earned = calculate_leave_earned($conn, $employee_id, $month, $year);
        $payroll_preview['leave_earned'] = $leave_earned;
    }
}

// Handle process payroll (after review)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payroll'])) {
    $employee_id = (int)$_POST['employee_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    // Check if payroll already exists for this period
    $check = $conn->query("SELECT payroll_id FROM payroll WHERE employee_id = $employee_id AND payroll_month = $month AND payroll_year = $year");
    if ($check->num_rows > 0) {
        $error = '<div class="alert alert-danger"><strong>Error:</strong> Payroll already processed for this employee in this period.</div>';
    } else {
        $payroll_data = calculate_enhanced_payroll($conn, $employee_id, $month, $year);
        
        $stmt = $conn->prepare(
            "INSERT INTO payroll (employee_id, payroll_month, payroll_year, gross_salary, deductions, net_salary, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'processed')"
        );
        $stmt->bind_param(
            "iiiddd",
            $employee_id,
            $month,
            $year,
            $payroll_data['gross_salary'],
            $payroll_data['total_deductions'],
            $payroll_data['net_salary']
        );
        
        if ($stmt->execute()) {
            $payroll_id = $stmt->insert_id;
            
            // Save detailed breakdown
            if (save_payroll_details($conn, $payroll_id, $payroll_data)) {
                $message = '<div class="alert alert-success">✓ Payroll processed successfully with detailed breakdown saved</div>';
                $payroll_preview = null;
            } else {
                $message = '<div class="alert alert-warning">✓ Payroll processed but error saving details</div>';
            }
        } else {
            $error = '<div class="alert alert-danger">Error processing payroll</div>';
        }
        $stmt->close();
    }
}

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    
    $stmt = $conn->prepare("UPDATE payroll SET status = 'paid' WHERE payroll_id = ?");
    $stmt->bind_param("i", $payroll_id);
    
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">✓ Payroll marked as paid</div>';
    } else {
        $error = '<div class="alert alert-danger">Error updating payroll status</div>';
    }
    $stmt->close();
}

// Get employees
$employees = $conn->query("SELECT * FROM employees WHERE status = 'active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get payroll records with details
$payroll = $conn->query(
    "SELECT p.*, e.first_name, e.last_name, e.employee_code FROM payroll p 
     JOIN employees e ON p.employee_id = e.employee_id 
     ORDER BY p.payroll_year DESC, p.payroll_month DESC LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payroll-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .payroll-preview {
            background: #f5f7fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 5px solid #667eea;
            margin-bottom: 30px;
        }
        
        .preview-header {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .preview-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        
        .preview-item label {
            font-weight: 600;
            color: #333;
        }
        
        .preview-item.section-header {
            background: #667eea;
            color: white;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
        }
        
        .preview-item.section-header label {
            color: white;
        }
        
        .deduction-item {
            padding: 10px;
            background: #ffebee;
            border-left: 3px solid #f44336;
            margin-bottom: 8px;
            border-radius: 4px;
        }
        
        .deduction-item label {
            font-weight: 600;
            color: #c62828;
        }
        
        .total-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: center;
        }
        
        .total-box h3 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .total-amount {
            font-size: 36px;
            font-weight: bold;
        }
        
        .preview-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .preview-actions button {
            flex: 1;
        }
        
        .salary-slip {
            background: white;
            padding: 30px;
            border: 2px solid #667eea;
            border-radius: 8px;
            max-width: 800px;
            margin: 20px auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .slip-header {
            text-align: center;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .slip-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .slip-row.total {
            border-bottom: 2px solid #667eea;
            font-weight: bold;
            padding: 12px 0;
        }
        
        .slip-section {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-info {
            background: #e3f2fd;
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Payroll Management (Enhanced)</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>

        <!-- Process Payroll Form -->
        <div class="payroll-section">
            <h3>💰 Process Payroll (Enhanced with CPF, CBIF, MPL)</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Select an employee and month to calculate payroll based on attendance, approved leaves, and all statutory deductions.
            </p>
            
            <form method="POST" style="max-width: 500px;">
                <div class="form-group">
                    <label for="employee">Employee *</label>
                    <select name="employee_id" id="employee" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo (isset($_POST['employee_id']) && $_POST['employee_id'] == $emp['employee_id'] ? 'selected' : ''); ?>>
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> (<?php echo htmlspecialchars($emp['position']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label for="month">Month *</label>
                        <input type="number" name="month" id="month" min="1" max="12" value="<?php echo (isset($_POST['month']) ? $_POST['month'] : date('m')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="year">Year *</label>
                        <input type="number" name="year" id="year" value="<?php echo (isset($_POST['year']) ? $_POST['year'] : date('Y')); ?>" required>
                    </div>
                </div>

                <button type="submit" name="preview_payroll" class="btn btn-primary" style="width: 100%;">👁️ Preview Payroll</button>
            </form>
        </div>

        <!-- Payroll Preview -->
        <?php if ($payroll_preview): ?>
        <div class="payroll-preview">
            <h3 style="margin-bottom: 20px;">📋 Payroll Preview & Review</h3>
            
            <div class="salary-slip">
                <div class="slip-header">
                    <h2 style="margin: 0; color: #667eea;">SALARY SLIP</h2>
                    <p style="margin: 5px 0; color: #666; font-size: 12px;">HRGetafe - Getafe LGU</p>
                </div>
                
                <div class="slip-row">
                    <strong>Employee:</strong>
                    <strong><?php echo htmlspecialchars($payroll_preview['employee_name']); ?></strong>
                </div>
                <div class="slip-row">
                    <strong>Period:</strong>
                    <strong><?php echo $payroll_preview['month'] . '/' . $payroll_preview['year']; ?></strong>
                </div>
                <div class="slip-row">
                    <strong>AMS (Service):</strong>
                    <strong><?php echo number_format($payroll_preview['ams'], 1); ?> years</strong>
                </div>
                
                <!-- Attendance Summary -->
                <div class="slip-section">
                    <h4 style="margin: 0 0 10px 0; color: #667eea;">Attendance Summary</h4>
                    <div class="slip-row">
                        <span>Present Days:</span>
                        <span><?php echo $payroll_preview['present_days']; ?> days</span>
                    </div>
                    <div class="slip-row">
                        <span>Absences:</span>
                        <span><?php echo $payroll_preview['absences']; ?> days</span>
                    </div>
                    <div class="slip-row">
                        <span>Late Arrivals:</span>
                        <span><?php echo $payroll_preview['lates']; ?> times</span>
                    </div>
                    <div class="slip-row">
                        <span>Approved Leaves:</span>
                        <span><?php echo $payroll_preview['approved_leaves']; ?> days</span>
                    </div>
                </div>
                
                <!-- Leave Earned Information -->
                <div class="slip-section">
                    <h4 style="margin: 0 0 10px 0; color: #667eea;">Leave Earned (Based on AMS)</h4>
                    <div class="slip-row">
                        <span>Leave Earned (Days):</span>
                        <span><?php echo number_format($payroll_preview['leave_earned']['leave_earned_days'], 3); ?> days</span>
                    </div>
                    <div class="slip-row">
                        <span>Leave Value:</span>
                        <span><?php echo format_currency($payroll_preview['leave_earned']['leave_earned_amount']); ?></span>
                    </div>
                </div>
                
                <!-- Earnings & Deductions -->
                <div class="slip-section">
                    <h4 style="margin: 0 0 10px 0;">Earnings & Deductions</h4>
                    <div class="slip-row">
                        <span>Gross Salary:</span>
                        <span style="color: #2e7d32; font-weight: bold;"><?php echo format_currency($payroll_preview['gross_salary']); ?></span>
                    </div>
                    
                    <div style="margin-top: 10px; margin-bottom: 5px; font-size: 12px; color: #c62828; font-weight: bold;">STATUTORY DEDUCTIONS:</div>
                    
                    <div class="slip-row" style="background: #fff3cd; margin-bottom: 5px;">
                        <span style="font-size: 12px;">SSS Contribution (3.63%)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['sss_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #fff3cd; margin-bottom: 5px;">
                        <span style="font-size: 12px;">PhilHealth Premium (2.75%)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['philhealth_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #fff3cd; margin-bottom: 5px;">
                        <span style="font-size: 12px;">Pag-IBIG (2.00%)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['pagibig_deduction']); ?></span>
                    </div>
                    
                    <div style="margin-top: 10px; margin-bottom: 5px; font-size: 12px; color: #c62828; font-weight: bold;">SPECIAL DEDUCTIONS:</div>
                    
                    <div class="slip-row" style="background: #ffebee; margin-bottom: 5px;">
                        <span style="font-size: 12px;">CPF (Consolidated Police Fund)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['cpf_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #ffebee; margin-bottom: 5px;">
                        <span style="font-size: 12px;">CBIF (Comprehensive Benefit Insurance)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['cbif_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #ffebee; margin-bottom: 5px;">
                        <span style="font-size: 12px;">MPL (Magna Carta for Public Employees)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['mpl_deduction']); ?></span>
                    </div>
                    
                    <div style="margin-top: 10px; margin-bottom: 5px; font-size: 12px; color: #c62828; font-weight: bold;">VARIABLE DEDUCTIONS:</div>
                    
                    <div class="slip-row" style="background: #ffebee; margin-bottom: 5px;">
                        <span style="font-size: 12px;">Absence Deduction (<?php echo $payroll_preview['absences']; ?> × ₱500)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['absence_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #ffebee; margin-bottom: 5px;">
                        <span style="font-size: 12px;">Late Deduction (<?php echo $payroll_preview['lates']; ?> × ₱100)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['late_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="background: #ffebee;">
                        <span style="font-size: 12px;">BIR Income Tax (12%)</span>
                        <span style="color: #c62828;">-<?php echo format_currency($payroll_preview['bir_deduction']); ?></span>
                    </div>
                    
                    <div class="slip-row" style="margin-top: 10px; background: #f0f0f0; font-weight: bold;">
                        <span>Total Deductions:</span>
                        <span>-<?php echo format_currency($payroll_preview['total_deductions']); ?></span>
                    </div>
                </div>
                
                <div class="slip-row total" style="font-size: 16px; margin-top: 15px;">
                    <span>NET SALARY:</span>
                    <span style="color: #2e7d32;"><?php echo format_currency($payroll_preview['net_salary']); ?></span>
                </div>
            </div>
            
            <div class="preview-actions">
                <form method="POST" style="display: inline; flex: 1;">
                    <input type="hidden" name="employee_id" value="<?php echo $payroll_preview['employee_id']; ?>">
                    <input type="hidden" name="month" value="<?php echo $payroll_preview['month']; ?>">
                    <input type="hidden" name="year" value="<?php echo $payroll_preview['year']; ?>">
                    <button type="submit" name="preview_payroll" class="btn btn-secondary" style="width: 100%; margin: 0;">↩️ Back to Edit</button>
                </form>
                <form method="POST" style="display: inline; flex: 1;">
                    <input type="hidden" name="employee_id" value="<?php echo $payroll_preview['employee_id']; ?>">
                    <input type="hidden" name="month" value="<?php echo $payroll_preview['month']; ?>">
                    <input type="hidden" name="year" value="<?php echo $payroll_preview['year']; ?>">
                    <button type="submit" name="confirm_payroll" class="btn btn-primary" style="width: 100%; margin: 0;">✅ Confirm & Process</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payroll Records -->
        <div class="payroll-section">
            <h3>📊 Payroll Records</h3>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payroll)): ?>
                        <?php foreach ($payroll as $pay): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></strong><br><small><?php echo $pay['employee_code']; ?></small></td>
                            <td><?php echo $pay['payroll_month'] . '/' . $pay['payroll_year']; ?></td>
                            <td><?php echo format_currency($pay['gross_salary']); ?></td>
                            <td><?php echo format_currency($pay['deductions']); ?></td>
                            <td><strong><?php echo format_currency($pay['net_salary']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo ($pay['status'] === 'paid' ? 'badge-success' : 'badge-warning'); ?>">
                                    <?php echo ucfirst($pay['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($pay['status'] === 'processed'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="payroll_id" value="<?php echo $pay['payroll_id']; ?>">
                                        <button type="submit" name="mark_paid" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">💳 Mark Paid</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #2e7d32; font-weight: bold;">✓ Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 30px;">No payroll records yet</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
