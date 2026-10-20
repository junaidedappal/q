<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'includes/db.php';

function mentor_activity_send_error(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['data' => [], 'error' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$role = $_SESSION['role'] ?? '';
$allowedRoles = ['admin', 'qualitycontroller', 'supervisor'];
if (($_SESSION['loggedin'] ?? false) !== true || !in_array($role, $allowedRoles, true)) {
    mentor_activity_send_error(403, 'Access denied.');
}

$supervisorScopeId = 0;
if ($role === 'supervisor') {
    $supervisorScopeId = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT);
    if ($supervisorScopeId === false || $supervisorScopeId < 1) {
        mentor_activity_send_error(403, 'Your supervisor account could not be verified.');
    }
}

$quick = $_GET['quick'] ?? 'today';
if (!is_string($quick) || !in_array($quick, ['today', 'yesterday', 'this_week', 'custom'], true)) {
    mentor_activity_send_error(400, 'Choose a valid date filter.');
}

$dateParameters = [];
if ($quick === 'today') {
    $dateCondition = 'DATE(s.date) = CURDATE()';
} elseif ($quick === 'yesterday') {
    $dateCondition = 'DATE(s.date) = SUBDATE(CURDATE(), 1)';
} elseif ($quick === 'this_week') {
    $dateCondition = 'YEARWEEK(s.date, 1) = YEARWEEK(CURDATE(), 1)';
} else {
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $fromDate = is_string($from) ? DateTimeImmutable::createFromFormat('!Y-m-d', $from) : false;
    $toDate = is_string($to) ? DateTimeImmutable::createFromFormat('!Y-m-d', $to) : false;

    if (
        !$fromDate || !$toDate ||
        $fromDate->format('Y-m-d') !== $from ||
        $toDate->format('Y-m-d') !== $to ||
        $fromDate > $toDate
    ) {
        mentor_activity_send_error(400, 'Enter a valid date range.');
    }

    $dateCondition = 'DATE(s.date) BETWEEN ? AND ?';
    $dateParameters = [$from, $to];
}

$mentorParam = $_GET['mentor_id'] ?? '';
if (!is_string($mentorParam) || ($mentorParam !== '' && (!ctype_digit($mentorParam) || (int) $mentorParam < 1))) {
    mentor_activity_send_error(400, 'Choose a valid mentor.');
}
$mentorId = $mentorParam === '' ? 0 : (int) $mentorParam;

$sql = "SELECT
            s.date, s.start_time, s.planned_hours, s.status, s.notes,
            sub.name AS subject_name,
            stu.name AS student_name,
            u_t.name AS teacher_name,
            u_m.name AS mentor_name
        FROM sessions s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u_t ON t.user_id = u_t.id
        JOIN schedules sch ON s.schedule_id = sch.id
        JOIN students stu ON sch.student_id = stu.id
        JOIN mentors m ON sch.mentor_id = m.id
        JOIN users u_m ON m.user_id = u_m.id
        WHERE $dateCondition
          AND (? = 0 OR m.supervisor_id = ?)
          AND (? = 0 OR m.id = ?)
        ORDER BY s.date DESC, s.start_time DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Mentor activity query preparation failed: ' . $conn->error);
    mentor_activity_send_error(500, 'Unable to load mentor activity.');
}

if ($quick === 'custom') {
    $stmt->bind_param(
        'ssiiii',
        $dateParameters[0],
        $dateParameters[1],
        $supervisorScopeId,
        $supervisorScopeId,
        $mentorId,
        $mentorId
    );
} else {
    $stmt->bind_param(
        'iiii',
        $supervisorScopeId,
        $supervisorScopeId,
        $mentorId,
        $mentorId
    );
}

if (!$stmt->execute()) {
    error_log('Mentor activity query execution failed: ' . $stmt->error);
    mentor_activity_send_error(500, 'Unable to load mentor activity.');
}

$result = $stmt->get_result();
if (!$result) {
    error_log('Mentor activity result retrieval failed: ' . $stmt->error);
    mentor_activity_send_error(500, 'Unable to load mentor activity.');
}

$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$data = [];
while ($row = $result->fetch_assoc()) {
    $statusClass = match ($row['status']) {
        'done' => 'bg-success',
        'missed' => 'bg-danger',
        'planned' => 'bg-primary',
        default => 'bg-secondary'
    };

    $data[] = [
        'time_date' => '<b>' . $escape(date('d M', strtotime($row['date']))) . '</b><br><small>' . $escape($row['start_time'] ?? '--:--') . '</small>',
        'student_name' => $escape($row['student_name']),
        'subject' => $escape($row['subject_name']),
        'teacher_name' => $escape($row['teacher_name']),
        'mentor_name' => $escape($row['mentor_name']),
        'hrs' => '<b>' . $escape($row['planned_hours']) . '</b>',
        'status_badge' => "<span class='badge $statusClass'>" . $escape(strtoupper($row['status'])) . '</span>'
    ];
}

echo json_encode(['data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);
