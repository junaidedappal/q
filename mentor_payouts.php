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

// --- 1. DATA FETCHING LOGIC (Updated to use PHONE instead of email) ---
$sql = "SELECT 
            m.id as mentor_id,
            u.name as mentor_name,
            u.phone as mentor_phone, 
            m.rate_per_session,
            m.rate_per_exam,
            (SELECT COUNT(*) FROM sessions sess 
             JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'done' 
             AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as total_sessions,
            (SELECT COUNT(*) FROM schedule_exams ex 
             JOIN schedules sch ON ex.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id 
             AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?) as total_exams
        FROM mentors m
        JOIN users u ON m.user_id = u.id
        WHERE u.status = 'active'";

if ($userRole === 'mentor') {
    $sql .= " AND u.id = " . intval($userId);
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $selectedMonth, $selectedYear, $selectedMonth, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();

$mentorsData = [];
$grandTotalPayout = 0;
$grandTotalSessions = 0;
$grandTotalExams = 0;

while ($row = $result->fetch_assoc()) {
    $earnings = ($row['total_sessions'] * $row['rate_per_session']) + ($row['total_exams'] * $row['rate_per_exam']);
    if ($earnings <= 0 && $userRole === 'admin') continue;
    
    $row['calculated_earnings'] = $earnings;
    $mentorsData[] = $row;
    
    $grandTotalPayout += $earnings;
    $grandTotalSessions += $row['total_sessions'];
    $grandTotalExams += $row['total_exams'];
}

// --- 2. CSV EXPORT LOGIC (Updated headers to Phone) ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    $fileName = "Mentor_Payouts_{$monthName}_{$selectedYear}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    // Headers updated to Phone
    $headers = ['Mentor Name', 'Phone Number', 'Sessions', 'Exams', 'Earnings (INR)'];
    fputcsv($output, $headers, ",", "\"", "\\");
    
    foreach ($mentorsData as $data) {
        fputcsv($output, [
            $data['mentor_name'],
            $data['mentor_phone'],
            $data['total_sessions'],
            $data['total_exams'],
            number_format($data['calculated_earnings'], 2, '.', '')
        ], ",", "\"", "\\");
    }
    
    if($userRole === 'admin') {
        fputcsv($output, ['TOTAL', '', $grandTotalSessions, $grandTotalExams, number_format($grandTotalPayout, 2, '.', '')], ",", "\"", "\\");
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

/* Responsive Header Area */
.page-header-gradient {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Table Stacking for Mobile */
@media (max-width: 768px) {
    .table-container {
        background: transparent !important;
        box-shadow: none !important;
    }

    .table-responsive {
        border: none;
    }

    .table thead {
        display: none;
    }

    /* Hide headers on mobile */
    .table tbody tr {
        display: block;
        background: white;
        border-radius: 12px;
        margin-bottom: 1rem;
        padding: 1rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0 !important;
        border: none !important;
        text-align: right;
    }

    .table tbody td::before {
        content: attr(data-label);
        font-weight: 700;
        color: #6e707e;
        text-transform: uppercase;
        font-size: 0.7rem;
        text-align: left;
    }

    .avatar-sm {
        width: 32px !important;
        height: 32px !important;
    }
}

.badge-soft-primary {
    background: #eef2ff;
    color: #4e73df;
    font-weight: 600;
}

.badge-soft-info {
    background: #e0f2fe;
    color: #0284c7;
    font-weight: 600;
}

.earnings-text {
    color: #1cc88a;
    font-weight: 800;
    font-family: 'Roboto Mono', monospace;
}

.stat-card {
    border: none;
    border-radius: 12px;
}
</style>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="page-header-gradient">
        <div class="row gy-3 align-items-center">
            <div class="col-12 col-lg-6 text-center text-lg-start">
                <h3 class="fw-bold mb-0"><i class="bi bi-wallet2 me-2"></i>Mentor Payouts</h3>
                <p class="mb-0 opacity-75 small"><?php echo "$monthName $selectedYear"; ?></p>
            </div>
            <div class="col-12 col-lg-6">
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-end gap-2">
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&export=csv"
                        class="btn btn-light btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i> Export
                    </a>
                    <form class="d-flex gap-1 bg-white p-1 rounded-pill shadow-sm">
                        <select name="month" class="form-select form-select-sm border-0 bg-transparent py-0"
                            style="width: auto;">
                            <?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm border-0 bg-transparent py-0"
                            style="width: auto;">
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
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-soft-primary p-3 rounded-3 me-3"><i class="bi bi-cash-stack fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">TOTAL PAYOUT</div>
                        <div class="h4 fw-bold mb-0 text-dark">₹<?php echo number_format($grandTotalPayout, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-soft-info p-3 rounded-3 me-3"><i class="bi bi-mortarboard fs-4 text-info"></i></div>
                    <div>
                        <div class="text-muted small fw-bold">SESSIONS</div>
                        <div class="h4 fw-bold mb-0 text-dark"><?php echo $grandTotalSessions; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="card stat-card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-light p-3 rounded-3 me-3"><i class="bi bi-journal-text fs-4 text-secondary"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-bold">EXAMS</div>
                        <div class="h4 fw-bold mb-0 text-dark"><?php echo $grandTotalExams; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container bg-white shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Mentor</th>
                        <th class="text-center">Workload</th>
                        <th class="text-center">Exams</th>
                        <th class="text-end">Earnings</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mentorsData)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No records found.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($mentorsData as $row): ?>
                    <tr>
                        <td class="ps-4" data-label="Mentor">
                            <div class="d-flex align-items-center text-start">

                                <div class="text-truncate" style="max-width: 150px;">
                                    <div class="fw-bold text-dark small mb-0"><?php echo $row['mentor_name']; ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">
                                        <?php echo $row['mentor_phone']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center" data-label="Sessions">
                            <span
                                class="badge badge-soft-primary px-2 py-1"><?php echo $row['total_sessions']; ?></span>
                        </td>
                        <td class="text-center" data-label="Exams">
                            <span class="badge badge-soft-info px-2 py-1"><?php echo $row['total_exams']; ?></span>
                        </td>
                        <td class="text-end fw-bold" data-label="Amount">
                            <span
                                class="earnings-text">₹<?php echo number_format($row['calculated_earnings'], 2); ?></span>
                        </td>
                        <td class="text-center" data-label="Action">
                            <a href="mentor_payout_detail.php?mentor_id=<?php echo $row['mentor_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                                class="btn btn-sm btn-outline-primary rounded-circle shadow-sm"><i
                                    class="bi bi-chevron-right"></i></a>
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