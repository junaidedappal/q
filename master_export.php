<?php
ob_start();
include 'includes/header.php';

if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'admin') {
    ob_end_clean();
    header('Location: dashboard.php');
    exit;
}

$years = [];
$yearQueryFailed = false;
try {
    require 'includes/db.php';
} catch (mysqli_sql_exception $exception) {
    error_log('Master export database connection failed: ' . $exception->getMessage());
    $yearQueryFailed = true;
}

if (!$yearQueryFailed) {
    try {
        $yearResult = $conn->query(
            'SELECT DISTINCT YEAR(created_at) AS year
             FROM students
             WHERE created_at IS NOT NULL AND YEAR(created_at) BETWEEN 1000 AND 9998
             ORDER BY year DESC'
        );
    } catch (mysqli_sql_exception $exception) {
        error_log('Master export year lookup failed: ' . $exception->getMessage());
        $yearQueryFailed = true;
    }
}

if (!$yearQueryFailed && $yearResult === false) {
    error_log('Master export year lookup failed: ' . $conn->error);
    $yearQueryFailed = true;
} elseif (!$yearQueryFailed) {
    while ($row = $yearResult->fetch_assoc()) {
        if ($row['year'] !== null) {
            $years[] = (int) $row['year'];
        }
    }
    $yearResult->free();
}

ob_end_flush();
?>

<style>
.export-page {
    max-width: 1040px;
}

.export-hero {
    background: linear-gradient(120deg, #064e3b 0%, #059669 55%, #34d399 100%);
    border-radius: 1.25rem;
    overflow: hidden;
    position: relative;
}

.export-hero::after {
    background: radial-gradient(circle, rgba(255, 255, 255, .18) 0, transparent 68%);
    content: "";
    height: 22rem;
    position: absolute;
    right: -7rem;
    top: -11rem;
    width: 22rem;
}

.export-card {
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
}

.export-icon {
    align-items: center;
    background: #ecfdf5;
    border-radius: 1rem;
    color: #047857;
    display: inline-flex;
    height: 3.5rem;
    justify-content: center;
    width: 3.5rem;
}

.export-step {
    align-items: flex-start;
    display: flex;
    gap: .75rem;
}

.export-step-number {
    align-items: center;
    background: #f1f5f9;
    border-radius: 50%;
    color: #334155;
    display: inline-flex;
    flex: 0 0 1.75rem;
    font-size: .8rem;
    font-weight: 700;
    height: 1.75rem;
    justify-content: center;
}
</style>

<main class="container export-page py-4 py-lg-5">
    <section class="export-hero text-white p-4 p-lg-5 mb-4 shadow-sm">
        <div class="position-relative" style="z-index: 1;">
            <span class="badge rounded-pill bg-white bg-opacity-25 text-white mb-3">ADMIN REPORT</span>
            <h1 class="h2 fw-bold mb-2">Master academic export</h1>
            <p class="mb-0 text-white-50">Create a spreadsheet-ready report with every schedule for each student.</p>
        </div>
    </section>

    <section class="card export-card shadow-sm">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="export-icon"><i class="bi bi-file-earmark-spreadsheet fs-3" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="h5 fw-bold mb-1">Your report, ready to download</h2>
                            <p class="text-muted mb-0 small">CSV format · Opens in Excel and Google Sheets</p>
                        </div>
                    </div>

                    <div class="d-grid gap-3">
                        <div class="export-step">
                            <span class="export-step-number">1</span>
                            <p class="mb-0 text-secondary">Choose the year the student records were created.</p>
                        </div>
                        <div class="export-step">
                            <span class="export-step-number">2</span>
                            <p class="mb-0 text-secondary">Each schedule has its own row; student details appear only on the first row for that student.</p>
                        </div>
                    </div>

                    <div class="alert alert-light border mt-4 mb-0 small" role="note">
                        The report has a fixed set of columns no matter how many schedules exist. Student details appear once, alongside the student's first schedule.
                    </div>
                </div>

                <div class="col-lg-6">
                    <form action="master_export_action.php" method="POST">
                        <label for="export-year" class="form-label fw-semibold">Academic year</label>
                        <select
                            id="export-year"
                            name="year"
                            class="form-select form-select-lg mb-3"
                            required
                            <?= $yearQueryFailed || count($years) === 0 ? 'disabled' : '' ?>
                        >
                            <option value="" selected disabled>Select a year</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>–<?= htmlspecialchars((string) ($year + 1), ENT_QUOTES, 'UTF-8') ?>
                                    (records from <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($yearQueryFailed): ?>
                            <div class="alert alert-danger" role="alert">
                                Academic years could not be loaded. Refresh the page or contact support if the problem continues.
                            </div>
                        <?php elseif (count($years) === 0): ?>
                            <div class="alert alert-info" role="status">
                                There are no student records available to export yet.
                            </div>
                        <?php endif; ?>

                        <button
                            type="submit"
                            class="btn btn-success btn-lg w-100 fw-semibold"
                            <?= $yearQueryFailed || count($years) === 0 ? 'disabled' : '' ?>
                        >
                            <i class="bi bi-download me-2" aria-hidden="true"></i>Download CSV report
                        </button>
                        <p class="form-text text-center mt-3 mb-0">Student enquiries are not included in this report.</p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
