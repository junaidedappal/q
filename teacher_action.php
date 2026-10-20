<?php
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Only 'admin' can access
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// --- HANDLE GET REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_teacher_details':
            $user_id = intval($_GET['user_id']);
            $data = [];
            // Get user part
            $stmt = $conn->prepare("SELECT id, name, email, phone, status FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $data['user_details'] = $stmt->get_result()->fetch_assoc();
            
            // Get teacher part
            $teacher_stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
            $teacher_stmt->bind_param("i", $user_id);
            $teacher_stmt->execute();
            $data['teacher_details'] = $teacher_stmt->get_result()->fetch_assoc();

            // Get subjects part
            $subjects_stmt = $conn->prepare("SELECT ts.subject_id FROM teacher_subjects ts JOIN teachers t ON ts.teacher_id = t.id WHERE t.user_id = ?");
            $subjects_stmt->bind_param("i", $user_id);
            $subjects_stmt->execute();
            $result = $subjects_stmt->get_result();
            $subject_ids = [];
            while ($row = $result->fetch_assoc()) { $subject_ids[] = $row['subject_id']; }
            $data['teacher_subjects'] = $subject_ids;
            
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        default: // Get list for DataTable
            $sql = "SELECT u.id, u.name, u.email, t.teacher_reg_code, u.status 
                    FROM users u
                    JOIN teachers t ON u.id = t.user_id
                    WHERE u.role = 'teacher' ORDER BY u.id DESC";
            $result = $conn->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $status_class = ($row['status'] === 'active') ? 'bg-success' : 'bg-dark';
                $row['status_badge'] = '<span class="badge '.$status_class.'">' . ucfirst($row['status']) . '</span>';
                $row['actions'] = '
                    <div class="d-flex gap-1">
                        <a href="view_teacher.php?id='.$row['id'].'" class="btn btn-sm btn-purple" title="View Profile"><i class="bi bi-eye-fill"></i></a>
                        <button class="btn btn-sm btn-info btn-edit" data-id="'.$row['id'].'" title="Edit"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-sm btn-danger btn-delete" data-id="'.$row['id'].'" title="Delete"><i class="bi bi-trash3-fill"></i></button>
                    </div>';
                $data[] = $row;
            }
            echo json_encode(["data" => $data]);
            break;
    }
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    function manageTeacherProfile($conn, $user_id, $post_data) {
        $teacher = $conn->query("SELECT id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
        $teacher_id = null;
        if ($teacher) {
            $teacher_id = $teacher['id'];
            $stmt = $conn->prepare("UPDATE teachers SET teacher_reg_code=?, hourly_rate=?, bio=?, experience_years=?, qualifications=?, address=? WHERE id=?");
            $stmt->bind_param("sdsissi", $post_data['teacher_reg_code'], $post_data['hourly_rate'], $post_data['bio'], $post_data['experience_years'], $post_data['qualifications'], $post_data['address'], $teacher_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO teachers (user_id, teacher_reg_code, hourly_rate, bio, experience_years, qualifications, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsiss", $user_id, $post_data['teacher_reg_code'], $post_data['hourly_rate'], $post_data['bio'], $post_data['experience_years'], $post_data['qualifications'], $post_data['address']);
            $stmt->execute();
            $teacher_id = $conn->insert_id;
        }
        $conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $teacher_id");
        if (!empty($post_data['teacher_subjects'])) {
            $subject_stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
            foreach ($post_data['teacher_subjects'] as $subject_id) {
                $subject_stmt->bind_param("ii", $teacher_id, $subject_id);
                $subject_stmt->execute();
            }
        }
    }

    $conn->begin_transaction();
    try {
        switch ($action) {
            case 'add':
                if (($_POST['role'] ?? '') !== 'teacher') {
                    throw new Exception('This endpoint only manages teacher accounts.');
                }
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $_POST['email']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) throw new Exception('This email address is already in use.');
                
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $_POST['name'], $_POST['email'], $_POST['phone'], $password_hash, $_POST['role'], $_POST['status']);
                $stmt->execute();
                $user_id = $conn->insert_id;

                if ($_POST['role'] === 'teacher') {
                    manageTeacherProfile($conn, $user_id, $_POST);
                }
                break;
            case 'edit':
                $user_id = intval($_POST['user_id'] ?? 0);
                if (($_POST['role'] ?? '') !== 'teacher') {
                    throw new Exception('This endpoint only manages teacher accounts.');
                }
                $teacher_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
                $teacher_check->bind_param("i", $user_id);
                $teacher_check->execute();
                if (!$teacher_check->get_result()->fetch_assoc()) {
                    throw new Exception('Teacher account not found.');
                }
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $_POST['email'], $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) throw new Exception('This email is already used by another account.');
                
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, status=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['role'], $_POST['status'], $password_hash, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, status=? WHERE id=?");
                    $stmt->bind_param("sssssi", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['role'], $_POST['status'], $user_id);
                }
                $stmt->execute();

                if ($_POST['role'] === 'teacher') {
                    manageTeacherProfile($conn, $user_id, $_POST);
                }
                break;
            case 'delete':
                $user_id = intval($_POST['user_id'] ?? 0);
                if ($user_id == $_SESSION['id']) throw new Exception('You cannot delete your own account.');
                $teacher_check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
                $teacher_check->bind_param("i", $user_id);
                $teacher_check->execute();
                if (!$teacher_check->get_result()->fetch_assoc()) {
                    throw new Exception('Teacher account not found.');
                }
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                break;
        }
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Operation successful!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>