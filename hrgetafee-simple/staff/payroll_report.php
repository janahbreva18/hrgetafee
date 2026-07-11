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

// Handle export to CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_payroll'])) {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $export_format = $_POST['export_format'] ?? 'csv';
    
    if (!$month || !$year) {
        $error = '<div class="alert alert-danger">Please select month and year</div>';
    } else {
        // Get all payroll records for the month
        $payroll_records = $conn->query(
            "SELECT p.*, e.first_name, e.last_name, e.employee_code, e.position, e.salary 
             FROM payroll p 
             JOIN employees e ON p.employee_id = e.employee_id 
             WHERE p.payroll_month = $month AND p.payroll_year = $year 
             ORDER BY e.employee_code"
        )->fetch_all(MYSQLI_ASSOC);
        
        if (empty($payroll_records)) {
            $error = '<div class="alert alert-danger">No payroll records found for this period</div>';
        } else {
            if ($export_format === 'csv') {
                // Generate CSV
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=Payroll_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv');
                
                $output = fopen('php://output', 'w');
                
                // Headers
                fputcsv($output, [
                    'Employee Code',
                    'Full Name',
                    'Position',
                    'Gross Salary',
                    'SSS',
                    'PhilHealth',
                    'Pag-IBIG',
                    'CPF',
                    'CBIF',
                    'MPL',
                    'Absence Deduction',
                    'Late Deduction',
                    'BIR Tax',
                    'Total Deductions',
                    'Net Salary',
                    'Status'
                ]);
                
                // Data rows
                foreach ($payroll_records as $record) {
                    $payroll_data = calculate_enhanced_payroll($conn, $record['employee_id'], $month, $year);
                    
                    fputcsv($output, [
                        $record['employee_code'],
                        $record['first_name'] . ' ' . $record['last_name'],
                        $record['position'],
                        number_format($payroll_data['gross_salary'], 2),
                        number_format($payroll_data['sss_deduction'], 2),
                        number_format($payroll_data['philhealth_deduction'], 2),
                        number_format($payroll_data['pagibig_deduction'], 2),
                        number_format($payroll_data['cpf_deduction'], 2),
                        number_format($payroll_data['cbif_deduction'], 2),
                        number_format($payroll_data['mpl_deduction'], 2),
                        number_format($payroll_data['absence_deduction'], 2),
                        number_format($payroll_data['late_deduction'], 2),
                        number_format($payroll_data['bir_deduction'], 2),
                        number_format($payroll_data['total_deductions'], 2),
                        number_format($payroll_data['net_salary'], 2),
                        ucfirst($record['status'])
                    ]);
                }
                
                fclose($output);
                exit;
            } elseif ($export_format === 'html') {
                // Generate HTML for printing
                $html_output = generate_payroll_report_html($conn, $payroll_records, $month, $year);
                header('Content-Type: text/html; charset=utf-8');
                echo $html_output;
                exit;
            }
        }
    }
}

// Generate HTML payroll report
function generate_payroll_report_html($conn, $payroll_records, $month, $year) {
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $total_gross = 0;
    $total_deductions = 0;
    $total_net = 0;
    
    // Calculate totals
    foreach ($payroll_records as $record) {
        $payroll_data = calculate_enhanced_payroll($conn, $record['employee_id'], $month, $year);
        $total_gross += $payroll_data['gross_salary'];
        $total_deductions += $payroll_data['total_deductions'];
        $total_net += $payroll_data['net_salary'];
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payroll Report - ' . $month_name . ' ' . $year . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 5px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f0f0f0; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: bold; }
        td { padding: 10px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #fafafa; }
        .total-row { background: #e8e8e8; font-weight: bold; }
        .amount { text-align: right; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: right; }
        .footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PAYROLL REPORT</h1>
        <p>Municipality of Getafe - Local Government Unit</p>
        <p><strong>Period: ' . $month_name . ' ' . $year . '</strong></p>
        <p>Generated: ' . date('F d, Y h:i A') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Employee Code</th>
                <th>Employee Name</th>
                <th>Position</th>
                <th class="amount">Gross Salary</th>
                <th class="amount">Total Deductions</th>
                <th class="amount">Net Salary</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($payroll_records as $record) {
        $payroll_data = calculate_enhanced_payroll($conn, $record['employee_id'], $month, $year);
        $html .= '<tr>
            <td>' . $record['employee_code'] . '</td>
            <td>' . $record['first_name'] . ' ' . $record['last_name'] . '</td>
            <td>' . $record['position'] . '</td>
            <td class="amount">₱' . number_format($payroll_data['gross_salary'], 2) . '</td>
            <td class="amount">₱' . number_format($payroll_data['total_deductions'], 2) . '</td>
            <td class="amount"><strong>₱' . number_format($payroll_data['net_salary'], 2) . '</strong></td>
        </tr>';
    }
    
    $html .= '</tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3">TOTAL</td>
                <td class="amount">₱' . number_format($total_gross, 2) . '</td>
                <td class="amount">₱' . number_format($total_deductions, 2) . '</td>
                <td class="amount">₱' . number_format($total_net, 2) . '</td>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <p><strong>Total Employees:</strong> ' . count($payroll_records) . '</p>
        <p><strong>Total Payroll:</strong> ₱' . number_format($total_net, 2) . '</p>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>';
    
    return $html;
}

// Get summary data
$months_with_payroll = $conn->query(
    "SELECT DISTINCT payroll_month, payroll_year FROM payroll ORDER BY payroll_year DESC, payroll_month DESC LIMIT 12"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Report & Export - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Payroll Report & Export</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>

        <!-- Export Payroll Section -->
        <div class="report-section">
            <h3>📊 Export Payroll Report</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Generate and export payroll reports for a specific month. Choose format: CSV (for spreadsheets) or HTML (for printing).
            </p>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="month">Month *</label>
                        <select name="month" id="month" required>
                            <option value="">-- Select Month --</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($m == date('m') ? 'selected' : ''); ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year">Year *</label>
                        <select name="year" id="year" required>
                            <option value="">-- Select Year --</option>
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == date('Y') ? 'selected' : ''); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="format">Export Format *</label>
                        <select name="export_format" id="format" required>
                            <option value="csv">CSV (Microsoft Excel)</option>
                            <option value="html">HTML (Print/Preview)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <button type="submit" name="export_payroll" class="btn btn-primary" style="width: 100%;">📥 Export Payroll</button>
                    <button type="reset" class="btn btn-secondary" style="width: 100%;">🔄 Clear</button>
                </div>
            </form>
        </div>

        <!-- Payroll Summary -->
        <div class="report-section">
            <h3>📈 Payroll Summary</h3>
            
            <?php if (!empty($months_with_payroll)): ?>
                <div class="table-container">
                    <h4>Recent Payroll Periods</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Total Employees</th>
                                <th>Total Gross</th>
                                <th>Total Deductions</th>
                                <th>Total Net</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($months_with_payroll as $period): ?>
                                <?php
                                    $m = $period['payroll_month'];
                                    $y = $period['payroll_year'];
                                    
                                    $summary = $conn->query(
                                        "SELECT 
                                            COUNT(*) as emp_count,
                                            SUM(gross_salary) as total_gross,
                                            SUM(deductions) as total_deductions,
                                            SUM(net_salary) as total_net
                                        FROM payroll 
                                        WHERE payroll_month = $m AND payroll_year = $y"
                                    )->fetch_assoc();
                                    
                                    $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                ?>
                            <tr>
                                <td><strong><?php echo $month_name . ' ' . $y; ?></strong></td>
                                <td><?php echo $summary['emp_count']; ?> employees</td>
                                <td>₱<?php echo number_format($summary['total_gross'], 2); ?></td>
                                <td>₱<?php echo number_format($summary['total_deductions'], 2); ?></td>
                                <td><strong>₱<?php echo number_format($summary['total_net'], 2); ?></strong></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="month" value="<?php echo $m; ?>">
                                        <input type="hidden" name="year" value="<?php echo $y; ?>">
                                        <input type="hidden" name="export_format" value="csv">
                                        <button type="submit" name="export_payroll" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">📥 CSV</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="month" value="<?php echo $m; ?>">
                                        <input type="hidden" name="year" value="<?php echo $y; ?>">
                                        <input type="hidden" name="export_format" value="html">
                                        <button type="submit" name="export_payroll" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">📄 Print</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: #999;">No payroll records found yet</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
