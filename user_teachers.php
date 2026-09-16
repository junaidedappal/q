<?php
include 'includes/header.php';
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['admin', 'hrmanager'])) {
    header("location: dashboard.php"); exit;
}

$subjects_res = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
?>

<style>
/* Modern Faculty Theme Styling */
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

.page-header-gradient {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border-radius: 1rem;
}

.glass-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 1.25rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
}

/* Table Enhancements */
#teachersTable thead th {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    color: #4a5568;
    border-bottom: 2px solid #edf2f7;
    padding: 1.25rem 1rem;
}

#teachersTable tbody td {
    padding: 1rem;
    color: #2d3748;
    border-bottom: 1px solid #f7fafc;
}

#teachersTable tbody tr:hover {
    background-color: rgba(56, 239, 125, 0.05);
}

/* Subject Pills */
.subject-pill {
    background: #e6fffa;
    color: #2c7a7b;
    border: 1px solid #81e6d9;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.remove-pill {
    cursor: pointer;
    font-size: 1rem;
    color: #e53e3e;
}

/* Modal Styling */
.modal-content {
    border-radius: 1.5rem;
    border: none;
}

.modal-header {
    padding: 1.5rem;
    border-radius: 1.5rem 1.5rem 0 0;
}

.form-label-custom {
    font-size: 0.8rem;
    font-weight: 700;
    color: #718096;
    text-transform: uppercase;
    margin-bottom: 0.4rem;
}

.form-control,
.form-select {
    border-radius: 0.75rem;
    padding: 0.6rem 1rem;
    border: 2px solid #e2e8f0;
}

.form-control:focus {
    border-color: #38ef7d;
    box-shadow: 0 0 0 3px rgba(56, 239, 125, 0.1);
}

.btn-pill {
    border-radius: 50px;
    font-weight: 700;
    transition: all 0.3s;
}

.btn-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}
</style>

<div class="container-fluid p-4 mb-4 rounded-3">
    <!-- Header -->
    <div
        class="page-header-gradient text-white p-4 mb-4 shadow-sm d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="text-center text-md-start">
            <h2 class="fw-bold mb-0"><i class="bi bi-mortarboard-fill me-2"></i>Teacher Management</h2>
            <p class="mb-0 opacity-75">Manage faculty profiles, rates, and subject expertise.</p>
        </div>
        <button class="btn btn-light text-success fw-bold btn-pill px-4 py-2 shadow-sm" id="addTeacherBtn">
            <i class="bi bi-plus-lg me-1"></i> Add New Teacher
        </button>
    </div>

    <!-- Main List -->
    <div class="card glass-card border-0">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="teachersTable" class="table table-hover align-middle mb-0" style="width:100%">
                    <thead>
                        <tr>
                            <th>Reg Code</th>
                            <th>Teacher Name</th>
                            <th>Contact Info</th>
                            <th class="text-center">Hourly Rate</th>
                            <th>Expertise</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Teacher Modal -->
<div class="modal fade" id="teacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="teacherModalLabel">Teacher Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="teacherForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="action">
                    <input type="hidden" name="user_id" id="user_id">

                    <!-- Section: Account -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-2 rounded-circle me-2 text-success"><i
                                class="bi bi-person-badge"></i></div>
                        <h6 class="text-success fw-bold mb-0 small text-uppercase">Account Credentials</h6>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-custom">Full Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Enter full name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Email Address *</label>
                            <input type="email" name="email" class="form-control" required
                                placeholder="email@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="Contact number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Password</label>
                            <input type="password" name="password" id="pass_field" class="form-control">
                            <small class="text-muted" id="pass_hint" style="font-size: 0.7rem;">Required for new
                                accounts.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Joining Date</label>
                            <input type="date" name="joining_date" class="form-control">
                        </div>
                    </div>

                    <!-- Section: Professional -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 p-2 rounded-circle me-2 text-success"><i
                                class="bi bi-briefcase"></i></div>
                        <h6 class="text-success fw-bold mb-0 small text-uppercase">Professional Profile</h6>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-custom">Reg. Code</label>
                            <input type="text" name="teacher_reg_code" class="form-control" placeholder="TEACHER CODE"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Hourly Rate (₹) *</label>
                            <input type="number" step="0.01" name="hourly_rate" class="form-control" required
                                placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">Exp (Years)</label>
                            <input type="number" name="experience_years" class="form-control" placeholder="0">
                        </div>

                        <div class="col-12">
                            <label class="form-label-custom">Subjects Expertise</label>
                            <div class="input-group mb-2 shadow-sm">
                                <select id="subject_select_input" class="form-select border-end-0">
                                    <option value="">Select Subject...</option>
                                    <?php 
                                    $subjects_res->data_seek(0);
                                    while($s = $subjects_res->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <button class="btn btn-success px-3" type="button" id="add_subject_btn"><i
                                        class="bi bi-plus-lg"></i></button>
                            </div>
                            <div id="subject_pills" class="d-flex flex-wrap gap-2 mt-2"></div>
                            <div id="subject_inputs" class="d-none"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Qualifications</label>
                            <input type="text" name="qualifications" class="form-control"
                                placeholder="e.g. MSc Physics">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                placeholder="Residential address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-3">
                    <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-5 btn-pill shadow" id="saveBtn">Save
                        Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const allSubjects =
        <?php $subjects_res->data_seek(0); echo json_encode($subjects_res->fetch_all(MYSQLI_ASSOC)); ?>;

    var table = $('#teachersTable').DataTable({
        "ajax": "user_teachers_action.php?action=list",
        "columns": [{
                "data": "teacher_reg_code"
            },
            {
                "data": "name",
                "render": (d, t, r) =>
                    `<b>${d}</b><br><small class="text-muted">Joined: ${r.joining_date || 'N/A'}</small>`
            },
            {
                "data": "email",
                "render": (d, t, r) => `${d}<br><small class='text-muted'>${r.phone || ''}</small>`
            },
            {
                "data": "hourly_rate",
                "className": "text-center",
                "render": d => `₹${parseFloat(d).toFixed(2)}`
            },
            {
                "data": "subjects",
                "render": d => d || '<span class="text-muted small">Not set</span>'
            },
            {
                "data": "status_badge"
            },
            {
                "data": "actions",
                "className": "text-end"
            }
        ]
    });

    function addSubjectPill(id, name) {
        if ($('#subject_inputs input[value="' + id + '"]').length > 0) return;
        $('#subject_pills').append(
            `<span class="badge bg-success p-2 rounded-pill" data-id="${id}">${name} <i class="bi bi-x cursor-pointer remove-pill"></i></span>`
        );
        $('#subject_inputs').append(`<input type="hidden" name="teacher_subjects[]" value="${id}">`);
    }

    $('#add_subject_btn').click(function() {
        let sel = $('#subject_select_input');
        if (sel.val()) addSubjectPill(sel.val(), sel.find('option:selected').text());
        sel.val('');
    });

    $('#subject_pills').on('click', '.remove-pill', function() {
        let id = $(this).parent().data('id');
        $(this).parent().remove();
        $(`#subject_inputs input[value="${id}"]`).remove();
    });

    $('#addTeacherBtn').click(function() {
        $('#teacherForm')[0].reset();
        $('#action').val('add');
        $('#user_id').val('');
        $('#subject_pills, #subject_inputs').empty();
        $('#pass_field').prop('required', true);
        $('#teacherModal').modal('show');
    });

    $('#teachersTable').on('click', '.btn-edit', function() {
        const id = $(this).data('id');
        $.get('user_teachers_action.php', {
            action: 'get',
            user_id: id
        }, function(res) {
            if (res.status === 'success') {
                const d = res.data;
                $('#teacherForm')[0].reset();
                $('#subject_pills, #subject_inputs').empty();
                $('#action').val('edit');
                $('#user_id').val(d.id);
                $('input[name="name"]').val(d.name);
                $('input[name="email"]').val(d.email);
                $('input[name="phone"]').val(d.phone);
                $('input[name="joining_date"]').val(d.joining_date);
                $('select[name="status"]').val(d.status);
                $('#pass_field').prop('required', false);

                if (d.teacher_details) {
                    $('input[name="teacher_reg_code"]').val(d.teacher_details.teacher_reg_code);
                    $('input[name="hourly_rate"]').val(d.teacher_details.hourly_rate);
                    $('input[name="experience_years"]').val(d.teacher_details.experience_years);
                    $('input[name="qualifications"]').val(d.teacher_details.qualifications);
                    $('textarea[name="address"]').val(d.teacher_details.address);
                }
                if (d.subjects_list) {
                    d.subjects_list.forEach(s => addSubjectPill(s.id, s.name));
                }
                $('#teacherModal').modal('show');
            }
        });
    });

    $('#teacherForm').on('submit', function(e) {
        e.preventDefault();
        $.post('user_teachers_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#teacherModal').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire('Success', res.message, 'success');
            } else Swal.fire('Error', res.message, 'error');
        }, 'json');
    });

    $('#teachersTable').on('click', '.btn-delete', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Teacher?',
            icon: 'warning',
            showCancelButton: true
        }).then(r => {
            if (r.isConfirmed) $.post('user_teachers_action.php', {
                action: 'delete',
                user_id: id
            }, () => table.ajax.reload(null, false));
        });
    });
});
</script>