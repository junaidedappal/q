<?php
ob_start();
include 'includes/header.php';

if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'admin') {
    ob_end_clean();
    header('Location: dashboard.php');
    exit;
}

require 'includes/db.php';

function mv_h($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$years = [];
$res = $conn->query(
    'SELECT DISTINCT YEAR(created_at) AS year FROM students
     WHERE created_at IS NOT NULL AND YEAR(created_at) BETWEEN 1000 AND 9998
     ORDER BY year DESC'
);
while ($res && ($r = $res->fetch_assoc())) {
    $years[] = (int) $r['year'];
}

$year = null;
$yearInput = $_GET['year'] ?? '';
$allYears = is_string($yearInput) && $yearInput === 'all';
if (!$allYears && is_string($yearInput) && preg_match('/^[0-9]{4}$/D', $yearInput) === 1 && in_array((int) $yearInput, $years, true)) {
    $year = (int) $yearInput;
} elseif (!$allYears && $years) {
    $year = $years[0];
}
$hasYearSelection = $allYears || $year !== null;

$scheduleFilterInput = $_GET['schedule_filter'] ?? '';
$scheduleFilter = in_array($scheduleFilterInput, ['new_needed', 'new_admission'], true)
    ? $scheduleFilterInput
    : 'all';
$supervisorInput = $_GET['supervisor_id'] ?? '';
if (!is_string($supervisorInput) || ($supervisorInput !== '' && !ctype_digit($supervisorInput))) {
    http_response_code(400);
    exit('Choose a valid supervisor.');
}
$supervisorId = $supervisorInput === '' ? 0 : (int) $supervisorInput;
$mentorInput = $_GET['mentor_id'] ?? '';
$mentorUnassigned = $mentorInput === 'none';
if (!is_string($mentorInput) || ($mentorInput !== '' && !$mentorUnassigned && !ctype_digit($mentorInput))) {
    http_response_code(400);
    exit('Choose a valid mentor.');
}
$mentorId = ($mentorInput === '' || $mentorUnassigned) ? 0 : (int) $mentorInput;
$mentorUnassignedFlag = $mentorUnassigned ? 1 : 0;

// Advanced filters (same options and rules as the Admissions page).
function mv_choice(string $key, array $allowed): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) && in_array($value, $allowed, true) ? $value : '';
}

$gradeResult = $conn->query(
    "SELECT DISTINCT class FROM students
     WHERE COALESCE(status, '') <> 'enquiry' AND class IS NOT NULL AND class <> ''
     ORDER BY class ASC"
);
$grades = [];
while ($gradeResult && ($g = $gradeResult->fetch_assoc())) {
    $grades[] = (string) $g['class'];
}

// Month filter: months that appear in any schedule start or end date (YYYY-MM).
$monthResult = $conn->query(
    "SELECT ym FROM (
        SELECT DATE_FORMAT(start_date, '%Y-%m') AS ym FROM schedules WHERE start_date >= '1000-01-01'
        UNION
        SELECT DATE_FORMAT(end_date, '%Y-%m') AS ym FROM schedules WHERE end_date >= '1000-01-01'
     ) months
     ORDER BY ym DESC"
);
$monthOptions = [];
while ($monthResult && ($mo = $monthResult->fetch_assoc())) {
    $ym = (string) $mo['ym'];
    if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $ym) === 1) {
        $monthOptions[$ym] = date('F Y', strtotime($ym . '-01'));
    }
}
$monthFilter = mv_choice('month', array_map('strval', array_keys($monthOptions)));

$studentStatusOptions = ['active' => 'Active', 'ready' => 'Ready', 'break' => 'Break', 'inactive' => 'Inactive'];
$feeOptions = ['paid' => 'Paid', 'partial' => 'Partial', 'pending' => 'Pending', 'no_schedule' => 'No Schedule'];
$examOptions = ['done' => 'Completed', 'pending' => 'Pending'];
$progressOptions = ['completed' => 'Fully Completed', 'pending' => 'In Progress', 'no_sch' => 'No Schedule'];
$checklistOptions = ['1' => 'Created', '0' => 'Pending'];

$gradeFilter = mv_choice('grade', $grades);
$studentStatusFilter = mv_choice('student_status', array_keys($studentStatusOptions));
$feeFilter = mv_choice('fee', array_keys($feeOptions));
$examFilter = mv_choice('exam', array_keys($examOptions));
$progressFilter = mv_choice('progress', array_keys($progressOptions));
$checklistFilters = [
    'checklist_material' => mv_choice('material', ['0', '1']),
    'checklist_group' => mv_choice('wa_group', ['0', '1']),
    'checklist_teams' => mv_choice('teams', ['0', '1']),
];
$advancedFilterCount = count(array_filter(
    array_merge([$gradeFilter, $studentStatusFilter, $feeFilter, $examFilter, $progressFilter], array_values($checklistFilters)),
    static fn($v) => $v !== ''
));

$supervisorResult = $conn->query(
    'SELECT DISTINCT sup.id, sup.name
     FROM mentors m
     JOIN users sup ON sup.id = m.supervisor_id
     ORDER BY sup.name ASC'
);
if (!$supervisorResult) {
    error_log('Master view supervisor filter query failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load supervisor filters.');
}
$supervisors = $supervisorResult->fetch_all(MYSQLI_ASSOC);

$mentorResult = $conn->query(
    'SELECT m.id, u.name
     FROM mentors m
     JOIN users u ON u.id = m.user_id
     ORDER BY u.name ASC'
);
if (!$mentorResult) {
    error_log('Master view mentor filter query failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load mentor filters.');
}
$mentors = $mentorResult->fetch_all(MYSQLI_ASSOC);

$students = [];
$totals = ['fee' => 0.0, 'salary' => 0.0, 'mentor' => 0.0, 'payout' => 0.0, 'balance' => 0.0, 'paid' => 0.0, 'total_hours' => 0.0, 'completed' => 0.0];

if ($hasYearSelection) {
    if (!$allYears) {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-01-01', $year + 1);
    }
    $subqueryYearFilter = $allYears ? '' : ' AND st.created_at >= ? AND st.created_at < ?';
    $studentYearFilter = $allYears ? '' : ' AND s.created_at >= ? AND s.created_at < ?';

    $stmt = $conn->prepare(
        "SELECT
            s.id AS student_id, s.name, s.status, s.fee_status, s.syllabus, s.class,
            s.language, s.country, s.phone, s.parent_phone,
            COALESCE(s.checklist_material, 0) AS checklist_material,
            COALESCE(s.checklist_group, 0) AS checklist_group,
            COALESCE(s.checklist_teams, 0) AS checklist_teams,
            COALESCE(u.name, '') AS mentor_name,
            COALESCE(sup.name, '') AS supervisor_name,
            COALESCE(m.rate_per_session, 0) AS rate_per_session,
            COALESCE(m.rate_per_exam, 0) AS rate_per_exam,
            COALESCE(attendance_totals.absent_count, 0) AS absent_count,
            sch.id AS schedule_id,
            CASE WHEN sch.id = (
                SELECT MIN(first_sch.id) FROM schedules first_sch WHERE first_sch.student_id = s.id
            ) THEN 1 ELSE 0 END AS is_first_schedule,
            sch.start_date, sch.end_date, sch.paid_hours,
            sch.total_hours, sch.fee_amount,
            COALESCE(session_totals.completed_hours, 0) AS completed_hours,
            COALESCE(session_totals.teacher_salary, 0) AS teacher_salary,
            COALESCE(session_totals.completed_sessions, 0) AS completed_sessions,
            COALESCE(exam_totals.exam_count, 0) AS exam_count
         FROM students s
         LEFT JOIN mentors m ON s.mentor_id = m.id
         LEFT JOIN users u ON m.user_id = u.id
         LEFT JOIN users sup ON m.supervisor_id = sup.id
         LEFT JOIN (
            SELECT a.student_id, COUNT(*) AS absent_count
            FROM attendance a
            INNER JOIN students st ON st.id = a.student_id
            WHERE a.attended = 0
              $subqueryYearFilter
              AND COALESCE(st.status, '') <> 'enquiry'
            GROUP BY a.student_id
         ) attendance_totals ON attendance_totals.student_id = s.id
         LEFT JOIN schedules sch ON sch.student_id = s.id
         LEFT JOIN (
            SELECT sess.schedule_id,
                   SUM(sess.planned_hours) AS completed_hours,
                   SUM(sess.planned_hours * sess.teacher_pay) AS teacher_salary,
                   COUNT(*) AS completed_sessions
            FROM sessions sess
            INNER JOIN schedules ss ON ss.id = sess.schedule_id
            INNER JOIN students st ON st.id = ss.student_id
            WHERE sess.status = 'done'
              $subqueryYearFilter
              AND COALESCE(st.status, '') <> 'enquiry'
            GROUP BY sess.schedule_id
         ) session_totals ON session_totals.schedule_id = sch.id
         LEFT JOIN (
            SELECT ex.schedule_id, COUNT(*) AS exam_count
            FROM schedule_exams ex
            INNER JOIN schedules es ON es.id = ex.schedule_id
            INNER JOIN students st ON st.id = es.student_id
            WHERE 1 = 1
              $subqueryYearFilter
              AND COALESCE(st.status, '') <> 'enquiry'
            GROUP BY ex.schedule_id
         ) exam_totals ON exam_totals.schedule_id = sch.id
         WHERE 1 = 1
           $studentYearFilter
           AND COALESCE(s.status, '') <> 'enquiry'
           AND (? = 0 OR m.supervisor_id = ?)
           AND (? = 0 OR s.mentor_id = ?)
           AND (? = 0 OR s.mentor_id IS NULL)
         ORDER BY s.name ASC, s.id ASC, sch.id ASC"
    );
    if ($allYears) {
        $stmt->bind_param('iiiii', $supervisorId, $supervisorId, $mentorId, $mentorId, $mentorUnassignedFlag);
    } else {
        $stmt->bind_param(
            'ssssssssiiiii',
            $start,
            $end,
            $start,
            $end,
            $start,
            $end,
            $start,
            $end,
            $supervisorId,
            $supervisorId,
            $mentorId,
            $mentorId,
            $mentorUnassignedFlag
        );
    }
    $stmt->execute();
    $rows = $stmt->get_result();

    while ($rec = $rows->fetch_assoc()) {
        // Student-level advanced filters
        if ($gradeFilter !== '' && (string) $rec['class'] !== $gradeFilter) {
            continue;
        }
        if ($studentStatusFilter !== '' && (string) $rec['status'] !== $studentStatusFilter) {
            continue;
        }
        foreach ($checklistFilters as $column => $wanted) {
            if ($wanted !== '' && (string) (int) $rec[$column] !== $wanted) {
                continue 2;
            }
        }

        $paidHours = (float) ($rec['paid_hours'] ?? 0);
        $completedHours = (float) $rec['completed_hours'];
        $totalHours = (float) ($rec['total_hours'] ?? 0);

        // Month filter: schedule start date or end date falls in the selected month
        if ($monthFilter !== '') {
            $startMonth = substr((string) ($rec['start_date'] ?? ''), 0, 7);
            $endMonth = substr((string) ($rec['end_date'] ?? ''), 0, 7);
            if ($startMonth !== $monthFilter && $endMonth !== $monthFilter) {
                continue;
            }
        }

        // Schedule-level advanced filters
        if ($feeFilter !== '') {
            $feeState = $totalHours <= 0 ? 'no_schedule'
                : ($paidHours >= $totalHours ? 'paid' : ($paidHours > 0 ? 'partial' : 'pending'));
            if ($feeState !== $feeFilter) {
                continue;
            }
        }
        if ($examFilter !== '' && ((int) $rec['exam_count'] > 0 ? 'done' : 'pending') !== $examFilter) {
            continue;
        }
        if ($progressFilter !== '') {
            $progressState = $totalHours <= 0 ? 'no_sch' : ($completedHours >= $totalHours ? 'completed' : 'pending');
            if ($progressState !== $progressFilter) {
                continue;
            }
        }

        if ($scheduleFilter === 'new_needed' && ($paidHours <= 0 || abs($paidHours - $completedHours) > 0.00001)) {
            continue;
        }
        if ($scheduleFilter === 'new_admission'
            && ((int) $rec['is_first_schedule'] !== 1 || abs($completedHours) > 0.00001)) {
            continue;
        }

        $sid = (int) $rec['student_id'];
        if (!isset($students[$sid])) {
            $students[$sid] = ['info' => $rec, 'schedules' => []];
        }
        if ($rec['schedule_id'] === null) {
            continue;
        }
        $salary = (float) $rec['teacher_salary'];
        $mentorFee = ((int) $rec['completed_sessions'] * (float) $rec['rate_per_session'])
            + ((int) $rec['exam_count'] * (float) $rec['rate_per_exam']);
        $fee = (float) ($rec['fee_amount'] ?? 0);
        $sched = [
            'start' => $rec['start_date'], 'end' => $rec['end_date'],
            'paid' => $paidHours,
            'total' => $totalHours,
            'completed' => $completedHours,
            'fee' => $fee, 'salary' => $salary, 'mentor' => $mentorFee,
            'payout' => $salary + $mentorFee,
            'balance' => $fee - ($salary + $mentorFee),
        ];
        $students[$sid]['schedules'][] = $sched;
        $totals['fee'] += $fee;
        $totals['salary'] += $salary;
        $totals['mentor'] += $mentorFee;
        $totals['payout'] += $sched['payout'];
        $totals['balance'] += $sched['balance'];
        $totals['paid'] += $sched['paid'];
        $totals['total_hours'] += $sched['total'];
        $totals['completed'] += $sched['completed'];
    }
    $stmt->close();
}

$headers = [
    'SI NO', 'STUDENT NAME', 'COUNTRY', 'STATUS', 'ACADEMIC MENTOR', 'SUPERVISOR', 'FEE STATUS', 'COMPLETED HRS', 'PAID HRS',
    'ABSENT', 'SYLLABUS', 'GRADE', 'LANGUAGE', 'STUDENT PHONE', 'PARENT PHONE',
    'SCHEDULE', 'START', 'END', 'TOTAL HRS',
    'FEE AMOUNT (₹)', 'TEACHER SALARY (₹)', 'MENTOR FEE (₹)', 'TOTAL PAYOUT (₹)', 'BALANCE-PROFIT (₹)',
];
$numericCols = [7, 8, 9, 18, 19, 20, 21, 22, 23];

// Yields table rows as arrays of cells; student columns only on a student's first row.
function mv_rows(array $students): Generator
{
    $n = 0;
    foreach ($students as $sid => $st) {
        $n++;
        $i = $st['info'];
        $studentCells = [
            $n, $i['name'], $i['country'], $i['status'], $i['mentor_name'], $i['supervisor_name'], $i['fee_status'],
            (int) $i['absent_count'], $i['syllabus'], $i['class'], $i['language'], $i['phone'], $i['parent_phone'],
        ];
        $scheds = $st['schedules'] ?: [null];
        foreach ($scheds as $k => $s) {
            $cells = $k === 0 ? array_slice($studentCells, 0, 7) : array_fill(0, 7, '');
            $cells[] = $s === null ? '' : $s['completed'];
            $cells[] = $s === null ? '' : $s['paid'];
            $cells = array_merge($cells, $k === 0 ? array_slice($studentCells, 7) : array_fill(0, 6, ''));
            if ($s === null) {
                $cells = array_merge($cells, array_fill(0, 9, ''));
            } else {
                array_push(
                    $cells, $k + 1, $s['start'], $s['end'],
                    $s['total'], $s['fee'], $s['salary'], $s['mentor'], $s['payout'], $s['balance']
                );
            }
            yield [$k === 0, $cells];
        }
    }
}

function mv_cell($v, bool $numeric, bool $currency = false): string
{
    if ($numeric && $v !== '' && $v !== null) {
        return ($currency ? '₹ ' : '') . number_format((float) $v, is_int($v) ? 0 : 2);
    }
    return mv_h($v);
}

function mv_csv_text($value): string
{
    $text = (string) ($value ?? '');
    if (preg_match('/^[\t\r\n ]*[=+\-@]/', $text) === 1) {
        return "'" . $text;
    }

    return $text;
}

if ($hasYearSelection && ($_GET['download'] ?? '') === '1') {
    ob_end_clean();
    $output = fopen('php://temp/maxmemory:5242880', 'w+b');
    if ($output === false) {
        error_log('Master view export could not open a temporary output stream.');
        http_response_code(500);
        exit('Unable to prepare the Excel export.');
    }

    $downloadYear = $allYears ? 'All_Years' : (string) $year;
    if (fwrite($output, "\xEF\xBB\xBF") === false || fputcsv($output, $headers, ',', '"', '') === false) {
        fclose($output);
        error_log('Master view export could not write the CSV header.');
        http_response_code(500);
        exit('Unable to prepare the Excel export.');
    }

    $totalCells = array_fill(0, count($headers), '');
    $totalCells[0] = 'TOTAL';
    $totalCells[7] = $totals['completed'];
    $totalCells[8] = $totals['paid'];
    $totalCells[18] = $totals['total_hours'];
    $totalCells[19] = $totals['fee'];
    $totalCells[20] = $totals['salary'];
    $totalCells[21] = $totals['mentor'];
    $totalCells[22] = $totals['payout'];
    $totalCells[23] = $totals['balance'];

    $exportNumericColumns = array_merge($numericCols, [0, 15]);
    $writeCsvRow = static function (array $cells) use ($output, $exportNumericColumns): bool {
        $row = [];
        foreach ($cells as $index => $value) {
            if ($value === '' || $value === null) {
                $row[] = '';
            } elseif (in_array($index, $exportNumericColumns, true) && is_numeric($value)) {
                $row[] = $value;
            } else {
                $row[] = mv_csv_text($value);
            }
        }
        return fputcsv($output, $row, ',', '"', '') !== false;
    };

    foreach (mv_rows($students) as [, $cells]) {
        if (!$writeCsvRow($cells)) {
            fclose($output);
            error_log('Master view export could not write a report row.');
            http_response_code(500);
            exit('Unable to generate the Excel export.');
        }
    }
    if (!$writeCsvRow($totalCells)) {
        fclose($output);
        error_log('Master view export could not write the totals row.');
        http_response_code(500);
        exit('Unable to generate the Excel export.');
    }
    if (rewind($output) === false) {
        fclose($output);
        error_log('Master view export could not prepare the CSV for download.');
        http_response_code(500);
        exit('Unable to finish the Excel export.');
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Quidei_Master_View_' . $downloadYear . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    if (fpassthru($output) === false) {
        error_log('Master view export failed while sending the CSV response.');
    }
    fclose($output);
    exit;
}

$filterParams = array_filter([
    'year' => $allYears ? 'all' : (string) ($year ?? ''),
    'schedule_filter' => $scheduleFilter,
    'month' => $monthFilter,
    'supervisor_id' => $supervisorId ? (string) $supervisorId : '',
    'mentor_id' => $mentorUnassigned ? 'none' : ($mentorId ? (string) $mentorId : ''),
    'grade' => $gradeFilter,
    'student_status' => $studentStatusFilter,
    'fee' => $feeFilter,
    'exam' => $examFilter,
    'progress' => $progressFilter,
    'material' => $checklistFilters['checklist_material'],
    'wa_group' => $checklistFilters['checklist_group'],
    'teams' => $checklistFilters['checklist_teams'],
], static fn($v) => $v !== '');
$downloadUrl = 'master_view.php?' . http_build_query($filterParams + ['download' => '1']);
$clearAdvancedUrl = 'master_view.php?' . http_build_query(array_intersect_key(
    $filterParams,
    array_flip(['year', 'schedule_filter', 'month', 'supervisor_id', 'mentor_id'])
));

// Renders one <select> for the advanced filter modal.
function mv_filter_select(string $name, string $label, array $options, string $selected): void
{
    $id = 'mv-f-' . $name;
    echo '<div class="col-6"><label for="' . mv_h($id) . '" class="mv-filter-label">' . mv_h($label) . '</label>';
    echo '<select id="' . mv_h($id) . '" name="' . mv_h($name) . '" form="mv-filter-form" class="form-select mv-adv-select">';
    echo '<option value="">All</option>';
    foreach ($options as $value => $text) {
        $value = (string) $value;
        echo '<option value="' . mv_h($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . mv_h($text) . '</option>';
    }
    echo '</select></div>';
}

ob_end_flush();
?>

<style>
.mv-hero {
    background: linear-gradient(120deg, #064e3b 0%, #059669 55%, #34d399 100%);
    border-radius: 1.25rem;
}

.mv-stat {
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    background: #fff;
    padding: 1rem 1.25rem;
    height: 100%;
}

.mv-stat .label {
    color: #64748b;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.mv-stat .value {
    color: #0f172a;
    font-size: 1.35rem;
    font-weight: 800;
}

.mv-wrap {
    max-height: 70vh;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    background: #fff;
}

.mv-search {
    max-width: 28rem;
}

.mv-table {
    border-collapse: separate;
    border-spacing: 0;
    font-size: .85rem;
    margin: 0;
    min-width: 1900px;
    width: 100%;
}

.mv-table th {
    background: #064e3b;
    color: #fff;
    font-size: .72rem;
    letter-spacing: .04em;
    padding: .65rem .6rem;
    position: sticky;
    top: 0;
    white-space: nowrap;
    z-index: 3;
}

.mv-table td {
    border-bottom: 1px solid #f1f5f9;
    padding: .5rem .6rem;
    white-space: nowrap;
}

.mv-table tr.first td {
    background: #f0fdf4;
    border-top: 2px solid #10b981;
}

.mv-table tbody tr:hover td {
    background: #ecfeff;
}

.mv-table .num {
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.mv-table th:nth-child(2),
.mv-table td:nth-child(2) {
    left: 0;
    position: sticky;
    z-index: 2;
    background: inherit;
    box-shadow: 1px 0 0 #e2e8f0;
}

.mv-table th:nth-child(2) {
    z-index: 4;
    background: #064e3b;
}

.mv-table tbody td:nth-child(2) {
    background: #fff;
    font-weight: 600;
}

.mv-table tbody tr.first td:nth-child(2) {
    background: #f0fdf4;
}

.mv-table tfoot td {
    background: #064e3b !important;
    bottom: 0;
    color: #fff;
    font-weight: 700;
    left: auto !important;
    position: sticky;
    z-index: 2;
    box-shadow: none !important;
}

.mv-filter-label {
    display: block;
    margin-bottom: 4px;
    color: #64748b;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.neg {
    color: #dc2626;
    font-weight: 700;
}

.pos {
    color: #059669;
    font-weight: 700;
}

@media (max-width: 575.98px) {
    .mv-hero {
        padding: 1.5rem !important;
    }

    .mv-stat .value {
        font-size: 1.1rem;
    }
}
</style>

<main class="container-fluid px-3 px-lg-4 py-4">
    <section class="mv-hero text-white p-4 p-lg-5 mb-4 shadow-sm">
        <span class="badge rounded-pill bg-white bg-opacity-25 text-white mb-3">ADMIN ONLY</span>
        <h1 class="h2 fw-bold mb-2">Master View</h1>
        <p class="mb-0 text-white-50">All student, schedule and payout data in one organised sheet, with Excel download.
        </p>
    </section>

    <form method="GET" id="mv-filter-form" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
            <label for="mv-year" class="form-label fw-semibold">Academic year</label>
            <select id="mv-year" name="year" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $allYears ? 'selected' : '' ?>>All years</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?>–<?= $y + 1 ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
            <label for="mv-schedule-filter" class="form-label fw-semibold">Schedule filter</label>
            <select id="mv-schedule-filter" name="schedule_filter" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $scheduleFilter === 'all' ? 'selected' : '' ?>>All schedules</option>
                <option value="new_needed" <?= $scheduleFilter === 'new_needed' ? 'selected' : '' ?>>New schedule needed
                </option>
                <option value="new_admission" <?= $scheduleFilter === 'new_admission' ? 'selected' : '' ?>>New admission
                </option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
            <label for="mv-month-filter" class="form-label fw-semibold">Month</label>
            <select id="mv-month-filter" name="month" class="form-select" onchange="this.form.submit()">
                <option value="">All months</option>
                <?php foreach ($monthOptions as $ym => $monthLabel): ?>
                <option value="<?= mv_h($ym) ?>" <?= (string) $ym === $monthFilter ? 'selected' : '' ?>>
                    <?= mv_h($monthLabel) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
            <label for="mv-supervisor-filter" class="form-label fw-semibold">Supervisor</label>
            <select id="mv-supervisor-filter" name="supervisor_id" class="form-select" onchange="this.form.submit()">
                <option value="">All supervisors</option>
                <?php foreach ($supervisors as $supervisor): ?>
                <option value="<?= (int) $supervisor['id'] ?>" <?= (int) $supervisor['id'] === $supervisorId ? 'selected' : '' ?>>
                    <?= mv_h($supervisor['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
            <label for="mv-mentor-filter" class="form-label fw-semibold">Mentor</label>
            <select id="mv-mentor-filter" name="mentor_id" class="form-select" onchange="this.form.submit()">
                <option value="">All mentors</option>
                <option value="none" <?= $mentorUnassigned ? 'selected' : '' ?>>Not Assigned</option>
                <?php foreach ($mentors as $mentor): ?>
                <option value="<?= (int) $mentor['id'] ?>" <?= (int) $mentor['id'] === $mentorId ? 'selected' : '' ?>>
                    <?= mv_h($mentor['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-auto">
            <button type="button" class="btn btn-primary fw-semibold w-100" data-bs-toggle="modal"
                data-bs-target="#mvAdvancedFilterModal">
                <i class="bi bi-filter-right me-1" aria-hidden="true"></i>Advanced Filters
                <?php if ($advancedFilterCount > 0): ?>
                <span class="badge rounded-pill bg-white text-primary ms-1"><?= $advancedFilterCount ?></span>
                <?php endif; ?>
            </button>
        </div>
        <?php if ($advancedFilterCount > 0): ?>
        <div class="col-12 col-sm-6 col-md-auto">
            <a class="btn btn-light border w-100" href="<?= mv_h($clearAdvancedUrl) ?>">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Clear advanced
            </a>
        </div>
        <?php endif; ?>
        <div class="col-12 col-sm-6 col-md-auto">
            <?php if ($hasYearSelection): ?>
            <a class="btn btn-success fw-semibold w-100" href="<?= mv_h($downloadUrl) ?>">
                <i class="bi bi-file-earmark-excel me-2" aria-hidden="true"></i>Download CSV for Excel
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- ADVANCED FILTER POPUP MODAL (same options as Admissions) -->
    <div class="modal fade" id="mvAdvancedFilterModal" tabindex="-1" aria-labelledby="mvAdvancedFilterTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="mvAdvancedFilterTitle">
                        <i class="bi bi-sliders me-2" aria-hidden="true"></i>Filter Options
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <?php
                        mv_filter_select('grade', 'Grade/Class', array_combine($grades, $grades) ?: [], $gradeFilter);
                        mv_filter_select('student_status', 'Active Status', $studentStatusOptions, $studentStatusFilter);
                        mv_filter_select('fee', 'Fee Status', $feeOptions, $feeFilter);
                        mv_filter_select('exam', 'Exam Status', $examOptions, $examFilter);
                        mv_filter_select('progress', 'Hours Progress', $progressOptions, $progressFilter);
                        mv_filter_select('material', 'Material', $checklistOptions, $checklistFilters['checklist_material']);
                        mv_filter_select('wa_group', 'WA Group', $checklistOptions, $checklistFilters['checklist_group']);
                        mv_filter_select('teams', 'MS Teams', $checklistOptions, $checklistFilters['checklist_teams']);
                        ?>
                    </div>
                    <p class="form-text mb-0 mt-3">Fee, exam and hours filters apply to each schedule row.</p>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none"
                        id="mv-adv-clear">Clear All</button>
                    <button type="submit" form="mv-filter-form"
                        class="btn btn-primary px-5 rounded-pill shadow">Apply</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$hasYearSelection || !$students): ?>
    <div class="alert alert-info">
        <?php if ($advancedFilterCount > 0): ?>
        No records match the selected advanced filters <?= $allYears ? 'across all years' : 'for this year' ?>.
        <?php elseif ($monthFilter !== ''): ?>
        No schedules start or end in <?= mv_h($monthOptions[$monthFilter] ?? $monthFilter) ?>
        <?= $allYears ? 'across all years' : 'for this year' ?>.
        <?php elseif ($scheduleFilter === 'new_needed'): ?>
        No schedules match the New schedule needed filter <?= $allYears ? 'across all years' : 'for this year' ?>.
        <?php elseif ($scheduleFilter === 'new_admission'): ?>
        No first schedules with zero completed hours were found <?= $allYears ? 'across all years' : 'for this year' ?>.
        <?php else: ?>
        No student records available <?= $allYears ? 'across all years' : 'for this year' ?>.
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="mv-stat">
                <div class="label">Students</div>
                <div class="value"><?= count($students) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mv-stat">
                <div class="label">Total fee</div>
                <div class="value">₹ <?= number_format($totals['fee'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mv-stat">
                <div class="label">Teacher + mentor payout</div>
                <div class="value">₹ <?= number_format($totals['salary'] + $totals['mentor'], 2) ?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mv-stat">
                <div class="label">Balance</div>
                <div class="value <?= $totals['balance'] < 0 ? 'neg' : 'pos' ?>">₹
                    <?= number_format($totals['balance'], 2) ?></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3">
        <label for="mv-table-search" class="visually-hidden">Search students and schedule details</label>
        <input type="search" id="mv-table-search" class="form-control mv-search"
            placeholder="Search student, mentor, supervisor, schedule...">
    </div>
    <div id="mv-search-empty" class="alert alert-info py-2" role="status" hidden>
        No students or schedules match your search.
    </div>
    <div class="mv-wrap shadow-sm">
        <table id="mv-table" class="table mv-table">
            <thead>
                <tr>
                    <?php foreach ($headers as $h): ?><th><?= mv_h($h) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (mv_rows($students) as [$first, $cells]): ?>
                <tr class="<?= $first ? 'first' : '' ?>">
                    <?php foreach ($cells as $idx => $v):
                            $isNum = in_array($idx, $numericCols, true);
                            $cls = $isNum ? 'num' : '';
                            if ($idx === 23 && $v !== '') {
                                $cls .= $v < 0 ? ' neg' : ' pos';
                            } ?>
                    <td class="<?= $cls ?>"><?= mv_cell($v, $isNum, $isNum && $idx >= 19) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-end">TOTAL</td>
                    <td class="num"><?= number_format($totals['completed'], 2) ?></td>
                    <td class="num"><?= number_format($totals['paid'], 2) ?></td>
                    <td colspan="9"></td>
                    <?php foreach (['total_hours', 'fee', 'salary', 'mentor', 'payout', 'balance'] as $k): ?>
                    <td class="num">
                        <?= in_array($k, ['fee', 'salary', 'mentor', 'balance'], true) ? '₹ ' : '' ?><?= number_format($totals[$k], 2) ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="form-text mt-2">Student details appear on the first row of each student; enquiries are excluded. Scroll
        sideways on small screens.</p>
    <?php endif; ?>
</main>

<script>
document.getElementById('mv-adv-clear')?.addEventListener('click', () => {
    document.querySelectorAll('.mv-adv-select').forEach(select => {
        select.value = '';
    });
});

(() => {
    const searchInput = document.getElementById('mv-table-search');
    const tableBody = document.querySelector('#mv-table tbody');
    const noResults = document.getElementById('mv-search-empty');
    if (!searchInput || !tableBody || !noResults) {
        return;
    }

    const groups = [];
    tableBody.querySelectorAll('tr').forEach(row => {
        if (row.classList.contains('first') || groups.length === 0) {
            groups.push([]);
        }
        groups[groups.length - 1].push(row);
    });

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLocaleLowerCase();
        let matches = 0;

        groups.forEach(rows => {
            const groupText = rows.map(row => row.textContent).join(' ').toLocaleLowerCase();
            const visible = query === '' || groupText.includes(query);
            rows.forEach(row => {
                row.hidden = !visible;
            });
            if (visible) {
                matches++;
            }
        });

        noResults.hidden = matches > 0;
    });
})();
</script>

<?php include 'includes/footer.php'; ?>