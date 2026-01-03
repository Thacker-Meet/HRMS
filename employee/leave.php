<?php
/**
 * DAYFLOW HRMS - Employee Leave Application
 * File: employee/leave.php
 */

require_once '../config/db.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (hasRole(['Admin', 'HR'])) {
    redirect('admin/leave_requests.php');
}

$db = getDB();
$employeeId = getCurrentEmployeeId();
$errors = [];

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveTypeId = (int)$_POST['leave_type_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = sanitize($_POST['reason']);
    
    // Validation
    if (empty($leaveTypeId) || empty($startDate) || empty($endDate) || empty($reason)) {
        $errors[] = "All fields are required";
    }
    
    if (strtotime($startDate) < strtotime(date('Y-m-d'))) {
        $errors[] = "Start date cannot be in the past";
    }
    
    if (strtotime($endDate) < strtotime($startDate)) {
        $errors[] = "End date must be after start date";
    }
    
    if (empty($errors)) {
        try {
            // Calculate total days
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $interval = $start->diff($end);
            $totalDays = $interval->days + 1;
            
            // Check for overlapping leave
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM leave_requests 
                WHERE employee_id = ? 
                AND status IN ('Pending', 'Approved')
                AND (
                    (start_date BETWEEN ? AND ?) OR
                    (end_date BETWEEN ? AND ?) OR
                    (? BETWEEN start_date AND end_date)
                )
            ");
            $stmt->execute([$employeeId, $startDate, $endDate, $startDate, $endDate, $startDate]);
            
            if ($stmt->fetch()['count'] > 0) {
                $errors[] = "You already have a leave request for these dates";
            } else {
                // Insert leave request
                $stmt = $db->prepare("
                    INSERT INTO leave_requests (
                        employee_id, leave_type_id, start_date, end_date, 
                        total_days, reason, applied_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'Pending')
                ");
                $stmt->execute([$employeeId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason]);
                
                setFlashMessage('success', 'Leave request submitted successfully!');
                redirect('employee/leave.php');
            }
            
        } catch(PDOException $e) {
            $errors[] = "Failed to submit leave request";
            error_log($e->getMessage());
        }
    }
}

// Get leave types
try {
    $stmt = $db->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_type");
    $leaveTypes = $stmt->fetchAll();
    
    // Get employee leave history
    $stmt = $db->prepare("
        SELECT lr.*, lt.leave_type, u.email as reviewed_by_email
        FROM leave_requests lr
        INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN users u ON lr.reviewed_by = u.user_id
        WHERE lr.employee_id = ?
        ORDER BY lr.applied_date DESC
    ");
    $stmt->execute([$employeeId]);
    $leaveHistory = $stmt->fetchAll();
    
    // Get leave summary
    $stmt = $db->prepare("
        SELECT 
            lt.leave_type,
            lt.max_days_per_year,
            COALESCE(SUM(CASE WHEN lr.status = 'Approved' 
                AND YEAR(lr.start_date) = YEAR(CURDATE()) THEN lr.total_days END), 0) as used_days
        FROM leave_types lt
        LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id AND lr.employee_id = ?
        WHERE lt.is_active = 1
        GROUP BY lt.id, lt.leave_type, lt.max_days_per_year
    ");
    $stmt->execute([$employeeId]);
    $leaveSummary = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $leaveTypes = $leaveHistory = $leaveSummary = [];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - <?php echo APP_NAME; ?></title>
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
        .sidebar .logo { padding: 20px; font-size: 1.3rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .leave-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #667eea; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo"><i class="fas fa-user-tie"></i> Employee Portal</div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link active" href="leave.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
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

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <h3 class="mb-4"><i class="fas fa-calendar-times me-2"></i> Leave Management</h3>

        <div class="row">
            <!-- Leave Balance -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Leave Balance</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($leaveSummary as $summary): ?>
                            <div class="leave-card">
                                <h6><?php echo $summary['leave_type']; ?></h6>
                                <div class="progress mb-2" style="height: 25px;">
                                    <?php 
                                    $percentage = $summary['max_days_per_year'] > 0 
                                        ? ($summary['used_days'] / $summary['max_days_per_year']) * 100 
                                        : 0;
                                    ?>
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo min($percentage, 100); ?>%;">
                                        <?php echo $summary['used_days']; ?> / <?php echo $summary['max_days_per_year']; ?>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    Available: <?php echo max(0, $summary['max_days_per_year'] - $summary['used_days']); ?> days
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Apply for Leave -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Apply for Leave</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Leave Type *</label>
                                    <select name="leave_type_id" class="form-control" required>
                                        <option value="">Select Leave Type</option>
                                        <?php foreach($leaveTypes as $type): ?>
                                            <option value="<?php echo $type['id']; ?>">
                                                <?php echo $type['leave_type']; ?> 
                                                (<?php echo $type['is_paid'] ? 'Paid' : 'Unpaid'; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" name="start_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" name="end_date" class="form-control" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Reason *</label>
                                    <textarea name="reason" class="form-control" rows="3" 
                                              placeholder="Please provide reason for leave..." required></textarea>
                                </div>
                                
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Submit Leave Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Leave History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Applied Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveHistory)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No leave requests found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($leaveHistory as $leave): ?>
                                <tr>
                                    <td><?php echo $leave['leave_type']; ?></td>
                                    <td><?php echo formatDate($leave['start_date']); ?></td>
                                    <td><?php echo formatDate($leave['end_date']); ?></td>
                                    <td><?php echo $leave['total_days']; ?></td>
                                    <td><?php echo substr($leave['reason'], 0, 50); ?>...</td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'Pending' => 'warning',
                                            'Approved' => 'success',
                                            'Rejected' => 'danger',
                                            'Cancelled' => 'secondary'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $badges[$leave['status']]; ?>">
                                            <?php echo $leave['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($leave['applied_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>