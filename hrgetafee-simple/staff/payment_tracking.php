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

// Create payment_records table if not exists
$check_table = $conn->query("SHOW TABLES LIKE 'payment_records'");
if ($check_table->num_rows == 0) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS payment_records (
            payment_id INT PRIMARY KEY AUTO_INCREMENT,
            payroll_id INT NOT NULL,
            employee_id INT NOT NULL,
            payment_date DATE,
            payment_method ENUM('cash', 'bank_transfer', 'check', 'other') DEFAULT 'cash',
            payment_reference VARCHAR(100),
            bank_account VARCHAR(50),
            check_number VARCHAR(50),
            amount_paid DECIMAL(10, 2),
            payment_notes TEXT,
            paid_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payroll_id) REFERENCES payroll(payroll_id),
            FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
            FOREIGN KEY (paid_by) REFERENCES users(user_id)
        )
    ");
}

// Handle record payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $employee_id = (int)$_POST['employee_id'];
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $payment_reference = $_POST['payment_reference'] ?? '';
    $bank_account = $_POST['bank_account'] ?? '';
    $check_number = $_POST['check_number'] ?? '';
    $payment_notes = $_POST['payment_notes'] ?? '';
    
    // Get payroll amount
    $payroll = $conn->query("SELECT net_salary FROM payroll WHERE payroll_id = $payroll_id")->fetch_assoc();
    $amount_paid = floatval($payroll['net_salary']);
    
    // Check if payment already recorded
    $check_payment = $conn->query("SELECT payment_id FROM payment_records WHERE payroll_id = $payroll_id");
    if ($check_payment->num_rows > 0) {
        $error = '<div class="alert alert-danger">Payment already recorded for this payroll period.</div>';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO payment_records 
            (payroll_id, employee_id, payment_date, payment_method, payment_reference, bank_account, check_number, amount_paid, payment_notes, paid_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $paid_by = $_SESSION['user_id'];
        $stmt->bind_param(
            "iisssssssi",
            $payroll_id,
            $employee_id,
            $payment_date,
            $payment_method,
            $payment_reference,
            $bank_account,
            $check_number,
            $amount_paid,
            $payment_notes,
            $paid_by
        );
        
        if ($stmt->execute()) {
            // Update payroll status to paid
            $conn->query("UPDATE payroll SET status = 'paid' WHERE payroll_id = $payroll_id");
            $message = '<div class="alert alert-success">✓ Payment recorded successfully!</div>';
        } else {
            $error = '<div class="alert alert-danger">Error recording payment: ' . $conn->error . '</div>';
        }
        $stmt->close();
    }
}

// Handle delete payment record
if (isset($_GET['delete_payment_id'])) {
    $payment_id = (int)$_GET['delete_payment_id'];
    $payment = $conn->query("SELECT payroll_id FROM payment_records WHERE payment_id = $payment_id")->fetch_assoc();
    
    if ($payment) {
        $conn->query("DELETE FROM payment_records WHERE payment_id = $payment_id");
        $conn->query("UPDATE payroll SET status = 'processed' WHERE payroll_id = " . $payment['payroll_id']);
        $message = '<div class="alert alert-warning">Payment record deleted.</div>';
    }
}

// Get unprocessed payroll
$unprocessed = $conn->query(
    "SELECT p.*, e.first_name, e.last_name, e.employee_code FROM payroll p 
     JOIN employees e ON p.employee_id = e.employee_id 
     WHERE p.status = 'processed' 
     ORDER BY p.payroll_year DESC, p.payroll_month DESC"
)->fetch_all(MYSQLI_ASSOC);

// Get payment records
$payment_records = $conn->query(
    "SELECT pr.*, e.first_name, e.last_name, e.employee_code, u.username, p.payroll_month, p.payroll_year
     FROM payment_records pr 
     JOIN employees e ON pr.employee_id = e.employee_id
     JOIN users u ON pr.paid_by = u.user_id
     JOIN payroll p ON pr.payroll_id = p.payroll_id
     ORDER BY pr.payment_date DESC LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - HRGetafe</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .payment-section {
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
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .table-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-cash {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .badge-bank {
            background: #bbdefb;
            color: #1565c0;
        }
        
        .badge-check {
            background: #ffe0b2;
            color: #e65100;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-danger {
            background: #f44336;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        }
        
        .modal-close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .method-specific {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            border-radius: 4px;
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
            font-size: 28px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Payment Tracking & Receipts</h2>
        <div class="navbar-user">
            <span>Welcome, <strong><?php echo htmlspecialchars($staff['first_name']); ?></strong></span>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Dashboard</a>

        <?php if ($message) echo $message; ?>
        <?php if ($error) echo $error; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php
                $paid_count = $conn->query("SELECT COUNT(*) as count FROM payment_records")->fetch_assoc()['count'];
                $total_paid = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payment_records")->fetch_assoc()['total'];
                $pending_count = count($unprocessed);
            ?>
            <div class="stat-card">
                <h4>Total Payments Recorded</h4>
                <div class="stat-value"><?php echo $paid_count; ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Amount Paid</h4>
                <div class="stat-value">₱<?php echo number_format($total_paid, 0); ?></div>
            </div>
            <div class="stat-card">
                <h4>Pending Payments</h4>
                <div class="stat-value"><?php echo $pending_count; ?></div>
            </div>
        </div>

        <!-- Record Payment Section -->
        <div class="payment-section">
            <h3>💳 Record Employee Payment</h3>
            <p style="color: #666; margin-bottom: 20px;">
                Record payment for processed payroll. Select payment method and enter payment details.
            </p>

            <?php if (!empty($unprocessed)): ?>
            <form method="POST" id="paymentForm">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="payroll">Select Employee & Payroll Period *</label>
                        <select name="payroll_id" id="payroll" required onchange="setEmployeeId()">
                            <option value="">-- Select --</option>
                            <?php foreach ($unprocessed as $pay): ?>
                                <option value="<?php echo $pay['payroll_id']; ?>" data-emp="<?php echo $pay['employee_id']; ?>" data-amount="<?php echo $pay['net_salary']; ?>">
                                    <?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?> - 
                                    <?php echo date('F Y', mktime(0, 0, 0, $pay['payroll_month'], 1, $pay['payroll_year'])); ?> 
                                    (₱<?php echo number_format($pay['net_salary'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="employee_id" id="employee_id">

                    <div class="form-group">
                        <label for="payment_date">Payment Date *</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select name="payment_method" id="payment_method" required onchange="showMethodFields()">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Bank Transfer Fields -->
                    <div class="method-specific" id="bank_fields">
                        <label for="bank_account">Bank Account / Transaction Reference *</label>
                        <input type="text" name="bank_account" id="bank_account" placeholder="e.g., BDO 1234-567890-123 or Transfer ID">
                    </div>

                    <!-- Check Fields -->
                    <div class="method-specific" id="check_fields">
                        <label for="check_number">Check Number *</label>
                        <input type="text" name="check_number" id="check_number" placeholder="e.g., CHK-2026-001">
                    </div>

                    <!-- Payment Reference -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="payment_reference">Payment Reference / Notes</label>
                        <input type="text" name="payment_reference" id="payment_reference" placeholder="e.g., Reference number, batch ID, etc.">
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="payment_notes">Additional Notes</label>
                        <textarea name="payment_notes" id="payment_notes" placeholder="Any additional information about this payment..."></textarea>
                    </div>
                </div>

                <button type="submit" name="record_payment" class="btn btn-primary" style="width: 100%; margin-top: 20px;">✓ Record Payment</button>
            </form>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: #999;">
                    No pending payments. All payroll has been recorded.
                </p>
            <?php endif; ?>
        </div>

        <!-- Payment Records -->
        <div class="table-container">
            <h3>📋 Payment Records History</h3>
            
            <?php if (!empty($payment_records)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_records as $record): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong><br>
                                <small><?php echo $record['employee_code']; ?></small>
                            </td>
                            <td><?php echo date('F Y', mktime(0, 0, 0, $record['payroll_month'], 1, $record['payroll_year'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($record['payment_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $record['payment_method'] === 'bank_transfer' ? 'bank' : ($record['payment_method'] === 'check' ? 'check' : 'cash'); ?>">
                                    <?php 
                                        $methods = [
                                            'cash' => '💵 Cash',
                                            'bank_transfer' => '🏦 Bank Transfer',
                                            'check' => '📄 Check',
                                            'other' => '📌 Other'
                                        ];
                                        echo $methods[$record['payment_method']] ?? ucfirst($record['payment_method']);
                                    ?>
                                </span>
                            </td>
                            <td><strong>₱<?php echo number_format($record['amount_paid'], 2); ?></strong></td>
                            <td>
                                <?php if ($record['payment_method'] === 'bank_transfer' && $record['bank_account']): ?>
                                    <small><?php echo htmlspecialchars($record['bank_account']); ?></small>
                                <?php elseif ($record['payment_method'] === 'check' && $record['check_number']): ?>
                                    <small><?php echo htmlspecialchars($record['check_number']); ?></small>
                                <?php elseif ($record['payment_reference']): ?>
                                    <small><?php echo htmlspecialchars($record['payment_reference']); ?></small>
                                <?php else: ?>
                                    <small style="color: #999;">-</small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars($record['username']); ?></small></td>
                            <td>
                                <button onclick="viewReceipt(<?php echo $record['payment_id']; ?>)" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">📄 Receipt</button>
                                <a href="?delete_payment_id=<?php echo $record['payment_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Delete this payment record?');">🗑️ Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="padding: 20px; text-align: center; color: #999;">
                    No payment records yet.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Receipt Modal -->
    <div id="receiptModal" class="payment-modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeReceipt()">&times;</span>
            <div id="receiptContent"></div>
            <button onclick="printReceipt()" class="btn btn-primary" style="width: 100%; margin-top: 20px;">🖨️ Print Receipt</button>
        </div>
    </div>

    <script>
        function setEmployeeId() {
            const select = document.getElementById('payroll');
            const empId = select.options[select.selectedIndex].getAttribute('data-emp');
            document.getElementById('employee_id').value = empId;
        }

        function showMethodFields() {
            const method = document.getElementById('payment_method').value;
            document.getElementById('bank_fields').style.display = method === 'bank_transfer' ? 'block' : 'none';
            document.getElementById('check_fields').style.display = method === 'check' ? 'block' : 'none';
        }

        function viewReceipt(paymentId) {
            // In a real scenario, fetch receipt data via AJAX
            // For now, show a simple receipt
            fetch(`payment_receipt_api.php?payment_id=${paymentId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('receiptContent').innerHTML = html;
                    document.getElementById('receiptModal').style.display = 'block';
                });
        }

        function closeReceipt() {
            document.getElementById('receiptModal').style.display = 'none';
        }

        function printReceipt() {
            window.print();
        }

        window.onclick = function(event) {
            const modal = document.getElementById('receiptModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
