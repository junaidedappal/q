<?php
include 'includes/header.php';
require 'includes/db.php';

// Role-based access control
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    header('location: dashboard.php');
    exit;
}
?>

<style>
/* ======================================= */
/* --- Enhanced Modern Dashboard --- */
/* ======================================= */
* {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1.5rem;
}

/* Page Hero */
.page-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2.5rem;
    border-radius: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.page-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 400px;
    height: 400px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {

    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }

    50% {
        transform: translateY(-20px) rotate(5deg);
    }
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-icon {
    font-size: 3.5rem;
    background: rgba(255, 255, 255, 0.25);
    padding: 1.25rem;
    border-radius: 1rem;
    backdrop-filter: blur(10px);
}

.hero-title {
    font-weight: 800;
    font-size: 2.2rem;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.hero-subtitle {
    opacity: 0.95;
    font-size: 1.1rem;
    font-weight: 500;
}

/* Filter Section */
.filter-section {
    background: #fff;
    border-radius: 1.25rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}

.filter-buttons .btn {
    border-radius: 50px;
    font-weight: 600;
    padding: 0.7rem 1.75rem;
    border: 2px solid #e2e8f0;
    color: #4a5568;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.filter-buttons .btn:hover {
    border-color: #667eea;
    color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.filter-buttons .btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #fff;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
}

.stat-icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 70px;
    border-radius: 1rem;
    margin-bottom: 1rem;
    position: relative;
    z-index: 1;
}

.stat-icon {
    font-size: 2rem;
    color: #fff;
}

.stat-content {
    position: relative;
    z-index: 1;
}

.stat-value {
    font-size: 2.8rem;
    font-weight: 800;
    color: #2d3748;
    margin: 0.5rem 0;
    line-height: 1;
}

.stat-label {
    font-size: 0.95rem;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-trend {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
}

.stat-trend.up {
    background: #d4edda;
    color: #155724;
}

.stat-trend.down {
    background: #f8d7da;
    color: #721c24;
}

/* Performance Section */
.performance-section {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin-bottom: 2rem;
}

.section-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
    margin: 0;
}

/* Leaderboard Table */
.leaderboard-table {
    padding: 2rem;
}

.table-wrapper {
    overflow-x: auto;
}

.performance-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.75rem;
}

.performance-table thead th {
    background: #f8f9fa;
    color: #4a5568;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 1.5rem;
    border: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.performance-table tbody tr {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    border-radius: 0.75rem;
}

.performance-table tbody tr:hover {
    background: linear-gradient(135deg, #f0f3ff, #faf5ff);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    transform: scale(1.02);
}

.performance-table tbody td {
    padding: 1.25rem 1.5rem;
    vertical-align: middle;
    border: none;
    font-weight: 500;
}

.performance-table tbody tr td:first-child {
    border-top-left-radius: 0.75rem;
    border-bottom-left-radius: 0.75rem;
}

.performance-table tbody tr td:last-child {
    border-top-right-radius: 0.75rem;
    border-bottom-right-radius: 0.75rem;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    font-weight: 800;
    font-size: 1.1rem;
}

.rank-1 {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #b8860b;
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.rank-2 {
    background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
    color: #757575;
}

.rank-3 {
    background: linear-gradient(135deg, #cd7f32, #e9a56e);
    color: #8b4513;
}

.rank-default {
    background: #f1f5f9;
    color: #64748b;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.user-details strong {
    display: block;
    color: #2d3748;
    font-size: 1.05rem;
}

.user-details small {
    color: #718096;
}

.role-badge {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: capitalize;
}

.role-admin {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: #fff;
}

.role-sales {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: #fff;
}

.enquiry-count {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.progress-bar-wrapper {
    width: 100%;
    max-width: 150px;
}

.progress {
    height: 10px;
    border-radius: 50px;
    background: #e2e8f0;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 50px;
    transition: width 0.6s ease;
}

/* Loading States */
.loading-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 4rem;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f4f6;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #718096;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e0;
    margin-bottom: 1rem;
}

.empty-state h4 {
    color: #4a5568;
    margin-bottom: 0.5rem;
}

/* Chart Section */
.chart-section {
    background: #fff;
    border-radius: 1.25rem;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
    }

    .page-hero {
        padding: 1.5rem;
    }

    .hero-title {
        font-size: 1.6rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .filter-buttons {
        flex-direction: column;
    }

    .filter-buttons .btn {
        width: 100%;
    }

    .section-header {
        padding: 1rem 1.5rem;
    }

    .leaderboard-table {
        padding: 1rem;
    }

    .performance-table thead th,
    .performance-table tbody td {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }

    .user-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}

@media (max-width: 576px) {
    .stat-value {
        font-size: 2.2rem;
    }

    .enquiry-count {
        font-size: 1.4rem;
    }

    .rank-badge {
        width: 35px;
        height: 35px;
        font-size: 0.95rem;
    }
}
</style>

<div class="dashboard-container">
    <!-- Hero Section -->
    <div class="page-hero text-white">
        <div class="hero-content">
            <div class="d-flex align-items-center gap-3 mb-2">
                <i class="bi bi-bar-chart-line-fill hero-icon"></i>
                <div>
                    <h1 class="hero-title">Sales Dashboard</h1>
                    <p class="hero-subtitle mb-0">Real-time performance tracking and analytics</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="text-center mb-3">
            <h5 class="mb-2" style="color: #4a5568; font-weight: 600;">
                <i class="bi bi-calendar-range me-2"></i>Select Time Period
            </h5>
        </div>
        <div class="filter-buttons">
            <button type="button" class="btn active" data-period="this_month">
                <i class="bi bi-calendar-month me-1"></i> This Month
            </button>
            <button type="button" class="btn" data-period="previous_month">
                <i class="bi bi-calendar-minus me-1"></i> Previous Month
            </button>
            <button type="button" class="btn" data-period="this_year">
                <i class="bi bi-calendar-check me-1"></i> This Year
            </button>
            <button type="button" class="btn" data-period="previous_year">
                <i class="bi bi-calendar-x me-1"></i> Previous Year
            </button>
            <button type="button" class="btn" data-period="all_time">
                <i class="bi bi-infinity me-1"></i> All Time
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <!-- Total Enquiries -->
        <div class="stat-card">
            <div class="stat-icon-wrapper" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <i class="bi bi-journal-check stat-icon"></i>
            </div>
            <div class="stat-content">
                <div id="total-enquiries-value" class="stat-value">--</div>
                <div class="stat-label">Total Enquiries</div>
            </div>
        </div>

        <!-- Average Enquiries -->
        <div class="stat-card">
            <div class="stat-icon-wrapper" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                <i class="bi bi-graph-up-arrow stat-icon"></i>
            </div>
            <div class="stat-content">
                <div id="avg-enquiries-value" class="stat-value">--</div>
                <div class="stat-label">Avg Per Person</div>
            </div>
        </div>

        <!-- Top Performer -->
        <div class="stat-card">
            <div class="stat-icon-wrapper" style="background: linear-gradient(135deg, #ffd700, #ffed4e);">
                <i class="bi bi-trophy-fill stat-icon" style="color: #b8860b;"></i>
            </div>
            <div class="stat-content">
                <div id="top-performer-name" class="stat-value" style="font-size: 1.5rem;">--</div>
                <div class="stat-label">Top Performer</div>
                <div id="top-performer-count" style="color: #718096; font-weight: 600; margin-top: 0.25rem;"></div>
            </div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="performance-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">
                    <i class="bi bi-people-fill"></i>
                    Performance Leaderboard
                </h2>
                <p class="section-subtitle">Team members ranked by enquiry generation</p>
            </div>
            <div id="active-users-count" class="badge"
                style="background: rgba(255,255,255,0.25); font-size: 1rem; padding: 0.5rem 1rem;">
                -- Active Users
            </div>
        </div>

        <div class="leaderboard-table">
            <div class="table-wrapper">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Salesperson</th>
                            <th>Role</th>
                            <th class="text-end">Enquiries</th>
                            <th style="width: 200px;">Performance</th>
                        </tr>
                    </thead>
                    <tbody id="sales-performance-body">
                        <tr>
                            <td colspan="5">
                                <div class="loading-spinner">
                                    <div class="spinner"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function() {
    function loadDashboardData(period) {
        // Show loading states
        $('#total-enquiries-value').html('<div class="spinner-border spinner-border-sm"></div>');
        $('#avg-enquiries-value').html('<div class="spinner-border spinner-border-sm"></div>');
        $('#top-performer-name').html('<div class="spinner-border spinner-border-sm"></div>');
        $('#top-performer-count').text('');
        $('#active-users-count').text('-- Active Users');
        $('#sales-performance-body').html(`
            <tr>
                <td colspan="5">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                    </div>
                </td>
            </tr>
        `);

        $.ajax({
            url: 'sales_dashboard_actions.php',
            type: 'GET',
            data: {
                period
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.sales_performance;
                    const total = response.total_enquiries;

                    // Update total enquiries
                    $('#total-enquiries-value').text(total.toLocaleString());

                    // Calculate and update average
                    const activeUsers = data.filter(u => u.enquiry_count > 0).length;
                    const avg = activeUsers > 0 ? Math.round(total / activeUsers * 10) / 10 : 0;
                    $('#avg-enquiries-value').text(avg.toLocaleString());

                    // Update active users count
                    $('#active-users-count').text(data.length + ' Active Users');

                    // Update top performer
                    if (data.length > 0 && data[0].enquiry_count > 0) {
                        $('#top-performer-name').text(data[0].name);
                        $('#top-performer-count').text(data[0].enquiry_count + ' enquiries');
                    } else {
                        $('#top-performer-name').text('N/A');
                        $('#top-performer-count').text('No data');
                    }

                    // Build performance table
                    const tableBody = $('#sales-performance-body');
                    tableBody.empty();

                    if (data.length > 0) {
                        const maxEnquiries = Math.max(...data.map(u => u.enquiry_count));

                        data.forEach((user, index) => {
                            const rank = index + 1;
                            const rankClass = rank === 1 ? 'rank-1' :
                                rank === 2 ? 'rank-2' :
                                rank === 3 ? 'rank-3' : 'rank-default';

                            const initials = user.name.split(' ').map(n => n[0]).join('')
                                .toUpperCase().substring(0, 2);
                            const roleClass = user.role === 'admin' ? 'role-admin' :
                                'role-sales';
                            const percentage = maxEnquiries > 0 ? (user.enquiry_count /
                                maxEnquiries * 100) : 0;

                            const row = `
                                <tr>
                                    <td>
                                        <div class="rank-badge ${rankClass}">${rank}</div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">${initials}</div>
                                            <div class="user-details">
                                                <strong>${user.name}</strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge ${roleClass}">${user.role}</span>
                                    </td>
                                    <td class="text-end">
                                        <div class="enquiry-count">${user.enquiry_count}</div>
                                    </td>
                                    <td>
                                        <div class="progress-bar-wrapper">
                                            <div class="progress">
                                                <div class="progress-bar" style="width: ${percentage}%"></div>
                                            </div>
                                            <small class="text-muted">${percentage.toFixed(0)}%</small>
                                        </div>
                                    </td>
                                </tr>
                            `;
                            tableBody.append(row);
                        });
                    } else {
                        tableBody.html(`
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <h4>No Data Available</h4>
                                        <p>No enquiries found for the selected period.</p>
                                    </div>
                                </td>
                            </tr>
                        `);
                    }
                } else {
                    Swal.fire('Error!', response.message || 'Failed to load data', 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Connection Error!', 'Could not retrieve data from the server.', 'error');
                $('#total-enquiries-value').text('--');
                $('#avg-enquiries-value').text('--');
                $('#top-performer-name').text('--');
                $('#sales-performance-body').html(`
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="bi bi-exclamation-triangle"></i>
                                <h4>Failed to Load Data</h4>
                                <p>Please try again later or contact support.</p>
                            </div>
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Filter button click handler
    $('.filter-buttons .btn').on('click', function() {
        $('.filter-buttons .btn').removeClass('active');
        $(this).addClass('active');
        const selectedPeriod = $(this).data('period');
        loadDashboardData(selectedPeriod);
    });

    // Initial load
    loadDashboardData('this_month');
});
</script>