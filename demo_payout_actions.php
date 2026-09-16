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

// --- HANDLE POST REQUESTS (CORRECTED LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
        echo json_encode(['status' => 'error', 'message' => 'You are not authorized.']);
        exit;
    }

    switch ($action) {
        case 'mark_as_paid':
        case 'retract_payment':
            // === FIX: VALIDATION MOVED INSIDE THE RELEVANT CASES ===
            $assignment_id = intval($_POST['assignment_id'] ?? 0);
            if ($assignment_id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid assignment ID.']);
                exit;
            }
            
            if ($action === 'mark_as_paid') {
                $stmt = $conn->prepare("UPDATE demo_assignments SET payment_status = 'Paid' WHERE id = ?");
                $stmt->bind_param('i', $assignment_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Payment status updated to Paid.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update payment status.']);
                }
            } else { // retract_payment
                $stmt = $conn->prepare("UPDATE demo_assignments SET payment_status = 'Pending' WHERE id = ?");
                $stmt->bind_param('i', $assignment_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Payment has been retracted.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to retract payment.']);
                }
            }
            break;

        case 'pay_all_teacher':
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $dateFrom = $_POST['dateFrom'] ?? null;
            $dateTo = $_POST['dateTo'] ?? null;

            if ($teacher_id <= 0 || empty($dateFrom) || empty($dateTo)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid data provided for bulk payment.']);
                exit;
            }

            $dateFrom .= ' 00:00:00';
            $dateTo .= ' 23:59:59';
            
            $sql = "UPDATE demo_assignments da
            JOIN enquiries e ON da.enquiry_id = e.id
            SET da.payment_status = 'Paid'
            WHERE da.teacher_id = ?
              AND da.payment_status = 'Pending'
              AND da.scheduled_at BETWEEN ? AND ?
              AND e.status = 'admission_finalized'
              AND da.outcome = 'attended'";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $teacher_id, $dateFrom, $dateTo);

            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                if ($affected_rows > 0) {
                    echo json_encode(['status' => 'success', 'message' => "$affected_rows pending payment(s) have been successfully marked as Paid."]);
                } else {
                    echo json_encode(['status' => 'success', 'message' => 'No pending payments were found for this teacher in the selected period.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update payment statuses.']);
            }
            break;

        default:
             echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
             break;
    }
    exit;
}
// --- HANDLE GET REQUESTS (Fetching Payout Data with NEW, STRICTER LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_summary';

    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    if ($dateFrom) $dateFrom .= ' 00:00:00';
    if ($dateTo) $dateTo .= ' 23:59:59';

    if ($action === 'get_teacher_details') {
        $teacherId = intval($_GET['teacher_id']);
        
        // === QUERY MODIFICATION #1: Stricter Status Check ===
        $sql = "SELECT 
                    da.id, da.scheduled_at, da.demo_payment_fee, da.payment_status, da.outcome, 
                    e.student_name, e.class, 
                    s.name AS subject_name
                FROM demo_assignments da
                JOIN enquiries e ON da.enquiry_id = e.id
                JOIN subjects s ON da.subject_id = s.id
                WHERE da.teacher_id = ? 
                  AND da.outcome = 'attended' 
                  AND e.status = 'admission_finalized'";
        
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

    } else { // get_summary action
        
        $sql = "SELECT 
                    t.id AS teacher_id, 
                    u.name AS teacher_name, 
                    u.phone AS teacher_phone, 
                    t.teacher_reg_code,
                    SUM(da.demo_payment_fee) AS total_payable_fee,
                    SUM(CASE WHEN da.payment_status = 'Paid' THEN da.demo_payment_fee ELSE 0 END) AS total_paid_fee,
                    GROUP_CONCAT(DISTINCT coord.name SEPARATOR ', ') as coordinator_names
                FROM demo_assignments da
                JOIN teachers t ON da.teacher_id = t.id
                JOIN users u ON t.user_id = u.id
                JOIN enquiries e ON da.enquiry_id = e.id
                LEFT JOIN users coord ON e.assigned_coordinator_id = coord.id
                WHERE da.outcome = 'attended' 
                  AND da.demo_payment_fee > 0
                  AND e.status = 'admission_finalized'";

        $params = [];
        $types = '';

        if ($dateFrom && $dateTo) {
            $sql .= " AND da.scheduled_at BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
            $types .= 'ss';
        }

        // === NEW & IMPROVED FILTERING LOGIC ===
        $filter_coordinator_id = $_GET['coordinator_id'] ?? null;

        if ($_SESSION['role'] === 'demoCordinator') {
            // A Coordinator can ONLY see their own data, regardless of the filter. This is a security measure.
            $sql .= ' AND e.assigned_coordinator_id = ?';
            $params[] = $_SESSION['id'];
            $types .= 'i';
        } elseif (in_array($_SESSION['role'], ['admin', 'demoAdmin']) && !empty($filter_coordinator_id)) {
            // An Admin/DemoAdmin CAN filter by a specific coordinator.
            $sql .= ' AND e.assigned_coordinator_id = ?';
            $params[] = $filter_coordinator_id;
            $types .= 'i';
        }
        
        $sql .= ' GROUP BY t.id, u.name,u.phone,  t.teacher_reg_code ORDER BY u.name ASC';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $teacher_summary = $result->fetch_all(MYSQLI_ASSOC);

        $overall_payable = array_sum(array_column($teacher_summary, 'total_payable_fee'));
        $overall_paid = array_sum(array_column($teacher_summary, 'total_paid_fee'));

        echo json_encode([
            'status' => 'success',
            'data' => $teacher_summary,
            'summary' => [
                'total_payable' => number_format($overall_payable, 2),
                'total_paid' => number_format($overall_paid, 2)
            ]
        ]);
    }
}
?>