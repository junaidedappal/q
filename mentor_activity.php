<?php
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('location: dashboard.php'); exit;
}

// Fetch Mentors for Filter
$mentors = $conn->query("SELECT m.id, u.name FROM mentors m JOIN users u ON m.user_id = u.id ORDER BY u.name ASC");
?>
<style>
body {
    background-color: #f0f2f5;
    font-family: 'Inter', sans-serif;
}

/* Responsive Header Area */
.page-header-gradient {
    background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
</style>
<div class="container-fluid py-4 ">
    <div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
        <h2 class="fw-bold mb-1"><i class="bi bi-activity me-2"></i>Mentor Activity Tracker</h2>
        <p class="mb-0 opacity-75">Monitor daily sessions, attendance status, and teaching hours.</p>
    </div>

    <!-- Filter Section -->
    <div class="card border-0 shadow-sm mb-4 " style="border-radius: 15px;">
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Quick Filter</label>
                    <select id="quickDate" class="form-select">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3 custom-date d-none">
                    <label class="form-label small fw-bold">From</label>
                    <input type="date" id="dateFrom" class="form-control">
                </div>
                <div class="col-md-3 custom-date d-none">
                    <label class="form-label small fw-bold">To</label>
                    <input type="date" id="dateTo" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Mentor</label>
                    <select id="mentorFilter" class="form-select">
                        <option value="">All Mentors</option>
                        <?php while($m = $mentors->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>"><?= $m['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100 fw-bold" id="refreshBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i> Update View
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Table -->
    <div class="card border-0 shadow-sm " style="border-radius: 15px;">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table id="activityTable" class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Time / Date</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Mentor</th>
                            <th>Teacher</th>
                            <th class="text-center">Hrs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#activityTable').DataTable({
        "ajax": {
            "url": "mentor_activity_action.php",
            "data": function(d) {
                d.quick = $('#quickDate').val();
                d.from = $('#dateFrom').val();
                d.to = $('#dateTo').val();
                d.mentor_id = $('#mentorFilter').val();
            }
        },
        "columns": [{
                "data": "time_date"
            },
            {
                "data": "student_name"
            },
            {
                "data": "subject"
            },
            {
                "data": "mentor_name"
            },
            {
                "data": "teacher_name"
            },
            {
                "data": "hrs",
                "className": "text-center"
            },
            {
                "data": "status_badge"
            }
        ],
        "order": [
            [0, 'desc']
        ],
        "pageLength": 25,
        "dom": 'Bfrtip',
        "buttons": ['excel', 'pdf']
    });

    $('#quickDate').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.custom-date').removeClass('d-none');
        } else {
            $('.custom-date').addClass('d-none');
        }
    });

    $('#refreshBtn').click(() => table.ajax.reload());
});
</script>