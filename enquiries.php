<?php
include 'includes/header.php';
require 'includes/db.php';

// Role-based access control
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    header('location: dashboard.php');
    exit;
}

// Fetch available years and months from the 'created_at' column
$available_years = $conn->query('SELECT DISTINCT YEAR(created_at) as year FROM enquiries ORDER BY year DESC');
$available_months = $conn->query('SELECT DISTINCT MONTH(created_at) as month, MONTHNAME(created_at) as month_name FROM enquiries ORDER BY month ASC');
$syllabuses_result = $conn->query('SELECT id, name FROM syllabus ORDER BY name ASC');
$subjects_result = $conn->query('SELECT id, name FROM subjects ORDER BY name ASC');

$grades_db_result = $conn->query('SELECT * FROM grades ORDER BY sort_order ASC');
$grouped_grades = [];
while ($row = $grades_db_result->fetch_assoc()) {
    $grouped_grades[$row['category']][] = $row;
}
?>

<style>
/* Enhanced Modern Styling */
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    background-attachment: fixed;
}

/* Hero Header Section */
.page-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    border-radius: 20px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
}

.page-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-20px);
    }
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-icon {
    font-size: 3rem;
    color: #ffd700;
    filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
}

.hero-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.hero-subtitle {
    font-size: 1.1rem;
    opacity: 0.95;
}

/* Main Card */
.main-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    box-shadow: 0 15px 45px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header-custom {
    background: rgba(102, 126, 234, 0.05);
    border-bottom: 2px solid rgba(102, 126, 234, 0.1);
    padding: 1.5rem;
}

.section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: #667eea;
}

/* Filter Section */
.filter-group {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    border: 1px solid rgba(102, 126, 234, 0.2);
    transition: all 0.3s ease;
}

.filter-group:hover {
    background: rgba(255, 255, 255, 1);
    border-color: rgba(102, 126, 234, 0.4);
}

.filter-label {
    font-weight: 600;
    color: #4a5568;
    font-size: 0.9rem;
    white-space: nowrap;
}

.filter-select {
    border: 2px solid rgba(102, 126, 234, 0.2);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Primary Button */
.btn-primary-custom {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: #fff;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
    color: #fff;
}

/* Table Styling */
.table-container {
    padding: 1.5rem;
}

.stylish-table {
    border: none;
    font-size: 0.95rem;
}

.stylish-table thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
}

.stylish-table thead th {
    border: none;
    font-weight: 700;
    color: #2d3748;
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.stylish-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.stylish-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
    transform: scale(1.01);
}

.stylish-table td {
    padding: 1rem;
    vertical-align: middle;
}

/* Status Badges - Enhanced */
.badge-status-new {
    background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3);
}

.badge-status-pending {
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    color: #000;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(255, 193, 7, 0.3);
}

.badge-status-confirmed {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(25, 135, 84, 0.3);
}

.badge-status-finalized {
    background: linear-gradient(135deg, #20c997 0%, #0dcaf0 100%);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(32, 201, 151, 0.3);
}

.badge.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: nowrap;
    /* ADD THIS LINE */
}

.btn-action {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.btn-action:hover {
    transform: translateY(-2px);
}

.btn-action.btn-purple {
    background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
    color: #fff;
}

.btn-action.btn-purple:hover {
    box-shadow: 0 8px 20px rgba(111, 66, 193, 0.4);
}

.btn-action.btn-info {
    background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%);
    color: #fff;
}

.btn-action.btn-info:hover {
    box-shadow: 0 8px 20px rgba(13, 202, 240, 0.4);
}

.btn-action.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
    color: #fff;
}

.btn-action.btn-danger:hover {
    box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
}

/* Modal Enhancements */
.modal-content {
    border-radius: 20px;
    border: none;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 1.5rem;
}

.modal-header-gradient-alt {
    background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
    border: none;
    padding: 1.5rem;
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.modal-body {
    padding: 2rem;
}

/* Form Enhancements */
.form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control,
.form-select {
    border: 2px solid rgba(102, 126, 234, 0.2);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Subject Pills */
.subject-pill {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: all 0.3s ease;
}

.subject-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.remove-subject-btn {
    background: none;
    border: none;
    color: white;
    margin-left: 0.75rem;
    padding: 0;
    line-height: 1;
    opacity: 0.8;
    font-size: 1.3rem;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.remove-subject-btn:hover {
    opacity: 1;
}

/* Alert Styling */
.alert-info {
    background: rgba(13, 110, 253, 0.1);
    border: 2px solid rgba(13, 110, 253, 0.2);
    border-radius: 12px;
    color: #0d6efd;
}

/* View Modal Info */
.info-label {
    font-weight: 700;
    color: #667eea;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-value {
    font-size: 1.1rem;
    color: #2d3748;
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 991px) {
    .filter-group {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-select {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .page-hero {
        padding: 1.5rem;
    }

    .hero-icon {
        font-size: 2.5rem;
    }

    .hero-title {
        font-size: 1.5rem;
    }

    .hero-subtitle {
        font-size: 0.95rem;
    }

    .section-title {
        font-size: 1.2rem;
    }

    .btn-primary-custom {
        width: 100%;
    }

    .action-buttons {
        flex-wrap: nowrap;
    }
}

@media (max-width: 576px) {
    .stylish-table {
        font-size: 0.85rem;
    }

    .stylish-table thead th,
    .stylish-table td {
        padding: 0.75rem 0.5rem;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        font-size: 0.85rem;
    }
}

/* NEW: Styling for DataTables buttons to match your theme */
.dt-buttons .btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.dt-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.dataTables_wrapper .dt-buttons {
    margin-bottom: 1rem;
}
</style>

<!-- Page Hero -->
<div class="page-hero text-white">
    <div class="hero-content">
        <div class="d-flex flex-column flex-sm-row align-items-center text-center text-sm-start gap-3">
            <i class="bi bi-person-lines-fill hero-icon"></i>
            <div>
                <h2 class="hero-title mb-0">Enquiry Management</h2>
                <p class="hero-subtitle mb-0">Track and manage all student enquiries in one place</p>
            </div>
        </div>
    </div>
</div>


<!-- Main Content Card -->
<div class="main-card">
    <div class="card-header-custom">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-auto">
                <h5 class="section-title"><i class="bi bi-table"></i>All Enquiries</h5>
            </div>
            <div class="col-12 col-lg-auto ms-lg-auto">
                <div class="d-flex flex-column flex-md-row gap-2">
                    <div class="filter-group"><label for="filterYear" class="filter-label"><i
                                class="bi bi-calendar-event me-1"></i>Year:</label><select
                            class="form-select form-select-sm"
                            id="filterYear"><?php $available_years->data_seek(0); echo "<option value=''>All Years</option>"; while ($year_row = $available_years->fetch_assoc()) { echo "<option value='{$year_row['year']}'>{$year_row['year']}</option>"; } ?></select>
                    </div>
                    <div class="filter-group"><label for="filterMonth" class="filter-label"><i
                                class="bi bi-calendar3 me-1"></i>Month:</label><select
                            class="form-select form-select-sm"
                            id="filterMonth"><?php $available_months->data_seek(0); echo "<option value=''>All Months</option>"; while ($month_row = $available_months->fetch_assoc()) { $month_value = str_pad($month_row['month'], 2, '0', STR_PAD_LEFT); echo "<option value='{$month_value}'>{$month_row['month_name']}</option>"; } ?></select>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-auto"><button class="btn btn-primary-custom w-100 w-lg-auto" id="addEnquiryBtn"><i
                        class="bi bi-plus-circle-fill me-2"></i>Add New Enquiry</button></div>
        </div>
    </div>
    <div class="table-container">
        <div class="table-responsive">
            <table id="enquiriesTable" class="table stylish-table  nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Country</th>
                        <th>Phone</th>
                        <th>Guardian</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Enquiry Modal -->
<div class="modal fade" id="enquiryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white" id="enquiryModalLabel">
                    <i class="bi bi-person-plus-fill me-2"></i>Add New Enquiry
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="enquiryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="enquiry_id" id="enquiry_id" value="0">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Student Name *</label>
                            <input type="text" class="form-control" name="student_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class *</label>
                            <select class="form-select" name="class" id="class_select" required>
                                <option value="">Select Class</option>
                                <?php foreach ($grouped_grades as $category => $grades): ?>
                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                    <?php foreach ($grades as $grade): ?>
                                    <option value="<?php echo htmlspecialchars($grade['grade_name']); ?>"
                                        data-pay="<?php echo $grade['payment_per_hour']; ?>"
                                        data-fee="<?php echo $grade['fee_per_hour']; ?>">
                                        <?php echo htmlspecialchars($grade['grade_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Syllabus *</label>
                            <select class="form-select" name="syllabus">
                                <?php
                                if ($syllabuses_result->num_rows > 0) {
                                    $syllabuses_result->data_seek(0);
                                    while ($syllabus_row = $syllabuses_result->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($syllabus_row['name']) . "'>" . htmlspecialchars($syllabus_row['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" placeholder="e.g. India, UAE">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Language *</label>
                            <select class="form-select" name="language">
                                <option value="">Select Language</option>
                                <option value="ENGLISH">ENGLISH</option>
                                <option value="MALAYALAM">MALAYALAM</option>
                                <option value="MIXED">MIXED</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Student Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Parent Phone</label>
                            <input type="text" class="form-control" name="parent_phone">
                        </div>

                        <div class="col-12" id="customer-status-message"></div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Subjects for Enquiry</label>
                        <div class="input-group">
                            <select id="subject-select-input" class="form-select">
                                <option value="">Select a subject to add...</option>
                                <?php 
                                $subjects_result->data_seek(0);
                                while ($row = $subjects_result->fetch_assoc()) {
                                    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . '</option>';
                                } 
                                ?>
                            </select>
                            <button class="btn btn-success" type="button" id="add-subject-btn">
                                <i class="bi bi-plus-lg me-1"></i>Add
                            </button>
                        </div>
                        <div id="selected-subjects-container" class="mt-3 d-flex flex-wrap gap-2"></div>
                        <div id="hidden-subjects-inputs"></div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Previous Performance</label>
                        <textarea class="form-control" name="previous_performance" rows="2"></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary-custom" id="saveBtn">
                        <i class="bi bi-check-circle me-1"></i>Save Enquiry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-gradient-alt">
                <h5 class="modal-title text-white" id="viewModalLabel">
                    <i class="bi bi-eye-fill me-2"></i>Enquiry Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#enquiriesTable').DataTable({
        "ajax": {
            "url": "enquiry_action.php",
            "type": "GET",
            "data": function(d) {
                d.filterYear = $('#filterYear').val();
                d.filterMonth = $('#filterMonth').val();
            }
        },
        "columns": [{
                "data": null,
                "render": function(data, type, row, meta) {
                    // This calculates: Row Index + Page Offset + 1
                    return meta.row + meta.settings._iDisplayStart + 1;
                },
                "orderable": true // Serial numbers shouldn't be used for sorting
            },
            {
                "data": "student_name",
                "render": function(data, type, row) {
                    return data + (row.is_previous_customer ? ' ' + row.customer_type_badge :
                        '');
                }
            },
            {
                "data": "class"
            },
            {
                "data": "country"
            },
            {
                "data": "phone"
            },
            {
                "data": "parent_phone"
            },
            {
                "data": "status_badge"
            },
            {
                "data": "created_at"
            },
            {
                "data": "creator_name",
                "defaultContent": "<i>N/A</i>"
            },
            {
                "data": "actions",
                "orderable": false,
                "searchable": false,
                "className": "text-center"
            }
        ],
        "scrollX": true,
        "order": [
            [0, 'desc']
        ], // Order by Date instead of Serial No
        "language": {
            "processing": '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            "emptyTable": "No enquiries found"
        },
        "processing": true,
        "dom": 'Bfrtip',
        "buttons": [{
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel-fill"></i> Excel',
                className: 'btn btn-success',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="bi bi-file-earmark-pdf-fill"></i> PDF',
                className: 'btn btn-danger',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            }
        ]
    });

    // --- ALL EVENT HANDLERS ARE NOW CORRECTLY PLACED OUTSIDE THE DATATABLE OBJECT ---

    $('#filterYear, #filterMonth').on('change', function() {
        table.ajax.reload();
    });

    // Helper function to add subject pills
    function addSubject(subjectId, subjectText) {
        if ($('#hidden-subjects-inputs input[value="' + subjectId + '"]').length > 0) return;
        const pillHtml =
            `<span class="subject-pill" data-id="${subjectId}">${subjectText} <button type="button" class="remove-subject-btn">&times;</button></span>`;
        $('#selected-subjects-container').append(pillHtml);
        const inputHtml = `<input type="hidden" name="subjects[]" value="${subjectId}">`;
        $('#hidden-subjects-inputs').append(inputHtml);
    }

    // "Add Subject" Button Handler
    $('#add-subject-btn').on('click', function() {
        const subjectId = $('#subject-select-input').val();
        if (subjectId) {
            const subjectText = $('#subject-select-input option:selected').text();
            addSubject(subjectId, subjectText);
            $('#subject-select-input').val('');
        }
    });

    // "Remove Subject" Button Handler
    $('#selected-subjects-container').on('click', '.remove-subject-btn', function() {
        const subjectPill = $(this).closest('.subject-pill');
        const subjectId = subjectPill.data('id');
        subjectPill.remove();
        $('#hidden-subjects-inputs input[value="' + subjectId + '"]').remove();
    });

    // "Check Phone" Handler
    function checkCustomerStatus() {
        const studentPhone = $('input[name="phone"]').val();
        const parentPhone = $('input[name="parent_phone"]').val();
        const enquiryId = $('#enquiry_id').val(); // Get the current enquiry ID

        if (!studentPhone && !parentPhone) {
            $('#customer-status-message').empty();
            return;
        }

        $.post('enquiry_action.php', {
            action: 'check_phone',
            phone: studentPhone,
            parent_phone: parentPhone,
            enquiry_id: enquiryId
        }, function(response) {
            if (response.exists) {
                $('#customer-status-message').html(
                    '<div class="alert alert-info py-2"><strong>Note:</strong> This phone number is linked to a previous enquiry.</div>'
                );
            } else {
                $('#customer-status-message').empty();
            }
        }, 'json');
    }
    $('input[name="phone"], input[name="parent_phone"]').on('blur', checkCustomerStatus);


    // --- MODAL AND FORM HANDLERS ---

    // "Add New Enquiry" Button Handler
    $('#addEnquiryBtn').on('click', function() {
        $('#enquiryForm')[0].reset();
        $('#enquiryModalLabel').html('<i class="bi bi-person-plus-fill me-2"></i>Add New Enquiry');
        $('#action').val('add');
        $('#enquiry_id').val('0');
        $('#selected-subjects-container, #hidden-subjects-inputs, #customer-status-message').empty();
        $('#enquiryModal').modal('show');
    });

    // "View" Button Handler
    $('#enquiriesTable tbody').on('click', '.btn-view', function() {
        var id = $(this).data('id'); // Correct way
        $.get('enquiry_action.php', {
            id: id
        }, function(response) {
            var d = response.details;
            var subjects = response.subjects_text.join(', ') || 'None';
            var body = `
                <div class="p-2">
                    <h4 class="mb-4" style="color: #667eea; font-weight: 700;"><i class="bi bi-person-circle me-2"></i>${d.student_name}</h4>
                    <div class="row g-4">
                        <div class="col-md-6"><div class="info-label">Status</div><div class="info-value">${d.status_badge}</div></div>
                        <div class="col-md-6"><div class="info-label">Class</div><div class="info-value">${d.class || 'N/A'}</div></div>
                        <div class="col-md-6"><div class="info-label">Syllabus</div><div class="info-value">${d.syllabus || 'N/A'}</div></div>
                        <div class="col-md-6"><div class="info-label">Language</div><div class="info-value">${d.language || 'N/A'}</div></div>
                        <div class="col-md-6"><div class="info-label">Country</div><div class="info-value">${d.country || 'N/A'}</div></div>
                        <div class="col-md-6"><div class="info-label">Student Phone</div><div class="info-value">${d.phone || 'N/A'}</div></div>
                        <div class="col-md-6"><div class="info-label">Parent Phone</div><div class="info-value">${d.parent_phone || 'N/A'}</div></div>
                        <div class="col-12"><div class="info-label">Requested Subjects</div><div class="info-value">${subjects}</div></div>
                        <div class="col-12"><div class="info-label">Previous Performance</div><p class="text-muted fst-italic">${d.previous_performance || 'None provided'}</p></div>
                        <div class="col-12"><div class="info-label">Remarks</div><p class="text-muted fst-italic">${d.remarks || 'No remarks'}</p></div>
                    </div>
                </div>`;
            $('#viewModalBody').html(body);
            $('#viewModal').modal('show');
        });
    });

    // "Edit" Button Handler
    $('#enquiriesTable tbody').on('click', '.btn-edit', function() {
        var id = $(this).data('id'); // Correct way
        $.get('enquiry_action.php', {
            id: id
        }, function(response) {
            $('#selected-subjects-container, #hidden-subjects-inputs, #customer-status-message')
                .empty();
            $('#enquiryForm')[0].reset();
            $('#enquiryModalLabel').html(
                '<i class="bi bi-pencil-square me-2"></i>Edit Enquiry');
            $('#action').val('edit');
            var d = response.details;
            $('#enquiry_id').val(d.id);
            $('input[name="student_name"]').val(d.student_name);
            $('select[name="class"]').val(d.class);
            $('select[name="syllabus"]').val(d.syllabus);
            $('select[name="language"]').val(d.language);
            $('input[name="country"]').val(d.country);
            $('input[name="phone"]').val(d.phone);
            $('input[name="parent_phone"]').val(d.parent_phone);
            $('textarea[name="previous_performance"]').val(d.previous_performance);
            $('textarea[name="remarks"]').val(d.remarks);
            if (response.subject_ids && response.subjects_text) {
                response.subject_ids.forEach((id, index) => addSubject(id, response
                    .subjects_text[index]));
            }
            $('#enquiryModal').modal('show');
        });
    });

    // "Delete" Button Handler
    $('#enquiriesTable tbody').on('click', '.btn-delete', function() {
        var id = $(this).data('id'); // Correct way
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('enquiry_action.php', {
                    action: 'delete',
                    id: id
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Deleted!', response.message, 'success');
                        table.ajax.reload();
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Form Submission Handler
    let isSubmittingEnquiry = false;
    const saveButton = $('#saveBtn');
    const saveButtonHtml = saveButton.html();

    $('#enquiryForm').on('submit', function(e) {
        e.preventDefault();
        if (isSubmittingEnquiry) return;

        isSubmittingEnquiry = true;
        saveButton.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...'
        );

        $.post('enquiry_action.php', $(this).serialize(), null, 'json')
            .done(function(response) {
                if (response.status === 'success') {
                    $('#enquiryModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    table.ajax.reload();
                } else {
                    Swal.fire('Error!', response.message, 'error');
                }
            })
            .fail(function() {
                Swal.fire('Error!', 'Unable to save the enquiry. Please try again.', 'error');
            })
            .always(function() {
                isSubmittingEnquiry = false;
                saveButton.prop('disabled', false).html(saveButtonHtml);
            });
    });
});
</script>