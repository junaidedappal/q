<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

// Authorization: Admin and Supervisor only
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    header('location: login.php'); exit;
}

// Robust Filter Logic
$mentor_id     = isset($_GET['mentor_id']) ? (int)$_GET['mentor_id'] : 0;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$monthName     = date('F', mktime(0, 0, 0, $selectedMonth, 10));

if ($mentor_id === 0) {
    die("<div class='container mt-5 alert alert-warning'>Invalid Mentor ID provided.</div>");
}

// Security: Supervisor check
if ($_SESSION['role'] === 'supervisor') {
    $check_stmt = $conn->prepare("SELECT id FROM mentors WHERE id = ? AND supervisor_id = ?");
    $check_stmt->bind_param("ii", $mentor_id, $_SESSION['id']);
    $check_stmt->execute();
    if (!$check_stmt->get_result()->fetch_assoc()) {
        die("<div class='container mt-5 alert alert-danger shadow-sm rounded-4'><b>Access Denied:</b> This mentor is not assigned to you.</div>");
    }
}

// 1. Fetch Mentor Identity
$mentor_query = $conn->prepare("SELECT u.name, u.phone FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
$mentor_query->bind_param("i", $mentor_id);
$mentor_query->execute();
$mentor = $mentor_query->get_result()->fetch_assoc();

// 2. Fetch Sessions (Excluding partial/rescheduled from summary stats logic later)
$sess_sql = "SELECT sess.*, sub.name as subject_name, u_t.name as teacher_name, 
                stu.name as student_name, sch.name as schedule_title, sch.id as sch_id
            FROM sessions sess
            JOIN schedules sch ON sess.schedule_id = sch.id
            JOIN students stu ON sch.student_id = stu.id
            JOIN subjects sub ON sess.subject_id = sub.id
            JOIN teachers t ON sess.teacher_id = t.id
            JOIN users u_t ON t.user_id = u_t.id
            WHERE sch.mentor_id = ? 
            AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?
            ORDER BY sess.date ASC";
$s_stmt = $conn->prepare($sess_sql);
$s_stmt->bind_param("iii", $mentor_id, $selectedMonth, $selectedYear);
$s_stmt->execute();
$sessions_res = $s_stmt->get_result();

// 3. Fetch Exams
$exam_sql = "SELECT ex.*, sub.name as subject_name, stu.name as student_name, 
                    sch.name as schedule_title, sch.id as sch_id
             FROM schedule_exams ex
             JOIN schedules sch ON ex.schedule_id = sch.id
             JOIN students stu ON sch.student_id = stu.id
             JOIN subjects sub ON ex.subject_id = sub.id
             WHERE sch.mentor_id = ? 
             AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?
             ORDER BY ex.exam_date ASC";
$e_stmt = $conn->prepare($exam_sql);
$e_stmt->bind_param("iii", $mentor_id, $selectedMonth, $selectedYear);
$e_stmt->execute();
$exams_res = $e_stmt->get_result();

// Data Processing & Stats Calculation (Focused on 4 Metrics)
$report = [];
$stats = ['done' => 0, 'planned' => 0, 'missed' => 0, 'exams' => 0];

while($s = $sessions_res->fetch_assoc()) {
    $report[$s['sch_id']]['info'] = ['student' => $s['student_name'], 'title' => $s['schedule_title']];
    $report[$s['sch_id']]['sessions'][] = $s;
    // Only count core statuses for the top summary
    if (isset($stats[$s['status']])) $stats[$s['status']]++;
}
while($e = $exams_res->fetch_assoc()) {
    if(!isset($report[$e['sch_id']])) {
        $report[$e['sch_id']]['info'] = ['student' => $e['student_name'], 'title' => $e['schedule_title']];
    }
    $report[$e['sch_id']]['exams'][] = $e;
    $stats['exams']++;
}

// --- CSV EXPORT ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Audit_'.$mentor['name'].'_'.$monthName.'.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); 
    fputcsv($out, ['Category', 'Date', 'Student', 'Subject', 'Teacher/Mark', 'Status', 'Notes'], ",", "\"", "\\");
    foreach ($report as $data) {
        if(isset($data['sessions'])) {
            foreach ($data['sessions'] as $s) {
                // Filter out from CSV as well if needed, or keep for full audit trail
                if(in_array($s['status'], ['partial', 'rescheduled'])) continue;
                fputcsv($out, ['Session', $s['date'], $s['student_name'], $s['subject_name'], $s['teacher_name'], strtoupper($s['status']), $s['notes']], ",", "\"", "\\");
            }
        }
        if(isset($data['exams'])) {
            foreach ($data['exams'] as $e) fputcsv($out, ['Exam', $e['exam_date'], $e['student_name'], $e['subject_name'], $e['obtained_mark'], 'Held', ''], ",", "\"", "\\");
        }
    }
    fclose($out); exit;
}
?>

<style>
body {
    background-color: #f1f5f9;
    font-family: 'Inter', sans-serif;
}

/* Professional Blue Header Gradient */
.header-section {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border-radius: 0 0 25px 25px;
    padding: 2.5rem 1rem;
    box-shadow: 0 10px 30px rgba(78, 115, 223, 0.15);
}

.stat-pill {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 12px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    height: 100%;
    transition: transform 0.2s;
}

.audit-card {
    border: none;
    border-radius: 20px;
    background: white;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    margin-bottom: 2rem;
    overflow: hidden;
}

.audit-card-head {
    background: #f8fafc;
    border-bottom: 2px solid #eef2f7;
    padding: 15px 25px;
}

/* Status Badges */
.st-badge {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 5px 12px;
    border-radius: 50px;
    display: inline-block;
}

.st-done {
    background: #ecfdf5;
    color: #059669;
}

.st-missed {
    background: #fff1f2;
    color: #e11d48;
}

.st-planned {
    background: #fffbeb;
    color: #d97706;
}

.st-other {
    background: #f1f5f9;
    color: #475569;
}

@media (max-width: 768px) {
    .header-section {
        border-radius: 0;
        padding: 1.5rem 1rem;
    }

    .desktop-table {
        display: none;
    }

    .mobile-session-row {
        border-bottom: 1px solid #f1f5f9;
        padding: 15px;
    }

    .mobile-session-row:last-child {
        border-bottom: none;
    }
}
</style>

<div class="header-section no-print">
    <div class="container">
        <!-- Navigation & Identity -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mb-4">
            <div class="text-center text-md-start w-100">
                <a href="supervisor_monitoring.php"
                    class="btn btn-sm btn-outline-light rounded-pill px-3 mb-3 d-inline-flex align-items-center"
                    style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <i class="bi bi-arrow-left me-1"></i> MONITORING
                </a>
                <h2 class="fw-bold mb-0 text-white"><?php echo $mentor['name']; ?></h2>
                <p class="opacity-75 small mb-0 mt-1"><i class="bi bi-calendar3 me-1"></i>
                    <?php echo "$monthName $selectedYear"; ?> Audit</p>
            </div>

            <div class="w-100">
                <div class="d-flex flex-wrap justify-content-center justify-content-md-end align-items-center gap-2">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>"
                        class="btn btn-sm rounded-pill px-4 fw-bold shadow-sm"
                        style="background:#10b981; color:white; height: 38px;">
                        <i class="bi bi-file-earmark-excel me-1"></i> EXCEL
                    </a>

                    <form class="d-flex gap-0 bg-white p-1 rounded-pill shadow-sm align-items-center border"
                        style="height: 38px;">
                        <input type="hidden" name="mentor_id" value="<?php echo $mentor_id; ?>">
                        <select name="month" class="form-select form-select-sm border-0 bg-transparent py-0 ps-3"
                            style="width: auto; font-weight: 600;">
                            <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                        </select>
                        <div class="vr opacity-25 my-1" style="width: 1px; background: #000;"></div>
                        <select name="year" class="form-select form-select-sm border-0 bg-transparent py-0 ps-2"
                            style="width: auto; font-weight: 600;">
                            <?php for($y=2024;$y<=date('Y');$y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
                        </select>
                        <button type="submit"
                            class="btn btn-primary btn-sm rounded-pill px-3 fw-bold h-100 ms-1">GO</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 4-Way KPI Grid (2x2 on Mobile, 4x1 on Desktop) -->
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <div class="stat-pill text-center">
                    <div class="small opacity-75 fw-bold" style="font-size:0.6rem; letter-spacing: 0.5px;">DONE</div>
                    <div class="fw-bold text-white h4 mb-0"><?php echo $stats['done']; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-pill text-center">
                    <div class="small opacity-75 fw-bold" style="font-size:0.6rem; letter-spacing: 0.5px;">PLANNED</div>
                    <div class="fw-bold text-white h4 mb-0"><?php echo $stats['planned']; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-pill text-center">
                    <div class="small opacity-75 fw-bold" style="font-size:0.6rem; letter-spacing: 0.5px;">MISSED</div>
                    <div class="fw-bold text-white h4 mb-0"><?php echo $stats['missed']; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-pill text-center" style="background: rgba(255,255,255,0.25);">
                    <div class="small opacity-75 fw-bold" style="font-size:0.6rem; letter-spacing: 0.5px;">EXAMS</div>
                    <div class="fw-bold text-white h4 mb-0"><?php echo $stats['exams']; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4 pb-5">
    <?php if(empty($report)): ?>
    <div class="text-center py-5 bg-white rounded-4 shadow-sm">
        <i class="bi bi-inbox display-1 text-muted opacity-25"></i>
        <p class="text-muted mt-3">No activity found for this period.</p>
    </div>
    <?php else: ?>
    <?php foreach($report as $sch_id => $data): ?>
    <div class="audit-card">
        <div class="audit-card-head d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark"><?php echo $data['info']['student']; ?></h5>
                <span class="text-muted small text-uppercase fw-bold"
                    style="font-size: 0.6rem;"><?php echo $data['info']['title'] ?: 'Default Schedule'; ?></span>
            </div>
            <i class="bi bi-shield-check fs-4 text-primary opacity-25"></i>
        </div>

        <div class="card-body p-0">
            <!-- Desktop View -->
            <div class="table-responsive desktop-table">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light small text-muted uppercase">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Subject & Teacher</th>
                            <th class="text-center">Hrs</th>
                            <th>Status</th>
                            <th class="pe-4">Mentor Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(isset($data['sessions'])): foreach($data['sessions'] as $s): 
                                // Skipping partial/rescheduled based on your logic request
                                if(in_array($s['status'], ['partial', 'rescheduled'])) continue;
                            ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold small"><?php echo date('d M Y', strtotime($s['date'])); ?></div>
                                <div class="text-muted small" style="font-size:0.7rem;">
                                    <?php echo $s['start_time'] ?: '--:--'; ?></div>
                            </td>
                            <td>
                                <div class="fw-bold small text-dark"><?php echo $s['subject_name']; ?></div>
                                <div class="small text-primary fw-bold"><?php echo $s['teacher_name']; ?></div>
                            </td>
                            <td class="text-center small fw-bold"><?php echo (float)$s['planned_hours']; ?></td>
                            <td><span class="st-badge st-<?php echo $s['status']; ?>"><?php echo $s['status']; ?></span>
                            </td>
                            <td class="pe-4 small text-muted fst-italic" style="max-width: 250px;">
                                <?php echo $s['notes'] ?: '-'; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View -->
            <div class="d-md-none">
                <?php if(isset($data['sessions'])): foreach($data['sessions'] as $s): 
                        if(in_array($s['status'], ['partial', 'rescheduled'])) continue;
                    ?>
                <div class="mobile-session-row">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($s['date'])); ?></div>
                            <div class="small text-muted"><?php echo $s['subject_name']; ?> •
                                <?php echo $s['teacher_name']; ?></div>
                        </div>
                        <span class="st-badge st-<?php echo $s['status']; ?>"><?php echo $s['status']; ?></span>
                    </div>
                    <?php if($s['notes']): ?>
                    <div class="p-2 rounded bg-light small text-muted border-start border-3 border-primary">
                        <?php echo $s['notes']; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Exams Summary -->
            <?php if(isset($data['exams'])): ?>
            <div class="bg-light p-3 border-top">
                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:0.65rem;">Exams Managed</div>
                <div class="row g-2">
                    <?php foreach($data['exams'] as $e): ?>
                    <div class="col-6 col-lg-4">
                        <div
                            class="p-2 border rounded-3 bg-white shadow-sm d-flex justify-content-between align-items-center">
                            <div class="small fw-bold text-dark text-truncate me-1"><?php echo $e['subject_name']; ?>
                            </div>
                            <span class="badge bg-dark rounded-pill px-2" style="font-size: 0.6rem;">Score:
                                <?php echo $e['obtained_mark'] ?: 'N/A'; ?></span>
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
</div>

<?php include 'includes/footer.php'; ?>