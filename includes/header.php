<?php
$session_lifetime = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string) $session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/nav_menu.php';

$currentPage = basename($_SERVER['PHP_SELF']);
// The sidebar layout is shown to logged-in users on every page except login.
$quideiShell = $currentPage !== 'login.php' && ($_SESSION['loggedin'] ?? false) === true;

$qhName = (string) ($_SESSION['name'] ?? '');
$qhRole = (string) ($_SESSION['role'] ?? '');
$qhRoleLabel = $qhRole === 'qualitycontroller' ? 'Quality Controller' : ucfirst($qhRole);
$qhInitial = strtoupper(mb_substr(trim($qhName) !== '' ? trim($qhName) : '?', 0, 1, 'UTF-8'));
$qhH = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$qhGroups = $quideiShell ? quidei_nav_groups($qhRole) : [];
$qhPageTitle = $currentPage === 'dashboard.php' ? 'Dashboard' : '';
$qhPageGroup = '';
foreach ($qhGroups as $qhGroup) {
    foreach ($qhGroup['modules'] as $qhModule) {
        if (quidei_nav_is_active($qhModule, $currentPage)) {
            $qhPageTitle = $qhModule['title'];
            $qhPageGroup = $qhGroup['title'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#064e3b">
    <title><?= $qhPageTitle !== '' ? $qhH($qhPageTitle) . ' · ' : '' ?>QUIDEI Education Management System</title>

    <!-- Apply the saved sidebar state before paint (avoids a flash on large screens). -->
    <script>
    try {
        if (localStorage.getItem('quidei.sidebar') === 'collapsed') {
            document.documentElement.classList.add('sb-collapsed');
        }
    } catch (e) {}
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
    :root {
        --emerald-400: #34d399;
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-700: #047857;
        --emerald-900: #064e3b;
        --slate-50: #f8fafc;
        --slate-100: #f1f5f9;
        --slate-200: #e2e8f0;
        --slate-500: #64748b;
        --slate-800: #1e293b;
        --slate-900: #0f172a;

        --sb-w: 272px;
        --sb-rail: 78px;
        --topbar-h: 64px;
        --app-bg: #f5f7fa;
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
        background-color: var(--app-bg);
        color: var(--slate-900);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    /* ================= APP SHELL ================= */
    .app-main {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 100vh;
    }

    .app-content {
        flex: 1 0 auto;
        width: 100%;
        max-width: 1680px;
        margin: 0 auto;
        padding: 1rem .875rem 2rem;
    }

    @media (min-width: 768px) {
        .app-content {
            padding: 1.5rem 1.5rem 2.5rem;
        }
    }

    @media (min-width: 1400px) {
        .app-content {
            padding: 1.75rem 2rem 3rem;
        }
    }

    /* ================= TOP BAR ================= */
    .app-topbar {
        position: sticky;
        top: 0;
        z-index: 1030;
        display: flex;
        align-items: center;
        gap: .5rem;
        height: var(--topbar-h);
        padding: 0 .75rem;
        background: rgba(255, 255, 255, .92);
        backdrop-filter: saturate(180%) blur(10px);
        -webkit-backdrop-filter: saturate(180%) blur(10px);
        border-bottom: 1px solid var(--slate-200);
        transition: box-shadow .2s ease;
    }

    .app-topbar.is-scrolled {
        box-shadow: 0 6px 18px -8px rgba(15, 23, 42, .18);
    }

    @media (min-width: 768px) {
        .app-topbar {
            padding: 0 1.5rem;
            gap: .75rem;
        }
    }

    .topbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 42px;
        height: 42px;
        padding: 0;
        color: var(--slate-800);
        background: transparent;
        border: 1px solid var(--slate-200);
        border-radius: 12px;
        font-size: 1.25rem;
        transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }

    .topbar-btn:hover,
    .topbar-btn:focus-visible {
        color: var(--emerald-700);
        background: #ecfdf5;
        border-color: #a7f3d0;
    }

    .topbar-btn:focus-visible {
        outline: 3px solid rgba(16, 185, 129, .35);
        outline-offset: 2px;
    }

    .topbar-brand {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        color: var(--slate-900);
        font-weight: 800;
        font-size: 1.1rem;
        letter-spacing: -.02em;
        text-decoration: none;
    }

    .topbar-brand i {
        color: var(--emerald-500);
    }

    .topbar-title {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
    }

    .topbar-title small {
        color: var(--emerald-600);
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .topbar-title span {
        color: var(--slate-900);
        font-size: 1rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (min-width: 768px) {
        .topbar-title span {
            font-size: 1.1rem;
        }
    }

    .user-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        color: #fff;
        background: linear-gradient(135deg, var(--emerald-500), var(--emerald-700));
        border-radius: 11px;
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(16, 185, 129, .25);
    }

    .user-menu-btn {
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        max-width: 240px;
        padding: .25rem .35rem;
        color: var(--slate-900);
        background: transparent;
        border: 1px solid transparent;
        border-radius: 14px;
        text-align: left;
    }

    .user-menu-btn:hover,
    .user-menu-btn[aria-expanded="true"] {
        background: var(--slate-50);
        border-color: var(--slate-200);
    }

    .user-menu-btn .um-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.15;
    }

    .user-menu-btn .um-name {
        font-size: .85rem;
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-menu-btn .um-role {
        color: var(--slate-500);
        font-size: .72rem;
        font-weight: 600;
    }

    .user-dropdown {
        min-width: 230px;
        padding: .5rem;
        border: 1px solid var(--slate-100);
        border-radius: 14px;
        box-shadow: 0 16px 32px -12px rgba(15, 23, 42, .25);
    }

    .user-dropdown .dropdown-item {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .6rem .75rem;
        border-radius: 10px;
        font-weight: 500;
    }

    .user-dropdown .dropdown-item:hover,
    .user-dropdown .dropdown-item:focus {
        color: var(--emerald-700);
        background: #ecfdf5;
    }

    .user-dropdown .dropdown-item.text-danger:hover,
    .user-dropdown .dropdown-item.text-danger:focus {
        color: #b91c1c !important;
        background: #fef2f2;
    }

    .user-dropdown-head {
        padding: .5rem .75rem .75rem;
        margin-bottom: .35rem;
        border-bottom: 1px solid var(--slate-100);
    }

    .role-chip {
        display: inline-block;
        margin-top: .3rem;
        padding: .15rem .55rem;
        color: var(--emerald-700);
        background: rgba(16, 185, 129, .12);
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    /* ================= SIDEBAR ================= */
    /* Below lg it is a Bootstrap offcanvas (slides in from the left). */
    .app-sidebar {
        --bs-offcanvas-width: min(300px, 86vw);
        --bs-offcanvas-bg: #062b23;
        border-right: 0 !important;
    }

    .sb-inner {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
        color: #d1fae5;
        background:
            radial-gradient(120% 60% at 0% 0%, rgba(52, 211, 153, .16) 0, transparent 60%),
            linear-gradient(180deg, #064e3b 0%, #062b23 100%);
    }

    .sb-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        height: var(--topbar-h);
        padding: 0 1rem 0 1.1rem;
        flex: 0 0 auto;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .sb-brand {
        display: flex;
        align-items: center;
        gap: .7rem;
        min-width: 0;
        color: #fff;
        text-decoration: none;
    }

    .sb-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 40px;
        height: 40px;
        color: #064e3b;
        background: linear-gradient(135deg, #6ee7b7, #10b981);
        border-radius: 12px;
        font-size: 1.25rem;
        box-shadow: 0 6px 14px rgba(0, 0, 0, .25);
    }

    .sb-brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.1;
    }

    .sb-brand-text strong {
        font-size: 1.15rem;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .sb-brand-text small {
        color: rgba(209, 250, 229, .6);
        font-size: .68rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .sb-nav {
        flex: 1 1 auto;
        padding: .85rem .7rem 1rem;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, .18) transparent;
    }

    .sb-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sb-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, .18);
        border-radius: 6px;
    }

    .sb-section-label {
        margin: .9rem .75rem .45rem;
        color: rgba(209, 250, 229, .45);
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sb-link,
    .sb-group-btn {
        position: relative;
        display: flex;
        align-items: center;
        gap: .75rem;
        width: 100%;
        min-height: 44px;
        padding: .55rem .75rem;
        color: rgba(236, 253, 245, .82);
        background: transparent;
        border: 0;
        border-radius: 12px;
        font-size: .9rem;
        font-weight: 600;
        text-align: left;
        text-decoration: none;
        white-space: nowrap;
        transition: background-color .15s ease, color .15s ease;
    }

    .sb-link>i,
    .sb-group-btn>i:first-child {
        flex: 0 0 auto;
        width: 1.35rem;
        font-size: 1.1rem;
        text-align: center;
    }

    .sb-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sb-link:hover,
    .sb-group-btn:hover,
    .sb-link:focus-visible,
    .sb-group-btn:focus-visible {
        color: #fff;
        background: rgba(255, 255, 255, .07);
    }

    .sb-link:focus-visible,
    .sb-group-btn:focus-visible {
        outline: 2px solid var(--emerald-400);
        outline-offset: -2px;
    }

    .sb-link.active {
        color: #fff;
        background: linear-gradient(90deg, rgba(16, 185, 129, .35), rgba(16, 185, 129, .12));
        box-shadow: inset 0 0 0 1px rgba(52, 211, 153, .25);
    }

    .sb-link.active::before {
        content: "";
        position: absolute;
        left: -.7rem;
        top: 22%;
        bottom: 22%;
        width: 4px;
        background: var(--emerald-400);
        border-radius: 0 4px 4px 0;
    }

    .sb-group {
        margin-top: .15rem;
    }

    .sb-group-btn.has-active {
        color: #fff;
    }

    .sb-caret {
        margin-left: auto;
        font-size: .75rem !important;
        opacity: .7;
        transition: transform .2s ease;
    }

    .sb-group-btn[aria-expanded="true"] .sb-caret {
        transform: rotate(180deg);
    }

    .sb-sub {
        position: relative;
        margin: .15rem 0 .35rem;
        padding: 0 0 0 1.05rem;
        list-style: none;
    }

    .sb-sub::before {
        content: "";
        position: absolute;
        left: 1.4rem;
        top: .25rem;
        bottom: .25rem;
        width: 1px;
        background: rgba(255, 255, 255, .12);
    }

    .sb-sub .sb-link {
        min-height: 40px;
        padding-left: .9rem;
        font-size: .85rem;
        font-weight: 500;
    }

    .sb-sub .sb-link>i {
        font-size: .95rem;
        opacity: .9;
    }

    .sb-sub .sb-link.active::before {
        left: -1.75rem;
    }

    .sb-foot {
        flex: 0 0 auto;
        padding: .75rem;
        border-top: 1px solid rgba(255, 255, 255, .08);
    }

    .sb-user {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .5rem;
        background: rgba(255, 255, 255, .06);
        border-radius: 14px;
    }

    .sb-user .user-avatar {
        color: #064e3b;
        background: linear-gradient(135deg, #a7f3d0, #34d399);
        box-shadow: none;
    }

    .sb-user-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.2;
    }

    .sb-user-text strong {
        color: #fff;
        font-size: .85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sb-user-text small {
        color: rgba(209, 250, 229, .65);
        font-size: .72rem;
    }

    .sb-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        margin-left: auto;
        color: #fecaca;
        border-radius: 10px;
        font-size: 1.1rem;
        text-decoration: none;
    }

    .sb-logout:hover,
    .sb-logout:focus-visible {
        color: #fff;
        background: rgba(239, 68, 68, .35);
    }

    .sb-close {
        filter: invert(1) grayscale(1) brightness(2);
        opacity: .75;
    }

    /* ---- Large screens: fixed sidebar + optional icon rail ---- */
    @media (min-width: 992px) {
        .app-sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1035;
            width: var(--sb-w);
            transition: width .22s ease;
        }

        .app-main {
            margin-left: var(--sb-w);
            transition: margin-left .22s ease;
        }

        html.sb-collapsed .app-main {
            margin-left: var(--sb-rail);
        }

        /* Rail (collapsed): icons only. */
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) {
            width: var(--sb-rail);
        }

        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-label,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-brand-text,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-caret,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-user-text,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-logout,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-section-label {
            display: none;
        }

        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-head {
            justify-content: center;
            padding: 0;
        }

        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-link,
        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-group-btn {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-user {
            justify-content: center;
            background: transparent;
        }

        /* In rail mode every module icon is listed; group headers become thin dividers. */
        html.sb-collapsed .app-sidebar .sb-group>.collapse {
            display: block !important;
            height: auto !important;
        }

        html.sb-collapsed .app-sidebar .sb-group-btn {
            pointer-events: none;
            min-height: 0;
            margin-top: .55rem;
            padding-top: .2rem;
            padding-bottom: .2rem;
            color: rgba(209, 250, 229, .45);
            font-size: .66rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        html.sb-collapsed .app-sidebar .sb-group-btn>i:first-child,
        html.sb-collapsed .app-sidebar .sb-caret {
            display: none;
        }

        html.sb-collapsed .app-sidebar:not(:hover):not(:focus-within) .sb-group-btn {
            height: 1px;
            padding: 0;
            margin: .6rem .9rem;
            background: rgba(255, 255, 255, .12);
        }

        html.sb-collapsed .app-sidebar .sb-sub {
            padding-left: 0;
        }

        html.sb-collapsed .app-sidebar .sb-sub::before,
        html.sb-collapsed .app-sidebar .sb-link.active::before {
            display: none;
        }

        /* Hovering the rail opens it over the page as a flyout. */
        html.sb-collapsed .app-sidebar:hover,
        html.sb-collapsed .app-sidebar:focus-within {
            box-shadow: 18px 0 40px -18px rgba(0, 0, 0, .45);
        }
    }

    @media (prefers-reduced-motion: reduce) {

        .app-sidebar,
        .app-main,
        .sb-caret,
        .collapsing {
            transition: none !important;
        }
    }

    /* ================= LEGACY / SHARED ================= */
    .btn-gradient-primary {
        background: var(--emerald-500);
        border: none;
        color: white;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .btn-gradient-primary:hover {
        background: var(--emerald-600);
        color: white;
        transform: translateY(-1px);
    }

    /* Keep wide tables scrollable inside cards instead of stretching the page. */
    .app-content .dataTables_wrapper {
        max-width: 100%;
    }

    @media (max-width: 575.98px) {

        .app-content .dataTables_wrapper .dataTables_filter,
        .app-content .dataTables_wrapper .dataTables_length {
            text-align: left !important;
        }

        .app-content .dataTables_wrapper .dataTables_filter input {
            width: 100%;
            margin-left: 0 !important;
        }

        .app-content .dataTables_wrapper .dataTables_filter label {
            width: 100%;
        }
    }
    </style>
</head>

<body>
    <?php if ($quideiShell): ?>
    <div class="app-shell">
        <aside class="app-sidebar offcanvas-lg offcanvas-start" id="appSidebar" tabindex="-1"
            aria-labelledby="appSidebarLabel">
            <div class="sb-inner">
                <div class="sb-head">
                    <a class="sb-brand" href="dashboard.php" title="QUIDEI Dashboard">
                        <span class="sb-logo"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                        <span class="sb-brand-text">
                            <strong id="appSidebarLabel">QUIDEI</strong>
                            <small>Education EMS</small>
                        </span>
                    </a>
                    <button type="button" class="btn-close sb-close d-lg-none" data-bs-dismiss="offcanvas"
                        data-bs-target="#appSidebar" aria-label="Close menu"></button>
                </div>

                <nav class="sb-nav" aria-label="Main menu">
                    <a class="sb-link<?= $currentPage === 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php"
                        title="Dashboard" <?= $currentPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
                        <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span class="sb-label">Dashboard</span>
                    </a>

                    <?php if ($qhGroups): ?>
                    <div class="sb-section-label">Modules</div>
                    <?php endif; ?>

                    <?php
                    // Open the group that contains the current page; users with few groups see them all open.
                    $qhOpenAll = count($qhGroups) <= 2;
                    foreach ($qhGroups as $qhIndex => $qhGroup):
                        $qhGroupActive = false;
                        foreach ($qhGroup['modules'] as $qhModule) {
                            if (quidei_nav_is_active($qhModule, $currentPage)) {
                                $qhGroupActive = true;
                            }
                        }
                        $qhOpen = $qhOpenAll || $qhGroupActive;
                        $qhGroupId = 'sbGroup' . $qhIndex;
                    ?>
                    <div class="sb-group">
                        <button type="button"
                            class="sb-group-btn<?= $qhGroupActive ? ' has-active' : '' ?><?= $qhOpen ? '' : ' collapsed' ?>"
                            data-bs-toggle="collapse" data-bs-target="#<?= $qhGroupId ?>"
                            aria-expanded="<?= $qhOpen ? 'true' : 'false' ?>" aria-controls="<?= $qhGroupId ?>">
                            <i class="bi <?= $qhH($qhGroup['icon']) ?>" aria-hidden="true"></i>
                            <span class="sb-label"><?= $qhH($qhGroup['title']) ?></span>
                            <i class="bi bi-chevron-down sb-caret" aria-hidden="true"></i>
                        </button>
                        <div class="collapse<?= $qhOpen ? ' show' : '' ?>" id="<?= $qhGroupId ?>">
                            <ul class="sb-sub">
                                <?php foreach ($qhGroup['modules'] as $qhModule):
                                    $qhActive = quidei_nav_is_active($qhModule, $currentPage); ?>
                                <li>
                                    <a class="sb-link<?= $qhActive ? ' active' : '' ?>"
                                        href="<?= $qhH($qhModule['href']) ?>" title="<?= $qhH($qhModule['title']) ?>"
                                        <?= $qhActive ? 'aria-current="page"' : '' ?>>
                                        <i class="bi <?= $qhH($qhModule['icon']) ?>" aria-hidden="true"></i>
                                        <span class="sb-label"><?= $qhH($qhModule['title']) ?></span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </nav>

                <div class="sb-foot">
                    <div class="sb-user">
                        <span class="user-avatar" aria-hidden="true"><?= $qhH($qhInitial) ?></span>
                        <span class="sb-user-text">
                            <strong><?= $qhH($qhName) ?></strong>
                            <small><?= $qhH($qhRoleLabel) ?></small>
                        </span>
                        <a class="sb-logout" href="logout.php" title="Sign out" aria-label="Sign out">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-topbar">
                <button type="button" class="topbar-btn d-lg-none" data-bs-toggle="offcanvas"
                    data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <button type="button" class="topbar-btn d-none d-lg-inline-flex" id="sbCollapseBtn"
                    aria-controls="appSidebar" aria-label="Collapse sidebar" title="Collapse sidebar">
                    <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
                </button>

                <div class="topbar-title">
                    <small><?= $qhH($qhPageGroup !== '' ? $qhPageGroup : 'QUIDEI') ?></small>
                    <span><?= $qhH($qhPageTitle !== '' ? $qhPageTitle : 'Workspace') ?></span>
                </div>

                <div class="dropdown ms-auto">
                    <button type="button" class="user-menu-btn" data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="Account menu">
                        <span class="user-avatar" aria-hidden="true"><?= $qhH($qhInitial) ?></span>
                        <span class="um-text d-none d-sm-flex">
                            <span class="um-name"><?= $qhH($qhName) ?></span>
                            <span class="um-role"><?= $qhH($qhRoleLabel) ?></span>
                        </span>
                        <i class="bi bi-chevron-down small text-secondary d-none d-sm-inline" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown">
                        <li>
                            <div class="user-dropdown-head">
                                <div class="fw-bold text-truncate"><?= $qhH($qhName) ?></div>
                                <span class="role-chip"><?= $qhH($qhRoleLabel) ?></span>
                            </div>
                        </li>
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-1x2"
                                    aria-hidden="true"></i>Dashboard</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"
                                    aria-hidden="true"></i>Sign out</a></li>
                    </ul>
                </div>
            </header>

            <div class="app-content">
    <?php else: ?>
    <div class="app-main">
        <header class="app-topbar">
            <a class="topbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill" aria-hidden="true"></i><span>QUIDEI</span>
            </a>
            <?php if ($currentPage !== 'login.php'): ?>
            <a class="btn btn-gradient-primary rounded-pill px-4 ms-auto" href="login.php">Login</a>
            <?php endif; ?>
        </header>

        <div class="app-content">
    <?php endif; ?>
