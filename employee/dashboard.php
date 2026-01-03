<?php
/**
 * DAYFLOW HRMS - Employee Dashboard
 * File: employee/dashboard.php
 */

require_once '../config/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

// Redirect admin/HR to their dashboard
if (hasRole(['Admin', 'HR'])) {
    redirect('admin/dashboard.php');
}

$db = getDB();
$employeeId = getCurrentEmployeeId();

try {
    // Get employee details
    $stmt = $db->prepare("
        SELECT e.*, s.net_salary 
        FROM employees e
        LEFT JOIN salary_structure s ON e.id = s.employee_id
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    // Get today's attendance
    $stmt = $db->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND attendance_date = CURDATE()
    ");
    $stmt->execute([$employeeId]);
    $todayAttendance = $stmt->fetch();
    
    // Get this month attendance stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN status = 'Leave' THEN 1 END) as leave_days
        FROM attendance
        WHERE employee_id = ? 
        AND MONTH(attendance_date) = MONTH(CURDATE())
        AND YEAR(attendance_date) = YEAR(CURDATE())
    ");
    $stmt->execute([$employeeId]);
    $monthStats = $stmt->fetch();
    
    // Get pending leave requests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM leave_requests 
        WHERE employee_id = ? AND status = 'Pending'
    ");
    $stmt->execute([$employeeId]);
    $pendingLeaves = $stmt->fetch()['count'];
    
    // Get recent leave requests
    $stmt = $db->prepare("
        SELECT lr.*, lt.leave_type 
        FROM leave_requests lr
        INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.employee_id = ?
        ORDER BY lr.applied_date DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentLeaves = $stmt->fetchAll();
    
    // Get this week's attendance
    $stmt = $db->prepare("
        SELECT * FROM attendance
        WHERE employee_id = ?
        AND YEARWEEK(attendance_date, 1) = YEARWEEK(CURDATE(), 1)
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$employeeId]);
    $weekAttendance = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $employee = null;
    $todayAttendance = null;
    $monthStats = ['present_days' => 0, 'absent_days' => 0, 'leave_days' => 0];
    $pendingLeaves = 0;
    $recentLeaves = [];
    $weekAttendance = [];
}

// Handle check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'check_in' && !$todayAttendance) {
            $stmt = $db->prepare("
                INSERT INTO attendance (employee_id, attendance_date, check_in_time, status, marked_by)
                VALUES (?, CURDATE(), CURTIME(), 'Present', ?)
            ");
            $stmt->execute([$employeeId, getCurrentUserId()]);
            setFlashMessage('success', 'Checked in successfully!');
            redirect('employee/dashboard.php');
            
        } elseif ($action === 'check_out' && $todayAttendance && !$todayAttendance['check_out_time']) {
            $workingHours = calculateWorkingHours($todayAttendance['check_in_time'], date('H:i:s'));
            
            $stmt = $db->prepare("
                UPDATE attendance 
                SET check_out_time = CURTIME(), working_hours = ?
                WHERE id = ?
            ");
            $stmt->execute([$workingHours, $todayAttendance['id']]);
            setFlashMessage('success', 'Checked out successfully!');
            redirect('employee/dashboard.php');
        }
        
    } catch(PDOException $e) {
        setFlashMessage('error', 'Failed to update attendance.');
        error_log($e->getMessage());
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - <?php echo APP_NAME; ?></title>
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
            width: 250px;
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
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 10px 0;
        }
        .attendance-btn {
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .badge-lg {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-user-tie"></i> Employee Portal
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link active" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a class="nav-link" href="profile.php">
                <i class="fas fa-user me-2"></i> My Profile
            </a>
            <a class="nav-link" href="attendance.php">
                <i class="fas fa-calendar-check me-2"></i> Attendance
            </a>
            <a class="nav-link" href="leave.php">
                <i class="fas fa-calendar-times me-2"></i> Leave Requests
            </a>
            <a class="nav-link" href="payroll.php">
                <i class="fas fa-money-bill-wave me-2"></i> Payroll
            </a>
            <a class="nav-link" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h3><?php echo $employee['full_name']; ?></h3>
                    <p class="mb-1"><i class="fas fa-id-badge me-2"></i> <?php echo $employee['employee_id']; ?></p>
                    <p class="mb-1"><i class="fas fa-briefcase me-2"></i> <?php echo $employee['designation']; ?></p>
                    <p class="mb-0"><i class="fas fa-building me-2"></i> <?php echo $employee['department']; ?></p>
                </div>
                <div class="col-md-3 text-center">
                    <h5>Monthly Salary</h5>
                    <h3><?php echo formatCurrency($employee['net_salary'] ?? 0); ?></h3>
                </div>
            </div>
        </div>

        <!-- Attendance Action -->
        <div class="card mb-4">
            <div class="card-body text-center p-4">
                <h5 class="mb-3">Today's Attendance</h5>
                <?php if (!$todayAttendance): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="check_in">
                        <button type="submit" class="btn btn-success attendance-btn">
                            <i class="fas fa-sign-in-alt me-2"></i> Check In
                        </button>
                    </form>
                    <p class="text-muted mt-2">You haven't checked in today</p>
                <?php elseif ($todayAttendance && !$todayAttendance['check_out_time']): ?>
                    <div class="alert alert-info d-inline-block">
                        <i class="fas fa-clock me-2"></i> Checked in at 
                        <strong><?php echo date('h:i A', strtotime($todayAttendance['check_in_time'])); ?></strong>
                    </div>
                    <br>
                    <form method="POST" class="d-inline mt-3">
                        <input type="hidden" name="action" value="check_out">
                        <button type="submit" class="btn btn-danger attendance-btn">
                            <i class="fas fa-sign-out-alt me-2"></i> Check Out
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success d-inline-block">
                        <i class="fas fa-check-circle me-2"></i> 
                        Checked in: <?php echo date('h:i A', strtotime($todayAttendance['check_in_time'])); ?> | 
                        Checked out: <?php echo date('h:i A', strtotime($todayAttendance['check_out_time'])); ?>
                        <br>Working Hours: <strong><?php echo $todayAttendance['working_hours']; ?> hrs</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-calendar-check text-success" style="font-size: 2rem;"></i>
                    <h3 class="text-success"><?php echo $monthStats['present_days']; ?></h3>
                    <p class="text-muted mb-0">Days Present</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-calendar-times text-danger" style="font-size: 2rem;"></i>
                    <h3 class="text-danger"><?php echo $monthStats['absent_days']; ?></h3>
                    <p class="text-muted mb-0">Days Absent</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-umbrella-beach text-info" style="font-size: 2rem;"></i>
                    <h3 class="text-info"><?php echo $monthStats['leave_days']; ?></h3>
                    <p class="text-muted mb-0">Days on Leave</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-clock text-warning" style="font-size: 2rem;"></i>
                    <h3 class="text-warning"><?php echo $pendingLeaves; ?></h3>
                    <p class="text-muted mb-0">Pending Leaves</p>
                </div>
            </div>
        </div>

        <!-- Recent Leave Requests -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Leave Requests</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentLeaves)): ?>
                    <p class="text-muted text-center mb-0">No leave requests found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentLeaves as $leave): ?>
                                <tr>
                                    <td><?php echo $leave['leave_type']; ?></td>
                                    <td><?php echo formatDate($leave['start_date']); ?></td>
                                    <td><?php echo formatDate($leave['end_date']); ?></td>
                                    <td><?php echo $leave['total_days']; ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = [
                                            'Pending' => 'warning',
                                            'Approved' => 'success',
                                            'Rejected' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $badgeClass[$leave['status']]; ?>">
                                            <?php echo $leave['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="leave.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Apply for Leave
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>