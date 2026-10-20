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
$currentYear = (int) date('Y');
$monthParam = $_GET['month'] ?? null;
$selectedMonth = is_scalar($monthParam) ? filter_var($monthParam, FILTER_VALIDATE_INT) : false;
if ($selectedMonth === false || $selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int) date('n');
}

$yearParam = $_GET['year'] ?? null;
$selectedYear = is_scalar($yearParam) ? filter_var($yearParam, FILTER_VALIDATE_INT) : false;
if ($selectedYear === false || $selectedYear < 2024 || $selectedYear > $currentYear) {
    $selectedYear = $currentYear;
}

$supervisorParam = $_GET['sup_id'] ?? '';
$selectedSup = is_scalar($supervisorParam) ? (string) $supervisorParam : '';
if ($selectedSup !== '' && $selectedSup !== 'null' && !ctype_digit($selectedSup)) {
    $selectedSup = '';
}
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
            (SELECT COUNT(DISTINCT student_id) FROM schedules WHERE mentor_id = m.id AND status = 'active') as active_students,
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

$exportParams = [
    'month' => $selectedMonth,
    'year' => $selectedYear,
    'export' => 'csv'
];
if ($userRole === 'admin' && $selectedSup !== '') {
    $exportParams['sup_id'] = $selectedSup;
}
$exportUrl = '?' . http_build_query($exportParams);

// --- 3. CSV EXPORT LOGIC ---
if (($_GET['export'] ?? '') === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Monitoring_Report_'.$monthName.'_'.$selectedYear.'.csv');
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

<style>
body {
    background-color: #f4f7fc;
}

.header-area {
    width: calc(100% - 1.5rem);
    max-width: 1480px;
    margin: 1rem auto 1.4rem !important;
    padding: 1.35rem 0.75rem;
    border-radius: 1.35rem;
    background:
        radial-gradient(circle at 88% 0%, rgba(255, 255, 255, 0.18), transparent 32%),
        linear-gradient(125deg, #0f172a 0%, #1e3a8a 52%, #2563eb 100%);
    box-shadow: 0 18px 36px rgba(30, 64, 175, 0.2);
}

.header-area .container {
    max-width: 1360px;
}

.header-area h2 {
    font-size: clamp(1.45rem, 4vw, 2rem);
    letter-spacing: -0.04em;
}

.header-area .btn-success {
    min-height: 2.8rem;
    padding-inline: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 0.85rem !important;
    color: #fff;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.8rem;
}

.header-area .btn-success:hover,
.header-area .btn-success:focus {
    border-color: rgba(255, 255, 255, 0.42);
    color: #fff;
    background: rgba(255, 255, 255, 0.22);
}

.header-area form {
    border: 1px solid rgba(226, 232, 240, 0.9) !important;
    border-radius: 1rem !important;
}

.header-area form .form-select {
    min-height: 2.5rem;
    border-radius: 0.7rem;
    color: #273449;
    font-size: 0.82rem;
}

.header-area form .btn-primary {
    min-height: 2.5rem !important;
    border-radius: 0.7rem !important;
}

.search-input-pill {
    min-height: 2.9rem;
    padding: 0.7rem 1rem 0.7rem 2.9rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.85rem;
    background: #fff;
    font-size: 0.88rem;
}

.search-input-pill:focus {
    border-color: #93c5fd;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.14);
}

.search-icon-inside {
    left: 1rem;
    color: #8190a6;
    font-size: 1rem;
}

.monitoring-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin: 0 0 0.9rem;
}

.monitoring-section-heading h3 {
    margin: 0;
    color: #172033;
    font-size: 1.08rem;
    font-weight: 750;
    letter-spacing: -0.025em;
}

.monitoring-section-heading p {
    margin: 0.25rem 0 0;
    color: #7c879b;
    font-size: 0.8rem;
}

.monitoring-count {
    flex: 0 0 auto;
    padding: 0.4rem 0.7rem;
    border: 1px solid #dbeafe;
    border-radius: 999px;
    color: #1d4ed8;
    background: #eff6ff;
    font-size: 0.75rem;
    font-weight: 700;
}

.mentor-card {
    position: relative;
    border: 1px solid #e8edf5;
    border-radius: 1.1rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(27, 39, 69, 0.055);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.mentor-card::before {
    position: absolute;
    inset: 0 0 auto;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6, #06b6d4);
    content: "";
}

.mentor-card:hover {
    border-color: #c7d8f8;
    box-shadow: 0 15px 30px rgba(30, 64, 175, 0.1);
    transform: translateY(-3px);
}

.mentor-card .card-body {
    padding: 1.1rem;
}

.mentor-card .user-avatar {
    flex: 0 0 3rem;
    border: 1px solid #dbeafe;
    border-radius: 0.9rem !important;
    color: #1d4ed8 !important;
    background: linear-gradient(145deg, #eff6ff, #dbeafe) !important;
    box-shadow: none;
}

.mentor-card h6 {
    overflow-wrap: anywhere;
    font-size: 0.98rem;
}

.mentor-card .badge.bg-soft-primary {
    border-color: #dbeafe !important;
    color: #1d4ed8 !important;
    background: #eff6ff;
    line-height: 1.2;
}

.stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem;
    margin-top: 1rem;
}

.stat-item {
    padding: 0.75rem 0.45rem;
    border: 1px solid #edf1f6;
    border-radius: 0.85rem;
}

.stat-val {
    margin: 0.25rem 0 0;
    font-size: 1.15rem;
    font-variant-numeric: tabular-nums;
}

.stat-lbl {
    font-size: 0.63rem;
    letter-spacing: 0.06em;
}

.monitoring-supervisor {
    margin-top: 0.9rem !important;
    padding-top: 0.75rem !important;
    border-top: 1px solid #edf1f6 !important;
    font-size: 0.76rem !important;
}

.monitoring-audit-link {
    min-height: 2.65rem;
    border-radius: 0.8rem !important;
    background: #172554 !important;
    font-size: 0.8rem !important;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.monitoring-audit-link:hover,
.monitoring-audit-link:focus {
    background: #1d4ed8 !important;
    transform: translateY(-1px);
}

.monitoring-empty {
    padding: 2.5rem 1rem;
    border: 1px dashed #cbd5e1;
    border-radius: 1rem;
    color: #64748b;
    background: rgba(255, 255, 255, 0.75);
    text-align: center;
}

.monitoring-empty i {
    display: block;
    margin-bottom: 0.65rem;
    color: #94a3b8;
    font-size: 2.2rem;
}

@media (max-width: 767.98px) {
    .header-area {
        width: calc(100% - 1rem);
        padding: 1.05rem 0.25rem;
        border-radius: 1rem;
    }

    .header-area .row {
        --bs-gutter-y: 0.85rem;
    }

    .header-area .col-lg-6.text-center {
        text-align: left !important;
    }

    .header-area .btn-success {
        width: 100%;
    }

    .header-area form {
        width: 100%;
    }

    .header-area form .col-12 .form-select {
        width: 100%;
    }

    .search-input-pill {
        min-height: 2.8rem;
    }

    .monitoring-section-heading {
        align-items: flex-start;
    }

    .monitoring-page-cards {
        --bs-gutter-x: 0.85rem;
        --bs-gutter-y: 0.85rem;
    }

    .mentor-card .card-body {
        padding: 1rem;
    }
}
</style>

<div class="header-area mb-4 no-print">
    <div class="container">
        <!-- Top Row: Identity and Filter/Excel -->
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 text-center text-lg-start">
                <h2 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Supervisor Monitoring</h2>
                <p class="mb-0 opacity-75 small"><?php echo htmlspecialchars($monthName . ' ' . $selectedYear, ENT_QUOTES, 'UTF-8'); ?> Performance Snapshot</p>
            </div>
            <div class="col-lg-6">
                <!-- Main Wrapper: Stacks on mobile, aligns right on desktop -->
                <div
                    class="d-flex flex-column flex-lg-row justify-content-center justify-content-lg-end align-items-stretch align-items-lg-center gap-2">

                    <!-- Export Button: Full width on mobile -->
                    <a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        class="btn btn-success fw-bold shadow-sm d-flex align-items-center justify-content-center"
                        aria-label="Export monitoring report as CSV">
                        <i class="bi bi-file-earmark-excel me-2"></i> EXPORT
                    </a>

                    <!-- Filter Form: Uses a grid to stay organized on mobile -->
                    <form method="GET" class="bg-white p-2 shadow-sm border">
                        <div class="row g-1 align-items-center">

                            <!-- ADMIN ONLY: Supervisor Select (Full width on mobile) -->
                            <?php if ($userRole === 'admin'): ?>
                            <div class="col-12 col-sm-auto border-sm-end pe-sm-2 me-sm-2 supervisor-filter">
                                <div class="input-group input-group-sm border-0">
                                    <span class="input-group-text bg-transparent border-0 pe-0"><i
                                            class="bi bi-person-badge text-primary"></i></span>
                                    <select name="sup_id" class="form-select border-0 fw-bold bg-transparent"
                                        aria-label="Filter by supervisor">
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
                                <select name="month" class="form-select form-select-sm border-0 fw-bold bg-transparent"
                                    aria-label="Select month">
                                    <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                                </select>
                            </div>

                            <div class="col-6 col-sm-auto">
                                <select name="year" class="form-select form-select-sm border-0 fw-bold bg-transparent"
                                    aria-label="Select year">
                                    <?php for($y=2024;$y<=date('Y');$y++) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
                                </select>
                            </div>

                            <!-- Go Button -->
                            <div class="col-12 col-sm-auto pt-2 pt-sm-0">
                                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold px-3 rounded-pill">
                                    Apply Filters
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
            <input type="search" id="monitorSearch" class="form-control search-input-pill shadow-sm"
                placeholder="Search by mentor name or phone..." aria-label="Search mentors by name or phone">
        </div>
    </div>
</div>

<div class="container pb-5">
    <div class="monitoring-section-heading">
        <div>
            <h3>Mentor performance</h3>
            <p>Session activity and student progress for the selected period.</p>
        </div>
        <span class="monitoring-count"><span id="visibleMentorCount"><?php echo count($mentorsData); ?></span> mentors</span>
    </div>

    <div class="row g-3 monitoring-page-cards" id="monitorGrid">
        <?php if(!empty($mentorsData)): ?>
        <?php foreach($mentorsData as $row): ?>
        <?php
            $mentorName = (string) $row['mentor_name'];
            $mentorPhone = (string) ($row['mentor_phone'] ?? '');
            $searchText = strtolower($mentorName . ' ' . $mentorPhone);
        ?>
        <div class="col-12 col-md-6 col-lg-4 monitor-item"
            data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card mentor-card shadow-sm h-100">
                <div class="card-body p-4">
                    <!-- Card Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center text-start">
                            <div class="user-avatar me-3 bg-primary text-white d-flex align-items-center justify-content-center"
                                style="width:50px; height:50px; border-radius: 14px; font-weight: 800; font-size: 1.2rem;">
                                <?php echo htmlspecialchars(strtoupper(substr($mentorName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($mentorName, ENT_QUOTES, 'UTF-8'); ?></h6>
                                <div class="text-muted small mt-1"><i class="bi bi-telephone-fill me-1"
                                        style="font-size:0.7rem;"></i><?php echo htmlspecialchars($mentorPhone ?: 'No phone on file', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <span class="badge bg-soft-primary text-primary rounded-pill border"
                            style="font-size: 0.65rem; padding: 5px 10px;">
                            <?php echo (int) $row['active_students']; ?> Active Students
                        </span>
                    </div>

                    <?php if($userRole === 'admin'): ?>
                    <div class="small text-muted mb-2 border-top pt-2"><i
                            class="bi bi-person-badge me-1"></i>Supervisor: <b
                            class="text-dark"><?php echo htmlspecialchars($row['supervisor_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></b></div>
                    <?php endif; ?>

                    <!-- Performance Grid -->
                    <div class="stats-grid">
                        <div class="stat-item border-success-subtle" style="background: #f0fdf4;">
                            <span class="stat-lbl text-success">Done</span>
                            <span class="stat-val text-success"><?php echo (int) $row['count_done']; ?></span>
                        </div>
                        <div class="stat-item border-warning-subtle" style="background: #fffbeb;">
                            <span class="stat-lbl text-warning">Planned</span>
                            <span class="stat-val text-warning"><?php echo (int) $row['count_planned']; ?></span>
                        </div>
                        <div class="stat-item border-danger-subtle" style="background: #fef2f2;">
                            <span class="stat-lbl text-danger">Missed</span>
                            <span class="stat-val text-danger"><?php echo (int) $row['count_missed']; ?></span>
                        </div>
                        <div class="stat-item border-info-subtle" style="background: #f0f9ff;">
                            <span class="stat-lbl text-info">Exams</span>
                            <span class="stat-val text-info"><?php echo (int) $row['count_exams']; ?></span>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <a href="supervisor_audit_mentor_detail.php?mentor_id=<?php echo (int) $row['mentor_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                        class="btn btn-dark w-100 rounded-pill btn-sm fw-bold border-0 py-2 mt-3 shadow-sm monitoring-audit-link">
                        Detailed Audit <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="col-12">
            <div class="monitoring-empty">
                <i class="bi bi-person-x"></i>
                <strong>No mentors found</strong>
                <div class="mt-1">No mentors are assigned or active for the selected filters.</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if(!empty($mentorsData)): ?>
        <div id="noResultsMsg" class="col-12 d-none">
            <div class="monitoring-empty">
                <i class="bi bi-search"></i>
                <strong>No matching mentors</strong>
                <div class="mt-1">Try another name or phone number.</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const $mentorItems = $(".monitor-item");

    $("#monitorSearch").on("input", function() {
        let value = $(this).val().toLowerCase().trim();
        let visibleCount = 0;

        $mentorItems.each(function() {
            let searchStr = $(this).attr("data-search") || "";
            if (searchStr.indexOf(value) > -1) {
                $(this).show();
                visibleCount += 1;
            } else {
                $(this).hide();
            }
        });

        $("#visibleMentorCount").text(visibleCount);
        $("#noResultsMsg").toggleClass("d-none", visibleCount > 0 || $mentorItems.length === 0);
    });
});
</script>