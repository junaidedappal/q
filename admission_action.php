<?php
// admission_action.php
ob_clean();
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_mentors':
            $result = $conn->query("SELECT m.id, u.name FROM mentors m JOIN users u ON m.user_id = u.id WHERE u.status = 'active' ORDER BY u.name ASC");
            $mentors = [];
            while ($row = $result->fetch_assoc()) {
                $mentors[] = $row;
            }
            echo json_encode(['status' => 'success', 'mentors' => $mentors]);
            break;

        case 'get_enquiry_details':
            $id = intval($_GET['enquiry_id']);
            $stmt = $conn->prepare('SELECT * FROM enquiries WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            if ($data)
                $data['selected_plan'] = $data['selected_plan'] ?? 'Not Selected';
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        case 'get_student_edit_details':
            $sid = intval($_GET['student_id']);

            // Fetch student details and their LATEST schedule, matching the logic of your dashboard
            $stmt = $conn->prepare('
                SELECT s.id, s.name, s.mentor_id, s.status,
                       sch.id as schedule_id, sch.start_date, sch.end_date, sch.total_hours, sch.paid_hours
                FROM students s
                LEFT JOIN schedules sch ON sch.id = (
                    SELECT id FROM schedules WHERE student_id = s.id ORDER BY start_date DESC, id DESC LIMIT 1
                )
                WHERE s.id = ?
            ');
            $stmt->bind_param('i', $sid);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            ob_clean();  // Ensuring no invisible spaces break your JSON
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        default:
            $sql = "
            (SELECT 
                e.id AS record_id, 'enquiry' AS source, 
                e.student_name AS name, e.class, '—' AS mentor_name, e.status, 
                0 as total_hours, 0 as paid_hours, 0 as completed_hours, 
                NULL as start_date, NULL as end_date,
                0 as checklist_material, 0 as checklist_group, 0 as checklist_teams, 0 as exam_count,
                0 as student_id, 0 as schedule_id
            FROM enquiries e WHERE e.status = 'admission_confirmed')
            UNION ALL
            (SELECT 
                s.id AS record_id, 'student' AS source, 
                s.name, s.class, COALESCE(u.name, 'Not Assigned') AS mentor_name, s.status,
                COALESCE(sch.total_hours, 0) as total_hours,
                COALESCE(sch.paid_hours, 0) as paid_hours,
                (SELECT COALESCE(SUM(planned_hours),0) FROM sessions sess WHERE sess.schedule_id = sch.id AND sess.status = 'done') as completed_hours,
                sch.start_date, sch.end_date,
                s.checklist_material, s.checklist_group, s.checklist_teams, 
                (SELECT COUNT(*) FROM schedule_exams ex WHERE ex.schedule_id = sch.id) as exam_count,
                s.id AS student_id, COALESCE(sch.id, 0) AS schedule_id
            FROM students s 
            LEFT JOIN mentors m ON s.mentor_id = m.id 
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN schedules sch ON sch.id = (
                SELECT id FROM schedules WHERE student_id = s.id ORDER BY start_date DESC, id DESC LIMIT 1
            )
            ) ORDER BY record_id DESC";

            $result = $conn->query($sql);
            $data = [];

            while ($row = $result->fetch_assoc()) {
                $makeIcon = function ($val, $col, $id) {
                    $color = $val ? 'text-success' : 'text-danger opacity-50';
                    $icon = $val ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                    return "<i class='bi $icon $color fs-5 cursor-pointer btn-toggle-check' data-id='$id' data-col='$col' data-table='students' data-val='$val'></i>";
                };

                if ($row['source'] === 'enquiry') {
                    $row['status_badge'] = '<span class="badge rounded-pill bg-success">New</span>';
                    $row['actions'] = '<button class="btn btn-sm btn-primary btn-finalize" data-id="' . $row['record_id'] . '"><i class="bi bi-person-plus-fill"></i> Admit</button>';
                    $row['hr_display'] = '-';
                    $row['fee_display'] = '-';
                    $row['schedule_dates'] = '-';
                    $row['check_mat'] = $row['check_grp'] = $row['check_team'] = $row['check_exam'] = '<i class="bi bi-dash-lg text-muted opacity-25"></i>';
                } else {
                    $paid = floatval($row['paid_hours']);
                    $total = floatval($row['total_hours']);
                    // ENSURE RAW NUMBERS ARE SAVED FOR JS
                    $compHours = floatval($row['completed_hours']);
                    $totalHours = floatval($row['total_hours']);
                    $row['completed_hours'] = $compHours; 
                    $row['total_hours'] = $totalHours;
                    if ($total == 0)
                        $row['fee_display'] = '<span class="badge bg-secondary">No Schedule</span>';
                    elseif ($paid >= $total)
                        $row['fee_display'] = '<span class="badge bg-success">Paid</span>';
                    elseif ($paid > 0)
                        $row['fee_display'] = '<span class="badge bg-warning text-dark">Partial (' . $paid . '/' . $total . ')</span>';
                    else
                        $row['fee_display'] = '<span class="badge bg-danger">Pending</span>';

                    $row['schedule_dates'] = ($row['start_date']) ? date('d M', strtotime($row['start_date'])) . ' - ' . date('d M', strtotime($row['end_date'])) : '<span class="badge bg-light text-secondary border">No Schedule</span>';
                    $row['hr_display'] = "<b>$compHours</b> / $totalHours";
                    $sCls = match ($row['status']) {
                        'active' => 'bg-primary',
                        'ready' => 'bg-info text-dark',
                        'break' => 'bg-warning text-dark',
                        default => 'bg-secondary'
                    };
                    $row['status_badge'] = '<span class="badge ' . $sCls . '">' . ucfirst($row['status']) . '</span>';

                    // Automated Exam Badge
                    $row['check_exam'] = ($row['exam_count'] > 0) ? '<i class="bi bi-check-circle-fill text-success fs-5" title="Exam Marks Recorded"></i>' : '<i class="bi bi-dash-circle text-muted opacity-50 fs-5"></i>';

                    $row['check_mat'] = $makeIcon($row['checklist_material'], 'checklist_material', $row['student_id']);
                    $row['check_grp'] = $makeIcon($row['checklist_group'], 'checklist_group', $row['student_id']);
                    $row['check_team'] = $makeIcon($row['checklist_teams'], 'checklist_teams', $row['student_id']);
                    $row['actions'] = '<div class="d-flex gap-1 justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary btn-edit" data-id="'.$row['record_id'].'"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger btn-delete" data-id="'.$row['record_id'].'" data-source="student"><i class="bi bi-trash"></i></button>
                </div>';
                }
                $data[] = $row;
            }
            echo json_encode(['data' => $data]);
            exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'finalize_admission':
            $enquiry_id = $_POST['enquiry_id'];

            // 1. Handle Nullable Inputs (Mentor & Dates)
            $mentor_id = !empty($_POST['mentor_id']) ? intval($_POST['mentor_id']) : null;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            // 2. Handle Integer Inputs (Default to 0)
            $total_hours = floatval($_POST['total_hours']);
            $paid_hours = floatval($_POST['paid_hours']);
            $status = !empty($_POST['status']) ? $_POST['status'] : 'ready';

            // 3. Validation
            if ($total_hours > 0 && $paid_hours > $total_hours) {
                echo json_encode(['status' => 'error', 'message' => 'Validation Error: Paid hours cannot be greater than Total hours.']);
                exit;
            }

            // 4. Calculate Fee Status
            if ($total_hours == 0) {
                $fee_status_enum = 'pending';
            } else {
                $fee_status_enum = ($paid_hours >= $total_hours) ? 'paid' : (($paid_hours > 0) ? 'partial' : 'pending');
            }

            $conn->begin_transaction();
            try {
                $e = $conn->query("SELECT * FROM enquiries WHERE id=$enquiry_id")->fetch_assoc();

                // Insert Student (Allowed NULL mentor_id)
                $stmt = $conn->prepare('INSERT INTO students (name, class, syllabus, language, phone, parent_phone, previous_performance, remarks, selected_plan, mentor_id, fee_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssssssiss', $e['student_name'], $e['class'], $e['syllabus'], $e['language'], $e['phone'], $e['parent_phone'], $e['previous_performance'], $e['remarks'], $e['selected_plan'], $mentor_id, $fee_status_enum, $status);
                $stmt->execute();
                $student_id = $conn->insert_id;

                // Insert Schedule (Allowed NULL dates)
                $sch_stmt = $conn->prepare("INSERT INTO schedules (student_id, mentor_id, total_hours, paid_hours, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $sch_stmt->bind_param('iiddss', $student_id, $mentor_id, $total_hours, $paid_hours, $start_date, $end_date);
                $sch_stmt->execute();

                $conn->query("INSERT INTO admissions (enquiry_id, student_id, approved_by, approved_at) VALUES ($enquiry_id, $student_id, {$_SESSION['id']}, NOW())");
                $conn->query("UPDATE enquiries SET status = 'admission_finalized' WHERE id = $enquiry_id");

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Student Admitted Successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            break;

        case 'update_student_schedule':
            $sid = intval($_POST['student_id']);
            $sch_id = intval($_POST['schedule_id']);

            // 1. HANDLE NULLABLE FIELDS
            // If mentor_id is empty, set to NULL, otherwise integer
            $mentor_id = !empty($_POST['mentor_id']) ? intval($_POST['mentor_id']) : null;

            // If dates are empty, set to NULL
            $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

            $status = $_POST['status'];
            $total_hours = floatval($_POST['total_hours']);
            $paid_hours = floatval($_POST['paid_hours']);

            // 2. VALIDATION (Only check if hours are entered)
            if ($total_hours > 0 && $paid_hours > $total_hours) {
                echo json_encode(['status' => 'error', 'message' => 'Paid hours cannot be greater than Total hours.']);
                exit;
            }

            // 3. RECALCULATE FEE STATUS
            // If total hours is 0, status is pending, otherwise calculate based on pay
            if ($total_hours == 0) {
                $fee_status = 'pending';
            } else {
                $fee_status = ($paid_hours >= $total_hours) ? 'paid' : (($paid_hours > 0) ? 'partial' : 'pending');
            }

            $conn->begin_transaction();
            try {
                // UPDATE STUDENT
                // We use 'i' for mentor_id. MySQLi handles NULL for 'i' correctly if the variable is PHP null.
                $stmt = $conn->prepare('UPDATE students SET mentor_id=?, fee_status=?, status=? WHERE id=?');
                $stmt->bind_param('issi', $mentor_id, $fee_status, $status, $sid);
                $stmt->execute();

                // UPDATE OR INSERT SCHEDULE
                if ($sch_id > 0) {
                    // Update existing schedule
                    $stmt2 = $conn->prepare('UPDATE schedules SET mentor_id=?, start_date=?, end_date=?, total_hours=?, paid_hours=? WHERE id=?');
                    $stmt2->bind_param('issddi', $mentor_id, $start, $end, $total_hours, $paid_hours, $sch_id);
                    $stmt2->execute();
                } else {
                    // Create new schedule (Only if at least Total Hours are provided, even if dates are null)
                    // If everything is empty, we might skip creating a schedule, but here we create it so we can track the Fee.
                    $stmt3 = $conn->prepare("INSERT INTO schedules (student_id, mentor_id, start_date, end_date, total_hours, paid_hours, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                    $stmt3->bind_param('iissdd', $sid, $mentor_id, $start, $end, $total_hours, $paid_hours);
                    $stmt3->execute();
                }

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Details updated successfully.']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
            }
            break;

            case 'delete_record':
                $id = intval($_POST['id']);
                $source = $_POST['source']; // 'enquiry' or 'student'
    
                $conn->begin_transaction();
                try {
                    if ($source === 'student') {
                        // 1. Find the linked enquiry ID before deleting the student
                        $get_enq = $conn->prepare("SELECT enquiry_id FROM admissions WHERE student_id = ?");
                        $get_enq->bind_param("i", $id);
                        $get_enq->execute();
                        $enq_res = $get_enq->get_result()->fetch_assoc();
                        $enquiry_id = $enq_res ? $enq_res['enquiry_id'] : null;
    
                        // 2. Delete Student 
                        // (This automatically wipes: admissions, schedules, sessions, attendance, payouts via CASCADE)
                        $del_stu = $conn->prepare("DELETE FROM students WHERE id = ?");
                        $del_stu->bind_param("i", $id);
                        $del_stu->execute();
    
                        // 3. Delete linked Enquiry if it exists
                        // (This wipes: enquiries, enquiry_subjects, demo_assignments via CASCADE)
                        if ($enquiry_id) {
                            $del_enq = $conn->prepare("DELETE FROM enquiries WHERE id = ?");
                            $del_enq->bind_param("i", $enquiry_id);
                            $del_enq->execute();
                        }
                    } else {
                        // Source was just an enquiry (not yet a student)
                        $del_enq_only = $conn->prepare("DELETE FROM enquiries WHERE id = ?");
                        $del_enq_only->bind_param("i", $id);
                        $del_enq_only->execute();
                    }
    
                    $conn->commit();
                    echo json_encode(['status' => 'success', 'message' => 'Student and all associated history wiped from database.']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['status' => 'error', 'message' => 'Wipe failed: ' . $e->getMessage()]);
                }
                exit;
    }
}
?>