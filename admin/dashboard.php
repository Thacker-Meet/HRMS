<?php
/**
 * DAYFLOW HRMS - Admin Dashboard
 * File: admin/dashboard.php
 */

require_once '../config/db.php';

// Check if user is logged in and has admin/HR role
if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();

// Get statistics
try {
    // Total employees
    $stmt = $db->query("SELECT COUNT(*) as total FROM employees WHERE user_id IN (SELECT user_id FROM users WHERE is_active = 1)");
    $totalEmployees = $stmt->fetch()['total'];
    
    // Present today
    $stmt = $db->query("SELECT COUNT(*) as total FROM attendance WHERE attendance_date = CURDATE() AND status = 'Present'");
    $presentToday = $stmt->fetch()['total'];
    
    // Pending leave requests
    $stmt = $db->query("SELECT COUNT(*) as total FROM leave_requests WHERE status = 'Pending'");
    $pendingLeaves = $stmt->fetch()['total'];
    
    // Total departments
    $stmt = $db->query("SELECT COUNT(DISTINCT department) as total FROM employees");
    $totalDepartments = $stmt->fetch()['total'];
    
    // Recent leave requests
    $stmt = $db->query("
        SELECT lr.*, e.full_name, lt.leave_type 
        FROM leave_requests lr
        INNER JOIN employees e ON lr.employee_id = e.id
        INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.status = 'Pending'
        ORDER BY lr.applied_date DESC
        LIMIT 5
    ");
    $recentLeaves = $stmt->fetchAll();
    
    // Today's attendance
    $stmt = $db->query("
        SELECT e.full_name, e.employee_id, a.check_in_time, a.status
        FROM attendance a
        INNER JOIN employees e ON a.employee_id = e.id
        WHERE a.attendance_date = CURDATE()
        ORDER BY a.check_in_time DESC
        LIMIT 10
    ");
    $todayAttendance = $stmt->fetchAll();
    
    // Department-wise employee count
    $stmt = $db->query("
        SELECT department, COUNT(*) as count
        FROM employees
        GROUP BY department
        ORDER BY count DESC
        LIMIT 5
    ");
    $departmentStats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $totalEmployees = $presentToday = $pendingLeaves = $totalDepartments = 0;
    $recentLeaves = $todayAttendance = $departmentStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 100;
        }
        .sidebar .logo {
            padding: 20px;
            font-size: 1.3rem;
            font-weight: bold;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .top-navbar {
            background: white;
            padding: 15px 30px;
            margin: -20px -20px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.primary .icon { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.success .icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.warning .icon { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.info .icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-header {
            background: white;
            border-bottom: 2px solid #f4f6f9;
            font-weight: 600;
        }
        .badge-status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?>
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link active" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="employees.php">
                <i class="fas fa-users me-2"></i> Employees
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-calendar-check me-2"></i> Attendance
            </a>
            <a class="nav-link" href="leave_requests.php">
                <i class="fas fa-calendar-times me-2"></i> Leave Requests
            </a>
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-money-bill-wave me-2"></i> Payroll
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i> Reports
            </a>
            <a class="nav-link" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Dashboard</h4>
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-bell text-muted"></i>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                    </div>
                    <div class="ms-2">
                        <strong><?php echo $_SESSION['full_name']; ?></strong>
                        <br><small class="text-muted"><?php echo $_SESSION['role_name']; ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Employees</h6>
                            <h2 class="mb-0"><?php echo $totalEmployees; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Present Today</h6>
                            <h2 class="mb-0"><?php echo $presentToday; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Leaves</h6>
                            <h2 class="mb-0"><?php echo $pendingLeaves; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Departments</h6>
                            <h2 class="mb-0"><?php echo $totalDepartments; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Row -->
        <div class="row g-4">
            <!-- Pending Leave Requests -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-times me-2"></i> Pending Leave Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentLeaves)): ?>
                            <p class="text-muted text-center">No pending leave requests</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Duration</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentLeaves as $leave): ?>
                                        <tr>
                                            <td><?php echo $leave['full_name']; ?></td>
                                            <td><?php echo $leave['leave_type']; ?></td>
                                            <td><?php echo $leave['total_days']; ?> days</td>
                                            <td>
                                                <a href="leave_requests.php?id=<?php echo $leave['id']; ?>" 
                                                   class="btn btn-sm btn-primary">Review</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Today's Attendance -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Today's Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todayAttendance)): ?>
                            <p class="text-muted text-center">No attendance marked today</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Check-In</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($todayAttendance as $att): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $att['full_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $att['employee_id']; ?></small>
                                            </td>
                                            <td><?php echo $att['check_in_time'] ? date('h:i A', strtotime($att['check_in_time'])) : '-'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $att['status'] == 'Present' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $att['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>