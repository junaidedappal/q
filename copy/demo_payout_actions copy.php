<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE POST REQUESTS (This part is already correct) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Security check: Only Admins can perform payment actions ---
    if (!in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
        echo json_encode(['status' => 'error', 'message' => 'You are not authorized to perform this action.']);
        exit;
    }

    $assignment_id = intval($_POST['assignment_id'] ?? 0);
    if ($assignment_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid assignment ID.']);
        exit;
    }

    switch ($action) {
        case 'mark_as_paid':
            $stmt = $conn->prepare("UPDATE demo_assignments SET payment_status = 'Paid' WHERE id = ?");
            $stmt->bind_param('i', $assignment_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Payment status updated to Paid.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update payment status.']);
            }
            break;

        // ===================================================================
        // == NEW: ACTION TO RETRACT A PAYMENT ==
        // ===================================================================
        case 'retract_payment':
            $stmt = $conn->prepare("UPDATE demo_assignments SET payment_status = 'Pending' WHERE id = ?");
            $stmt->bind_param('i', $assignment_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Payment has been retracted and set to Pending.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to retract payment.']);
            }
            break;
            
        default:
             echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
             break;
    }
    exit;
}


// --- HANDLE GET REQUESTS (Fetching Payout Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_summary';

    // ===================================================================
    // == THE CORRECT AND FINAL FIX IS HERE ==
    // ===================================================================
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    if ($dateFrom) {
        $dateFrom .= ' 00:00:00'; // Set start time to the beginning of the day
    }
    if ($dateTo) {
        $dateTo .= ' 23:59:59'; // Set end time to the very end of the day
    }
    // ===================================================================

    if ($action === 'get_teacher_details') {
        $teacherId = intval($_GET['teacher_id']);
        
        $sql = "SELECT da.id, da.scheduled_at, da.demo_payment_fee, da.payment_status, da.outcome, e.student_name, s.name AS subject_name
                FROM demo_assignments da
                JOIN enquiries e ON da.enquiry_id = e.id
                JOIN subjects s ON da.subject_id = s.id
                WHERE da.teacher_id = ? AND da.outcome IN ('attended', 'redemo')";
        
        $params = ['i', $teacherId];
        
        if ($dateFrom && $dateTo) {
            $sql .= " AND da.scheduled_at BETWEEN ? AND ?";
            $params[0] .= 'ss';
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        if ($_SESSION['role'] === 'demoCordinator') {
            $sql .= ' AND e.assigned_coordinator_id = ?';
            $params[0] .= 'i';
            $params[] = $_SESSION['id'];
        }

        $sql .= ' ORDER BY da.scheduled_at DESC';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $demos = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $demos]);

    } else {
        // Main summary action
        $sql = "SELECT 
                    t.id AS teacher_id, u.name AS teacher_name, t.teacher_reg_code,
                    SUM(CASE WHEN da.outcome = 'attended' THEN da.demo_payment_fee ELSE 0 END) AS total_attended_fee,
                    SUM(CASE WHEN da.outcome = 'redemo' THEN da.demo_payment_fee ELSE 0 END) AS total_redemo_fee,
                    SUM(CASE WHEN da.payment_status = 'Paid' THEN da.demo_payment_fee ELSE 0 END) AS total_paid_fee
                FROM demo_assignments da
                JOIN teachers t ON da.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                JOIN enquiries e ON da.enquiry_id = e.id
                WHERE da.outcome IN ('attended', 'redemo')";

        $params = [];
        $types = '';

        if ($dateFrom && $dateTo) {
            $sql .= " AND da.scheduled_at BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
            $types .= 'ss';
        }

        if ($_SESSION['role'] === 'demoCordinator') {
            $sql .= ' AND e.assigned_coordinator_id = ?';
            $params[] = $_SESSION['id'];
            $types .= 'i';
        }
        
        $sql .= ' GROUP BY t.id, u.name, t.teacher_reg_code ORDER BY u.name ASC';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher_summary = $result->fetch_all(MYSQLI_ASSOC);

        $overall_attended = array_sum(array_column($teacher_summary, 'total_attended_fee'));
        $overall_redemo = array_sum(array_column($teacher_summary, 'total_redemo_fee'));
        $overall_paid = array_sum(array_column($teacher_summary, 'total_paid_fee'));

        echo json_encode([
            'status' => 'success',
            'data' => $teacher_summary,
            'summary' => [
                'total_attended' => number_format($overall_attended, 2),
                'total_redemo' => number_format($overall_redemo, 2),
                'total_paid' => number_format($overall_paid, 2)
            ]
        ]);
    }
}
?>