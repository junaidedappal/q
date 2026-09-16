<?php
include 'includes/header.php';
require 'includes/db.php';
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header("location: dashboard.php");
    exit;
}
?>

<style>
/* Modern Color Palette & Variables */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --info-gradient: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    --hover-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
}

/* Page Header with Enhanced Gradient */
.page-header-gradient {
    background: var(--primary-gradient);
    border-radius: 1.25rem;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.page-header-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.page-header-gradient h2 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
}

.page-header-gradient p {
    font-size: 1rem;
    opacity: 0.95;
    position: relative;
    z-index: 1;
}

/* Enhanced Stat Cards */
.stat-card {
    background: #ffffff;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: var(--card-shadow);
    text-align: center;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
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
    letter-spacing: 0.5px;
}

/* Filter Section */
.filter-section {
    background: #ffffff;
    padding: 2rem;
    border-radius: 1.25rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.filter-section .form-label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.filter-section .form-control,
.filter-section .form-select {
    border-radius: 0.75rem;
    border: 2px solid #e2e8f0;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.filter-section .form-control:focus,
.filter-section .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Main Card */
.main-card {
    background: #ffffff;
    border-radius: 1.25rem;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

/* Enhanced Table Container */
.table-container {
    padding: 2rem;
    overflow-x: auto;
}

/* Stylish Table */
.stylish-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    font-size: 0.95rem;
}

.stylish-table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    padding: 1.25rem 1rem;
    border: none;
    white-space: nowrap;
}

.stylish-table thead th:first-child {
    border-top-left-radius: 0.75rem;
}

.stylish-table thead th:last-child {
    border-top-right-radius: 0.75rem;
}

.stylish-table tbody tr {
    transition: all 0.2s ease;
    border-bottom: 1px solid #e2e8f0;
}

.stylish-table tbody tr:hover {
    background-color: #f7fafc;
    transform: scale(1.01);
}

.stylish-table tbody td {
    padding: 1.25rem 1rem;
    color: #4a5568;
    vertical-align: middle;
}

/* Enhanced Buttons */
.btn-primary {
    background: var(--primary-gradient);
    border: none;
    border-radius: 0.75rem;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-info {
    background: var(--info-gradient);
    border: none;
    border-radius: 0.5rem;
    padding: 0.5rem 1.25rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(54, 209, 220, 0.3);
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(54, 209, 220, 0.4);
}

.btn-success {
    background: var(--success-gradient);
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(17, 153, 142, 0.3);
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(17, 153, 142, 0.4);
}

.btn-warning {
    background: var(--warning-gradient);
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    color: white;
    box-shadow: 0 4px 10px rgba(240, 147, 251, 0.3);
    transition: all 0.3s ease;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(240, 147, 251, 0.4);
}

/* Enhanced Modal */
.modal-content {
    border-radius: 1.25rem;
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 1.25rem 1.25rem 0 0;
    padding: 1.5rem 2rem;
    border: none;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
}

.modal-header .btn-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 2rem;
}

/* Enhanced Badges */
.badge {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.75rem;
    letter-spacing: 0.3px;
}

/* DataTables Buttons Enhancement */
.dt-buttons {
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.dt-button {
    background: white !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 0.75rem !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 600 !important;
    color: #4a5568 !important;
    transition: all 0.3s ease !important;
}

.dt-button:hover {
    background: var(--primary-gradient) !important;
    color: white !important;
    border-color: transparent !important;
    transform: translateY(-2px);
}

/* Responsive Enhancements */
@media (max-width: 768px) {
    .page-header-gradient {
        padding: 1.5rem;
    }

    .page-header-gradient h2 {
        font-size: 1.5rem;
    }

    .stat-card {
        margin-bottom: 1rem;
    }

    .filter-section {
        padding: 1.5rem;
    }

    .filter-section .col-md-3 {
        margin-bottom: 1rem;
    }

    .table-container {
        padding: 1rem;
    }

    .stylish-table {
        font-size: 0.85rem;
    }

    .stylish-table thead th,
    .stylish-table tbody td {
        padding: 0.75rem 0.5rem;
    }

    .modal-dialog {
        margin: 0.5rem;
    }
}

@media (max-width: 576px) {
    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .stat-card .stat-value {
        font-size: 1.5rem;
    }

    .btn-primary,
    .btn-info {
        padding: 0.65rem 1rem;
        font-size: 0.9rem;
    }
}

/* Loading Animation */
.table-container::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    animation: loadingBar 1.5s ease-in-out infinite;
}

@keyframes loadingBar {

    0%,
    100% {
        transform: scaleX(0);
    }

    50% {
        transform: scaleX(1);
    }
}

/* Empty State Styling */
.dataTables_empty {
    padding: 3rem !important;
    color: #718096;
    font-size: 1.1rem;
}
</style>

<!-- Page Header -->
<div class="page-header-gradient text-white">
    <h2 class="fw-bold mb-2">
        <i class="bi bi-wallet2 me-3"></i>Teacher Demo Payouts
    </h2>
    <p class="mb-0">Comprehensive summary of teacher payments for completed demo sessions</p>
</div>

<!-- Summary Stat Cards (Visible to Admin only) -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success-gradient);">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div class="stat-value" id="totalAttended">₹0.00</div>
            <div class="stat-label">Total for 'Attended'</div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--info-gradient);">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <div class="stat-value" id="totalRedemo">₹0.00</div>
            <div class="stat-label">Total for 'Redemo'</div>
        </div>
    </div>
    <div class="col-lg-4 col-md-12">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary-gradient);">
                <i class="bi bi-wallet-fill"></i>
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
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date From:</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date To:</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Quick Select:</label>
                <select class="form-select" id="quickSelect">
                    <option value="custom">Custom Range</option>
                    <option value="this_month">This Month</option>
                    <option value="previous_month">Previous Month</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <button class="btn btn-primary w-100" id="applyFiltersBtn">
                    <i class="bi bi-filter me-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table id="payoutsSummaryTable" class="table stylish-table" style="width:100%">
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>'Attended' Fee</th>
                    <th>'Redemo' Fee</th>
                    <th>Total Unpaid</th>
                    <th>Total Paid</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">
                    <i class="bi bi-file-text me-2"></i>Demo Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="detailsTable" class="table table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Student</th>
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
        let year = date.getFullYear();
        let month = String(date.getMonth() + 1).padStart(2, '0');
        let day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

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
            },
            "dataSrc": function(json) {
                if (json.summary) {
                    $('#totalAttended').text('₹' + json.summary.total_attended);
                    $('#totalRedemo').text('₹' + json.summary.total_redemo);
                    $('#totalPaid').text('₹' + json.summary.total_paid);
                }
                return json.data;
            }
        },
        "columns": [{
                "data": "teacher_name"
            },
            {
                "data": "total_attended_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": "total_redemo_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    const totalUnpaid = (parseFloat(row.total_attended_fee) + parseFloat(row
                        .total_redemo_fee)) - parseFloat(row.total_paid_fee);
                    return '₹' + totalUnpaid.toFixed(2);
                }
            },
            {
                "data": "total_paid_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": "teacher_id",
                "render": function(data, type, row) {
                    return `<button class="btn btn-sm btn-info btn-view-details" data-teacher-id="${data}" data-teacher-name="${row.teacher_name}">
                        <i class="bi bi-eye me-1"></i>View Details
                    </button>`;
                }
            }
        ],
        "dom": 'Bfrtip',
        "buttons": [{
                extend: 'excel',
                title: function() {
                    var dateFrom = $('#dateFrom').val();
                    var dateTo = $('#dateTo').val();
                    return 'Teacher_Demo_Payouts_' + dateFrom + '_to_' + dateTo;
                },
                messageTop: function() {
                    var dateFrom = $('#dateFrom').val();
                    var dateTo = $('#dateTo').val();
                    return 'Teacher Demo Payouts Report\nPeriod: ' + dateFrom + ' to ' + dateTo;
                },
                exportOptions: {
                    columns: [0, 1, 2, 3, 4] // Exclude last column (Action)
                }
            },
            {
                extend: 'pdf',
                title: function() {
                    var dateFrom = $('#dateFrom').val();
                    var dateTo = $('#dateTo').val();
                    return 'Teacher Demo Payouts - ' + dateFrom + ' to ' + dateTo;
                },
                messageTop: function() {
                    var dateFrom = $('#dateFrom').val();
                    var dateTo = $('#dateTo').val();
                    return 'Teacher Demo Payouts Report | Period: ' + dateFrom + ' to ' +
                    dateTo;
                },
                exportOptions: {
                    columns: [0, 1, 2, 3, 4] // Exclude last column (Action)
                }
            },
            {
                extend: 'csv',
                title: function() {
                    var dateFrom = $('#dateFrom').val();
                    var dateTo = $('#dateTo').val();
                    return 'Teacher_Demo_Payouts_' + dateFrom + '_to_' + dateTo;
                },
                exportOptions: {
                    columns: [0, 1, 2, 3, 4] // Exclude last column (Action)
                }
            }
        ],
        "responsive": true,
        "language": {
            "emptyTable": "No payout data found for the selected date range."
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

    // Event handler for view details button
    $('#payoutsSummaryTable').on('click', '.btn-view-details', function() {
        var teacherId = $(this).data('teacher-id');
        var teacherName = $(this).data('teacher-name');
        $('#detailsModalLabel').html('<i class="bi bi-file-text me-2"></i>Demo Details for: ' +
            teacherName);

        $.get('demo_payout_actions.php', {
            action: 'get_teacher_details',
            teacher_id: teacherId,
            dateFrom: $('#dateFrom').val(),
            dateTo: $('#dateTo').val()
        }, function(response) {
            const tableBody = $('#detailsTable tbody');
            tableBody.empty();
            response.data.forEach(demo => {
                let statusBadge = demo.payment_status === 'Paid' ? 'bg-success' :
                    'bg-warning';
                let actionBtn = '';
                const userRole = '<?php echo $_SESSION['role']; ?>';
                const canTakeAction = (userRole === 'admin' || userRole ===
                    'demoAdmin' || userRole === 'demoCordinator');

                if (canTakeAction) {
                    if (demo.payment_status === 'Pending') {
                        actionBtn = `<button class="btn btn-sm btn-success btn-mark-paid" data-id="${demo.id}">
                            <i class="bi bi-check-circle me-1"></i>Mark Paid
                        </button>`;
                    } else {
                        actionBtn = `<button class="btn btn-sm btn-warning btn-retract" data-id="${demo.id}">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Retract
                        </button>`;
                    }
                } else {
                    actionBtn =
                    `<span class="text-muted">${demo.payment_status}</span>`;
                }

                tableBody.append(`
                    <tr>
                        <td>${demo.student_name}</td>
                        <td>${demo.subject_name}</td>
                        <td>${new Date(demo.scheduled_at).toLocaleDateString('en-GB')}</td>
                        <td><span class="badge ${demo.outcome === 'attended' ? 'bg-info' : 'bg-secondary'}">${demo.outcome}</span></td>
                        <td>₹${parseFloat(demo.demo_payment_fee).toFixed(2)}</td>
                        <td><span class="badge ${statusBadge}">${demo.payment_status}</span></td>
                        <td>${actionBtn}</td>
                    </tr>
                `);
            });
            $('#detailsModal').modal('show');
        });
    });

    // Event handler for mark as paid inside modal
    $('#detailsTable').on('click', '.btn-mark-paid', function() {
        var assignmentId = $(this).data('id');
        Swal.fire({
            title: 'Mark as Paid?',
            text: "This action cannot be undone easily.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#11998e',
            confirmButtonText: 'Yes, mark as paid!'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('demo_payout_actions.php', {
                    action: 'mark_as_paid',
                    assignment_id: assignmentId
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Success!', response.message, 'success');
                        $('#detailsModal').modal('hide');
                        summaryTable.ajax.reload();
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    // Event handler for the "Retract" button
    $('#detailsTable').on('click', '.btn-retract', function() {
        var assignmentId = $(this).data('id');
        Swal.fire({
            title: 'Retract Payment?',
            text: "This will set the payment status back to 'Pending'.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f5576c',
            confirmButtonText: 'Yes, retract it!'
        }).then(result => {
            if (result.isConfirmed) {
                $.post('demo_payout_actions.php', {
                    action: 'retract_payment',
                    assignment_id: assignmentId
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Retracted!', response.message, 'success');
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