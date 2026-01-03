<?php
/**
 * DAYFLOW HRMS - Email Verification
 * File: auth/verify_email.php
 */

require_once '../config/db.php';

$message = '';
$success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    try {
        $db = getDB();
        
        // Find user with this token
        $stmt = $db->prepare("
            SELECT user_id, email, is_verified 
            FROM users 
            WHERE verification_token = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['is_verified']) {
                $message = "Email already verified. You can login now.";
                $success = true;
            } else {
                // Verify the email
                $stmt = $db->prepare("
                    UPDATE users 
                    SET is_verified = 1, verification_token = NULL 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user['user_id']]);
                
                $message = "Email verified successfully! You can now login.";
                $success = true;
            }
        } else {
            $message = "Invalid or expired verification link.";
        }
        
    } catch(PDOException $e) {
        $message = "Verification failed. Please try again.";
        error_log($e->getMessage());
    }
} else {
    $message = "Invalid verification link.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .verify-container {
            max-width: 500px;
            margin: auto;
            text-align: center;
        }
        .verify-card {
            background: white;
            border-radius: 15px;
            padding: 50px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .icon-box {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        .success-icon {
            background: #d4edda;
            color: #28a745;
        }
        .error-icon {
            background: #f8d7da;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verify-container">
            <div class="verify-card">
                <div class="icon-box <?php echo $success ? 'success-icon' : 'error-icon'; ?>">
                    <i class="fas fa-<?php echo $success ? 'check-circle' : 'times-circle'; ?>"></i>
                </div>
                
                <h3 class="mb-3">Email Verification</h3>
                
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                    <?php echo $message; ?>
                </div>
                
                <?php if ($success): ?>
                    <a href="login.php?verified=1" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Proceed to Login
                    </a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Register Again
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

