<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    header('location: login.php'); exit;
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['id'];

// --- 1. FILTERS ---
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedSup = $_GET['sup_id'] ?? ''; // New Supervisor Filter
$monthName = date('F', mktime(0, 0, 0, (int)$selectedMonth, 10));

// Fetch Supervisors for Admin Filter
$supervisors = [];
if ($userRole === 'admin') {
    $sup_res = $conn->query("SELECT id, name FROM users WHERE role IN ('supervisor', 'admin') AND status = 'active' ORDER BY name ASC");
    while($s = $sup_res->fetch_assoc()) { $supervisors[] = $s; }
}

// --- 2. DATA QUERY ---
$sql = "SELECT 
            m.id as mentor_id, 
            u.name as mentor_name, 
            u.phone as mentor_phone,
            m.supervisor_id,
            sup.name as supervisor_name,
            (SELECT COUNT(*) FROM schedules WHERE mentor_id = m.id AND status = 'active') as active_students,
            (SELECT COUNT(*) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as count_done,
            (SELECT COUNT(*) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'planned' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as count_planned,
            (SELECT COUNT(*) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND sess.status = 'missed' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) as count_missed,
            (SELECT COUNT(*) FROM schedule_exams ex JOIN schedules sch ON ex.schedule_id = sch.id 
             WHERE sch.mentor_id = m.id AND MONTH(ex.exam_date) = ? AND YEAR(ex.exam_date) = ?) as count_exams
        FROM mentors m
        JOIN users u ON m.user_id = u.id
        LEFT JOIN users sup ON m.supervisor_id = sup.id
        WHERE u.status = 'active'";

// Apply Role & Supervisor Filters
if ($userRole === 'supervisor') {
    $sql .= " AND m.supervisor_id = " . intval($userId);
} elseif ($userRole === 'admin' && $selectedSup !== '') {
    if ($selectedSup === 'null') {
        $sql .= " AND m.supervisor_id IS NULL";
    } else {
        $sql .= " AND m.supervisor_id = " . intval($selectedSup);
    }
}

$sql .= " ORDER BY u.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiiiiiii', 
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear
);
$stmt->execute();
$result = $stmt->get_result();

$mentorsData = [];
while ($row = $result->fetch_assoc()) { $mentorsData[] = $row; }

// --- 3. CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Monitoring_Report_'.$monthName.'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // Excel UTF-8 BOM
    fputcsv($output, ['Mentor Name', 'Phone', 'Supervisor', 'Active Students', 'Done', 'Planned', 'Missed', 'Exams'], ",", "\"", "\\");
    foreach ($mentorsData as $m) {
        fputcsv($output, [
            $m['mentor_name'], $m['mentor_phone'], $m['supervisor_name'] ?? 'N/A',
            $m['active_students'], $m['count_done'], $m['count_planned'], 
            $m['count_missed'], $m['count_exams']
        ], ",", "\"", "\\");
    }
    fclose($output); exit;
}
?>

<style>
body {
    background-color: #f1f5f9;
    font-family: 'Inter', sans-serif;
}

/* Professional Blue Header */
.header-area {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border-radius: 0 0 20px 20px;
    padding: 2rem 1rem;
    box-shadow: 0 10px 30px rgba(78, 115, 223, 0.15);
}

.mentor-card {
    border: none;
    border-radius: 24px;
    transition: all 0.3s ease;
    background: white;
    overflow: hidden;
}

.mentor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
}

/* Stats 2x2 Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 12px 5px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}

.stat-val {
    display: block;
    font-size: 1.2rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 2px;
    font-family: 'Roboto Mono', monospace;
}

.stat-lbl {
    font-size: 0.6rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.search-input-pill {
    border-radius: 50px;
    padding: 0.75rem 1.5rem 0.75rem 3.5rem;
    border: none;
    font-size: 1rem;
}

.search-icon-inside {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.2rem;
}

@media (max-width: 768px) {
    .header-area {
        border-radius: 0;
        padding: 1.5rem 1rem;
    }

    .flex-mobile-center {
        justify-content: center !important;
    }
}
</style>

<div class="header-area mb-4 no-print">
    <div class="container">
        <!-- Top Row: Identity and Filter/Excel -->
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 text-center text-lg-start">
                <h2 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Monitoring</h2>
                <p class="mb-0 opacity-75 small"><?php echo "$monthName $selectedYear"; ?> Performance Snapshot</p>
            </div>
            <div class="col-lg-6">
                <!-- Main Wrapper: Stacks on mobile, aligns right on desktop -->
                <div
                    class="d-flex flex-column flex-md-row justify-content-center justify-content-lg-end align-items-stretch align-items-md-center gap-2">

                    <!-- Export Button: Full width on mobile -->
                    <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv"
                        class="btn btn-success fw-bold shadow-sm d-flex align-items-center justify-content-center"
                        style="border-radius: 12px; height: 45px;">
                        <i class="bi bi-file-earmark-excel me-2"></i> EXPORT
                    </a>

                    <!-- Filter Form: Uses a grid to stay organized on mobile -->
                    <form method="GET" class="bg-white p-2 shadow-sm border" style="border-radius: 15px;">
                        <div class="row g-1 align-items-center">

                            <!-- ADMIN ONLY: Supervisor Select (Full width on mobile) -->
                            <?php if ($userRole === 'admin'): ?>
                            <div class="col-12 col-sm-auto border-sm-end pe-sm-2 me-sm-2">
                                <div class="input-group input-group-sm border-0">
                                    <span class="input-group-text bg-transparent border-0 pe-0"><i
                                            class="bi bi-person-badge text-primary"></i></span>
                                    <select name="sup_id" class="form-select border-0 fw-bold bg-transparent"
                                        style="min-width: 140px; cursor:pointer;">
                                        <option value="">All Supervisors</option>
                                        <option value="null" <?php echo ($selectedSup === 'null' ? 'selected' : ''); ?>>
                                            Not Assigned</option>
                                        <?php foreach($supervisors as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"
                                            <?php echo ($selectedSup == $s['id'] ? 'selected' : ''); ?>>
                                            <?php echo htmlspecialchars($s['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Date Selectors: Side-by-side on small screens -->
                            <div class="col-6 col-sm-auto">
                                <select name="month" class="form-select form-select-sm border-0 fw-bold bg-transparent">
                                    <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                                </select>
                            </div>

                            <div class="col-6 col-sm-auto">
                                <select name="year" class="form-select form-select-sm border-0 fw-bold bg-transparent">
                                    <?php for($y=2024;$y<=date('Y');$y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
                                </select>
                            </div>

                            <!-- Go Button -->
                            <div class="col-12 col-sm-auto pt-2 pt-sm-0">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold px-3 rounded-pill"
                                    style="height: 32px;">
                                    GO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Search Bar Row -->
        <div class="mt-4 position-relative">
            <i class="bi bi-search search-icon-inside"></i>
            <input type="text" id="monitorSearch" class="form-control search-input-pill shadow-sm"
                placeholder="Search by mentor name or phone...">
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="row g-4" id="monitorGrid">
        <?php if(!empty($mentorsData)): ?>
        <?php foreach($mentorsData as $row): ?>
        <div class="col-12 col-md-6 col-lg-4 monitor-item"
            data-search="<?php echo strtolower($row['mentor_name'].' '.$row['mentor_phone']); ?>">
            <div class="card mentor-card shadow-sm h-100">
                <div class="card-body p-4">
                    <!-- Card Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center text-start">
                            <div class="user-avatar me-3 bg-primary text-white d-flex align-items-center justify-content-center"
                                style="width:50px; height:50px; border-radius: 14px; font-weight: 800; font-size: 1.2rem;">
                                <?php echo strtoupper(substr($row['mentor_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><?php echo $row['mentor_name']; ?></h6>
                                <div class="text-muted small mt-1"><i class="bi bi-telephone-fill me-1"
                                        style="font-size:0.7rem;"></i><?php echo $row['mentor_phone']; ?></div>
                            </div>
                        </div>
                        <span class="badge bg-soft-primary text-primary rounded-pill border"
                            style="font-size: 0.65rem; padding: 5px 10px;">
                            <?php echo $row['active_students']; ?> Students
                        </span>
                    </div>

                    <?php if($userRole === 'admin'): ?>
                    <div class="small text-muted mb-2 border-top pt-2"><i
                            class="bi bi-person-badge me-1"></i>Supervisor: <b
                            class="text-dark"><?php echo $row['supervisor_name'] ?: 'N/A'; ?></b></div>
                    <?php endif; ?>

                    <!-- Performance Grid -->
                    <div class="stats-grid">
                        <div class="stat-item border-success-subtle" style="background: #f0fdf4;">
                            <span class="stat-lbl text-success">Done</span>
                            <span class="stat-val text-success"><?php echo $row['count_done']; ?></span>
                        </div>
                        <div class="stat-item border-warning-subtle" style="background: #fffbeb;">
                            <span class="stat-lbl text-warning">Planned</span>
                            <span class="stat-val text-warning"><?php echo $row['count_planned']; ?></span>
                        </div>
                        <div class="stat-item border-danger-subtle" style="background: #fef2f2;">
                            <span class="stat-lbl text-danger">Missed</span>
                            <span class="stat-val text-danger"><?php echo $row['count_missed']; ?></span>
                        </div>
                        <div class="stat-item border-info-subtle" style="background: #f0f9ff;">
                            <span class="stat-lbl text-info">Exams</span>
                            <span class="stat-val text-info"><?php echo $row['count_exams']; ?></span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <a href="supervisor_audit_mentor_detail.php?mentor_id=<?php echo $row['mentor_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                        class="btn btn-dark w-100 rounded-pill btn-sm fw-bold border-0 py-2 mt-3 shadow-sm">
                        Detailed Audit <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-person-x display-1 text-muted opacity-25"></i>
            <p class="text-muted mt-3">No mentors assigned or found for this month.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Advanced search logic: triggers on input (typing/pasting)
    $("#monitorSearch").on("input", function() {
        let value = $(this).val().toLowerCase().trim();
        let foundAny = false;

        $(".monitor-item").each(function() {
            let searchStr = $(this).attr("data-search") || "";
            if (searchStr.indexOf(value) > -1) {
                $(this).show();
                foundAny = true;
            } else {
                $(this).hide();
            }
        });

        // Toggle "No Results" message
        if (!foundAny) {
            if ($("#noResultsMsg").length === 0) {
                $("#monitorGrid").append(
                    '<div id="noResultsMsg" class="col-12 text-center py-5 text-muted">No mentors match your search criteria.</div>'
                );
            }
        } else {
            $("#noResultsMsg").remove();
        }
    });
});
</script>