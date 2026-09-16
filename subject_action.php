<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Only 'admin' can manage subjects.
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE GET REQUESTS (Reading Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_single':
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, name FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        default: // This is the default action for loading the DataTable
            $result = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
            $data = [];
            while ($row = $result->fetch_assoc()) {
                // Add the action buttons for each row
                $row['actions'] = '
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-info btn-edit" data-id="'.$row['id'].'" title="Edit"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-sm btn-danger btn-delete" data-id="'.$row['id'].'" title="Delete"><i class="bi bi-trash3-fill"></i></button>
                    </div>';
                $data[] = $row;
            }
            // DataTables expects the data in a 'data' object
            echo json_encode(["data" => $data]);
            break;
    }
}

// --- HANDLE POST REQUESTS (Creating, Updating, Deleting Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Use a try-catch block for clean error handling
    try {
        switch ($action) {
            case 'add':
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('Subject name cannot be empty.');
                }

                // Check for duplicates before inserting
                $stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('A subject with this name already exists.');
                }

                $stmt = $conn->prepare("INSERT INTO subjects (name) VALUES (?)");
                $stmt->bind_param("s", $name);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to save the subject.');
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Subject added successfully!']);
                break;

            case 'edit':
                $id = intval($_POST['subject_id']);
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('Subject name cannot be empty.');
                }

                // Check if ANOTHER subject (not this one) has the new name
                $stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception('Another subject with this name already exists.');
                }

                $stmt = $conn->prepare("UPDATE subjects SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $name, $id);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to update the subject.');
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Subject updated successfully!']);
                break;

            case 'delete':
                $id = intval($_POST['subject_id']);
                
                $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->bind_param("i", $id);
                if (!$stmt->execute()) {
                    // This can happen if there are foreign key constraints preventing deletion
                    throw new Exception('Failed to delete the subject. It might be in use by teachers or students.');
                }

                echo json_encode(['status' => 'success', 'message' => 'Subject deleted successfully!']);
                break;
        }
    } catch (Exception $e) {
        // Send a 400 Bad Request header for client-side errors (like validation)
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>