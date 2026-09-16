<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE GET REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $data = [];
        $stmt = $conn->prepare('SELECT * FROM enquiries WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_assoc();
        
        $status_map = [
            'new' => ['text' => 'New', 'class' => 'badge-status-new'],
            'admission_confirmed' => ['text' => 'Admission Confirmed', 'class' => 'badge-status-confirmed'],
            'admission_finalized' => ['text' => 'Admission Finalized', 'class' => 'badge-status-finalized']
        ];
        $status_info = $status_map[$details['status']] ?? ['text' => 'Admission Pending', 'class' => 'badge-status-pending'];
        $details['status_badge'] = "<span class='badge {$status_info['class']}'>{$status_info['text']}</span>";
        $data['details'] = $details;

        // OPTIMIZED: Get both subject IDs and names in one go
        $stmt_subjects = $conn->prepare('SELECT s.id, s.name FROM subjects s JOIN enquiry_subjects es ON s.id = es.subject_id WHERE es.enquiry_id = ?');
        $stmt_subjects->bind_param('i', $id);
        $stmt_subjects->execute();
        $subjects_result = $stmt_subjects->get_result();
        $data['subject_ids'] = [];
        $data['subjects_text'] = [];
        while($subject = $subjects_result->fetch_assoc()){
            $data['subject_ids'][] = $subject['id'];
            $data['subjects_text'][] = $subject['name'];
        }
        echo json_encode($data);

    } else {
        // Main list for DataTables
        $filterYear = isset($_GET['filterYear']) && $_GET['filterYear'] !== '' ? intval($_GET['filterYear']) : null;
        $filterMonth = isset($_GET['filterMonth']) && $_GET['filterMonth'] !== '' ? intval($_GET['filterMonth']) : null;
        $sql = 'SELECT e.*, u.name AS creator_name 
                FROM enquiries e 
                LEFT JOIN users u ON e.created_by = u.id';
        $where_clauses = []; $params = []; $types = '';
        if ($filterYear) { $where_clauses[] = 'YEAR(e.created_at) = ?'; $params[] = $filterYear; $types .= 'i'; }
        if ($filterMonth) { $where_clauses[] = 'MONTH(e.created_at) = ?'; $params[] = $filterMonth; $types .= 'i'; }
        if (!empty($where_clauses)) { $sql .= ' WHERE ' . implode(' AND ', $where_clauses); }
        $sql .= ' ORDER BY e.id DESC';
        if (!empty($params)) { $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); } 
        else { $result = $conn->query($sql); }
        $userRole = $_SESSION['role'];
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $status_map = [
                'new' => ['text' => 'New', 'class' => 'badge-status-new'],
                'admission_confirmed' => ['text' => 'Admission Confirmed', 'class' => 'badge-status-confirmed'],
                'admission_finalized' => ['text' => 'Admission Finalized', 'class' => 'badge-status-finalized']
            ];
            $status_info = $status_map[$row['status']] ?? ['text' => 'Admission Pending', 'class' => 'badge-status-pending'];
            $row['status_badge'] = "<span class='badge {$status_info['class']}'>{$status_info['text']}</span>";

            $row['customer_type_badge'] = $row['is_previous_customer'] ? '<span class="badge bg-success text-white">OLD</span>' : '';
            
            $row['created_at'] = date('d M, Y', strtotime($row['created_at']));
            $actions = '<div class="action-buttons">
                <button class="btn-action btn-purple btn-view" data-id="' . $row['id'] . '" title="View Full Details"><i class="bi bi-eye-fill"></i></button>
                <button class="btn-action btn-info btn-edit" data-id="' . $row['id'] . '" title="Edit Enquiry"><i class="bi bi-pencil-square"></i></button>';
            if ($userRole == 'super' || $userRole == 'admin') {
                $actions .= '<button class="btn-action btn-danger btn-delete" data-id="' . $row['id'] . '" title="Delete Enquiry"><i class="bi bi-trash3-fill"></i></button>';
            }
            $actions .= '</div>';
            $row['actions'] = $actions;
            $data[] = $row;
        }
        echo json_encode(['data' => $data]);
    }
}

// --- HANDLE POST REQUESTS (Create, Update, Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        switch ($action) {
            case 'check_phone':
                $phone = trim($_POST['phone'] ?? '');
                $parent_phone = trim($_POST['parent_phone'] ?? '');
                $enquiry_id = intval($_POST['enquiry_id'] ?? 0);

                if (empty($phone) && empty($parent_phone)) {
                    echo json_encode(['exists' => false]);
                    exit;
                }

                $query_parts = [];
                $params = [];
                $types = '';

                if (!empty($phone)) {
                    $query_parts[] = "(phone = ? OR parent_phone = ?)";
                    $params[] = $phone; $params[] = $phone; $types .= 'ss';
                }
                if (!empty($parent_phone)) {
                    $query_parts[] = "(phone = ? OR parent_phone = ?)";
                    $params[] = $parent_phone; $params[] = $parent_phone; $types .= 'ss';
                }

                $sql = "SELECT id FROM enquiries WHERE (" . implode(' OR ', $query_parts) . ")";

                if ($enquiry_id > 0) {
                    $sql .= " AND id != ?";
                    $params[] = $enquiry_id;
                    $types .= 'i';
                }
                $sql .= " LIMIT 1";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode(['exists' => $result->num_rows > 0]);
                break;

            case 'add':
                $phone = trim($_POST['phone'] ?? '');
                $parent_phone = trim($_POST['parent_phone'] ?? '');
                $is_previous = 0;

                $query_parts = []; $params = []; $types = '';
                if (!empty($phone)) {
                    $query_parts[] = "(phone = ? OR parent_phone = ?)";
                    $params[] = $phone; $params[] = $phone; $types .= 'ss';
                }
                if (!empty($parent_phone)) {
                    $query_parts[] = "(phone = ? OR parent_phone = ?)";
                    $params[] = $parent_phone; $params[] = $parent_phone; $types .= 'ss';
                }

                if (!empty($query_parts)) {
                    $sql_check = "SELECT id FROM enquiries WHERE " . implode(' OR ', $query_parts) . " LIMIT 1";
                    $stmt_check = $conn->prepare($sql_check);
                    $stmt_check->bind_param($types, ...$params);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        $is_previous = 1;
                    }
                }

                $conn->begin_transaction();
                try {
                    // $stmt1 = $conn->prepare("INSERT INTO enquiries (student_name, class, syllabus, language, phone, parent_phone, previous_performance, remarks, status, is_previous_customer, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?)");
                    // $stmt1->bind_param("ssssssssii", $_POST['student_name'], $_POST['class'], $_POST['syllabus'], $_POST['language'], $_POST['phone'], $_POST['parent_phone'], $_POST['previous_performance'], $_POST['remarks'], $is_previous, $_SESSION['id']);
                    
                    $stmt1 = $conn->prepare("INSERT INTO enquiries (student_name, class, syllabus, language, country, phone, parent_phone, previous_performance, remarks, status, is_previous_customer, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?)");
                    $stmt1->bind_param("sssssssssii", $_POST['student_name'], $_POST['class'], $_POST['syllabus'], $_POST['language'], $_POST['country'], $_POST['phone'], $_POST['parent_phone'], $_POST['previous_performance'], $_POST['remarks'], $is_previous, $_SESSION['id']);
                    $stmt1->execute();
                    $enquiry_id = $conn->insert_id;

                    if (!empty($_POST['subjects'])) {
                        $stmt2 = $conn->prepare("INSERT INTO enquiry_subjects (enquiry_id, subject_id) VALUES (?, ?)");
                        foreach ($_POST['subjects'] as $subject_id) {
                            $stmt2->bind_param("ii", $enquiry_id, $subject_id);
                            $stmt2->execute();
                        }
                    }
                    $conn->commit();
                    echo json_encode(['status' => 'success', 'message' => 'Enquiry added successfully!']);
                } catch (mysqli_sql_exception $exception) {
                    $conn->rollback();
                    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $exception->getMessage()]);
                }
                break;

            case 'edit':
                $enquiry_id = intval($_POST['enquiry_id']);
                // $stmt = $conn->prepare('UPDATE enquiries SET student_name = ?, class = ?, syllabus = ?, language = ?, phone = ?, parent_phone = ?, previous_performance = ?, remarks = ? WHERE id = ?');
                // $stmt->bind_param('ssssssssi', $_POST['student_name'], $_POST['class'], $_POST['syllabus'], $_POST['language'], $_POST['phone'], $_POST['parent_phone'], $_POST['previous_performance'], $_POST['remarks'], $enquiry_id);
                $stmt = $conn->prepare('UPDATE enquiries SET student_name = ?, class = ?, syllabus = ?, language = ?, country = ?, phone = ?, parent_phone = ?, previous_performance = ?, remarks = ? WHERE id = ?');
                $stmt->bind_param('sssssssssi', $_POST['student_name'], $_POST['class'], $_POST['syllabus'], $_POST['language'], $_POST['country'], $_POST['phone'], $_POST['parent_phone'], $_POST['previous_performance'], $_POST['remarks'], $enquiry_id);
                if ($stmt->execute()) {
                    $conn->query("DELETE FROM enquiry_subjects WHERE enquiry_id = $enquiry_id");
                    if (!empty($_POST['subjects'])) {
                        foreach ($_POST['subjects'] as $subject_id) {
                            $sub_stmt = $conn->prepare('INSERT INTO enquiry_subjects (enquiry_id, subject_id) VALUES (?, ?)');
                            $sub_stmt->bind_param('ii', $enquiry_id, $subject_id);
                            $sub_stmt->execute();
                        }
                    }
                    echo json_encode(['status' => 'success', 'message' => 'Enquiry updated successfully.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update enquiry.']);
                }
                break;

            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $conn->prepare('DELETE FROM enquiries WHERE id = ?');
                $stmt->bind_param('i', $id);

                if ($stmt->execute()) {
                    $conn->query("DELETE FROM enquiry_subjects WHERE enquiry_id = $id");
                    echo json_encode(['status' => 'success', 'message' => 'Enquiry deleted successfully.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to delete enquiry.']);
                }
                break;
        }
    }
}
?>