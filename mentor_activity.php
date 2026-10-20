<?php
include 'includes/header.php';
require 'includes/db.php';

$role = $_SESSION['role'] ?? '';
$allowedRoles = ['admin', 'qualitycontroller', 'supervisor'];
if (($_SESSION['loggedin'] ?? false) !== true || !in_array($role, $allowedRoles, true)) {
    header('location: dashboard.php');
    exit;
}

$supervisorScopeId = 0;
if ($role === 'supervisor') {
    $supervisorScopeId = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT);
    if ($supervisorScopeId === false || $supervisorScopeId < 1) {
        http_response_code(403);
        exit('Your supervisor account could not be verified.');
    }
}

if ($role === 'supervisor') {
    $mentorStmt = $conn->prepare(
        "SELECT m.id, u.name
         FROM mentors m
         JOIN users u ON m.user_id = u.id
         WHERE m.supervisor_id = ?
         ORDER BY u.name ASC"
    );
    if (!$mentorStmt) {
        error_log('Mentor activity filter query preparation failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load the mentor list.');
    }
    $mentorStmt->bind_param('i', $supervisorScopeId);
    if (!$mentorStmt->execute()) {
        error_log('Mentor activity filter query execution failed: ' . $mentorStmt->error);
        http_response_code(500);
        exit('Unable to load the mentor list.');
    }
    $mentors = $mentorStmt->get_result();
} else {
    $mentors = $conn->query(
        "SELECT m.id, u.name
         FROM mentors m
         JOIN users u ON m.user_id = u.id
         ORDER BY u.name ASC"
    );
}

if (!$mentors) {
    error_log('Mentor activity filter query failed: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load the mentor list.');
}

$scopeLabel = match ($role) {
    'admin' => 'All mentors',
    'qualitycontroller' => 'All mentors · read-only',
    'supervisor' => 'Your assigned mentors',
    default => 'Mentor activity'
};
$mentorFilterLabel = $role === 'supervisor' ? 'All assigned mentors' : 'All mentors';
?>
<style>
body {
    background: #f4f7fb;
}

.activity-page {
    max-width: 1680px;
    margin: 0 auto;
    padding-inline: clamp(.75rem, 2.5vw, 2rem);
}

.activity-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    padding: clamp(1.25rem, 4vw, 2.25rem);
    margin-bottom: 1rem;
    color: #fff;
    border-radius: 1.5rem;
    background: linear-gradient(125deg, #064e3b 0%, #047857 54%, #0f766e 100%);
    box-shadow: 0 18px 38px rgba(6, 78, 59, .18);
}

.activity-hero::after {
    content: "";
    position: absolute;
    z-index: -1;
    width: 19rem;
    height: 19rem;
    right: -7rem;
    top: -11rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, .1);
}

.activity-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem;
    color: rgba(255, 255, 255, .8);
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.activity-hero h1 {
    margin: 0;
    font-size: clamp(1.55rem, 5vw, 2.25rem);
    font-weight: 800;
    letter-spacing: -.04em;
}

.activity-hero p {
    max-width: 44rem;
    margin: .55rem 0 0;
    color: rgba(255, 255, 255, .8);
    font-size: .95rem;
}

.activity-scope {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    padding: .65rem .9rem;
    color: #fff;
    font-size: .85rem;
    font-weight: 700;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .24);
    border-radius: 999px;
    backdrop-filter: blur(8px);
}

.activity-panel {
    overflow: hidden;
    margin-bottom: 1rem;
    background: #fff;
    border: 1px solid #e8eef5;
    border-radius: 1.25rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .045);
}

.activity-panel-heading {
    margin: 0;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
}

.activity-panel .form-label {
    margin-bottom: .45rem;
    color: #475569;
    font-size: .78rem;
    font-weight: 700;
}

.activity-panel .form-select,
.activity-panel .form-control {
    min-height: 46px;
    border-color: #dce5ee;
    border-radius: .75rem;
}

.activity-panel .form-select:focus,
.activity-panel .form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .14);
}

.activity-refresh {
    min-height: 46px;
    border: 0;
    border-radius: .75rem;
    background: linear-gradient(135deg, #059669, #047857);
    box-shadow: 0 6px 14px rgba(5, 150, 105, .2);
}

.activity-table-wrap {
    border-radius: 1.25rem;
}

#activityTable {
    min-width: 790px;
}

#activityTable thead th {
    padding: .9rem .8rem;
    color: #475569;
    background: #f8fafc;
    border-bottom: 1px solid #e8eef5;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    white-space: nowrap;
}

#activityTable tbody td {
    padding: .85rem .8rem;
    color: #334155;
    border-color: #eef2f6;
    font-size: .88rem;
}

.activity-table-panel .card-body {
    padding: .75rem;
}

.activity-table-panel .dt-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    margin-bottom: .75rem;
}

.activity-table-panel .dt-buttons .btn {
    border-radius: .65rem !important;
}

.activity-table-panel .dataTables_filter {
    margin-bottom: .75rem;
    text-align: left;
}

.activity-table-panel .dataTables_filter input {
    min-height: 40px;
    margin-left: .4rem;
    border: 1px solid #dce5ee;
    border-radius: .65rem;
}

.activity-table-panel .dataTables_info,
.activity-table-panel .dataTables_paginate {
    padding-top: .9rem;
    font-size: .8rem;
}

@media (min-width: 768px) {
    .activity-hero {
        flex-direction: row;
        align-items: center;
    }

    .activity-table-panel .card-body {
        padding: 1rem;
    }
}

@media (max-width: 575.98px) {
    .activity-page {
        padding-inline: .75rem;
    }

    .activity-scope {
        font-size: .78rem;
    }

    .activity-table-panel .dataTables_filter,
    .activity-table-panel .dataTables_filter label {
        width: 100%;
    }

    .activity-table-panel .dataTables_filter input {
        display: block;
        width: 100%;
        margin: .4rem 0 0;
    }
}
</style>

<main class="activity-page container-fluid py-3 py-lg-4">
    <section class="activity-hero">
        <div>
            <span class="activity-eyebrow"><i class="bi bi-activity"></i> Operations overview</span>
            <h1>Mentor Activity</h1>
            <p>Review session schedules, attendance, and teaching hours for the selected date range.</p>
        </div>
        <div class="activity-scope"><i class="bi bi-shield-check"></i><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></div>
    </section>

    <section class="activity-panel">
        <div class="card-body p-3 p-lg-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1 mb-3">
                <h2 class="activity-panel-heading">Activity filters</h2>
                <span class="small text-muted">Choose a time period and mentor</span>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-xl-2">
                    <label for="quickDate" class="form-label">Quick date</label>
                    <select id="quickDate" class="form-select">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This week</option>
                        <option value="custom">Custom range</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2 custom-date d-none">
                    <label for="dateFrom" class="form-label">From</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>
                <div class="col-12 col-md-6 col-xl-2 custom-date d-none">
                    <label for="dateTo" class="form-label">To</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <label for="mentorFilter" class="form-label">Mentor</label>
                    <select id="mentorFilter" class="form-select">
                        <option value=""><?= htmlspecialchars($mentorFilterLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php while ($mentor = $mentors->fetch_assoc()): ?>
                        <option value="<?= (int) $mentor['id'] ?>"><?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-xl-2">
                    <button type="button" class="btn btn-success activity-refresh w-100 fw-bold" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i> Update view
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div id="activityError" class="alert alert-danger d-none" role="alert"></div>

    <section class="activity-panel activity-table-panel">
        <div class="card-body">
            <div class="table-responsive activity-table-wrap">
                <table id="activityTable" class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Time / Date</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Mentor</th>
                            <th>Teacher</th>
                            <th class="text-center">Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#activityTable').DataTable({
        ajax: {
            url: 'mentor_activity_action.php',
            data: function(data) {
                data.quick = $('#quickDate').val();
                data.from = $('#dateFrom').val();
                data.to = $('#dateTo').val();
                data.mentor_id = $('#mentorFilter').val();
            },
            error: function(xhr) {
                var response = xhr.responseJSON;
                var message = response && response.error ? response.error : 'Unable to load mentor activity.';
                $('#activityError').text(message).removeClass('d-none');
            }
        },
        columns: [
            { data: 'time_date' },
            { data: 'student_name' },
            { data: 'subject' },
            { data: 'mentor_name' },
            { data: 'teacher_name' },
            { data: 'hrs', className: 'text-center' },
            { data: 'status_badge' }
        ],
        order: [],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['excel', 'pdf'],
        language: {
            emptyTable: 'No mentor activity found for this period.',
            zeroRecords: 'No activity matches your search.'
        }
    });

    $('#quickDate').on('change', function() {
        var isCustom = $(this).val() === 'custom';
        $('.custom-date').toggleClass('d-none', !isCustom);
        $('#dateFrom, #dateTo').prop('required', isCustom);
        $('#activityError').addClass('d-none').text('');
    });

    $('#refreshBtn').on('click', function() {
        if ($('#quickDate').val() === 'custom' && (!$('#dateFrom').val() || !$('#dateTo').val())) {
            $('#dateFrom').trigger('focus');
            $('#activityError').text('Select both dates for a custom range.').removeClass('d-none');
            return;
        }
        $('#activityError').addClass('d-none').text('');
        table.ajax.reload();
    });
});
</script>
