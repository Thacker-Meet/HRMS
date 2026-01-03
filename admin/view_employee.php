<?php
/**
 * DAYFLOW HRMS - View Employee Details
 * File: admin/view_employee.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();
$employeeId = (int)($_GET['id'] ?? 0);

// Get complete employee details
try {
    $stmt = $db->prepare("
        SELECT e.*, u.email, u.is_active, u.last_login, 
               r.role_name, s.*, 
               manager.full_name as manager_name
        FROM employees e
        INNER JOIN users u ON e.user_id = u.user_id
        INNER JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN salary_structure s ON e.id = s.employee_id
        LEFT JOIN employees manager ON e.reporting_manager = manager.id
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        setFlashMessage('error', 'Employee not found');
        redirect('admin/employees.php');
    }
    
    // Get attendance summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Leave' THEN 1 END) as leaves
        FROM attendance
        WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(CURDATE())
    ");
    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch();
    
    // Get leave summary
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM leave_requests
        WHERE employee_id = ?
        GROUP BY status
    ");
    $stmt->execute([$employeeId]);
    $leaveSummary = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    redirect('admin/employees.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo APP_NAME; ?></title>
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
        .main-content { margin-left: 250px; padding: 20px; }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            font-weight: bold;
            border: 5px solid rgba(255,255,255,0.3);
        }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .info-label { font-weight: 600; color: #6c757d; margin-bottom: 5px; }
        .info-value { font-size: 1.1rem; color: #212529; }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .stat-box h3 { margin: 0; color: #667eea; }
        .stat-box p { margin: 5px 0 0; color: #6c757d; }
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
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user me-2"></i> Employee Profile</h3>
            <div>
                <a href="edit_employee.php?id=<?php echo $employee['id']; ?>" class="btn btn-primary me-2">
                    <i class="fas fa-edit me-2"></i> Edit Profile
                </a>
                <a href="employees.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="profile-avatar mx-auto">
                        <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                    </div>
                </div>
                <div class="col-md-7">
                    <h2><?php echo $employee['full_name']; ?></h2>
                    <p class="mb-1"><i class="fas fa-id-badge me-2"></i> <?php echo $employee['employee_id']; ?></p>
                    <p class="mb-1"><i class="fas fa-briefcase me-2"></i> <?php echo $employee['designation']; ?></p>
                    <p class="mb-0"><i class="fas fa-building me-2"></i> <?php echo $employee['department']; ?></p>
                </div>
                <div class="col-md-3 text-center">
                    <span class="badge <?php echo $employee['is_active'] ? 'bg-success' : 'bg-danger'; ?> p-2 mb-2" 
                          style="font-size: 1rem;">
                        <?php echo $employee['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <p class="mb-0 mt-2"><small>Role: <?php echo $employee['role_name']; ?></small></p>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $attendance['present'] ?? 0; ?></h3>
                    <p>Days Present</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $attendance['absent'] ?? 0; ?></h3>
                    <p>Days Absent</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo $attendance['leaves'] ?? 0; ?></h3>
                    <p>On Leave</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <h3><?php echo formatCurrency($employee['net_salary'] ?? 0); ?></h3>
                    <p>Monthly Salary</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i> Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo $employee['full_name']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo formatDate($employee['date_of_birth']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo $employee['gender']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo $employee['email']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo $employee['phone'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?php echo $employee['address'] ?? 'N/A'; ?><br>
                                <?php echo $employee['city'] ? $employee['city'] . ', ' : ''; ?>
                                <?php echo $employee['state'] ? $employee['state'] . ' - ' : ''; ?>
                                <?php echo $employee['zip_code']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Job Information -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i> Job Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><?php echo $employee['employee_id']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?php echo $employee['department']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Designation</div>
                            <div class="info-value"><?php echo $employee['designation']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Date of Joining</div>
                            <div class="info-value"><?php echo formatDate($employee['date_of_joining']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Employment Type</div>
                            <div class="info-value"><?php echo $employee['employment_type']; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Reporting Manager</div>
                            <div class="info-value"><?php echo $employee['manager_name'] ?? 'N/A'; ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Last Login</div>
                            <div class="info-value">
                                <?php echo $employee['last_login'] ? formatDate($employee['last_login'], 'd-M-Y h:i A') : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Salary Information -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Salary Structure</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">Earnings</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['basic_salary'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>HRA</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['hra'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Conveyance Allowance</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['conveyance_allowance'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Medical Allowance</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['medical_allowance'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Special Allowance</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['special_allowance'] ?? 0); ?></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td>Gross Salary</td>
                                        <td class="text-end text-success"><?php echo formatCurrency($employee['gross_salary'] ?? 0); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-danger">Deductions</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>PF Deduction</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['pf_deduction'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tax Deduction</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['tax_deduction'] ?? 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Other Deductions</td>
                                        <td class="text-end"><?php echo formatCurrency($employee['other_deductions'] ?? 0); ?></td>
                                    </tr>
                                    <tr class="fw-bold">
                                        <td>Total Deductions</td>
                                        <td class="text-end text-danger"><?php echo formatCurrency($employee['total_deductions'] ?? 0); ?></td>
                                    </tr>
                                    <tr class="fw-bold bg-light">
                                        <td>Net Salary</td>
                                        <td class="text-end text-primary"><?php echo formatCurrency($employee['net_salary'] ?? 0); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>