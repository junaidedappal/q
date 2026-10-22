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
if (is_string($yearInput) && preg_match('/^[0-9]{4}$/D', $yearInput) === 1 && in_array((int) $yearInput, $years, true)) {
    $year = (int) $yearInput;
} elseif ($years) {
    $year = $years[0];
}

$students = [];
$totals = ['fee' => 0.0, 'salary' => 0.0, 'mentor' => 0.0, 'balance' => 0.0, 'paid' => 0.0, 'total_hours' => 0.0, 'completed' => 0.0];

if ($year !== null) {
    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-01-01', $year + 1);

    $stmt = $conn->prepare(
        "SELECT
            s.id AS student_id, s.name, s.status, s.fee_status, s.syllabus, s.class,
            s.language, s.phone, s.parent_phone,
            COALESCE(u.name, '') AS mentor_name,
            COALESCE(sup.name, '') AS supervisor_name,
            COALESCE(m.rate_per_session, 0) AS rate_per_session,
            COALESCE(m.rate_per_exam, 0) AS rate_per_exam,
            COALESCE(attendance_totals.absent_count, 0) AS absent_count,
            sch.id AS schedule_id, sch.start_date, sch.end_date, sch.paid_hours,
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
              AND st.created_at >= ? AND st.created_at < ?
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
              AND st.created_at >= ? AND st.created_at < ?
              AND COALESCE(st.status, '') <> 'enquiry'
            GROUP BY sess.schedule_id
         ) session_totals ON session_totals.schedule_id = sch.id
         LEFT JOIN (
            SELECT ex.schedule_id, COUNT(*) AS exam_count
            FROM schedule_exams ex
            INNER JOIN schedules es ON es.id = ex.schedule_id
            INNER JOIN students st ON st.id = es.student_id
            WHERE st.created_at >= ? AND st.created_at < ?
              AND COALESCE(st.status, '') <> 'enquiry'
            GROUP BY ex.schedule_id
         ) exam_totals ON exam_totals.schedule_id = sch.id
         WHERE s.created_at >= ? AND s.created_at < ?
           AND COALESCE(s.status, '') <> 'enquiry'
         ORDER BY s.name ASC, s.id ASC, sch.id ASC"
    );
    $stmt->bind_param('ssssssss', $start, $end, $start, $end, $start, $end, $start, $end);
    $stmt->execute();
    $rows = $stmt->get_result();

    while ($rec = $rows->fetch_assoc()) {
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
            'paid' => (float) ($rec['paid_hours'] ?? 0),
            'total' => (float) ($rec['total_hours'] ?? 0),
            'completed' => (float) $rec['completed_hours'],
            'fee' => $fee, 'salary' => $salary, 'mentor' => $mentorFee,
            'balance' => $fee - ($salary + $mentorFee),
        ];
        $students[$sid]['schedules'][] = $sched;
        $totals['fee'] += $fee;
        $totals['salary'] += $salary;
        $totals['mentor'] += $mentorFee;
        $totals['balance'] += $sched['balance'];
        $totals['paid'] += $sched['paid'];
        $totals['total_hours'] += $sched['total'];
        $totals['completed'] += $sched['completed'];
    }
    $stmt->close();
}

$headers = [
    'SI NO', 'STUDENT NAME', 'STATUS', 'ACADEMIC MENTOR', 'SUPERVISOR', 'FEE STATUS', 'ABSENT',
    'SYLLABUS', 'GRADE', 'LANGUAGE', 'STUDENT PHONE', 'PARENT PHONE',
    'SCHEDULE', 'START', 'END', 'PAID HRS', 'TOTAL HRS', 'COMPLETED HRS',
    'FEE', 'TEACHER SALARY', 'MENTOR FEE', 'BALANCE',
];
$numericCols = [6, 15, 16, 17, 18, 19, 20, 21];

// Yields table rows as arrays of cells; student columns only on a student's first row.
function mv_rows(array $students): Generator
{
    $n = 0;
    foreach ($students as $sid => $st) {
        $n++;
        $i = $st['info'];
        $studentCells = [
            $n, $i['name'], $i['status'], $i['mentor_name'], $i['supervisor_name'], $i['fee_status'],
            (int) $i['absent_count'], $i['syllabus'], $i['class'], $i['language'], $i['phone'], $i['parent_phone'],
        ];
        $scheds = $st['schedules'] ?: [null];
        foreach ($scheds as $k => $s) {
            $cells = $k === 0 ? $studentCells : array_fill(0, 12, '');
            if ($s === null) {
                $cells = array_merge($cells, array_fill(0, 10, ''));
            } else {
                array_push(
                    $cells, $k + 1, $s['start'], $s['end'],
                    $s['paid'], $s['total'], $s['completed'],
                    $s['fee'], $s['salary'], $s['mentor'], $s['balance']
                );
            }
            yield [$k === 0, $cells];
        }
    }
}

function mv_cell($v, bool $numeric): string
{
    if ($numeric && $v !== '' && $v !== null) {
        return number_format((float) $v, is_int($v) ? 0 : 2);
    }
    return mv_h($v);
}

// Excel download: HTML table with styling, opened natively by Excel.
if ($year !== null && ($_GET['download'] ?? '') === '1') {
    ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="Quidei_Master_View_' . $year . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8">'
        . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Master ' . $year . '</x:Name>'
        . '<x:WorksheetOptions><x:FreezePanes/><x:SplitHorizontal>3</x:SplitHorizontal><x:TopRowBottomPane>3</x:TopRowBottomPane>'
        . '<x:SplitVertical>2</x:SplitVertical><x:LeftColumnRightPane>2</x:LeftColumnRightPane><x:ActivePane>0</x:ActivePane>'
        . '<x:ProtectContents>False</x:ProtectContents></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->'
        . '<style>table{border-collapse:collapse;font-family:Calibri,Arial;font-size:11pt}'
        . 'th{background:#064e3b;color:#fff;border:1px solid #94a3b8;padding:6px;text-align:center;font-weight:bold}'
        . 'td{border:1px solid #cbd5e1;padding:4px 6px;vertical-align:middle}'
        . '.first td{border-top:2px solid #059669;background:#f0fdf4}'
        . '.num{text-align:right;mso-number-format:"#,##0.00"}.int{text-align:right;mso-number-format:"0"}'
        . '.txt{mso-number-format:"\@"}'
        . '.title{font-size:16pt;font-weight:bold;color:#064e3b;border:none}'
        . '.tot td{background:#064e3b;color:#fff;font-weight:bold;border:1px solid #94a3b8}'
        . '</style></head><body><table>';
    $colCount = count($headers);
    echo '<tr><td class="title" colspan="' . $colCount . '">Quidei Master Report - Academic Year ' . $year . '-' . ($year + 1) . '</td></tr>';
    echo '<tr><td colspan="' . $colCount . '" style="border:none">Generated ' . mv_h(date('d M Y, H:i')) . ' - Students: ' . count($students) . '</td></tr>';
    echo '<tr>';
    foreach ($headers as $h) {
        echo '<th>' . mv_h($h) . '</th>';
    }
    echo '</tr>';
    foreach (mv_rows($students) as [$first, $cells]) {
        echo '<tr' . ($first ? ' class="first"' : '') . '>';
        foreach ($cells as $idx => $v) {
            if (in_array($idx, $numericCols, true) && $v !== '') {
                echo '<td class="' . (is_int($v) ? 'int' : 'num') . '">' . (is_int($v) ? $v : number_format((float) $v, 2, '.', '')) . '</td>';
            } else {
                $text = (string) $v;
                if (preg_match('/^[\t\r\n ]*[=+\-@]/', $text) === 1) {
                    $text = "'" . $text;
                }
                echo '<td class="txt">' . mv_h($text) . '</td>';
            }
        }
        echo '</tr>';
    }
    echo '<tr class="tot"><td colspan="15" style="text-align:right">TOTAL</td>';
    foreach (['paid', 'total_hours', 'completed', 'fee', 'salary', 'mentor', 'balance'] as $k) {
        echo '<td class="num">' . number_format($totals[$k], 2, '.', '') . '</td>';
    }
    echo '</tr></table></body></html>';
    exit;
}

ob_end_flush();
?>

<style>
.mv-hero { background: linear-gradient(120deg, #064e3b 0%, #059669 55%, #34d399 100%); border-radius: 1.25rem; }
.mv-stat { border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; padding: 1rem 1.25rem; height: 100%; }
.mv-stat .label { color: #64748b; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.mv-stat .value { color: #0f172a; font-size: 1.35rem; font-weight: 800; }
.mv-wrap { max-height: 70vh; overflow: auto; border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; }
.mv-table { border-collapse: separate; border-spacing: 0; font-size: .85rem; margin: 0; min-width: 1900px; width: 100%; }
.mv-table th { background: #064e3b; color: #fff; font-size: .72rem; letter-spacing: .04em; padding: .65rem .6rem; position: sticky; top: 0; white-space: nowrap; z-index: 3; }
.mv-table td { border-bottom: 1px solid #f1f5f9; padding: .5rem .6rem; white-space: nowrap; }
.mv-table tr.first td { background: #f0fdf4; border-top: 2px solid #10b981; }
.mv-table tbody tr:hover td { background: #ecfeff; }
.mv-table .num { font-variant-numeric: tabular-nums; text-align: right; }
.mv-table th:nth-child(2), .mv-table td:nth-child(2) { left: 0; position: sticky; z-index: 2; background: inherit; box-shadow: 1px 0 0 #e2e8f0; }
.mv-table th:nth-child(2) { z-index: 4; background: #064e3b; }
.mv-table tbody td:nth-child(2) { background: #fff; font-weight: 600; }
.mv-table tbody tr.first td:nth-child(2) { background: #f0fdf4; }
.mv-table tfoot td { background: #064e3b !important; bottom: 0; color: #fff; font-weight: 700; left: auto !important; position: sticky; z-index: 2; box-shadow: none !important; }
.neg { color: #dc2626; font-weight: 700; }
.pos { color: #059669; font-weight: 700; }
@media (max-width: 575.98px) { .mv-hero { padding: 1.5rem !important; } .mv-stat .value { font-size: 1.1rem; } }
</style>

<main class="container-fluid px-3 px-lg-4 py-4">
    <section class="mv-hero text-white p-4 p-lg-5 mb-4 shadow-sm">
        <span class="badge rounded-pill bg-white bg-opacity-25 text-white mb-3">ADMIN ONLY</span>
        <h1 class="h2 fw-bold mb-2">Master View</h1>
        <p class="mb-0 text-white-50">All student, schedule and payout data in one organised sheet, with Excel download.</p>
    </section>

    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <label for="mv-year" class="form-label fw-semibold">Academic year</label>
            <select id="mv-year" name="year" class="form-select" onchange="this.form.submit()" <?= $years ? '' : 'disabled' ?>>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?>–<?= $y + 1 ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-auto">
            <?php if ($year !== null): ?>
                <a class="btn btn-success fw-semibold w-100" href="master_view.php?year=<?= $year ?>&amp;download=1">
                    <i class="bi bi-file-earmark-excel me-2" aria-hidden="true"></i>Download Excel
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($year === null || !$students): ?>
        <div class="alert alert-info">No student records available for this year.</div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="mv-stat"><div class="label">Students</div><div class="value"><?= count($students) ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="mv-stat"><div class="label">Total fee</div><div class="value"><?= number_format($totals['fee'], 2) ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="mv-stat"><div class="label">Teacher + mentor payout</div><div class="value"><?= number_format($totals['salary'] + $totals['mentor'], 2) ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="mv-stat"><div class="label">Balance</div><div class="value <?= $totals['balance'] < 0 ? 'neg' : 'pos' ?>"><?= number_format($totals['balance'], 2) ?></div></div></div>
        </div>

        <div class="mv-wrap shadow-sm">
            <table class="table mv-table">
                <thead><tr>
                    <?php foreach ($headers as $h): ?><th><?= mv_h($h) ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach (mv_rows($students) as [$first, $cells]): ?>
                    <tr class="<?= $first ? 'first' : '' ?>">
                        <?php foreach ($cells as $idx => $v):
                            $isNum = in_array($idx, $numericCols, true);
                            $cls = $isNum ? 'num' : '';
                            if ($idx === 21 && $v !== '') {
                                $cls .= $v < 0 ? ' neg' : ' pos';
                            } ?>
                            <td class="<?= $cls ?>"><?= mv_cell($v, $isNum) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <td colspan="15" class="text-end">TOTAL</td>
                    <?php foreach (['paid', 'total_hours', 'completed', 'fee', 'salary', 'mentor', 'balance'] as $k): ?>
                        <td class="num"><?= number_format($totals[$k], 2) ?></td>
                    <?php endforeach; ?>
                </tr></tfoot>
            </table>
        </div>
        <p class="form-text mt-2">Student details appear on the first row of each student; enquiries are excluded. Scroll sideways on small screens.</p>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
