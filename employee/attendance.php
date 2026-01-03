<?php

/**
 * DAYFLOW HRMS - Employee Attendance View
 * File: employee/attendance.php
 */
/* ===============================
   HOLIDAY LIST (STATIC FOR NOW)
   =============================== */

$holidays = [
    // January 2026
    '2026-01-01' => 'New Year Day',
    '2026-01-14' => 'Makar Sankranti / Pongal / Bhogi',
    '2026-01-20' => 'Guru Gobind Singh Jayanti',
    '2026-01-26' => 'Republic Day',

    // February 2026
    '2026-02-15' => 'Maha Shivratri',

    // March 2026
    '2026-03-04' => 'Dhuleti (Holi 2nd day)',
    '2026-03-19' => 'Gudi Padwa / Telugu New Year’s Day',
    '2026-03-21' => 'Id Ul Fitr (Ramzan Id)',
    '2026-03-26' => 'Ram Navami',
    '2026-03-31' => 'Mahavir Jayanti',
];

/* ===============================
   TEMP ABSENT DATES (DEMO)
   =============================== */

$manualAbsentDates = [
    '2026-01-02' => true
];


require_once '../config/db.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

if (hasRole(['Admin', 'HR'])) {
    redirect('admin/attendance.php');
}

$db = getDB();
$employeeId = getCurrentEmployeeId();

// Get month/year filter
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get attendance records
try {
    $stmt = $db->prepare("
        SELECT * FROM attendance
        WHERE employee_id = ?
        AND MONTH(attendance_date) = ?
        AND YEAR(attendance_date) = ?
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$employeeId, $month, $year]);
    $attendanceRecords = $stmt->fetchAll();

    // Get summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Leave' THEN 1 END) as leaves,
            COUNT(CASE WHEN status = 'Half-Day' THEN 1 END) as half_days,
            SUM(working_hours) as total_hours
        FROM attendance
        WHERE employee_id = ?
        AND MONTH(attendance_date) = ?
        AND YEAR(attendance_date) = ?
    ");
    $stmt->execute([$employeeId, $month, $year]);
    $summary = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $attendanceRecords = [];
    $summary = ['present' => 0, 'absent' => 0, 'leaves' => 0, 'half_days' => 0, 'total_hours' => 0];
}
/* ===============================
   CALENDAR VARIABLES (FIX)
   =============================== */

// Attendance map
$attendanceMap = [];
foreach ($attendanceRecords as $rec) {
    $attendanceMap[date('Y-m-d', strtotime($rec['attendance_date']))] = $rec['status'];
}

// Calendar calculations
$firstDayOfMonth = strtotime("$year-$month-01");
$totalDays = date('t', $firstDayOfMonth);
$startDay = date('w', $firstDayOfMonth); // Sunday = 0

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - <?php echo APP_NAME; ?></title>
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
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .stat-card h3 {
            margin: 10px 0;
        }

        .stat-card.present {
            border-left: 4px solid #28a745;
        }

        .stat-card.absent {
            border-left: 4px solid #dc3545;
        }

        .stat-card.leave {
            border-left: 4px solid #17a2b8;
        }

        .stat-card.hours {
            border-left: 4px solid #ffc107;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* ===============================
   INTERACTIVE ATTENDANCE CALENDAR
   =============================== */

        .calendar-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px;
            margin-bottom: 35px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .calendar-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .calendar-title {
            font-size: 1.35rem;
            font-weight: 600;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 14px;
        }

        .calendar-weekday {
            text-align: center;
            font-weight: 600;
            color: #6c757d;
        }

        .calendar-day {
            border: 2px solid #dee2e6;
            border-radius: 14px;
            height: 100px;
            padding: 12px;
            background: #f9fafb;
            position: relative;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .calendar-day:hover {
            background: #343a40;
            color: #ffffff;
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }

        .calendar-day:hover .calendar-date {
            color: #ffffff;
        }

        .calendar-date {
            font-size: 1.6rem;
            font-weight: 700;
            color: #212529;
        }

        .calendar-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            position: absolute;
            bottom: 10px;
            right: 10px;
        }

        /* Dot Colors */
        .dot-present {
            background: #28a745;
        }

        .dot-absent {
            background: #dc3545;
        }

        .dot-leave {
            background: #0dcaf0;
        }

        .dot-halfday {
            background: #fd7e14;
        }

        .dot-holiday {
            background: #f1c40f;
        }

        .dot-weekoff {
            background: #000000;
        }


        /* Legends */
        .calendar-legend {
            display: flex;
            gap: 18px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #495057;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .holiday-link {
            font-weight: 600;
            color: #0d6efd;
            text-decoration: none;
        }

        .holiday-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo"><i class="fas fa-user-tie"></i> Employee Portal</div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a>
            <a class="nav-link active" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> Attendance</a>
            <a class="nav-link" href="leave.php"><i class="fas fa-calendar-times me-2"></i> Leave Requests</a>
            <a class="nav-link" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i> Payroll</a>
            <a class="nav-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-calendar-check me-2"></i> My Attendance</h3>
            <form method="GET" class="d-flex gap-2">
                <select name="month" class="form-select" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>"
                            <?php echo $month == sprintf('%02d', $m) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y'); $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>


        <!-- Summary Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card present">
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                    <h3 class="text-success"><?php echo $summary['present'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Days Present</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card absent">
                    <i class="fas fa-times-circle fa-2x text-danger"></i>
                    <h3 class="text-danger"><?php echo $summary['absent'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Days Absent</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card leave">
                    <i class="fas fa-umbrella-beach fa-2x text-info"></i>
                    <h3 class="text-info"><?php echo $summary['leaves'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Days on Leave</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card hours">
                    <i class="fas fa-clock fa-2x text-warning"></i>
                    <h3 class="text-warning"><?php echo number_format($summary['total_hours'] ?? 0, 1); ?></h3>
                    <p class="text-muted mb-0">Total Hours</p>
                </div>
            </div>
        </div>

        <!-- INTERACTIVE ATTENDANCE CALENDAR -->
        <div class="calendar-card" id="attendance-calendar">

            <!-- Header -->
            <div class="calendar-header-bar">
                <div class="calendar-title">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Attendance Calendar – <?php echo date('F Y', $firstDayOfMonth); ?>
                </div>

                <a href="holiday_list.php" class="holiday-link">
                    Holiday list
                </a>
            </div>



            <!-- Weekdays -->
            <div class="calendar-grid mb-2">
                <?php
                foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day) {
                    echo "<div class='calendar-weekday'>$day</div>";
                }

                // Empty blocks
                for ($i = 0; $i < $startDay; $i++) {
                    echo "<div></div>";
                }

                // Dates
                for ($d = 1; $d <= $totalDays; $d++) {
                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $status = $attendanceMap[$currentDate] ?? null;
                    $isHoliday = array_key_exists($currentDate, $holidays);
                    $isSunday  = date('w', strtotime($currentDate)) == 0;
                    $isManualAbsent = array_key_exists($currentDate, $manualAbsentDates);

                    // Priority: Holiday > Manual Absent > Attendance > Sunday
                    if ($isHoliday) {
                        $dotClass = 'dot-holiday';
                    } elseif ($isManualAbsent) {
                        $dotClass = 'dot-absent';
                    } elseif ($status) {
                        $dotClass = match ($status) {
                            'Present'  => 'dot-present',
                            'Absent'   => 'dot-absent',
                            'Leave'    => 'dot-leave',
                            'Half-Day' => 'dot-halfday',
                            default    => ''
                        };
                    } elseif ($isSunday) {
                        $dotClass = 'dot-weekoff';
                    } else {
                        $dotClass = '';
                    }




                    echo "<div class='calendar-day'>
                    <div class='calendar-date'>$d</div>";

                    if ($dotClass) {
                        echo "<div class='calendar-dot $dotClass'></div>";
                    }

                    echo "</div>";
                }
                ?>
            </div>

            <!-- Legends -->
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-dot dot-present"></span> Present
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-absent"></span> Absent
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-leave"></span> Leave
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-halfday"></span> Half Day
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-holiday"></span> Holiday
                </div>
                <div class="legend-item">
                    <span class="legend-dot dot-weekoff"></span> Weekly Off (Sunday)
                </div>

            </div>

        </div>

        <!-- HOLIDAY LIST -->




        <!-- BIG ATTENDANCE CALENDAR -->



        <!-- Attendance Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Attendance Records -
                    <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Working Hours</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceRecords)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No attendance records found for this month</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td><?php echo date('d-M-Y', strtotime($record['attendance_date'])); ?></td>
                                        <td><?php echo date('l', strtotime($record['attendance_date'])); ?></td>
                                        <td>
                                            <?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $record['working_hours'] ? number_format($record['working_hours'], 2) . ' hrs' : '-'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'Present' => 'success',
                                                'Absent' => 'danger',
                                                'Leave' => 'info',
                                                'Half-Day' => 'warning',
                                                'Holiday' => 'secondary'
                                            ];
                                            $class = $statusClass[$record['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $class; ?> status-badge">
                                                <?php echo $record['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $record['remarks'] ?? '-'; ?></td>
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