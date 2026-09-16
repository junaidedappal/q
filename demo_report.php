<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'demoAdmin', 'demoCordinator'])) {
    header('location: dashboard.php');
    exit;
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['id'];

// Get Year Filter (Default to current year)
$selectedYear = $_GET['year'] ?? date('Y');

// Base query logic for coordinator restriction
$coord_filter = ($userRole === 'demoCordinator') ? " AND assigned_coordinator_id = $userId" : "";

// Fetch Monthly Stats
$monthly_stats = [];
for ($m = 1; $m <= 12; $m++) {
    $monthName = date('F', mktime(0, 0, 0, $m, 1));
    
    // 1. Enquiries
    $enq = $conn->query("SELECT COUNT(id) as total FROM enquiries WHERE YEAR(created_at) = $selectedYear AND MONTH(created_at) = $m $coord_filter")->fetch_assoc()['total'];
    
    // 2. Scheduled (All Demos)
    $sch = $conn->query("SELECT COUNT(da.id) as total FROM demo_assignments da JOIN enquiries e ON da.enquiry_id = e.id WHERE YEAR(da.scheduled_at) = $selectedYear AND MONTH(da.scheduled_at) = $m $coord_filter")->fetch_assoc()['total'];
    
    // 3. Attendees (Attended only)
    $att = $conn->query("SELECT COUNT(da.id) as total FROM demo_assignments da JOIN enquiries e ON da.enquiry_id = e.id WHERE da.outcome = 'attended' AND YEAR(da.scheduled_at) = $selectedYear AND MONTH(da.scheduled_at) = $m $coord_filter")->fetch_assoc()['total'];
    
    // 4. Admissions
    $adm = $conn->query("SELECT COUNT(id) as total FROM enquiries WHERE status = 'admission_finalized' AND YEAR(created_at) = $selectedYear AND MONTH(created_at) = $m $coord_filter")->fetch_assoc()['total'];

    $monthly_stats[] = [
        'month' => $monthName,
        'enquiries' => $enq,
        'scheduled' => $sch,
        'attendees' => $att,
        'admissions' => $adm,
        'conversion' => ($enq > 0) ? round(($adm / $enq) * 100, 1) : 0
    ];
}
?>

<style>
.report-card {
    background: white;
    border-radius: 1rem;
    border: none;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

.table thead th {
    background-color: #f8f9fa;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.progress-sm {
    height: 6px;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Demo Performance Report</h2>
            <p class="text-muted">Monthly breakdown for the year <?php echo $selectedYear; ?></p>
        </div>
        <form class="d-flex gap-2">
            <select name="year" class="form-select" onchange="this.form.submit()">
                <?php for($y=date('Y'); $y>=2024; $y--) echo "<option value='$y' ".($y==$selectedYear?'selected':'').">$y</option>"; ?>
            </select>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
        </form>
    </div>

    <div class="card report-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Month</th>
                        <th class="text-center">Enquiries</th>
                        <th class="text-center">Scheduled</th>
                        <th class="text-center">Attendees</th>
                        <th class="text-center">Admissions</th>
                        <th class="text-center">Conv. %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($monthly_stats as $row): ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?php echo $row['month']; ?></td>
                        <td class="text-center"><span
                                class="badge bg-light text-dark border"><?php echo $row['enquiries']; ?></span></td>
                        <td class="text-center"><?php echo $row['scheduled']; ?></td>
                        <td class="text-center text-primary fw-bold"><?php echo $row['attendees']; ?></td>
                        <td class="text-center text-success fw-bold"><?php echo $row['admissions']; ?></td>
                        <td class="text-center" style="width: 150px;">
                            <div class="small mb-1"><?php echo $row['conversion']; ?>%</div>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: <?php echo $row['conversion']; ?>%">
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>