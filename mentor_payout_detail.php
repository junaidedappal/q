<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

$mentor_id = intval($_GET['mentor_id']);
$month = intval($_GET['month']);
$year = intval($_GET['year']);
$monthName = date('F', mktime(0, 0, 0, $month, 10));

// Security: Mentor can only see their own ID
if ($_SESSION['role'] === 'mentor') {
    $mentor_check_stmt = $conn->prepare("SELECT id FROM mentors WHERE user_id = ?");
    $mentor_check_stmt->bind_param("i", $_SESSION['id']);
    $mentor_check_stmt->execute();
    $check = $mentor_check_stmt->get_result()->fetch_assoc();
    if (!$check || $check['id'] != $mentor_id) die("<div class='container mt-5 text-center'><h4>Unauthorized access.</h4></div>");
}

// 1. Fetch Mentor Info
$mentor_stmt = $conn->prepare("SELECT u.name, u.phone, m.rate_per_session, m.rate_per_exam FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
$mentor_stmt->bind_param("i", $mentor_id);
$mentor_stmt->execute();
$mentor = $mentor_stmt->get_result()->fetch_assoc();

// 2. Fetch All Detailed Sessions
$sessions_sql = "SELECT sess.*, sub.name as subject_name, u.name as teacher_name, stu.name as student_name, sch.name as schedule_title, sch.id as sch_id
                FROM sessions sess
                JOIN schedules sch ON sess.schedule_id = sch.id
                JOIN students stu ON sch.student_id = stu.id
                JOIN subjects sub ON sess.subject_id = sub.id
                JOIN teachers t ON sess.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                WHERE sch.mentor_id = ? AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?
                ORDER BY sess.date ASC";
$s_stmt = $conn->prepare($sessions_sql);
$s_stmt->bind_param("iii", $mentor_id, $month, $year);
$s_stmt->execute();
$sessions_res = $s_stmt->get_result();

// 3. Fetch All Detailed Exams
$exams_sql = "SELECT ex.*, sub.name as subject_name, stu.name as student_name, sch.name as schedule_title, sch.id as sch_id
              FROM schedule_exams ex
              JOIN schedules sch ON ex.schedule_id = sch.id
              JOIN students stu ON sch.student_id = stu.id
              JOIN subjects sub ON ex.subject_id = sub.id
              WHERE sch.mentor_id = ? AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?
              ORDER BY ex.exam_date ASC";
$e_stmt = $conn->prepare($exams_sql);
$e_stmt->bind_param("iii", $mentor_id, $month, $year);
$e_stmt->execute();
$exams_res = $e_stmt->get_result();

$grouped_data = [];
$csv_data = [];
while($s = $sessions_res->fetch_assoc()) {
    $grouped_data[$s['sch_id']]['info'] = ['student' => $s['student_name'], 'title' => $s['schedule_title']];
    $grouped_data[$s['sch_id']]['sessions'][] = $s;
    $csv_data[] = ['Session', $s['date'], $s['student_name'], $s['subject_name'], $s['teacher_name'], $s['planned_hours'], $mentor['rate_per_session']];
}
while($e = $exams_res->fetch_assoc()) {
    $grouped_data[$e['sch_id']]['info'] = ['student' => $e['student_name'], 'title' => $e['schedule_title']];
    $grouped_data[$e['sch_id']]['exams'][] = $e;
    $csv_data[] = ['Exam', $e['exam_date'], $e['student_name'], $e['subject_name'], 'N/A', $e['obtained_mark'], $mentor['rate_per_exam']];
}

// --- CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    $filename = "Payout_Detail_{$mentor['name']}_{$monthName}_{$year}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
    fputcsv($output, ['Type', 'Date', 'Student', 'Subject', 'Teacher/Mark', 'Hrs/Score', 'Rate'], ",", "\"", "\\");
    foreach($csv_data as $line) { fputcsv($output, $line, ",", "\"", "\\"); }
    fclose($output);
    exit;
}
?>

<style>
body {
    background-color: #f8f9fa;
    font-family: 'Inter', sans-serif;
}

.card-report {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.card-report-header {
    background: #ffffff;
    border-bottom: 1px solid #edf2f7;
    padding: 1rem 1.25rem;
}

.badge-soft-primary {
    background: #eef2ff;
    color: #4338ca;
}

.badge-soft-success {
    background: #f0fdf4;
    color: #166534;
}

.badge-soft-info {
    background: #e0f2fe;
    color: #0369a1;
}

@media (max-width: 768px) {
    .table-responsive-stack thead {
        display: none;
    }

    .table-responsive-stack tr {
        display: block;
        border-bottom: 1px solid #eee;
        padding: 10px 0;
    }

    .table-responsive-stack td {
        display: flex;
        justify-content: space-between;
        text-align: right;
        border: none;
        padding: 5px 15px;
        font-size: 0.85rem;
    }

    .table-responsive-stack td::before {
        content: attr(data-label);
        font-weight: bold;
        color: #718096;
        text-transform: uppercase;
        font-size: 0.7rem;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .card-report {
        box-shadow: none;
        border: 1px solid #eee;
    }
}
</style>

<div class="container py-4">
    <!-- Action Bar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 no-print">
        <a href="mentor_payouts.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>"
            class="btn btn-sm btn-light border rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <div class="d-flex gap-2">
            <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv"
                class="btn btn-sm btn-outline-success rounded-pill px-3">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-dark rounded-pill px-3">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="card card-report mb-4 bg-primary text-white p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-uppercase opacity-75 small fw-bold">Detailed Payout Report</h6>
                <h2 class="fw-bold mb-0"><?php echo $mentor['name']; ?></h2>
                <p class="mb-0 opacity-75"><?php echo "$monthName $year"; ?> • Phone: <?php echo $mentor['phone']; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php 
                    $t_s = 0; $t_e = 0;
                    foreach($grouped_data as $g) {
                        $t_s += isset($g['sessions']) ? count($g['sessions']) : 0;
                        $t_e += isset($g['exams']) ? count($g['exams']) : 0;
                    }
                    $grand_total = ($t_s * $mentor['rate_per_session']) + ($t_e * $mentor['rate_per_exam']);
                ?>
                <div class="small opacity-75">MONTHLY TOTAL</div>
                <div class="display-6 fw-bold">₹<?php echo number_format($grand_total, 2); ?></div>
            </div>
        </div>
    </div>

    <!-- Student Breakdown -->
    <?php if(empty($grouped_data)): ?>
    <div class="text-center py-5 bg-white rounded shadow-sm">
        <i class="bi bi-folder2-open display-4 text-muted"></i>
        <p class="mt-2 text-muted">No payout records for this period.</p>
    </div>
    <?php else: ?>
    <?php foreach($grouped_data as $sch_id => $data): 
            $s_count = isset($data['sessions']) ? count($data['sessions']) : 0;
            $e_count = isset($data['exams']) ? count($data['exams']) : 0;
            $subtotal = ($s_count * $mentor['rate_per_session']) + ($e_count * $mentor['rate_per_exam']);
        ?>
    <div class="card card-report">
        <div class="card-report-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-0 text-dark"><?php echo $data['info']['student']; ?></h6>
                <span class="small text-muted text-uppercase"
                    style="font-size: 0.65rem;"><?php echo $data['info']['title'] ?: 'Default Schedule'; ?></span>
            </div>
            <div class="text-end">
                <span
                    class="badge badge-soft-success px-3 py-2 rounded-pill">₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Sessions List -->
            <?php if($s_count > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-responsive-stack mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 small">Date</th>
                            <th class="small">Subject</th>
                            <th class="small">Teacher</th>
                            <th class="text-center small">Hrs</th>
                            <th class="text-end pe-4 small">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['sessions'] as $sess): ?>
                        <tr>
                            <td class="ps-4" data-label="Date"><?php echo date('d M', strtotime($sess['date'])); ?></td>
                            <td data-label="Subject" class="fw-bold"><?php echo $sess['subject_name']; ?></td>
                            <td data-label="Teacher"><span
                                    class="badge badge-soft-primary"><?php echo $sess['teacher_name']; ?></span></td>
                            <td class="text-center" data-label="Hrs"><?php echo $sess['planned_hours']; ?></td>
                            <td class="text-end pe-4" data-label="Rate">₹<?php echo $mentor['rate_per_session']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Exams List -->
            <?php if($e_count > 0): ?>
            <div class="bg-light p-3 border-top">
                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size: 0.65rem;">Exams Held</div>
                <div class="row g-2">
                    <?php foreach($data['exams'] as $ex): ?>
                    <div class="col-md-6">
                        <div
                            class="bg-white border rounded p-2 d-flex justify-content-between align-items-center shadow-sm">
                            <div>
                                <div class="small fw-bold text-dark"><?php echo $ex['subject_name']; ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">
                                    <?php echo date('d M Y', strtotime($ex['exam_date'])); ?></div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-info small mb-1">₹<?php echo $mentor['rate_per_exam']; ?></span>
                                <?php if($ex['obtained_mark']): ?>
                                <div class="fw-bold small text-success">Mark: <?php echo $ex['obtained_mark']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="mt-5 text-center no-print">
        <p class="text-muted small">Generated automatically by System • <?php echo date('d M Y, H:i'); ?></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>