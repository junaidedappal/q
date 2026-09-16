<?php
// finalized_student_action.php
session_start();
ob_start(); // Prevents accidental whitespace from corrupting JSON output
header('Content-Type: application/json');
require 'includes/db.php';

// 1. Authorization: Modern Role Check
$allowedRoles = ['admin', 'sales'];
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], $allowedRoles)) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access Attempt']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    // --- 2. GET HANDLER: Fetch Student List ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $sql = "SELECT s.*, u.name as mentor_name 
                FROM students s 
                LEFT JOIN mentors m ON s.mentor_id = m.id 
                LEFT JOIN users u ON m.user_id = u.id 
                WHERE s.status != 'enquiry'
                ORDER BY s.id DESC";
        
        $result = $conn->query($sql);
        if (!$result) throw new Exception("Database Query Failed: " . $conn->error);

        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Clean numeric data before sending to JS
            $row['id'] = intval($row['id']);
            $data[] = $row;
        }

        ob_clean();
        echo json_encode(['data' => $data]);
        exit;
    }

    // --- 3. GET HANDLER: Fetch Single Record ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res) throw new Exception("Student record not found.");

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $res]);
        exit;
    }

    // --- 4. POST HANDLER: Data Update ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_student') {
        // Validation: Ensure required fields are not just whitespace
        if (empty(trim($_POST['name']))) throw new Exception("Student Name is mandatory.");

        $stmt = $conn->prepare("UPDATE students SET name=?, class=?, syllabus=?, language=?, phone=?, parent_phone=?, remarks=? WHERE id=?");
        
        // Clean and prepare variables
        $name = trim($_POST['name']);
        $class = trim($_POST['class']);
        $syllabus = trim($_POST['syllabus']);
        $lang = $_POST['language'];
        $phone = trim($_POST['phone']);
        $p_phone = trim($_POST['parent_phone']);
        $remarks = trim($_POST['remarks']);
        $id = intval($_POST['id']);

        $stmt->bind_param("sssssssi", $name, $class, $syllabus, $lang, $phone, $p_phone, $remarks, $id);

        if (!$stmt->execute()) {
            throw new Exception("Execution Failed: " . $stmt->error);
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Student record updated successfully.']);
        exit;
    }

    // If no case matches
    throw new Exception("Invalid Action requested.");

} catch (Exception $e) {
    // 5. Global Error Handler: Return clean JSON error
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}