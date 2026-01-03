<?php
/**
 * DAYFLOW HRMS - Reports & Analytics
 * File: admin/reports.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();

// Get report statistics
try {
    // Total employees
    $totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE user_id IN (SELECT user_id FROM users WHERE is_active = 1)")->fetchColumn();
    
    // This month attendance
    $stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent
        FROM attendance 
        WHERE MONTH(attendance_date) = MONTH(CURDATE())
    ");
    $monthAttendance = $stmt->fetch();
    
    // Department-wise count
    $stmt = $db->query("
        SELECT department, COUNT(*) as count
        FROM employees
        GROUP BY department
        ORDER BY count DESC
    ");
    $deptStats = $stmt->fetchAll();
    
    // Recent leave requests
    $stmt = $db->query("
        SELECT lr.*, e.full_name, lt.leave_type
        FROM leave_requests lr
        INNER JOIN employees e ON lr.employee_id = e.id
        INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
        ORDER BY lr.applied_date DESC
        LIMIT 10
    ");
    $recentLeaves = $stmt->fetchAll();
    
    // Monthly payroll summary
    $stmt = $db->query("
        SELECT 
            pay_period_month,
            pay_period_year,
            SUM(net_salary) as total_salary,
            COUNT(*) as employee_count
        FROM payroll
        WHERE pay_period_year = YEAR(CURDATE())
        GROUP BY pay_period_year, pay_period_month
        ORDER BY pay_period_month DESC
        LIMIT 6
    ");
    $payrollSummary = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $totalEmployees = 0;
    $monthAttendance = ['present' => 0, 'absent' => 0];
    $deptStats = [];
    $recentLeaves = [];
    $payrollSummary = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo APP_NAME; ?></title>
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
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .report-card i { font-size: 3rem; margin-bottom: 15px; }
        .chart-container { height: 300px; }
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
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link active" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <h3 class="mb-4"><i class="fas fa-chart-bar me-2"></i> Reports & Analytics</h3>

        <!-- Quick Report Access -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="report-card" onclick="window.location.href='attendance.php'">
                    <i class="fas fa-calendar-check text-primary"></i>
                    <h5>Attendance Report</h5>
                    <p class="text-muted mb-0">View detailed attendance</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card" onclick="window.location.href='leave_requests.php'">
                    <i class="fas fa-calendar-times text-success"></i>
                    <h5>Leave Report</h5>
                    <p class="text-muted mb-0">View leave statistics</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card" onclick="window.location.href='payroll.php'">
                    <i class="fas fa-money-bill-wave text-warning"></i>
                    <h5>Payroll Report</h5>
                    <p class="text-muted mb-0">View salary reports</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="report-card" onclick="window.location.href='employees.php'">
                    <i class="fas fa-users text-info"></i>
                    <h5>Employee Report</h5>
                    <p class="text-muted mb-0">View employee data</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Department Statistics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i> Department-wise Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Employees</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($deptStats as $dept): ?>
                                    <tr>
                                        <td><strong><?php echo $dept['department']; ?></strong></td>
                                        <td><?php echo $dept['count']; ?></td>
                                        <td>
                                            <?php 
                                            $percentage = $totalEmployees > 0 ? ($dept['count'] / $totalEmployees) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Payroll Summary -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Monthly Payroll Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Employees</th>
                                        <th>Total Salary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payrollSummary as $payroll): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?php echo date('F Y', mktime(0, 0, 0, $payroll['pay_period_month'], 1, $payroll['pay_period_year'])); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $payroll['employee_count']; ?></td>
                                        <td class="text-success">
                                            <strong><?php echo formatCurrency($payroll['total_salary']); ?></strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Leave Requests -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Leave Requests</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentLeaves as $leave): ?>
                                    <tr>
                                        <td><?php echo $leave['full_name']; ?></td>
                                        <td><?php echo $leave['leave_type']; ?></td>
                                        <td>
                                            <?php echo formatDate($leave['start_date']); ?> to 
                                            <?php echo formatDate($leave['end_date']); ?>
                                            (<?php echo $leave['total_days']; ?> days)
                                        </td>
                                        <td><?php echo formatDate($leave['applied_date']); ?></td>
                                        <td>
                                            <?php
                                            $colors = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
                                            $color = $colors[$leave['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>"><?php echo $leave['status']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Overview -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> This Month Attendance Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h2 class="text-primary"><?php echo $totalEmployees; ?></h2>
                                <p class="text-muted">Total Employees</p>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-success"><?php echo $monthAttendance['present']; ?></h2>
                                <p class="text-muted">Total Present</p>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-danger"><?php echo $monthAttendance['absent']; ?></h2>
                                <p class="text-muted">Total Absent</p>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-info">
                                    <?php 
                                    $totalDays = $monthAttendance['present'] + $monthAttendance['absent'];
                                    $attendanceRate = $totalDays > 0 ? ($monthAttendance['present'] / $totalDays) * 100 : 0;
                                    echo number_format($attendanceRate, 1); 
                                    ?>%
                                </h2>
                                <p class="text-muted">Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i> Export Reports</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Export comprehensive reports for record keeping and analysis</p>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="alert('Excel export feature - To be implemented')">
                        <i class="fas fa-file-excel me-2"></i> Export to Excel
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="alert('PDF export feature - To be implemented')">
                        <i class="fas fa-file-pdf me-2"></i> Export to PDF
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>