<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(['data' => []]));
}

$quick = $_GET['quick'] ?? 'today';
$mentor_id = $_GET['mentor_id'] ?? '';

// Date Filter Logic
$date_clause = "DATE(s.date) = CURDATE()";
if ($quick === 'yesterday') $date_clause = "DATE(s.date) = SUBDATE(CURDATE(),1)";
if ($quick === 'this_week') $date_clause = "YEARWEEK(s.date, 1) = YEARWEEK(CURDATE(), 1)";
if ($quick === 'custom') {
    $from = $_GET['from']; $to = $_GET['to'];
    $date_clause = "s.date BETWEEN '$from' AND '$to'";
}

$sql = "SELECT 
            s.date, s.start_time, s.planned_hours, s.status, s.notes,
            sub.name as subject_name,
            stu.name as student_name,
            u_t.name as teacher_name,
            u_m.name as mentor_name
        FROM sessions s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u_t ON t.user_id = u_t.id
        JOIN schedules sch ON s.schedule_id = sch.id
        JOIN students stu ON sch.student_id = stu.id
        JOIN mentors m ON sch.mentor_id = m.id
        JOIN users u_m ON m.user_id = u_m.id
        WHERE $date_clause";

if (!empty($mentor_id)) {
    $sql .= " AND m.id = " . intval($mentor_id);
}

$sql .= " ORDER BY s.date DESC, s.start_time DESC";

$result = $conn->query($sql);
$data = [];

while ($row = $result->fetch_assoc()) {
    $statusClass = match($row['status']) {
        'done' => 'bg-success',
        'missed' => 'bg-danger',
        'planned' => 'bg-primary',
        default => 'bg-secondary'
    };

    $data[] = [
        "time_date" => "<b>".date('d M', strtotime($row['date']))."</b><br><small>".($row['start_time'] ?? '--:--')."</small>",
        "student_name" => $row['student_name'],
        "subject" => $row['subject_name'],
        "teacher_name" => $row['teacher_name'],
        "mentor_name" => $row['mentor_name'],
        "hrs" => "<b>".$row['planned_hours']."</b>",
        "status_badge" => "<span class='badge $statusClass'>".strtoupper($row['status'])."</span>"
    ];
}

echo json_encode(['data' => $data]);