<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

$teacher_id = intval($_GET['teacher_id']);
$month = intval($_GET['month']);
$year = intval($_GET['year']);
$monthName = date('F', mktime(0, 0, 0, $month, 10));

// Fetch Teacher Info
$teacher_stmt = $conn->prepare("SELECT u.name, u.phone FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->get_result()->fetch_assoc();

// Detailed Query: Join all relevant tables
$sql = "SELECT 
            sess.date,
            sess.planned_hours,
            sess.teacher_pay,
            (sess.planned_hours * sess.teacher_pay) as subtotal,
            sub.name as subject_name,
            stu.name as student_name,
            sch.name as schedule_title,
            u_m.name as mentor_name
        FROM sessions sess
        JOIN schedules sch ON sess.schedule_id = sch.id
        JOIN students stu ON sch.student_id = stu.id
        JOIN subjects sub ON sess.subject_id = sub.id
        JOIN mentors m ON sch.mentor_id = m.id
        JOIN users u_m ON m.user_id = u_m.id
        WHERE sess.teacher_id = ? 
        AND sess.status = 'done' 
        AND MONTH(sess.date) = ? AND YEAR(sess.date) = ?";

// Security for Mentor: Only show sessions taken under THEIR mentorship
if ($_SESSION['role'] === 'mentor') {
    $sql .= " AND u_m.id = " . intval($_SESSION['id']);
}
$sql .= " ORDER BY u_m.name ASC, sess.date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $teacher_id, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$total_earnings = 0;
$groupedItems = [];

while($row = $result->fetch_assoc()){
    $groupedItems[$row['mentor_name']][] = $row;
    $total_earnings += $row['subtotal'];
}

// --- CSV EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    ob_end_clean();
    $fileName = "Teacher_Payout_Detail_{$teacher['name']}_{$monthName}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $fileName);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    
    fputcsv($output, ['Mentor', 'Date', 'Student', 'Subject', 'Hrs', 'Rate', 'Subtotal'], ",", "\"", "\\");
    
    foreach ($groupedItems as $mName => $rows) {
        foreach ($rows as $r) {
            fputcsv($output, [
                $mName,
                $r['date'],
                $r['student_name'],
                $r['subject_name'],
                $r['planned_hours'],
                $r['teacher_pay'],
                number_format($r['subtotal'], 2, '.', '')
            ], ",", "\"", "\\");
        }
    }
    fputcsv($output, ['TOTAL', '', '', '', '', '', number_format($total_earnings, 2, '.', '')], ",", "\"", "\\");
    fclose($output);
    exit;
}
?>

<style>
body {
    background-color: #f8f9fa;
}

.mentor-group-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
}

.mentor-header {
    background: #eef2ff;
    color: #4338ca;
    padding: 12px 20px;
    font-weight: 700;
    border-bottom: 1px solid #e0e7ff;
}

.badge-soft-primary {
    background: #eef2ff;
    color: #4338ca;
}

/* Mobile Stack Table */
@media (max-width: 768px) {
    .table-responsive-stack thead {
        display: none;
    }

    .table-responsive-stack tr {
        display: block;
        border-bottom: 1px solid #eee;
        padding: 10px 0;
    }

    .table-responsive-stack td {
        display: flex;
        justify-content: space-between;
        text-align: right;
        border: none;
        padding: 5px 15px;
        font-size: 0.85rem;
    }

    .table-responsive-stack td::before {
        content: attr(data-label);
        font-weight: bold;
        color: #6b7280;
        text-transform: uppercase;
        font-size: 0.7rem;
    }
}

@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<div class="container py-4">
    <!-- Header Actions -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 no-print">
        <a href="teacher_payouts.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>"
            class="btn btn-sm btn-light border rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <div class="d-flex gap-2">
            <a href="?<?php echo $_SERVER['QUERY_STRING']; ?>&export=csv"
                class="btn btn-sm btn-outline-success rounded-pill px-3">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-sm btn-dark rounded-pill px-3">
                <i class="bi bi-printer me-1"></i> Print PDF
            </button>
        </div>
    </div>

    <!-- Teacher Summary Card -->
    <div class="card border-0 shadow-sm mb-4 bg-primary text-white p-4 rounded-3">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-uppercase small fw-bold opacity-75">Detailed Payout Report</h6>
                <h2 class="fw-bold mb-0"><?php echo $teacher['name']; ?></h2>
                <p class="mb-0 opacity-75"><?php echo "$monthName $year"; ?> • Phone: <?php echo $teacher['phone']; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0 border-start border-white border-opacity-25">
                <div class="small opacity-75 fw-bold">TOTAL EARNED</div>
                <div class="display-6 fw-bold">₹<?php echo number_format($total_earnings, 2); ?></div>
            </div>
        </div>
    </div>

    <?php if(empty($groupedItems)): ?>
    <div class="alert alert-light text-center py-5 shadow-sm">No payout records found for this period.</div>
    <?php else: ?>
    <?php foreach($groupedItems as $mentorName => $sessions): 
            $mentorSubtotal = array_sum(array_column($sessions, 'subtotal'));
        ?>
    <div class="card mentor-group-card shadow-sm">
        <div class="mentor-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-person-workspace me-2"></i>Mentor: <?php echo $mentorName; ?></span>
            <span class="small">Subtotal: ₹<?php echo number_format($mentorSubtotal, 2); ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-responsive-stack mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 small">Date / Student</th>
                            <th class="small">Subject</th>
                            <th class="text-center small">Hrs</th>
                            <th class="text-center small">Rate</th>
                            <th class="text-end pe-4 small">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sessions as $r): ?>
                        <tr>
                            <td class="ps-4" data-label="Date/Student">
                                <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($r['date'])); ?></div>
                                <div class="small text-muted"><?php echo $r['student_name']; ?></div>
                            </td>
                            <td data-label="Subject">
                                <span class="badge badge-soft-primary px-2"><?php echo $r['subject_name']; ?></span>
                                <div class="text-muted" style="font-size:0.65rem"><?php echo $r['schedule_title']; ?>
                                </div>
                            </td>
                            <td class="text-center fw-bold" data-label="Hours"><?php echo $r['planned_hours']; ?></td>
                            <td class="text-center text-muted" data-label="Rate" style="font-size:0.8rem">
                                ₹<?php echo $r['teacher_pay']; ?>/hr</td>
                            <td class="text-end pe-4 fw-bold text-dark" data-label="Total">
                                ₹<?php echo number_format($r['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="text-center py-4 no-print">
        <p class="text-muted small">Generated on <?php echo date('d M Y, h:i A'); ?></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>