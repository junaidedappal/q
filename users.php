<?php
include 'includes/header.php';
require 'includes/db.php';

// Authorization Check
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
header("location: dashboard.php");
exit;
}
?>
<style>
body {
    background-color: #f4f6fb;
}

.users-page {
    max-width: 1480px;
    margin: 0 auto;
    padding: 1rem 0.75rem 2.5rem;
    color: #172033;
}

.users-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.25rem;
    color: #fff;
    border-radius: 1.25rem;
    background:
        radial-gradient(circle at 90% 0%, rgba(255, 255, 255, 0.2), transparent 32%),
        linear-gradient(125deg, #312e81 0%, #4f46e5 52%, #7c3aed 100%);
    box-shadow: 0 16px 34px rgba(79, 70, 229, 0.2);
}

.users-hero-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.users-hero-icon {
    display: grid;
    flex: 0 0 3.25rem;
    width: 3.25rem;
    height: 3.25rem;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.14);
    font-size: 1.5rem;
}

.users-hero-eyebrow {
    margin-bottom: 0.2rem;
    color: rgba(255, 255, 255, 0.76);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.users-hero h1 {
    margin: 0;
    font-size: clamp(1.35rem, 4vw, 1.9rem);
    font-weight: 750;
    letter-spacing: -0.035em;
}

.users-hero p {
    margin: 0.3rem 0 0;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.88rem;
}

.users-add-button {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 2.8rem;
    padding: 0.65rem 1rem;
    border: 0;
    border-radius: 0.85rem;
    color: #3730a3;
    background: #fff;
    font-weight: 700;
    box-shadow: 0 5px 14px rgba(30, 27, 75, 0.16);
}

.users-add-button:hover,
.users-add-button:focus {
    color: #312e81;
    background: #eef2ff;
}

.users-panel {
    margin-top: 1rem;
    overflow: hidden;
    border: 1px solid #e7eaf2;
    border-radius: 1.15rem;
    background: #fff;
    box-shadow: 0 10px 30px rgba(27, 39, 69, 0.06);
}

.users-toolbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    padding: 1.2rem 1.25rem;
    border-bottom: 1px solid #edf0f5;
}

.users-toolbar h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 750;
    letter-spacing: -0.02em;
}

.users-toolbar p {
    margin: 0.25rem 0 0;
    color: #778197;
    font-size: 0.82rem;
}

.users-filters {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) minmax(180px, 0.7fr);
    gap: 0.7rem;
    width: min(100%, 540px);
}

.users-search,
.users-role-filter {
    min-height: 2.7rem;
    border: 1px solid #e2e7f0;
    border-radius: 0.8rem;
    background-color: #f9fafc;
    color: #263248;
}

.users-search:focus,
.users-role-filter:focus {
    border-color: #818cf8;
    background-color: #fff;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.12);
}

.users-search-icon {
    display: grid;
    width: 2.75rem;
    place-items: center;
    border: 1px solid #e2e7f0;
    border-right: 0;
    border-radius: 0.8rem 0 0 0.8rem;
    background: #f9fafc;
    color: #7c879b;
}

.users-search {
    border-left: 0;
    border-radius: 0 0.8rem 0.8rem 0;
}

.users-table-shell {
    padding: 0 1rem 0.6rem;
}

.users-table {
    margin: 0 !important;
}

.users-table thead th {
    padding: 0.85rem 0.75rem;
    border-bottom: 1px solid #e9edf4;
    color: #7c879b;
    font-size: 0.68rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    white-space: nowrap;
}

.users-table tbody td {
    padding: 0.9rem 0.75rem;
    border-bottom: 1px solid #f0f2f6;
    color: #344056;
    vertical-align: middle;
}

.users-table tbody tr:last-child td {
    border-bottom: 0;
}

.users-table tbody tr:hover td {
    background: #fafbff;
}

.users-table .btn {
    min-width: 2.25rem;
    min-height: 2.25rem;
    border-radius: 0.7rem;
}

.users-table .badge {
    padding: 0.42rem 0.65rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.users-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem 1rem;
    color: #7c879b;
    font-size: 0.82rem;
}

.users-pagination .pagination {
    margin: 0;
}

.users-pagination .page-link {
    margin: 0 0.12rem;
    border: 0;
    border-radius: 0.6rem !important;
    color: #4f46e5;
}

.users-pagination .page-item.active .page-link {
    color: #fff;
    background: #4f46e5;
}

.modal-content.glass-card {
    overflow: hidden;
    border: 1px solid #e7eaf2;
    border-radius: 1.15rem;
    background: #fff;
    box-shadow: 0 20px 55px rgba(20, 24, 48, 0.2);
}

.modal-header-gradient {
    border: 0;
    background: linear-gradient(125deg, #4338ca, #7c3aed);
}

.modal-header-gradient-alt {
    border: 0;
    background: linear-gradient(125deg, #312e81, #6366f1);
}

.modal-body h6 {
    margin-bottom: 0.8rem;
    color: #475569 !important;
    font-size: 0.75rem;
    font-weight: 750;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.modal-body .form-label {
    margin-bottom: 0.4rem;
    color: #39445a;
    font-size: 0.82rem;
    font-weight: 650;
}

.modal-body .form-control,
.modal-body .form-select {
    min-height: 2.75rem;
    border-color: #dfe4ee;
    border-radius: 0.75rem;
}

.modal-body .form-control:focus,
.modal-body .form-select:focus {
    border-color: #818cf8;
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.12);
}

@media (min-width: 768px) {
    .users-page {
        padding: 1.5rem 1.25rem 3rem;
    }

    .users-hero {
        padding: 1.6rem 1.8rem;
        border-radius: 1.4rem;
    }
}

@media (max-width: 767.98px) {
    .users-hero {
        align-items: stretch;
        flex-direction: column;
        padding: 1.15rem;
        border-radius: 1.05rem;
    }

    .users-hero-content {
        align-items: flex-start;
    }

    .users-hero-icon {
        flex-basis: 2.8rem;
        width: 2.8rem;
        height: 2.8rem;
        border-radius: 0.85rem;
        font-size: 1.25rem;
    }

    .users-hero p {
        max-width: 26rem;
        font-size: 0.8rem;
    }

    .users-add-button {
        width: 100%;
    }

    .users-toolbar {
        align-items: stretch;
        flex-direction: column;
        gap: 0.9rem;
        padding: 1rem;
    }

    .users-filters {
        grid-template-columns: 1fr;
        width: 100%;
    }

    .users-table-shell {
        padding: 0.75rem;
    }

    .users-table-shell .table-responsive {
        overflow: visible;
    }

    #usersTable,
    #usersTable tbody {
        display: block;
        width: 100% !important;
    }

    #usersTable thead {
        display: none;
    }

    #usersTable tbody {
        display: grid;
        gap: 0.75rem;
    }

    #usersTable tbody tr {
        display: grid;
        grid-template-columns: 2.7rem repeat(2, minmax(0, 1fr));
        gap: 0.25rem 0.85rem;
        padding: 0.8rem 0.9rem;
        border: 1px solid #e8ebf3;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 4px 12px rgba(27, 39, 69, 0.04);
    }

    #usersTable tbody tr:hover {
        border-color: #c7d2fe;
        box-shadow: 0 8px 18px rgba(79, 70, 229, 0.08);
    }

    #usersTable tbody td {
        display: block;
        min-width: 0;
        padding: 0.35rem 0;
        overflow-wrap: anywhere;
        border: 0;
        background: transparent !important;
        font-size: 0.83rem;
    }

    #usersTable tbody td:nth-child(1) {
        display: grid;
        grid-column: 1;
        grid-row: 1;
        width: 2.2rem;
        height: 2.2rem;
        align-self: center;
        place-items: center;
        padding: 0;
        border-radius: 50%;
        color: #4338ca;
        background: #eef2ff !important;
        font-size: 0.75rem;
        font-weight: 750;
    }

    #usersTable tbody td.dataTables_empty {
        display: block;
        grid-column: 1 / -1;
        padding: 1.5rem 0.5rem;
        color: #778197;
        text-align: center;
    }

    #usersTable tbody td:nth-child(2) {
        grid-column: 2 / -1;
        grid-row: 1;
        color: #172033;
        font-size: 0.98rem;
        font-weight: 750;
    }

    #usersTable tbody td:nth-child(3) {
        grid-column: 1 / -1;
        grid-row: 2;
        padding-top: 0;
        color: #778197;
        font-size: 0.78rem;
    }

    #usersTable tbody td:nth-child(4),
    #usersTable tbody td:nth-child(5),
    #usersTable tbody td:nth-child(6) {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    #usersTable tbody td:nth-child(4)::before {
        content: "Role";
    }

    #usersTable tbody td:nth-child(4) {
        grid-column: 1 / 3;
        grid-row: 3;
    }

    #usersTable tbody td:nth-child(5)::before {
        content: "Status";
    }

    #usersTable tbody td:nth-child(5) {
        grid-column: 3;
        grid-row: 3;
    }

    #usersTable tbody td:nth-child(6)::before {
        content: "Payout";
    }

    #usersTable tbody td:nth-child(6) {
        grid-column: 1 / 3;
        grid-row: 4;
    }

    #usersTable tbody td:nth-child(4)::before,
    #usersTable tbody td:nth-child(5)::before,
    #usersTable tbody td:nth-child(6)::before {
        color: #8b95a8;
        font-size: 0.62rem;
        font-weight: 750;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    #usersTable tbody td:nth-child(6) .form-check {
        justify-content: flex-start !important;
        margin-left: auto;
    }

    #usersTable tbody td:nth-child(7) {
        display: flex;
        grid-column: 3;
        grid-row: 4;
        justify-content: flex-end;
        padding-top: 0.55rem;
    }

    .users-pagination {
        align-items: stretch;
        flex-direction: column;
        padding: 0.25rem 0 0;
        text-align: center;
    }

    .users-pagination .pagination {
        justify-content: center;
    }

    .modal-dialog {
        margin: 0.65rem;
    }

    .modal-footer > .btn {
        flex: 1 1 0;
    }
}
</style>

<main class="users-page">
    <header class="users-hero">
        <div class="users-hero-content">
            <div class="users-hero-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="users-hero-eyebrow">Team administration</div>
                <h1>User Management</h1>
                <p>Manage account access, roles, and payout permissions.</p>
            </div>
        </div>
        <button class="users-add-button" id="addUserBtn" type="button">
            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
            <span>Add New User</span>
        </button>
    </header>

    <section class="users-panel" aria-labelledby="usersPanelTitle">
        <div class="users-toolbar">
            <div>
                <h2 id="usersPanelTitle">All System Users</h2>
                <p>Search accounts or filter the list by role.</p>
            </div>
            <div class="users-filters">
                <div class="input-group">
                    <span class="users-search-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input id="userSearch" class="form-control users-search" type="search"
                        placeholder="Search name or email..." aria-label="Search users">
                </div>
                <select id="roleFilter" class="form-select users-role-filter" aria-label="Filter users by role">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="sales">Sales</option>
                    <option value="demoAdmin">Demo Admin</option>
                    <option value="demoCordinator">Demo Cordinator</option>
                    <option value="mentor">Mentor</option>
                    <option value="hrmanager">HR MANAGER</option>
                    <option value="accounts">Accounts</option>
                    <option value="supervisor">Super Visor</option>
                    <option value="qualitycontroller">Quality Controller</option>
                </select>
            </div>
        </div>

        <div class="users-table-shell">
            <div class="table-responsive">
                <table id="usersTable" class="table users-table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th scope="col">S.No.</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-center">Payout Edit</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
</main>

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
                            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control"
                                    name="name" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Email Address</label><input type="email" class="form-control"
                                    name="email" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Phone Number</label><input type="text" class="form-control"
                                    name="phone"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control"
                                    name="password" placeholder="Leave blank to keep current"><small
                                    class="form-text text-muted">Required for new users.</small></div>
                        </div>
                    </div>
                    <h6 class="text-muted mt-3">System Access</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Role</label><select name="role" id="role_select"
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
                                    <option value="qualitycontroller">Quality Controller</option>
                                </select></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select></div>
                        </div>
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
                "orderable": false,
                "searchable": false,
                "render": function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            {
                "data": "name"
            },
            {
                "data": "email"
            },
            {
                "data": "role"
            },
            {
                "data": "status_badge"
            },
            {
                "data": "payout_permission",
                "orderable": false,
                "className": "text-center"
            },
            {
                "data": "actions",
                "orderable": false
            }
        ],
        "responsive": false,
        "dom": 'rt<"users-pagination"ip>',
        "language": {
            "emptyTable": "No user accounts found.",
            "zeroRecords": "No users match your search or filters."
        },
        "order": [],
        "columnDefs": [{
            "targets": 3,
            "data": "role_badge",
            "render": function(data, type, row) {
                return type === 'display' ? row.role_badge : row.role;
            }
        }]
    });

    $('#roleFilter').on('change', function() {
        var selectedRole = $(this).val();
        table.column(3).search(selectedRole ? '^' + selectedRole + '$' : '', true, false).draw();
    });

    $('#userSearch').on('input', function() {
        table.search(this.value).draw();
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

    $('#addUserBtn').on('click', function() {
        $('#userForm')[0].reset();
        $('#userModalLabel').text('Add New User');
        $('#action').val('add');
        $('input[name="password"]').prop('required', true).attr('placeholder', 'Password (Required)');
        $('#userModal').modal('show');
    });

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

            let modalBodyHtml = `
                <h5 class="mb-3">General Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><div class="info-label">Full Name</div><div class="info-value">${d.name}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Email Address</div><div class="info-value">${d.email}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Phone</div><div class="info-value">${d.phone || 'N/A'}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Role</div><div class="info-value">${d.role_badge}</div></div>
                    <div class="col-md-6 mb-3"><div class="info-label">Status</div><div class="info-value">${d.status_badge}</div></div>
                </div>`;

            $('#viewModalBody').html(modalBodyHtml);
            $('#viewModal').modal('show');
        }, 'json');
    });


    $('#usersTable tbody').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('user_action.php', {
            action: 'get_user_details',
            user_id: id
        }, function(response) {
            if (response.status !== 'success' || !response.data) {
                Swal.fire('Error', response.message || 'Could not fetch user details.', 'error');
                return;
            }
            var d = response.data;
            $('#userForm')[0].reset();

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