<?php
// my_students.php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'mentor'])) {
    header("location: login.php");
    exit;
}
$userRole = $_SESSION['role'];
ob_end_flush();
?>

<style>
.page-header-gradient {
    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
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

.btn-gradient-mentor-alt {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    border: none;
    color: white;
}

.cursor-pointer {
    cursor: pointer;
    transition: transform 0.2s;
}

.cursor-pointer:hover {
    transform: scale(1.2);
}

/* Floating Filter Button for Mobile */
.filter-btn-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
</style>

<div class="page-header-gradient text-dark p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-video3 display-4 text-danger me-3"></i>
        <div>
            <h2 class="fw-bold mb-0"><?php echo ($userRole === 'admin') ? 'All Students' : 'My Students'; ?></h2>
            <p class="mb-0 small">Manage academic progress and setup status.</p>
        </div>
    </div>
</div>

<div class="filter-btn-container">
    <button class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal"
        data-bs-target="#filterModal">
        <i class="bi bi-filter-right me-1"></i> Filter Students
    </button>
    <button id="btnResetFiltersGlobal" class="btn btn-light border btn-sm rounded-pill px-3">
        <i class="bi bi-arrow-counterclockwise"></i> Reset
    </button>
</div>

<div class="card shadow border-0">
    <div class="card-body p-3">
        <div class="table-responsive">
            <table id="studentsTable" class="table table-hover align-middle mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th rowspan="2">Sl</th>
                        <th rowspan="2">Student Info</th>
                        <?php if ($userRole === 'admin'): ?><th rowspan="2">Mentor</th><?php endif; ?>
                        <th rowspan="2">Status</th>
                        <th rowspan="2">Fee</th>
                        <th colspan="3" class="header-group-schedule">Schedule</th>
                        <th colspan="3" class="header-group-onetime">Setup</th>
                        <th rowspan="2" class="text-end">Action</th>
                    </tr>
                    <tr>
                        <th class="text-center bg-light">Dates</th>
                        <th class="text-center bg-light">Hrs</th>
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

<!-- FILTER MODAL -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Filter Options</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="filterForm">
                    <div class="row g-3">
                        <!-- ONLY SHOW MENTOR FILTER TO ADMIN -->
                        <?php if ($userRole === 'admin'): ?>
                        <div class="col-12">
                            <label class="small fw-bold text-muted">Mentor</label>
                            <select id="f_mentor" class="form-select shadow-sm">
                                <option value="">All Mentors</option>
                                <option value="Not Assigned">Not Assigned</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Active Status</label>
                            <select id="f_status" class="form-select">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="ready">Ready</option>
                                <option value="break">Break</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Fee Status</label>
                            <select id="f_fee" class="form-select">
                                <option value="">All</option>
                                <option value="paid">Paid</option>
                                <option value="partial">Partial</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Exam Status</label>
                            <select id="f_exam" class="form-select">
                                <option value="">All</option>
                                <option value="done">Conducted</option>
                                <option value="pending">Not Conducted</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Study Material</label>
                            <select id="f_mat" class="form-select">
                                <option value="">All</option>
                                <option value="1">Sent</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">WA Group</label>
                            <select id="f_grp" class="form-select">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">MS Teams</label>
                            <select id="f_team" class="form-select">
                                <option value="">All</option>
                                <option value="1">Created</option>
                                <option value="0">Pending</option>
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
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" id="btnResetModal">Clear All</button>
                <button type="button" class="btn btn-primary px-4" id="btnApplyFilters">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const userRole = "<?php echo $userRole; ?>";
    const storageKey = "my_students_filters"; // Key for session storage

    // 1. Initialize DataTable
    var table = $('#studentsTable').DataTable({
        "ajax": "my_students_action.php",
        "stateSave": true, // Remembers pagination and sorting natively
        "columns": [{
                "data": null,
                "render": (d, t, r, m) => m.row + 1
            },
            {
                "data": "name",
                "render": (d, t, r) => `<b>${d}</b><br><small>${r.class}</small>`
            },
            <?php if ($userRole === 'admin'): ?> {
                "data": "mentor_name"
            },
            <?php endif; ?> {
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
        "pageLength": 25
    });

    // 2. FUNCTION: Save filter values to Session Storage
    function saveFilters() {
        const filters = {
            mentor: $('#f_mentor').val(),
            status: $('#f_status').val(),
            fee: $('#f_fee').val(),
            exam: $('#f_exam').val(),
            prog: $('#f_progress').val(),
            mat: $('#f_mat').val(),
            grp: $('#f_grp').val(),
            team: $('#f_team').val()
        };
        sessionStorage.setItem(storageKey, JSON.stringify(filters));
    }

    // 3. FUNCTION: Load filter values from Session Storage
    function loadFilters() {
        const savedFilters = sessionStorage.getItem(storageKey);
        if (savedFilters) {
            const f = JSON.parse(savedFilters);
            $('#f_mentor').val(f.mentor);
            $('#f_status').val(f.status);
            $('#f_fee').val(f.fee);
            $('#f_exam').val(f.exam);
            $('#f_progress').val(f.prog);
            $('#f_mat').val(f.mat);
            $('#f_grp').val(f.grp);
            $('#f_team').val(f.team);

            // Redraw table with loaded filters
            table.draw();
        }
    }

    // 4. POPULATE MENTORS & THEN LOAD SAVED FILTERS
    if (userRole === 'admin') {
        $.get("admission_action.php?action=get_mentors", function(mRes) {
            if (mRes.status === 'success') {
                mRes.mentors.forEach(m => {
                    $('#f_mentor').append(new Option(m.name, m.name));
                });
                // Load filters only AFTER dropdowns are populated
                loadFilters();
            }
        }, 'json');
    } else {
        loadFilters();
    }

    // 5. CUSTOM FILTER LOGIC
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        var row = table.row(dataIndex).data();

        var mentor = $('#f_mentor').val();
        var status = $('#f_status').val();
        var fee = $('#f_fee').val();
        var exam = $('#f_exam').val();
        var f_prog = $('#f_progress').val();
        var mat = $('#f_mat').val();
        var grp = $('#f_grp').val();
        var team = $('#f_team').val();

        if (userRole === 'admin' && mentor && row.mentor_name !== mentor) return false;
        if (status && row.status !== status) return false;

        if (fee === 'paid' && !row.fee_display.includes('bg-success')) return false;
        if (fee === 'partial' && !row.fee_display.includes('bg-warning')) return false;
        if (fee === 'pending' && !row.fee_display.includes('bg-danger')) return false;

        if (exam === 'done' && !row.check_exam.includes('text-success')) return false;
        if (exam === 'pending' && row.check_exam.includes('text-success')) return false;

        var comp = parseFloat(row.completed_hours) || 0;
        var total = parseFloat(row.total_hours) || 0;
        if (f_prog === 'completed' && (total === 0 || comp < total)) return false;
        if (f_prog === 'pending' && (total === 0 || comp >= total)) return false;
        if (f_prog === 'no_sch' && total > 0) return false;

        function checkValue(htmlString, target) {
            if (target === "") return true;
            var match = htmlString.match(/data-val=['"](\d)['"]/);
            return match ? match[1] === target : false;
        }

        if (!checkValue(row.check_mat, mat)) return false;
        if (!checkValue(row.check_grp, grp)) return false;
        if (!checkValue(row.check_team, team)) return false;

        return true;
    });

    // 6. EVENT HANDLERS
    $('#btnApplyFilters').on('click', function() {
        saveFilters(); // Save before drawing
        table.draw();
        $('#filterModal').modal('hide');
    });

    $('#btnResetModal, #btnResetFiltersGlobal').on('click', function() {
        $('#filterForm')[0].reset();
        sessionStorage.removeItem(storageKey); // Clear the storage
        table.search('').draw();
    });

    // Toggle Checklist
    $('#studentsTable').on('click', '.btn-toggle-check', function() {
        var btn = $(this);
        $.post('my_students_action.php', {
            action: 'toggle_checklist',
            id: btn.data('id'),
            col: btn.data('col'),
            table: btn.data('table'),
            val: btn.data('val')
        }, () => table.ajax.reload(null, false));
    });
});
</script>