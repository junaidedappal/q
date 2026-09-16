<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    header('location: dashboard.php');
    exit;
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM grades WHERE id = $id");
    header('location: grades.php?msg=deleted');
    exit;
}

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $grade_name = $conn->real_escape_string($_POST['grade_name']);
    $category = $conn->real_escape_string($_POST['category']);
    $fee = (float)$_POST['fee_per_hour'];
    $pay = (float)$_POST['payment_per_hour'];
    $sort = (int)$_POST['sort_order'];

    if ($id > 0) {
        $sql = "UPDATE grades SET grade_name='$grade_name', category='$category', fee_per_hour='$fee', payment_per_hour='$pay', sort_order='$sort' WHERE id=$id";
    } else {
        $sql = "INSERT INTO grades (grade_name, category, fee_per_hour, payment_per_hour, sort_order) VALUES ('$grade_name', '$category', '$fee', '$pay', '$sort')";
    }
    
    if ($conn->query($sql)) {
        header('location: grades.php?msg=success');
        exit;
    }
}

$grades = $conn->query("SELECT * FROM grades ORDER BY sort_order ASC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-slate-800">Manage Grades & Rates</h4>
        <button class="btn btn-primary rounded-pill px-4" onclick="openGradeModal()">
            <i class="bi bi-plus-lg me-2"></i>Add New Grade
        </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive p-3">
            <table class="table align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Sort</th>
                        <th>Grade Name</th>
                        <th>Category</th>
                        <th>Student Fee</th>
                        <th>Teacher Pay</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $grades->fetch_assoc()): ?>
                    <tr>
                        <td class="text-muted small"><?php echo $row['sort_order']; ?></td>
                        <td class="fw-bold"><?php echo $row['grade_name']; ?></td>
                        <td><span class="badge bg-soft-info text-info"><?php echo $row['category']; ?></span></td>
                        <td>₹<?php echo $row['fee_per_hour']; ?></td>
                        <td class="text-success fw-bold">₹<?php echo $row['payment_per_hour']; ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light" onclick='editGrade(<?php echo json_encode($row); ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-danger"
                                onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['grade_name']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <input type="hidden" name="id" id="grade_id" value="0">
                <div class="col-12">
                    <label class="form-label">Grade Name</label>
                    <input type="text" name="grade_name" id="grade_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category" id="category" class="form-select">
                        <option value="Kindergarten">Kindergarten</option>
                        <option value="School">School</option>
                        <option value="Undergraduate">Undergraduate</option>
                        <option value="Postgraduate">Postgraduate</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Student Fee / Hr</label>
                    <input type="number" step="0.01" name="fee_per_hour" id="fee_per_hour" class="form-control"
                        required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teacher Pay / Hr</label>
                    <input type="number" step="0.01" name="payment_per_hour" id="payment_per_hour" class="form-control"
                        required>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-primary w-100 rounded-3">Save Grade Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gradeModal = new bootstrap.Modal(document.getElementById('gradeModal'));

    // --- SUCCESS/DELETE ALERTS ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        Swal.fire({
            icon: 'success',
            title: msg === 'deleted' ? 'Deleted!' : 'Saved!',
            text: msg === 'deleted' ? 'Grade removed successfully.' : 'Grade details updated.',
            timer: 2000,
            showConfirmButton: false
        });
        // Clean URL
        window.history.replaceState({}, document.title, "grades.php");
    }

    // --- DELETE CONFIRMATION ---
    window.confirmDelete = function(id, name) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You are about to delete " + name,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'grades.php?delete=' + id;
            }
        });
    };

    window.openGradeModal = function() {
        document.getElementById('grade_id').value = "0";
        document.getElementById('grade_name').value = "";
        document.getElementById('fee_per_hour').value = "0";
        document.getElementById('payment_per_hour').value = "0";
        document.getElementById('sort_order').value = "0";
        document.getElementById('modalTitle').innerText = "Add New Grade";
        gradeModal.show();
    };

    window.editGrade = function(data) {
        document.getElementById('grade_id').value = data.id;
        document.getElementById('grade_name').value = data.grade_name;
        document.getElementById('category').value = data.category;
        document.getElementById('fee_per_hour').value = data.fee_per_hour;
        document.getElementById('payment_per_hour').value = data.payment_per_hour;
        document.getElementById('sort_order').value = data.sort_order;
        document.getElementById('modalTitle').innerText = "Edit Grade";
        gradeModal.show();
    };
});
</script>

<style>
.bg-soft-info {
    background-color: #e0f2fe;
}

.btn-light {
    border-color: #f1f5f9;
    transition: 0.2s;
}

.btn-light:hover {
    background-color: #f8fafc;
    transform: translateY(-1px);
}
</style>

<?php include 'includes/footer.php'; ?>