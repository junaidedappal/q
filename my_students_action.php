<?php
session_start();
ob_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Only 'mentor' and 'admin' can access
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'mentor'])) {
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
            $stmt = $conn->prepare('SELECT id, name FROM students WHERE id = ?');
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $data['student_info'] = $stmt->get_result()->fetch_assoc();

            // Get schedules and their sessions
            $schedules = [];
            $schedule_stmt = $conn->prepare('SELECT * FROM schedules WHERE student_id = ? ORDER BY start_date DESC');
            $schedule_stmt->bind_param('i', $student_id);
            $schedule_stmt->execute();
            $schedule_result = $schedule_stmt->get_result();
            while ($schedule = $schedule_result->fetch_assoc()) {
                $exams = [];
                $exam_stmt = $conn->prepare('
                    SELECT e.*, sub.name as subject_name 
                    FROM schedule_exams e 
                    JOIN subjects sub ON e.subject_id = sub.id 
                    WHERE e.schedule_id = ? 
                    ORDER BY e.exam_date ASC
                ');
                $exam_stmt->bind_param('i', $schedule['id']);
                $exam_stmt->execute();
                $exam_res = $exam_stmt->get_result();
                while ($ex = $exam_res->fetch_assoc()) {
                    $exams[] = $ex;
                }
                $schedule['exams'] = $exams;
                $sessions = [];
                $session_stmt = $conn->prepare('
                    SELECT s.*, sub.name as subject_name, u.name as teacher_name 
                    FROM sessions s
                    JOIN subjects sub ON s.subject_id = sub.id
                    JOIN teachers t ON s.teacher_id = t.id
                    JOIN users u ON t.user_id = u.id
                    WHERE s.schedule_id = ? ORDER BY s.date, s.start_time
                ');
                $session_stmt->bind_param('i', $schedule['id']);
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

        case 'get_single_schedule':
            $schedule_id = intval($_GET['schedule_id']);
            $stmt = $conn->prepare('SELECT * FROM schedules WHERE id = ?');
            $stmt->bind_param('i', $schedule_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

        case 'get_teachers_and_subjects':
            $data = [];
            $data['teachers'] = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 'active'")->fetch_all(MYSQLI_ASSOC);
            $data['subjects'] = $conn->query('SELECT id, name FROM subjects')->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

       // ***** MAIN DASHBOARD LIST (DEFAULT CASE) *****
        default:
            $user_role = $_SESSION['role'];

            // Query logic: 
            // 1. We join 'sch' by finding the ID that has the MAXIMUM end_date for that student.
            // 2. If multiple have the same end_date, we take the highest ID.
            $sql = "SELECT 
                s.id, s.name, s.class, s.status, s.fee_status,
                s.checklist_material, s.checklist_group, s.checklist_teams,
                COALESCE(u.name, 'Not Assigned') as mentor_name,
                sch.id as schedule_id, 
                sch.start_date, 
                sch.end_date,
                sch.exam_skip_reason,
                COALESCE(sch.total_hours, 0) as total_hours,
                COALESCE(sch.paid_hours, 0) as paid_hours,
                (SELECT COALESCE(SUM(planned_hours), 0) FROM sessions WHERE schedule_id = sch.id AND status = 'done') as completed_hours,
                (SELECT COUNT(*) FROM schedule_exams WHERE schedule_id = sch.id) as exam_count
            FROM students s
            LEFT JOIN mentors m ON s.mentor_id = m.id
            LEFT JOIN users u ON m.user_id = u.id
            /* JOIN the schedule with the GREATEST end_date */
            LEFT JOIN schedules sch ON sch.id = (
                SELECT id FROM schedules 
                WHERE student_id = s.id 
                ORDER BY end_date DESC, id DESC 
                LIMIT 1
            )
            WHERE 1=1";

            if ($user_role === 'mentor') {
                $sql .= ' AND s.mentor_id = (SELECT id FROM mentors WHERE user_id = ' . intval($_SESSION['id']) . ')';
            }

            // --- SORTING THE TABLE ---
            // Students with active/future end dates appear at the top.
            // (sch.end_date IS NULL) ensures students without schedules stay at the bottom.
            $result = $conn->query($sql . ' ORDER BY (sch.end_date IS NULL) ASC, sch.end_date DESC, s.id DESC');
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                // Status Badge Logic
                $sClass = match ($row['status']) {
                    'active' => 'bg-primary',
                    'ready' => 'bg-info text-dark',
                    'break' => 'bg-warning text-dark',
                    default => 'bg-secondary'
                };
                $row['status_badge'] = "<span class='badge $sClass'>" . ucfirst($row['status']) . '</span>';

                // Raw numbers for frontend calculation/filtering
                $comp = floatval($row['completed_hours']);
                $total = floatval($row['total_hours']);
                $paid = floatval($row['paid_hours']);
                $row['completed_hours'] = $comp;
                $row['total_hours'] = $total;

                // Fee Display Logic
                if ($total == 0)
                    $row['fee_display'] = '<span class="badge bg-secondary">No Schedule</span>';
                elseif ($paid >= $total)
                    $row['fee_display'] = '<span class="badge bg-success">Paid</span>';
                elseif ($paid > 0)
                    $row['fee_display'] = "<span class='badge bg-warning text-dark'>Partial $paid/$total</span>";
                else
                    $row['fee_display'] = '<span class="badge bg-danger">Pending</span>';

                // Date Display
                $row['schedule_dates'] = ($row['start_date']) ? date('d M', strtotime($row['start_date'])) . ' - ' . date('d M', strtotime($row['end_date'])) : '--';
                
                // Hours Display (Summed)
                $row['hr_display'] = "<b>$comp</b> / $total";

                // Automated Exam Logic
                $exCount = intval($row['exam_count']);
                if ($row['schedule_id'] > 0) {
                    if ($exCount > 0) {
                        $row['check_exam'] = '<i class="bi bi-check-circle-fill text-success fs-5" title="' . $exCount . ' Exam marks recorded"></i>';
                    } elseif (!empty($row['exam_skip_reason'])) {
                        $row['check_exam'] = '<i class="bi bi-info-circle-fill text-warning fs-5" title="Skipped: ' . $row['exam_skip_reason'] . '"></i>';
                    } else {
                        $row['check_exam'] = '<i class="bi bi-dash-circle text-muted opacity-50 fs-5" title="No exam recorded"></i>';
                    }
                } else {
                    $row['check_exam'] = '<i class="bi bi-dash-lg text-muted opacity-25"></i>';
                }

                // Checklist Icons
                $makeIcon = function ($val, $col, $id) {
                    $color = $val ? 'text-success' : 'text-danger opacity-50';
                    $icon = $val ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                    return "<i class='bi $icon $color fs-5 cursor-pointer btn-toggle-check' data-id='$id' data-col='$col' data-table='students' data-val='$val'></i>";
                };

                $row['check_mat'] = $makeIcon($row['checklist_material'], 'checklist_material', $row['id']);
                $row['check_grp'] = $makeIcon($row['checklist_group'], 'checklist_group', $row['id']);
                $row['check_team'] = $makeIcon($row['checklist_teams'], 'checklist_teams', $row['id']);

                $row['actions'] = '<a href="student_schedule.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary btn-gradient-mentor-alt">Manage</a>';

                $data[] = $row;
            }

            ob_clean();
            echo json_encode(['data' => $data]);
            exit;
    }
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add_schedule':
            $student_id = intval($_POST['student_id']);

            // 1. Fetch the mentor_id assigned to this student from the students table
            // This ensures the schedule is linked correctly regardless of who (Admin or Mentor) creates it.
            $stu_stmt = $conn->prepare('SELECT mentor_id FROM students WHERE id = ?');
            $stu_stmt->bind_param('i', $student_id);
            $stu_stmt->execute();
            $student_data = $stu_stmt->get_result()->fetch_assoc();

            if (!$student_data) {
                echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
                exit;
            }
            $student_mentor_id = $student_data['mentor_id'];

            // 2. Auto-Generate Schedule Name (e.g. "Schedule 2")
            $count_stmt = $conn->prepare('SELECT COUNT(*) as total FROM schedules WHERE student_id = ?');
            $count_stmt->bind_param('i', $student_id);
            $count_stmt->execute();
            $total = $count_stmt->get_result()->fetch_assoc()['total'];
            $auto_name = 'Schedule ' . ($total + 1);

            // 3. Get Paid & Total Hours
            $paid = !empty($_POST['paid_hours']) ? floatval($_POST['paid_hours']) : 0;
            $total_h = floatval($_POST['total_hours']);

            // Validation
            if ($paid > $total_h) {
                echo json_encode(['status' => 'error', 'message' => 'Paid hours cannot exceed Total hours.']);
                exit;
            }

            // 4. Insert
            // Use 'd' for double/float if hours can be decimal (1.5), otherwise 'i' for integer
            $stmt = $conn->prepare("INSERT INTO schedules (student_id, mentor_id, name, start_date, end_date, total_hours, paid_hours, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");

            $stmt->bind_param('iisssdd',
                $student_id,
                $student_mentor_id,
                $auto_name,
                $_POST['start_date'],
                $_POST['end_date'],
                $total_h,
                $paid);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'New schedule created: ' . $auto_name]);
            } else {
                // Return the specific SQL error for debugging if it still fails
                echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->error]);
            }
            break;
        case 'add_session':
            $schedule_id = intval($_POST['schedule_id']);
            $subject_id = $_POST['subject_id'];
            $teacher_id = $_POST['teacher_id'];
            $planned_hours = floatval($_POST['planned_hours']);

            // Handle dates
            $dates_string = $_POST['dates'];
            $dates_array = explode(', ', $dates_string);
            $dates_array = array_filter(array_map('trim', $dates_array));  // Clean empty entries
            $count_new_sessions = count($dates_array);

            // 1. Get Schedule Info
            $sch_query = $conn->query("SELECT total_hours, paid_hours FROM schedules WHERE id = $schedule_id");
            $sch_data = $sch_query->fetch_assoc();
            // $max_hours = floatval($sch_data['total_hours']); // Not strictly needed for this check
            $paid_hours = floatval($sch_data['paid_hours']);

            // 2. Get Currently Used Hours
            $sum_query = $conn->query("SELECT SUM(planned_hours) as used_hours FROM sessions WHERE schedule_id = $schedule_id");
            $sum_data = $sum_query->fetch_assoc();
            $used_hours = floatval($sum_data['used_hours'] ?? 0);

            // 3. Determine the Allowed Limit
            if ($paid_hours > 0) {
                // If they paid, the limit is what they paid
                $allowed_limit = $paid_hours;
                $limit_type = 'Paid Limit';
            } else {
                // If NOT paid, allow exactly 2 hours (Grace/Trial)
                $allowed_limit = 2.0;
                $limit_type = 'Unpaid Grace Limit (2 Hours)';
            }

            // 4. Check if New Sessions exceed the Limit
            $hours_to_add = $count_new_sessions * $planned_hours;
            $new_total = $used_hours + $hours_to_add;

            if ($new_total > $allowed_limit) {
                $remaining = max(0, $allowed_limit - $used_hours);

                // Custom error message based on situation
                if ($paid_hours <= 0) {
                    $msg = "Payment Pending: You can only schedule 2 grace hours before payment. Used: $used_hours. Remaining: $remaining.";
                } else {
                    $msg = "Limit Exceeded: Student paid for $paid_hours hours. Used: $used_hours. Remaining: $remaining.";
                }

                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit;
            }

            $stmt = $conn->prepare("INSERT INTO sessions (schedule_id, subject_id, teacher_id, date, start_time, planned_hours, status) VALUES (?, ?, ?, ?, NULL, ?, 'planned')");

            $successCount = 0;
            foreach ($dates_array as $date) {
                if (!empty($date)) {
                    $stmt->bind_param('iiisd', $schedule_id, $subject_id, $teacher_id, $date, $planned_hours);
                    if ($stmt->execute())
                        $successCount++;
                }
            }

            if ($successCount > 0) {
                echo json_encode(['status' => 'success', 'message' => "$successCount sessions scheduled successfully!"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to add sessions.']);
            }
            break;

        case 'add_exam':
            $stmt = $conn->prepare('INSERT INTO schedule_exams (schedule_id, subject_id, exam_date, obtained_mark) VALUES (?, ?, ?, ?)');
            // Using 's' for obtained_mark to allow flexible formatting
            $stmt->bind_param('iiss', $_POST['schedule_id'], $_POST['subject_id'], $_POST['exam_date'], $_POST['obtained_mark']);
            if ($stmt->execute())
                echo json_encode(['status' => 'success', 'message' => 'Mark recorded!']);
            break;

        case 'delete_exam':
            $stmt = $conn->prepare('DELETE FROM schedule_exams WHERE id = ?');
            $stmt->bind_param('i', $_POST['id']);
            if ($stmt->execute())
                echo json_encode(['status' => 'success']);
            break;

        case 'save_attendance':
            $session_id = intval($_POST['session_id']);
            $student_id = $_POST['student_id'];

            $status_attendance = $_POST['attendance_status'];  // 1 or 0
            $remarks = $_POST['remarks'];

            if ($status_attendance == '0' && empty($remarks)) {
                echo json_encode(['status' => 'error', 'message' => 'Please provide a reason for the absence.']);
                exit;
            }

            // Determine Time & Hours based on status
            if ($status_attendance == '0') {
                // ABSENT
                $actual_time = null;
                $actual_hours = 0;  // Or keep planned hours if you charge for absence? Usually 0 or full duration.
                // NOTE: If you want Absent sessions to still consume Schedule Hours, set this to floatval($_POST['actual_hours'])
                // Based on your previous prompt "hour is 0", we use 0.
                $session_status = 'missed';
                $minutes = 0;
            } else {
                // PRESENT
                $actual_time = !empty($_POST['actual_start_time']) ? $_POST['actual_start_time'] : null;
                $actual_hours = floatval($_POST['actual_hours']);
                $session_status = 'done';
                $minutes = $actual_hours * 60;
            }

            // --- VALIDATION: CHECK HOURS LIMIT (Only if Present/Hours > 0) ---
            if ($actual_hours > 0) {
                // 1. Get Session's Schedule ID and OLD hours (to subtract)
                $sess_query = $conn->query("SELECT schedule_id, planned_hours FROM sessions WHERE id = $session_id");
                $sess_data = $sess_query->fetch_assoc();
                $sch_id = $sess_data['schedule_id'];
                $old_hours = floatval($sess_data['planned_hours']);

                // 2. Get Schedule Total
                $sch_query = $conn->query("SELECT total_hours FROM schedules WHERE id = $sch_id");
                $total_limit = floatval($sch_query->fetch_assoc()['total_hours']);

                // 3. Get Current Total Used
                $sum_query = $conn->query("SELECT SUM(planned_hours) as used FROM sessions WHERE schedule_id = $sch_id");
                $current_used = floatval($sum_query->fetch_assoc()['used'] ?? 0);

                // 4. Calculate New Projection
                // (Current Used - This Session's Old Value) + New Value
                $projected_total = ($current_used - $old_hours) + $actual_hours;

                if ($projected_total > $total_limit) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Validation Error: This duration ($actual_hours hrs) exceeds the schedule limit. Remaining budget: " . ($total_limit - ($current_used - $old_hours)) . ' hrs.'
                    ]);
                    exit;
                }
            }
            // -------------------------------------

            $conn->begin_transaction();
            try {
                // 1. Update Session
                $update_stmt = $conn->prepare('UPDATE sessions SET status = ?, start_time = ?, planned_hours = ? WHERE id = ?');
                $update_stmt->bind_param('ssdi', $session_status, $actual_time, $actual_hours, $session_id);
                $update_stmt->execute();

                // 2. Delete old attendance
                $conn->query("DELETE FROM attendance WHERE session_id = $session_id");

                // 3. Insert Attendance Log
                $att_stmt = $conn->prepare('INSERT INTO attendance (session_id, student_id, hour_index, taught_minutes, attended, reason) VALUES (?, ?, ?, ?, ?, ?)');

                $hour_index = 0;

                $att_stmt->bind_param('iiiiis', $session_id, $student_id, $hour_index, $minutes, $status_attendance, $remarks);
                $att_stmt->execute();

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Session updated successfully.']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

        case 'edit_schedule':
            $schedule_id = intval($_POST['schedule_id']);
            // Validate inputs
            if (intval($_POST['paid_hours']) > intval($_POST['total_hours'])) {
                echo json_encode(['status' => 'error', 'message' => 'Paid hours cannot exceed Total hours.']);
                exit;
            }

            $stmt = $conn->prepare('UPDATE schedules SET name=?, start_date=?, end_date=?, total_hours=?, paid_hours=? WHERE id=?');
            $stmt->bind_param('sssiii',
                $_POST['name'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['total_hours'],
                $_POST['paid_hours'],
                $schedule_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Schedule updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Update failed.']);
            }
            break;

        case 'toggle_checklist':
            // Allow Mentors to update checklist statuses
            $id = intval($_POST['id']);
            $col = $_POST['col'];
            $table = $_POST['table'];
            $val = intval($_POST['val']);
            $newVal = $val ? 0 : 1;

            $allowed_students = ['checklist_material', 'checklist_group', 'checklist_teams'];
            $allowed_schedules = [''];

            $stmt = null;
            if ($table === 'students' && in_array($col, $allowed_students)) {
                $stmt = $conn->prepare("UPDATE students SET $col = ? WHERE id = ?");
            } elseif ($table === 'schedules' && in_array($col, $allowed_schedules)) {
                $stmt = $conn->prepare("UPDATE schedules SET $col = ? WHERE id = ?");
            }

            if ($stmt) {
                $stmt->bind_param('ii', $newVal, $id);
                $stmt->execute();
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
            }
            break;
    }
}
$conn->close();
?>