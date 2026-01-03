<?php
/**
 * DAYFLOW HRMS - Employee Management (Admin)
 * File: admin/employees.php
 */

require_once '../config/db.php';

if (!isLoggedIn() || !hasRole(['Admin', 'HR'])) {
    redirect('auth/login.php');
}

$db = getDB();

// Handle employee actions (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = (int)$_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'toggle_status') {
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlashMessage('success', 'Employee status updated successfully!');
        }
        redirect('admin/employees.php');
    } catch(PDOException $e) {
        setFlashMessage('error', 'Failed to update employee status.');
        error_log($e->getMessage());
    }
}

// Get all employees
try {
    $search = $_GET['search'] ?? '';
    $department = $_GET['department'] ?? '';
    
    $query = "
        SELECT 
            e.*, 
            u.email, 
            u.is_active, 
            u.last_login,
            r.role_name,
            s.net_salary
        FROM employees e
        INNER JOIN users u ON e.user_id = u.user_id
        INNER JOIN roles r ON u.role_id = r.role_id
        LEFT JOIN salary_structure s ON e.id = s.employee_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (e.employee_id LIKE ? OR e.full_name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($department) {
        $query .= " AND e.department = ?";
        $params[] = $department;
    }
    
    $query .= " ORDER BY e.id DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    // Get departments for filter
    $deptStmt = $db->query("SELECT DISTINCT department FROM employees ORDER BY department");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    error_log($e->getMessage());
    $employees = [];
    $departments = [];
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - <?php echo APP_NAME; ?></title>
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
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .table-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .action-btn {
            padding: 5px 10px;
            font-size: 0.85rem;
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
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
            <a class="nav-link active" href="employees.php">
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
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-users me-2"></i> Employee Management</h3>
            <a href="add_employee.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Employee
            </a>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by ID, name, or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="department" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Search
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="employees.php" class="btn btn-secondary w-100">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employees Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Contact</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No employees found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($employees as $emp): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="table-avatar me-3">
                                                <?php echo strtoupper(substr($emp['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo $emp['employee_id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($emp['email']); ?>
                                            <br>
                                            <i class="fas fa-phone me-1"></i> <?php echo $emp['phone'] ?? 'N/A'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo $emp['department'] ?? 'N/A'; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $emp['designation'] ?? 'N/A'; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $emp['role_name']; ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo formatCurrency($emp['net_salary'] ?? 0); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($emp['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view_employee.php?id=<?php echo $emp['id']; ?>" 
                                               class="btn btn-sm btn-info action-btn" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" 
                                               class="btn btn-sm btn-primary action-btn" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to change this employee\'s status?')">
                                                <input type="hidden" name="user_id" value="<?php echo $emp['user_id']; ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-<?php echo $emp['is_active'] ? 'warning' : 'success'; ?> action-btn" 
                                                        title="<?php echo $emp['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
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