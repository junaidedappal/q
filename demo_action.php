<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE GET REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_enquiry_details':
            $enquiry_id = intval($_GET['enquiry_id']);
            $subjects_sql = 'SELECT s.id, s.name FROM subjects s JOIN enquiry_subjects es ON s.id = es.subject_id WHERE es.enquiry_id = ?';
            $stmt = $conn->prepare($subjects_sql);
            $stmt->bind_param('i', $enquiry_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();

            $subjects_data = [];
            while ($subject = $subjects_result->fetch_assoc()) {
                $history_sql = 'SELECT da.id, da.outcome, da.feedback, u.name as teacher_name, da.scheduled_at 
                                FROM demo_assignments da
                                JOIN teachers t ON da.teacher_id = t.id
                                JOIN users u ON t.user_id = u.id
                                WHERE da.enquiry_id = ? AND da.subject_id = ? 
                                ORDER BY da.id ASC';
                $history_stmt = $conn->prepare($history_sql);
                $history_stmt->bind_param('ii', $enquiry_id, $subject['id']);
                $history_stmt->execute();
                $history_result = $history_stmt->get_result();
                $history = [];
                while ($demo = $history_result->fetch_assoc()) {
                    $history[] = $demo;
                }
                $subject['history'] = $history;
                $subjects_data[] = $subject;
            }
            echo json_encode(['status' => 'success', 'subjects' => $subjects_data]);
            break;

        case 'get_teachers':
            $subject_id = $_GET['subject_id'] ?? null;

            $sql = 'SELECT t.id, u.name, t.teacher_reg_code
                    FROM teachers t 
                    JOIN users u ON t.user_id = u.id ';

            $params = [];
            $types = '';

            if ($subject_id && is_numeric($subject_id)) {
                $sql .= " JOIN teacher_subjects ts ON t.id = ts.teacher_id WHERE ts.subject_id = ? AND u.status = 'active'";
                $params[] = $subject_id;
                $types .= 'i';
            } else {
                $sql .= " WHERE u.status = 'active'";
            }

            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $teachers = [];
            while ($row = $result->fetch_assoc()) {
                $row['display_name'] = $row['name'] . ($row['teacher_reg_code'] ? ' (' . $row['teacher_reg_code'] . ')' : '');
                $teachers[] = $row;
            }
            echo json_encode(['status' => 'success', 'teachers' => $teachers]);
            break;

        case 'get_single_demo':
            $demo_id = intval($_GET['demo_id']);
            // == ADD demo_payment_fee TO THE SELECT LIST ==
            $stmt = $conn->prepare('SELECT id, teacher_id, zoom_link, scheduled_at, duration_minutes, outcome, feedback, demo_payment_fee FROM demo_assignments WHERE id = ?');
            $stmt->bind_param('i', $demo_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

            default: // Main list for the DataTable
            $filterYear = !empty($_GET['filterYear']) ? intval($_GET['filterYear']) : null;
            $filterMonth = !empty($_GET['filterMonth']) ? intval($_GET['filterMonth']) : null;
            // === NEW: GET THE COORDINATOR FILTER FROM THE REQUEST ===
            $filterCoordinator = !empty($_GET['filterCoordinator']) ? intval($_GET['filterCoordinator']) : null;
            $loadAll = isset($_GET['loadAll']) && $_GET['loadAll'] === 'true';

            $base_sql = "SELECT e.id, e.student_name, e.class, e.status, e.created_at, u.name AS coordinator_name, e.assigned_coordinator_id
                         FROM enquiries e
                         LEFT JOIN users u ON e.assigned_coordinator_id = u.id";
            
            $where_clauses = ["e.status != 'rejected'"];
            $params = [];
            $types = '';

            // === UPDATED ROLE-BASED FILTERING LOGIC ===
            if ($_SESSION['role'] === 'demoCordinator') {
                // If the user is a coordinator, force the filter to their ID, ignoring any dropdown selection.
                $where_clauses[] = 'e.assigned_coordinator_id = ?';
                $params[] = $_SESSION['id'];
                $types .= 'i';
            } elseif (in_array($_SESSION['role'], ['admin', 'demoAdmin']) && $filterCoordinator) {
                // If the user is an admin AND they selected a coordinator from the dropdown, apply that filter.
                $where_clauses[] = 'e.assigned_coordinator_id = ?';
                $params[] = $filterCoordinator;
                $types .= 'i';
            }

            if ($filterYear) {
                $where_clauses[] = 'YEAR(e.created_at) = ?';
                $params[] = $filterYear;
                $types .= 'i';
            }
            if ($filterMonth) {
                $where_clauses[] = 'MONTH(e.created_at) = ?';
                $params[] = $filterMonth;
                $types .= 'i';
            }
            
            $where_string = ' WHERE ' . implode(' AND ', $where_clauses);
            $count_sql = "SELECT COUNT(*) as total FROM enquiries e " . $where_string;
            $base_sql .= $where_string;

            // Get total count
            $count_stmt = $conn->prepare($count_sql);
            if (!empty($params)) {
                $count_stmt->bind_param($types, ...$params);
            }
            $count_stmt->execute();
            $total_records = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
            
            $base_sql .= ' ORDER BY e.id DESC';
            if (!$loadAll) {
                $base_sql .= ' LIMIT 200';
            }

            // Fetch data
            $stmt = $conn->prepare($base_sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $row['enquiry_date'] = date('d M, Y', strtotime($row['created_at']));

                // Status badge logic
                $status_text = 'In Progress';
                $status_class = 'badge-status-pending';
                if ($row['status'] === 'new') {
                    $status_text = 'New'; $status_class = 'badge-status-new';
                } else if ($row['status'] === 'admission_confirmed') {
                    $status_text = 'Confirmed'; $status_class = 'badge-status-confirmed';
                } else if ($row['status'] === 'admission_finalized') {
                    $status_text = 'Finalized'; $status_class = 'badge-status-finalized';
                }
                $row['status_badge'] = '<span class="badge-modern ' . $status_class . '">' . $status_text . '</span>';
                
                // Coordinator display
                if (!empty($row['coordinator_name'])) {
                    $row['coordinator_display'] = htmlspecialchars($row['coordinator_name']);
                } else {
                    $row['coordinator_display'] = '<span class="badge bg-warning text-dark">Not Assigned</span>';
                }
                
                // Actions
                $actions = '<div class="d-flex gap-1">
                                <a href="view_demo_details.php?id=' . $row['id'] . '" class="btn btn-sm btn-gradient-info" title="View Full Details">
                                    <i class="bi bi-eye-fill"></i> View
                                </a>';
                if (in_array($_SESSION['role'], ['admin', 'demoAdmin']) && $row['status'] !== 'admission_finalized') {
                    $actions .= '<button class="btn btn-sm btn-gradient-success btn-assign-coordinator" data-id="' . $row['id'] . '" data-coordinator-id="' . $row['assigned_coordinator_id'] . '" title="Assign Coordinator">
                                    <i class="bi bi-person-check-fill"></i> Assign
                                 </button>';
                }
                $actions .= '</div>';
                $row['actions'] = $actions;
                
                $data[] = $row;
            }
            echo json_encode([
                'data' => $data,
                'total' => $total_records
            ]);
            break;
    }
}


// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'assign_coordinator':
            if (!in_array($_SESSION['role'], ['admin', 'demoAdmin'])) {
                echo json_encode(['status' => 'error', 'message' => 'You are not authorized to assign coordinators.']);
                exit;
            }

            $enquiry_id = intval($_POST['enquiry_id']);
            $coordinator_id = intval($_POST['coordinator_id']);

            $stmt = $conn->prepare('UPDATE enquiries SET assigned_coordinator_id = ? WHERE id = ?');
            $stmt->bind_param('ii', $coordinator_id, $enquiry_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Coordinator assigned successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to assign coordinator.']);
            }
            break;

        case 'schedule_demo':
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("INSERT INTO demo_assignments (enquiry_id, subject_id, teacher_id, zoom_link, scheduled_at, duration_minutes, outcome, demo_payment_fee) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt1->bind_param('iiissid', $_POST['enquiry_id'], $_POST['subject_id'], $_POST['teacher_id'], $_POST['zoom_link'], $_POST['datetime'], $_POST['duration'], $_POST['demo_payment_fee']);
                $stmt1->execute();
                $stmt2 = $conn->prepare("UPDATE enquiries SET status = 'demo_in_progress' WHERE id = ?");
                $stmt2->bind_param('i', $_POST['enquiry_id']);
                $stmt2->execute();

                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Demo scheduled successfully!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            }
            break;

            case 'edit_demo':
                $stmt = $conn->prepare("UPDATE demo_assignments SET teacher_id = ?, scheduled_at = ?, duration_minutes = ?, zoom_link = ?, demo_payment_fee = ? WHERE id = ?");
                $datetime = date('Y-m-d H:i:s', strtotime($_POST['datetime']));

                $stmt->bind_param(
                    "isisdi", 
                    $_POST['teacher_id'],       // i - integer
                    $datetime,                 // s - string
                    $_POST['duration'],         // i - integer
                    $_POST['zoom_link'],        // s - string
                    $_POST['demo_payment_fee'], // d - double
                    $_POST['demo_assignment_id']// i - integer
                );          
                
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Demo schedule updated successfully!']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update schedule.']);
                }
                break;

        case 'delete_demo':
            $stmt = $conn->prepare('DELETE FROM demo_assignments WHERE id = ?');
            $stmt->bind_param('i', $_POST['demo_assignment_id']);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Demo assignment has been deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete demo.']);
            }
            break;

        case 'update_outcome':
            $stmt = $conn->prepare('UPDATE demo_assignments SET outcome = ?, feedback = ? WHERE id = ?');
            $stmt->bind_param('ssi', $_POST['outcome'], $_POST['feedback'], $_POST['demo_assignment_id']);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Demo outcome updated!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update outcome.']);
            }
            break;

            case 'approve_for_admission':
                $selected_plan = $_POST['selected_plan'] ?? '';
                
                // Validate that a plan was actually sent
                if(empty($selected_plan)){
                     echo json_encode(['status' => 'error', 'message' => 'Please enter the selected plan (e.g., 3 Sessions/Week).']);
                     exit;
                }
    
                // Update status AND the selected_plan column
                $stmt = $conn->prepare("UPDATE enquiries SET status = 'admission_confirmed', selected_plan = ? WHERE id = ?");
                $stmt->bind_param('si', $selected_plan, $_POST['enquiry_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Student approved and plan saved successfully!']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to approve.']);
                }
                break;

        // case 'approve_for_admission':
        //     $stmt = $conn->prepare("UPDATE enquiries SET status = 'admission_confirmed' WHERE id = ?");
        //     $stmt->bind_param('i', $_POST['enquiry_id']);
        //     if ($stmt->execute()) {
        //         echo json_encode(['status' => 'success', 'message' => 'Student approved for admission!']);
        //     } else {
        //         echo json_encode(['status' => 'error', 'message' => 'Failed to approve.']);
        //     }
        //     break;
    }
}

$conn->close();
?>