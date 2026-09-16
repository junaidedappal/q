<?php
session_start();
ob_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Only 'mentor' and 'admin' can access
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'mentor', 'supervisor'])) {
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
            $user_id = $_SESSION['id'];
            $user_role = $_SESSION['role'];
        
            // SECURITY CHECK FOR SUPERVISOR
            if ($user_role === 'supervisor') {
                $check_stmt = $conn->prepare("
                    SELECT s.id FROM students s
                    JOIN mentors m ON s.mentor_id = m.id
                    WHERE s.id = ? AND m.supervisor_id = ?
                ");
                $check_stmt->bind_param("ii", $student_id, $user_id);
                $check_stmt->execute();
                if (!$check_stmt->get_result()->fetch_assoc()) {
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: This student does not belong to your mentor group.']);
                    exit;
                }
            }
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

            $demo_stmt = $conn->prepare('
            SELECT md.*, s.name as subject_name, u.name as teacher_name 
            FROM mentor_demos md
            JOIN subjects s ON md.subject_id = s.id
            JOIN teachers t ON md.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE md.student_id = ? 
            ORDER BY md.demo_date DESC
        ');
            $demo_stmt->bind_param('i', $student_id);
            $demo_stmt->execute();
            $data['mentor_demos'] = $demo_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'get_default_pay':
            $schedule_id = intval($_GET['schedule_id']);

            // Change g.teacher_pay_per_hour to g.payment_per_hour
            $query = $conn->prepare('
                    SELECT g.payment_per_hour 
                    FROM grades g 
                    JOIN students s ON s.class = g.grade_name 
                    JOIN schedules sch ON sch.student_id = s.id 
                    WHERE sch.id = ? 
                    LIMIT 1
                ');
            $query->bind_param('i', $schedule_id);
            $query->execute();
            $result = $query->get_result()->fetch_assoc();

            if ($result) {
                // Use the correct array key here too
                echo json_encode(['status' => 'success', 'pay_rate' => $result['payment_per_hour']]);
            } else {
                echo json_encode(['status' => 'error', 'pay_rate' => 0]);
            }
            exit;

            
            // --- NEW CASE: Fetch Single Demo for Edit ---
        case 'get_single_mentor_demo':
            $demo_id = intval($_GET['demo_id']);
            $stmt = $conn->prepare("SELECT * FROM mentor_demos WHERE id = ?");
            $stmt->bind_param("i", $demo_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            ob_clean();
            echo json_encode($result ? ['status' => 'success', 'data' => $result] : ['status' => 'error']);
            exit;

        case 'check_pending_exams':
            $student_id = intval($_GET['student_id']);
            // Look for schedules where:
            // 1. No exam marks exist in schedule_exams
            // 2. AND exam_skip_reason is empty/null
            $stmt = $conn->prepare("
                    SELECT sch.id, sch.name 
                    FROM schedules sch 
                    LEFT JOIN schedule_exams ex ON sch.id = ex.schedule_id 
                    WHERE sch.student_id = ? 
                    AND (sch.exam_skip_reason IS NULL OR sch.exam_skip_reason = '')
                    GROUP BY sch.id 
                    HAVING COUNT(ex.id) = 0
                ");
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $pending = [];
            while ($row = $result->fetch_assoc()) {
                $pending[] = $row;
            }

            ob_clean();
            echo json_encode(['status' => 'success', 'pending' => $pending]);
            exit;

        case 'get_single_schedule':
            try {
                $schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;

                if ($schedule_id <= 0) {
                    throw new Exception('Invalid Schedule ID.');
                }

                // 1. Fetch the basic record
                $stmt = $conn->prepare('SELECT * FROM schedules WHERE id = ?');
                $stmt->execute([$schedule_id]);
                $result = $stmt->get_result()->fetch_assoc();

                if (!$result) {
                    throw new Exception('Record not found.');
                }

                // 2. Calculate Sequential Position
                // We renamed the alias from 'rank' to 'seq_number' to avoid SQL syntax errors
                $sid = $result['student_id'];
                $rank_stmt = $conn->prepare('SELECT COUNT(*) as seq_number FROM schedules WHERE student_id = ? AND id <= ?');
                $rank_stmt->bind_param('ii', $sid, $schedule_id);
                $rank_stmt->execute();
                $rank_data = $rank_stmt->get_result()->fetch_assoc();

                // Use the new alias 'seq_number'
                $result['name'] = 'Schedule ' . ($rank_data['seq_number'] ?? 1);

                ob_clean();
                echo json_encode(['status' => 'success', 'data' => $result]);
            } catch (Exception $e) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_single_session':
            $session_id = intval($_GET['session_id']);

            // We fetch notes and teacher_pay directly from your session table structure
            $stmt = $conn->prepare('
                    SELECT 
                        s.id,
                        s.teacher_pay, 
                        s.notes, 
                        s.status,
                        s.planned_hours,
                        s.start_time,
                        g.payment_per_hour as default_grade_pay
                    FROM sessions s
                    JOIN schedules sch ON s.schedule_id = sch.id
                    JOIN students stu ON sch.student_id = stu.id
                    LEFT JOIN grades g ON stu.class = g.grade_name
                    WHERE s.id = ?
                ');
            $stmt->bind_param('i', $session_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_assoc();

            if ($data) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'teacher_pay' => $data['teacher_pay'],
                        'notes' => $data['notes'],
                        'status' => $data['status'],
                        'planned_hours' => $data['planned_hours'],
                        'start_time' => $data['start_time'],
                        'default_pay' => $data['default_grade_pay']
                    ]
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Session not found']);
            }
            exit;

        // case 'get_teachers_and_subjects':
        //     $data = [];
        //     $data['teachers'] = $conn->query("SELECT t.id, u.name FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 'active'")->fetch_all(MYSQLI_ASSOC);
        //     $data['subjects'] = $conn->query('SELECT id, name FROM subjects')->fetch_all(MYSQLI_ASSOC);
        //     echo json_encode(['status' => 'success', 'data' => $data]);
        //     break;

        case 'get_teachers_and_subjects':
            $data = [];
            // Added t.teacher_reg_code to the SELECT
            $data['teachers'] = $conn->query("SELECT t.id, u.name, t.teacher_reg_code FROM teachers t JOIN users u ON t.user_id = u.id WHERE u.status = 'active' ORDER BY u.name ASC")->fetch_all(MYSQLI_ASSOC);
            $data['subjects'] = $conn->query('SELECT id, name FROM subjects')->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        // ***** THIS IS THE RESTORED DEFAULT CASE *****
        default:
            // === MAIN TABLE QUERY FOR MENTOR DASHBOARD (LATEST SCHEDULE ONLY) ===
            $user_role = $_SESSION['role'];
            $params = [];
            $types = '';

            $sql = "SELECT 
            s.id, s.name, s.class, s.status, s.fee_status,
            s.checklist_material, s.checklist_group, s.checklist_teams,
            COALESCE(u.name, 'Not Assigned') as mentor_name,
            sch.id as schedule_id, 
            sch.start_date, 
            sch.end_date,
            COALESCE(sch.total_hours, 0) as total_hours,
            COALESCE(sch.paid_hours, 0) as paid_hours,
            (SELECT COUNT(*) FROM sessions sess WHERE sess.schedule_id = sch.id AND sess.status = 'done') as completed_hours
        FROM students s
        LEFT JOIN mentors m ON s.mentor_id = m.id
        LEFT JOIN users u ON m.user_id = u.id
        /* JOIN only the LATEST schedule for each student */
        LEFT JOIN schedules sch ON sch.id = (
            SELECT id FROM schedules 
            WHERE student_id = s.id 
            ORDER BY id DESC LIMIT 1
        )
        WHERE 1=1";

            // FILTER: If user is Mentor, restrict to their students
            if ($user_role === 'mentor') {
                $mentor_user_id = $_SESSION['id'];
                $mentor_stmt = $conn->prepare('SELECT id FROM mentors WHERE user_id = ?');
                $mentor_stmt->bind_param('i', $mentor_user_id);
                $mentor_stmt->execute();
                $mentor_id = $mentor_stmt->get_result()->fetch_assoc()['id'] ?? 0;

                $sql .= ' AND s.mentor_id = ?';
                $params[] = $mentor_id;
                $types .= 'i';
            }

            $sql .= ' ORDER BY s.id DESC';
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                // Formatting logic
                $statusClass = match ($row['status']) {
                    'active' => 'bg-primary',
                    'inactive' => 'bg-secondary',
                    'ready' => 'bg-info text-dark',
                    'break' => 'bg-warning text-dark',
                    default => 'bg-secondary'
                };
                $row['status_badge'] = '<span class="badge ' . $statusClass . '">' . ucfirst($row['status']) . '</span>';

                $paid = intval($row['paid_hours']);
                $total = intval($row['total_hours']);
                if ($total == 0)
                    $feeHtml = '<span class="badge bg-secondary">No Schedule</span>';
                elseif ($paid == 0)
                    $feeHtml = '<span class="badge bg-danger">Pending</span>';
                elseif ($paid >= $total)
                    $feeHtml = '<span class="badge bg-success">Paid</span>';
                else
                    $feeHtml = '<span class="badge bg-warning text-dark">PARTIAL ' . $paid . '/' . $total . '</span>';
                $row['fee_display'] = $feeHtml;

                if ($row['start_date'] && $row['end_date']) {
                    $s = date('d M', strtotime($row['start_date']));
                    $e = date('d M', strtotime($row['end_date']));
                    $row['schedule_dates'] = "<span class='fw-bold text-dark' style='font-size:0.8rem'>$s - $e</span>";
                } else {
                    $row['schedule_dates'] = '<span class="badge bg-light text-secondary border">No Schedule</span>';
                }

                $row['hr_display'] = "<b>{$row['completed_hours']}</b> / {$row['total_hours']}";

                $makeIcon = function ($val, $col, $id, $table) {
                    if ($table == 'schedules' && ($id == 0 || $id == null))
                        return '<i class="bi bi-dash-lg text-muted opacity-25"></i>';
                    $color = $val ? 'text-success' : 'text-danger opacity-50';
                    $icon = $val ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                    return "<i class='bi $icon $color fs-5 cursor-pointer btn-toggle-check' data-id='$id' data-col='$col' data-table='$table' data-val='$val'></i>";
                };

                $row['check_mat'] = $makeIcon($row['checklist_material'], 'checklist_material', $row['id'], 'students');
                $row['check_grp'] = $makeIcon($row['checklist_group'], 'checklist_group', $row['id'], 'students');
                $row['check_team'] = $makeIcon($row['checklist_teams'], 'checklist_teams', $row['id'], 'students');
                $row['actions'] = '<a href="student_schedule.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary btn-gradient-mentor-alt"><i class="bi bi-calendar2-week-fill me-1"></i> Manage</a>';

                $data[] = $row;
            }

            // CLEAN THE BUFFER AND EXIT
            ob_clean();
            echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
            exit;
    }
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($_SESSION['role'] === 'supervisor') {
        echo json_encode(['status' => 'error', 'message' => 'Permission Denied: Supervisors have view-only access.']);
        exit;
    }
    switch ($action) {
        
        case 'add_schedule':
            $student_id = intval($_POST['student_id']);

            // 1. Fetch Student/Mentor Info
            $stu_stmt = $conn->prepare('SELECT mentor_id FROM students WHERE id = ?');
            $stu_stmt->bind_param('i', $student_id);
            $stu_stmt->execute();
            $student_data = $stu_stmt->get_result()->fetch_assoc();
            if (!$student_data) {
                echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
                exit;
            }
            $student_mentor_id = $student_data['mentor_id'];

            // 2. Count ONLY this student's schedules to get the NEXT sequence
            $count_stmt = $conn->prepare('SELECT COUNT(*) as total FROM schedules WHERE student_id = ?');
            $count_stmt->bind_param('i', $student_id);
            $count_stmt->execute();
            $total = $count_stmt->get_result()->fetch_assoc()['total'];
            $auto_name = 'Schedule ' . ($total + 1);

            // 3. Validation & Vars
            $paid = !empty($_POST['paid_hours']) ? floatval($_POST['paid_hours']) : 0;
            $total_h = floatval($_POST['total_hours']);
            if ($paid > $total_h) {
                echo json_encode(['status' => 'error', 'message' => 'Paid hours cannot exceed Total.']);
                exit;
            }

            // 4. Insert
            $stmt = $conn->prepare("INSERT INTO schedules (student_id, mentor_id, name, start_date, end_date, total_hours, paid_hours, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param('iisssdd', $student_id, $student_mentor_id, $auto_name, $_POST['start_date'], $_POST['end_date'], $total_h, $paid);

            ob_clean();
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'New schedule created: ' . $auto_name]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->error]);
            }
            exit;

        case 'delete_schedule':
            $schedule_id = intval($_POST['schedule_id']);
            $user_role = $_SESSION['role'];

            // 1. Fetch Schedule Info (to check paid_hours), Session Count, and Exam Count
            // We do this in one query using subqueries for efficiency
            $check_stmt = $conn->prepare('
        SELECT 
            sch.paid_hours,
            (SELECT COUNT(*) FROM sessions WHERE schedule_id = sch.id) as session_count,
            (SELECT COUNT(*) FROM schedule_exams WHERE schedule_id = sch.id) as exam_count
        FROM schedules sch
        WHERE sch.id = ?
    ');
            $check_stmt->bind_param('i', $schedule_id);
            $check_stmt->execute();
            $sch_data = $check_stmt->get_result()->fetch_assoc();

            if (!$sch_data) {
                echo json_encode(['status' => 'error', 'message' => 'Schedule not found.']);
                exit;
            }

            $paid_hours = floatval($sch_data['paid_hours']);
            $session_count = intval($sch_data['session_count']);
            $exam_count = intval($sch_data['exam_count']);

            // 2. Logic: If user is a Mentor, check all blocking conditions
            if ($user_role === 'mentor') {
                $reasons = [];

                if ($paid_hours > 0) {
                    $reasons[] = "it has recorded payments (Paid Hours: $paid_hours)";
                }
                if ($session_count > 0) {
                    $reasons[] = "it contains $session_count sessions";
                }
                if ($exam_count > 0) {
                    $reasons[] = "it has $exam_count exam marks recorded";
                }

                // If any reasons exist, block the mentor
                if (!empty($reasons)) {
                    $msg = 'Restricted: You cannot delete this schedule because ' . implode(', and ', $reasons) . '. Only an Admin can delete this data.';
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit;
                }
            }

            // 3. If code reaches here, it's either an Admin OR a completely empty/unpaid schedule for a Mentor
            $del_stmt = $conn->prepare('DELETE FROM schedules WHERE id = ?');
            $del_stmt->bind_param('i', $schedule_id);

            if ($del_stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Schedule deleted successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            }
            break;

        case 'add_mentor_demo':
            $student_id = intval($_POST['student_id']);
            $teacher_id = intval($_POST['teacher_id']);
            $subject_id = intval($_POST['subject_id']);
            $demo_date = $_POST['demo_date'];
            $duration = floatval($_POST['duration_hours']);

            // We set default outcome as 'interested' and empty feedback as per your simplified request
            $outcome = 'interested';
            $feedback = 'Independent Mentor Demo';

            $stmt = $conn->prepare('INSERT INTO mentor_demos (student_id, teacher_id, subject_id, demo_date, duration_hours, outcome, feedback) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiisdss', $student_id, $teacher_id, $subject_id, $demo_date, $duration, $outcome, $feedback);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Demo recorded successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            }
            exit;


        // --- NEW CASE: Update Demo ---
        case 'edit_mentor_demo':
            if ($_SESSION['role'] !== 'admin') {
                echo json_encode(['status' => 'error', 'message' => 'Only Admin can edit demo records.']);
                exit;
            }
            $stmt = $conn->prepare("UPDATE mentor_demos SET subject_id=?, teacher_id=?, demo_date=?, duration_hours=? WHERE id=?");
            $stmt->bind_param("iisdi", $_POST['subject_id'], $_POST['teacher_id'], $_POST['demo_date'], $_POST['duration_hours'], $_POST['demo_id']);
            
            ob_clean();
            echo json_encode($stmt->execute() ? ['status' => 'success', 'message' => 'Demo updated!'] : ['status' => 'error']);
            exit;

        // --- NEW CASE: Delete Demo ---
        case 'delete_mentor_demo':
            if ($_SESSION['role'] !== 'admin') {
                echo json_encode(['status' => 'error', 'message' => 'Only Admin can delete demo records.']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM mentor_demos WHERE id = ?");
            $stmt->bind_param("i", $_POST['demo_id']);
            
            ob_clean();
            echo json_encode($stmt->execute() ? ['status' => 'success', 'message' => 'Demo deleted.'] : ['status' => 'error']);
            exit;
        case 'add_session':
            $schedule_id = intval($_POST['schedule_id']);
            $subject_id = intval($_POST['subject_id']);
            $teacher_id = intval($_POST['teacher_id']);
            $planned_hours = floatval($_POST['planned_hours']);
            $teacher_pay = !empty($_POST['teacher_pay']) ? floatval($_POST['teacher_pay']) : 0;

            // 1. Fetch Schedule Info, Dates, and Student Schedule Count in one go
            $sch_query = $conn->prepare('
                SELECT student_id, paid_hours, start_date, end_date,
                (SELECT COUNT(*) FROM schedules WHERE student_id = s.student_id) as sch_count
                FROM schedules s 
                WHERE id = ?
            ');
            $sch_query->bind_param('i', $schedule_id);
            $sch_query->execute();
            $sch_data = $sch_query->get_result()->fetch_assoc();

            if (!$sch_data) {
                echo json_encode(['status' => 'error', 'message' => 'Schedule not found.']);
                exit;
            }

            // 2. STRICT DATE EXISTENCE CHECK
            if (empty($sch_data['start_date']) || empty($sch_data['end_date']) || $sch_data['start_date'] == '0000-00-00') {
                echo json_encode(['status' => 'error', 'message' => 'Incomplete Schedule: Please set the Start and End dates in "Edit Schedule Details" first.']);
                exit;
            }

            // 3. DETERMINE ALLOWED HOURS LIMIT
            $student_id = $sch_data['student_id'];
            $paid_hours = floatval($sch_data['paid_hours']);
            $sch_count = intval($sch_data['sch_count']);

            if ($paid_hours > 0) {
                $allowed_limit = $paid_hours;
            } else {
                // Rule: New student (1st schedule) = 0 grace, Old student = 2.0 grace
                $allowed_limit = ($sch_count <= 1) ? 0 : 2.0;
            }

            // 4. CALCULATE PROPOSED HOURS
            $dates_string = $_POST['dates'];
            $dates_array = array_filter(array_map('trim', explode(', ', $dates_string)));
            $hours_to_add = count($dates_array) * $planned_hours;

            // 5. CHECK CURRENT USAGE IN THIS SCHEDULE
            $sum_query = $conn->prepare('SELECT SUM(planned_hours) as used FROM sessions WHERE schedule_id = ?');
            $sum_query->bind_param('i', $schedule_id);
            $sum_query->execute();
            $used_hours = floatval($sum_query->get_result()->fetch_assoc()['used'] ?? 0);

            $new_total = $used_hours + $hours_to_add;

            // 6. VALIDATE HOURS LIMIT
            if ($new_total > $allowed_limit) {
                if ($paid_hours <= 0) {
                    $msg = ($sch_count <= 1)
                        ? "Payment Required: As this is the student's first schedule, sessions cannot be added until payment is recorded."
                        : "Grace Limit Reached: For unpaid schedules, only 2 grace hours allowed. Total used: $used_hours, Trying to add: $hours_to_add.";
                } else {
                    $msg = "Limit Exceeded: Student paid for $paid_hours hours. This action would result in $new_total total hours.";
                }
                echo json_encode(['status' => 'error', 'message' => $msg]);
                exit;
            }

            // 7. VALIDATE DATE RANGE (Compare every session date to schedule window)
            $start_ts = strtotime($sch_data['start_date']);
            $end_ts = strtotime($sch_data['end_date']);

            foreach ($dates_array as $date) {
                $session_ts = strtotime($date);
                if ($session_ts < $start_ts || $session_ts > $end_ts) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Date Error: Session date ($date) is outside the allowed range for this schedule (" . $sch_data['start_date'] . ' to ' . $sch_data['end_date'] . ').'
                    ]);
                    exit;
                }
            }

            // 8. GET TEACHER PAY (Fallback to grades table if input was 0)
            if ($teacher_pay <= 0) {
                $rate_stmt = $conn->prepare('SELECT g.payment_per_hour FROM grades g JOIN students s ON s.class = g.grade_name WHERE s.id = ? LIMIT 1');
                $rate_stmt->bind_param('i', $student_id);
                $rate_stmt->execute();
                $teacher_pay = $rate_stmt->get_result()->fetch_assoc()['payment_per_hour'] ?? 0;
            }

            // 9. INSERT SESSIONS
            $stmt = $conn->prepare("INSERT INTO sessions (schedule_id, subject_id, teacher_id, date, planned_hours, teacher_pay, status) VALUES (?, ?, ?, ?, ?, ?, 'planned')");
            $successCount = 0;
            foreach ($dates_array as $date) {
                $stmt->bind_param('iiisdd', $schedule_id, $subject_id, $teacher_id, $date, $planned_hours, $teacher_pay);
                if ($stmt->execute())
                    $successCount++;
            }

            ob_clean();
            echo json_encode(['status' => 'success', 'message' => "$successCount sessions scheduled successfully!"]);
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

        case 'submit_exam_skip_reason':
            $schedule_id = intval($_POST['schedule_id']);
            $reason = trim($_POST['reason']);

            if (empty($reason)) {
                echo json_encode(['status' => 'error', 'message' => 'Reason is required to proceed.']);
                exit;
            }

            $stmt = $conn->prepare('UPDATE schedules SET exam_skip_reason = ? WHERE id = ?');
            $stmt->bind_param('si', $reason, $schedule_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Reason recorded.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'DB Error.']);
            }
            exit;

        case 'save_attendance':
            $session_id = intval($_POST['session_id']);
            $user_role = $_SESSION['role'];

            // 1. ACCOUNTS LOCK CHECK
            if ($user_role != 'admin') {
                $date_query = $conn->prepare('SELECT date FROM sessions WHERE id = ?');
                $date_query->bind_param('i', $session_id);
                $date_query->execute();
                $sess_date = $date_query->get_result()->fetch_assoc()['date'];

                $current_month = date('Y-m');
                $session_month = date('Y-m', strtotime($sess_date));

                // if ($session_month < $current_month) {
                //     echo json_encode([
                //         'status' => 'error',
                //         'message' => 'Access Denied: Attendance for previous months is locked because accounts have been cleared. Contact Admin for changes.'
                //     ]);
                //     exit;
                // }
            }
            $student_id = intval($_POST['student_id']);
            $status_attendance = $_POST['attendance_status'];  // '1' or '0'

            // Capture inputs
            $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : '';
            $remarks = $notes;  // Used for attendance 'reason' column
            $teacher_pay = !empty($_POST['teacher_pay']) ? floatval($_POST['teacher_pay']) : 0;

            // 1. Absence Validation
            if ($status_attendance == '0' && empty($remarks)) {
                echo json_encode(['status' => 'error', 'message' => 'Please provide a reason for the absence.']);
                exit;
            }

            // 2. Fetch Schedule Context for Hour Validation
            $sch_stmt = $conn->prepare('
                SELECT sch.id, sch.paid_hours,
                (SELECT COUNT(*) FROM schedules WHERE student_id = sch.student_id) as sch_count
                FROM schedules sch
                JOIN sessions s ON s.schedule_id = sch.id
                WHERE s.id = ?
            ');
            $sch_stmt->bind_param('i', $session_id);
            $sch_stmt->execute();
            $sch_data = $sch_stmt->get_result()->fetch_assoc();

            $schedule_id = $sch_data['id'];
            $paid_hours = floatval($sch_data['paid_hours']);
            $sch_count = intval($sch_data['sch_count']);

            // 3. Determine the Allowed Limit
            if ($paid_hours > 0) {
                $allowed_limit = $paid_hours;
            } else {
                // Rule: First schedule = 0, Subsequent = 2.0 grace
                $allowed_limit = ($sch_count <= 1) ? 0 : 2.0;
            }

            // 4. Calculate Minutes and Status
            if ($status_attendance == '0') {
                $actual_time = null;
                $actual_hours = 0;
                $session_status = 'missed';
                $minutes = 0;
            } else {
                $actual_time = !empty($_POST['actual_start_time']) ? $_POST['actual_start_time'] : null;
                $actual_hours = floatval($_POST['actual_hours']);
                $session_status = 'done';
                $minutes = $actual_hours * 60;
            }

            // 5. VALIDATION: Check if this marking exceeds the limit
            if ($status_attendance == '1') {
                // Sum hours of OTHER sessions already marked 'done' in this schedule
                $usage_stmt = $conn->prepare("SELECT SUM(planned_hours) as used FROM sessions WHERE schedule_id = ? AND status = 'done' AND id != ?");
                $usage_stmt->bind_param('ii', $schedule_id, $session_id);
                $usage_stmt->execute();
                $already_done_hours = floatval($usage_stmt->get_result()->fetch_assoc()['used'] ?? 0);

                if (($already_done_hours + $actual_hours) > $allowed_limit) {
                    $remaining = max(0, $allowed_limit - $already_done_hours);
                    if ($paid_hours <= 0) {
                        $msg = ($sch_count <= 1)
                            ? "Payment Required: This is the first schedule. No classes can be marked 'Present' until payment is updated."
                            : "Grace Limit Reached: Unpaid schedules are limited to 2.0 grace hours. Remaining: $remaining hrs.";
                    } else {
                        $msg = "Limit Exceeded: Student paid for $paid_hours hours. Remaining budget for this schedule: $remaining hrs.";
                    }
                    echo json_encode(['status' => 'error', 'message' => $msg]);
                    exit;
                }
            }

            // 6. DB Transaction for Update and Insertion
            $conn->begin_transaction();
            try {
                // Update Session Details
                $update_stmt = $conn->prepare('UPDATE sessions SET status = ?, start_time = ?, planned_hours = ?, teacher_pay = ?, notes = ? WHERE id = ?');
                $update_stmt->bind_param('ssddsi', $session_status, $actual_time, $actual_hours, $teacher_pay, $notes, $session_id);
                $update_stmt->execute();

                // Clear old attendance entry if re-marking
                $conn->query("DELETE FROM attendance WHERE session_id = $session_id");

                // Insert into Attendance Table
                $att_stmt = $conn->prepare('INSERT INTO attendance (session_id, student_id, hour_index, taught_minutes, attended, reason) VALUES (?, ?, 0, ?, ?, ?)');
                // Params: session_id(i), student_id(i), taught_minutes(i), attended(i), reason(s)
                $att_stmt->bind_param('iiiis', $session_id, $student_id, $minutes, $status_attendance, $remarks);
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

            // Use floatval to support decimals (e.g. 1.5 hours)
            $name = trim($_POST['name']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $total_h = floatval($_POST['total_hours']);
            $paid_h = floatval($_POST['paid_hours']);

            // Validation
            if ($paid_h > $total_h) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Paid hours cannot exceed Total hours.']);
                exit;
            }

            // Params: s (name), s (start), s (end), d (total), d (paid), i (id)
            $stmt = $conn->prepare('UPDATE schedules SET name=?, start_date=?, end_date=?, total_hours=?, paid_hours=? WHERE id=?');
            $stmt->bind_param('sssddi', $name, $start_date, $end_date, $total_h, $paid_h, $schedule_id);

            ob_clean();
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Schedule updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $conn->error]);
            }
            exit;

        case 'delete_session':
            $session_id = intval($_POST['session_id']);
            $user_role = $_SESSION['role'];

            if ($user_role != 'admin') {
                $date_query = $conn->prepare('SELECT date FROM sessions WHERE id = ?');
                $date_query->bind_param('i', $session_id);
                $date_query->execute();
                $sess_date = $date_query->get_result()->fetch_assoc()['date'];

                if (date('Y-m', strtotime($sess_date)) < date('Y-m')) {
                    echo json_encode(['status' => 'error', 'message' => 'Cannot delete sessions from previous months.']);
                    exit;
                }
            }

            // 1. Safety Check: Verify the session exists and is still 'planned'
            $stmt = $conn->prepare('SELECT status FROM sessions WHERE id = ?');
            $stmt->bind_param('i', $session_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();

            if (!$result) {
                echo json_encode(['status' => 'error', 'message' => 'Session not found.']);
                exit;
            }

            if ($result['status'] !== 'planned') {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Restricted: You cannot delete a session that has already been marked as Done or Missed.'
                ]);
                exit;
            }

            // 2. Perform deletion
            $del_stmt = $conn->prepare('DELETE FROM sessions WHERE id = ?');
            $del_stmt->bind_param('i', $session_id);

            if ($del_stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Planned session removed successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
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