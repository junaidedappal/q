<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';
// Authorization Check: Only 'admin' role can access this page
if (!isset($_SESSION["loggedin"]) || $_SESSION['role'] !== 'admin') {
    header("location: dashboard.php");
    exit;
}
$userRole = $_SESSION['role'];
?>
<style>
/* Reusing the beautiful styles from your other modules */
.page-header-gradient {
    background: linear-gradient(135deg, #007adf 0%, #00ecbc 100%);
}

.glass-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

.stylish-table thead th {
    font-weight: 600;
}

.modal-header-gradient {
    background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
}

.btn-gradient {
    background: linear-gradient(135deg, #007adf 0%, #00ecbc 100%);
    border: none;
    color: white;
}

.btn-gradient:hover {
    color: white;
    opacity: 0.9;
}
</style>

<!-- Stylish Page Header -->
<div class="page-header-gradient text-white p-4 mb-4 rounded-3 shadow-sm">
    <div class="d-flex align-items-center">
        <i class="bi bi-journal-bookmark-fill display-4 me-3"></i>
        <div>
            <h2 class="fw-bold mb-0">Subject Management</h2>
            <p class="mb-0">Add, edit, and manage all academic subjects.</p>
        </div>
    </div>
</div>

<!-- Main Content Card -->
<div class="card glass-card">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-4">
        <h5 class="card-title mb-0 text-dark">All Subjects</h5>
        <button class="btn btn-primary btn-gradient shadow-sm" id="addSubjectBtn">
            <i class="bi bi-plus-circle-fill me-1"></i>Add New Subject
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="subjectsTable" class="table table-hover stylish-table" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header modal-header-gradient">
                <h5 class="modal-title text-white" id="subjectModalLabel">Add New Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="subjectForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="subject_id" id="subject_id">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-bold">Subject Name</label>
                        <input type="text" class="form-control" name="name" id="name" required
                            placeholder="e.g., Physics">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-gradient" id="saveBtn">Save Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#subjectsTable').DataTable({
        "ajax": "subject_action.php",
        "columns": [{
                "data": "id",
                "width": "10%"
            },
            {
                "data": "name"
            },
            {
                "data": "actions",
                "orderable": false,
                "searchable": false,
                "width": "15%"
            }
        ],
        "order": [
            [1, 'asc']
        ] // Order by subject name by default
    });

    // --- EVENT HANDLERS ---

    // 1. Handle "Add New Subject" button click
    $('#addSubjectBtn').on('click', function() {
        $('#subjectForm')[0].reset();
        $('#subjectModalLabel').text('Add New Subject');
        $('#action').val('add');
        $('#name').removeClass('is-invalid'); // Clear previous validation
        $('#subjectModal').modal('show');
    });

    // 2. Handle "Edit" button click (using event delegation for dynamic buttons)
    $('#subjectsTable tbody').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('subject_action.php', {
            action: 'get_single',
            id: id
        }, function(response) {
            if (response.status === 'success' && response.data) {
                var subject = response.data;
                $('#subjectModalLabel').text('Edit Subject');
                $('#action').val('edit');
                $('#subject_id').val(subject.id);
                $('#name').val(subject.name);
                $('#name').removeClass('is-invalid');
                $('#subjectModal').modal('show');
            } else {
                Swal.fire('Error!', 'Could not fetch subject details.', 'error');
            }
        }, 'json');
    });

    // 3. Handle "Delete" button click (using event delegation)
    $('#subjectsTable tbody').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "Deleting a subject cannot be undone and may affect associated records.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('subject_action.php', {
                    action: 'delete',
                    subject_id: id
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Deleted!', response.message, 'success');
                        table.ajax.reload(null,
                            false); // Reload table without resetting pagination
                    }
                }, 'json').fail(function(xhr) {
                    var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr
                        .responseJSON.message : 'An error occurred.';
                    Swal.fire('Error!', errorMsg, 'error');
                });
            }
        });
    });

    // 4. Handle form submission for both Add and Edit
    $('#subjectForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#saveBtn');
        $btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm"></span> Saving...');

        $.post('subject_action.php', $(this).serialize(), function(response) {
            if (response.status === 'success') {
                $('#subjectModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                table.ajax.reload(null, false);
            }
        }, 'json').fail(function(xhr) {
            // This handles the error messages from the backend (e.g., duplicates)
            var errorMsg = 'An unknown error occurred.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            Swal.fire('Error!', errorMsg, 'error');
        }).always(function() {
            $btn.prop('disabled', false).text('Save Subject');
        });
    });
});
</script>