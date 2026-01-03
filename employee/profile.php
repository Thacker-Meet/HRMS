<?php
/**
 * DAYFLOW HRMS - Employee Profile (Self View/Edit)
 * File: employee/profile.php
 */

require_once '../config/db.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (hasRole(['Admin', 'HR'])) {
    redirect('admin/dashboard.php');
}

$db = getDB();
$employeeId = getCurrentEmployeeId();
$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $zipCode = sanitize($_POST['zip_code']);
    $alternatePhone = sanitize($_POST['alternate_phone'] ?? '');
    $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $emergencyRelation = sanitize($_POST['emergency_contact_relation'] ?? '');
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE employees SET
                    phone = ?, alternate_phone = ?, address = ?,
                    city = ?, state = ?, zip_code = ?,
                    emergency_contact_name = ?,
                    emergency_contact_phone = ?,
                    emergency_contact_relation = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $phone, $alternatePhone, $address, $city, $state, $zipCode,
                $emergencyName, $emergencyPhone, $emergencyRelation,
                $employeeId
            ]);
            
            setFlashMessage('success', 'Profile updated successfully!');
            redirect('employee/profile.php');
            
        } catch(PDOException $e) {
            $errors[] = "Failed to update profile.";
            error_log($e->getMessage());
        }
    }
}

// Get employee details
try {
    $stmt = $db->prepare("
        SELECT e.*, u.email, s.net_salary
        FROM employees e
        INNER JOIN users u ON e.user_id = u.user_id
        LEFT JOIN salary_structure s ON e.id = s.employee_id
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
} catch(PDOException $e) {
    error_log($e->getMessage());
    redirect('employee/dashboard.php');
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
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
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
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
        }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .info-row { display: flex; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { flex: 0 0 200px; font-weight: 600; color: #6c757d; }
        .info-value { flex: 1; color: #212529; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-user-tie"></i> Employee Portal</div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link" href="leave.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
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

        <h3 class="mb-4"><i class="fas fa-user me-2"></i> My Profile</h3>

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
                    <h4>Monthly Salary</h4>
                    <h3><?php echo formatCurrency($employee['net_salary'] ?? 0); ?></h3>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- View Information (Read-only) -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Basic Information</h5>
                        <small>These details cannot be changed. Contact HR for updates.</small>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value"><?php echo $employee['employee_id']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Full Name</div>
                            <div class="info-value"><?php echo $employee['full_name']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo $employee['email']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value"><?php echo formatDate($employee['date_of_birth']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo $employee['gender']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Department</div>
                            <div class="info-value"><?php echo $employee['department']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Designation</div>
                            <div class="info-value"><?php echo $employee['designation']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Date of Joining</div>
                            <div class="info-value"><?php echo formatDate($employee['date_of_joining']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Employment Type</div>
                            <div class="info-value"><?php echo $employee['employment_type']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Information -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-edit me-2"></i> Editable Information</h5>
                        <small>You can update these details</small>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="text" name="phone" class="form-control" 
                                    value="<?php echo $employee['phone']; ?>" maxlength="10" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Alternate Phone</label>
                                <input type="text" name="alternate_phone" class="form-control" 
                                    value="<?php echo $employee['alternate_phone']; ?>" maxlength="10">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo $employee['address']; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="<?php echo $employee['city']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control" value="<?php echo $employee['state']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Zip Code</label>
                                <input type="text" name="zip_code" class="form-control" value="<?php echo $employee['zip_code']; ?>">
                            </div>
                            
                            <hr>
                            <h6>Emergency Contact</h6>
                            <div class="mb-3">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-control" 
                                    value="<?php echo $employee['emergency_contact_name']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Phone</label>
                                <input type="text" name="emergency_contact_phone" class="form-control" 
                                    value="<?php echo $employee['emergency_contact_phone']; ?>" maxlength="10">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Relation</label>
                                <input type="text" name="emergency_contact_relation" class="form-control" 
                                    value="<?php echo $employee['emergency_contact_relation']; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>