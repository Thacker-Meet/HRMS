<?php
/**
 * DAYFLOW HRMS - User Registration
 * File: auth/register.php
 */

require_once '../config/db.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = sanitize($_POST['employee_id']);
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $department = sanitize($_POST['department']);
    $designation = sanitize($_POST['designation']);

    // Validation
    if (!preg_match('/^EMP[0-9]{3,}$/', $employeeId)) {
        $errors[] = "Employee ID must be in format EMP001";
    }

    if (!$firstName || !$lastName) {
        $errors[] = "First name and last name are required";
    }

    if (!isValidEmail($email)) {
        $errors[] = "Valid email address is required";
    }

    if (!isValidPhone($phone)) {
        $errors[] = "Valid phone number is required";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            // Check duplicate
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? OR employee_id = ?");
            $stmt->execute([$email, $employeeId]);

            if ($stmt->fetch()) {
                $errors[] = "Email or Employee ID already exists";
            }

            if (empty($errors)) {
                $db->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // User inserted as VERIFIED
                $stmt = $db->prepare("
                    INSERT INTO users (employee_id, email, password_hash, role_id, is_verified)
                    VALUES (?, ?, ?, 3, 1)
                ");
                $stmt->execute([$employeeId, $email, $passwordHash]);
                $userId = $db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO employees (
                        user_id, employee_id, first_name, last_name,
                        phone, department, designation, date_of_joining
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
                ");
                $stmt->execute([
                    $userId, $employeeId, $firstName, $lastName,
                    $phone, $department, $designation
                ]);

                $db->commit();

                $success = "Registration successful! You can now login.";
                $_POST = [];
            }

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h2 {
            color: #667eea;
            font-weight: bold;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: bold;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .password-strength {
            font-size: 0.85rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="register-card">
                <div class="logo">
                    <h2><i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?></h2>
                    <p class="text-muted">Create Your Account</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error!</strong>
                        <ul class="mb-0">
                            <?php foreach($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong>Success!</strong> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" name="employee_id" class="form-control" 
                                   placeholder="EMP001" value="<?php echo $_POST['employee_id'] ?? ''; ?>" required>
                            <small class="text-muted">Format: EMP001, EMP002, etc.</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo $_POST['first_name'] ?? ''; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo $_POST['last_name'] ?? ''; ?>" required>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo $_POST['email'] ?? ''; ?>" required>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="9876543210" value="<?php echo $_POST['phone'] ?? ''; ?>" 
                                   maxlength="10" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department *</label>
                            <select name="department" class="form-control" required>
                                <option value="">Select Department</option>
                                <option value="IT">IT</option>
                                <option value="HR">Human Resources</option>
                                <option value="Finance">Finance</option>
                                <option value="Sales">Sales</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operations">Operations</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Designation *</label>
                            <input type="text" name="designation" class="form-control" 
                                   value="<?php echo $_POST['designation'] ?? ''; ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" 
                                   id="password" required>
                            <small class="text-muted">Min 8 characters, uppercase, lowercase & number</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-register w-100 mb-3">
                        <i class="fas fa-user-plus"></i> Register
                    </button>

                    <div class="text-center">
                        <p class="mb-0">Already have an account? 
                            <a href="login.php" class="text-decoration-none">Login here</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>