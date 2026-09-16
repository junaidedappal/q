<?php
include 'includes/header.php';
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    header("location: dashboard.php");
    exit;
}
// Fetch subjects into a PHP array ONCE.
$subjects_query_result = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
$all_subjects_array = $subjects_query_result->fetch_all(MYSQLI_ASSOC);
?>
<style>
/* Your complete stylish CSS is correct */
body {
    background-color: #f0f2f5;
}

.page-header-gradient {
    background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

.stylish-table thead th {
    border: none;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #ddd;
}

.stylish-table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.btn-purple {
    background-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5a3e8d;
    color: white;
}

.btn-gradient-teacher {
    background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
    border: none;
    color: white;
}

.btn-gradient-teacher:hover {
    color: white;
    opacity: 0.9;
}

.modal-header-gradient {
    background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
    border-bottom: none;
}

#teacher_fields {
    border-top: 1px solid #dee2e6;
    padding-top: 1rem;
    margin-top: 1rem;
}

.subject-pill {
    background-color: #198754;
    color: white;
    padding: 0.3rem 0.75rem;
    border-radius: 1rem;
    display: inline-flex;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 500;
}

.remove-subject-btn {
    background: none;
    border: none;
    color: white;
    margin-left: 0.5rem;
    padding: 0;
    line-height: 1;
    opacity: 0.7;
    font-size: 1.2rem;
}

.remove-subject-btn:hover {
    opacity: 1;
}
</style>

<!-- Stylish Page Header -->
<div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-video display-4 me-3"></i>
        <div>
            <h2 class="fw-bold mb-0">Teacher Management</h2>
            <p class="mb-0">A central dashboard for all teacher profiles and information.</p>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="card glass-card">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-4">
        <h5 class="card-title mb-0 text-dark">All Registered Teachers</h5>
        <a href="users.php" class="btn btn-primary btn-gradient-teacher shadow-sm">
            <i class="bi bi-person-plus-fill me-1"></i>Add / Manage All Users
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="teachersTable" class="table table-hover stylish-table" style="width:100%">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Reg. Code</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Teacher Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white" id="userModalLabel">Edit Teacher</h5><button type="button"
                    class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit"><input type="hidden" name="user_id" id="user_id">

                    <h6 class="text-muted">Basic Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3"><label>Full Name</label><input type="text" class="form-control"
                                    name="name" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Email Address</label><input type="email" class="form-control"
                                    name="email" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Phone Number</label><input type="text" class="form-control"
                                    name="phone"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Password</label><input type="password" class="form-control"
                                    name="password" placeholder="Leave blank to keep current"></div>
                        </div>
                    </div>

                    <h6 class="text-muted mt-3">System Access</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3"><label>Role</label><input type="text" class="form-control" name="role"
                                    value="teacher" readonly></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Status</label><select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select></div>
                        </div>
                    </div>

                    <div id="teacher_fields">
                        <hr>
                        <h6 class="text-muted">Teacher Profile Details</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3"><label>Teacher Reg. Code</label><input type="text"
                                        class="form-control" name="teacher_reg_code"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3"><label>Hourly Rate ($)</label><input type="number" step="0.01"
                                        class="form-control" name="hourly_rate"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3"><label>Experience (Years)</label><input type="number"
                                        class="form-control" name="experience_years"></div>
                            </div>
                        </div>
                        <div class="mb-3"><label>Qualifications</label><input type="text" class="form-control"
                                name="qualifications"></div>
                        <div class="mb-3">
                            <label class="form-label">Subjects Taught</label>
                            <div class="input-group">
                                <select id="teacher-subject-select-input" class="form-select">
                                    <option value="">Select a subject to add...</option>
                                    <?php foreach ($all_subjects_array as $subject) { echo "<option value='{$subject['id']}'>".htmlspecialchars($subject['name'])."</option>"; } ?>
                                </select>
                                <button class="btn btn-success" type="button" id="add-teacher-subject-btn"><i
                                        class="bi bi-plus-lg"></i> Add</button>
                            </div>
                            <div id="teacher-selected-subjects-container" class="mt-2 d-flex flex-wrap gap-2"></div>
                            <div id="teacher-hidden-subjects-inputs"></div>
                        </div>
                        <div class="mb-3"><label>Bio / Profile Summary</label><textarea class="form-control" name="bio"
                                rows="2"></textarea></div>
                        <div class="mb-3"><label>Address</label><textarea class="form-control" name="address"
                                rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-gradient" id="saveBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#teachersTable').DataTable({
        "ajax": "teacher_action.php",
        "columns": [{
                "data": "id"
            }, {
                "data": "name"
            }, {
                "data": "email"
            },
            {
                "data": "teacher_reg_code"
            }, {
                "data": "status_badge"
            },
            {
                "data": "actions",
                "orderable": false
            }
        ],
        "order": [
            [0, 'desc']
        ]
    });

    var allSubjects = <?php echo json_encode($all_subjects_array); ?>;

    function addTeacherSubject(subjectId, subjectText) {
        if ($('#teacher-hidden-subjects-inputs input[value="' + subjectId + '"]').length > 0) return;
        const pillHtml =
            `<span class="subject-pill" data-id="${subjectId}">${subjectText} <button type="button" class="remove-subject-btn">&times;</button></span>`;
        $('#teacher-selected-subjects-container').append(pillHtml);
        const inputHtml = `<input type="hidden" name="teacher_subjects[]" value="${subjectId}">`;
        $('#teacher-hidden-subjects-inputs').append(inputHtml);
    }

    $('#add-teacher-subject-btn').on('click', function() {
        const subjectId = $('#teacher-subject-select-input').val();
        if (subjectId) {
            const subjectText = $('#teacher-subject-select-input option:selected').text();
            addTeacherSubject(subjectId, subjectText);
            $('#teacher-subject-select-input').val('');
        }
    });

    $('#teacher-selected-subjects-container').on('click', '.remove-subject-btn', function() {
        const subjectId = $(this).closest('.subject-pill').data('id');
        $(this).closest('.subject-pill').remove();
        $('#teacher-hidden-subjects-inputs input[value="' + subjectId + '"]').remove();
    });

    $('#teachersTable tbody').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('user_action.php', {
            action: 'get_user_details',
            user_id: id
        }, function(response) {
            if (response.status === 'success') {
                var d = response.data;
                $('#userForm')[0].reset();
                $('#teacher-selected-subjects-container, #teacher-hidden-subjects-inputs')
                    .empty();

                $('#userModalLabel').text('Edit Teacher: ' + d.name);
                $('#user_id').val(d.id);
                $('input[name="name"]').val(d.name);
                $('input[name="email"]').val(d.email);
                $('input[name="phone"]').val(d.phone);
                $('select[name="status"]').val(d.status);
                $('input[name="password"]').prop('required', false);

                if (d.teacher_details) {
                    var td = d.teacher_details;
                    $('input[name="teacher_reg_code"]').val(td.teacher_reg_code);
                    $('input[name="hourly_rate"]').val(td.hourly_rate);
                    $('input[name="experience_years"]').val(td.experience_years);
                    $('input[name="qualifications"]').val(td.qualifications);
                    $('textarea[name="bio"]').val(td.bio);
                    $('textarea[name="address"]').val(td.address);
                }
                if (d.teacher_subjects) {
                    d.teacher_subjects.forEach(function(subjectId) {
                        const subject = allSubjects.find(s => s.id == subjectId);
                        if (subject) addTeacherSubject(subject.id, subject.name);
                    });
                }
                $('#userModal').modal('show');
            }
        }, 'json');
    });

    $('#teachersTable tbody').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete the teacher's user account and profile.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('user_action.php', {
                    action: 'delete',
                    user_id: id
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Deleted!', response.message, 'success');
                        table.ajax.reload();
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        $.post('user_action.php', $(this).serialize(), function(response) {
            if (response.status === 'success') {
                $('#userModal').modal('hide');
                Swal.fire('Success!', response.message, 'success');
                table.ajax.reload();
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json');
    });
});
</script>