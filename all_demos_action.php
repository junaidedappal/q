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
    // Base query with all necessary JOINs
    $base_sql = "SELECT 
                    da.id, 
                    da.scheduled_at, 
                    da.outcome, 
                    da.demo_payment_fee, 
                    da.payment_status,
                    e.student_name,
                    s.name AS subject_name,
                    u.name AS teacher_name
                FROM demo_assignments da
                JOIN enquiries e ON da.enquiry_id = e.id
                JOIN subjects s ON da.subject_id = s.id
                JOIN teachers t ON da.teacher_id = t.id
                JOIN users u ON t.user_id = u.id";

    $where_clauses = [];
    $params = [];
    $types = '';

    // Date filters
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    if ($dateFrom && $dateTo) {
        $where_clauses[] = "da.scheduled_at BETWEEN ? AND ?";
        $params[] = $dateFrom . ' 00:00:00';
        $params[] = $dateTo . ' 23:59:59';
        $types .= 'ss';
    }

    // Status filters
    if (!empty($_GET['outcomeFilter'])) {
        $where_clauses[] = "da.outcome = ?";
        $params[] = $_GET['outcomeFilter'];
        $types .= 's';
    }
    if (!empty($_GET['paymentStatusFilter'])) {
        $where_clauses[] = "da.payment_status = ?";
        $params[] = $_GET['paymentStatusFilter'];
        $types .= 's';
    }

    // Role-based filter
    if ($_SESSION['role'] === 'demoCordinator') {
        $where_clauses[] = "e.assigned_coordinator_id = ?";
        $params[] = $_SESSION['id'];
        $types .= 'i';
    }

    // Combine WHERE clauses
    if (!empty($where_clauses)) {
        $base_sql .= ' WHERE ' . implode(' AND ', $where_clauses);
    }

    $base_sql .= ' ORDER BY da.scheduled_at DESC';

    $stmt = $conn->prepare($base_sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        // Format date
        $row['formatted_date'] = date('d M, Y h:i A', strtotime($row['scheduled_at']));

        // Create badges for statuses
        $outcome_classes = [
            'attended' => 'bg-success', 'redemo' => 'bg-info text-dark',
            'missed' => 'bg-danger', 'pending' => 'bg-warning text-dark'
        ];
        $payment_classes = ['Paid' => 'bg-success', 'Pending' => 'bg-warning text-dark'];

        $row['outcome_badge'] = '<span class="badge ' . ($outcome_classes[$row['outcome']] ?? 'bg-secondary') . '">' . ucfirst($row['outcome']) . '</span>';
        $row['payment_badge'] = '<span class="badge ' . ($payment_classes[$row['payment_status']] ?? 'bg-secondary') . '">' . ucfirst($row['payment_status']) . '</span>';
        
        $data[] = $row;
    }

    echo json_encode(["data" => $data]);
}
?>