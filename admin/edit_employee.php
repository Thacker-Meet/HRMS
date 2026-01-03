<?php
/**
 * DAYFLOW HRMS - Edit Employee
 * File: admin/edit_employee.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();
$employeeId = (int)($_GET['id'] ?? 0);
$errors = [];

// Get employee details
try {
    $stmt = $db->prepare("
        SELECT e.*, u.email, u.role_id, s.*
        FROM employees e
        INNER JOIN users u ON e.user_id = u.user_id
        LEFT JOIN salary_structure s ON e.id = s.employee_id
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        setFlashMessage('error', 'Employee not found');
        redirect('admin/employees.php');
    }
} catch(PDOException $e) {
    error_log($e->getMessage());
    redirect('admin/employees.php');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $dateOfBirth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $state = sanitize($_POST['state']);
    $zipCode = sanitize($_POST['zip_code']);
    $department = sanitize($_POST['department']);
    $designation = sanitize($_POST['designation']);
    $employmentType = $_POST['employment_type'];
    $roleId = (int)$_POST['role_id'];
    
    // Salary details
    $basicSalary = (float)$_POST['basic_salary'];
    $hra = (float)($_POST['hra'] ?? 0);
    $conveyanceAllowance = (float)($_POST['conveyance_allowance'] ?? 0);
    $medicalAllowance = (float)($_POST['medical_allowance'] ?? 0);
    $specialAllowance = (float)($_POST['special_allowance'] ?? 0);
    $pfDeduction = (float)($_POST['pf_deduction'] ?? 0);
    $taxDeduction = (float)($_POST['tax_deduction'] ?? 0);
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user role
            $stmt = $db->prepare("UPDATE users SET role_id = ? WHERE user_id = ?");
            $stmt->execute([$roleId, $employee['user_id']]);
            
            // Update employee
            $stmt = $db->prepare("
                UPDATE employees SET
                    first_name = ?, last_name = ?, date_of_birth = ?,
                    gender = ?, phone = ?, address = ?, city = ?, 
                    state = ?, zip_code = ?, department = ?,
                    designation = ?, employment_type = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $firstName, $lastName, $dateOfBirth, $gender, $phone,
                $address, $city, $state, $zipCode, $department,
                $designation, $employmentType, $employeeId
            ]);
            
            // Update salary structure
            $stmt = $db->prepare("
                UPDATE salary_structure SET
                    basic_salary = ?, hra = ?, conveyance_allowance = ?,
                    medical_allowance = ?, special_allowance = ?,
                    pf_deduction = ?, tax_deduction = ?
                WHERE employee_id = ?
            ");
            $stmt->execute([
                $basicSalary, $hra, $conveyanceAllowance,
                $medicalAllowance, $specialAllowance,
                $pfDeduction, $taxDeduction, $employeeId
            ]);
            
            $db->commit();
            setFlashMessage('success', 'Employee updated successfully!');
            redirect('admin/employees.php');
            
        } catch(PDOException $e) {
            $db->rollBack();
            $errors[] = "Failed to update employee.";
            error_log($e->getMessage());
        }
    }
}

$roles = $db->query("SELECT * FROM roles ORDER BY role_id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - <?php echo APP_NAME; ?></title>
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
        .section-title { background: #f8f9fa; padding: 15px; font-weight: bold; margin: -20px -20px 20px; border-radius: 10px 10px 0 0; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?></div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="employees.php"><i class="fas fa-users me-2"></i> Employees</a>
            <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link" href="leave_requests.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-user-edit me-2"></i> Edit Employee</h3>
            <a href="employees.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0"><?php foreach($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="section-title"><i class="fas fa-user me-2"></i> Personal Information</div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Employee ID</label>
                            <input type="text" class="form-control" value="<?php echo $employee['employee_id']; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" value="<?php echo $employee['first_name']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" value="<?php echo $employee['last_name']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo $employee['date_of_birth']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="Male" <?php echo $employee['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $employee['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $employee['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $employee['phone']; ?>" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo $employee['address']; ?></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo $employee['city']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?php echo $employee['state']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Zip Code</label>
                            <input type="text" name="zip_code" class="form-control" value="<?php echo $employee['zip_code']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="section-title"><i class="fas fa-briefcase me-2"></i> Job Information</div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Department *</label>
                            <select name="department" class="form-control" required>
                                <option value="IT" <?php echo $employee['department'] == 'IT' ? 'selected' : ''; ?>>IT</option>
                                <option value="HR" <?php echo $employee['department'] == 'HR' ? 'selected' : ''; ?>>HR</option>
                                <option value="Finance" <?php echo $employee['department'] == 'Finance' ? 'selected' : ''; ?>>Finance</option>
                                <option value="Sales" <?php echo $employee['department'] == 'Sales' ? 'selected' : ''; ?>>Sales</option>
                                <option value="Marketing" <?php echo $employee['department'] == 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                <option value="Operations" <?php echo $employee['department'] == 'Operations' ? 'selected' : ''; ?>>Operations</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Designation *</label>
                            <input type="text" name="designation" class="form-control" value="<?php echo $employee['designation']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Employment Type</label>
                            <select name="employment_type" class="form-control">
                                <option value="Full-Time" <?php echo $employee['employment_type'] == 'Full-Time' ? 'selected' : ''; ?>>Full-Time</option>
                                <option value="Part-Time" <?php echo $employee['employment_type'] == 'Part-Time' ? 'selected' : ''; ?>>Part-Time</option>
                                <option value="Contract" <?php echo $employee['employment_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="Intern" <?php echo $employee['employment_type'] == 'Intern' ? 'selected' : ''; ?>>Intern</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role_id" class="form-control" required>
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>" 
                                        <?php echo $employee['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                        <?php echo $role['role_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo $employee['email']; ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="section-title"><i class="fas fa-money-bill-wave me-2"></i> Salary Structure</div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" name="basic_salary" class="form-control" step="0.01" 
                                value="<?php echo $employee['basic_salary']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">HRA</label>
                            <input type="number" name="hra" class="form-control" step="0.01" 
                                value="<?php echo $employee['hra']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Conveyance</label>
                            <input type="number" name="conveyance_allowance" class="form-control" step="0.01" 
                                value="<?php echo $employee['conveyance_allowance']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Medical</label>
                            <input type="number" name="medical_allowance" class="form-control" step="0.01" 
                                value="<?php echo $employee['medical_allowance']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Special Allowance</label>
                            <input type="number" name="special_allowance" class="form-control" step="0.01" 
                                value="<?php echo $employee['special_allowance']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">PF Deduction</label>
                            <input type="number" name="pf_deduction" class="form-control" step="0.01" 
                                value="<?php echo $employee['pf_deduction']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Tax Deduction</label>
                            <input type="number" name="tax_deduction" class="form-control" step="0.01" 
                                value="<?php echo $employee['tax_deduction']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i> Update Employee
                </button>
                <a href="employees.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>