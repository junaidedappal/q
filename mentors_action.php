<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_mentor':
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT m.*, u.name FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        echo json_encode(['status' => 'success', 'data' => $res]);
        break;

    case 'get_supervisors':
        $sql = "SELECT id, name FROM users WHERE role = 'supervisor' AND status = 'active'";
        $res = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $res]);
        break;

    case 'update_mentor':
        $id = intval($_POST['id']);
        $rate_s = floatval($_POST['rate_per_session']);
        $rate_e = floatval($_POST['rate_per_exam']);
        $sup_id = !empty($_POST['supervisor_id']) ? intval($_POST['supervisor_id']) : null;

        $stmt = $conn->prepare("UPDATE mentors SET rate_per_session = ?, rate_per_exam = ?, supervisor_id = ? WHERE id = ?");
        $stmt->bind_param("ddii", $rate_s, $rate_e, $sup_id, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Mentor configuration saved!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
        }
        break;
}