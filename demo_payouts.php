<?php
include 'includes/header.php';
require 'includes/db.php';
// Authorization Check
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header("location: dashboard.php");
    exit;
}
// === NEW: Fetch coordinators for the filter dropdown ===
$coordinators_query = "SELECT id, name FROM users WHERE role IN ('demoCordinator', 'demoAdmin') AND status = 'active' ORDER BY name ASC";
$coordinators_result = $conn->query($coordinators_query);
?>

<style>
/* Modern Color Palette & Variables */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

/* ... All other beautiful CSS is the same and omitted for brevity ... */
.page-header-gradient {
    background: var(--primary-gradient);
    border-radius: 1.25rem;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.page-header-gradient h2 {
    font-size: 1.75rem;
    font-weight: 700;
}

.page-header-gradient p {
    font-size: 1rem;
    opacity: 0.95;
}

.stat-card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    text-align: center;
    height: 100%;
}

.stat-card .stat-icon {
    font-size: 2rem;
    color: white;
    width: 70px;
    height: 70px;
    border-radius: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.stat-card .stat-label {
    font-size: 0.875rem;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
}

.main-card {
    background: #ffffff;
    border-radius: 1.25rem;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.filter-section {
    padding: 2rem;
}

.table-container {
    padding: 2rem;
    overflow-x: auto;
}

.stylish-table {
    width: 100%;
}

.stylish-table thead th {
    background: var(--primary-gradient);
    color: white;
    padding: 1.25rem 1rem;
    border: none;
}

.stylish-table tbody td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
}

.dataTables_empty {
    padding: 3rem !important;
}

.dt-buttons {
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.75rem;
}

.dt-button {
    background: white !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 0.75rem !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 600 !important;
    color: #4a5568 !important;
}

.dt-button:hover {
    background: var(--primary-gradient) !important;
    color: white !important;
    border-color: transparent !important;
}
</style>

<!-- Page Header (Updated Text) -->
<div class="page-header-gradient text-white">
    <h2 class="fw-bold mb-2">
        <i class="bi bi-wallet2 me-3"></i>Teacher Demo Payouts
    </h2>
    <p class="mb-0">Manage payments for demos linked to students with finalized admissions.</p>
</div>

<!-- Summary Stat Cards (Simplified) -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="row g-4 mb-4">
    <div class="col-lg-6 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning-gradient);">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="stat-value" id="totalPayableUnpaid">₹0.00</div>
            <div class="stat-label">Total Payable (Unpaid)</div>
        </div>
    </div>
    <div class="col-lg-6 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success-gradient);">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-value" id="totalPaid">₹0.00</div>
            <div class="stat-label">Total Paid in Period</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Card with Filters and Table -->
<div class="main-card">
    <div class="filter-section">
        <!-- === UPDATED 4x4 GRID FOR FILTERS === -->
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date From:</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date To:</label>
                <input type="date" class="form-control" id="dateTo">
            </div>

            <?php // Show coordinator filter ONLY to admin/demoAdmin ?>
            <?php if (in_array($_SESSION['role'], ['admin', 'demoAdmin'])): ?>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Filter by Coordinator:</label>
                <select class="form-select" id="coordinatorFilter">
                    <option value="">All Coordinators</option>
                    <?php
                    if ($coordinators_result->num_rows > 0) {
                        while ($row = $coordinators_result->fetch_assoc()) {
                            echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Quick Select:</label>
                <select class="form-select" id="quickSelect">
                    <option value="custom">Custom Range</option>
                    <option value="this_month" selected>This Month</option>
                    <option value="previous_month">Previous Month</option>
                </select>
            </div>
            <div class="col-12 mt-3">
                <button class="btn btn-primary w-100" id="applyFiltersBtn">
                    <i class="bi bi-filter me-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <!-- Table Headers (Simplified) -->
        <table id="payoutsSummaryTable" class="table stylish-table" style="width:100%">
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>Phone</th>
                    <th>Coordinator</th>
                    <th>Total Payable Fee</th>
                    <th>Total Paid</th>
                    <th>Total Unpaid</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Demo Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="detailsTable" class="table table-bordered" style="width:100%">
                        <!-- Modal table headers are the same -->
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Outcome</th>
                                <th>Fee</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    function formatDate(date) {
        let y = date.getFullYear();
        let m = String(date.getMonth() + 1).padStart(2, '0');
        let d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // Set initial date range to "This Month"
    var startOfMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    var endOfMonth = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0);
    $('#dateFrom').val(formatDate(startOfMonth));
    $('#dateTo').val(formatDate(endOfMonth));

    var summaryTable = $('#payoutsSummaryTable').DataTable({
        "ajax": {
            "url": "demo_payout_actions.php",
            "type": "GET",
            "data": function(d) {
                d.action = 'get_summary';
                d.dateFrom = $('#dateFrom').val();
                d.dateTo = $('#dateTo').val();
                d.coordinator_id = $('#coordinatorFilter').val();
            },
            // === CORRECTLY UPDATE THE SIMPLIFIED STAT CARDS ===
            "dataSrc": function(json) {
                if (json.summary) {
                    let totalPayable = parseFloat(json.summary.total_payable.replace(/,/g, ''));
                    let totalPaid = parseFloat(json.summary.total_paid.replace(/,/g, ''));
                    let unpaid = totalPayable - totalPaid;

                    $('#totalPayableUnpaid').text('₹' + unpaid.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                    $('#totalPaid').text('₹' + totalPaid.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                }
                return json.data;
            }
        },
        // === DataTable Columns (Simplified) ===
        "columns": [{
                "data": "teacher_name"
            },
            {
                "data": "teacher_phone",
                "render": function(data) {
                    return data ? data : '<span class="text-muted small">No Phone</span>';
                }
            }, // ADDED THIS OBJECT
            {
                "data": "coordinator_names",
                "render": function(data, type, row) {
                    // If a coordinator name is not available, show 'N/A'
                    return data ? data : '<span class="text-muted">N/A</span>';
                }
            },
            {
                "data": "total_payable_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": "total_paid_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    const unpaid = parseFloat(row.total_payable_fee) - parseFloat(row
                        .total_paid_fee);
                    return '₹' + unpaid.toFixed(2);
                }
            },
            {
                "data": "teacher_id",
                "orderable": false,
                "searchable": false,
                "render": function(data, type, row) {
                    // === FIX #1: DYNAMICALLY ADD THE 'PAY ALL' BUTTON ===
                    let buttons = `<button class="btn btn-sm btn-info btn-view-details" data-teacher-id="${data}" data-teacher-name="${row.teacher_name}">
                                     <i class="bi bi-eye me-1"></i>View
                                 </button>`;

                    const unpaidAmount = parseFloat(row.total_payable_fee) - parseFloat(row
                        .total_paid_fee);

                    if (unpaidAmount > 0) {
                        buttons += `<button class="btn btn-sm btn-success btn-pay-all ms-1" 
                                           data-teacher-id="${data}" 
                                           data-teacher-name="${row.teacher_name}"
                                           title="Pay all pending for this period">
                                     <i class="bi bi-wallet-fill me-1"></i>Pay All
                                   </button>`;
                    }

                    return `<div class="d-flex gap-1">${buttons}</div>`;
                }
            }
        ],

        "dom": 'Bfrtip',
        // === Export Buttons (Updated Columns) ===
        "buttons": [{
                extend: 'excel',
                exportOptions: {
                    columns: [0, 1, 2, 3]
                }
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: [0, 1, 2, 3]
                }
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: [0, 1, 2, 3]
                }
            }
        ],
        "language": {
            "emptyTable": "No payable demos found for finalized admissions in this period."
        }
    });

    $('#applyFiltersBtn').on('click', function() {
        summaryTable.ajax.reload();
    });

    $('#quickSelect').on('change', function() {
        const val = $(this).val();
        let start, end;
        if (val === 'this_month') {
            start = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            end = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0);
        } else if (val === 'previous_month') {
            start = new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1);
            end = new Date(new Date().getFullYear(), new Date().getMonth(), 0);
        }
        if (start && end) {
            $('#dateFrom').val(formatDate(start));
            $('#dateTo').val(formatDate(end));
        }
    });

    $('#payoutsSummaryTable').on('click', '.btn-view-details', function() {
        var teacherId = $(this).data('teacher-id');
        var teacherName = $(this).data('teacher-name');
        $('#detailsModalLabel').html('<i class="bi bi-file-text me-2"></i>Payable Demo Details for: ' +
            teacherName);

        $.get('demo_payout_actions.php', {
            action: 'get_teacher_details',
            teacher_id: teacherId,
            dateFrom: $('#dateFrom').val(),
            dateTo: $('#dateTo').val()
        }, function(response) {
            const tableBody = $('#detailsTable tbody');
            tableBody.empty();

            // === FIX #3: UPDATED COLSPAN TO 8 ===
            if (response.data.length === 0) {
                tableBody.append(
                    '<tr><td colspan="8" class="text-center p-4">No payable demos found.</td></tr>'
                );
            } else {
                response.data.forEach(demo => {
                    let statusBadge = demo.payment_status === 'Paid' ? 'bg-success' :
                        'bg-warning';
                    let actionBtn = '';
                    const canTakeAction =
                        <?php echo json_encode(in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])); ?>;
                    if (canTakeAction) {
                        actionBtn = demo.payment_status === 'Pending' ?
                            `<button class="btn btn-sm btn-success btn-mark-paid" data-id="${demo.id}"><i class="bi bi-check-circle me-1"></i>Mark Paid</button>` :
                            `<button class="btn btn-sm btn-warning btn-retract" data-id="${demo.id}"><i class="bi bi-arrow-counterclockwise me-1"></i>Retract</button>`;
                    } else {
                        actionBtn =
                            `<span class="text-muted">${demo.payment_status}</span>`;
                    }

                    // === FIX #2: ADDED THE 'demo.class' CELL ===
                    tableBody.append(`
                        <tr>
                            <td>${demo.student_name}</td>
                            <td>${demo.class}</td>
                            <td>${demo.subject_name}</td>
                            <td>${new Date(demo.scheduled_at).toLocaleDateString('en-GB')}</td>
                            <td><span class="badge ${demo.outcome === 'attended' ? 'bg-info' : 'bg-secondary'}">${demo.outcome}</span></td>
                            <td>₹${parseFloat(demo.demo_payment_fee).toFixed(2)}</td>
                            <td><span class="badge ${statusBadge}">${demo.payment_status}</span></td>
                            <td>${actionBtn}</td>
                        </tr>
                    `);
                });
            }
            $('#detailsModal').modal('show');
        });
    });

    // === FIX #2: ADD NEW EVENT HANDLER FOR THE 'PAY ALL' BUTTON ===
    $('#payoutsSummaryTable').on('click', '.btn-pay-all', function() {
        const teacherId = $(this).data('teacher-id');
        const teacherName = $(this).data('teacher-name');
        const dateFrom = $('#dateFrom').val();
        const dateTo = $('#dateTo').val();

        Swal.fire({
            title: `Pay all for ${teacherName}?`,
            html: `This will mark all <b>pending</b> demos for this teacher between <b>${dateFrom}</b> and <b>${dateTo}</b> as 'Paid'.<br><br>This action cannot be easily undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#11998e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Pay All!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('demo_payout_actions.php', {
                    action: 'pay_all_teacher',
                    teacher_id: teacherId,
                    dateFrom: dateFrom,
                    dateTo: dateTo
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        summaryTable.ajax
                            .reload(); // Refresh the main table to show updated totals
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });
    // Combined event handler for mark paid / retract buttons in the modal
    $('#detailsTable').on('click', '.btn-mark-paid, .btn-retract', function() {
        const isPaidAction = $(this).hasClass('btn-mark-paid');
        const assignmentId = $(this).data('id');
        const swalConfig = {
            title: isPaidAction ? 'Mark as Paid?' : 'Retract Payment?',
            text: isPaidAction ? "Confirm payment for this demo." :
                "This will set the status back to 'Pending'.",
            icon: isPaidAction ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isPaidAction ? '#11998e' : '#f5576c',
            confirmButtonText: isPaidAction ? 'Yes, mark as paid!' : 'Yes, retract it!'
        };

        Swal.fire(swalConfig).then(result => {
            if (result.isConfirmed) {
                $.post('demo_payout_actions.php', {
                    action: isPaidAction ? 'mark_as_paid' : 'retract_payment',
                    assignment_id: assignmentId
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        $('#detailsModal').modal('hide');
                        summaryTable.ajax.reload();
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
</script>