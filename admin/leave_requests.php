<?php
/**
 * DAYFLOW HRMS - Admin Leave Request Management
 * File: admin/leave_requests.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leaveId = (int)$_POST['leave_id'];
    $action = $_POST['action'];
    $adminRemarks = sanitize($_POST['admin_remarks'] ?? '');
    
    try {
        $db->beginTransaction();
        
        if ($action === 'approve') {
            // Update leave request
            $stmt = $db->prepare("
                UPDATE leave_requests 
                SET status = 'Approved', 
                    reviewed_by = ?, 
                    reviewed_date = CURDATE(),
                    admin_remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([getCurrentUserId(), $adminRemarks, $leaveId]);
            
            // Get leave details
            $stmt = $db->prepare("SELECT employee_id, start_date, end_date FROM leave_requests WHERE id = ?");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch();
            
            // Mark attendance as 'Leave' for approved dates
            $start = new DateTime($leave['start_date']);
            $end = new DateTime($leave['end_date']);
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
            
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                
                // Insert or update attendance
                $stmt = $db->prepare("
                    INSERT INTO attendance (employee_id, attendance_date, status, marked_by)
                    VALUES (?, ?, 'Leave', ?)
                    ON DUPLICATE KEY UPDATE status = 'Leave', marked_by = ?
                ");
                $stmt->execute([
                    $leave['employee_id'], 
                    $dateStr, 
                    getCurrentUserId(),
                    getCurrentUserId()
                ]);
            }
            
            setFlashMessage('success', 'Leave request approved successfully!');
            
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("
                UPDATE leave_requests 
                SET status = 'Rejected', 
                    reviewed_by = ?, 
                    reviewed_date = CURDATE(),
                    admin_remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([getCurrentUserId(), $adminRemarks, $leaveId]);
            
            setFlashMessage('success', 'Leave request rejected.');
        }
        
        $db->commit();
        redirect('admin/leave_requests.php');
        
    } catch(PDOException $e) {
        $db->rollBack();
        setFlashMessage('error', 'Failed to process leave request.');
        error_log($e->getMessage());
    }
}

// Get leave requests
try {
    $status = $_GET['status'] ?? 'Pending';
    
    $stmt = $db->prepare("
        SELECT 
            lr.*,
            e.full_name,
            e.employee_id as emp_code,
            e.department,
            lt.leave_type,
            u.email as reviewed_by_email
        FROM leave_requests lr
        INNER JOIN employees e ON lr.employee_id = e.id
        INNER JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN users u ON lr.reviewed_by = u.user_id
        WHERE lr.status = ?
        ORDER BY lr.applied_date DESC
    ");
    $stmt->execute([$status]);
    $leaveRequests = $stmt->fetchAll();
    
    // Get counts
    $counts = [];
    foreach(['Pending', 'Approved', 'Rejected'] as $s) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE status = ?");
        $stmt->execute([$s]);
        $counts[$s] = $stmt->fetch()['count'];
    }
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $leaveRequests = [];
    $counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - <?php echo APP_NAME; ?></title>
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
        .status-tab { cursor: pointer; padding: 15px; text-align: center; border-radius: 10px; transition: all 0.3s; margin-bottom: 10px; }
        .status-tab:hover { transform: translateY(-3px); }
        .status-tab.active { box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo"><i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?></div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="employees.php"><i class="fas fa-users me-2"></i> Employees</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link active" href="leave_requests.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
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

        <h3 class="mb-4"><i class="fas fa-calendar-times me-2"></i> Leave Request Management</h3>

        <!-- Status Tabs -->
        <div class="row mb-4">
            <div class="col-md-4">
                <a href="?status=Pending" class="text-decoration-none">
                    <div class="status-tab bg-warning text-white <?php echo $status === 'Pending' ? 'active' : ''; ?>">
                        <h4><?php echo $counts['Pending']; ?></h4>
                        <p class="mb-0">Pending Requests</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="?status=Approved" class="text-decoration-none">
                    <div class="status-tab bg-success text-white <?php echo $status === 'Approved' ? 'active' : ''; ?>">
                        <h4><?php echo $counts['Approved']; ?></h4>
                        <p class="mb-0">Approved Requests</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="?status=Rejected" class="text-decoration-none">
                    <div class="status-tab bg-danger text-white <?php echo $status === 'Rejected' ? 'active' : ''; ?>">
                        <h4><?php echo $counts['Rejected']; ?></h4>
                        <p class="mb-0">Rejected Requests</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $status; ?> Leave Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Applied Date</th>
                                <?php if ($status === 'Pending'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr>
                                    <td colspan="<?php echo $status === 'Pending' ? '7' : '6'; ?>" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No <?php echo strtolower($status); ?> leave requests</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($leaveRequests as $request): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $request['full_name']; ?></strong><br>
                                        <small class="text-muted"><?php echo $request['emp_code']; ?> - <?php echo $request['department']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $request['leave_type']; ?></span>
                                    </td>
                                    <td>
                                        <?php echo formatDate($request['start_date']); ?><br>
                                        to<br>
                                        <?php echo formatDate($request['end_date']); ?>
                                    </td>
                                    <td><strong><?php echo $request['total_days']; ?> days</strong></td>
                                    <td><?php echo substr($request['reason'], 0, 50); ?>...</td>
                                    <td><?php echo formatDate($request['applied_date']); ?></td>
                                    <?php if ($status === 'Pending'): ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>

                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">Approve Leave Request</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <p><strong>Employee:</strong> <?php echo $request['full_name']; ?></p>
                                                    <p><strong>Duration:</strong> <?php echo $request['total_days']; ?> days</p>
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Remarks (Optional)</label>
                                                        <textarea name="admin_remarks" class="form-control" rows="3"></textarea>
                                                    </div>
                                                    <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Approve Leave</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">Reject Leave Request</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <div class="modal-body">
                                                    <p><strong>Employee:</strong> <?php echo $request['full_name']; ?></p>
                                                    <div class="mb-3">
                                                        <label class="form-label">Reason for Rejection *</label>
                                                        <textarea name="admin_remarks" class="form-control" rows="3" required></textarea>
                                                    </div>
                                                    <input type="hidden" name="leave_id" value="<?php echo $request['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Reject Leave</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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