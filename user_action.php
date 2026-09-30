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
        case 'get_user_details':
            $user_id = intval($_GET['user_id']);
            $stmt = $conn->prepare("SELECT id, name, email, phone, role, status, can_edit_teacher_payout FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if ($data) {
                // Add styled badges for the View modal
                $role_class = 'bg-secondary';
                if ($data['role'] === 'admin') $role_class = 'bg-danger';
                if ($data['role'] === 'sales') $role_class = 'bg-warning text-dark';
                if ($data['role'] === 'demoAdmin') $role_class = 'bg-info text-dark';
                if ($data['role'] === 'demoCordinator') $role_class = 'bg-info';
                if ($data['role'] === 'mentor') $role_class = 'bg-primary';
                if ($data['role'] === 'teacher') $role_class = 'bg-success';
                if ($data['role'] === 'accounts') $role_class = 'bg-dark';
                $data['role_badge'] = '<span class="badge ' . $role_class . '">' . ucfirst($data['role']) . '</span>';
                $status_class = ($data['status'] === 'active') ? 'bg-success' : 'bg-secondary';
                $data['status_badge'] = '<span class="badge ' . $status_class . '">' . ucfirst($data['status']) . '</span>';

                // If the user is a teacher, get their extra details
                if ($data['role'] === 'teacher') {
                    $teacher_stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
                    $teacher_stmt->bind_param("i", $user_id);
                    $teacher_stmt->execute();
                    $data['teacher_details'] = $teacher_stmt->get_result()->fetch_assoc();

                    // --- MODIFICATION START: Fetch subject ID and Name ---
                    $subjects_stmt = $conn->prepare("
                        SELECT ts.subject_id, s.name 
                        FROM teacher_subjects ts 
                        JOIN teachers t ON ts.teacher_id = t.id 
                        JOIN subjects s ON ts.subject_id = s.id
                        WHERE t.user_id = ?
                    ");
                    $subjects_stmt->bind_param("i", $user_id);
                    $subjects_stmt->execute();
                    $result = $subjects_stmt->get_result();
                    $subjects = [];
                    while ($row = $result->fetch_assoc()) {
                        $subjects[] = $row; // Will be an array of objects like [{subject_id: 1, name: 'Math'}, ...]
                    }
                    $data['teacher_subjects_details'] = $subjects;
                    // --- MODIFICATION END ---

                    // Keep this for the edit form which just needs IDs
                    $subjects_stmt = $conn->prepare("SELECT ts.subject_id FROM teacher_subjects ts JOIN teachers t ON ts.teacher_id = t.id WHERE t.user_id = ?");
                    $subjects_stmt->bind_param("i", $user_id);
                    $subjects_stmt->execute();
                    $result = $subjects_stmt->get_result();
                    $subject_ids = [];
                    while ($row = $result->fetch_assoc()) { $subject_ids[] = $row['subject_id']; }
                    $data['teacher_subjects'] = $subject_ids;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        default: // Get list for DataTable
            $sql = "SELECT id, name, email, role, status, can_edit_teacher_payout FROM users ORDER BY id DESC";
            $result = $conn->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $role_class = 'bg-secondary';
                if ($row['role'] === 'admin') $role_class = 'bg-danger';
                if ($row['role'] === 'sales') $role_class = 'bg-warning text-dark';
                if ($row['role'] === 'demoAdmin') $role_class = 'bg-info text-dark';
                if ($row['role'] === 'demoCordinator') $role_class = 'bg-info';
                if ($row['role'] === 'mentor') $role_class = 'bg-primary';
                if ($row['role'] === 'teacher') $role_class = 'bg-success';
                if ($row['role'] === 'accounts') $role_class = 'bg-dark';
                $row['role_badge'] = '<span class="badge ' . $role_class . '">' . htmlspecialchars(ucfirst($row['role'])) . '</span>';
                
                $status_class = ($row['status'] === 'active') ? 'bg-success' : 'bg-secondary';
                $row['status_badge'] = '<span class="badge ' . $status_class . '">' . htmlspecialchars(ucfirst($row['status'])) . '</span>';
                
                $payoutToggle = '<div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input payout-permission-toggle" type="checkbox" data-id="' . $row['id'] . '"' . ($row['can_edit_teacher_payout'] ? ' checked' : '') . ' aria-label="Allow teacher payout editing">
                                </div>';

                $row['payout_permission'] = $payoutToggle;
                $row['actions'] = '<div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-sm btn-purple btn-view" data-id="'.$row['id'].'" title="View"><i class="bi bi-eye-fill"></i></button>
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
    // ... (Your entire POST logic remains unchanged as it is already correct) ...
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_teacher_payout_permission') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $allowed = !empty($_POST['can_edit_teacher_payout']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET can_edit_teacher_payout = ? WHERE id = ?");
        $stmt->bind_param("ii", $allowed, $user_id);

        echo json_encode($stmt->execute()
            ? ['status' => 'success', 'message' => 'Teacher payout permission updated.']
            : ['status' => 'error', 'message' => 'Could not update teacher payout permission.']);
        exit;
    }

    // --- Helper Functions (These are correct) ---
    function manageTeacherProfile($conn, $user_id, $post_data) {
        $teacher = $conn->query("SELECT id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
        $teacher_id = null;
        if ($teacher) {
            $teacher_id = $teacher['id'];
            $stmt = $conn->prepare("UPDATE teachers SET teacher_reg_code=?, hourly_rate=?, bio=?, experience_years=?, qualifications=?, address=? WHERE id=?");
            $stmt->bind_param("sdsssi", $post_data['teacher_reg_code'], $post_data['hourly_rate'], $post_data['bio'], $post_data['experience_years'], $post_data['qualifications'], $post_data['address'], $teacher_id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO teachers (user_id, teacher_reg_code, hourly_rate, bio, experience_years, qualifications, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdssis", $user_id, $post_data['teacher_reg_code'], $post_data['hourly_rate'], $post_data['bio'], $post_data['experience_years'], $post_data['qualifications'], $post_data['address']);
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
    function manageMentorProfile($conn, $user_id) {
        $mentor = $conn->query("SELECT id FROM mentors WHERE user_id = $user_id")->fetch_assoc();
        if (!$mentor) {
            $stmt = $conn->prepare("INSERT INTO mentors (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    }
     // --- Main Transaction Logic ---
     $conn->begin_transaction();
     try {
         switch ($action) {
             case 'add':
                 if (empty($_POST['password'])) throw new Exception('Password is required for new users.');
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
                 if ($_POST['role'] === 'mentor') {
                     manageMentorProfile($conn, $user_id);
                 }
                 break; 
 
             case 'edit':
                 $user_id = $_POST['user_id'];
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
                 if ($_POST['role'] === 'mentor') {
                     manageMentorProfile($conn, $user_id);
                 }
                 break;
 
             case 'delete':
                 $user_id = $_POST['user_id'];
                 if ($user_id == $_SESSION['id']) throw new Exception('You cannot delete your own account.');
                 // Note: The foreign key ON DELETE CASCADE will handle deleting the mentor/teacher profile.
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