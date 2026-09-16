<?php
include 'includes/header.php';
require 'includes/db.php';
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header("location: dashboard.php");
    exit;
}
?>
<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #0fd850 0%, #00c9ff 100%);
    --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --warning-gradient: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
    --glass-bg: rgba(255, 255, 255, 0.95);
    --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.12);
}

body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    background-attachment: fixed;
    min-height: 100vh;
}

.page-wrapper {
    padding: 2rem 0;
}

/* Modern Header */
.hero-header {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
}

.hero-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border-radius: 50%;
    filter: blur(60px);
}

.hero-header .content {
    position: relative;
    z-index: 1;
}

.hero-header .icon-box {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    margin-right: 1.5rem;
}

.hero-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.hero-header p {
    color: #718096;
    font-size: 1rem;
    margin: 0;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: #718096;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Filter Section */
.filter-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.filter-card .section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.filter-card .section-title i {
    margin-right: 0.5rem;
    color: #667eea;
}

.form-label {
    font-weight: 600;
    color: #4a5568;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control,
.form-select {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus,
.form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    background: white;
}

.btn-filter {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 12px;
    padding: 0.75rem 2rem;
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-reset {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 2rem;
    color: #4a5568;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.btn-reset:hover {
    border-color: #cbd5e0;
    background: #f7fafc;
}

/* Table Card */
.table-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.table-modern {
    margin: 0;
}

.table-modern thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    border: none;
    white-space: nowrap;
}

.table-modern thead th:first-child {
    border-top-left-radius: 12px;
}

.table-modern thead th:last-child {
    border-top-right-radius: 12px;
}

.table-modern tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: #2d3748;
    font-size: 0.9rem;
    border-bottom: 1px solid #e2e8f0;
}

.table-modern tbody tr {
    transition: all 0.2s ease;
}

.table-modern tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
}

/* Status Badges */
.badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.badge-attended {
    background: linear-gradient(135deg, #0fd850 0%, #00c9ff 100%);
    color: white;
}

.badge-redemo {
    background: linear-gradient(135deg, #ffd89b 0%, #ff8c42 100%);
    color: white;
}

.badge-missed {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.badge-pending {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #4a5568;
}

.badge-paid {
    background: linear-gradient(135deg, #0fd850 0%, #00c9ff 100%);
    color: white;
}

.badge-payment-pending {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    color: #744210;
}

/* DataTables Custom Styling */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    color: #4a5568;
    font-weight: 500;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px;
    margin: 0 0.25rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    color: white !important;
}

.dt-buttons {
    margin-bottom: 1rem;
}

.dt-button {
    background: white !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 10px !important;
    padding: 0.5rem 1rem !important;
    color: #4a5568 !important;
    font-weight: 600 !important;
    margin-right: 0.5rem !important;
    transition: all 0.3s ease !important;
}

.dt-button:hover {
    border-color: #667eea !important;
    background: #667eea !important;
    color: white !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .hero-header {
        padding: 1.5rem;
    }

    .hero-header h1 {
        font-size: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .filter-card {
        padding: 1.5rem;
    }
}

/* Loading State */
.table-loading {
    text-align: center;
    padding: 3rem;
    color: #718096;
}

.table-loading i {
    font-size: 3rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>

<div class="container page-wrapper">
    <!-- Hero Header -->
    <div class="hero-header">
        <div class="content d-flex align-items-center">
            <div class="icon-box">
                <i class="bi bi-clipboard2-check-fill text-white" style="font-size: 2rem;"></i>
            </div>
            <div>
                <h1>All Demo Classes</h1>
                <p>Comprehensive overview of all scheduled demo sessions with detailed analytics and filtering</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-calendar-check text-white" style="font-size: 1.5rem;"></i>
            </div>
            <div class="stat-value" id="totalDemos">0</div>
            <div class="stat-label">Total Demos</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #0fd850 0%, #00c9ff 100%);">
                <i class="bi bi-check-circle text-white" style="font-size: 1.5rem;"></i>
            </div>
            <div class="stat-value" id="attendedDemos">0</div>
            <div class="stat-label">Completed</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ffd89b 0%, #ff8c42 100%);">
                <i class="bi bi-arrow-repeat text-white" style="font-size: 1.5rem;"></i>
            </div>
            <div class="stat-value" id="redemoDemos">0</div>
            <div class="stat-label">Redemo</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #0fd850 0%, #00c9ff 100%);">
                <i class="bi bi-cash-coin text-white" style="font-size: 1.5rem;"></i>
            </div>
            <div class="stat-value" id="totalRevenue">₹0</div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="section-title">
            <i class="bi bi-funnel-fill"></i>
            Filter Options
        </div>
        <div class="row g-3">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Outcome</label>
                <select class="form-select" id="outcomeFilter">
                    <option value="">All Outcomes</option>
                    <option value="attended">Completed</option>
                    <option value="redemo">Redemo</option>
                    <option value="missed">Missed</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Payment</label>
                <select class="form-select" id="paymentStatusFilter">
                    <option value="">All Status</option>
                    <option value="Paid">Paid</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-12 d-flex align-items-end gap-2">
                <button class="btn btn-filter flex-fill" id="applyFiltersBtn">
                    <i class="bi bi-search me-2"></i>Filter
                </button>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" style="color: #2d3748; font-weight: 600;">Demo Classes List</h5>
        </div>
        <div class="table-responsive">
            <table id="allDemosTable" class="table table-modern" style="width:100%">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Teacher Name</th>
                        <th>Subject</th>
                        <th>Scheduled Date</th>
                        <th>Outcome</th>
                        <th>Fee</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Set default date range to this month
    const today = new Date();
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0];
    $('#dateFrom').val(startOfMonth);
    $('#dateTo').val(endOfMonth);

    var table = $('#allDemosTable').DataTable({
        "ajax": {
            "url": "all_demos_action.php",
            "type": "GET",
            "data": function(d) {
                d.dateFrom = $('#dateFrom').val();
                d.dateTo = $('#dateTo').val();
                d.outcomeFilter = $('#outcomeFilter').val();
                d.paymentStatusFilter = $('#paymentStatusFilter').val();
            },
            "dataSrc": function(json) {
                // Update stats
                updateStats(json.data);
                return json.data;
            }
        },
        "columns": [{
                "data": "student_name"
            },
            {
                "data": "teacher_name"
            },
            {
                "data": "subject_name"
            },
            {
                "data": "formatted_date"
            },
            {
                "data": "outcome_badge"
            },
            {
                "data": "demo_payment_fee",
                "render": $.fn.dataTable.render.number(',', '.', 2, '₹')
            },
            {
                "data": "payment_badge"
            }
        ],
        "order": [
            [3, 'desc']
        ],
        "dom": 'Bfrtip',
        "buttons": [{
                extend: 'excel',
                text: '<i class="bi bi-file-excel me-2"></i>Excel',
                className: 'dt-button'
            },
            {
                extend: 'pdf',
                text: '<i class="bi bi-file-pdf me-2"></i>PDF',
                className: 'dt-button'
            },
            {
                extend: 'csv',
                text: '<i class="bi bi-filetype-csv me-2"></i>CSV',
                className: 'dt-button'
            }
        ],
        "language": {
            "emptyTable": "No demo classes found for the selected filters.",
            "loadingRecords": '<div class="table-loading"><i class="bi bi-hourglass-split"></i><p class="mt-3">Loading demo classes...</p></div>'
        },
        "pageLength": 25,
        "responsive": true
    });

    // Update stats based on filtered data
    function updateStats(data) {
        let total = data.length;
        let attended = data.filter(d => d.outcome === 'attended').length;
        let redemo = data.filter(d => d.outcome === 'redemo').length;

        let totalRevenue = data.reduce((sum, d) => {
            return sum + (d.payment_status === 'Paid' ? parseFloat(d.demo_payment_fee || 0) : 0);
        }, 0);

        $('#totalDemos').text(total);
        $('#attendedDemos').text(attended);
        $('#redemoDemos').text(redemo);
        $('#totalRevenue').text('₹' + totalRevenue.toLocaleString('en-IN', {
            minimumFractionDigits: 2
        }));
    }

    // Apply filters button click handler
    $('#applyFiltersBtn').on('click', function() {
        table.ajax.reload();
    });

    // Allow pressing Enter in date fields to filter
    $('#dateFrom, #dateTo').on('keypress', function(e) {
        if (e.which === 13) {
            table.ajax.reload();
        }
    });
});
</script>