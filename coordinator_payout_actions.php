<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin'])) {
    echo json_encode(['status'=>'error','message'=>'Access denied']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$dateFrom = $_GET['dateFrom'] ?? null;
$dateTo = $_GET['dateTo'] ?? null;

if($dateFrom) $dateFrom .= " 00:00:00";
if($dateTo) $dateTo .= " 23:59:59";


// ---------------------------------------------------------
// 1️⃣ COORDINATOR SUMMARY TABLE
// ---------------------------------------------------------
if($action === "get_summary"){

    $sql = "
        SELECT 
            coord.id AS coordinator_id,
            coord.name AS coordinator_name,
            SUM(da.demo_payment_fee) AS total_payable_fee,
            SUM(CASE WHEN da.payment_status = 'Paid'
                     THEN da.demo_payment_fee ELSE 0 END) AS total_paid_fee
        FROM demo_assignments da
        JOIN enquiries e ON da.enquiry_id = e.id
        LEFT JOIN users coord ON e.assigned_coordinator_id = coord.id
        WHERE da.outcome='attended'
          AND da.demo_payment_fee > 0
          AND e.status='admission_finalized'
    ";

    $params = [];
    $types = "";

    if($dateFrom && $dateTo){
        $sql .= " AND da.scheduled_at BETWEEN ? AND ?";
        $params[] = $dateFrom;
        $params[] = $dateTo;
        $types .= "ss";
    }

    $sql .= " GROUP BY coord.id ORDER BY coord.name ASC";

    $stmt = $conn->prepare($sql);
    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status'=>'success',
        'data'=>$data
    ]);
    exit;
}


// ---------------------------------------------------------
// 2️⃣ DETAILS FOR ONE COORDINATOR
// ---------------------------------------------------------
if($action === "get_details"){

    $coordId = intval($_GET['coordinator_id']);

    $sql = "
        SELECT 
            e.student_name,
            e.class,
            u.name AS teacher_name,
            s.name AS subject_name,
            DATE(da.scheduled_at) AS date,
            da.outcome,
            da.demo_payment_fee AS fee,
            da.payment_status
        FROM demo_assignments da
        JOIN enquiries e ON da.enquiry_id = e.id
        JOIN teachers t ON da.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN subjects s ON da.subject_id = s.id
        WHERE da.outcome='attended'
          AND da.demo_payment_fee > 0
          AND e.status='admission_finalized'
          AND e.assigned_coordinator_id = ?
    ";

    $params = ['i', $coordId];

    if($dateFrom && $dateTo){
        $sql .= " AND da.scheduled_at BETWEEN ? AND ?";
        $params[0] .= "ss";
        $params[] = $dateFrom;
        $params[] = $dateTo;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status'=>'success',
        'data'=>$data
    ]);
    exit;
}

?>