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

// Modules come from includes/nav_menu.php (shared with the sidebar), already filtered by role.
$moduleGroups = quidei_nav_groups($userRole);
$moduleCount = array_sum(array_map(static fn($g) => count($g['modules']), $moduleGroups));

$h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<style>
/* ---------- Hero ---------- */
.dash-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.35rem 1.25rem;
    margin-bottom: 1.5rem;
    color: #fff;
    background: linear-gradient(125deg, #064e3b 0%, #047857 55%, #0f766e 100%);
    border-radius: 1.25rem;
    box-shadow: 0 18px 38px -12px rgba(6, 78, 59, .35);
}

.dash-hero::before {
    content: "";
    position: absolute;
    width: 22rem;
    height: 22rem;
    top: -13rem;
    right: -6rem;
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 50%;
    box-shadow: 0 0 0 2rem rgba(255, 255, 255, .035), 0 0 0 4rem rgba(255, 255, 255, .025);
    pointer-events: none;
}

.dash-hero>* {
    position: relative;
    z-index: 1;
}

.dash-hero-art {
    position: absolute;
    z-index: 0;
    right: .5rem;
    bottom: -1.5rem;
    color: rgba(255, 255, 255, .1);
    font-size: 7rem;
    line-height: 1;
    pointer-events: none;
}

.dash-date {
    margin-bottom: .35rem;
    color: rgba(255, 255, 255, .7);
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.dash-greeting {
    margin: 0;
    font-size: clamp(1.4rem, 5.5vw, 2.4rem);
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -.04em;
    overflow-wrap: anywhere;
}

.dash-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    margin-top: .75rem;
}

.dash-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    min-height: 30px;
    padding: .3rem .75rem;
    color: #ecfdf5;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .22);
    border-radius: 999px;
    font-size: .75rem;
    font-weight: 700;
}

.dash-search {
    position: relative;
    width: 100%;
}

.dash-search i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}

.dash-search input {
    width: 100%;
    height: 48px;
    padding: 0 1rem 0 2.6rem;
    color: #0f172a;
    background: #fff;
    border: 0;
    border-radius: 14px;
    box-shadow: 0 10px 24px -10px rgba(0, 0, 0, .35);
    font-size: .95rem;
}

.dash-search input:focus {
    outline: 3px solid rgba(110, 231, 183, .7);
    outline-offset: 2px;
}

@media (min-width: 768px) {
    .dash-hero {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 2rem 2.25rem;
        margin-bottom: 2rem;
        border-radius: 1.5rem;
    }

    .dash-search {
        max-width: 340px;
    }

    .dash-hero-art {
        right: 2rem;
        bottom: -2.5rem;
        font-size: 11rem;
    }
}

/* ---------- Groups ---------- */
.module-group {
    margin-bottom: 1.75rem;
}

.module-group-header {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: .85rem;
}

.module-group-header::after {
    content: "";
    height: 1px;
    flex: 1;
    background: #dbe5ed;
}

.module-group-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    color: var(--emerald-700);
    background: #d1fae5;
    border-radius: 8px;
    font-size: .85rem;
}

.module-group-title {
    margin: 0;
    color: var(--slate-800);
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
}

.module-group-count {
    color: var(--slate-500);
    font-size: .72rem;
    font-weight: 700;
}

/* Mobile first: 2 columns, then as many ~200px columns as fit. */
.module-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}

@media (min-width: 576px) {
    .module-grid {
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        gap: 1rem;
    }
}

/* ---------- Cards ---------- */
.module-card {
    --icon-gradient: linear-gradient(135deg, #10b981, #059669);
    --hover-bg: #10b981;
    position: relative;
    isolation: isolate;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    height: 100%;
    min-height: 150px;
    padding: 1rem;
    color: inherit;
    background: #fff;
    border: 1px solid #e8eef5;
    border-radius: 1.1rem;
    box-shadow: 0 6px 16px rgba(15, 23, 42, .045);
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}

.module-card::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: var(--hover-bg);
    opacity: 0;
    transition: opacity .2s ease;
}

.module-card:hover::after,
.module-card:focus-visible::after {
    opacity: .05;
}

.module-card:focus-visible {
    outline: 3px solid rgba(16, 185, 129, .45);
    outline-offset: 3px;
}

@media (hover: hover) {
    .module-card:hover {
        transform: translateY(-4px);
        border-color: #a7f3d0;
        box-shadow: 0 16px 28px -8px rgba(15, 23, 42, .14);
    }
}

.card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 46px;
    height: 46px;
    margin-bottom: .85rem;
    color: #fff;
    background: var(--icon-gradient);
    border-radius: .9rem;
    box-shadow: 0 7px 14px rgba(15, 23, 42, .12);
    font-size: 1.3rem;
}

.card-title {
    margin: 0 0 .3rem;
    color: var(--slate-800);
    font-size: .92rem;
    font-weight: 800;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.card-description {
    display: -webkit-box;
    margin: 0;
    overflow: hidden;
    color: var(--slate-500);
    font-size: .74rem;
    line-height: 1.4;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.card-go {
    position: absolute;
    top: 1rem;
    right: 1rem;
    color: #cbd5e1;
    font-size: 1rem;
    transition: color .2s ease, transform .2s ease;
}

.module-card:hover .card-go,
.module-card:focus-visible .card-go {
    color: var(--emerald-600);
    transform: translateX(3px);
}

@media (min-width: 576px) {
    .module-card {
        min-height: 172px;
        padding: 1.25rem;
    }

    .card-icon {
        width: 52px;
        height: 52px;
        margin-bottom: 1rem;
        font-size: 1.45rem;
    }

    .card-title {
        font-size: 1rem;
    }

    .card-description {
        font-size: .8rem;
    }
}

/* Card colour themes */
.card-enquiries { --icon-gradient: linear-gradient(135deg, #10b981, #059669); --hover-bg: #10b981; }
.card-sales { --icon-gradient: linear-gradient(135deg, #3b82f6, #2563eb); --hover-bg: #3b82f6; }
.card-demos { --icon-gradient: linear-gradient(135deg, #8b5cf6, #7c3aed); --hover-bg: #8b5cf6; }
.card-all-demos { --icon-gradient: linear-gradient(135deg, #f59e0b, #d97706); --hover-bg: #f59e0b; }
.card-payouts { --icon-gradient: linear-gradient(135deg, #ec4899, #db2777); --hover-bg: #ec4899; }
.card-coord-payouts { --icon-gradient: linear-gradient(135deg, #06b6d4, #0891b2); --hover-bg: #06b6d4; }
.card-admissions { --icon-gradient: linear-gradient(135deg, #10b981, #34d399); --hover-bg: #10b981; }
.card-mentor { --icon-gradient: linear-gradient(135deg, #6366f1, #4f46e5); --hover-bg: #6366f1; }
.card-reports { --icon-gradient: linear-gradient(135deg, #4f46e5, #9333ea); --hover-bg: #4f46e5; }
.card-users { --icon-gradient: linear-gradient(135deg, #64748b, #475569); --hover-bg: #64748b; }
.card-subjects { --icon-gradient: linear-gradient(135deg, #f43f5e, #e11d48); --hover-bg: #f43f5e; }
.card-teacher-payouts { --icon-gradient: linear-gradient(135deg, #6366f1, #a855f7); --hover-bg: #6366f1; }
.card-quidei-payout { --icon-gradient: linear-gradient(135deg, #0f172a, #334155); --hover-bg: #1e293b; }
.card-expense-audit { --icon-gradient: linear-gradient(135deg, #0f172a, #334155); --hover-bg: #1e293b; }
.card-mentors-config { --icon-gradient: linear-gradient(135deg, #6366f1, #4f46e5); --hover-bg: #6366f1; }
.card-monitoring { --icon-gradient: linear-gradient(135deg, #1e293b, #334155); --hover-bg: #1e293b; }

.module-card[hidden],
.module-group[hidden] {
    display: none !important;
}

.dash-empty {
    padding: 2.5rem 1rem;
    color: var(--slate-500);
    background: #fff;
    border: 1px dashed #cbd5e1;
    border-radius: 1rem;
    text-align: center;
}

@media (prefers-reduced-motion: reduce) {

    .module-card,
    .module-card::after,
    .card-go {
        transition: none;
    }
}
</style>

<section class="dash-hero">
    <div>
        <div class="dash-date"><?= $h(date('l, d F Y')) ?></div>
        <h1 class="dash-greeting"><?php echo $greeting; ?>, <?php echo $userName; ?> <?php echo $greetingIcon; ?></h1>
        <div class="dash-meta">
            <span class="dash-chip"><i class="bi bi-person-badge" aria-hidden="true"></i><?= $h($roleLabel) ?></span>
            <span class="dash-chip"><i class="bi bi-grid" aria-hidden="true"></i><?= (int) $moduleCount ?>
                module<?= $moduleCount === 1 ? '' : 's' ?></span>
        </div>
    </div>
    <?php if ($moduleCount > 4): ?>
    <div class="dash-search">
        <label for="dashModuleSearch" class="visually-hidden">Find a module</label>
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="dashModuleSearch" placeholder="Find a module…" autocomplete="off">
    </div>
    <?php endif; ?>
    <div class="dash-hero-art" aria-hidden="true"><i class="bi bi-grid-1x2-fill"></i></div>
</section>

<?php if (!$moduleGroups): ?>
<div class="dash-empty">
    <i class="bi bi-inbox fs-2 d-block mb-2" aria-hidden="true"></i>
    No modules are assigned to your role yet. Please contact the administrator.
</div>
<?php endif; ?>

<?php foreach ($moduleGroups as $group): ?>
<section class="module-group" aria-label="<?= $h($group['title']) ?>">
    <div class="module-group-header">
        <span class="module-group-icon"><i class="bi <?= $h($group['icon']) ?>" aria-hidden="true"></i></span>
        <h2 class="module-group-title"><?= $h($group['title']) ?></h2>
        <span class="module-group-count"><?= count($group['modules']) ?></span>
    </div>
    <div class="module-grid">
        <?php foreach ($group['modules'] as $module): ?>
        <a href="<?= $h($module['href']) ?>"
            class="module-card<?= !empty($module['class']) ? ' ' . $h($module['class']) : '' ?>"
            data-search="<?= $h(strtolower($module['title'] . ' ' . $module['description'] . ' ' . $group['title'])) ?>"
            <?php if (!empty($module['style'])): ?> style="<?= $h($module['style']) ?>" <?php endif; ?>>
            <i class="bi bi-arrow-right card-go" aria-hidden="true"></i>
            <div class="card-icon" <?php if (!empty($module['iconStyle'])): ?>
                style="<?= $h($module['iconStyle']) ?>" <?php endif; ?>>
                <i class="bi <?= $h($module['icon']) ?>" aria-hidden="true"></i>
            </div>
            <h3 class="card-title"><?= $h($module['title']) ?></h3>
            <p class="card-description"><?= $h($module['description']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<div id="dashNoMatch" class="dash-empty" hidden>
    <i class="bi bi-search fs-3 d-block mb-2" aria-hidden="true"></i>
    No modules match your search.
</div>

<?php include 'includes/footer.php'; ?>

<script>
(() => {
    const input = document.getElementById('dashModuleSearch');
    if (!input) return;
    const groups = document.querySelectorAll('.module-group');
    const empty = document.getElementById('dashNoMatch');
    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let shown = 0;
        groups.forEach(group => {
            let groupShown = 0;
            group.querySelectorAll('.module-card').forEach(card => {
                const match = q === '' || card.dataset.search.includes(q);
                card.hidden = !match;
                if (match) groupShown++;
            });
            group.hidden = groupShown === 0;
            shown += groupShown;
        });
        empty.hidden = shown > 0;
    });
})();
</script>
