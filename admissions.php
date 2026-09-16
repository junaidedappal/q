<?php
// admissions.php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    header("location: dashboard.php");
    exit;
}
ob_end_flush();
?>

<style>
.page-header-gradient {
    background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
}

.table thead th {
    font-size: 0.7rem;
    text-transform: uppercase;
    vertical-align: middle;
}

.header-group-onetime {
    background-color: #fff3cd !important;
    color: #856404;
    text-align: center;
    border-bottom: 2px solid #fff !important;
}

.header-group-schedule {
    background-color: #d1e7dd !important;
    color: #0f5132;
    text-align: center;
    border-bottom: 2px solid #fff !important;
}

/* Filter Toolbar */
.admin-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

/* Modal Styling */
.modal-filter-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 4px;
    display: block;
}
</style>

<div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-toggles display-5 me-3"></i>
        <div>
            <h2 class="fw-bold mb-0">Admissions Dashboard</h2>
            <p class="mb-0 opacity-75 small">Comprehensive enrollment and onboarding management.</p>
        </div>
    </div>
</div>

<!-- COMPACT FILTER BUTTONS -->
<div class="admin-toolbar px-1">
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal"
        data-bs-target="#adminFilterModal">
        <i class="bi bi-filter-right me-1"></i> Advanced Filters
    </button>
    <button id="btnResetGlobal" class="btn btn-light border btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-counterclockwise"></i> Reset View
    </button>
</div>

<div class="card shadow border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="admissionsTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th rowspan="2">Sl</th>
                        <th rowspan="2">Student Info</th>
                        <th rowspan="2">Mentor</th>
                        <th rowspan="2">Status</th>
                        <th rowspan="2">Fee</th>
                        <th colspan="3" class="header-group-schedule">Active Schedule</th>
                        <th colspan="3" class="header-group-onetime">Onboarding Status</th>
                        <th rowspan="2" class="text-end">Action</th>
                    </tr>
                    <tr>
                        <th class="text-center bg-light">Dates</th>
                        <th class="text-center bg-light">Hours</th>
                        <th class="text-center bg-light">Exam</th>
                        <th class="text-center bg-light">Mat.</th>
                        <th class="text-center bg-light">Grp</th>
                        <th class="text-center bg-light">Teams</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADVANCED FILTER POPUP MODAL -->
<div class="modal fade" id="adminFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Filter Options</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="modal-filter-label">Mentor</label>
                            <select id="f_mentor" class="form-select shadow-sm">
                                <option value="">All Mentors</option>
                                <option value="Not Assigned">Not Assigned</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">Grade/Class</label>
                            <select id="f_class" class="form-select shadow-sm">
                                <option value="">All</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">Active Status</label>
                            <select id="f_status" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="ready">Ready</option>
                                <option value="break">Break</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">Fee Status</label>
                            <select id="f_fee" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="Paid">Paid</option>
                                <option value="Partial">Partial</option>
                                <option value="Pending">Pending</option>
                                <option value="No Schedule">No Schedule</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">Exam Status</label>
                            <select id="f_exam" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="done">Completed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <!-- ADDED PROGRESS FILTER HERE -->
                        <div class="col-6">
                            <label class="modal-filter-label">Hours Progress</label>
                            <select id="f_progress" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="completed">Fully Completed</option>
                                <option value="pending">In Progress</option>
                                <option value="no_sch">No Schedule</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="modal-filter-label">Material</label>
                            <select id="f_mat" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">WA Group</label>
                            <select id="f_grp" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="modal-filter-label">MS Teams</label>
                            <select id="f_team" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none"
                    id="btnResetModal">Clear All</button>
                <button type="button" class="btn btn-primary px-5 rounded-pill shadow"
                    id="btnApplyFilters">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Finalize Admission Modal -->
<div class="modal fade" id="admissionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-check-fill me-2"></i>Finalize Admission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="admissionForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="finalize_admission">
                    <input type="hidden" name="enquiry_id" id="adm_enquiry_id">

                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <small class="text-muted text-uppercase">Student Name</small>
                            <h5 class="mb-0 fw-bold text-primary" id="adm_student_name">Loading...</h5>
                        </div>
                        <div class="text-end">
                            <small class="text-muted text-uppercase">Plan</small>
                            <div class="fw-bold" id="adm_plan">-</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Assign Mentor</label>
                            <select class="form-select" name="mentor_id" id="mentor_select"></select>
                        </div>

                        <!-- NEW STATUS DROPDOWN FOR ADMISSION -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Initial Status</label>
                            <select class="form-select" name="status">
                                <option value="ready" selected>Ready for Admission</option>
                                <option value="active">Active</option>
                                <option value="break">Break</option>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <p class="text-muted small mb-2"><i class="bi bi-calendar-range me-1"></i> <strong>Schedule &
                            Payment Details</strong></p>

                    <div class="row g-3">
                        <!-- DATES (Not Required) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Start Date</label>
                            <input type="date" class="form-control" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>

                        <!-- HOURS (Not Required - Defaults to 0) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-primary">Total Scheduled Hours</label>
                            <input type="number" class="form-control" name="total_hours" placeholder="0.00" step="0.01"
                                min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">Hours Paid For</label>
                            <input type="number" class="form-control" name="paid_hours" placeholder="0.00" step="0.01"
                                min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Confirm Admission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal (Kept same as your request) -->
<div class="modal fade" id="editStudentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Student Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_student_schedule">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <input type="hidden" name="schedule_id" id="edit_schedule_id">

                    <h5 class="text-secondary fw-bold mb-3" id="edit_student_name"></h5>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold">Mentor</label>
                            <select class="form-select" name="mentor_id" id="edit_mentor_select"></select>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>

                        <div class="col-md-6"><label class="form-label small fw-bold">Start Date</label><input
                                type="date" class="form-control" name="start_date" id="edit_start_date"></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">End Date</label><input type="date"
                                class="form-control" name="end_date" id="edit_end_date"></div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-primary">Total Hours</label>
                            <input type="number" class="form-control" name="total_hours" placeholder="0.00" step="0.01"
                                min="0" id="edit_total_hours">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">Paid Hours</label>
                            <input type="number" class="form-control" name="paid_hours" placeholder="0.00" step="0.01"
                                min="0" id="edit_paid_hours">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="ready">Ready for Admission</option>
                                <option value="active">Active</option>
                                <option value="break">Break</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {

    // 1. Initial Dropdown Population
    function loadInitialData() {
        $.get("admission_action.php?action=get_mentors", function(res) {
            if (res.status === 'success' && res.mentors) {
                res.mentors.forEach(m => {
                    $('#f_mentor').append(new Option(m.name, m.name));
                    $('#mentor_select, #edit_mentor_select').append(new Option(m.name, m.id));
                });
            }
        }, 'json');
    }
    loadInitialData();
    // 2. Initialize DataTable
    var table = $('#admissionsTable').DataTable({
        "ajax": "admission_action.php",
        "columns": [{
                "data": null,
                "render": (d, t, r, m) => m.row + 1
            },
            {
                "data": "name",
                "render": (d, t, r) => `<b>${d}</b><br><small class="text-muted">${r.class}</small>`
            },
            {
                "data": "mentor_name"
            },
            {
                "data": "status_badge"
            },
            {
                "data": "fee_display"
            },
            {
                "data": "schedule_dates",
                "className": "text-center"
            },
            {
                "data": "hr_display",
                "className": "text-center"
            },
            {
                "data": "check_exam",
                "className": "text-center"
            },
            {
                "data": "check_mat",
                "className": "text-center"
            },
            {
                "data": "check_grp",
                "className": "text-center"
            },
            {
                "data": "check_team",
                "className": "text-center"
            },
            {
                "data": "actions",
                "className": "text-end"
            }
        ],
        "pageLength": 25,
        "language": {
            "search": "",
            "searchPlaceholder": "Search by Name..."
        },
        "initComplete": function(settings, json) {
            // Fill Class Filter
            var uniqueClasses = [];
            json.data.forEach(row => {
                if (row.class && !uniqueClasses.includes(row.class)) uniqueClasses.push(row
                    .class);
            });
            uniqueClasses.sort().forEach(cls => $('#f_class').append(new Option(cls, cls)));
        }
    });

    function loadMentors(selectId = '#mentor_select', selectedVal = null) {
        $.get("admission_action.php?action=get_mentors", function(res) {
            // 1. Add an empty option for "No Mentor" or "Not Ready"
            let opts = '<option value="">-- No Mentor Assigned --</option>';

            if (res.mentors) {
                res.mentors.forEach(m => {
                    // Loose comparison (==) allows matching string "5" with int 5, or null with ""
                    let sel = (m.id == selectedVal) ? 'selected' : '';
                    opts += `<option value="${m.id}" ${sel}>${m.name}</option>`;
                });
            }
            $(selectId).html(opts);
        }, 'json');
    }

    // ==========================================
    // 1. POPULATE MENTOR FILTER DROPDOWN
    // ==========================================
    $.get("admission_action.php?action=get_mentors", function(res) {
        if (res.mentors) {
            res.mentors.forEach(m => {
                $('#filterMentor').append(new Option(m.name, m
                    .name)); // Using Name for search
            });
        }
    }, 'json');

    // ==========================================
    // 2. APPLY FILTERS
    // ==========================================

    // 3. Custom Search Logic (8 filters)
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = table.row(dataIndex).data();

        var mentor = $('#f_mentor').val();
        var grade = $('#f_class').val();
        var status = $('#f_status').val();
        var fee = $('#f_fee').val();
        var exam = $('#f_exam').val();
        var progress = $('#f_progress').val();
        var mat = $('#f_mat').val();
        var grp = $('#f_grp').val();
        var team = $('#f_team').val();

        if (mentor && row.mentor_name !== mentor) return false;
        if (grade && row.class !== grade) return false;
        if (status && !row.status_badge.toLowerCase().includes(status)) return false;
        if (fee && !row.fee_display.includes(fee)) return false;

        if (exam === 'done' && !row.check_exam.includes('text-success')) return false;
        if (exam === 'pending' && row.check_exam.includes('text-success')) return false;
        // --- NEW: HOURS PROGRESS LOGIC ---
        var comp = parseFloat(row.completed_hours) || 0;
        var total = parseFloat(row.total_hours) || 0;

        if (progress === 'completed') {
            if (total === 0 || comp < total) return false;
        } else if (progress === 'pending') {
            if (total === 0 || comp >= total) return false;
        } else if (progress === 'no_sch') {
            if (total > 0) return false;
        }

        function getVal(htmlString) {
            var match = htmlString.match(/data-val=['"](\d)['"]/);
            return match ? match[1] : null;
        }

        // 5. Material Filter
        if (mat !== "" && getVal(row.check_mat) !== mat) return false;

        // 6. WA Group Filter
        if (grp !== "" && getVal(row.check_grp) !== grp) return false;

        // 7. MS Teams Filter
        if (team !== "" && getVal(row.check_team) !== team) return false;

        return true;
    });

    // Apply & Reset
    $('#btnApplyFilters').click(() => {
        table.draw();
        $('#adminFilterModal').modal('hide');
    });
    $('#btnResetModal, #btnResetGlobal').click(() => {
        $('#filterForm')[0].reset();
        table.search('').draw();
    });

    // Finalize Modal
    $('#admissionsTable tbody').on('click', '.btn-finalize', function() {
        var id = $(this).data('id');
        $('#adm_enquiry_id').val(id);
        $.get("admission_action.php", {
            action: 'get_enquiry_details',
            enquiry_id: id
        }, function(res) {
            if (res.status === 'success') {
                $('#adm_student_name').text(res.data.student_name);
                $('#adm_plan').text(res.data.selected_plan);
                loadMentors('#mentor_select');
                $('#admissionModal').modal('show');
            }
        }, 'json');
    });

    $('#admissionsTable tbody').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        var source = $(this).data('source');

        Swal.fire({
            title: 'PERMANENT WIPE?',
            html: `<div class="alert alert-danger small">
                    <b>Warning:</b> This will delete this student from:<br>
                    - Student Profile & Enquiry<br>
                    - All Schedules & Sessions<br>
                    - Attendance & Payout Logs<br>
                    - Exam Marks & Demos
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Wipe Everything!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('admission_action.php', {
                    action: 'delete_record',
                    id: id,
                    source: source
                }, function(res) {
                    if (res.status === 'success') {
                        table.ajax.reload(null, false);
                        Swal.fire('Deleted!', res.message, 'success');
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    $('#admissionForm').on('submit', function(e) {
        e.preventDefault();
        $.post('admission_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#admissionModal').modal('hide');
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Admitted!',
                    text: res.message,
                    timer: 2000
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message
                });
            }
        }, 'json');
    });



    // === MISSING EDIT JS LOGIC ===
    // === JS UPDATES FOR EDIT ===
    $('#admissionsTable tbody').on('click', '.btn-edit', function() {
        var btn = $(this);
        var id = btn.data('id');
        $.get("admission_action.php", {
            action: 'get_student_edit_details',
            student_id: id
        }, function(res) {
            if (res.status === 'success') {
                var d = res.data;
                $('#edit_student_id').val(d.id);
                $('#edit_schedule_id').val(d.schedule_id);
                $('#edit_student_name').text(d.name);
                $('#edit_status').val(d.status);

                // Schedule
                $('#edit_start_date').val(d.start_date);
                $('#edit_end_date').val(d.end_date);

                // New Paid Logic
                $('#edit_total_hours').val(d.total_hours);
                $('#edit_paid_hours').val(d.paid_hours); // Load Paid Hours

                loadMentors('#edit_mentor_select', d.mentor_id);
                $('#editStudentModal').modal('show');
            }
        }, 'json');
    });

    $('#editStudentForm').on('submit', function(e) {
        e.preventDefault();
        $.post('admission_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#editStudentModal').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire('Updated!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
});
</script>