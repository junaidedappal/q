<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Only 'mentor' and 'admin' can access
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'mentor'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE GET REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_schedule_details':
            $student_id = intval($_GET['student_id']);
            $data = [];

            // Get student info
            $stmt = $conn->prepare("SELECT id, name FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $data['student_info'] = $stmt->get_result()->fetch_assoc();

            // Get schedules and their sessions
            $schedules = [];
            $schedule_stmt = $conn->prepare("SELECT * FROM schedules WHERE student_id = ? ORDER BY start_date DESC");
            $schedule_stmt->bind_param("i", $student_id);
            $schedule_stmt->execute();
            $schedule_result = $schedule_stmt->get_result();
            while ($schedule = $schedule_result->fetch_assoc()) {
                $sessions = [];
                $session_stmt = $conn->prepare("
                    SELECT s.*, sub.name as subject_name, u.name as teacher_name 
                    FROM sessions s
                    JOIN subjects sub ON s.subject_id = sub.id
                    JOIN teachers t ON s.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE s.schedule_id = ? ORDER BY s.date, s.start_time
                ");
                $session_stmt->bind_param("i", $schedule['id']);
                $session_stmt->execute();
                $session_result = $session_stmt->get_result();
                while ($session = $session_result->fetch_assoc()) {
                    $sessions[] = $session;
                }
                $schedule['sessions'] = $sessions;
                $schedules[] = $schedule;
            }
            $data['schedules'] = $schedules;

            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'get_teachers_and_subjects':
            $data = [];
            $data['teachers'] = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 'active'")->fetch_all(MYSQLI_ASSOC);
            $data['subjects'] = $conn->query("SELECT id, name FROM subjects")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        // ***** THIS IS THE RESTORED DEFAULT CASE *****
        // This is required for the 'my_students.php' page to function.
        default:
            $user_role = $_SESSION['role'];
            $params = [];
            $types = '';
            
            $sql = "SELECT s.id, s.name, s.class, s.status, COALESCE(u.name, 'Not Assigned') as mentor_name
                    FROM students s
                    LEFT JOIN mentors m ON s.mentor_id = m.id
                    LEFT JOIN users u ON m.user_id = u.id";

            if ($user_role === 'mentor') {
                $mentor_user_id = $_SESSION['id'];
                $mentor_stmt = $conn->prepare("SELECT id FROM mentors WHERE user_id = ?");
                $mentor_stmt->bind_param("i", $mentor_user_id);
                $mentor_stmt->execute();
                $mentor_id = $mentor_stmt->get_result()->fetch_assoc()['id'] ?? 0;
                $sql .= " WHERE s.mentor_id = ?";
                $params[] = $mentor_id;
                $types .= 'i';
            }
            
            $sql .= " ORDER BY s.id DESC";

            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $status_class = ($row['status'] === 'active') ? 'bg-success' : 'bg-secondary';
                $row['status_badge'] = '<span class="badge rounded-pill '.$status_class.'">' . ucfirst($row['status']) . '</span>';
                $row['actions'] = '<a href="student_schedule.php?id='.$row['id'].'" class="btn btn-sm btn-primary btn-gradient-mentor-alt"><i class="bi bi-calendar2-week-fill me-1"></i>Manage Schedule</a>';
                $data[] = $row;
            }
            echo json_encode(["data" => $data]);
            break;
    }
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add_schedule':
            $stmt = $conn->prepare("INSERT INTO schedules (student_id, mentor_id, name, start_date, end_date, total_hours) VALUES (?, ?, ?, ?, ?, ?)");
            
            // Securely get the mentor_id
            $mentor_user_id = $_SESSION['id'];
            $mentor_id_stmt = $conn->prepare("SELECT id FROM mentors WHERE user_id = ?");
            $mentor_id_stmt->bind_param("i", $mentor_user_id);
            $mentor_id_stmt->execute();
            $mentor_id = $mentor_id_stmt->get_result()->fetch_assoc()['id'] ?? 0;

            if ($mentor_id == 0 && $_SESSION['role'] !== 'admin') {
                echo json_encode(['status' => 'error', 'message' => 'Could not identify a valid mentor profile for this user.']);
                exit;
            }
            // If admin is creating, we can allow a null mentor_id or handle it differently if needed
            $student_mentor_id = !empty($_POST['mentor_id']) ? $_POST['mentor_id'] : $mentor_id;

            $stmt->bind_param("iisssi", $_POST['student_id'], $student_mentor_id, $_POST['name'], $_POST['start_date'], $_POST['end_date'], $_POST['total_hours']);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'New schedule created successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to create schedule.']);
            }
            break;
            
        case 'add_session':
            $stmt = $conn->prepare("INSERT INTO sessions (schedule_id, subject_id, teacher_id, date, start_time, planned_hours, status) VALUES (?, ?, ?, ?, ?, ?, 'planned')");
            $stmt->bind_param("iiisss", $_POST['schedule_id'], $_POST['subject_id'], $_POST['teacher_id'], $_POST['date'], $_POST['start_time'], $_POST['planned_hours']);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'New session added successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to add session.']);
            }
            break;

        case 'save_attendance':
            $session_id = $_POST['session_id'];
            $student_id = $_POST['student_id'];
            $attendance_data = $_POST['attendance'];

            $conn->begin_transaction();
            try {
                // First, delete any old attendance records for this session
                $delete_stmt = $conn->prepare("DELETE FROM attendance WHERE session_id = ?");
                $delete_stmt->bind_param("i", $session_id);
                $delete_stmt->execute();

                // Now, insert the new records
                $insert_stmt = $conn->prepare("INSERT INTO attendance (session_id, student_id, hour_index, taught_minutes, attended) VALUES (?, ?, ?, ?, ?)");
                foreach ($attendance_data as $hour_index => $hour_data) {
                    $attended = isset($hour_data['attended']) ? 1 : 0;
                    $minutes = $attended ? ($hour_data['minutes'] ?? 60) : 0;
                    $insert_stmt->bind_param("iiiii", $session_id, $student_id, $hour_index, $minutes, $attended);
                    $insert_stmt->execute();
                }

                // Finally, update the session status to 'done'
                $update_stmt = $conn->prepare("UPDATE sessions SET status = 'done' WHERE id = ?");
                $update_stmt->bind_param("i", $session_id);
                $update_stmt->execute();

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Attendance saved successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;
    }
}
$conn->close();
?>