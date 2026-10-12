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
                        -- 1. Count completed classes and independent demos
                        ((SELECT COUNT(*) FROM sessions sess JOIN schedules sch ON sess.schedule_id = sch.id
                            WHERE sch.mentor_id = m.id AND sess.status = 'done' AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?) +
                         (SELECT COUNT(*) FROM mentor_demos md JOIN students s ON md.student_id = s.id
                            WHERE s.mentor_id = m.id AND MONTH(md.demo_date) = ? AND YEAR(md.demo_date) = ?)) as total_sessions,
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
$stmt->bind_param('iiiiiiiiii',
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear, 
    $selectedMonth, $selectedYear,
    $selectedMonth, $selectedYear
);
$stmt->execute();
$result = $stmt->get_result();

$payouts = [];
$companyTotal = 0;
$companyTeacherTotal = 0;
$companyMentorTotal = 0;

while ($row = $result->fetch_assoc()) {
    $mentorOwnEarnings = ($row['total_sessions'] * $row['rate_per_session']) + ($row['total_exams'] * $row['rate_per_exam']);
    $combinedTeacherPayout = floatval($row['total_session_teacher_payout'] ?? 0) + floatval($row['total_demo_teacher_payout'] ?? 0);
    
    $row['mentor_earnings'] = $mentorOwnEarnings;
    $row['total_teacher_payout'] = $combinedTeacherPayout;
    $row['grand_lump_sum'] = $mentorOwnEarnings + $combinedTeacherPayout;
    
    if ($row['grand_lump_sum'] > 0 || $userRole === 'mentor') {
        $payouts[] = $row;
        $companyTotal += $row['grand_lump_sum'];
        $companyTeacherTotal += $row['total_teacher_payout'];
        $companyMentorTotal += $row['mentor_earnings'];
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

    fputcsv($output, ['Mentor Name', 'Phone', 'Classes & Demos', 'Exams', 'Teachers Payout (Inc. Demos)', 'Mentor Fee', 'Grand Total'], ",", "\"", "\\");

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
        fputcsv($output, [], ",", "\"", "\\");
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
    border-radius: 16px;
    padding: 1.25rem;
}

@media (min-width: 768px) {
    .quidei-header {
        padding: 2rem;
    }
}

.card-payout {
    border: none;
    border-radius: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card-payout:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}

.text-lump-sum {
    color: #059669;
    font-weight: 800;
}

.currency-box {
    display: inline-flex;
    align-items: baseline;
    justify-content: center;
    gap: 2px;
    white-space: nowrap;
    word-break: normal;
}

.currency-sym {
    font-size: 0.85em;
    font-weight: 600;
    line-height: 1;
}

.payout-stat-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.75rem 0.5rem;
    height: 100%;
}

.btn-export {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: white;
}

.btn-export:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.payout-filter {
    min-height: 42px;
}
</style>

<div class="container-fluid px-3 px-sm-4 py-3 py-sm-4">
    <!-- Header -->
    <div class="quidei-header mb-3 mb-md-4 shadow-sm">
        <div class="row gy-3 align-items-center">
            <div class="col-12 col-md-6 text-center text-md-start">
                <h3 class="fw-bold mb-1 fs-4 fs-md-3">
                    <i class="bi bi-shield-check me-2"></i>QUIDEI PAYOUTS
                </h3>
                <p class="mb-0 opacity-75 small">Consolidated Mentor & Teacher Distribution</p>
            </div>

            <div class="col-12 col-md-6">
                <div
                    class="d-flex flex-column flex-sm-row gap-2 justify-content-md-end align-items-stretch align-items-sm-center">
                    <a href="?month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&export=csv"
                        class="btn btn-sm btn-export rounded-pill px-3 py-2 text-center">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </a>

                    <form class="d-flex align-items-center gap-1 bg-white p-1 rounded-pill shadow-sm">
                        <select name="month" class="form-select form-select-sm border-0 bg-transparent ps-3 pe-4"
                            style="width: auto; min-width: 90px; cursor: pointer;">
                            <?php for($m=1; $m<=12; $m++): ?>
                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>"
                                <?php echo ($m == $selectedMonth ? 'selected' : ''); ?>>
                                <?php echo date('M', mktime(0, 0, 0, $m, 10)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>

                        <select name="year" class="form-select form-select-sm border-0 bg-transparent ps-2 pe-4"
                            style="width: auto; min-width: 85px; cursor: pointer;">
                            <?php for($y=2024; $y<=date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y == $selectedYear ? 'selected' : ''); ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>

                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3">Go</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Mentor Filter -->
    <div class="row g-2 mb-3 mb-md-4">
        <div class="col-12 col-md-7">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="search" id="payoutSearch" class="form-control payout-filter border-start-0"
                    placeholder="Search mentors..." aria-label="Search mentors">
            </div>
        </div>
        <div class="col-12 col-md-5">
            <select id="mentorFilter" class="form-select payout-filter" aria-label="Filter by mentor">
                <option value="">All mentors</option>
                <?php foreach($payouts as $p): ?>
                <option value="<?php echo (int)$p['mentor_id']; ?>"><?php echo htmlspecialchars($p['mentor_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Admin Company Total -->
    <?php if($userRole === 'admin'): ?>
    <div class="card border-0 shadow-sm rounded-3 mb-3 mb-md-4">
        <div class="card-body p-3">
            <div class="row g-3 text-center">
                <div class="col-12 col-md-4">
                    <div class="text-muted fw-semibold small text-uppercase">Teachers Payment Total</div>
                    <div class="currency-box text-dark fs-5 fw-bold mt-1">
                        <span class="currency-sym">₹</span><?php echo number_format($companyTeacherTotal, 2); ?>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-muted fw-semibold small text-uppercase">Mentors Payment Total</div>
                    <div class="currency-box text-dark fs-5 fw-bold mt-1">
                        <span class="currency-sym">₹</span><?php echo number_format($companyMentorTotal, 2); ?>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="text-muted fw-semibold small text-uppercase">Grand Total Monthly Outflow</div>
                    <div class="currency-box text-primary fs-4 fw-bold mt-1">
                        <span class="currency-sym">₹</span><?php echo number_format($companyTotal, 2); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mentor Cards Grid -->
    <div class="row g-3">
        <?php if(empty($payouts)): ?>
        <div class="col-12 text-center py-5 bg-white rounded-3 shadow-sm text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
            No payout activity found for <?php echo "$monthName $selectedYear"; ?>.
        </div>
        <?php else: ?>
        <?php foreach($payouts as $p): ?>
        <div class="col-12 col-xl-6 payout-card-item" data-mentor-id="<?php echo (int)$p['mentor_id']; ?>"
            data-mentor-name="<?php echo htmlspecialchars($p['mentor_name'], ENT_QUOTES); ?>">
            <div class="card card-payout shadow-sm h-100">
                <div class="card-body p-3 p-sm-4">

                    <!-- Mentor Info & Link -->
                    <div
                        class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3 pb-3 border-bottom">
                        <div>
                            <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($p['mentor_name']); ?></h5>
                            <!-- <small class="text-muted d-inline-block mt-1">
                                <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($p['mentor_phone']); ?>
                            </small> -->
                        </div>
                        <a href="quidei_payout_detail.php?mentor_id=<?php echo $p['mentor_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>"
                            class="btn btn-sm btn-outline-dark rounded-pill px-3  w-sm-auto text-center">
                            Detailed Breakdown <i class="bi bi-chevron-right ms-1 small"></i>
                        </a>
                    </div>

                    <!-- Numeric Stats -->
                    <div class="row g-2 text-center">
                        <div class="col-12 col-sm-4">
                            <div class="payout-stat-card">
                                <div class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Teachers
                                    Total</div>
                                <div class="currency-box text-dark fw-bold fs-6 mt-1">
                                    <span
                                        class="currency-sym">₹</span><?php echo number_format($p['total_teacher_payout'], 2); ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.65rem;">(Sessions + Demos)</div>
                            </div>
                        </div>

                        <div class="col-6 col-sm-4">
                            <div class="payout-stat-card">
                                <div class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Mentor Fee
                                </div>
                                <div class="currency-box text-dark fw-bold fs-6 mt-1">
                                    <span
                                        class="currency-sym">₹</span><?php echo number_format($p['mentor_earnings'], 2); ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.65rem;">(Classes, Demos &amp; Exams)</div>
                            </div>
                        </div>

                        <div class="col-6 col-sm-4">
                            <div class="payout-stat-card bg-emerald-subtle">
                                <div class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Grand Total
                                </div>
                                <div class="currency-box text-lump-sum fs-6 mt-1">
                                    <span
                                        class="currency-sym">₹</span><?php echo number_format($p['grand_lump_sum'], 2); ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.65rem;">(Total Distribution)</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div id="noPayoutMatches" class="d-none text-center py-4 bg-white rounded-3 shadow-sm text-muted">
        No mentors match your search.
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('payoutSearch');
    const mentorFilter = document.getElementById('mentorFilter');
    const payoutCards = document.querySelectorAll('.payout-card-item');
    const noMatches = document.getElementById('noPayoutMatches');

    function filterPayouts() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedMentor = mentorFilter.value;
        let visibleCards = 0;

        payoutCards.forEach(function(card) {
            const matchesSearch = card.dataset.mentorName.toLowerCase().includes(searchTerm);
            const matchesMentor = !selectedMentor || card.dataset.mentorId === selectedMentor;
            const isVisible = matchesSearch && matchesMentor;

            card.classList.toggle('d-none', !isVisible);
            visibleCards += isVisible ? 1 : 0;
        });

        noMatches.classList.toggle('d-none', visibleCards > 0 || payoutCards.length === 0);
    }

    searchInput.addEventListener('input', filterPayouts);
    mentorFilter.addEventListener('change', filterPayouts);
});
</script>

<?php include 'includes/footer.php'; ?>