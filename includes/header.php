<?php
// Start the session
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education Management System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
    :root {
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-700: #047857;
        --slate-50: #f8fafc;
        --slate-900: #0f172a;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: #ffffff;
        background-image: radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.03) 0, transparent 50%);
        min-height: 100vh;
    }

    /* Modern Emerald Navbar */
    .navbar-custom {
        background-color: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        padding: 0.8rem 0;
        z-index: 1050;
    }

    .navbar-custom .navbar-brand {
        font-weight: 800;
        font-size: 1.4rem;
        color: var(--slate-900) !important;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .navbar-custom .navbar-brand i {
        color: var(--emerald-500);
    }

    .navbar-custom .nav-link {
        color: var(--slate-900) !important;
        font-weight: 600;
        padding: 0.5rem 1rem;
    }

    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: var(--emerald-500);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #fff;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
    }

    /* Dropdown Menu */
    .navbar-custom .dropdown-menu {
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        padding: 10px;
        margin-top: 10px;
        background: #fff;
    }

    .dropdown-header-custom {
        padding: 10px 15px;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 5px;
    }

    .dropdown-header-custom .user-name {
        font-weight: 700;
        color: var(--slate-900);
    }

    .dropdown-header-custom .user-role {
        font-size: 0.75rem;
        color: var(--emerald-600);
        background: rgba(16, 185, 129, 0.1);
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    .navbar-custom .dropdown-item {
        border-radius: 8px;
        padding: 8px 12px;
        font-weight: 500;
        transition: 0.2s;
    }

    .navbar-custom .dropdown-item:hover {
        background-color: var(--slate-50);
        color: var(--emerald-600);
    }

    /* Button Gradients updated to Emerald */
    .btn-gradient-primary {
        background: var(--emerald-500);
        border: none;
        color: white;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .btn-gradient-primary:hover {
        background: var(--emerald-600);
        transform: translateY(-1px);
    }

    @media (max-width: 991px) {
        .navbar-collapse {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.05);
            margin-top: 10px;
        }
    }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <?php 
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage != 'login.php'): 
    ?>
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill"></i>
                <span>QUIDEI EMS</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
                            </span>
                            <span class="d-lg-none">Account</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0" aria-labelledby="userDropdown">
                            <li>
                                <div class="dropdown-header-custom">
                                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
                                    <div class="user-role text-uppercase">
                                        <?php echo htmlspecialchars($_SESSION['role']); ?></div>
                                </div>
                            </li>
                            <li><a class="dropdown-item" href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                                </a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-gradient-primary px-4 py-2" href="login.php">
                            Login
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container mt-4 mb-5 flex-grow-1">