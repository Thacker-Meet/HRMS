<?php
/**
 * DAYFLOW HRMS - Employee Payroll View
 * File: employee/payroll.php
 */

require_once '../config/db.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (hasRole(['Admin', 'HR'])) {
    redirect('admin/payroll.php');
}

$db = getDB();
$employeeId = getCurrentEmployeeId();

// Get payroll records
try {
    $stmt = $db->prepare("
        SELECT * FROM payroll
        WHERE employee_id = ?
        ORDER BY pay_period_year DESC, pay_period_month DESC
    ");
    $stmt->execute([$employeeId]);
    $payrollRecords = $stmt->fetchAll();
    
    // Get current salary structure
    $stmt = $db->prepare("
        SELECT * FROM salary_structure WHERE employee_id = ?
    ");
    $stmt->execute([$employeeId]);
    $salaryStructure = $stmt->fetch();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $payrollRecords = [];
    $salaryStructure = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payroll - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            width: 250px;
        }
        .sidebar .logo { padding: 20px; font-size: 1.3rem; font-weight: bold; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .salary-breakdown {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .breakdown-item:last-child { border-bottom: none; }
        .breakdown-total {
            font-size: 1.3rem;
            font-weight: bold;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-user-tie"></i> Employee Portal</div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link" href="leave.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link active" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <h3 class="mb-4"><i class="fas fa-money-bill-wave me-2"></i> My Payroll</h3>

        <?php if ($salaryStructure): ?>
        <!-- Current Salary Structure -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-rupee-sign me-2"></i> Current Salary Structure</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success mb-3">Earnings</h6>
                                <div class="breakdown-item">
                                    <span>Basic Salary</span>
                                    <strong><?php echo formatCurrency($salaryStructure['basic_salary']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>HRA</span>
                                    <strong><?php echo formatCurrency($salaryStructure['hra']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Conveyance Allowance</span>
                                    <strong><?php echo formatCurrency($salaryStructure['conveyance_allowance']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Medical Allowance</span>
                                    <strong><?php echo formatCurrency($salaryStructure['medical_allowance']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Special Allowance</span>
                                    <strong><?php echo formatCurrency($salaryStructure['special_allowance']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Other Allowances</span>
                                    <strong><?php echo formatCurrency($salaryStructure['other_allowances']); ?></strong>
                                </div>
                                <div class="breakdown-total text-success">
                                    <div class="d-flex justify-content-between">
                                        <span>Gross Salary</span>
                                        <strong><?php echo formatCurrency($salaryStructure['gross_salary']); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="text-danger mb-3">Deductions</h6>
                                <div class="breakdown-item">
                                    <span>Provident Fund (PF)</span>
                                    <strong><?php echo formatCurrency($salaryStructure['pf_deduction']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Tax Deduction (TDS)</span>
                                    <strong><?php echo formatCurrency($salaryStructure['tax_deduction']); ?></strong>
                                </div>
                                <div class="breakdown-item">
                                    <span>Other Deductions</span>
                                    <strong><?php echo formatCurrency($salaryStructure['other_deductions']); ?></strong>
                                </div>
                                <div class="breakdown-total text-danger">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Deductions</span>
                                        <strong><?php echo formatCurrency($salaryStructure['total_deductions']); ?></strong>
                                    </div>
                                </div>
                                <div class="breakdown-total text-primary mt-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Net Salary (Take Home)</span>
                                        <strong><?php echo formatCurrency($salaryStructure['net_salary']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payroll History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Payroll History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payrollRecords)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice-dollar fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No payroll records found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Period</th>
                                    <th>Days Present</th>
                                    <th>Gross Salary</th>
                                    <th>Deductions</th>
                                    <th>Net Salary</th>
                                    <th>Payment Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payrollRecords as $record): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php echo date('F Y', mktime(0, 0, 0, $record['pay_period_month'], 1, $record['pay_period_year'])); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $record['days_present']; ?> days</td>
                                    <td><?php echo formatCurrency($record['gross_salary']); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($record['total_deductions']); ?></td>
                                    <td class="text-success">
                                        <strong><?php echo formatCurrency($record['net_salary']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'Pending' => 'warning',
                                            'Processed' => 'info',
                                            'Paid' => 'success'
                                        ];
                                        $class = $statusClass[$record['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $class; ?>">
                                            <?php echo $record['payment_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#salarySlipModal<?php echo $record['id']; ?>">
                                            <i class="fas fa-file-download me-1"></i> View Slip
                                        </button>
                                    </td>
                                </tr>

                                <!-- Salary Slip Modal -->
                                <div class="modal fade" id="salarySlipModal<?php echo $record['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Salary Slip - 
                                                    <?php echo date('F Y', mktime(0, 0, 0, $record['pay_period_month'], 1, $record['pay_period_year'])); ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="text-center mb-4">
                                                    <h4><?php echo APP_NAME; ?></h4>
                                                    <p class="text-muted">Salary Slip</p>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-6">
                                                        <strong>Employee ID:</strong> <?php echo $_SESSION['employee_code']; ?>
                                                    </div>
                                                    <div class="col-6">
                                                        <strong>Pay Period:</strong> 
                                                        <?php echo date('F Y', mktime(0, 0, 0, $record['pay_period_month'], 1, $record['pay_period_year'])); ?>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <h6 class="text-success">Earnings</h6>
                                                        <table class="table table-sm">
                                                            <tr><td>Basic Salary</td><td class="text-end"><?php echo formatCurrency($record['basic_salary']); ?></td></tr>
                                                            <tr><td>HRA</td><td class="text-end"><?php echo formatCurrency($record['hra']); ?></td></tr>
                                                            <tr><td>Conveyance</td><td class="text-end"><?php echo formatCurrency($record['conveyance_allowance']); ?></td></tr>
                                                            <tr><td>Medical</td><td class="text-end"><?php echo formatCurrency($record['medical_allowance']); ?></td></tr>
                                                            <tr><td>Special</td><td class="text-end"><?php echo formatCurrency($record['special_allowance']); ?></td></tr>
                                                            <tr class="fw-bold">
                                                                <td>Gross Salary</td>
                                                                <td class="text-end text-success"><?php echo formatCurrency($record['gross_salary']); ?></td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                    <div class="col-6">
                                                        <h6 class="text-danger">Deductions</h6>
                                                        <table class="table table-sm">
                                                            <tr><td>PF Deduction</td><td class="text-end"><?php echo formatCurrency($record['pf_deduction']); ?></td></tr>
                                                            <tr><td>Tax (TDS)</td><td class="text-end"><?php echo formatCurrency($record['tax_deduction']); ?></td></tr>
                                                            <tr><td>Other</td><td class="text-end"><?php echo formatCurrency($record['other_deductions']); ?></td></tr>
                                                            <tr class="fw-bold">
                                                                <td>Total Deductions</td>
                                                                <td class="text-end text-danger"><?php echo formatCurrency($record['total_deductions']); ?></td>
                                                            </tr>
                                                        </table>
                                                        <div class="alert alert-success">
                                                            <strong>Net Salary:</strong> 
                                                            <span class="float-end"><?php echo formatCurrency($record['net_salary']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-4">
                                                        <strong>Days Present:</strong> <?php echo $record['days_present']; ?>
                                                    </div>
                                                    <div class="col-4">
                                                        <strong>Days Absent:</strong> <?php echo $record['days_absent']; ?>
                                                    </div>
                                                    <div class="col-4">
                                                        <strong>Days Leave:</strong> <?php echo $record['days_leave']; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" onclick="window.print()">
                                                    <i class="fas fa-print me-2"></i> Print
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>