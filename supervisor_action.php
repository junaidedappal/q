<?php
session_start();
ob_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization: Admin and Supervisor only
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Access Denied']));
}

$action = $_GET['action'] ?? '';

if ($action === 'get_mentor_students') {
    $mentor_id = intval($_GET['mentor_id'] ?? 0);

    if ($_SESSION['role'] === 'supervisor') {
        $access_stmt = $conn->prepare("SELECT id FROM mentors WHERE id = ? AND supervisor_id = ?");
        $access_stmt->bind_param("ii", $mentor_id, $_SESSION['id']);
        $access_stmt->execute();
        if (!$access_stmt->get_result()->fetch_assoc()) {
            ob_clean();
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
            exit;
        }
    }

    // SQL: Fetches student info, latest schedule dates, total hours, and SUM of completed hours
    $sql = "SELECT s.id, s.name, s.class, s.status, s.checklist_material, s.checklist_group, s.checklist_teams,
            sch.start_date, sch.end_date, 
            COALESCE(sch.total_hours, 0) as total_hours, 
            COALESCE(sch.paid_hours, 0) as paid_hours,
            (SELECT COALESCE(SUM(planned_hours), 0) FROM sessions WHERE schedule_id = sch.id AND status = 'done') as completed_hours,
            (SELECT COUNT(*) FROM schedule_exams WHERE schedule_id = sch.id) as exam_count
            FROM students s
            LEFT JOIN schedules sch ON sch.id = (SELECT id FROM schedules WHERE student_id = s.id ORDER BY id DESC LIMIT 1)
            WHERE s.mentor_id = ? 
            ORDER BY s.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        
        // 1. RAW VALUES (Essential for the JS "Progress" filter)
        $comp = floatval($row['completed_hours']);
        $total = floatval($row['total_hours']);
        $row['completed_hours'] = $comp; 
        $row['total_hours'] = $total;

        // 2. VIEW-ONLY ICON GENERATOR
        $viewIcon = function ($val) {
            $color = $val ? 'text-success' : 'text-danger opacity-50';
            $icon = $val ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            return "<i class='bi $icon $color fs-5' data-val='$val'></i>";
        };

        // 3. STATUS BADGE
        $sCls = match($row['status']) { 
            'active' => 'bg-primary', 
            'ready'  => 'bg-info text-dark', 
            'break'  => 'bg-warning text-dark', 
            default  => 'bg-secondary' 
        };
        $row['status_badge'] = "<span class='badge $sCls'>".ucfirst($row['status'])."</span>";

        // 4. FEE DISPLAY
        $paid = floatval($row['paid_hours']);
        if ($total == 0) {
            $row['fee_display'] = '<span class="text-muted small">No Schedule</span>';
        } elseif ($paid >= $total) {
            $row['fee_display'] = '<span class="badge bg-success">Paid</span>';
        } elseif ($paid > 0) {
            $row['fee_display'] = '<span class="badge bg-warning text-dark">Partial (' . $paid . '/' . $total . ')</span>';
        } else {
            $row['fee_display'] = '<span class="badge bg-danger">Pending</span>';
        }

        // 5. SCHEDULE PROGRESS DISPLAY
        $row['schedule_dates'] = $row['start_date'] ? date('d M', strtotime($row['start_date']))." - ".date('d M', strtotime($row['end_date'])) : '<span class="text-muted">--</span>';
        $row['hr_display'] = "<b>$comp</b> / $total <small class='text-muted'>Hrs</small>";
        
        // 6. EXAM STATUS (Needs text-success for the JS filter to detect "Done")
        $row['check_exam'] = ($row['exam_count'] > 0) 
            ? "<i class='bi bi-check-circle-fill text-success fs-5' title='Marks Recorded'></i>" 
            : "<i class='bi bi-dash-circle text-muted opacity-50 fs-5' title='No Marks'></i>";

        // 7. CHECKLIST ICONS
        $row['check_mat'] = $viewIcon($row['checklist_material']);
        $row['check_grp'] = $viewIcon($row['checklist_group']);
        $row['check_team'] = $viewIcon($row['checklist_teams']);

        $data[] = $row;
    }
    
    ob_clean();
    echo json_encode(['data' => $data]);
    exit;
}