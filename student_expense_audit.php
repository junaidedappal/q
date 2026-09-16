<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

/* ===========================
   AUTH CHECK
=========================== */
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'accountant'])) {
    header('location: login.php');
    exit;
}

/* ===========================
   FILTER
=========================== */
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear  = $_GET['year'] ?? date('Y');
$monthName     = date('F', mktime(0, 0, 0, (int)$selectedMonth, 10));

/* ===========================
   MAIN QUERY
=========================== */
$sql = "SELECT 
            stu.id as student_id, stu.name as student_name, stu.class,
            u_t.name as teacher_name, u_t.id as teacher_id,
            u_m.name as mentor_name, m.rate_per_session, m.rate_per_exam,
            sess.planned_hours, sess.teacher_pay
        FROM sessions sess
        JOIN schedules sch ON sess.schedule_id = sch.id
        JOIN students stu ON sch.student_id = stu.id
        JOIN teachers t ON sess.teacher_id = t.id
        JOIN users u_t ON t.user_id = u_t.id
        JOIN mentors m ON sch.mentor_id = m.id
        JOIN users u_m ON m.user_id = u_m.id
        WHERE sess.status = 'done'
        AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?
        ORDER BY stu.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$res = $stmt->get_result();

$report = [];
$total_teaching_only = 0;

while ($row = $res->fetch_assoc()) {
    $sid = $row['student_id'];
    $tid = $row['teacher_id'];
    $sessionCost = $row['planned_hours'] * $row['teacher_pay'];

    if (!isset($report[$sid])) {
        $report[$sid] = [
            'info' => ['name' => $row['student_name'], 'class' => $row['class'], 'mentor' => $row['mentor_name']],
            'mentor_rates' => ['s' => $row['rate_per_session'], 'e' => $row['rate_per_exam']],
            'teachers' => [],
            'session_count' => 0,
            'teaching_cost' => 0
        ];
    }

    if (!isset($report[$sid]['teachers'][$tid])) {
        $report[$sid]['teachers'][$tid] = ['name' => $row['teacher_name'], 'total' => 0, 'hrs' => 0];
    }

    $report[$sid]['teachers'][$tid]['total'] += $sessionCost;
    $report[$sid]['teachers'][$tid]['hrs']   += $row['planned_hours'];
    $report[$sid]['session_count']++;
    $report[$sid]['teaching_cost'] += $sessionCost;
    $total_teaching_only += $sessionCost;
}

/* ===========================
   EXAM COUNT + FINAL TOTALS
=========================== */
$total_mentor_only = 0;
foreach ($report as $sid => &$data) {
    $e_stmt = $conn->prepare("SELECT COUNT(*) as ec FROM schedule_exams ex JOIN schedules sch ON ex.schedule_id = sch.id WHERE sch.student_id = ? AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?");
    $e_stmt->bind_param("iii", $sid, $selectedMonth, $selectedYear);
    $e_stmt->execute();
    $e_count = $e_stmt->get_result()->fetch_assoc()['ec'];

    $data['exam_count'] = $e_count;
    $data['mentor_total'] = ($data['session_count'] * $data['mentor_rates']['s']) + ($e_count * $data['mentor_rates']['e']);
    $data['grand_total'] = $data['teaching_cost'] + $data['mentor_total'];
    $total_mentor_only += $data['mentor_total'];
}
unset($data); // Break reference

$companyGrandTotal = $total_teaching_only + $total_mentor_only;

/* ===========================
   BETTER EXCEL EXPORT (CSV)
=========================== */
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    $filename = "Expense_Audit_{$monthName}_{$selectedYear}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename='.$filename);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    
    fputcsv($output, ['Student Name', 'Class', 'Mentor', 'Sessions', 'Exams', 'Teacher Cost', 'Mentor Cost', 'Total Expense'], ",", "\"", "\\");
    
    foreach ($report as $r) {
        fputcsv($output, [
            $r['info']['name'], $r['info']['class'], $r['info']['mentor'],
            $r['session_count'], $r['exam_count'],
            number_format($r['teaching_cost'], 2, '.', ''),
            number_format($r['mentor_total'], 2, '.', ''),
            number_format($r['grand_total'], 2, '.', '')
        ], ",", "\"", "\\");
    }
    fputcsv($output, ['TOTAL COMPANY OUTFLOW', '', '', '', '', '', '', number_format($companyGrandTotal, 2, '.', '')], ",", "\"", "\\");
    fclose($output);
    exit;
}
?>

<style>
body {
    background-color: #f1f5f9;
    font-family: 'Inter', sans-serif;
}

.header-finance {
    background: #0f172a;
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
}

.stat-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.student-card {
    border: none;
    border-radius: 16px;
    background: white;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-head {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    padding: 15px 20px;
}

.label-finance {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.money-font {
    font-family: 'Roboto Mono', monospace;
    font-weight: 700;
}

@media (max-width: 768px) {
    .flex-stack {
        display: block !important;
    }

    .mobile-mt {
        margin-top: 10px;
    }
}
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="header-finance mb-4 shadow-sm p-3 p-md-4">
        <!-- Added gy-3 to handle vertical spacing when items stack on mobile -->
        <div class="row align-items-center gy-3">

            <!-- Title & Subtitle: Centered on mobile, left-aligned on desktop -->
            <div class="col-12 col-md-6 text-center text-md-start">
                <h3 class="fw-bold mb-1">
                    <i class="bi bi-wallet2 me-2 text-info"></i>Expense Audit
                </h3>
                <p class="mb-0 opacity-75 small">
                    <?php echo "$monthName $selectedYear"; ?> • Financial Deep Audit
                </p>
            </div>

            <!-- Actions (Export & Filter): Centered on mobile, right-aligned on desktop -->
            <div class="col-12 col-md-6">
                <div class="d-flex flex-wrap justify-content-center justify-content-md-end align-items-center gap-2">

                    <!-- Export Button -->
                    <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv"
                        class="btn btn-light btn-sm rounded-pill px-3 fw-bold shadow-sm border">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i>
                        <span class="d-inline">Export</span>
                        <!-- Hidden text logic removed for simplicity, works on all sizes -->
                    </a>

                    <!-- Filter Form: Uses flex-nowrap to keep selects side-by-side -->
                    <form class="d-flex gap-1 bg-white p-1 rounded-pill shadow-sm border align-items-center">
                        <select name="month" class="form-select form-select-sm border-0 bg-transparent py-0 ps-3"
                            style="width: auto; min-width: 80px;">
                            <?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                        </select>

                        <!-- Vertical Divider between selects -->
                        <div class="vr my-1 opacity-25 d-none d-sm-block" style="width: 1px; background-color: #000;">
                        </div>

                        <select name="year" class="form-select form-select-sm border-0 bg-transparent py-0"
                            style="width: auto;">
                            <?php for($y=2024; $y<=date('Y'); $y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
                        </select>

                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                            Go
                        </button>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <!-- Company Total Summary -->
    <div class="card stat-card p-3 mb-4 bg-white border-start border-primary border-5">
        <div class="d-flex justify-content-between align-items-center">
            <span class="label-finance">ESTIMATED COMPANY OUTFLOW</span>
            <span
                class="h3 fw-bold text-dark money-font mb-0">₹<?php echo number_format($companyGrandTotal, 2); ?></span>
        </div>
    </div>

    <?php if(empty($report)): ?>
    <div class="alert alert-light text-center py-5 shadow-sm">No financial activity recorded for this period.</div>
    <?php else: ?>
    <?php foreach ($report as $sid => $data): ?>
    <div class="student-card shadow-sm">
        <!-- Card Head -->
        <div class="card-head d-flex justify-content-between align-items-center flex-stack">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><?php echo $data['info']['name']; ?></h5>
                <small class="text-muted text-uppercase fw-bold" style="font-size:0.65rem;">
                    <?php echo $data['info']['class']; ?> • <span class="text-primary">Mentor:
                        <?php echo $data['info']['mentor']; ?></span>
                </small>
            </div>
            <div class="text-md-end mobile-mt">
                <div class="label-finance">Total Student Expense</div>
                <div class="h4 fw-bold text-dark mb-0 money-font">₹<?php echo number_format($data['grand_total'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
            <div class="row g-4 p-4">
                <!-- Teaching Detail -->
                <div class="col-md-7 border-md-end">
                    <div class="label-finance mb-3" style="color: #4e73df;"><i class="bi bi-person-video3 me-1"></i>
                        Teacher Payouts</div>
                    <?php foreach($data['teachers'] as $t): ?>
                    <div class="d-flex justify-content-between py-2 border-bottom border-light">
                        <span class="text-dark small fw-bold"><?php echo $t['name']; ?> <small
                                class="text-muted fw-normal">(<?php echo $t['hrs']; ?> Hrs)</small></span>
                        <span class="money-font small">₹<?php echo number_format($t['total'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Mentor Detail -->
                <div class="col-md-5">
                    <div class="label-finance mb-3" style="color: #f6993f;"><i class="bi bi-shield-check me-1"></i>
                        Mentor Professional Fees</div>
                    <div class="bg-light p-3 rounded-3 shadow-sm border border-white">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Mentoring (<?php echo $data['session_count']; ?> sess)</span>
                            <span
                                class="money-font">₹<?php echo number_format($data['session_count'] * $data['mentor_rates']['s'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Exams (<?php echo $data['exam_count']; ?> exams)</span>
                            <span
                                class="money-font">₹<?php echo number_format($data['exam_count'] * $data['mentor_rates']['e'], 2); ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold text-dark">
                            <span class="small">MENTOR SUBTOTAL</span>
                            <span
                                class="money-font small">₹<?php echo number_format($data['mentor_total'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center py-4 no-print text-muted small">
        Financial Audit System • <?php echo date('d M Y, h:i A'); ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>