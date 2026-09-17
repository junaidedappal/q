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

// 2. Fetch Regular Sessions 
$session_sql = "SELECT sess.date, sess.planned_hours, sess.teacher_pay, sess.notes, sub.name as subject_name, stu.name as student_name, u_t.name as teacher_name, u_t.phone as teacher_phone, u_t.id as teacher_user_id, 'Curriculum' as type FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id JOIN students stu ON sch.student_id = stu.id JOIN subjects sub ON sess.subject_id = sub.id JOIN teachers t ON sess.teacher_id = t.id JOIN users u_t ON t.user_id = u_t.id WHERE sch.mentor_id = ? AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ? ORDER BY u_t.name ASC, sess.date ASC"; 
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

// 3. Fetch Independent Mentor Demos 
$demo_sql = "SELECT md.demo_date as date, md.duration_hours as planned_hours, g.payment_per_hour as teacher_pay, md.feedback as notes, sub.name as subject_name, stu.name as student_name, u_t.name as teacher_name, u_t.phone as teacher_phone, u_t.id as teacher_user_id, 'Independent Demo' as type FROM mentor_demos md JOIN students stu ON md.student_id = stu.id JOIN grades g ON stu.class = g.grade_name JOIN subjects sub ON md.subject_id = sub.id JOIN teachers t ON md.teacher_id = t.id JOIN users u_t ON t.user_id = u_t.id WHERE stu.mentor_id = ? AND MONTH(md.demo_date) = ? AND YEAR(md.demo_date) = ? ORDER BY u_t.name ASC"; 
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
$exam_sql = "SELECT ex.exam_date, ex.obtained_mark, sub.name as subject_name, stu.name as student_name FROM schedule_exams ex JOIN schedules sch ON ex.schedule_id = sch.id JOIN students stu ON sch.student_id = stu.id JOIN subjects sub ON ex.subject_id = sub.id WHERE sch.mentor_id = ? AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ? ORDER BY ex.exam_date ASC";
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

// --- CSV EXPORT --- 
if (isset($_GET['export']) && $_GET['export'] == 'csv') { 
    if (ob_get_length()) ob_end_clean();
    $fileName = "Quidei_Pay_Sheet_{$mentor['name']}_{$monthName}.csv";
    header('Content-Type: text/csv; charset=utf-8'); 
    header('Content-Disposition: attachment; filename=' . $fileName); 
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

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

<!-- Header Summary -->
<div class="card card-main bg-white p-4 mb-4">
    <div class="row align-items-center">
        <!-- Added mobile bottom spacing and border adjustments -->
        <div class="col-md-7 border-md-end border-bottom border-md-bottom-0 pb-3 pb-md-0 mb-3 mb-md-0">
            <h6 class="text-uppercase fw-bold text-muted small mb-1">Company Distribution Sheet</h6>
            <h2 class="fw-bold text-slate-900 mb-0"><?php echo $mentor['name']; ?></h2>
            <div class="mt-2 small text-muted">
                <span class="me-3"><i class="bi bi-calendar3"></i> <?php echo "$monthName $year"; ?></span>
                <span><i class="bi bi-telephone"></i> <?php echo $mentor['phone']; ?></span>
            </div>
        </div>
        <!-- Align text to start on mobile, end on desktop -->
        <div class="col-md-5 text-start text-md-end">
            <div class="small fw-bold text-muted">TOTAL LUMP SUM PAYABLE</div>
            <!-- text-nowrap prevents symbol overlap -->
            <div class="display-6 fw-bold text-primary text-nowrap">₹<?php echo number_format($lump_sum_total, 2); ?>
            </div>
        </div>
    </div>
</div>

<!-- Distribution List -->
<h5 class="fw-bold mb-3"><i class="bi bi-people-fill me-2"></i>Teacher Wise Distribution</h5>
<?php foreach($teacher_data as $tid => $data): 
    $t_sum = array_sum(array_map(function($i){ return $i['planned_hours'] * $i['teacher_pay']; }, $data['list']));
?>
<div class="card card-main mb-4 overflow-hidden">
    <!-- Stack items vertically on mobile, horizontally on sm and up -->
    <div
        class="teacher-section-header p-3 d-flex flex-column flex-sm-row justify-content-sm-between align-items-start align-items-sm-center border-bottom">
        <div class="mb-2 mb-sm-0">
            <b><?php echo $data['info']['name']; ?></b><br>
            <small class="opacity-75"><?php echo $data['info']['phone']; ?></small>
        </div>
        <div class="text-start text-sm-end">
            <small class="opacity-75 small fw-bold">TOTAL DISTRIBUTION</small>
            <!-- text-nowrap added here -->
            <div class="h5 fw-bold mb-0 text-nowrap">₹<?php echo number_format($t_sum, 2); ?></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="bg-light small text-uppercase">
                <tr>
                    <th class="ps-3 ps-md-4">Type</th>
                    <th>Date</th>
                    <th>Student & Subject</th>
                    <th class="text-center">Rate</th>
                    <th class="text-end pe-3 pe-md-4">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['list'] as $row): ?>
                <tr>
                    <td class="ps-3 ps-md-4">
                        <span
                            class="type-pill <?php echo $row['type']=='Independent Demo' ? 'type-demo' : 'type-curr'; ?>">
                            <?php echo $row['type']=='Independent Demo' ? 'Demo' : 'Class'; ?>
                        </span>
                    </td>
                    <td class="small text-nowrap"><?php echo date('d M', strtotime($row['date'])); ?></td>
                    <td>
                        <div class="fw-bold small"><?php echo $row['student_name']; ?></div>
                        <div class="text-muted" style="font-size:0.7rem">
                            <?php echo $row['subject_name']; ?> (<?php echo $row['planned_hours']; ?> hrs)
                        </div>
                    </td>
                    <!-- text-nowrap added to prevent overlap -->
                    <td class="text-center small text-muted text-nowrap">₹<?php echo $row['teacher_pay']; ?></td>
                    <!-- text-nowrap added to prevent overlap -->
                    <td class="text-end pe-3 pe-md-4 fw-bold text-nowrap">
                        ₹<?php echo number_format($row['planned_hours'] * $row['teacher_pay'], 2); ?>
                    </td>
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
                <div class="small text-muted mb-1">Sessions Supervised</div>
                <!-- text-nowrap added here -->
                <div class="fw-bold text-nowrap">₹<?php echo number_format($mentor_session_earn, 2); ?></div>
            </div>
            <div class="col-6">
                <div class="h4 mb-0"><?php echo $total_exams_count; ?></div>
                <div class="small text-muted mb-1">Exams Managed</div>
                <!-- text-nowrap added here -->
                <div class="fw-bold text-nowrap">₹<?php echo number_format($mentor_exam_earn, 2); ?></div>
            </div>
        </div>
    </div>
    <!-- Adjusted footer flex layout for extra small screens -->
    <div
        class="card-footer bg-dark text-white d-flex flex-column flex-sm-row justify-content-sm-between align-items-center py-3">
        <span class="text-uppercase small fw-bold mb-2 mb-sm-0">Subtotal Mentor Fee</span>
        <!-- text-nowrap added here -->
        <span class="h5 mb-0 fw-bold text-nowrap">₹<?php echo number_format($mentor_total, 2); ?></span>
    </div>
</div>