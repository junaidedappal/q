<?php
session_start();

if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the export form to generate this report.');
}

$yearInput = $_POST['year'] ?? null;
if (!is_string($yearInput) || preg_match('/^[0-9]{4}$/D', $yearInput) !== 1) {
    http_response_code(400);
    exit('Select a valid academic year and try again.');
}

$year = (int) $yearInput;
if ($year < 1000 || $year > 9998) {
    http_response_code(400);
    exit('Select a valid academic year and try again.');
}

$startDate = sprintf('%04d-01-01', $year);
$endDate = sprintf('%04d-01-01', $year + 1);

function failMasterExport(string $logMessage, string $responseMessage = 'The export could not be generated. Please try again later.'): void
{
    error_log($logMessage);
    http_response_code(500);
    exit($responseMessage);
}

function prepareMasterExportStatement(mysqli $conn, string $query): mysqli_stmt
{
    try {
        $stmt = $conn->prepare($query);
    } catch (mysqli_sql_exception $exception) {
        failMasterExport('Master export prepare failed: ' . $exception->getMessage());
    }

    if ($stmt === false) {
        failMasterExport('Master export prepare failed: ' . $conn->error);
    }

    return $stmt;
}

function executeMasterExportStatement(mysqli_stmt $stmt): void
{
    try {
        $success = $stmt->execute();
    } catch (mysqli_sql_exception $exception) {
        failMasterExport('Master export query failed: ' . $exception->getMessage());
    }

    if (!$success) {
        failMasterExport('Master export query failed: ' . $stmt->error);
    }
}

function getMasterExportResult(mysqli_stmt $stmt): mysqli_result
{
    try {
        $result = $stmt->get_result();
    } catch (mysqli_sql_exception $exception) {
        failMasterExport('Master export result read failed: ' . $exception->getMessage());
    }

    if ($result === false) {
        failMasterExport('Master export result read failed: ' . $stmt->error);
    }

    return $result;
}

function safeMasterExportText(mixed $value): string
{
    $text = (string) ($value ?? '');
    if (preg_match('/^[\t\r\n ]*[=+\-@]/', $text) === 1) {
        return "'" . $text;
    }

    return $text;
}

try {
    require 'includes/db.php';
} catch (mysqli_sql_exception $exception) {
    failMasterExport('Master export database connection failed: ' . $exception->getMessage());
}

$reportStmt = prepareMasterExportStatement(
    $conn,
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
        INNER JOIN students attendance_student ON attendance_student.id = a.student_id
        WHERE a.attended = 0
          AND attendance_student.created_at >= ? AND attendance_student.created_at < ?
          AND COALESCE(attendance_student.status, '') <> 'enquiry'
        GROUP BY a.student_id
     ) attendance_totals ON attendance_totals.student_id = s.id
     LEFT JOIN schedules sch ON sch.student_id = s.id
     LEFT JOIN (
        SELECT sess.schedule_id,
               SUM(sess.planned_hours) AS completed_hours,
               SUM(sess.planned_hours * sess.teacher_pay) AS teacher_salary,
               COUNT(*) AS completed_sessions
        FROM sessions sess
        INNER JOIN schedules session_schedule ON session_schedule.id = sess.schedule_id
        INNER JOIN students session_student ON session_student.id = session_schedule.student_id
        WHERE sess.status = 'done'
          AND session_student.created_at >= ? AND session_student.created_at < ?
          AND COALESCE(session_student.status, '') <> 'enquiry'
        GROUP BY sess.schedule_id
     ) session_totals ON session_totals.schedule_id = sch.id
     LEFT JOIN (
        SELECT ex.schedule_id, COUNT(*) AS exam_count
        FROM schedule_exams ex
        INNER JOIN schedules exam_schedule ON exam_schedule.id = ex.schedule_id
        INNER JOIN students exam_student ON exam_student.id = exam_schedule.student_id
        WHERE exam_student.created_at >= ? AND exam_student.created_at < ?
          AND COALESCE(exam_student.status, '') <> 'enquiry'
        GROUP BY ex.schedule_id
     ) exam_totals ON exam_totals.schedule_id = sch.id
     WHERE s.created_at >= ? AND s.created_at < ?
       AND COALESCE(s.status, '') <> 'enquiry'
     ORDER BY s.name ASC, s.id ASC, sch.id ASC"
);
$reportStmt->bind_param(
    'ssssssss',
    $startDate,
    $endDate,
    $startDate,
    $endDate,
    $startDate,
    $endDate,
    $startDate,
    $endDate
);
executeMasterExportStatement($reportStmt);
$reportRows = getMasterExportResult($reportStmt);

$output = fopen('php://temp/maxmemory:5242880', 'w+b');
if ($output === false) {
    failMasterExport('Master export could not open a temporary output stream.');
}

if (fwrite($output, "\xEF\xBB\xBF") === false) {
    fclose($output);
    failMasterExport('Master export could not write the CSV header.');
}

$headers = [
    'SI NO', 'STUDENT ID', 'STUDENT NAME', 'STUDENT STATUS', 'ACADEMIC MENTOR', 'SUPERVISOR',
    'FEE STATUS', 'ABSENT COUNT', 'YEAR', 'SYLLABUS', 'GRADE', 'LANGUAGE',
    'STUDENT PHONE', 'PARENT PHONE', 'SCHEDULE NO', 'SCHEDULE START', 'SCHEDULE END',
    'PAID HOURS', 'TOTAL HOURS', 'COMPLETED HOURS', 'FEE AMOUNT', 'TEACHER SALARY',
    'MENTOR FEE', 'BALANCE'
];

if (fputcsv($output, $headers, ',', '"', '') === false) {
    fclose($output);
    failMasterExport('Master export could not write the CSV header row.');
}

$lastStudentId = null;
$studentNumber = 0;
$scheduleNumber = 0;
while ($record = $reportRows->fetch_assoc()) {
    $studentId = (int) $record['student_id'];
    $isFirstStudentRow = $lastStudentId !== $studentId;
    if ($isFirstStudentRow) {
        $studentNumber++;
        $scheduleNumber = 0;
        $lastStudentId = $studentId;
    }

    $hasSchedule = $record['schedule_id'] !== null;
    if ($hasSchedule) {
        $scheduleNumber++;
    }

    $teacherSalary = (float) $record['teacher_salary'];
    $mentorFee = ((int) $record['completed_sessions'] * (float) $record['rate_per_session'])
        + ((int) $record['exam_count'] * (float) $record['rate_per_exam']);
    $balance = (float) ($record['fee_amount'] ?? 0) - ($teacherSalary + $mentorFee);

    $row = $isFirstStudentRow
        ? [
            $studentNumber,
            $studentId,
            safeMasterExportText($record['name']),
            safeMasterExportText($record['status']),
            safeMasterExportText($record['mentor_name']),
            safeMasterExportText($record['supervisor_name']),
            safeMasterExportText($record['fee_status']),
            (int) $record['absent_count'],
            $year,
            safeMasterExportText($record['syllabus']),
            safeMasterExportText($record['class']),
            safeMasterExportText($record['language']),
            safeMasterExportText($record['phone']),
            safeMasterExportText($record['parent_phone'])
        ]
        : array_fill(0, 14, '');

    if ($hasSchedule) {
        array_push(
            $row,
            $scheduleNumber,
            safeMasterExportText($record['start_date']),
            safeMasterExportText($record['end_date']),
            (float) ($record['paid_hours'] ?? 0),
            (float) ($record['total_hours'] ?? 0),
            (float) $record['completed_hours'],
            (float) ($record['fee_amount'] ?? 0),
            $teacherSalary,
            $mentorFee,
            $balance
        );
    } else {
        array_push($row, '', '', '', '', '', '', '', '', '', '');
    }

    if (fputcsv($output, $row, ',', '"', '') === false) {
        fclose($output);
        failMasterExport('Master export could not write a report row.');
    }
}

$reportRows->free();
$reportStmt->close();

if (rewind($output) === false) {
    fclose($output);
    failMasterExport('Master export could not prepare the CSV for download.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Quidei_Master_Report_' . $year . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (fpassthru($output) === false) {
    error_log('Master export failed while sending the CSV response.');
}
fclose($output);
exit;
