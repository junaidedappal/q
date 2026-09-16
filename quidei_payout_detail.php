<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

$mentor_id = intval($_GET['mentor_id']);
$month = intval($_GET['month']);
$year = intval($_GET['year']);
$monthName = date('F', mktime(0, 0, 0, $month, 10));

// Security: Mentor check
if ($_SESSION['role'] === 'mentor') {
    $stmt = $conn->prepare("SELECT id FROM mentors WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $m = $stmt->get_result()->fetch_assoc();
    if(!$m || $m['id'] != $mentor_id) die("Unauthorized access.");
}

// 1. Fetch Mentor Identity & Rates
$mentor_stmt = $conn->prepare("SELECT u.name, u.phone, m.rate_per_session, m.rate_per_exam FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
$mentor_stmt->bind_param("i", $mentor_id);
$mentor_stmt->execute();
$mentor = $mentor_stmt->get_result()->fetch_assoc();

$teacher_data = [];
$total_sessions_count = 0;
$total_teacher_payout = 0;

// 2. Fetch Regular Sessions (Uses pay saved in the session row)
$session_sql = "SELECT 
                    sess.date, sess.planned_hours, sess.teacher_pay, sess.notes,
                    sub.name as subject_name, stu.name as student_name, 
                    u_t.name as teacher_name, u_t.phone as teacher_phone, u_t.id as teacher_user_id,
                    'Curriculum' as type
                FROM sessions sess
                JOIN schedules sch ON sess.schedule_id = sch.id
                JOIN students stu ON sch.student_id = stu.id
                JOIN subjects sub ON sess.subject_id = sub.id
                JOIN teachers t ON sess.teacher_id = t.id
                JOIN users u_t ON t.user_id = u_t.id
                WHERE sch.mentor_id = ? AND sess.status = 'done' 
                AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?
                ORDER BY u_t.name ASC, sess.date ASC";
$s_stmt = $conn->prepare($session_sql);
$s_stmt->bind_param("iii", $mentor_id, $month, $year);
$s_stmt->execute();
$res_sessions = $s_stmt->get_result();

while ($s = $res_sessions->fetch_assoc()) {
    $teacher_data[$s['teacher_user_id']]['info'] = ['name' => $s['teacher_name'], 'phone' => $s['teacher_phone']];
    $teacher_data[$s['teacher_user_id']]['list'][] = $s;
    $total_sessions_count++;
    $total_teacher_payout += ($s['planned_hours'] * $s['teacher_pay']);
}

// 3. Fetch Independent Mentor Demos (Pulls Rate from GRADES table based on Student Class)
$demo_sql = "SELECT 
                md.demo_date as date, md.duration_hours as planned_hours, g.payment_per_hour as teacher_pay, md.feedback as notes,
                sub.name as subject_name, stu.name as student_name,
                u_t.name as teacher_name, u_t.phone as teacher_phone, u_t.id as teacher_user_id,
                'Independent Demo' as type
             FROM mentor_demos md
             JOIN students stu ON md.student_id = stu.id
             JOIN grades g ON stu.class = g.grade_name
             JOIN subjects sub ON md.subject_id = sub.id
             JOIN teachers t ON md.teacher_id = t.id
             JOIN users u_t ON t.user_id = u_t.id
             WHERE stu.mentor_id = ? AND MONTH(md.demo_date) = ? AND YEAR(md.demo_date) = ?
             ORDER BY u_t.name ASC";
$d_stmt = $conn->prepare($demo_sql);
$d_stmt->bind_param("iii", $mentor_id, $month, $year);
$d_stmt->execute();
$res_demos = $d_stmt->get_result();

while ($d = $res_demos->fetch_assoc()) {
    if (!isset($teacher_data[$d['teacher_user_id']])) {
        $teacher_data[$d['teacher_user_id']]['info'] = ['name' => $d['teacher_name'], 'phone' => $d['teacher_phone']];
    }
    $teacher_data[$d['teacher_user_id']]['list'][] = $d;
    $total_teacher_payout += ($d['planned_hours'] * $d['teacher_pay']);
}

// 4. Fetch Exams
$exam_sql = "SELECT ex.exam_date, ex.obtained_mark, sub.name as subject_name, stu.name as student_name
             FROM schedule_exams ex
             JOIN schedules sch ON ex.schedule_id = sch.id
             JOIN students stu ON sch.student_id = stu.id
             JOIN subjects sub ON ex.subject_id = sub.id
             WHERE sch.mentor_id = ? AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?
             ORDER BY ex.exam_date ASC";
$e_stmt = $conn->prepare($exam_sql);
$e_stmt->bind_param("iii", $mentor_id, $month, $year);
$e_stmt->execute();
$exam_res = $e_stmt->get_result();
$exam_data = $exam_res->fetch_all(MYSQLI_ASSOC);
$total_exams_count = count($exam_data);

// Mentor Calculation
$mentor_session_earn = $total_sessions_count * $mentor['rate_per_session'];
$mentor_exam_earn = $total_exams_count * $mentor['rate_per_exam'];
$mentor_total = $mentor_session_earn + $mentor_exam_earn;
$lump_sum_total = $mentor_total + $total_teacher_payout;

// --- CSV EXPORT (FIXED FOR PHP 8.4 DEPRECATION) ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    if (ob_get_length()) ob_end_clean();
    $fileName = "Quidei_Pay_Sheet_{$mentor['name']}_{$monthName}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 
    
    // Header with required escape parameters
    fputcsv($output, ['Category', 'Date', 'Student', 'Subject', 'Type', 'Rate', 'Amount'], ",", "\"", "\\");
    
    foreach ($teacher_data as $tid => $t) {
        fputcsv($output, ["TEACHER: " . $t['info']['name']], ",", "\"", "\\");
        foreach ($t['list'] as $row) {
            fputcsv($output, [
                'Teacher Distribution', $row['date'], $row['student_name'], $row['subject_name'], 
                $row['type'], $row['teacher_pay'], ($row['planned_hours'] * $row['teacher_pay'])
            ], ",", "\"", "\\");
        }
    }
    fputcsv($output, ['MENTOR PROFESSIONAL FEE', '', '', '', '', '', $mentor_total], ",", "\"", "\\");
    fputcsv($output, ['GRAND LUMP SUM TOTAL', '', '', '', '', '', $lump_sum_total], ",", "\"", "\\");
    fclose($output);
    exit;
}
?>

<style>
body {
    background-color: #f8fafc;
    font-family: 'Inter', sans-serif;
}

.card-main {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.teacher-section-header {
    background: #0f172a;
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
}

.type-pill {
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: bold;
    text-transform: uppercase;
}

.type-demo {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fcd34d;
}

.type-curr {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #86efac;
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="quidei_payouts.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>"
            class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left"></i> Back</a>
        <div class="d-flex gap-2">
            <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv"
                class="btn btn-sm btn-outline-success rounded-pill px-3"><i class="bi bi-file-earmark-excel"></i> Export
                CSV</a>
            <button onclick="window.print()" class="btn btn-sm btn-dark rounded-pill px-3"><i class="bi bi-printer"></i>
                Print Sheet</button>
        </div>
    </div>

    <!-- Header Summary -->
    <div class="card card-main bg-white p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-7 border-md-end">
                <h6 class="text-uppercase fw-bold text-muted small mb-1">Company Distribution Sheet</h6>
                <h2 class="fw-bold text-slate-900 mb-0"><?php echo $mentor['name']; ?></h2>
                <div class="mt-2 small text-muted">
                    <span class="me-3"><i class="bi bi-calendar3"></i> <?php echo "$monthName $year"; ?></span>
                    <span><i class="bi bi-telephone"></i> <?php echo $mentor['phone']; ?></span>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="small fw-bold text-muted">TOTAL LUMP SUM PAYABLE</div>
                <div class="display-6 fw-bold text-primary">₹<?php echo number_format($lump_sum_total, 2); ?></div>
            </div>
        </div>
    </div>

    <!-- Distribution List -->
    <h5 class="fw-bold mb-3"><i class="bi bi-people-fill me-2"></i>Teacher Wise Distribution</h5>
    <?php foreach($teacher_data as $tid => $data): 
        $t_sum = array_sum(array_map(function($i){ return $i['planned_hours'] * $i['teacher_pay']; }, $data['list']));
    ?>
    <div class="card card-main mb-4 overflow-hidden">
        <div class="teacher-section-header d-flex justify-content-between align-items-center">
            <div><b><?php echo $data['info']['name']; ?></b><br><small
                    class="opacity-75"><?php echo $data['info']['phone']; ?></small></div>
            <div class="text-end"><small class="opacity-75 small fw-bold">TOTAL DISTRIBUTION</small>
                <div class="h5 fw-bold mb-0">₹<?php echo number_format($t_sum, 2); ?></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="bg-light small text-uppercase">
                    <tr>
                        <th class="ps-4">Type</th>
                        <th>Date</th>
                        <th>Student & Subject</th>
                        <th class="text-center">Rate</th>
                        <th class="text-end pe-4">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($data['list'] as $row): ?>
                    <tr>
                        <td class="ps-4"><span
                                class="type-pill <?php echo $row['type']=='Independent Demo' ? 'type-demo' : 'type-curr'; ?>"><?php echo $row['type']=='Independent Demo' ? 'Demo' : 'Class'; ?></span>
                        </td>
                        <td class="small"><?php echo date('d M', strtotime($row['date'])); ?></td>
                        <td>
                            <div class="fw-bold small"><?php echo $row['student_name']; ?></div>
                            <div class="text-muted" style="font-size:0.7rem"><?php echo $row['subject_name']; ?>
                                (<?php echo $row['planned_hours']; ?> hrs)</div>
                        </td>
                        <td class="text-center small text-muted">₹<?php echo $row['teacher_pay']; ?></td>
                        <td class="text-end pe-4 fw-bold">
                            ₹<?php echo number_format($row['planned_hours'] * $row['teacher_pay'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Mentor Fee Section -->
    <div class="card card-main bg-white mb-5">
        <div class="card-header bg-light fw-bold">Mentor Professional Fee</div>
        <div class="card-body py-4">
            <div class="row text-center gy-3">
                <div class="col-6 border-end">
                    <div class="h4 mb-0"><?php echo $total_sessions_count; ?></div>
                    <div class="small text-muted">Sessions Supervised</div>
                    <div class="fw-bold">₹<?php echo number_format($mentor_session_earn, 2); ?></div>
                </div>
                <div class="col-6">
                    <div class="h4 mb-0"><?php echo $total_exams_count; ?></div>
                    <div class="small text-muted">Exams Managed</div>
                    <div class="fw-bold">₹<?php echo number_format($mentor_exam_earn, 2); ?></div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-dark text-white d-flex justify-content-between align-items-center">
            <span class="text-uppercase small fw-bold">Subtotal Mentor Fee</span>
            <span class="h5 mb-0 fw-bold">₹<?php echo number_format($mentor_total, 2); ?></span>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>