<?php
ob_start();
// student_schedule.php
include 'includes/header.php';
require 'includes/db.php';

// Security Checks
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'mentor', 'supervisor'])) {
    header('location: login.php');
    exit;
}

// --- ADD THIS LINE HERE ---
$userRole = $_SESSION['role'];
$canEditTeacherPayout = $userRole === 'admin';
if (!$canEditTeacherPayout) {
    $permission_stmt = $conn->prepare('SELECT can_edit_teacher_payout FROM users WHERE id = ?');
    $permission_stmt->bind_param('i', $_SESSION['id']);
    $permission_stmt->execute();
    $canEditTeacherPayout = (bool) ($permission_stmt->get_result()->fetch_assoc()['can_edit_teacher_payout'] ?? false);
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<div class="container my-5"><div class="alert alert-danger">Error: Invalid student ID.</div></div>');
}
$student_id = intval($_GET['id']);

// Fetch basic student info - Added mentor_id here
$student_info = $conn->query("SELECT name, class, status, mentor_id FROM students WHERE id = $student_id")->fetch_assoc();

if (!$student_info) {
    die('<div class="container my-5"><div class="alert alert-danger">Error: Student not found.</div></div>');
}
// Store the mentor ID for the supervisor's back button
$assigned_mentor_id = $student_info['mentor_id'];
?>

<!-- Flatpickr for Multiple Dates -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- Select2 Bootstrap 5 Theme (Optional but recommended for styling) -->
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<!-- Add this to your <style> block in student_schedule.php to fix modal focus issues -->
<style>
.select2-container {
    z-index: 2000 !important;
}

/* Ensures search stays above modal */
</style>

<style>
body {
    background-color: #f0f2f5;
}

.page-header-gradient {
    background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s;
}

.schedule-card.status-active {
    border-left: 5px solid #0d6efd;
}

.session-table th {
    font-weight: 600;
    color: #555;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.modal-header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-bottom: none;
}

.cursor-pointer {
    cursor: pointer;
}

.cursor-pointer:hover {
    transform: scale(1.1);
}

/* Progress Bar Styling */
.progress-thin {
    height: 8px;
    border-radius: 10px;
    background-color: #e9ecef;
}

/* Make Table thumb-friendly on Mobile */
@media (max-width: 768px) {
    .session-table thead {
        display: none;
        /* Hide headers on mobile */
    }

    .session-table td[data-label="Hours"] {
        color: #4f46e5 !important;
        /* Makes the hours stand out on mobile */
        font-weight: 800;
    }

    .session-table tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 10px;
        background: #fff;
    }

    .session-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: none;
        padding: 5px 0;
        text-align: right;
    }

    .session-table td::before {
        content: attr(data-label);
        font-weight: bold;
        text-transform: uppercase;
        font-size: 0.7rem;
        color: #888;
        float: left;
    }

    .session-table td.text-end {
        justify-content: center;
        border-top: 1px solid #f8f9fa;
        margin-top: 10px;
        padding-top: 10px;
    }
}

/* Custom Status Badges */
.badge-soft-success {
    background: #d1e7dd;
    color: #0f5132;
}

.badge-soft-warning {
    background: #fff3cd;
    color: #664d03;
}

.badge-soft-danger {
    background: #f8d7da;
    color: #842029;
}

.badge-soft-primary {
    background: #cfe2ff;
    color: #084298;
}
</style>

<!-- Header -->
<!-- Responsive Header -->
<div
    class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm d-flex flex-column flex-md-row justify-content-md-between align-items-center gap-3">
    <!-- Text Section: Centered on mobile, Left-aligned on desktop -->
    <div class="text-center text-md-start">
        <h2 class="fw-bold mb-0">
            <i class="bi bi-calendar-week me-2"></i><?php echo htmlspecialchars($student_info['name']); ?>
        </h2>
        <p class="mb-0 opacity-75">
            <?php echo htmlspecialchars($student_info['class']); ?> | Manage Schedules
        </p>
    </div>


    <div class="d-flex flex-wrap justify-content-center gap-2">
        <?php if ($userRole === 'supervisor'): ?>
        <!-- SUPERVISOR BACK BUTTON: Goes to the Mentor's specific audit page -->
        <a href="supervisor_audit_mentor_detail.php?mentor_id=<?php echo $assigned_mentor_id; ?>"
            class="btn btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i> Back to Audit
        </a>
        <?php else: ?>


        <!-- ADMIN/MENTOR BACK BUTTON -->
        <a href="my_students.php" class="btn btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>

        <!-- NEW DEMO BUTTON (Hidden for Supervisor) -->
        <button class="btn btn-warning text-dark fw-bold shadow-sm" id="addDemoBtn">
            <i class="bi bi-play-circle-fill me-1"></i> Add Demo
        </button>

        <!-- Existing New Schedule Button (Hidden for Supervisor) -->
        <button class="btn btn-light text-primary fw-bold shadow-sm" id="addScheduleBtn">
            <i class="bi bi-plus-lg me-1"></i> New Schedule
        </button>
        <?php endif; ?>
    </div>



</div>
<div id="demoSessionsContainer" class="mb-4">
    <!-- Independent Demos will load here -->
</div>
<div id="schedulesContainer" class="pb-5">
    <div class="text-center p-5">
        <div class="spinner-border text-primary"></div>
    </div>
</div>

<!-- 1. Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white">Create New Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addScheduleForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_schedule">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <div class="alert alert-light small text-muted border mb-3">
                        <i class="bi bi-info-circle me-1"></i> Name auto-generated.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>

                        <!-- Total Hours (Visible to both Admin and Mentor) -->
                        <div class="col-md-6">
                            <label class="small fw-bold text-primary">Total Hours</label>
                            <input type="number" name="total_hours" class="form-control" step="0.05" required>
                        </div>

                        <!-- Paid Hours (RESTRICTED: Only Admin can see/set initial paid hours) -->
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="col-md-6">
                            <label class="small fw-bold text-success">Paid Hours</label>
                            <input type="number" name="paid_hours" class="form-control" step="0.05" value="0">
                        </div>
                        <?php else: ?>
                        <!-- If Mentor: Default to 0 paid hours hiddenly -->
                        <input type="hidden" name="paid_hours" value="0">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. EDIT SCHEDULE MODAL (FIXED) -->
<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Edit Schedule Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editScheduleForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_schedule">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">

                    <div class="mb-3">
                        <label class="small fw-bold">Schedule Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>

                        <!-- Total Hours (Added step="0.25") -->
                        <div class="col-md-6">
                            <label class="small fw-bold text-primary">Total Hours</label>
                            <input type="number" name="total_hours" id="edit_total_hours" class="form-control"
                                step="0.05" required>
                        </div>

                        <!-- Paid Hours (Added step="0.25") -->
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="col-md-6">
                            <label class="small fw-bold text-success">Paid Hours</label>
                            <input type="number" name="paid_hours" id="edit_paid_hours" class="form-control" step="0.05"
                                required>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="paid_hours" id="edit_paid_hours">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white">Bulk Add Sessions</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSessionForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_session">
                    <input type="hidden" name="schedule_id" id="session_schedule_id">

                    <div class="mb-3">
                        <label class="small fw-bold">Subject</label>
                        <select name="subject_id" id="subject_select" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">Teacher</label>
                        <select name="teacher_id" id="teacher_select" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-primary">Select Dates (Multiple)</label>
                        <input type="text" name="dates" id="multi_date_picker" class="form-control bg-white"
                            placeholder="Click to select dates..." required>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label class="small fw-bold">Duration (Hours)</label>
                                <input type="number" name="planned_hours" class="form-control" step="0.05" min="0"
                                    max="5" value="1" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label
                                    class="small fw-bold text-danger d-flex justify-content-between align-items-center">
                                    <span>Teacher Pay/Hour</span>
                                    <?php if ($canEditTeacherPayout): ?>

                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input cursor-pointer" type="checkbox"
                                            id="lock_pay_add">
                                        <label class="form-check-label text-muted" style="font-size: 0.65rem;"
                                            for="lock_pay_add">Edit</label>
                                    </div>
                                    <?php endif; ?>
                                </label>
                                <!-- Added readonly attribute and a light background class -->
                                <input type="number" name="teacher_pay" id="teacher_pay_input"
                                    class="form-control bg-light" step="0.01" required readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Sessions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. MARK ATTENDANCE MODAL -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card border-0">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white">Finalize Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="attendanceForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="save_attendance">
                    <input type="hidden" name="session_id" id="attendance_session_id">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <!-- Status Selection (Moved to Top) -->
                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold small d-block mb-2">Did the session take place?</label>
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="attendance_status" id="status_present" value="1"
                                checked>
                            <label class="btn btn-outline-success px-4" for="status_present"><i
                                    class="bi bi-check-circle me-1"></i> Class Taken (Present)</label>

                            <input type="radio" class="btn-check" name="attendance_status" id="status_absent" value="0">
                            <label class="btn btn-outline-danger px-4" for="status_absent"><i
                                    class="bi bi-x-circle me-1"></i> Class Not Taken (Absent/Missed)</label>
                        </div>
                    </div>

                    <hr>

                    <!-- Time & Decimal Duration -->
                    <div class="row g-3 mb-3" id="time_inputs_container">
                        <!-- <div class="col-md-6">
                            <label class="form-label fw-bold text-primary small">Actual Start Time <span
                                    class="text-danger">*</span></label>
                            <input type="time" name="actual_start_time" id="actual_start_time" class="form-control">
                        </div> -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold text-primary small">Duration (Hours) <span
                                    class="text-danger">*</span></label>
                            <!-- Min is 0 to allow script to handle it, but validation will enforce >0 for Present -->
                            <input type="number" name="actual_hours" id="att_duration_hours" class="form-control"
                                step="0.05" min="0">
                            <div class="form-text small">e.g. 1.0, 1.5, 1.20</div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold d-flex justify-content-between align-items-center">
                                <span>Teacher Payment Rate (Per Hour) ₹</span>
                                <?php if ($canEditTeacherPayout): ?>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input cursor-pointer" type="checkbox" id="lock_pay_att">
                                    <label class="form-check-label text-muted" style="font-size: 0.65rem;"
                                        for="lock_pay_att">Edit</label>
                                </div>
                                <?php endif; ?>
                            </label>
                            <input type="number" step="0.01" name="teacher_pay" class="form-control bg-light"
                                id="att_teacher_pay" readonly>
                            <small class="text-muted">This is pulled from the grade table.</small>
                        </div>
                    </div>



                    <div class="mb-3 d-none" id="absence_reason_container">
                        <label for="attendance_absence_reason" class="form-label fw-bold small">Reason for absence <span
                                class="text-danger">*</span></label>
                        <select name="notes" id="attendance_absence_reason" class="form-select" disabled required>
                            <option value="">Select a reason</option>
                            <option value="Due to Teacher">Due to Teacher</option>
                            <option value="Due to Students">Due to Students</option>
                        </select>
                    </div>

                    <div class="mb-3" id="session_notes_container">
                        <label for="attendance_notes" class="form-label fw-bold small">Remarks / Notes</label>
                        <textarea name="notes" id="attendance_notes" class="form-control" rows="2"
                            placeholder="e.g. Topics covered..."></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manage Exams/Marks Modal -->
<div class="modal fade" id="manageExamsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trophy me-2"></i>Exam Marks</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- FORM TO ADD MARK -->
                <form id="addExamForm" class="row g-2 mb-4 border-bottom pb-3">
                    <input type="hidden" name="action" value="add_exam">
                    <input type="hidden" name="schedule_id" id="exam_schedule_id">

                    <div class="col-md-12 mb-2">
                        <label class="small fw-bold">Subject</label>
                        <select name="subject_id" id="exam_subject_select" class="form-select" required></select>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">Date</label>
                        <input type="date" name="exam_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-danger">Mark Obtained</label>
                        <input type="text" name="obtained_mark" class="form-control" placeholder="e.g. 45/50" required>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-danger w-100">Add Mark to Schedule</button>
                    </div>
                </form>

                <!-- LIST OF MARKS -->
                <div id="examsListContainer"></div>
            </div>
        </div>
    </div>
</div>

<!-- Simplified Independent Demo Modal -->
<div class="modal fade" id="mentorDemoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-play-circle-fill me-2"></i>Book Independent Demo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="mentorDemoForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_mentor_demo">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <div class="mb-3">
                        <label class="small fw-bold">Subject</label>
                        <select name="subject_id" id="demo_subject_select" class="form-select" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold">Teacher</label>
                        <select name="teacher_id" id="demo_teacher_select" class="form-select" required></select>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="small fw-bold">Date</label>
                            <input type="date" name="demo_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-primary">Duration (Hours)</label>
                            <input type="number" name="duration_hours" class="form-control" step="0.05" min="0"
                                value="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">Save Demo Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Demo Modal (Admin Only) -->
<div class="modal fade" id="editDemoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Demo Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDemoForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_mentor_demo">
                    <input type="hidden" name="demo_id" id="edit_demo_id">

                    <div class="mb-3">
                        <label class="small fw-bold">Subject</label>
                        <select name="subject_id" id="edit_demo_subject" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Teacher</label>
                        <select name="teacher_id" id="edit_demo_teacher" class="form-select" required></select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="small fw-bold">Date</label>
                            <input type="date" name="demo_date" id="edit_demo_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Duration (Hrs)</label>
                            <input type="number" name="duration_hours" id="edit_demo_duration" class="form-control"
                                step="0.05" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="submit" class="btn btn-dark w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script>
$(document).ready(function() {
    // 1. Core Variables
    const userRole = "<?php echo $_SESSION['role']; ?>";
    const studentId = <?php echo $student_id; ?>;
    let teachersAndSubjects = {};

    // 2. Persistent Accordion State Key (Unique per student)
    const storageKey = "open_sch_stu_" + studentId;

    /**
     * MAIN FUNCTION: Load Schedules and Demos
     */
    function loadScheduleDetails() {
        $('#schedulesContainer').html(
            '<div class="text-center p-5"> <div class = "spinner-border text-primary" > </div> </div>');
        $('#demoSessionsContainer').empty();

        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth();

        $.get('student_schedule_action.php', {
            action: 'get_schedule_details',
            student_id: studentId
        }, function(res) {
            if (res.status === 'success') {
                $('#schedulesContainer').empty();

                if (res.data.schedules && res.data.schedules.length > 0) {
                    let totalSchedules = res.data.schedules.length;
                    let accordionHtml =
                        '<div class="accordion accordion-flush" id="schedulesAccordion">';

                    // --- ACCORDION PERSISTENCE LOGIC ---
                    const lastOpenId = localStorage.getItem(storageKey);

                    res.data.schedules.forEach(function(sch, index) {
                        let displayTitle = (!sch.name || sch.name === 'null') ?
                            "Schedule " + (
                                totalSchedules - index) : sch.name;
                        let total = parseFloat(sch.total_hours) || 0;
                        let paid = parseFloat(sch.paid_hours) || 0;
                        let examCount = sch.exams ? sch.exams.length : 0;
                        let completedHours = 0;
                        let sessionsRows = '';

                        // Determine if this specific item should be open based on localStorage
                        let isThisOpen = (lastOpenId === "collapse" + sch.id);
                        let collapseClass = isThisOpen ? "show" : "";
                        let btnClass = isThisOpen ? "" : "collapsed";

                        if (sch.sessions && sch.sessions.length > 0) {
                            sch.sessions.forEach(sess => {
                                if (sess.status === 'done') completedHours +=
                                    parseFloat(sess.planned_hours) || 0;

                                let statusClass = sess.status === 'done' ?
                                    'badge-soft-success' : (sess.status ===
                                        'missed' ?
                                        'badge-soft-danger' : 'badge-soft-warning');
                                let durationVal = parseFloat(sess.planned_hours);

                                const sessDate = new Date(sess.date);
                                const isPastMonth = (sessDate.getFullYear() <
                                    currentYear) || (sessDate.getFullYear() ===
                                    currentYear &&
                                    sessDate.getMonth() < currentMonth);
                                let actionBtn = '';
                                if (userRole === 'supervisor') {
                                    actionBtn = `<span
            class="badge bg-light text-dark border small">Read Only</span>`;
                                } else if (userRole === 'mentor' && isPastMonth &&
                                    sess
                                    .status !== 'planned') {
                                    actionBtn =
                                        `<span class="badge bg-light text-muted border small"><i class="bi bi-lock-fill"></i> Locked</span>`;
                                } else {
                                    let btnColor = sess.status === 'planned' ?
                                        'btn-primary' : 'btn-outline-success';
                                    actionBtn =
                                        `<button class="btn btn-sm ${btnColor} btn-mark-attendance" data-session-id="${sess.id}"
                data-hours="${sess.planned_hours}"><i
                    class="bi ${sess.status === 'planned' ? 'bi-check2-circle' : 'bi-pencil-square'}"></i></button>`;
                                }

                                let deleteBtn = (userRole !== 'supervisor' && sess
                                        .status === 'planned' && !(userRole ===
                                            'mentor' && isPastMonth)) ?
                                    `<button class="btn btn-sm btn-outline-danger ms-1 btn-delete-session" data-id="${sess.id}"><i
                    class="bi bi-trash"></i></button>` :
                                    '';

                                sessionsRows += `
            <tr>
                <td><small class="fw-bold d-block text-dark">${sess.subject_name}</small><small
                        class="text-muted">${sess.date}</small></td>
                <td><span class="small">${sess.teacher_name}</span></td>
                <td class="text-center fw-bold text-primary">${durationVal}</td>
                <td><span class="badge ${statusClass}">${sess.status.substring(0,1).toUpperCase()}</span></td>
                <td class="text-end">${actionBtn}${deleteBtn}</td>
            </tr>`;
                            });
                        } else {
                            sessionsRows =
                                '<tr> <td colspan = "5" class = "text-center text-muted small py-4" > No sessions yet. </td> </tr> ';
                        }

                        completedHours = parseFloat(completedHours.toFixed(2));
                        let progressPercent = total > 0 ? (completedHours / total) * 100 :
                            0;
                        let feeStatus = (paid >= total && total > 0) ? 'text-success' : (
                            paid >
                            0 ? 'text-warning' : 'text-danger');

                        let toolbarHtml = '';
                        let footerHtml = '';

                        if (userRole === 'supervisor') {
                            toolbarHtml =
                                `<div class="w-100 mb-3"><button class="btn btn-sm btn-outline-primary w-100 py-2 btn-manage-exams"
                    data-id="${sch.id}" data-exams='${JSON.stringify(sch.exams || [])}'><i
                        class="bi bi-award me-2"></i>View Exam Marks (${examCount})</button></div>`;
                        } else {
                            toolbarHtml = `<div class="d-flex flex-wrap gap-2 mb-3 bg-light p-2 rounded">
                <button class="btn btn-sm btn-white border btn-edit-schedule" data-id="${sch.id}"><i
                        class="bi bi-gear me-1"></i>Edit</button>
                <button class="btn btn-sm btn-outline-danger btn-delete-schedule" data-id="${sch.id}"><i
                        class="bi bi-trash"></i></button>
                <button class="btn btn-sm btn-outline-primary btn-manage-exams" data-id="${sch.id}"
                    data-exams='${JSON.stringify(sch.exams || [])}'><i class="bi bi-award me-1"></i>Exams
                    (${examCount})</button>
            </div>`;
                            footerHtml =
                                `<div class="card-footer bg-light border-0"><button
                    class="btn btn-sm btn-primary w-100 py-2 btn-add-session" data-schedule-id="${sch.id}">+ Add New
                    Sessions</button></div>`;
                        }

                        accordionHtml += `
            <div class="accordion-item shadow-sm border mb-3" style="border-radius:1rem !important; overflow:hidden;">
                <h2 class="accordion-header">
                    <button class="accordion-button ${btnClass}" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse${sch.id}">
                        <div class="w-100 me-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark">${displayTitle}</span>
                                <span class="badge rounded-pill bg-light text-primary border"
                                    style="font-size:0.7rem">${Math.round(progressPercent)}% Done</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="text-muted" style="font-size:0.7rem">${sch.start_date} to
                                    ${sch.end_date}</span>
                                <i class="bi bi-cash-stack ${feeStatus}"></i>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="collapse${sch.id}" class="accordion-collapse collapse ${collapseClass}"
                    data-bs-parent="#schedulesAccordion">
                    <div class="accordion-body p-3">
                        ${toolbarHtml}
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-3">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th>Details</th>
                                        <th>Tutor</th>
                                        <th class="text-center">Hrs</th>
                                        <th>St</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody>${sessionsRows}</tbody>
                            </table>
                        </div>
                        ${footerHtml}
                    </div>
                </div>
            </div>`;
                    });
                    accordionHtml += '</div>';
                    $('#schedulesContainer').html(accordionHtml);
                } else {
                    $('#schedulesContainer').html(
                        '<div class="text-center py-5 text-muted">No regular schedules found.</div>'
                    );
                }

                // --- PART B: INDEPENDENT MENTOR DEMOS ---
                if (res.data.mentor_demos && res.data.mentor_demos.length > 0) {
                    let demoRows = '';
                    res.data.mentor_demos.forEach(d => {
                        let adminDemoBtns = (userRole === 'admin') ?
                            `<button class="btn btn-sm btn-link text-dark p-0 me-2 btn-edit-demo" data-id="${d.id}"><i
            class="bi bi-pencil"></i></button><button class="btn btn-sm btn-link text-danger p-0 btn-delete-demo"
        data-id="${d.id}"><i class="bi bi-trash"></i></button>` :
                            '';
                        demoRows +=
                            `<tr>
        <td><b>${d.subject_name}</b><br><small>${d.demo_date}</small></td>
        <td>${d.teacher_name}</td>
        <td class="text-center">${d.duration_hours}</td>
        <td class="text-end">${adminDemoBtns}</td>
    </tr>`;
                    });
                    $('#demoSessionsContainer').html(
                        `<div class="card border-warning mb-4 shadow-sm" style="border-radius:1rem; overflow:hidden;">
        <div class="card-header bg-warning py-2 fw-bold small text-dark"><i class="bi bi-star-fill me-2"></i>Independent
            Demos</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <tbody>${demoRows}</tbody>
            </table>
        </div>
    </div>`
                    );
                }
            }
        }, 'json');
    }

    // --- ACCORDION STATE SAVING (Persistent between refreshes/actions) ---
    $(document).on('shown.bs.collapse', '#schedulesAccordion .accordion-collapse', function() {
        localStorage.setItem(storageKey, $(this).attr('id'));
    });
    $(document).on('hidden.bs.collapse', '#schedulesAccordion .accordion-collapse', function() {
        if (localStorage.getItem(storageKey) === $(this).attr('id')) {
            localStorage.removeItem(storageKey);
        }
    });

    // --- SELECT2 TEACHER SEARCH HELPER ---
    function initTeacherSelect(selectId, modalId) {
        let tOpts = '<option value="">Search Teacher by Name or Code...</option>';
        teachersAndSubjects.teachers.sort((a, b) => a.name.localeCompare(b.name)).forEach(t => {
            tOpts += `<option value="${t.id}">${t.name} [${t.teacher_reg_code || 'N/A'}]</option>`;
        });
        $(selectId).html(tOpts).select2({
            theme: "bootstrap-5",
            dropdownParent: $(modalId),
            width: '100%'
        });
    }

    // --- BUTTON HANDLERS: INDEPENDENT DEMOS ---
    $('#addDemoBtn').click(function() {
        let tOpts = '<option value="">Search Teacher by Name or Code...</option>';
        teachersAndSubjects.teachers.forEach(t => {
            tOpts +=
                `<option value="${t.id}">${t.name} [${t.teacher_reg_code || 'No Code'}]</option>`;
        });
        $('#demo_teacher_select').html(tOpts);

        $('#demo_teacher_select').select2({
            theme: "bootstrap-5",
            dropdownParent: $('#mentorDemoModal')
        });


        let sOpts = '<option value="">Select Subject</option>';
        teachersAndSubjects.subjects.forEach(s => sOpts +=
            `<option value="${s.id}">${s.name}</option>`);
        $('#demo_subject_select').html(sOpts);
        $('#mentorDemoModal').modal('show');
    });

    $('#mentorDemoForm').on('submit', function(e) {
        e.preventDefault();
        $.post('student_schedule_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#mentorDemoModal').modal('hide');
                loadScheduleDetails();
                Swal.fire('Saved!', 'Demo recorded.', 'success');
            }
        }, 'json');
    });
    // 1. Fetch Demo for Edit
    $('#demoSessionsContainer').on('click', '.btn-edit-demo', function() {
        const id = $(this).data('id');
        $.get('student_schedule_action.php', {
            action: 'get_single_mentor_demo',
            demo_id: id
        }, function(res) {
            if (res.status === 'success') {
                const d = res.data;
                $('#edit_demo_id').val(d.id);
                $('#edit_demo_date').val(d.demo_date);
                $('#edit_demo_duration').val(d.duration_hours);

                // Populate dropdowns
                let tOpts = '';
                teachersAndSubjects.teachers.forEach(t => {
                    tOpts +=
                        `<option value="${t.id}" ${t.id==d.teacher_id ? 'selected' :''}>${t.name} [${t.teacher_reg_code}]</option>`;
                });
                $('#edit_demo_teacher').html(tOpts);

                $('#edit_demo_teacher').select2({
                    theme: "bootstrap-5",
                    dropdownParent: $('#editDemoModal')
                });

                let sOpts = '';
                teachersAndSubjects.subjects.forEach(s => sOpts +=
                    `<option value="${s.id}" ${s.id==d.subject_id ? 'selected' :''}>${s.name}</option>`
                );
                $('#edit_demo_subject').html(sOpts);

                $('#editDemoModal').modal('show');
            }
        }, 'json');
    });

    // 2. Submit Edit Form
    $('#editDemoForm').on('submit', function(e) {
        e.preventDefault();
        $.post('student_schedule_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#editDemoModal').modal('hide');
                loadScheduleDetails();
            }
        }, 'json');
    });

    // 3. Delete Demo
    $('#demoSessionsContainer').on('click', '.btn-delete-demo', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete this record?',
            icon: 'warning',
            showCancelButton: true
        }).then(r => {
            if (r.isConfirmed) $.post('student_schedule_action.php', {
                action: 'delete_mentor_demo',
                demo_id: id
            }, () => loadScheduleDetails(), 'json');
        });
    });

    // --- BUTTON HANDLERS: EDIT SCHEDULE (POPULATE BUG FIXED) ---
    $('#schedulesContainer').on('click', '.btn-edit-schedule', function() {
        let id = $(this).data('id');
        $('#editScheduleForm')[0].reset();
        $.get('student_schedule_action.php', {
            action: 'get_single_schedule',
            schedule_id: id
        }, function(res) {
            if (res.status === 'success') {
                let d = res.data;
                $('#edit_schedule_id').val(d.id);
                $('#edit_name').val(d.name);
                $('#edit_start_date').val(d.start_date);
                $('#edit_end_date').val(d.end_date);
                $('#edit_total_hours').val(parseFloat(d.total_hours) ||
                    0); // Correctly fetch numeric data
                $('#edit_paid_hours').val(parseFloat(d.paid_hours) || 0);
                $('#editScheduleModal').modal('show');
            }
        }, 'json');
    });

    // --- BUTTON HANDLERS: ADD SESSIONS (DATE RANGE CHECK) ---
    $('#schedulesContainer').on('click', '.btn-add-session', function() {
        const scheduleId = $(this).data('schedule-id');
        $.get('student_schedule_action.php', {
            action: 'get_single_schedule',
            schedule_id: scheduleId
        }, function(res) {
            if (res.status === 'success') {
                const sch = res.data;
                if (!sch.start_date || !sch.end_date || sch.start_date === '0000-00-00') {
                    Swal.fire({
                            title: 'Dates Required!',
                            text: 'Set Schedule dates first.',
                            icon: 'warning'
                        })
                        .then(() => $('.btn-edit-schedule[data-id="' + scheduleId + '"]')
                            .click());
                    return;
                }

                const currentMonthStart = "<?php echo date('Y-m-01'); ?>";
                const minDate = userRole === 'admin' || sch.start_date > currentMonthStart ?
                    sch.start_date : currentMonthStart;
                const maxDate = sch.end_date;

                if (minDate > maxDate) {
                    Swal.fire({
                        title: 'No Available Dates',
                        text: userRole === 'admin' ? 'This schedule has no available dates.' :
                            'This schedule has no dates from the current month onward.',
                        icon: 'warning'
                    });
                    return;
                }

                $('#session_schedule_id').val(scheduleId);
                const dateInput = document.querySelector('#multi_date_picker');
                if (dateInput._flatpickr) dateInput._flatpickr.destroy();
                flatpickr(dateInput, {
                    mode: "multiple",
                    dateFormat: "Y-m-d",
                    minDate: minDate,
                    maxDate: maxDate
                });




                let tOpts = '<option value="">Search Teacher by Name or Code...</option>';
                teachersAndSubjects.teachers.forEach(t => {
                    // Display format: "John Doe (T-101)"
                    tOpts +=
                        `<option value="${t.id}">${t.name} [${t.teacher_reg_code || 'No Code'}]</option>`;
                });
                $('#teacher_select').html(tOpts);

                // INITIALIZE SEARCHABLE SELECT
                $('#teacher_select').select2({
                    theme: "bootstrap-5",
                    dropdownParent: $(
                        '#addSessionModal'
                    ) // IMPORTANT: Needed for search focus to work in modals
                });

                let sOpts = '<option value="">Select Subject</option>';
                teachersAndSubjects.subjects.forEach(s => sOpts +=
                    `<option value="${s.id}">${s.name}</option>`);
                $('#subject_select').html(sOpts);

                $.get('student_schedule_action.php', {
                    action: 'get_default_pay',
                    schedule_id: scheduleId
                }, function(p) {
                    $('#teacher_pay_input').val(p.pay_rate || '0.00');
                }, 'json');
                $('#addSessionModal').modal('show');
            }
        }, 'json');
    });

    // --- BUTTON HANDLERS: ATTENDANCE ---
    $('#schedulesContainer').on('click', '.btn-mark-attendance', function() {
        const sessionId = $(this).data('session-id');
        $('#attendance_session_id').val(sessionId);
        $.get('student_schedule_action.php', {
            action: 'get_single_session',
            session_id: sessionId
        }, function(res) {
            if (res.status === 'success') {
                const d = res.data;
                const isAbsent = d.status === 'missed';
                const absenceReasons = ['Due to Teacher', 'Due to Students'];
                $('#attendance_notes').val(isAbsent ? '' : (d.notes || ''));
                $('#attendance_absence_reason').val(
                    isAbsent && absenceReasons.includes(d.notes) ? d.notes : ''
                );
                $('#att_teacher_pay').val(parseFloat(d.teacher_pay) > 0 ? d.teacher_pay : d
                    .default_pay);
                $(isAbsent ? '#status_absent' : '#status_present').prop('checked', true).trigger('change');
                $('#att_duration_hours').val(isAbsent ? 0 : d.planned_hours);
                $('#attendanceModal').modal('show');
            }
        }, 'json');
    });

    $('input[name="attendance_status"]').on('change', function() {
        const isAbs = $('#status_absent').is(':checked');
        const durationInput = $('#att_duration_hours').prop('disabled', isAbs);
        if (isAbs) durationInput.val('0');
        else if (durationInput.val() === '0') durationInput.val('');
        $('#absence_reason_container').toggleClass('d-none', !isAbs);
        $('#session_notes_container').toggleClass('d-none', isAbs);
        $('#attendance_absence_reason').prop({
            disabled: !isAbs,
            required: isAbs
        });
        $('#attendance_notes').prop({
            disabled: isAbs,
            required: false
        });
    });

    // --- EXAM MANAGEMENT (SUPERVISOR RESTRICTED) ---
    $('#schedulesContainer').on('click', '.btn-manage-exams', function() {
        const schId = $(this).data('id');
        const exams = $(this).data('exams');
        $('#exam_schedule_id').val(schId);

        // HIDE FORM IF SUPERVISOR
        if (userRole === 'supervisor') {
            $('#addExamForm').hide();
        } else {
            $('#addExamForm').show();
        }

        let sOpts = '<option value="">Select Subject</option>';
        teachersAndSubjects.subjects.forEach(s => sOpts +=
            `<option value="${s.id}">${s.name}</option>`);
        $('#exam_subject_select').html(sOpts);
        renderExamsList(exams);
        $('#manageExamsModal').modal('show');
    });

    function renderExamsList(exams) {
        let html = '';
        if (!exams || exams.length === 0) {
            html = '<div class="text-center text-muted py-3 small">No marks recorded yet.</div>';
        } else {
            html =
                '<div class="table-responsive"> <table class = "table table-sm align-middle"><tbody> ';
            exams.forEach(ex => {
                // HIDE DELETE BUTTON IF SUPERVISOR
                let deleteCell = (userRole !== 'supervisor') ?
                    `<td class="text-end"><button class="btn btn-sm text-danger btn-delete-exam" data-id="${ex.id}"><i
                            class="bi bi-trash"></i></button></td>` :
                    '';

                html += `<tr>
                    <td><b>${ex.subject_name}</b><br><small>${ex.exam_date}</small></td>
                    <td><span class="badge bg-danger">${ex.obtained_mark}</span></td>
                    ${deleteCell}
                </tr>`;
            });
            html += '</tbody> </table> </div> ';
        }
        $('#examsListContainer').html(html);
    }
    $('#addExamForm').on('submit', function(e) {
        e.preventDefault();
        $.post('student_schedule_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#manageExamsModal').modal('hide');
                loadScheduleDetails();
            }
        }, 'json');
    });

    $('#manageExamsModal').on('click', '.btn-delete-exam', function() {
        const id = $(this).data('id');
        if (confirm('Delete mark?')) $.post('student_schedule_action.php', {
            action: 'delete_exam',
            id: id
        }, () => loadScheduleDetails());
    });

    // --- BUTTON HANDLERS: NEW SCHEDULE & DELETIONS ---
    $('#addScheduleBtn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true);
        $.get('student_schedule_action.php', {
            action: 'check_pending_exams',
            student_id: studentId
        }, function(res) {
            btn.prop('disabled', false);
            if (res.status === 'success' && res.pending.length > 0) {
                if (userRole === 'admin') {
                    Swal.fire({
                            title: 'Reminder',
                            text: 'Exams missing for previous schedules.',
                            icon: 'info',
                            showCancelButton: true
                        })
                        .then(r => {
                            if (r.isConfirmed) $('#addScheduleModal').modal('show');
                        });
                } else {
                    const missing = res.pending[0];
                    Swal.fire({
                            title: 'Reason Required',
                            html: `No exam for <b>${missing.name}</b>. Why?`,
                            input: 'textarea',
                            preConfirm: (v) => v || Swal.showValidationMessage(
                                'Required')
                        })
                        .then(r => {
                            if (r.isConfirmed) $.post('student_schedule_action.php', {
                                    action: 'submit_exam_skip_reason',
                                    schedule_id: missing.id,
                                    reason: r.value
                                }, () => $('#addScheduleModal').modal('show'),
                                'json');
                        });
                }
            } else {
                $('#addScheduleModal').modal('show');
            }
        }, 'json');
    });

    $('#schedulesContainer').on('click', '.btn-delete-schedule', function() {
        const id = $(this).data('id');
        Swal.fire({
                title: 'Delete Schedule?',
                text: 'Mentors can only delete empty/unpaid schedules.',
                icon: 'warning',
                showCancelButton: true
            })
            .then(r => {
                if (r.isConfirmed) $.post('student_schedule_action.php', {
                    action: 'delete_schedule',
                    schedule_id: id
                }, (res) => {
                    if (res.status === 'success') loadScheduleDetails();
                    else Swal.fire('Error', res.message, 'error');
                }, 'json');
            });
    });

    $('#schedulesContainer').on('click', '.btn-delete-session', function() {
        const id = $(this).data('id');
        if (confirm('Delete session?')) $.post('student_schedule_action.php', {
            action: 'delete_session',
            session_id: id
        }, () => loadScheduleDetails());
    });

    // Universal Pay Field Lock
    $(document).on('change', '#lock_pay_add, #lock_pay_att', function() {
        const target = $(this).attr('id') === 'lock_pay_add' ? '#teacher_pay_input' :
            '#att_teacher_pay';
        $(target).prop('readonly', !$(this).is(':checked')).toggleClass('bg-light', !$(this).is(
            ':checked'));
    });

    // Final Submit Handler
    $('#addScheduleForm, #editScheduleForm, #addSessionForm, #attendanceForm').on('submit', function(e) {
        e.preventDefault();
        $.post('student_schedule_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('.modal').modal('hide');
                loadScheduleDetails();
                Swal.fire('Success', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    // --- INITIALIZATION ---
    loadScheduleDetails();
    $.get('student_schedule_action.php', {
        action: 'get_teachers_and_subjects'
    }, function(res) {
        teachersAndSubjects = res.data;
    }, 'json');
});
</script>