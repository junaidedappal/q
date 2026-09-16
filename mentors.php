<?php
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header('location: login.php'); exit;
}

// Auto-Sync missing profiles
$conn->query("INSERT INTO mentors (user_id, rate_per_session, rate_per_exam)
              SELECT id, 15.00, 30.00 FROM users 
              WHERE role = 'mentor' AND id NOT IN (SELECT user_id FROM mentors)");

$sql = "SELECT m.*, u.name, u.phone, u.status, sup.name as supervisor_name 
        FROM mentors m 
        JOIN users u ON m.user_id = u.id 
        LEFT JOIN users sup ON m.supervisor_id = sup.id
        ORDER BY u.name ASC";
$result = $conn->query($sql);
?>

<style>
.search-input {
    border-radius: 12px;
    padding: 0.6rem 1rem 0.6rem 2.5rem;
    border: 1px solid #e2e8f0;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

.mentor-card {
    border: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 20px;
    transition: all 0.3s ease;
    height: 100%;
}

.mentor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1);
}

.rate-container {
    background: #f8fafc;
    border-radius: 15px;
    padding: 12px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.rate-item label {
    font-size: 0.6rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    display: block;
}

.rate-item span {
    font-weight: 700;
    color: var(--slate-900);
}

.supervisor-tag {
    font-size: 0.7rem;
    background: #fff1f2;
    color: #e11d48;
    padding: 3px 10px;
    border-radius: 6px;
    font-weight: 700;
}

.btn-update-sm {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 12px;
    background: var(--emerald-500);
    color: white;
    border: none;
}
</style>

<div class="container pb-5">
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-6">
            <h3 class="fw-bold mb-0">Mentor Management</h3>
            <p class="text-muted small mb-0">Rates & Supervisor Assignments</p>
        </div>
        <div class="col-md-6 d-flex justify-content-md-end">
            <div class="position-relative w-100" style="max-width: 350px;">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="mentorSearch" class="form-control search-input" placeholder="Search mentors...">
            </div>
        </div>
    </div>

    <div class="row g-4" id="mentorGrid">
        <?php while($row = $result->fetch_assoc()): ?>
        <div class="col-12 col-md-6 col-lg-4 mentor-item"
            data-search="<?php echo strtolower($row['name'].' '.$row['phone']); ?>">
            <div class="card mentor-card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3" style="width:40px; height:40px;">
                                <?php echo strtoupper(substr($row['name'], 0, 1)); ?></div>
                            <div>
                                <h6 class="fw-bold mb-0"><?php echo $row['name']; ?></h6>
                                <small class="text-muted"><?php echo $row['phone']; ?></small>
                            </div>
                        </div>
                        <span
                            class="badge <?php echo $row['status'] == 'active' ? 'bg-success' : 'bg-danger'; ?> rounded-pill"
                            style="font-size:0.5rem; height:fit-content;">
                            <?php echo strtoupper($row['status']); ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Managed
                            By:</label><br>
                        <?php if($row['supervisor_name']): ?>
                        <span class="supervisor-tag"><i
                                class="bi bi-person-badge me-1"></i><?php echo $row['supervisor_name']; ?></span>
                        <?php else: ?>
                        <span class="text-muted small italic">Not assigned</span>
                        <?php endif; ?>
                    </div>

                    <div class="rate-container mb-3 text-center">
                        <div class="rate-item border-end">
                            <label>Session</label>
                            <span>₹<?php echo number_format($row['rate_per_session'], 2); ?></span>
                        </div>
                        <div class="rate-item">
                            <label>Exam</label>
                            <span>₹<?php echo number_format($row['rate_per_exam'], 2); ?></span>
                        </div>
                    </div>

                    <button class="btn btn-update-sm w-100 edit-mentor-btn" data-id="<?php echo $row['id']; ?>">
                        Configure Mentor
                    </button>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="mentorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0" style="border-radius: 25px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">Mentor Config</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="mentorRatesForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_mentor">
                    <input type="hidden" name="id" id="m_id">

                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-2 text-uppercase">Assign Supervisor</label>
                        <select name="supervisor_id" id="m_supervisor"
                            class="form-select fw-semibold border-2"></select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-2 text-uppercase">Session Rate</label>
                            <input type="number" step="0.01" name="rate_per_session" id="m_rate_s"
                                class="form-control fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted mb-2 text-uppercase">Exam Rate</label>
                            <input type="number" step="0.01" name="rate_per_exam" id="m_rate_e"
                                class="form-control fw-bold" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" class="btn btn-gradient-primary w-100 rounded-pill py-3 fw-bold shadow">Save
                        Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const mModal = new bootstrap.Modal(document.getElementById('mentorModal'));

    // Search logic
    $("#mentorSearch").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        $(".mentor-item").filter(function() {
            $(this).toggle($(this).data("search").indexOf(value) > -1)
        });
    });

    // Open Modal
    $('.edit-mentor-btn').on('click', function() {
        const id = $(this).data('id');
        const btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        // Load supervisors first
        $.get('mentors_action.php', {
            action: 'get_supervisors'
        }, function(supRes) {
            let opts = '<option value="">No Supervisor Assigned</option>';
            supRes.data.forEach(s => opts += `<option value="${s.id}">${s.name}</option>`);
            $('#m_supervisor').html(opts);

            // Load mentor data
            $.get('mentors_action.php', {
                action: 'get_mentor',
                id: id
            }, function(res) {
                if (res.status === 'success') {
                    $('#m_id').val(res.data.id);
                    $('#m_rate_s').val(res.data.rate_per_session);
                    $('#m_rate_e').val(res.data.rate_per_exam);
                    $('#m_supervisor').val(res.data.supervisor_id);
                    btn.prop('disabled', false).html('Configure Mentor');
                    mModal.show();
                }
            }, 'json');
        }, 'json');
    });

    // Form Submit
    $('#mentorRatesForm').on('submit', function(e) {
        e.preventDefault();
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('Updating...');

        $.post('mentors_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                mModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', res.message, 'error');
                submitBtn.prop('disabled', false).html('Save Configuration');
            }
        }, 'json');
    });
});
</script>