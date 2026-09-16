<?php
// We include the header at the very top to handle sessions and the opening HTML structure.
include 'includes/header.php';
require 'includes/db.php';
// --- Security Checks ---
// 1. User must be logged in with an appropriate role.
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header("location: login.php");
    exit;
}
// 2. An 'id' parameter must exist in the URL and it must be a number.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Display a user-friendly error message within the page structure.
    echo '<div class="container my-4"><div class="alert alert-danger">Error: Invalid enquiry ID provided. <a href="demos.php" class="alert-link">Return to Demo Dashboard</a>.</div></div>';
    include 'includes/footer.php';
    exit; // Stop executing the rest of the page.
}
$enquiry_id = intval($_GET['id']);

// --- Fetch Main Enquiry Data ---
$enquiry_stmt = $conn->prepare("SELECT * FROM enquiries WHERE id = ?");
$enquiry_stmt->bind_param("i", $enquiry_id);
$enquiry_stmt->execute();
$enquiry = $enquiry_stmt->get_result()->fetch_assoc();

// If no enquiry is found with that ID, show an error.
if (!$enquiry) {
    echo '<div class="container my-4"><div class="alert alert-danger">Error: Enquiry not found. <a href="demos.php" class="alert-link">Return to Demo Dashboard</a>.</div></div>';
    include 'includes/footer.php';
    exit;
}

// A small helper function to display data cleanly, showing 'N/A' if empty.
function display_data($value, $placeholder = 'N/A') {
    echo htmlspecialchars(!empty($value) ? $value : $placeholder);
}
?>

<style>
/* CSS for this specific view page */
body {
    background-color: #f8f9fa;
}

.page-header-gradient {
    background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
}

.info-card {
    background: #ffffff;
    border-radius: 1rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.07);
    border: none;
    height: 100%;
    /* Ensures cards in the same row have equal height */
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
    color: #5b86e5;
    font-size: 1.2rem;
}
</style>

<!-- Stylish Page Header -->
<div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex flex-column flex-sm-row align-items-center text-center text-sm-start">
        <i class="bi bi-clipboard2-data-fill display-4 mb-2 mb-sm-0 me-sm-3"></i>
        <div>
            <h2 class="fw-bold mb-0">Demo Profile: <?php display_data($enquiry['student_name']); ?></h2>
            <p class="mb-0">A complete overview and management hub for the student's demo journey.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Enquiry Details (Static Information) -->
    <div class="col-lg-5">
        <div class="info-card p-4">
            <h4 class="mb-4 border-bottom pb-2 text-primary">Enquiry Details</h4>

            <div class="mb-3">
                <div class="info-label">Student Name</div>
                <div class="info-value"><i
                        class="bi bi-person-fill"></i><?php display_data($enquiry['student_name']); ?></div>
            </div>
            <div class="mb-3">
                <div class="info-label">Class</div>
                <div class="info-value"><i class="bi bi-mortarboard-fill"></i><?php display_data($enquiry['class']); ?>
                </div>
            </div>
            <div class="mb-3">
                <div class="info-label">Syllabus</div>
                <div class="info-value"><i class="bi bi-book-half"></i><?php display_data($enquiry['syllabus']); ?>
                </div>
            </div>

            <!-- NEW: Selected Plan Section -->
            <div class="mb-3">
                <div class="info-label">Selected Plan</div>
                <div class="info-value text-success">
                    <i class="bi bi-calendar-check-fill"></i>
                    <strong><?php display_data($enquiry['selected_plan'], 'Not Assigned Yet'); ?></strong>
                </div>
            </div>
            <!-- End New Section -->

            <hr class="my-4">
            <h5 class="text-muted mb-3">Contact Information</h5>
            <div class="mb-3">
                <div class="info-label">Student Phone</div>
                <div class="info-value"><i class="bi bi-telephone-fill"></i><?php display_data($enquiry['phone']); ?>
                </div>
            </div>
            <div class="mb-3">
                <div class="info-label">Parent Phone</div>
                <div class="info-value"><i class="bi bi-phone-fill"></i><?php display_data($enquiry['parent_phone']); ?>
                </div>
            </div>

            <hr class="my-4">
            <h5 class="text-muted mb-3">Notes from Sales Team</h5>
            <div class="mb-3">
                <div class="info-label">Previous Performance</div>
                <p class="text-muted fst-italic bg-light p-2 rounded">
                    <?php echo nl2br(htmlspecialchars(!empty($enquiry['previous_performance']) ? $enquiry['previous_performance'] : 'No notes provided.')); ?>
                </p>
            </div>
            <div class="mb-3">
                <div class="info-label">General Remarks</div>
                <p class="text-muted fst-italic bg-light p-2 rounded">
                    <?php echo nl2br(htmlspecialchars(!empty($enquiry['remarks']) ? $enquiry['remarks'] : 'No remarks provided.')); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Right Column: Interactive Demo Management -->
    <div class="col-lg-7">
        <div class="info-card p-4 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-4">
                <h4 class="mb-0 text-primary">Demo Management</h4>
                <div>
                    <span class="info-label d-block text-end">Overall Status</span>
                    <?php
                        // === FIX #1: ADDED 'admission_finalized' STATUS CHECK ===
                        $status_text = 'Admission Pending';
                        $status_class = 'bg-warning text-dark';
                        if ($enquiry['status'] === 'admission_confirmed') {
                            $status_text = 'Admission Confirmed';
                            $status_class = 'bg-info';
                        } elseif ($enquiry['status'] === 'admission_finalized') {
                            $status_text = 'Admission Finalized';
                            $status_class = 'bg-success';
                        }
                        echo '<span id="overallStatusBadge" class="badge fs-6 rounded-pill '.$status_class.'">'.$status_text.'</span>';
                    ?>
                </div>
            </div>

            <!-- This container will be filled dynamically by JavaScript -->
            <div id="subjectStatusContainer" class="flex-grow-1">
                <!-- Initial loading spinner for better user experience -->
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>

            <!-- Card Footer with Action Buttons -->
            <?php if ($enquiry['status'] != 'admission_finalized'): ?>
            <div class="card-footer bg-transparent border-0 mt-auto text-center pt-4">
                <button type="button" class="btn btn-success px-4 shadow-sm" id="approveAdmissionBtn">
                    <i class="bi bi-check2-circle me-2"></i>Approve for Admission
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Back Button -->
<div class="text-center my-4">
    <a href="demos.php" class="btn btn-secondary px-4"><i class="bi bi-arrow-left me-2"></i>Back to Demo Dashboard</a>
</div>


<!-- =================================================================== -->
<!-- =================== ALL MANAGEMENT MODALS ========================= -->
<!-- =================================================================== -->

<!-- Schedule Demo Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Demo</h5>
            </div>
            <form id="scheduleForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="schedule_demo">
                    <input type="hidden" name="enquiry_id" id="schedule_enquiry_id">
                    <input type="hidden" name="subject_id" id="schedule_subject_id">
                    <div class="mb-3"><label class="form-label">Subject</label><input type="text"
                            id="schedule_subject_name" class="form-control" readonly></div>
                    <div class="mb-3"><label class="form-label">Assign Teacher</label><select class="form-select"
                            name="teacher_id" id="teacher_id_schedule" required></select></div>
                    <div class="mb-3"><label class="form-label">Date and Time</label><input type="datetime-local"
                            class="form-control" name="datetime" required></div>
                    <div class="mb-3"><label class="form-label">Duration (minutes)</label><input type="number"
                            class="form-control" name="duration" value="60" required></div>
                    <div class="mb-3 d-none"><label class="form-label">Meeting Link</label><input type="url"
                            class="form-control" name="zoom_link" placeholder="video link"></div>
                    <div class="mb-3">
                        <label class="form-label">Teacher Demo Fee *</label>
                        <input type="number" class="form-control" name="demo_payment_fee" placeholder="e.g., 500.00"
                            step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Demo Modal -->
<div class="modal fade" id="editDemoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Demo Schedule</h5>
            </div>
            <form id="editDemoForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_demo">
                    <input type="hidden" name="demo_assignment_id" id="edit_demo_assignment_id">
                    <div class="mb-3"><label class="form-label">Assign Teacher</label><select class="form-select"
                            name="teacher_id" id="teacher_id_edit" required></select></div>
                    <div class="mb-3"><label class="form-label">Date and Time</label><input type="datetime-local"
                            class="form-control" name="datetime" id="edit_datetime" required></div>
                    <div class="mb-3"><label class="form-label">Duration (minutes)</label><input type="number"
                            class="form-control" name="duration" id="edit_duration" required></div>
                    <div class="mb-3 d-none"><label class="form-label">Meeting Link</label><input type="url"
                            class="form-control" name="zoom_link" id="edit_zoom_link"
                            placeholder="https://zoom.us/j/..."></div>
                    <div class="mb-3">
                        <label class="form-label">Teacher Demo Fee *</label>
                        <input type="number" class="form-control" name="demo_payment_fee" id="edit_demo_payment_fee"
                            placeholder="e.g., 500.00" step="1" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Approve Admission Modal -->
<div class="modal fade" id="approveAdmissionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-check-circle-fill me-2"></i>Confirm Admission</h5>
            </div>
            <form id="approveAdmissionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve_for_admission">
                    <input type="hidden" name="enquiry_id" id="approve_enquiry_id">

                    <p class="mb-3">You are about to move this student to <strong>Admission Confirmed</strong> status.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Plan / Package *</label>
                        <input type="text" class="form-control" name="selected_plan"
                            placeholder="e.g. 3 Sessions per week, Monthly Package" required>
                        <div class="form-text">Please specify the session frequency or package agreed upon.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Update Outcome Modal -->
<div class="modal fade" id="outcomeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="outcomeModalLabel">Update Outcome</h5>
            </div>
            <form id="outcomeForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_outcome">
                    <input type="hidden" name="demo_assignment_id" id="outcome_demo_assignment_id">
                    <div class="mb-3"><label class="form-label">Outcome</label><select name="outcome"
                            id="outcome_select" class="form-select" required>
                            <option value="attended">Attended</option>
                            <option value="redemo">Needs Redemo</option>
                            <option value="missed">Missed</option>
                        </select></div>
                    <div class="mb-3"><label class="form-label">Feedback / Remarks</label><textarea name="feedback"
                            id="outcome_feedback" class="form-control" rows="3"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Outcome</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php 
include 'includes/footer.php'; 
?>

<!-- =================================================================== -->
<!-- ================= JAVASCRIPT LOGIC FOR THE PAGE =================== -->
<!-- =================================================================== -->
<script>
$(document).ready(function() {
    // Make the enquiry ID from PHP available to JavaScript
    const enquiry_id = <?php echo $enquiry_id; ?>;
    const enquiryStatus = '<?php echo $enquiry['status']; ?>';
    // Initial load of the dynamic management section when the page is ready
    loadSubjectDetails(enquiry_id);

    function loadSubjectDetails(id) {
        $.ajax({
            url: 'demo_action.php',
            type: 'GET',
            data: {
                action: 'get_enquiry_details',
                enquiry_id: id
            },
            dataType: 'json',
            success: function(response) {
                $('#subjectStatusContainer').empty();
                if (response.status === 'success' && response.subjects.length > 0) {
                    response.subjects.forEach(function(sub) {
                        var subjectHtml =
                            `<div class="mb-3 border rounded p-3 bg-light"><h5>${sub.name}</h5>`;
                        if (sub.history.length === 0) {
                            subjectHtml += `<p class="text-muted">No demo scheduled.</p>`;
                        } else {
                            subjectHtml += `<ul class="list-group list-group-flush mb-2">`;
                            sub.history.forEach(function(demo, index) {
                                let outcomeBadge;
                                switch (demo.outcome) {
                                    case 'attended':
                                        outcomeBadge = 'bg-success';
                                        break;
                                    case 'missed':
                                        outcomeBadge = 'bg-danger';
                                        break;
                                    case 'redemo':
                                        outcomeBadge = 'bg-info text-dark';
                                        break;
                                    default:
                                        outcomeBadge = 'bg-warning text-dark';
                                        break;
                                }
                                subjectHtml += `<li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <strong>Attempt ${index + 1}:</strong> <span class="badge ${outcomeBadge}">${demo.outcome}</span>
                                        <small class="d-block text-muted">Teacher: ${demo.teacher_name} | Feedback: ${demo.feedback || 'N/A'}</small>
                                    </div>`;
                                // === FIX #4: ONLY SHOW ACTION BUTTONS IF NOT FINALIZED ===
                                if (enquiryStatus !== 'admission_finalized') {
                                    subjectHtml += `<div class="d-flex gap-2 mt-2 mt-md-0">
                                        <button class="btn btn-sm btn-info text-white btn-update-outcome" data-demo-id="${demo.id}" title="Update Outcome"><i class="bi bi-check-all me-1"></i> Outcome</button>
                                        <button class="btn btn-sm btn-dark btn-edit-demo" data-demo-id="${demo.id}" data-subject-id="${sub.id}" title="Edit Schedule"><i class="bi bi-pencil-square me-1"></i> Edit</button>
                                        <button class="btn btn-sm btn-danger btn-delete-demo" data-demo-id="${demo.id}" title="Delete Attempt"><i class="bi bi-trash3-fill me-1"></i> Delete</button>
                                    </div>`;
                                }
                                subjectHtml += `</li>`;
                            });
                            subjectHtml += `</ul>`;
                        }
                        // === FIX #4 (cont.): ONLY SHOW 'SCHEDULE' BUTTON IF NOT FINALIZED ===
                        if (enquiryStatus !== 'admission_finalized') {
                            subjectHtml +=
                                `<button class="btn btn-sm btn-outline-primary btn-schedule-subject" data-subject-id="${sub.id}" data-subject-name="${sub.name}"><i class="bi bi-plus-circle me-1"></i>Schedule New Attempt</button>`;
                        }
                        subjectHtml += `</div>`;
                        $('#subjectStatusContainer').append(subjectHtml);
                    });
                } else {
                    $('#subjectStatusContainer').html(
                        '<div class="alert alert-info">No subjects were requested for this enquiry.</div>'
                    );
                }
            },
            error: function() {
                $('#subjectStatusContainer').html(
                    '<div class="alert alert-danger">Could not load demo details. Please try again.</div>'
                );
            }
        });
    }

    // This reusable function fetches teachers for a subject and populates a dropdown
    function updateTeacherOptions(selectElementId, subjectId, selectedTeacherId = null) {
        const select = $(selectElementId);
        select.empty().append(new Option('Loading teachers...', ''));
        $.get('demo_action.php', {
            action: 'get_teachers',
            subject_id: subjectId
        }, function(response) {
            select.empty().append(new Option('Select a Teacher', ''));
            if (response.status === 'success' && response.teachers.length > 0) {
                response.teachers.forEach(function(teacher) {
                    select.append(new Option(teacher.display_name, teacher.id));
                });
                if (selectedTeacherId) select.val(selectedTeacherId);
            } else {
                select.empty().append(new Option('No teachers available for this subject', ''));
            }
        }, 'json');
    }

    // --- EVENT HANDLERS for dynamically created buttons ---
    // We use event delegation on the container for buttons that are added by AJAX.

    $('#subjectStatusContainer').on('click', '.btn-schedule-subject', function() {
        $('#scheduleForm')[0].reset();
        $('#schedule_enquiry_id').val(enquiry_id);
        var subject_id = $(this).data('subject-id');
        $('#schedule_subject_id').val(subject_id);
        $('#schedule_subject_name').val($(this).data('subject-name'));
        updateTeacherOptions('#teacher_id_schedule', subject_id);
        $('#scheduleModal').modal('show');
    });

    $('#subjectStatusContainer').on('click', '.btn-update-outcome', function() {
        var demo_id = $(this).data('demo-id');
        $.get("demo_action.php", {
            action: 'get_single_demo',
            demo_id: demo_id
        }, function(response) {
            if (response.status === 'success') {
                var demo = response.data;
                $('#outcomeForm')[0].reset();
                $('#outcome_demo_assignment_id').val(demo.id);
                $('#outcome_select').val(demo.outcome);
                $('#outcome_feedback').val(demo.feedback);
                $('#outcomeModal').modal('show');
            }
        });
    });

    $('#subjectStatusContainer').on('click', '.btn-edit-demo', function() {
        var demo_id = $(this).data('demo-id');
        var subject_id = $(this).data('subject-id');
        $.get("demo_action.php", {
            action: 'get_single_demo',
            demo_id: demo_id
        }, function(response) {
            if (response.status === 'success') {
                var demo = response.data;
                $('#editDemoForm')[0].reset();
                $('#edit_demo_assignment_id').val(demo.id);
                if (demo.scheduled_at) $('#edit_datetime').val(demo.scheduled_at.replace(' ',
                    'T'));
                $('#edit_duration').val(demo.duration_minutes);
                $('#edit_zoom_link').val(demo.zoom_link);
                $('#edit_demo_payment_fee').val(demo.demo_payment_fee);
                updateTeacherOptions('#teacher_id_edit', subject_id, demo.teacher_id);
                $('#editDemoModal').modal('show');
            }
        }, 'json');
    });

    $('#subjectStatusContainer').on('click', '.btn-delete-demo', function() {
        var demo_id = $(this).data('demo-id');
        Swal.fire({
            title: 'Delete this Demo Attempt?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('demo_action.php', {
                    action: 'delete_demo',
                    demo_assignment_id: demo_id
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Deleted!', response.message, 'success');
                        loadSubjectDetails(enquiry_id); // Refresh the list
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Universal form submission handler for all modals on this page
    $('.modal form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        $.post('demo_action.php', form.serialize(), function(response) {
            if (response.status === 'success') {
                form.closest('.modal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                loadSubjectDetails(enquiry_id); // Refresh the details on the page
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json');
    });

    $('#approveAdmissionBtn').on('click', function() {
        // Set the ID in the hidden field inside the new modal
        $('#approve_enquiry_id').val(enquiry_id);
        // Show the modal
        $('#approveAdmissionModal').modal('show');
    });

    // 2. Handle the specific Admission Form Submission
    $('#approveAdmissionForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);

        $.post('demo_action.php', form.serialize(), function(response) {
            if (response.status === 'success') {
                $('#approveAdmissionModal').modal('hide');

                Swal.fire({
                    icon: 'success',
                    title: 'Approved!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Update the status badge on the page instantly
                $('#overallStatusBadge').removeClass('bg-warning text-dark')
                    .addClass('bg-success').text('Admission Confirmed');

                // Remove the approve button since it's now done
                $('#approveAdmissionBtn').parent().remove();

            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json');
    });
});
</script>