<?php
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin'])) {
    header("location: dashboard.php");
    exit;
}
?>

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
}

.page-header-gradient {
    background: var(--primary-gradient);
    color: white;
    padding: 2rem;
    border-radius: 1rem;
    margin-bottom: 2rem;
}

.main-card {
    background: #fff;
    border-radius: 1rem;
    box-shadow: var(--card-shadow);
}

.table-container {
    padding: 2rem;
}
</style>

<div class="page-header-gradient">
    <h2 class="fw-bold">
        <i class="bi bi-people-fill me-3"></i>Coordinator Demo Payout Summary
    </h2>
    <p>Total payout amount handled by each demo coordinator</p>
</div>

<div class="main-card mb-4">
    <div class="p-3">
        <div class="row g-3">
            <div class="col-md-3">
                <label>Date From:</label>
                <input type="date" id="dateFrom" class="form-control">
            </div>

            <div class="col-md-3">
                <label>Date To:</label>
                <input type="date" id="dateTo" class="form-control">
            </div>

            <div class="col-md-3">
                <label>Quick Select:</label>
                <select id="quickSelect" class="form-select">
                    <option value="this_month" selected>This Month</option>
                    <option value="previous_month">Previous Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button class="btn btn-primary w-100" id="applyFiltersBtn">
                    <i class="bi bi-filter-circle me-2"></i>Apply Filters
                </button>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table id="coordinatorTable" class="table table-bordered">
            <thead>
                <tr>
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
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="detailsModalLabel">Coordinator Demo Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table id="detailsTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Outcome</th>
                            <th>Fee</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {

    function formatDate(date) {
        let y = date.getFullYear();
        let m = ("0" + (date.getMonth() + 1)).slice(-2);
        let d = ("0" + date.getDate()).slice(-2);
        return `${y}-${m}-${d}`;
    }

    let start = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    let end = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0);

    $('#dateFrom').val(formatDate(start));
    $('#dateTo').val(formatDate(end));

    var table = $('#coordinatorTable').DataTable({
        "ajax": {
            "url": "coordinator_payout_actions.php",
            "type": "GET",
            "data": function(d) {
                d.action = "get_summary";
                d.dateFrom = $('#dateFrom').val();
                d.dateTo = $('#dateTo').val();
            }
        },
        "columns": [{
                "data": "coordinator_name"
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
                "render": function(r) {
                    return "₹" + (r.total_payable_fee - r.total_paid_fee).toFixed(2);
                }
            },
            {
                "data": "coordinator_id",
                "render": function(id, t, row) {
                    return `
                        <button class="btn btn-info btn-sm btn-view" data-id="${id}">
                            <i class="bi bi-eye"></i> View
                        </button>
                    `;
                }
            }
        ]
    });

    $('#applyFiltersBtn').click(function() {
        table.ajax.reload();
    });

    $('#quickSelect').change(function() {
        let v = $(this).val();
        let s, e;

        if (v === "this_month") {
            s = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
            e = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0);
        } else if (v === "previous_month") {
            s = new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1);
            e = new Date(new Date().getFullYear(), new Date().getMonth(), 0);
        }
        $('#dateFrom').val(formatDate(s));
        $('#dateTo').val(formatDate(e));
    });

    // VIEW DETAILS
    $('#coordinatorTable').on('click', '.btn-view', function() {
        let id = $(this).data('id');
        $('#detailsModal').modal('show');

        $.get("coordinator_payout_actions.php", {
            action: "get_details",
            coordinator_id: id,
            dateFrom: $('#dateFrom').val(),
            dateTo: $('#dateTo').val()
        }, function(res) {
            let tbody = $("#detailsTable tbody");
            tbody.empty();

            res.data.forEach(d => {
                tbody.append(`
                    <tr>
                        <td>${d.student_name}</td>
                        <td>${d.class}</td>
                        <td>${d.teacher_name}</td>
                        <td>${d.subject_name}</td>
                        <td>${d.date}</td>
                        <td>${d.outcome}</td>
                        <td>₹${d.fee}</td>
                        <td>${d.payment_status}</td>
                    </tr>
                `);
            });
        }, 'json');
    });

});
</script>