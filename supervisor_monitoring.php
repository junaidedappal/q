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

// Search (mentor name or phone)
$searchParam = $_GET['q'] ?? '';
$searchTerm = is_scalar($searchParam) ? trim((string) $searchParam) : '';
if (function_exists('mb_substr')) {
    $searchTerm = mb_substr($searchTerm, 0, 100);
} else {
    $searchTerm = substr($searchTerm, 0, 100);
}

// Pagination
$perPageOptions = [12, 24, 48];
$perPageParam = $_GET['per_page'] ?? null;
$perPage = is_scalar($perPageParam) ? filter_var($perPageParam, FILTER_VALIDATE_INT) : false;
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = $perPageOptions[0];
}
$pageParam = $_GET['page'] ?? null;
$currentPage = is_scalar($pageParam) ? filter_var($pageParam, FILTER_VALIDATE_INT) : false;
if ($currentPage === false || $currentPage < 1) {
    $currentPage = 1;
}

$isExport = (($_GET['export'] ?? '') === 'csv');
$isPartial = (($_GET['partial'] ?? '') === '1');

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
        LEFT JOIN users sup ON m.supervisor_id = sup.id";

$whereSql = " WHERE u.status = 'active'";
$whereTypes = '';
$whereParams = [];

// Apply Role & Supervisor Filters
if ($userRole === 'supervisor') {
    $whereSql .= " AND m.supervisor_id = " . intval($userId);
} elseif ($userRole === 'admin' && $selectedSup !== '') {
    if ($selectedSup === 'null') {
        $whereSql .= " AND m.supervisor_id IS NULL";
    } else {
        $whereSql .= " AND m.supervisor_id = " . intval($selectedSup);
    }
}

// Apply Search Filter
if ($searchTerm !== '') {
    $like = '%' . addcslashes($searchTerm, '%_\\') . '%';
    $whereSql .= " AND (u.name LIKE ? OR u.phone LIKE ?)";
    $whereTypes .= 'ss';
    $whereParams[] = $like;
    $whereParams[] = $like;
}

$statTypes = 'iiiiiiii';
$statParams = [
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear
];

// Total matching mentors (for pagination)
$totalMentors = 0;
if (!$isExport) {
    $countSql = "SELECT COUNT(*) FROM mentors m JOIN users u ON m.user_id = u.id" . $whereSql;
    $countStmt = $conn->prepare($countSql);
    if ($whereTypes !== '') {
        $countStmt->bind_param($whereTypes, ...$whereParams);
    }
    $countStmt->execute();
    $totalMentors = (int) $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();
}
$totalPages = max(1, (int) ceil($totalMentors / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;

$sql .= $whereSql . " ORDER BY u.name ASC, m.id ASC";
$types = $statTypes . $whereTypes;
$params = array_merge($statParams, $whereParams);

// The CSV export contains every matching mentor; the page shows one page at a time.
if (!$isExport) {
    $sql .= " LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $perPage;
    $params[] = $offset;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$mentorsData = [];
while ($row = $result->fetch_assoc()) { $mentorsData[] = $row; }

// Current filter state, reused for export, pagination links and the browser URL
$filterParams = [
    'month' => $selectedMonth,
    'year' => $selectedYear,
];
if ($userRole === 'admin' && $selectedSup !== '') {
    $filterParams['sup_id'] = $selectedSup;
}
if ($searchTerm !== '') {
    $filterParams['q'] = $searchTerm;
}

$exportUrl = '?' . http_build_query($filterParams + ['export' => 'csv']);

$pageUrl = function (int $page) use ($filterParams, $perPage, $perPageOptions): string {
    $params = $filterParams;
    if ($perPage !== $perPageOptions[0]) {
        $params['per_page'] = $perPage;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return '?' . http_build_query($params);
};

// --- 3. CSV EXPORT LOGIC ---
if ($isExport) {
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

// --- 4. RESULTS RENDERING (full page + AJAX partial) ---
function sm_render_results(array $mentorsData, string $userRole, int $selectedMonth, int $selectedYear,
    int $totalMentors, int $currentPage, int $totalPages, int $perPage, array $perPageOptions,
    string $searchTerm, callable $pageUrl): void
{
    $from = $totalMentors > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $to = $totalMentors > 0 ? $from + count($mentorsData) - 1 : 0;
    ?>
    <div class="monitoring-section-heading">
        <div>
            <h3>Mentor performance</h3>
            <p>Session activity and student progress for the selected period.</p>
        </div>
        <span class="monitoring-count">
            <?php if ($totalMentors > 0): ?>
            <?php echo $from; ?>&ndash;<?php echo $to; ?> of <?php echo $totalMentors; ?> mentors
            <?php else: ?>
            0 mentors
            <?php endif; ?>
        </span>
    </div>

    <div class="row g-3 monitoring-page-cards" id="monitorGrid">
        <?php if(!empty($mentorsData)): ?>
        <?php foreach($mentorsData as $row): ?>
        <?php
            $mentorName = (string) $row['mentor_name'];
            $mentorPhone = (string) ($row['mentor_phone'] ?? '');
        ?>
        <div class="col-12 col-md-6 col-lg-4 monitor-item">
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
        <?php elseif ($searchTerm !== ''): ?>
        <div class="col-12">
            <div class="monitoring-empty">
                <i class="bi bi-search"></i>
                <strong>No matching mentors</strong>
                <div class="mt-1">No mentor matches &ldquo;<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>&rdquo;. Try another name or phone number.</div>
            </div>
        </div>
        <?php else: ?>
        <div class="col-12">
            <div class="monitoring-empty">
                <i class="bi bi-person-x"></i>
                <strong>No mentors found</strong>
                <div class="mt-1">No mentors are assigned or active for the selected filters.</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalMentors > 0): ?>
    <div class="monitoring-pagination">
        <label class="monitoring-per-page">
            <span>Show</span>
            <select name="per_page" form="monitorFilterForm" class="form-select form-select-sm" aria-label="Mentors per page">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?php echo (int) $opt; ?>" <?php echo $opt === $perPage ? 'selected' : ''; ?>><?php echo (int) $opt; ?></option>
                <?php endforeach; ?>
            </select>
            <span>per page</span>
        </label>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="Mentor pages">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" data-page-link href="<?php echo htmlspecialchars($pageUrl(max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php
                $window = 1;
                $pages = [1, $totalPages];
                for ($p = $currentPage - $window; $p <= $currentPage + $window; $p++) {
                    if ($p >= 1 && $p <= $totalPages) { $pages[] = $p; }
                }
                $pages = array_values(array_unique($pages));
                sort($pages);
                $prev = 0;
                foreach ($pages as $p):
                    if ($p - $prev > 1): ?>
                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                    <?php endif; ?>
                <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                    <a class="page-link" data-page-link href="<?php echo htmlspecialchars($pageUrl($p), ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $p === $currentPage ? 'aria-current="page"' : ''; ?>><?php echo $p; ?></a>
                </li>
                <?php
                    $prev = $p;
                endforeach;
                ?>
                <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" data-page-link href="<?php echo htmlspecialchars($pageUrl(min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
}

$periodLabel = $monthName . ' ' . $selectedYear . ' Performance Snapshot';

// AJAX partial: return only the results block
if ($isPartial) {
    ob_start();
    sm_render_results($mentorsData, $userRole, $selectedMonth, $selectedYear, $totalMentors,
        $currentPage, $totalPages, $perPage, $perPageOptions, $searchTerm, $pageUrl);
    $resultsHtml = ob_get_clean();
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'html' => $resultsHtml,
        'exportUrl' => $exportUrl,
        'periodLabel' => $periodLabel,
        'url' => $pageUrl($currentPage),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
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

/* Pagination */
.monitoring-pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 1.4rem;
}

.monitoring-per-page {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0;
    color: #64748b;
    font-size: 0.8rem;
}

.monitoring-per-page .form-select {
    width: auto;
    min-height: 2.1rem;
    border-color: #e2e8f0;
    border-radius: 0.6rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.monitoring-pagination .pagination {
    flex-wrap: wrap;
    gap: 0.3rem;
}

.monitoring-pagination .page-link {
    min-width: 2.1rem;
    height: 2.1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 0.6rem !important;
    color: #1e3a8a;
    background: #fff;
    font-size: 0.8rem;
    font-weight: 600;
}

.monitoring-pagination .page-link:hover {
    border-color: #bfdbfe;
    background: #eff6ff;
}

.monitoring-pagination .page-item.active .page-link {
    border-color: #1d4ed8;
    color: #fff;
    background: #1d4ed8;
}

.monitoring-pagination .page-item.disabled .page-link {
    color: #cbd5e1;
    background: #f8fafc;
}

/* Loading state */
.monitor-results {
    position: relative;
    min-height: 12rem;
}

.monitor-results.is-loading .monitoring-pagination {
    opacity: 0.45;
    pointer-events: none;
}

.monitor-progress {
    position: fixed;
    inset: 0 0 auto;
    z-index: 2000;
    height: 3px;
    overflow: hidden;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}

.monitor-progress.active {
    opacity: 1;
}

.monitor-progress::before {
    position: absolute;
    inset: 0;
    width: 40%;
    background: linear-gradient(90deg, #3b82f6, #06b6d4);
    animation: monitor-progress 1.1s ease-in-out infinite;
    content: "";
}

@keyframes monitor-progress {
    from { transform: translateX(-100%); }
    to { transform: translateX(260%); }
}

.skeleton-card {
    height: 100%;
    padding: 1.1rem;
    border: 1px solid #e8edf5;
    border-radius: 1.1rem;
    background: #fff;
    box-shadow: 0 8px 24px rgba(27, 39, 69, 0.055);
}

.skeleton-line {
    display: block;
    border-radius: 0.5rem;
    background: linear-gradient(90deg, #eef2f7 25%, #e2e8f0 37%, #eef2f7 63%);
    background-size: 400% 100%;
    animation: monitor-shimmer 1.3s ease infinite;
}

.skeleton-avatar { width: 3rem; height: 3rem; border-radius: 0.9rem; flex: 0 0 3rem; }
.skeleton-title { width: 60%; height: 0.95rem; }
.skeleton-sub { width: 40%; height: 0.7rem; margin-top: 0.5rem; }
.skeleton-stat { height: 3.4rem; border-radius: 0.85rem; }
.skeleton-btn { height: 2.65rem; margin-top: 1rem; border-radius: 0.8rem; }

@keyframes monitor-shimmer {
    from { background-position: 100% 50%; }
    to { background-position: 0 50%; }
}

.monitor-search-spinner {
    position: absolute;
    right: 1rem;
    top: 50%;
    margin-top: -0.5rem;
    color: #3b82f6;
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-line,
    .monitor-progress::before {
        animation: none;
    }
}

@media (max-width: 575.98px) {
    .monitoring-pagination {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .monitoring-pagination nav {
        display: flex;
        justify-content: center;
    }

    .monitoring-per-page {
        justify-content: center;
    }
}
</style>

<div class="monitor-progress" id="monitorProgress" aria-hidden="true"></div>

<div class="header-area mb-4 no-print">
    <div class="container">
        <!-- Top Row: Identity and Filter/Excel -->
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 text-center text-lg-start">
                <h2 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>Supervisor Monitoring</h2>
                <p class="mb-0 opacity-75 small" id="monitorPeriodLabel"><?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div class="col-lg-6">
                <!-- Main Wrapper: Stacks on mobile, aligns right on desktop -->
                <div
                    class="d-flex flex-column flex-lg-row justify-content-center justify-content-lg-end align-items-stretch align-items-lg-center gap-2">

                    <!-- Export Button: Full width on mobile -->
                    <a href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>" id="monitorExportBtn"
                        class="btn btn-success fw-bold shadow-sm d-flex align-items-center justify-content-center"
                        aria-label="Export monitoring report as CSV">
                        <i class="bi bi-file-earmark-excel me-2"></i> EXPORT
                    </a>

                    <!-- Filter Form: Uses a grid to stay organized on mobile -->
                    <form method="GET" id="monitorFilterForm" class="bg-white p-2 shadow-sm border">
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
                                <select name="month" data-default="<?php echo (int) date('n'); ?>" class="form-select form-select-sm border-0 fw-bold bg-transparent"
                                    aria-label="Select month">
                                    <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($m==$selectedMonth?'selected':'').">".date('M', mktime(0,0,0,$m,10))."</option>"; ?>
                                </select>
                            </div>

                            <div class="col-6 col-sm-auto">
                                <select name="year" data-default="<?php echo $currentYear; ?>" class="form-select form-select-sm border-0 fw-bold bg-transparent"
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
            <input type="search" id="monitorSearch" name="q" form="monitorFilterForm"
                class="form-control search-input-pill shadow-sm" autocomplete="off" maxlength="100"
                value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Search by mentor name or phone..." aria-label="Search mentors by name or phone">
            <span class="spinner-border spinner-border-sm monitor-search-spinner d-none" id="monitorSearchSpinner"
                role="status" aria-hidden="true"></span>
        </div>
    </div>
</div>

<div class="container pb-5">
    <div id="monitorResults" class="monitor-results" aria-live="polite">
        <?php sm_render_results($mentorsData, $userRole, $selectedMonth, $selectedYear, $totalMentors,
            $currentPage, $totalPages, $perPage, $perPageOptions, $searchTerm, $pageUrl); ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function () {
    const form = document.getElementById('monitorFilterForm');
    const results = document.getElementById('monitorResults');
    const search = document.getElementById('monitorSearch');
    const searchSpinner = document.getElementById('monitorSearchSpinner');
    const exportBtn = document.getElementById('monitorExportBtn');
    const periodLabel = document.getElementById('monitorPeriodLabel');
    const progress = document.getElementById('monitorProgress');
    const applyBtn = form.querySelector('button[type="submit"]');
    const applyBtnHtml = applyBtn.innerHTML;

    let controller = null;
    let debounceTimer = null;
    let currentQuery = normalise(new URLSearchParams(window.location.search));

    // Drop empty values / defaults so URLs stay clean and comparable
    function normalise(params) {
        const out = new URLSearchParams();
        params.forEach(function (value, key) {
            if (key === 'partial' || key === 'export') return;
            if (value === '' || (key === 'page' && value === '1') || (key === 'per_page' && value === '12')) return;
            out.set(key, value);
        });
        return out.toString();
    }

    function formQuery() {
        const params = new URLSearchParams(new FormData(form));
        params.set('q', search.value.trim());
        return normalise(params);
    }

    function skeletonCards(count) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += '<div class="col-12 col-md-6 col-lg-4"><div class="skeleton-card" aria-hidden="true">' +
                '<div class="d-flex align-items-center gap-3"><span class="skeleton-line skeleton-avatar"></span>' +
                '<div class="flex-grow-1"><span class="skeleton-line skeleton-title"></span><span class="skeleton-line skeleton-sub"></span></div></div>' +
                '<div class="stats-grid">' +
                '<span class="skeleton-line skeleton-stat"></span><span class="skeleton-line skeleton-stat"></span>' +
                '<span class="skeleton-line skeleton-stat"></span><span class="skeleton-line skeleton-stat"></span>' +
                '</div><span class="skeleton-line skeleton-btn"></span></div></div>';
        }
        return html;
    }

    function setLoading(on, fromSearch) {
        results.classList.toggle('is-loading', on);
        results.setAttribute('aria-busy', on ? 'true' : 'false');
        progress.classList.toggle('active', on);
        searchSpinner.classList.toggle('d-none', !(on && fromSearch));
        applyBtn.disabled = on;
        applyBtn.innerHTML = on
            ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Loading'
            : applyBtnHtml;

        if (on) {
            const grid = document.getElementById('monitorGrid');
            if (grid) {
                const shown = grid.querySelectorAll('.monitor-item').length;
                grid.innerHTML = skeletonCards(Math.min(Math.max(shown, 3), 6));
            }
        }
    }

    function showError(previousHtml) {
        results.innerHTML = '<div class="alert alert-danger d-flex align-items-center justify-content-between gap-2 mb-3" role="alert">' +
            '<span><i class="bi bi-exclamation-triangle me-2"></i>Could not load mentors. Check your connection and try again.</span>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" data-retry>Retry</button></div>' + previousHtml;
    }

    function syncForm(params) {
        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.tagName === 'SELECT' && el.name && el.name !== 'per_page') {
                el.value = params.has(el.name) ? params.get(el.name) : (el.dataset.default || '');
            }
        });
        search.value = params.get('q') || '';
    }

    function load(query, opts) {
        opts = opts || {};
        if (controller) controller.abort();
        const myController = new AbortController();
        controller = myController;

        const previousHtml = results.innerHTML;
        const scrollTarget = results.getBoundingClientRect().top < 0;
        setLoading(true, opts.fromSearch);

        const requestParams = new URLSearchParams(query);
        requestParams.set('partial', '1');

        fetch(window.location.pathname + '?' + requestParams.toString(), {
            signal: myController.signal,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                results.innerHTML = data.html;
                exportBtn.href = data.exportUrl;
                periodLabel.textContent = data.periodLabel;
                currentQuery = normalise(new URLSearchParams(data.url.replace(/^\?/, '')));
                if (opts.push !== false) {
                    const url = window.location.pathname + (currentQuery ? '?' + currentQuery : '');
                    window.history.pushState({ monitor: true }, '', url);
                }
                if (scrollTarget) {
                    results.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                showError(previousHtml);
            })
            .finally(function () {
                if (controller === myController) {
                    controller = null;
                    setLoading(false, false);
                }
            });
    }

    // Filter form: Apply button / Enter key
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        load(formQuery());
    });

    // Dropdowns apply immediately
    form.addEventListener('change', function (e) {
        if (e.target.tagName === 'SELECT') {
            clearTimeout(debounceTimer);
            load(formQuery());
        }
    });

    // Per-page select lives in the results block (linked to the form via form="")
    results.addEventListener('change', function (e) {
        if (e.target.name === 'per_page') {
            clearTimeout(debounceTimer);
            load(formQuery());
        }
    });

    // Search: wait until typing pauses
    search.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            const query = formQuery();
            if (query !== currentQuery) load(query, { fromSearch: true });
        }, 400);
    });

    // Pagination links + retry
    results.addEventListener('click', function (e) {
        const retry = e.target.closest('[data-retry]');
        if (retry) {
            load(currentQuery, { push: false });
            return;
        }
        const link = e.target.closest('a[data-page-link]');
        if (!link) return;
        e.preventDefault();
        if (link.closest('.page-item.disabled, .page-item.active')) return;
        load(normalise(new URLSearchParams(link.search)));
    });

    // Browser back / forward
    window.addEventListener('popstate', function () {
        const params = new URLSearchParams(window.location.search);
        syncForm(params);
        load(normalise(params), { push: false });
    });
})();
</script>
