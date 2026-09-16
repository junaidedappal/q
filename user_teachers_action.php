<?php
session_start();
ob_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'hrmanager'])) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// --- 1. LIST ALL TEACHERS ---
if ($action === 'list') {
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.status, u.joining_date, t.teacher_reg_code, t.hourly_rate,
            (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') 
             FROM teacher_subjects ts 
             JOIN subjects s ON ts.subject_id = s.id 
             WHERE ts.teacher_id = t.id) as subjects
            FROM users u
            LEFT JOIN teachers t ON u.id = t.user_id
            WHERE u.role = 'teacher' 
            ORDER BY u.id DESC";
            
    $result = $conn->query($sql);
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['status_badge'] = ($row['status'] === 'active') 
            ? '<span class="badge bg-success">Active</span>' 
            : '<span class="badge bg-secondary">Inactive</span>';
            
        $row['actions'] = '<div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm btn-outline-primary btn-edit" data-id="'.$row['id'].'" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="'.$row['id'].'" title="Delete"><i class="bi bi-trash"></i></button>
                           </div>';
        $data[] = $row;
    }
    
    ob_clean();
    echo json_encode(['data' => $data]);
    exit;
}

// --- 2. GET SINGLE TEACHER DETAILS ---
if ($action === 'get') {
    $uid = intval($_GET['user_id']);
    
    $user = $conn->query("SELECT id, name, email, phone, status, joining_date FROM users WHERE id = $uid")->fetch_assoc();
    
    if ($user) {
        $user['teacher_details'] = $conn->query("SELECT * FROM teachers WHERE user_id = $uid")->fetch_assoc();
        
        $subjects_query = "SELECT s.id, s.name 
                           FROM teacher_subjects ts 
                           JOIN subjects s ON ts.subject_id = s.id 
                           JOIN teachers t ON ts.teacher_id = t.id 
                           WHERE t.user_id = $uid";
        $user['subjects_list'] = $conn->query($subjects_query)->fetch_all(MYSQLI_ASSOC);
        
        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Teacher not found']);
    }
    exit;
}

// --- 3. POST ACTIONS (ADD / EDIT / DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $conn->begin_transaction();

    try {
        if ($action === 'delete') {
            $uid = intval($_POST['user_id']);
            if ($uid === $_SESSION['id']) throw new Exception('You cannot delete your own account.');
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
        } 
        else {
            $uid = intval($_POST['user_id'] ?? 0);
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $status = $_POST['status'];
            $joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;

            // --- A. USER TABLE SYNC ---
            if ($action === 'add') {
                if (empty($_POST['password'])) throw new Exception('Password is required for new users.');
                
                // Email Duplicate Check
                $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
                if ($check->num_rows > 0) throw new Exception('Email already exists.');

                $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, status, joining_date) VALUES (?, ?, ?, ?, 'teacher', ?, ?)");
                $stmt->bind_param("ssssss", $name, $email, $phone, $pw, $status, $joining_date);
                $stmt->execute();
                $uid = $conn->insert_id;
            } 
            else { // Edit
                if (!empty($_POST['password'])) {
                    $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, status=?, joining_date=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssssi", $name, $email, $phone, $status, $joining_date, $pw, $uid);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, status=?, joining_date=? WHERE id=?");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $status, $joining_date, $uid);
                }
                $stmt->execute();
            }

            // --- B. TEACHER PROFILE TABLE SYNC ---
            $reg_code = $_POST['teacher_reg_code'] ?? '';
            $hr_rate = floatval($_POST['hourly_rate'] ?? 0);
            $exp = intval($_POST['experience_years'] ?? 0);
            $qual = $_POST['qualifications'] ?? '';
            $addr = $_POST['address'] ?? '';

            $t_check = $conn->query("SELECT id FROM teachers WHERE user_id = $uid")->fetch_assoc();
            if ($t_check) {
                $tid = $t_check['id'];
                $t_stmt = $conn->prepare("UPDATE teachers SET teacher_reg_code=?, hourly_rate=?, experience_years=?, qualifications=?, address=? WHERE id=?");
                $t_stmt->bind_param("sdissi", $reg_code, $hr_rate, $exp, $qual, $addr, $tid);
            } else {
                $t_stmt = $conn->prepare("INSERT INTO teachers (user_id, teacher_reg_code, hourly_rate, experience_years, qualifications, address) VALUES (?, ?, ?, ?, ?, ?)");
                $t_stmt->bind_param("isdiss", $uid, $reg_code, $hr_rate, $exp, $qual, $addr);
            }
            $t_stmt->execute();
            $tid = $t_check ? $tid : $conn->insert_id;

            // --- C. SUBJECTS SYNC ---
            $conn->query("DELETE FROM teacher_subjects WHERE teacher_id = $tid");
            if (!empty($_POST['teacher_subjects'])) {
                $sub_stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
                foreach ($_POST['teacher_subjects'] as $subject_id) {
                    $sub_stmt->bind_param("ii", $tid, $subject_id);
                    $sub_stmt->execute();
                }
            }
        }
        
        $conn->commit();
        ob_clean();
        echo json_encode(['status' => 'success', 'message' => 'Data processed successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}