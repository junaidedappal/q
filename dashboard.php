<?php
ob_start();
include 'includes/header.php';
require 'includes/db.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('location: login.php');
    exit;
}

// --- 1. GREETING AND USER INFO ---
$userRole = $_SESSION['role'];
$userName = htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8');
$roleLabel = $userRole === 'qualitycontroller' ? 'Quality Controller' : ucfirst($userRole);
$currentHour = date('G');

if ($currentHour < 12) {
    $greeting = 'Good Morning'; $greetingIcon = '🌅';
} elseif ($currentHour < 18) {
    $greeting = 'Good Afternoon'; $greetingIcon = '☀️';
} else {
    $greeting = 'Good Evening'; $greetingIcon = '🌙';
}

$moduleGroups = [

'Admin & Finance' => [
       
        [
            'href' => 'finalized_students.php',
            'roles' => ['sales', 'admin'],
            'class' => 'card-admissions',
            'icon' => 'bi-mortarboard-fill',
            'title' => 'Student Data',
            'description' => 'Student Master Data Management',
        ],
        [
            'href' => 'admissions.php',
            'roles' => ['admin'],
            'class' => 'card-admissions',
            'icon' => 'bi-check-circle-fill',
            'title' => 'Admissions',
            'description' => 'Student Schedule fee status mentor assigning dashboard',
        ],
        
        // [
        //     'href' => 'master_export.php',
        //     'roles' => ['admin'],
        //     'class' => '',
        //     'icon' => 'bi-file-earmark-spreadsheet-fill',
        //     'title' => 'Master Export',
        //     'description' => 'Full 3-Month Schedule Report',
        //     'style' => '--icon-gradient: linear-gradient(135deg, #059669, #10b981); --hover-bg: #10b981;',
        // ],
        [
            'href' => 'master_view.php',
            'roles' => ['admin'],
            'class' => '',
            'icon' => 'bi-table',
            'title' => 'Admin Dashboard Main View',
            'description' => 'All data sheet + Excel download',
            'style' => '--icon-gradient: linear-gradient(135deg, #0ea5e9, #6366f1); --hover-bg: #6366f1;',
        ],
    ],
    'Mentor' => [
        [
            'href' => 'my_students.php',
            'roles' => ['mentor', 'admin'],
            'class' => 'card-mentor',
            'icon' => 'bi-mortarboard-fill',
            'title' => 'Mentor Dashboard',
            'description' => 'Managed by Mentors',
        ],
        [
            'href' => 'mentors.php',
            'roles' => ['admin'],
            'class' => 'card-mentors-config',
            'icon' => 'bi-person-gear',
            'title' => 'Mentor Salary  Management',
            'description' => 'Manage Mentors',
        ],
        [
            'href' => 'mentor_activity.php',
            'roles' => ['admin', 'qualitycontroller', 'supervisor'],
            'class' => 'card-monitoring',
            'icon' => 'bi-calendar2-check-fill',
            'title' => 'Mentor Activity',
            'description' => $userRole === 'supervisor'
                ? 'Sessions for your assigned mentors'
                : 'All mentor sessions and attendance',
        ],
    ],
    
    
    'Demo Department' => [

        [
            'href' => 'enquiries.php',
            'roles' => ['sales', 'admin'],
            'class' => 'card-enquiries',
            'icon' => 'bi-person-plus-fill',
            'title' => 'Enquiries',
            'description' => 'Lead Management',
        ],
        [
            'href' => 'sales_dashboard.php',
            'roles' => ['admin'],
            'class' => 'card-sales',
            'icon' => 'bi-graph-up-arrow',
            'title' => 'Sales Analytics',
            'description' => 'Performance Tracking',
        ],
        [
            'href' => 'demos.php',
            'roles' => ['demoAdmin', 'demoCordinator', 'admin'],
            'class' => 'card-demos',
            'icon' => 'bi-play-btn-fill',
            'title' => 'Manage Demo Classes',
            'description' => 'Live Session',
        ],
        [
            'href' => 'all_demos.php',
            'roles' => ['admin', 'demoAdmin'],
            'class' => 'card-all-demos',
            'icon' => 'bi-archive-fill',
            'title' => 'Demo Overview',
            'description' => 'History',
        ],
         [
            'href' => 'demo_payouts.php',
            'roles' => ['admin', 'demoAdmin', 'demoCordinator'],
            'class' => 'card-payouts',
            'icon' => 'bi-wallet-fill',
            'title' => 'Demo Class Payouts',
            'description' => 'Payout for Teachers',
        ],
        [
            'href' => 'coordinator_payouts.php',
            'roles' => ['admin', 'demoAdmin'],
            'class' => 'card-coord-payouts',
            'icon' => 'bi-people-fill',
            'title' => 'Demo Coordinates Payouts',
            'description' => 'Payout for Cordinators',
        ],
        [
            'href' => 'demo_report.php',
            'roles' => ['admin', 'demoAdmin'],
            'class' => 'card-reports',
            'icon' => 'bi-graph-up-arrow',
            'title' => 'Monthly Analytics',
            'description' => 'Demo Performance Report',
        ],
    ],
    'Supervisor' => [
        [
            'href' => 'supervisor_monitoring.php',
            'roles' => ['admin', 'supervisor'],
            'class' => 'card-monitoring',
            'icon' => 'bi-activity',
            'title' => 'Supervisor Module',
            'description' => 'Analysis Mentor Performance',
        ],
        
    ],
    'User Management' => [
        [
            'href' => 'users.php',
            'roles' => ['admin'],
            'class' => 'card-users',
            'icon' => 'bi-person-badge-fill',
            'title' => 'User Module',
            'description' => 'Staff Management',
        ],
        [
            'href' => 'subjects.php',
            'roles' => ['admin'],
            'class' => 'card-subjects',
            'icon' => 'bi-bookmarks-fill',
            'title' => 'Subjects Module',
            'description' => 'Manage Subjects',
        ],
        [
            'href' => 'grades.php',
            'roles' => ['admin'],
            'class' => 'card-enquiries',
            'icon' => 'bi-layers-fill',
            'title' => 'Grades/Classes Module',
            'description' => 'Pricing & Classes',
            'iconStyle' => 'background: linear-gradient(135deg, #6366f1, #a855f7);',
        ],
    ],
    
    
    
    'Accounts' => [      
        
        [
            'href' => 'quidei_payouts.php',
            'roles' => ['mentor', 'admin'],
            'class' => 'card-quidei-payout',
            'icon' => 'bi-shield-check',
            'title' => 'Salary Payouts',
            'description' => 'Monthly Payout',
        ],
        [
            'href' => 'student_expense_audit.php',
            'roles' => ['admin', 'accountant'],
            'class' => 'card-expense-audit',
            'icon' => 'bi-pie-chart-fill',
            'title' => 'Expense Audit',
            'description' => 'Student Cost Analysis',
        ],
    ],
    'HR Department' => [
        [
            'href' => 'user_teachers.php',
            'roles' => ['admin', 'hrmanager'],
            'class' => 'card-teacher-payouts',
            'icon' => 'bi-mortarboard-fill',
            'title' => 'Teacher Module',
            'description' => 'Faculty & Profiles',
            'iconStyle' => 'background: linear-gradient(135deg, #198754, #20c997);',
        ],
    ],
];

?>

<style>
:root {
    --emerald-500: #10b981;
    --emerald-600: #059669;
    --slate-800: #1e293b;
    --slate-500: #64748b;
}

/* Hero Section */
.dashboard-hero {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 1.5rem;
    padding: 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.04);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
    position: relative;
    overflow: hidden;
}

/* Stylish Card Grid */
.module-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 1.25rem;
    padding: 1.75rem 1rem;
    height: 100%;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    position: relative;
    overflow: hidden;
}

/* Fixed Filter Styles - No Overlap */
.filter-box {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px 12px;
    display: flex;
    align-items: center;
    min-width: 180px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.filter-box i {
    color: var(--emerald-primary);
    margin-right: 8px;
    font-size: 1.1rem;
}

.filter-box select {
    border: none;
    font-weight: 600;
    color: var(--slate-800);
    background: transparent;
    cursor: pointer;
    width: 100%;
    outline: none;
}

/* Background mesh gradient effect on hover */
.module-card:hover {
    border-color: transparent;
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08);
}

.module-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--hover-bg);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: 0;
}

.module-card:hover::after {
    opacity: 0.05;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin-bottom: 1.25rem;
    background: var(--icon-gradient);
    color: white;
    box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 1;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--slate-800);
    margin-bottom: 0.5rem;
    position: relative;
    z-index: 1;
}

.card-description {
    font-size: 0.85rem;
    color: var(--slate-500);
    line-height: 1.4;
    position: relative;
    z-index: 1;
}

/* Gradient Definitions for specific cards */
.card-enquiries {
    --icon-gradient: linear-gradient(135deg, #10b981, #059669);
    --hover-bg: #10b981;
}

.card-sales {
    --icon-gradient: linear-gradient(135deg, #3b82f6, #2563eb);
    --hover-bg: #3b82f6;
}

.card-demos {
    --icon-gradient: linear-gradient(135deg, #8b5cf6, #7c3aed);
    --hover-bg: #8b5cf6;
}

.card-all-demos {
    --icon-gradient: linear-gradient(135deg, #f59e0b, #d97706);
    --hover-bg: #f59e0b;
}

.card-payouts {
    --icon-gradient: linear-gradient(135deg, #ec4899, #db2777);
    --hover-bg: #ec4899;
}

.card-coord-payouts {
    --icon-gradient: linear-gradient(135deg, #06b6d4, #0891b2);
    --hover-bg: #06b6d4;
}

.card-admissions {
    --icon-gradient: linear-gradient(135deg, #10b981, #34d399);
    --hover-bg: #10b981;
}

.card-mentor {
    --icon-gradient: linear-gradient(135deg, #6366f1, #4f46e5);
    --hover-bg: #6366f1;
}

.card-reports {
    --icon-gradient: linear-gradient(135deg, #4f46e5, #9333ea);
    /* Indigo to Purple */
    --hover-bg: #4f46e5;
}

.card-users {
    --icon-gradient: linear-gradient(135deg, #64748b, #475569);
    --hover-bg: #64748b;
}

.card-subjects {
    --icon-gradient: linear-gradient(135deg, #f43f5e, #e11d48);
    --hover-bg: #f43f5e;
}

.card-mentor-payouts {
    --icon-gradient: linear-gradient(135deg, #f43f5e, #881337);
    /* Deep Rose to Burgundy */
    --hover-bg: #f43f5e;
}

.card-teacher-payouts {
    --icon-gradient: linear-gradient(135deg, #6366f1, #a855f7);
    --hover-bg: #6366f1;
}

/* Card for Quidie Payouts */
.card-quidei-payout {
    --icon-gradient: linear-gradient(135deg, #0f172a, #334155);
    --hover-bg: #1e293b;
}

.card-expense-audit {
    --icon-gradient: linear-gradient(135deg, #0f172a, #334155);
    /* Deep Slate/Navy theme for Finance */
    --hover-bg: #1e293b;
}

.card-mentors-config {
    --icon-gradient: linear-gradient(135deg, #6366f1, #4f46e5);
    --hover-bg: #6366f1;
}

.card-monitoring {
    --icon-gradient: linear-gradient(135deg, #1e293b, #334155);
    --hover-bg: #1e293b;
}

/* Stats styling from previous step remains the same... */
.stat-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 1rem;
    padding: 1.5rem;
    text-align: center;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--slate-800);
}

body {
    background-color: #f4f7fb;
}

.dashboard-shell {
    width: 100%;
    max-width: 1680px;
    margin: 0 auto;
    padding-inline: clamp(.75rem, 2.5vw, 2rem);
}

.dashboard-hero {
    min-height: 168px;
    padding: clamp(1.25rem, 4vw, 2.5rem);
    margin-bottom: clamp(1.5rem, 4vw, 2.5rem);
    color: #fff;
    background: linear-gradient(125deg, #064e3b 0%, #047857 55%, #0f766e 100%);
    border: 0;
    box-shadow: 0 18px 38px rgba(6, 78, 59, .18);
}

.dashboard-hero::before {
    content: "";
    position: absolute;
    width: 22rem;
    height: 22rem;
    top: -13rem;
    right: -5rem;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 50%;
    box-shadow: 0 0 0 2rem rgba(255, 255, 255, .035), 0 0 0 4rem rgba(255, 255, 255, .025);
    pointer-events: none;
}

.dashboard-hero>div:not(.dashboard-hero-art) {
    position: relative;
    z-index: 1;
}

.hero-greeting {
    max-width: 100%;
    color: #fff;
    font-size: clamp(1.55rem, 5.5vw, 2.65rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -.045em;
    overflow-wrap: anywhere;
}

.hero-subtitle {
    margin-top: 0;
    color: rgba(255, 255, 255, .78);
    font-size: .85rem;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    min-height: 30px;
    padding: .35rem .7rem;
    color: #ecfdf5;
    background: rgba(255, 255, 255, .15);
    border: 1px solid rgba(255, 255, 255, .24);
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 800;
}

.dashboard-hero-art {
    position: absolute;
    z-index: 0;
    right: clamp(1rem, 5vw, 4rem);
    bottom: -2.5rem;
    color: rgba(255, 255, 255, .12);
    font-size: clamp(7rem, 17vw, 12rem);
    line-height: 1;
    pointer-events: none;
}

.section-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 1rem;
    margin: 0 0 1rem;
}

.section-kicker {
    margin: 0 0 .2rem;
    color: var(--emerald-600);
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.section-header h2 {
    margin: 0;
    color: var(--slate-800);
    font-size: clamp(1.15rem, 3vw, 1.5rem);
    font-weight: 800;
    letter-spacing: -.03em;
}

.section-description {
    margin: 0;
    color: var(--slate-500);
    font-size: .82rem;
}

.module-group {
    margin-bottom: 1.75rem;
}

.module-group-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    margin-bottom: .85rem;
}

.module-group-header::after {
    content: "";
    height: 1px;
    flex: 1;
    background: #dbe5ed;
}

.module-group-title {
    margin: 0;
    color: var(--emerald-600);
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.module-card {
    isolation: isolate;
    min-height: 164px;
    padding: 1rem .65rem;
    border-color: #e8eef5;
    border-radius: 1.15rem;
    box-shadow: 0 7px 18px rgba(15, 23, 42, .045);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}

.module-card:focus-visible {
    outline: 3px solid rgba(16, 185, 129, .45);
    outline-offset: 3px;
}

.module-card::after {
    z-index: -1;
    transition: opacity .2s ease;
}

.module-card:hover::after,
.module-card:focus-visible::after {
    opacity: .045;
}

@media (hover: hover) {
    .module-card:hover {
        transform: translateY(-4px);
        border-color: #a7f3d0;
        box-shadow: 0 15px 28px rgba(15, 23, 42, .09);
    }
}

.card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex: 0 0 auto;
    margin-bottom: .8rem;
    background: var(--icon-gradient);
    border-radius: .95rem;
    box-shadow: 0 7px 14px rgba(15, 23, 42, .12);
    font-size: 1.35rem;
}

.card-title {
    margin-bottom: .35rem;
    font-size: .94rem;
    font-weight: 800;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.card-description {
    margin: 0;
    font-size: .73rem;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.dashboard-signout {
    min-height: 44px;
}

@media (min-width: 576px) {
    .module-card {
        min-height: 188px;
        padding: 1.25rem .9rem;
    }

    .card-icon {
        width: 54px;
        height: 54px;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .card-title {
        font-size: 1rem;
    }

    .card-description {
        font-size: .8rem;
    }
}

@media (max-width: 575.98px) {
    .dashboard-shell {
        padding-inline: .75rem;
    }

    .dashboard-hero {
        min-height: 150px;
        border-radius: 1.2rem;
    }

    .dashboard-hero-art {
        right: .35rem;
        bottom: -1.3rem;
    }

    .section-header {
        align-items: start;
    }

    .section-description {
        display: none;
    }

    .dashboard-signout {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {

    .module-card,
    .module-card::after {
        transition: none;
    }
}
</style>

<main class="dashboard-shell container-fluid py-3 py-lg-4">
    <div class="dashboard-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="hero-greeting"><?php echo $greeting; ?>, <?php echo $userName; ?> <?php echo $greetingIcon; ?>
            </div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="role-badge"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="hero-subtitle">Welcome back to your workspace</span>
            </div>
        </div>
        <div class="dashboard-hero-art" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></div>
    </div>

    <div class="section-header">
        <div>
            <p class="section-kicker">Your workspace</p>
            <h2>Quick Access</h2>
        </div>
        <p class="section-description">Choose a module to continue your work.</p>
    </div>

    <?php foreach ($moduleGroups as $groupTitle => $modules): ?>
    <?php
    $visibleModules = [];
    foreach ($modules as $module) {
        if (in_array($userRole, $module['roles'], true)) {
            $visibleModules[] = $module;
        }
    }
    if (!$visibleModules) {
        continue;
    }
    ?>
    <section class="module-group" aria-label="<?php echo htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="module-group-header">
            <h3 class="module-group-title"><?php echo htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
        </div>
        <div class="row g-3">
            <?php foreach ($visibleModules as $module): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?php echo htmlspecialchars($module['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="module-card<?php echo $module['class'] ? ' ' . htmlspecialchars($module['class'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                    <?php if (!empty($module['style'])): ?>
                    style="<?php echo htmlspecialchars($module['style'], ENT_QUOTES, 'UTF-8'); ?>" <?php endif; ?>>
                    <div class="card-icon" <?php if (!empty($module['iconStyle'])): ?>
                        style="<?php echo htmlspecialchars($module['iconStyle'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php endif; ?>>
                        <i class="bi <?php echo htmlspecialchars($module['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($module['title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    <p class="card-description">
                        <?php echo htmlspecialchars($module['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <div class="text-center pb-5">
        <a href="logout.php" class="dashboard-signout btn btn-outline-danger px-4 rounded-pill fw-bold">
            <i class="bi bi-power me-2"></i>Sign Out
        </a>
    </div>
</main>

<?php include 'includes/footer.php'; ?>