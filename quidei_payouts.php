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

// --- UPDATED QUIDEI PAYOUT LOGIC ---
$sql = "SELECT 
            m.id as mentor_id,
            u.name as mentor_name,
            u.phone as mentor_phone,
            m.rate_per_session,
            m.rate_per_exam,
            -- 1. Count Total Regular Sessions
            (SELECT COUNT(*) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as total_sessions,
            -- 2. Count Total Exams
            (SELECT COUNT(*) FROM schedule_exams ex JOIN schedules sch ON ex.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?) as total_exams,
            -- 3. Calculate Regular Teacher Payout (Uses pay saved in session)
            (SELECT SUM(sess.planned_hours * sess.teacher_pay) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as total_session_teacher_payout,
            -- 4. NEW LOGIC: Calculate Independent Demo Teacher Payout (Duration * Grade Rate)
            (SELECT SUM(md.duration_hours * g.payment_per_hour) 
             FROM mentor_demos md 
             JOIN students s ON md.student_id = s.id
             JOIN grades g ON s.class = g.grade_name
             WHERE s.mentor_id = m.id AND MONTH(md.demo_date) = ? AND YEAR(md.demo_date) = ?) as total_demo_teacher_payout
        FROM mentors m
        JOIN users u ON m.user_id = u.id
        WHERE u.status = 'active'";

if ($userRole === 'mentor') {
    $sql .= " AND u.id = " . intval($userId);
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiiiiiii', 
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear
);
$stmt->execute();
$result = $stmt->get_result();

$payouts = [];
$companyTotal = 0;

while ($row = $result->fetch_assoc()) {
    // Mentor's earnings from sessions and exams
    $mentorOwnEarnings = ($row['total_sessions'] * $row['rate_per_session']) + ($row['total_exams'] * $row['rate_per_exam']);
    
    // Combined Teacher Payout: (Regular Classes) + (Independent Demos at Grade Rate)
    $combinedTeacherPayout = floatval($row['total_session_teacher_payout'] ?? 0) + floatval($row['total_demo_teacher_payout'] ?? 0);
    
    $row['mentor_earnings'] = $mentorOwnEarnings;
    $row['total_teacher_payout'] = $combinedTeacherPayout;
    $row['grand_lump_sum'] = $mentorOwnEarnings + $combinedTeacherPayout;
    
    if ($row['grand_lump_sum'] > 0 || $userRole === 'mentor') {
        $payouts[] = $row;
        $companyTotal += $row['grand_lump_sum'];
    }
}

// --- CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    if (ob_get_length()) ob_end_clean();

    $fileName = "Quidei_Summary_{$monthName}_{$selectedYear}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

    // FIXED: Added ",", "\"", "\\" parameters to all fputcsv calls
    fputcsv($output, ['Mentor Name', 'Phone', 'Sessions', 'Exams', 'Teachers Payout (Inc. Demos)', 'Mentor Fee', 'Grand Total'], ",", "\"", "\\");

    foreach ($payouts as $p) {
        fputcsv($output, [
            $p['mentor_name'], 
            $p['mentor_phone'], 
            $p['total_sessions'], 
            $p['total_exams'],
            number_format($p['total_teacher_payout'], 2, '.', ''),
            number_format($p['mentor_earnings'], 2, '.', ''),
            number_format($p['grand_lump_sum'], 2, '.', '')
        ], ",", "\"", "\\");
    }

    if ($userRole === 'admin') {
        fputcsv($output, [], ",", "\"", "\\"); // Empty line
        fputcsv($output, ['TOTAL COMPANY OUTFLOW', '', '', '', '', '', number_format($companyTotal, 2, '.', '')], ",", "\"", "\\");
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

.quidei-header {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
}

.card-payout {
    border: none;
    border-radius: 15px;
    transition: 0.2s;
}

.card-payout:hover {
    transform: translateY(-3px);
}

.text-lump-sum {
    color: #10b981;
    font-weight: 800;
}

.btn-export {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="quidei-header mb-4 shadow">
        <div class="row gy-3 align-items-center">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>QUIDIE PAYOUTS</h2>
                <p class="mb-0 opacity-75 small">Consolidated Mentor & Teacher Distribution (Including Independent
                    Demos)</p>
            </div>
            <div class="col-lg-6 text-lg-end">
                <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&export=csv"
                    class="btn btn-sm btn-export rounded-pill px-3">
                    <i class="bi bi-file-earmark-excel"></i> Export Excel
                </a>
                <form class="d-inline-flex gap-2 bg-white p-1 rounded-pill ms-2 shadow-sm">
                    <select name="month"
                        class="form-select form-select-sm border-0 bg-transparent"><?php for($m=1;$m<=12;$m++) echo "<option value='".str_pad($m,2,'0',STR_PAD_LEFT)."' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?></select>
                    <select name="year"
                        class="form-select form-select-sm border-0 bg-transparent"><?php for($y=2024;$y<=date('Y');$y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?></select>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Go</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <?php if($userRole === 'admin'): ?>
    <div class="alert bg-white border-0 shadow-sm rounded-3 d-flex justify-content-between align-items-center p-3 mb-4">
        <span class="fw-bold text-muted small text-uppercase">Total Monthly Company Outflow</span>
        <span class="h4 fw-bold text-primary mb-0">₹<?php echo number_format($companyTotal, 2); ?></span>
    </div>
    <?php endif; ?>

    <!-- Mentor Cards -->
    <div class="row g-3">
        <?php if(empty($payouts)): ?>
        <div class="col-12 text-center py-5 text-muted">No payout activity found for
            <?php echo "$monthName $selectedYear"; ?>.</div>
        <?php else: ?>
        <?php foreach($payouts as $p): ?>
        <div class="col-12 col-lg-6">
            <div class="card card-payout shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-2">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?php echo $p['mentor_name']; ?></h5>
                            <small class="text-muted"><i
                                    class="bi bi-telephone me-1"></i><?php echo $p['mentor_phone']; ?></small>
                        </div>
                        <a href="quidei_payout_detail.php?mentor_id=<?php echo $p['mentor_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                            class="btn btn-sm btn-outline-dark rounded-pill px-3">View Detailed Breakdown</a>
                    </div>

                    <div class="row text-center gy-2">
                        <div class="col-4 border-end">
                            <div class="small text-muted fw-bold mb-1" style="font-size: 0.6rem;">TEACHERS TOTAL</div>
                            <div class="fw-bold text-dark">₹<?php echo number_format($p['total_teacher_payout'], 2); ?>
                            </div>
                            <div class="text-muted" style="font-size: 0.55rem;">(Inc. Sessions & Demos)</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="small text-muted fw-bold mb-1" style="font-size: 0.6rem;">MENTOR FEE</div>
                            <div class="fw-bold text-dark">₹<?php echo number_format($p['mentor_earnings'], 2); ?></div>
                            <div class="text-muted" style="font-size: 0.55rem;">(Sessions & Exams)</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted fw-bold mb-1" style="font-size: 0.6rem;">GRAND TOTAL</div>
                            <div class="text-lump-sum h5 mb-0">₹<?php echo number_format($p['grand_lump_sum'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>