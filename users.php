<?php
include 'includes/header.php';
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
header("location: dashboard.php");
exit;
}
$subjects_result = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC");
?>
<style>
/* Stylish CSS for the User Management Page */
body {
    background-color: #f0f2f5;
}

.page-header-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.stylish-table td,
.stylish-table th {
    vertical-align: middle;
}

.modal-header-gradient {
    background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
    border-bottom: none;
}

.modal-header-gradient-alt {
    background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
    border-bottom: none;
}

.btn-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
}

.btn-gradient:hover {
    color: white;
    opacity: 0.9;
}

.btn-purple {
    background-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5a3e8d;
    color: white;
}

.info-label {
    font-weight: 600;
    color: #adb5bd;
    font-size: 0.85rem;
    text-transform: uppercase;
}

.info-value {
    font-size: 1.1rem;
    color: #212529;
}

#teacher_fields {
    border-top: 1px solid #dee2e6;
    padding-top: 1rem;
    margin-top: 1rem;
}

.subject-pill {
    background-color: #198754;
    /* Green for teachers */
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

@media (max-width: 768px) {
    .card-header {
        align-items: stretch !important;
        flex-direction: column;
        gap: 0.75rem;
    }

    .card-header>.d-flex {
        flex-wrap: wrap;
    }
}
</style>

<!-- Stylish Page Header -->
<div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-people-fill display-4 me-3"></i>
        <div>
            <h2 class="fw-bold mb-0">User Management</h2>
            <p class="mb-0">Control access and roles for all application users.</p>
        </div>
    </div>
</div>
<div class="card glass-card">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-4">
        <h5 class="card-title mb-0 text-dark">All System Users</h5>

        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light border-0"><i class="bi bi-funnel-fill"></i></span>
            <select id="roleFilter" class="form-select form-select-sm border-0">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option value="sales">Sales</option>
                <option value="demoAdmin">Demo Admin</option>
                <option value="demoCordinator">Demo Cordinator</option>
                <option value="mentor">Mentor</option>
                <option value="hrmanager">HR MANAGER</option>
                <option value="accounts">Accounts</option>
                <option value="supervisor">Super Visor</option>
            </select>
        </div>
        <button class="btn btn-primary btn-gradient shadow-sm flex-shrink-0" id="addUserBtn">
            <i class="bi bi-person-plus-fill me-1"></i>Add New User
        </button>
    </div>

</div>
<div class="card-body">
    <div class="table-responsive">
        <table id="usersTable" class="table table-hover stylish-table" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-center">Payout Edit</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white" id="userModalLabel">Add New User</h5><button type="button"
                    class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="action"><input type="hidden" name="user_id" id="user_id">
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
                                    name="password" placeholder="Leave blank to keep current"><small
                                    class="form-text text-muted">Required for new users.</small></div>
                        </div>
                    </div>
                    <h6 class="text-muted mt-3">System Access</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3"><label>Role</label><select name="role" id="role_select"
                                    class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="sales">Sales</option>
                                    <option value="demoAdmin">Demo Admin</option>
                                    <option value="demoCordinator">Demo Cordinator</option>
                                    <option value="mentor">Mentor</option>
                                    <option value="hrmanager">HR MANAGER</option>
                                    <option value="accounts">Accounts</option>
                                    <option value="supervisor">Super Visor</option>
                                </select></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label>Status</label><select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select></div>
                        </div>
                    </div>
                    <div id="teacher_fields" style="display: none;">
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
                                    <?php $subjects_result->data_seek(0); while($row = $subjects_result->fetch_assoc()) { echo "<option value='{$row['id']}'>".htmlspecialchars($row['name'])."</option>"; } ?>
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
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button><button type="submit"
                        class="btn btn-primary btn-gradient" id="saveBtn">Save User</button></div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header modal-header-gradient-alt">
                <h5 class="modal-title text-white">User Details</h5><button type="button"
                    class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalBody"></div>
            <div class="modal-footer border-0"><button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#usersTable').DataTable({
        "ajax": {
            "url": "user_action.php",
            "type": "GET" // Explicitly set GET for datatables
        },
        "columns": [{
                "data": "id",
                "responsivePriority": 5
            },
            {
                "data": "name",
                "responsivePriority": 1
            },
            {
                "data": "email",
                "responsivePriority": 4
            },
            {
                "data": "role",
                "responsivePriority": 3
            },
            {
                "data": "status_badge",
                "responsivePriority": 6
            },
            {
                "data": "payout_permission",
                "orderable": false,
                "className": "text-center",
                "responsivePriority": 2
            },
            {
                "data": "actions",
                "orderable": false,
                "responsivePriority": 1
            }
        ],
        "responsive": true,
        "order": [
            [0, 'desc']
        ],
        "columnDefs": [{
            "targets": 3,
            "data": "role_badge",
            "render": function(data, type, row) {
                return row.role_badge;
            }
        }]
    });

    $('#roleFilter').on('change', function() {
        var selectedRole = $(this).val();
        table.column(3).search(selectedRole ? '^' + selectedRole + '$' : '', true, false).draw();
    });

    $('#usersTable tbody').on('change', '.payout-permission-toggle', function() {
        const toggle = this;
        const allowed = toggle.checked ? 1 : 0;

        $.post('user_action.php', {
            action: 'toggle_teacher_payout_permission',
            user_id: $(toggle).data('id'),
            can_edit_teacher_payout: allowed
        }, function(response) {
            if (response.status !== 'success') {
                toggle.checked = !toggle.checked;
                Swal.fire('Error', response.message, 'error');
            }
        }, 'json').fail(function() {
            toggle.checked = !toggle.checked;
            Swal.fire('Error', 'Could not update teacher payout permission.', 'error');
        });
    });

    var allSubjects = <?php echo json_encode($subjects_result->fetch_all(MYSQLI_ASSOC)); ?>;

    function addTeacherSubject(subjectId, subjectText) {
        if ($('#teacher-hidden-subjects-inputs input[value="' + subjectId + '"]').length > 0) return;
        const pillHtml =
            `<span class="subject-pill" data-id="${subjectId}">${subjectText} <button type="button" class="remove-subject-btn">&times;</button></span>`;
        $('#teacher-selected-subjects-container').append(pillHtml);
        const inputHtml = `<input type="hidden" name="teacher_subjects[]" value="${subjectId}">`;
        $('#teacher-hidden-subjects-inputs').append(inputHtml);
    }

    $('#role_select').on('change', function() {
        if ($(this).val() === 'teacher') {
            $('#teacher_fields').slideDown();
        } else {
            $('#teacher_fields').slideUp();
        }
    });

    $('#addUserBtn').on('click', function() {
        $('#userForm')[0].reset();
        $('#userModalLabel').text('Add New User');
        $('#action').val('add');
        $('input[name="password"]').prop('required', true).attr('placeholder', 'Password (Required)');
        $('#teacher_fields').hide();
        $('#teacher-selected-subjects-container, #teacher-hidden-subjects-inputs').empty();
        $('#userModal').modal('show');
    });

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

    // --- MODIFICATION START: Updated 'View' button logic ---
    $('#usersTable tbody').on('click', '.btn-view', function() {
        var id = $(this).data('id');
        $.get('user_action.php', {
            action: 'get_user_details',
            user_id: id
        }, function(response) {
            if (response.status !== 'success' || !response.data) {
                Swal.fire('Error', 'Could not fetch user details.', 'error');
                return;
            }
            var d = response.data;
            var td = d.teacher_details;
            var teacherSubjects = d.teacher_subjects_details;

            // Using template literals for cleaner HTML
            let modalBodyHtml = `
                <h5 class="mb-3">General Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><div class="info-label">Full Name</div><div class="info-value">${d.name}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Email Address</div><div class="info-value">${d.email}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Phone</div><div class="info-value">${d.phone || 'N/A'}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Role</div><div class="info-value">${d.role_badge}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Status</div><div class="info-value">${d.status_badge}</div></div>
                </div>`;

            // If user is a teacher, append their details
            if (d.role === 'teacher' && td) {
                modalBodyHtml += `
                    <hr class="my-4">
                    <h5 class="mb-3">Teacher Profile</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3"><div class="info-label">Registration Code</div><div class="info-value">${td.teacher_reg_code || 'N/A'}</div></div>
                        <div class="col-md-6 mb-3"><div class="info-label">Hourly Rate</div><div class="info-value">$${parseFloat(td.hourly_rate || 0).toFixed(2)}</div></div>
                        <div class="col-md-6 mb-3"><div class="info-label">Experience</div><div class="info-value">${td.experience_years || '0'} Years</div></div>
                        <div class="col-md-6 mb-3"><div class="info-label">Qualifications</div><div class="info-value">${td.qualifications || 'N/A'}</div></div>
                        <div class="col-12 mb-3"><div class="info-label">Bio</div><p class="mb-0">${td.bio || 'Not provided.'}</p></div>
                        <div class="col-12 mb-3"><div class="info-label">Address</div><p class="mb-0">${td.address || 'Not provided.'}</p></div>
                    </div>`;

                if (teacherSubjects && teacherSubjects.length > 0) {
                    modalBodyHtml += `
                        <div class="info-label mt-2">Subjects Taught</div>
                        <div class="d-flex flex-wrap gap-2 mt-1">`;
                    teacherSubjects.forEach(function(subject) {
                        modalBodyHtml +=
                            `<span class="badge bg-success p-2">${subject.name}</span>`;
                    });
                    modalBodyHtml += `</div>`;
                }
            }

            $('#viewModalBody').html(modalBodyHtml);
            $('#viewModal').modal('show');
        }, 'json');
    });
    // --- MODIFICATION END ---


    $('#usersTable tbody').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('user_action.php', {
            action: 'get_user_details',
            user_id: id
        }, function(response) {
            var d = response.data;
            $('#userForm')[0].reset();
            $('#teacher-selected-subjects-container, #teacher-hidden-subjects-inputs').empty();

            $('#userModalLabel').text('Edit User: ' + d.name);
            $('#action').val('edit');
            $('#user_id').val(d.id);

            $('input[name="name"]').val(d.name);
            $('input[name="email"]').val(d.email);
            $('input[name="phone"]').val(d.phone);
            $('select[name="role"]').val(d.role);
            $('select[name="status"]').val(d.status);

            $('input[name="password"]').prop('required', false).attr('placeholder',
                'Leave blank to keep current');

            if (d.role === 'teacher') {
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
                        if (subject) {
                            addTeacherSubject(subject.id, subject.name);
                        }
                    });
                }
                $('#teacher_fields').show();
            } else {
                $('#teacher_fields').hide();
            }
            $('#userModal').modal('show');
        }, 'json');
    });

    $('#usersTable tbody').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "This action will permanently delete the user.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete user!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('user_action.php', {
                    action: 'delete',
                    user_id: id
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Deleted!', response.message, 'success');
                        table.ajax.reload(null, false);
                    } else {
                        Swal.fire('Error!', response.message, 'error');
                    }
                }, 'json');
            }
        });
    });

    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#saveBtn');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'
        );

        $.post('user_action.php', $(this).serialize(), function(response) {
            if (response.status === 'success') {
                $('#userModal').modal('hide');
                Swal.fire('Success!', response.message, 'success');
                table.ajax.reload(null, false);
            } else {
                Swal.fire('Error!', response.message, 'error');
            }
        }, 'json').always(function() {
            $btn.prop('disabled', false).text('Save User');
        });
    });
});
</script>