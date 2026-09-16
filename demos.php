<?php
include 'includes/header.php';
require 'includes/db.php';
// Role-based access control
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header("location: dashboard.php");
    exit;
}
$available_years = $conn->query('SELECT DISTINCT YEAR(created_at) as year FROM enquiries ORDER BY year DESC');
$available_months = $conn->query('SELECT DISTINCT MONTH(created_at) as month, MONTHNAME(created_at) as month_name FROM enquiries ORDER BY month ASC');
$coordinators_result = $conn->query("SELECT id, name FROM users WHERE role IN ('demoCordinator', 'demoAdmin')  AND  status = 'active' ORDER BY name ASC");
?>
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --info-gradient: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
    --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    --card-hover-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

.page-header-modern {
    background: var(--primary-gradient);
    border-radius: 1.5rem;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.glass-card-modern {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
}

.glass-card-modern:hover {
    box-shadow: var(--card-hover-shadow);
}

.filter-section {
    background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
    padding: 1.5rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.form-select-modern,
.form-control-modern {
    border: 2px solid #e9ecef;
    border-radius: 0.75rem;
    padding: 0.6rem 1rem;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.form-select-modern:focus,
.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.btn-modern {
    border-radius: 0.75rem;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-modern:hover::before {
    width: 300px;
    height: 300px;
}

.btn-gradient-primary {
    background: var(--primary-gradient);
    color: white;
}

.btn-gradient-success {
    background: var(--success-gradient);
    color: white;
}

.btn-gradient-info {
    background: var(--info-gradient);
    color: white;
}

.table-modern {
    border-collapse: separate;
    border-spacing: 0;
}

.table-modern thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    border: none;
    padding: 1rem;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-modern thead th:first-child {
    border-top-left-radius: 1rem;
}

.table-modern thead th:last-child {
    border-top-right-radius: 1rem;
}

.table-modern tbody tr {
    transition: all 0.3s ease;
    background: white;
}

.table-modern tbody tr:hover {
    background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.table-modern tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.badge-modern {
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-weight: 600;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}

.badge-status-new {
    background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
    color: white;
}

.badge-status-pending {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.badge-status-confirmed {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

/* === FIX: ADDED NEW STYLE FOR THE FINALIZED BADGE === */
.badge-status-finalized {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    /* Changed to a success green */
    color: white;
}

.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 5px solid rgba(255, 255, 255, 0.3);
    border-top: 5px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.view-all-section {
    text-align: center;
    padding: 2rem;
    background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
    border-radius: 1rem;
    margin-top: 1.5rem;
}

#viewAllBtn {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 1rem 3rem;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 3rem;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

#viewAllBtn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
}

.stats-counter {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 600;
}

.icon-modern {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    margin-right: 1rem;
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Modern Page Header -->
<div class="page-header-modern text-white">
    <div class="d-flex align-items-center">
        <div class="icon-modern">
            <i class="bi bi-calendar-event display-5"></i>
        </div>
        <div>
            <h2 class="fw-bold mb-1">Demo Management Center</h2>
            <p class="mb-0 opacity-90">Track, manage, and organize all demo sessions efficiently</p>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <!-- === UPDATED 4-COLUMN LAYOUT FOR FILTERS === -->
    <div class="row g-3 align-items-center">
        <div class="col-lg-3 col-md-6">
            <label class="form-label mb-1 fw-semibold text-muted small"><i class="bi bi-calendar3 me-1"></i>Filter by
                Year</label>
            <select class="form-select form-select-modern" id="filterYear">
                <option value="">All Years</option>
                <?php
                while ($year_row = $available_years->fetch_assoc()) {
                    echo "<option value='{$year_row['year']}'>{$year_row['year']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-lg-3 col-md-6">
            <label class="form-label mb-1 fw-semibold text-muted small"><i class="bi bi-calendar-month me-1"></i>Filter
                by Month</label>
            <select class="form-select form-select-modern" id="filterMonth">
                <option value="">All Months</option>
                <?php
                while ($month_row = $available_months->fetch_assoc()) {
                    $month_value = str_pad($month_row['month'], 2, '0', STR_PAD_LEFT);
                    echo "<option value='{$month_value}'>{$month_row['month_name']}</option>";
                }
                ?>
            </select>
        </div>

        <?php // === NEW: COORDINATOR FILTER (FOR ADMINS/DEMOADMINS ONLY) === ?>
        <?php if (in_array($_SESSION['role'], ['admin', 'demoAdmin'])): ?>
        <div class="col-lg-3 col-md-6">
            <label class="form-label mb-1 fw-semibold text-muted small"><i
                    class="bi bi-person-check-fill me-1"></i>Filter by Coordinator</label>
            <select class="form-select form-select-modern" id="filterCoordinator">
                <option value="">All Coordinators</option>
                <?php
                while ($coordinator = $coordinators_result->fetch_assoc()) {
                    echo "<option value='{$coordinator['id']}'>" . htmlspecialchars($coordinator['name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-lg-3 col-md-6">
            <label class="form-label mb-1 fw-semibold text-muted small"><i class="bi bi-search me-1"></i>Search</label>
            <input type="text" class="form-control form-control-modern" id="searchBox" placeholder="Search by name...">
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-12 d-flex justify-content-end gap-2">
            <button class="btn btn-modern btn-gradient-info flex-fill" id="applyFiltersBtn">
                <i class="bi bi-filter-circle me-2"></i>Apply Filters
            </button>
            <button class="btn btn-modern btn-outline-secondary" id="resetFiltersBtn" title="Reset all filters">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="glass-card-modern p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="bi bi-table me-2 text-primary"></i>All Demos
        </h5>
        <span class="stats-counter" id="recordCounter">Showing <strong id="currentCount">0</strong> of <strong
                id="totalCount">0</strong> records</span>
    </div>

    <div class="table-responsive">
        <table id="demosTable" class="table table-modern" style="width:100%">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Class</th>
                    <th>Enquiry Date</th>
                    <th>Coordinator</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div class="view-all-section" id="viewAllSection" style="display: none;">
        <p class="mb-3 text-muted">
            <i class="bi bi-info-circle me-2"></i>
            Showing first 200 records for optimal performance
        </p>
        <button class="btn" id="viewAllBtn">
            <i class="bi bi-eye-fill me-2"></i>Load All Records
        </button>
    </div>
</div>

<!-- Back Button -->
<div class="text-center my-4">
    <a href="dashboard.php" class="btn btn-secondary px-4"><i class="bi bi-arrow-left me-2"></i>Back to
        Dashboard</a>
</div>
<!-- All your existing modals remain the same -->
<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Demo Details for: </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="details_enquiry_id">
                <h6>Subject & Demo History:</h6>
                <div id="subjectStatusContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="approveAdmissionBtn">Approve for Admission</button>
            </div>
        </div>
    </div>
</div>

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
                    <div class="mb-3"><label>Subject</label><input type="text" id="schedule_subject_name"
                            class="form-control" readonly></div>
                    <div class="mb-3"><label>Assign Teacher</label><select class="form-control" name="teacher_id"
                            id="teacher_id_schedule" required></select></div>
                    <div class="mb-3"><label>Date and Time</label><input type="datetime-local" class="form-control"
                            name="datetime" required></div>
                    <div class="mb-3"><label>Duration (minutes)</label><input type="number" class="form-control"
                            name="duration" value="60" required></div>
                    <div class="mb-3 d-none"><label>Meeting Link</label><input type="text" class="form-control"
                            name="zoom_link"></div>
                    <div class="mb-3">
                        <label>Teacher Demo Fee</label>
                        <input type="number" class="form-control" name="demo_payment_fee" placeholder="e.g., 500.00"
                            step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-target="#detailsModal"
                        data-bs-toggle="modal">Back</button>
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
                    <div class="mb-3"><label>Assign Teacher</label><select class="form-control" name="teacher_id"
                            id="teacher_id_edit" required></select></div>
                    <div class="mb-3"><label>Date and Time</label><input type="datetime-local" class="form-control"
                            name="datetime" id="edit_datetime" required></div>
                    <div class="mb-3"><label>Duration (minutes)</label><input type="number" class="form-control"
                            name="duration" id="edit_duration" required></div>
                    <div class="mb-3 d-none"><label>Meeting Link</label><input type="text" class="form-control"
                            name="zoom_link" id="edit_zoom_link" required></div>
                    <div class="mb-3">
                        <label>Teacher Demo Fee</label>
                        <input type="number" class="form-control" name="demo_payment_fee" placeholder="e.g., 500.00"
                            step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-target="#detailsModal"
                        data-bs-toggle="modal">Back</button>
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Coordinator Modal -->
<div class="modal fade" id="assignCoordinatorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Demo Coordinator</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignCoordinatorForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_coordinator">
                    <input type="hidden" name="enquiry_id" id="assign_enquiry_id">
                    <div class="mb-3">
                        <label for="coordinator_id_select" class="form-label">Select Coordinator</label>
                        <select class="form-select" name="coordinator_id" id="coordinator_id_select" required>
                            <option value="">Select a Coordinator...</option>
                            <?php
                            if ($coordinators_result->num_rows > 0) {
                                $coordinators_result->data_seek(0);
                                while ($coordinator = $coordinators_result->fetch_assoc()) {
                                    echo "<option value='{$coordinator['id']}'>" . htmlspecialchars($coordinator['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    let table;
    let loadAll = false;
    let totalRecords = 0;

    function showLoading() {
        $('#loadingOverlay').css('display', 'flex');
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    function updateRecordCounter(current, total) {
        $('#currentCount').text(current);
        $('#totalCount').text(total);
        totalRecords = total;

        // Show "View All" button if there are more than 200 records and not loading all
        if (total > 200 && !loadAll) {
            $('#viewAllSection').show();
        } else {
            $('#viewAllSection').hide();
        }
    }

    function initDataTable() {
        if (table) {
            table.destroy();
        }

        table = $('#demosTable').DataTable({
            "ajax": {
                "url": "demo_action.php",
                "type": "GET",
                "data": function(d) {
                    d.filterYear = $('#filterYear').val();
                    d.filterMonth = $('#filterMonth').val();
                    d.filterCoordinator = $('#filterCoordinator').val();
                    d.loadAll = loadAll;
                    d.limit = loadAll ? null : 200;
                },
                "dataSrc": function(json) {
                    updateRecordCounter(json.data.length, json.total || json.data.length);
                    return json.data;
                }
            },
            "columns": [{
                    "data": "student_name"
                },
                {
                    "data": "class"
                },
                {
                    "data": "enquiry_date"
                },
                {
                    "data": "coordinator_display",
                    "orderable": true
                },
                {
                    "data": "status_badge",
                    "orderable": true
                },
                {
                    "data": "actions",
                    "orderable": false
                }
            ],
            "order": [
                [2, 'desc']
            ],
            "pageLength": 50,
            "lengthMenu": [
                [25, 50, 100, 200],
                [25, 50, 100, 200]
            ],
            "processing": true,
            "language": {
                "processing": '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
                "emptyTable": "No demo records found",
                "zeroRecords": "No matching records found"
            },
            "drawCallback": function() {
                hideLoading();
            }
        });
    }

    // Restore filters on page load
    const saved = JSON.parse(localStorage.getItem("demoFilters"));
    if (saved) {
        $('#filterYear').val(saved.year);
        $('#filterMonth').val(saved.month);
        $('#filterCoordinator').val(saved.coordinator);
        $('#searchBox').val(saved.search);
    }

    function saveFilters() {
        localStorage.setItem("demoFilters", JSON.stringify({
            year: $('#filterYear').val(),
            month: $('#filterMonth').val(),
            coordinator: $('#filterCoordinator').val(),
            search: $('#searchBox').val(),
        }));
    }

    $('#filterYear, #filterMonth, #filterCoordinator').on('change', saveFilters);
    $('#searchBox').on('keyup', saveFilters);
    $('#applyFiltersBtn').on('click', saveFilters);

    // Initialize table on page load
    initDataTable();

    // Apply filters button
    $('#applyFiltersBtn').on('click', function() {
        showLoading();
        loadAll = false;
        table.ajax.reload();
    });

    // Reset filters
    $('#resetFiltersBtn').on('click', function() {
        $('#filterYear').val('');
        $('#filterMonth').val('');
        $('#filterCoordinator').val('');
        $('#searchBox').val('');
        showLoading();
        loadAll = false;
        table.ajax.reload();
    });

    // Search functionality
    $('#searchBox').on('keyup', function() {
        table.search(this.value).draw();
    });

    // View All button
    $('#viewAllBtn').on('click', function() {
        showLoading();
        loadAll = true;
        $(this).prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-2"></span>Loading...');

        table.ajax.reload(function() {
            $('#viewAllBtn').prop('disabled', false).html(
                '<i class="bi bi-eye-fill me-2"></i>Load All Records');
        });
    });



    $('#demosTable tbody').on('click', '.btn-assign-coordinator', function() {
        var enquiry_id = $(this).data('id');
        var currentCoordinatorId = $(this).data('coordinator-id');
        $('#assign_enquiry_id').val(enquiry_id);
        $('#coordinator_id_select').val(currentCoordinatorId);
        $('#assignCoordinatorModal').modal('show');
    });

    $('#assignCoordinatorForm').on('submit', function(e) {
        e.preventDefault();
        $.post('demo_action.php', $(this).serialize(), function(response) {
            if (response.status === 'success') {
                $('#assignCoordinatorModal').modal('hide');
                Swal.fire('Success!', response.message, 'success');
                table.ajax.reload();
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json');
    });




    $('#subjectStatusContainer').on('click', '.btn-schedule-subject', function() {
        $('#scheduleForm')[0].reset();
        $('#schedule_enquiry_id').val($('#details_enquiry_id').val());
        var subject_id = $(this).data('subject-id');
        $('#schedule_subject_id').val(subject_id);
        $('#schedule_subject_name').val($(this).data('subject-name'));

        $('#detailsModal').modal('hide');
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
                $('#detailsModal').modal('hide');
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

                $('#detailsModal').modal('hide');
                $('#editDemoModal').modal('show');
            }
        }, 'json');
    });



    $('.modal form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        $.post('demo_action.php', form.serialize(), function(response) {
            if (response.status === 'success') {
                form.closest('.modal').modal('hide');
                Swal.fire('Success!', response.message, 'success');
                table.ajax.reload();
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json');
    });

    $('#approveAdmissionBtn').on('click', function() {
        var enquiry_id = $('#details_enquiry_id').val();
        Swal.fire({
            title: 'Approve for Admission?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, Approve!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('demo_action.php', {
                    action: 'approve_for_admission',
                    enquiry_id: enquiry_id
                }, function(response) {
                    if (response.status === 'success') {
                        $('#detailsModal').modal('hide');
                        Swal.fire('Approved!', response.message, 'success');
                        table.ajax.reload();
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
</script>