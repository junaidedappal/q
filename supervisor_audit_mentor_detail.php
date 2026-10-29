<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    header('location: login.php'); exit;
}

$userRole = $_SESSION['role'];
$mentor_id = intval($_GET['mentor_id'] ?? 0);
$month = intval($_GET['month'] ?? date('m'));
$year = intval($_GET['year'] ?? date('Y'));

if ($userRole === 'supervisor') {
    $m_stmt = $conn->prepare("SELECT u.name FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ? AND m.supervisor_id = ?");
    $m_stmt->bind_param("ii", $mentor_id, $_SESSION['id']);
} else {
    $m_stmt = $conn->prepare("SELECT u.name FROM mentors m JOIN users u ON m.user_id = u.id WHERE m.id = ?");
    $m_stmt->bind_param("i", $mentor_id);
}
$m_stmt->execute();
$mentor = $m_stmt->get_result()->fetch_assoc();
if ($userRole === 'supervisor' && !$mentor) {
    header('location: supervisor_monitoring.php');
    exit;
}
$mentor_name = $mentor['name'] ?? 'Unknown Mentor';
?>

<style>
.page-header-gradient {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
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
}

.header-group-schedule {
    background-color: #d1e7dd !important;
    color: #0f5132;
    text-align: center;
}
</style>

<div
    class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm d-flex justify-content-between align-items-center">
    <div>
        <h2 class="fw-bold mb-0">Mentor Performance Audit</h2>
        <p class="mb-0 opacity-75">Reviewing: <b><?php echo htmlspecialchars($mentor_name, ENT_QUOTES, 'UTF-8'); ?></b></p>
    </div>
    <a href="supervisor_monitoring.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>"
        class="btn btn-light btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-left"></i> Back to Grid
    </a>
</div>

<!-- COMPACT FILTER BUTTON -->
<div class="mb-4">
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal"
        data-bs-target="#auditFilterModal">
        <i class="bi bi-filter-right me-1"></i> Filter These Students
    </button>
</div>

<div class="card shadow border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="auditTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th rowspan="2">Sl</th>
                        <th rowspan="2">Student</th>
                        <th rowspan="2">Status</th>
                        <th rowspan="2">Fee</th>
                        <th colspan="3" class="header-group-schedule">Active Schedule</th>
                        <th colspan="3" class="header-group-onetime">Onboarding</th>
                        <th rowspan="2" class="text-end">View</th>
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

<!-- READ-ONLY FILTER MODAL -->
<div class="modal fade" id="auditFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Filter Audit View</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Status</label>
                            <select id="f_status" class="form-select">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="ready">Ready</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Fee</label>
                            <select id="f_fee" class="form-select">
                                <option value="">All</option>
                                <option value="Paid">Paid</option>
                                <option value="Partial">Partial</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small fw-bold text-muted">Material</label>
                            <select id="f_mat" class="form-select">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small fw-bold text-muted">WA Group</label>
                            <select id="f_grp" class="form-select">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="small fw-bold text-muted">Teams</label>
                            <select id="f_team" class="form-select">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-muted">Exam Status</label>
                            <select id="f_exam" class="form-select">
                                <option value="">All</option>
                                <option value="done">Conducted</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="small fw-bold text-muted">Hours Progress</label>
                            <select id="f_progress" class="form-select">
                                <option value="">All Progress</option>
                                <option value="completed">Fully Completed (0 Hrs Left)</option>
                                <option value="pending">In Progress (Has Remaining Hrs)</option>
                                <option value="no_sch">No Schedule Set</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none"
                        id="btnResetModal">Clear All</button>
                    <button type="button" class="btn btn-primary px-5 rounded-pill shadow"
                        onclick="applyAuditFilters()">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#auditTable').DataTable({
        "ajax": {
            "url": "supervisor_action.php",
            "data": {
                action: 'get_mentor_students',
                mentor_id: <?php echo $mentor_id; ?>
            }
        },
        "columns": [{
                "data": null,
                "render": (d, t, r, m) => m.row + 1
            },
            {
                "data": "name",
                "render": (d, t, r) => `<b>${d}</b><br><small>${r.class}</small>`
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
                "data": "id",
                "className": "text-end",
                "render": (id) =>
                    `<a href="student_schedule.php?id=${id}" class="btn btn-sm btn-outline-primary py-0">View</a>`
            }
        ],
        "pageLength": 25,
        "language": {
            "search": "",
            "searchPlaceholder": "Quick search student name..."
        }
    });

    // --- CUSTOM FILTER LOGIC ---
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = table.row(dataIndex).data();

        var f_status = $('#f_status').val();
        var f_fee = $('#f_fee').val();
        var f_exam = $('#f_exam').val();
        var f_progress = $('#f_progress').val();
        var f_mat = $('#f_mat').val(),
            f_grp = $('#f_grp').val(),
            f_team = $('#f_team').val();

        // 1. Status & Fee
        if (f_status && !row.status_badge.toLowerCase().includes(f_status)) return false;
        if (f_fee && !row.fee_display.includes(f_fee)) return false;

        // 2. Exam Status (Check icon color)
        if (f_exam === 'done' && !row.check_exam.includes('text-success')) return false;
        if (f_exam === 'pending' && row.check_exam.includes('text-success')) return false;

        // 3. Hours Progress Logic
        var comp = parseFloat(row.completed_hours) || 0;
        var total = parseFloat(row.total_hours) || 0;

        if (f_progress === 'completed') {
            if (total === 0 || comp < total) return false;
        } else if (f_progress === 'pending') {
            if (total === 0 || comp >= total) return false;
        } else if (f_progress === 'no_sch') {
            if (total > 0) return false;
        }

        // 4. Checklist logic (Read data-val using regex)
        function checkVal(html, target) {
            if (target === "") return true;
            let m = html.match(/data-val=['"](\d)['"]/);
            return m ? m[1] === target : false;
        }

        if (!checkVal(row.check_mat, f_mat)) return false;
        if (!checkVal(row.check_grp, f_grp)) return false;
        if (!checkVal(row.check_team, f_team)) return false;

        return true;
    });

    // Handler: Apply Filters
    window.applyAuditFilters = function() {
        table.draw();
        $('#auditFilterModal').modal('hide');
    };

    // Handler: Reset Filters (Global and Modal)
    $('#btnResetModal, #btnResetGlobal').on('click', function() {
        $('#filterForm')[0].reset(); // Clears all select boxes
        table.search('').draw(); // Clears text search and redraws custom filters
    });
});
</script>