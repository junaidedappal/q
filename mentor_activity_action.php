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

// Supervisor filter (admin / quality controller). Supervisors stay scoped to themselves.
$supervisorParam = $_GET['supervisor_id'] ?? '';
if (!is_string($supervisorParam) || ($supervisorParam !== '' && (!ctype_digit($supervisorParam) || (int) $supervisorParam < 1))) {
    mentor_activity_send_error(400, 'Choose a valid supervisor.');
}
if ($role !== 'supervisor' && $supervisorParam !== '') {
    $supervisorScopeId = (int) $supervisorParam;
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

$status = $_GET['status'] ?? '';
if (!is_string($status) || !in_array($status, ['', 'planned', 'done', 'missed'], true)) {
    mentor_activity_send_error(400, 'Choose a valid status filter.');
}

// Ensure your 'sessions' table actually contains schedule_id and teacher_id. 
// If it only contains mentor_id/student_id directly, these JOINs will trigger a SQL error.
$sql = "SELECT
            s.date, s.start_time, s.planned_hours, s.status, s.notes,
            sub.name AS subject_name,
            stu.name AS student_name,
            u_t.name AS teacher_name,
            u_m.name AS mentor_name,
            (SELECT a.reason FROM attendance a
             WHERE a.session_id = s.id AND a.attended = 0
             ORDER BY a.id DESC LIMIT 1) AS absence_reason
        FROM sessions s
        JOIN subjects sub ON s.subject_id = sub.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN users u_t ON t.user_id = u_t.id
        LEFT JOIN schedules sch ON s.schedule_id = sch.id
        LEFT JOIN students stu ON sch.student_id = stu.id
        LEFT JOIN mentors m ON sch.mentor_id = m.id
        LEFT JOIN users u_m ON m.user_id = u_m.id
        WHERE $dateCondition
          AND (? = 0 OR m.supervisor_id = ?)
          AND (? = 0 OR m.id = ?)
          /* Force the collation on the status comparison */
          AND (? = '' OR s.status COLLATE utf8mb4_unicode_ci = ?)
        ORDER BY s.date DESC, s.start_time DESC";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if ($quick === 'custom') {
        $stmt->bind_param(
            'ssiiiiss',
            $dateParameters[0],
            $dateParameters[1],
            $supervisorScopeId,
            $supervisorScopeId,
            $mentorId,
            $mentorId,
            $status,
            $status
        );
    } else {
        $stmt->bind_param(
            'iiiiss',
            $supervisorScopeId,
            $supervisorScopeId,
            $mentorId,
            $mentorId,
            $status,
            $status
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Result retrieval failed: ' . $stmt->error);
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

        $statusBadge = "<span class='badge $statusClass'>" . $escape(strtoupper($row['status'])) . '</span>';
        $absenceReason = '';
        if ($row['status'] === 'missed') {
            // Reason saved with the attendance entry; older entries may only have it in the session notes.
            $absenceReason = trim((string) ($row['absence_reason'] ?? ''));
            if ($absenceReason === '') {
                $absenceReason = trim((string) ($row['notes'] ?? ''));
            }
        }
        // Reason column: shown only for missed classes.
        $reasonDisplay = '';
        if ($row['status'] === 'missed') {
            $reasonDisplay = $absenceReason !== ''
                ? '<span class="text-danger fw-semibold">' . $escape($absenceReason) . '</span>'
                : '<span class="text-muted small">No reason recorded</span>';
        }

        $data[] = [
            'date_label' => $escape(date('d M Y', strtotime($row['date']))),
            'absence_reason' => $escape($absenceReason),
            'time_date' => '<b>' . $escape(date('d M', strtotime($row['date']))) . '</b><br><small>' . $escape($row['start_time'] ?? '--:--') . '</small>',
            'student_name' => $escape($row['student_name']),
            'subject' => $escape($row['subject_name']),
            'teacher_name' => $escape($row['teacher_name'] ?? 'N/A'),
            'mentor_name' => $escape($row['mentor_name'] ?? 'N/A'),
            'hrs' => '<b>' . $escape($row['planned_hours']) . '</b>',
            'status_badge' => $statusBadge,
            'reason_display' => $reasonDisplay
        ];
    }

    echo json_encode(['data' => $data], JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    // If there is an SQL syntax issue or a missing column, it will now print out exactly what it is on your screen.
    error_log('Mentor activity DB error: ' . $e->getMessage());
    mentor_activity_send_error(500, 'Database Error: ' . $e->getMessage());
}