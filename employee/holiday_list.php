<?php
require_once '../config/db.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (hasRole(['Admin', 'HR'])) {
    redirect('admin/attendance.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Holiday List - <?php echo APP_NAME; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    background:#f4f6f9;
    font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.main-content {
    padding: 30px;
}
.breadcrumb-link {
    color:#0d6efd;
    text-decoration:none;
    font-weight:600;
}
.breadcrumb-link:hover {
    text-decoration:underline;
}
.holiday-date {
    color:#6c757d;
}
</style>
</head>

<body>
<div class="main-content">

    <!-- Back link -->
    <div class="mb-3">
        <a href="attendance.php" class="breadcrumb-link">
            Attendance &amp; leave – Overview
        </a>
    </div>

    <h2 class="mb-4">Holiday list</h2>

    <!-- JANUARY -->
    <h5 class="mb-3">January 2026</h5>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">1, Thursday</div>
        <div class="col-md-6">New Year Day</div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">14, Wednesday</div>
        <div class="col-md-6">Makar Sankranti / Pongal / Bhogi</div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">
            20, Tuesday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Guru Gobind Singh Jayanti</div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 holiday-date">26, Monday</div>
        <div class="col-md-6">Republic Day</div>
    </div>

    <hr>

    <!-- FEBRUARY -->
    <h5 class="mt-4 mb-3">February 2026</h5>

    <div class="row mb-4">
        <div class="col-md-6 holiday-date">
            15, Sunday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Maha Shivratri</div>
    </div>

    <hr>

    <!-- MARCH -->
    <h5 class="mt-4 mb-3">March 2026</h5>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">4, Wednesday</div>
        <div class="col-md-6">Dhuleti (Holi 2nd day) / Duliwandan</div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">
            19, Thursday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Gudi Padwa / Telugu New Year’s Day</div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">
            21, Saturday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Id Ul Fitr (Ramzan Id)</div>
    </div>

    <div class="row mb-2">
        <div class="col-md-6 holiday-date">
            26, Thursday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Ram Navami</div>
    </div>

    <div class="row">
        <div class="col-md-6 holiday-date">
            31, Tuesday <span class="text-secondary">(Optional Holiday)</span>
        </div>
        <div class="col-md-6">Mahavir Jayanti</div>
    </div>

</div>
</body>
</html>
