<?php
/**
 * DAYFLOW HRMS - User Login
 * File: auth/login.php
 */

require_once '../config/db.php';

$error = '';

// Check if already logged in
if (isLoggedIn()) {
    if (hasRole(['Admin', 'HR'])) {
        redirect('admin/dashboard.php');
    } else {
        redirect('employee/dashboard.php');
    }
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please enter email and password";
    } else {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT u.*, r.role_name, e.id as emp_id, e.first_name, e.last_name 
                FROM users u
                INNER JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN employees e ON u.user_id = e.user_id
                WHERE u.email = ?
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {

                // Only active check remains
                if (!$user['is_active']) {
                    $error = "Your account has been deactivated. Please contact HR.";
                } else {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['employee_id'] = $user['emp_id'];
                    $_SESSION['employee_code'] = $user['employee_id'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['LAST_ACTIVITY'] = time();

                    // Update last login
                    $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                       ->execute([$user['user_id']]);

                    // Log successful login
                    $db->prepare("
                        INSERT INTO login_logs (user_id, email, ip_address, user_agent, login_status)
                        VALUES (?, ?, ?, ?, 'Success')
                    ")->execute([
                        $user['user_id'],
                        $email,
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);

                    if (in_array($user['role_name'], ['Admin', 'HR'])) {
                        redirect('admin/dashboard.php');
                    } else {
                        redirect('employee/dashboard.php');
                    }
                }
            } else {
                $error = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
        }
    }
}

$timeoutMsg = isset($_GET['timeout']) ? "Your session has expired. Please login again." : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
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
        .login-container {
            max-width: 450px;
            margin: auto;
        }
        .login-card {
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
            margin-bottom: 5px;
        }
        .logo p {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .forgot-password:hover {
            text-decoration: underline;
        }
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .divider::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #ddd;
        }
        .divider::after {
            content: "";
            position: absolute;
            right: 0;
            top: 50%;
            width: 45%;
            height: 1px;
            background: #ddd;
        }
        .demo-credentials {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="logo">
                    <h2><i class="fas fa-users-cog"></i> <?php echo APP_NAME; ?></h2>
                    <p>Welcome Back! Please Login</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($timeoutMsg): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-clock"></i> <?php echo $timeoutMsg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['verified'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> Email verified successfully! You can now login.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="Enter your email" 
                                   value="<?php echo $_POST['email'] ?? ''; ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required>
                        </div>
                    </div>

                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <a href="forgot_password.php" class="forgot-password">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>

                </form>

                <!-- Demo Credentials
                <div class="demo-credentials">
                    <strong><i class="fas fa-info-circle"></i> Demo Credentials:</strong>
                    <div class="mt-2">
                        <strong>Admin:</strong><br>
                        Email: admin@dayflow.com<br>
                        Password: Admin@123
                    </div>
                </div> -->
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 text-white">
                <p class="mb-0">&copy; 2026 <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>