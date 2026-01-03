<?php
/**
 * DAYFLOW HRMS - Database Configuration
 * File: config/db.php
 */

/* ==========================
   DATABASE CONFIG
========================== */
define('DB_HOST', 'localhost');
define('DB_NAME', 'dayflow_hrms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ==========================
   APPLICATION CONFIG
========================== */
define('APP_NAME', 'Dayflow HRMS');
define('APP_URL', 'http://localhost/dayflow_hrms');
define('ADMIN_EMAIL', 'admin@dayflow.com');

/* ==========================
   FILE UPLOAD CONFIG
========================== */
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

/* ==========================
   SESSION CONFIG
========================== */
define('SESSION_TIMEOUT', 7200);

/* ==========================
   TIMEZONE
========================== */
date_default_timezone_set('Asia/Kolkata');

/* ==========================
   ERROR REPORTING
========================== */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ==========================
   DATABASE CLASS
========================== */
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/* ==========================
   HELPERS
========================== */
function getDB() {
    return Database::getInstance()->getConnection();
}

function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . APP_URL . "/" . $url);
    exit();
}

/* ==========================
   AUTH HELPERS
========================== */
function isLoggedIn() {
    return isset($_SESSION['user_id'], $_SESSION['role_id']);
}

function hasRole($roles) {
    return isLoggedIn() && in_array($_SESSION['role_name'], (array)$roles);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentEmployeeId() {
    return $_SESSION['employee_id'] ?? null;
}

/* ==========================
   FLASH MESSAGE
========================== */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = compact('type', 'message');
}

function getFlashMessage() {
    if (!empty($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/* ==========================
   FORMAT HELPERS
========================== */
function formatDate($date, $format = 'd-M-Y') {
    return empty($date) || $date === '0000-00-00' ? 'N/A' : date($format, strtotime($date));
}

/* 🔥 FIXED FUNCTION */
function formatCurrency($amount) {
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $amount = 0;
    }
    return '₹ ' . number_format((float)$amount, 2);
}

/* ==========================
   TIME CALCULATION
========================== */
function calculateWorkingHours($checkIn, $checkOut) {
    if (!$checkIn || !$checkOut) return 0;
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    $diff = $start->diff($end);
    return round($diff->h + ($diff->i / 60), 2);
}

/* ==========================
   EMAIL & TOKEN
========================== */
function sendEmail($to, $subject, $message, $headers = '') {
    if (!$headers) {
        $headers = "From: " . ADMIN_EMAIL . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    }
    return mail($to, $subject, $message, $headers);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/* ==========================
   VALIDATION
========================== */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone($phone) {
    return preg_match('/^[6-9]\d{9}$/', $phone);
}

/* ==========================
   FILE HELPERS
========================== */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function generateUniqueFilename($originalName) {
    return uniqid() . '_' . time() . '.' . getFileExtension($originalName);
}

function isImageFile($mimeType) {
    return in_array($mimeType, ALLOWED_IMAGE_TYPES);
}

/* ==========================
   LOGGING
========================== */
function logActivity($userId, $action, $details = '') {
    try {
        getDB()->prepare(
            "INSERT INTO activity_logs (user_id, action, details, ip_address)
             VALUES (?, ?, ?, ?)"
        )->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {}
}

function getRoleName($roleId) {
    return [1=>'Admin',2=>'HR',3=>'Employee'][$roleId] ?? 'Unknown';
}

/* ==========================
   SESSION TIMEOUT
========================== */
function checkSessionTimeout() {
    if (isset($_SESSION['LAST_ACTIVITY']) &&
        time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return true;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    return false;
}

/* ==========================
   SESSION START
========================== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn() && checkSessionTimeout()) {
    redirect('auth/login.php?timeout=1');
}
?>