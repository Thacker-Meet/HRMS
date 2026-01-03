<?php
/**
 * DAYFLOW HRMS - Admin Payroll Management
 * File: admin/payroll.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();

// Handle payroll generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate_payroll') {
        $month = (int)$_POST['month'];
        $year = (int)$_POST['year'];
        
        try {
            $db->beginTransaction();
            
            // Get all active employees
            $stmt = $db->query("
                SELECT e.id, s.*
                FROM employees e
                INNER JOIN users u ON e.user_id = u.user_id
                INNER JOIN salary_structure s ON e.id = s.employee_id
                WHERE u.is_active = 1
            ");
            $employees = $stmt->fetchAll();
            
            $generated = 0;
            foreach ($employees as $emp) {
                // Check if payroll already exists
                $stmt = $db->prepare("
                    SELECT id FROM payroll 
                    WHERE employee_id = ? AND pay_period_month = ? AND pay_period_year = ?
                ");
                $stmt->execute([$emp['employee_id'], $month, $year]);
                
                if ($stmt->fetch()) {
                    continue; // Skip if already generated
                }
                
                // Get attendance data
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                        COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                        COUNT(CASE WHEN status = 'Leave' THEN 1 END) as leaves
                    FROM attendance
                    WHERE employee_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
                ");
                $stmt->execute([$emp['employee_id'], $month, $year]);
                $attendance = $stmt->fetch();
                
                // Insert payroll
                $stmt = $db->prepare("
                    INSERT INTO payroll (
                        employee_id, pay_period_month, pay_period_year,
                        basic_salary, hra, conveyance_allowance, medical_allowance,
                        special_allowance, other_allowances, gross_salary,
                        pf_deduction, tax_deduction, other_deductions, total_deductions,
                        net_salary, days_present, days_absent, days_leave,
                        payment_status, generated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
                ");
                $stmt->execute([
                    $emp['employee_id'], $month, $year,
                    $emp['basic_salary'], $emp['hra'], $emp['conveyance_allowance'],
                    $emp['medical_allowance'], $emp['special_allowance'], $emp['other_allowances'],
                    $emp['gross_salary'], $emp['pf_deduction'], $emp['tax_deduction'],
                    $emp['other_deductions'], $emp['total_deductions'], $emp['net_salary'],
                    $attendance['present'], $attendance['absent'], $attendance['leaves']
                ]);
                $generated++;
            }
            
            $db->commit();
            setFlashMessage('success', "Payroll generated for $generated employees!");
            redirect('admin/payroll.php');
            
        } catch(PDOException $e) {
            $db->rollBack();
            setFlashMessage('error', 'Failed to generate payroll.');
            error_log($e->getMessage());
        }
    }
}

// Get payroll records
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

try {
    $stmt = $db->prepare("
        SELECT p.*, e.full_name, e.employee_id as emp_code, e.department
        FROM payroll p
        INNER JOIN employees e ON p.employee_id = e.id
        WHERE p.pay_period_month = ? AND p.pay_period_year = ?
        ORDER BY e.full_name
    ");
    $stmt->execute([$month, $year]);
    $payrollRecords = $stmt->fetchAll();
    
    // Get summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_employees,
            SUM(gross_salary) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(net_salary) as total_net
        FROM payroll
        WHERE pay_period_month = ? AND pay_period_year = ?
    ");
    $stmt->execute([$month, $year]);
    $summary = $stmt->fetch();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $payrollRecords = [];
    $summary = ['total_employees' => 0, 'total_gross' => 0, 'total_deductions' => 0, 'total_net' => 0];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - <?php echo APP_NAME; ?></title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?></div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="employees.php"><i class="fas fa-users me-2"></i> Employees</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link" href="leave_requests.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link active" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-money-bill-wave me-2"></i> Payroll Management</h3>
            <div class="d-flex gap-2">
                <select class="form-select" name="month" onchange="window.location.href='payroll.php?month=' + this.value + '&year=' + document.querySelector('[name=year]').value">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>" 
                            <?php echo $month == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select class="form-select" name="year" onchange="window.location.href='payroll.php?month=' + document.querySelector('[name=month]').value + '&year=' + this.value">
                    <?php for($y = date('Y'); $y >= date('Y'); $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="border-left: 4px solid #667eea;">
                    <h6 class="text-muted">Total Employees</h6>
                    <h3><?php echo $summary['total_employees']; ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left: 4px solid #28a745;">
                    <h6 class="text-muted">Total Gross</h6>
                    <h3 class="text-success"><?php echo formatCurrency($summary['total_gross']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left: 4px solid #dc3545;">
                    <h6 class="text-muted">Total Deductions</h6>
                    <h3 class="text-danger"><?php echo formatCurrency($summary['total_deductions']); ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left: 4px solid #17a2b8;">
                    <h6 class="text-muted">Total Net Payable</h6>
                    <h3 class="text-info"><?php echo formatCurrency($summary['total_net']); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>