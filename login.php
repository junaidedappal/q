<?php
// If user is already logged in, redirect away from this page
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
if (isset($_GET['error']) && $_GET['error'] == '1') {
    $error_message = 'Invalid email or password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Secure Access</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
    :root {
        --emerald-500: #10b981;
        --emerald-600: #059669;
        --emerald-700: #047857;
        --slate-900: #0f172a;
        --slate-500: #64748b;
    }

    * {
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
    }

    body {
        background-color: #ffffff;
        background-image: radial-gradient(at 0% 0%, rgba(16, 185, 129, 0.05) 0, transparent 50%),
            radial-gradient(at 50% 100%, rgba(16, 185, 129, 0.05) 0, transparent 50%);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
    }

    .login-container {
        width: 100%;
        max-width: 420px;
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .login-card {
        background: #ffffff;
        border: 1px solid #f1f5f9;
        border-radius: 1.5rem;
        padding: 2.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    }

    .brand-section {
        text-align: center;
        margin-bottom: 2rem;
    }

    .logo-circle {
        width: 64px;
        height: 64px;
        background: var(--emerald-500);
        color: white;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
    }

    h2 {
        color: var(--slate-900);
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
    }

    .subtitle {
        color: var(--slate-500);
        font-size: 0.95rem;
    }

    /* Form Styling */
    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-group i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--slate-500);
        transition: color 0.3s;
    }

    .form-control {
        width: 100%;
        padding: 0.8rem 1rem 0.8rem 2.8rem;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        font-size: 1rem;
        transition: all 0.2s ease;
        outline: none;
    }

    .form-control:focus {
        background-color: #ffffff;
        border-color: var(--emerald-500);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .form-control:focus+i {
        color: var(--emerald-500);
    }

    .btn-login {
        width: 100%;
        padding: 0.9rem;
        background-color: var(--emerald-500);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-login:hover {
        background-color: var(--emerald-600);
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    /* Alert Styling */
    .alert {
        background-color: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #b91c1c;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* Responsive adjustments */
    @media (max-width: 480px) {
        .login-card {
            padding: 1.5rem;
            border: none;
            box-shadow: none;
        }

        body {
            background-color: #ffffff;
            align-items: flex-start;
            padding-top: 10vh;
        }
    }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-section">
                <div class="logo-circle">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <h2>Welcome back</h2>
                <p class="subtitle">Please enter your details to sign in</p>
            </div>

            <?php if (!empty($error_message)): ?>
            <div class="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?php echo $error_message; ?></span>
            </div>
            <?php endif; ?>

            <form action="login_action.php" method="post">
                <div class="form-group">
                    <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                    <i class="bi bi-envelope"></i>
                </div>

                <div class="form-group">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <i class="bi bi-lock"></i>
                </div>

                <button type="submit" class="btn-login">
                    Sign In
                    <i class="bi bi-arrow-right-short"></i>
                </button>
            </form>
        </div>

        <p style="text-align: center; margin-top: 2rem; color: var(--slate-500); font-size: 0.85rem;">
            &copy; <?php echo date('Y'); ?>
            QUIDEI LEARNS
            . All rights reserved.
        </p>
    </div>

</body>

</html>