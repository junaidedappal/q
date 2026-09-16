<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'mentor'])) {
    header('location: login.php');
    exit;
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['id'];

$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$monthName = date('F', mktime(0, 0, 0, (int)$selectedMonth, 10));

// --- 1. DATA FETCHING LOGIC ---
// We calculate sum of (hours * pay) for sessions marked 'done'
$sql = "SELECT 
            t.id as teacher_id,
            u_t.name as teacher_name,
            u_t.phone as teacher_phone,
            COUNT(sess.id) as session_count,
            SUM(sess.planned_hours) as total_hours,
            SUM(sess.planned_hours * sess.teacher_pay) as total_payout
        FROM sessions sess
        JOIN teachers t ON sess.teacher_id = t.id
        JOIN users u_t ON t.user_id = u_t.id
        JOIN schedules sch ON sess.schedule_id = sch.id
        JOIN mentors m ON sch.mentor_id = m.id
        WHERE sess.status = 'done' 
        AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?";

// If Mentor is logged in, only show teachers who took sessions for THEIR students
if ($userRole === 'mentor') {
    $sql .= " AND m.user_id = " . intval($userId);
}

$sql .= " GROUP BY t.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $selectedMonth, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

$teachersData = [];
$grandTotalPayout = 0;
$totalHoursCount = 0;
$totalSessionsCount = 0;

while ($row = $result->fetch_assoc()) {
    $teachersData[] = $row;
    $grandTotalPayout += $row['total_payout'];
    $totalHoursCount += $row['total_hours'];
    $totalSessionsCount += $row['session_count'];
}

// --- 2. CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    $fileName = "Teacher_Payouts_{$monthName}_{$selectedYear}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
    fputcsv($output, ['Teacher Name', 'Phone', 'Sessions', 'Hours', 'Total Pay (INR)'], ",", "\"", "\\");
    foreach ($teachersData as $d) {
        fputcsv($output, [$d['teacher_name'], $d['teacher_phone'], $d['session_count'], $d['total_hours'], number_format($d['total_payout'], 2, '.', '')], ",", "\"", "\\");
    }
    fclose($output);
    exit;
}
?>

<style>
body {
    background-color: #f0f2f5;
    font-family: 'Inter', sans-serif;
}

.header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
}

.stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

@media (max-width: 768px) {
    .table-stack thead {
        display: none;
    }

    .table-stack tr {
        display: block;
        background: white;
        border-radius: 12px;
        margin-bottom: 1rem;
        padding: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table-stack td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0 !important;
        border: none !important;
    }

    .table-stack td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        font-size: 0.7rem;
    }
}

.text-success-custom {
    color: #10b981;
    font-weight: 800;
    font-family: 'Courier New', monospace;
}
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="header-gradient mb-4">
        <div class="row gy-3 align-items-center">
            <div class="col-12 col-lg-6">
                <h3 class="fw-bold mb-0"><i class="bi bi-person-badge me-2"></i>Teacher Payouts</h3>
                <p class="mb-0 opacity-75 small">Monthly Earnings Report: <?php echo "$monthName $selectedYear"; ?></p>
            </div>
            <div class="col-12 col-lg-6 text-lg-end">
                <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&export=csv"
                        class="btn btn-light btn-sm rounded-pill px-3 fw-bold">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i> Export
                    </a>
                    <form class="d-flex gap-1 bg-white p-1 rounded-pill shadow-sm">
                        <select name="month" class="form-select form-select-sm border-0 bg-transparent">
                            <?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm border-0 bg-transparent">
                            <?php for($y=2024; $y<=date('Y')+1; $y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Go</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-3 me-3 text-primary"><i class="bi bi-cash-stack fs-4"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL TEACHER PAYOUT</div>
                        <div class="h4 fw-bold mb-0">₹<?php echo number_format($grandTotalPayout, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-3 me-3 text-info"><i class="bi bi-clock-history fs-4"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL HOURS</div>
                        <div class="h4 fw-bold mb-0"><?php echo $totalHoursCount; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-3 me-3 text-warning"><i class="bi bi-journal-check fs-4"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL SESSIONS</div>
                        <div class="h4 fw-bold mb-0"><?php echo $totalSessionsCount; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-stack align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Teacher</th>
                        <th class="text-center">Sessions</th>
                        <th class="text-center">Hrs Done</th>
                        <th class="text-end">Total Earnings</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teachersData)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No payout data for this month.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($teachersData as $row): ?>
                    <tr>
                        <td class="ps-4" data-label="Teacher">
                            <div class="fw-bold text-dark"><?php echo $row['teacher_name']; ?></div>
                            <div class="text-muted small"><i class="bi bi-phone"></i>
                                <?php echo $row['teacher_phone']; ?></div>
                        </td>
                        <td class="text-center" data-label="Sessions">
                            <span class="badge bg-light text-dark border"><?php echo $row['session_count']; ?></span>
                        </td>
                        <td class="text-center" data-label="Hours">
                            <span class="fw-bold"><?php echo $row['total_hours']; ?></span>
                        </td>
                        <td class="text-end fw-bold" data-label="Amount">
                            <span
                                class="text-success-custom">₹<?php echo number_format($row['total_payout'], 2); ?></span>
                        </td>
                        <td class="text-center" data-label="Action">
                            <a href="teacher_payout_detail.php?teacher_id=<?php echo $row['teacher_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                                class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>