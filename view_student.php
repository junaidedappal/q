<?php
include 'includes/header.php';
require 'includes/db.php';

// --- Security Checks ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    header("location: login.php");
    exit;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // A more user-friendly error page
    die('<div class="alert alert-danger">Error: Invalid student ID provided. <a href="admissions.php">Go Back</a></div>');
}
$student_id = intval($_GET['id']);

// --- Fetch Student Data ---
$sql = "SELECT s.*, COALESCE(u.name, 'Not Assigned') as mentor_name, u.email as mentor_email
        FROM students s
        LEFT JOIN mentors m ON s.mentor_id = m.id
        LEFT JOIN users u ON m.user_id = u.id
        WHERE s.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    die('<div class="alert alert-danger">Error: Student with this ID was not found. <a href="admissions.php">Go Back</a></div>');
}

// Helper function to display data or a placeholder
function display_info($value, $placeholder = 'Not Provided') {
    echo htmlspecialchars(!empty($value) ? $value : $placeholder);
}
?>

<style>
/* Custom styling for the view page */
body {
    background-color: #f8f9fa;
    /* Lighter grey for a modern feel */
}

.profile-header {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    border-radius: 1.25rem;
    padding: 2.5rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
    transform: rotate(30deg);
}

.profile-card {
    border-radius: 1.25rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    border: none;
    margin-top: -4rem;
    /* Increase overlap for a more dramatic effect */
    background: #ffffff;
    z-index: 10;
    position: relative;
}

.profile-tabs .nav-link {
    color: #6c757d;
    font-weight: 600;
    border: none;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.profile-tabs .nav-link.active {
    color: #2575fc;
    border-bottom: 3px solid #2575fc;
}

.info-label {
    font-weight: 600;
    color: #adb5bd;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1.1rem;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.info-value i {
    color: #6a11cb;
}

.status-badge.active {
    background-color: #198754 !important;
}

.status-badge.inactive {
    background-color: #6c757d !important;
}

.btn-gradient-back {
    background: linear-gradient(135deg, #fd7e14 0%, #ff5555 100%);
    border: none;
    color: white;
}
</style>

<!-- Profile Header -->
<div class="profile-header text-center shadow-lg">
    <i class="bi bi-person-bounding-box" style="font-size: 4rem;"></i>
    <h1 class="display-4 fw-bold mt-2"><?php display_info($student['name']); ?></h1>
    <p class="lead mb-0">Student ID: #<?php echo $student['id']; ?></p>
</div>

<!-- Main Profile Card with Tabs -->
<div class="profile-card p-4">
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs profile-tabs mb-4" id="studentTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details"
                type="button" role="tab">Primary Details</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button"
                role="tab">Notes & Remarks</button>
        </li>
        <!-- Add more tabs here in the future, e.g., for Attendance -->
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="studentTabContent">
        <!-- Primary Details Tab Pane -->
        <div class="tab-pane fade show active" id="details" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="info-label">Current Status</div>
                    <div class="info-value">
                        <?php if ($student['status'] === 'active'): ?>
                        <span class="badge fs-6 rounded-pill status-badge active py-2 px-3">Active</span>
                        <?php else: ?>
                        <span class="badge fs-6 rounded-pill status-badge inactive py-2 px-3">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Assigned Mentor</div>
                    <div class="info-value"><i
                            class="bi bi-person-workspace"></i><?php display_info($student['mentor_name']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Student Phone</div>
                    <div class="info-value"><i
                            class="bi bi-telephone-fill"></i><?php display_info($student['phone']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Parent Phone</div>
                    <div class="info-value"><i
                            class="bi bi-phone-fill"></i><?php display_info($student['parent_phone']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Class</div>
                    <div class="info-value"><i
                            class="bi bi-mortarboard-fill"></i><?php display_info($student['class']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Syllabus</div>
                    <div class="info-value"><i class="bi bi-book-half"></i><?php display_info($student['syllabus']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Tab Pane -->
        <div class="tab-pane fade" id="notes" role="tabpanel">
            <h5 class="mb-3">Previous Performance</h5>
            <div class="p-3 bg-light rounded mb-4">
                <p class="text-muted fst-italic mb-0">
                    <?php echo nl2br(htmlspecialchars(!empty($student['previous_performance']) ? $student['previous_performance'] : 'No performance notes were recorded.')); ?>
                </p>
            </div>

            <h5 class="mb-3">General Remarks</h5>
            <div class="p-3 bg-light rounded">
                <p class="text-muted fst-italic mb-0">
                    <?php echo nl2br(htmlspecialchars(!empty($student['remarks']) ? $student['remarks'] : 'No general remarks were recorded.')); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Back Button -->
<div class="text-center mt-4">
    <a href="admissions.php" class="btn btn-gradient-back px-4 shadow-sm"><i class="bi bi-arrow-left me-2"></i>Back to
        Admissions List</a>
</div>

<?php include 'includes/footer.php'; ?>