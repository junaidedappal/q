<?php
include 'includes/header.php';
require 'includes/db.php';

// 1. Role-based access control
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    header('location: dashboard.php'); exit;
}

$userRole = $_SESSION['role'];

// 2. Fetch Dynamic Data for Dropdowns
$syllabuses_result = $conn->query('SELECT id, name FROM syllabus ORDER BY name ASC');
$grades_db_result = $conn->query('SELECT * FROM grades ORDER BY sort_order ASC');

$grouped_grades = [];
while ($row = $grades_db_result->fetch_assoc()) {
    $grouped_grades[$row['category']][] = $row;
}
?>

<style>
/* Modern CSS Variable Palette */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --surface-card: #ffffff;
    --text-main: #2d3748;
    --text-muted: #718096;
}

body {
    background-color: #f8fafc;
    font-family: 'Inter', sans-serif;
    color: var(--text-main);
}

.hero-banner {
    background: var(--primary-gradient);
    border-radius: 0 0 2rem 2rem;
    padding: 3rem 1.5rem;
    color: white;
    margin-bottom: -2rem;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
}

.master-card {
    background: var(--surface-card);
    border-radius: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

#studentsTableMaster thead th {
    background: #f8fafc;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    padding: 1.25rem 1rem;
}

.section-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: #4a5568;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 1rem;
}

.form-group-box {
    background: #f8fafc;
    padding: 1.25rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    border: 1px solid #edf2f7;
}

.form-label-custom {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 5px;
    display: block;
}

@media (max-width: 768px) {
    .column-hide-mobile {
        display: none !important;
    }
}
</style>

<div class="hero-banner text-center text-md-start">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold mb-1"><i class="bi bi-person-badge-fill me-2"></i>Student Records</h1>
                <p class="opacity-75 mb-0">Manage information for all admitted students.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3">
                <a href="dashboard.php"
                    class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm">Dashboard</a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid mt-5 px-3 pb-5 ">
    <div class="card master-card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="studentsTableMaster" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Identity</th>
                            <th class="column-hide-mobile">Academic Info</th>
                            <th>Contact & Mentor</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade " id="editStudentModalMaster" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0 p-4" style="border-radius: 20px 20px 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Student Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStudentFormMaster">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="section-label"><i class="bi bi-person-fill"></i> Personal & Class Details</div>
                    <div class="form-group-box">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Full Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Class / Grade *</label>
                                <select class="form-select" name="class" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($grouped_grades as $category => $grades): ?>
                                    <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                        <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo htmlspecialchars($grade['grade_name']); ?>">
                                            <?php echo htmlspecialchars($grade['grade_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-label"><i class="bi bi-book-half"></i> Curriculum & Language</div>
                    <div class="form-group-box">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Syllabus *</label>
                                <select class="form-select" name="syllabus" required>
                                    <?php 
                                    $syllabuses_result->data_seek(0);
                                    while ($row = $syllabuses_result->fetch_assoc()) {
                                        echo "<option value='".htmlspecialchars($row['name'])."'>".htmlspecialchars($row['name'])."</option>";
                                    } 
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Language Preference</label>
                                <select class="form-select" name="language">
                                    <option value="ENGLISH">ENGLISH</option>
                                    <option value="MALAYALAM">MALAYALAM</option>
                                    <option value="MIXED">MIXED</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-label"><i class="bi bi-telephone-fill"></i> Contact Numbers</div>
                    <div class="form-group-box">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Student Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Parent Phone</label>
                                <input type="text" class="form-control" name="parent_phone">
                            </div>
                        </div>
                    </div>

                    <div class="section-label"><i class="bi bi-chat-dots"></i> Internal Remarks</div>
                    <textarea class="form-control" name="remarks" rows="3" placeholder="Notes..."></textarea>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-lg fw-bold">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    var table = $('#studentsTableMaster').DataTable({
        "ajax": "finalized_student_action.php?action=list",
        "columns": [{
                "data": null,
                "render": (d, t, r, m) =>
                    `<span class='text-muted fw-bold'>${m.row + m.settings._iDisplayStart + 1}</span>`
            },
            {
                "data": "name",
                "render": (d, t, r) =>
                    `<div><span class='fw-bold text-dark d-block'>${d}</span><span class='badge bg-light text-primary border' style='font-size:0.6rem'>ID: #${r.id}</span></div>`
            },
            {
                "data": "class",
                "className": "column-hide-mobile",
                "render": (d, t, r) =>
                    `<div class='small fw-bold'>${d}</div><div class='small text-muted'>${r.syllabus || ''}</div>`
            },
            {
                "data": "phone",
                "render": (d, t, r) =>
                    `<div class='small'>S: ${d}</div><div class='small text-muted'>M: ${r.mentor_name || 'N/A'}</div>`
            },
            {
                "data": "id",
                "className": "text-end pe-4",
                "render": (id) =>
                    `<button class="btn btn-sm btn-outline-primary rounded-pill btn-edit-student px-3" data-id="${id}"><i class="bi bi-pencil-square"></i></button>`
            }
        ]
    });

    $('#studentsTableMaster').on('click', '.btn-edit-student', function() {
        const id = $(this).data('id');
        $.get('finalized_student_action.php', {
            action: 'get',
            id: id
        }, function(res) {
            if (res.status === 'success') {
                const d = res.data;
                $('#edit_id').val(d.id);
                $('input[name="name"]').val(d.name);
                $('select[name="class"]').val(d.class); // Dynamic Select
                $('select[name="syllabus"]').val(d.syllabus); // Dynamic Select
                $('select[name="language"]').val(d.language);
                $('input[name="phone"]').val(d.phone);
                $('input[name="parent_phone"]').val(d.parent_phone);
                $('textarea[name="remarks"]').val(d.remarks);
                $('#editStudentModalMaster').modal('show');
            }
        });
    });

    $('#editStudentFormMaster').on('submit', function(e) {
        e.preventDefault();
        $.post('finalized_student_action.php', $(this).serialize(), function(res) {
            if (res.status === 'success') {
                $('#editStudentModalMaster').modal('hide');
                table.ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        }, 'json');
    });
});
</script>