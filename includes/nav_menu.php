<?php
// includes/nav_menu.php
// Single source of truth for the app's modules. Used by the sidebar (includes/header.php)
// and by the dashboard cards (dashboard.php).
//
// To add a module: add an entry to the right group. 'roles' controls who sees it,
// 'match' lists sub-pages that should highlight this menu item (e.g. detail pages).
// The page itself must still have its own role guard.

if (!function_exists('quidei_nav_groups')) {
    /**
     * Returns the module groups visible to $role (groups with no visible modules are dropped).
     * Each group: ['title', 'icon', 'modules' => [...]].
     */
    function quidei_nav_groups(string $role): array
    {
        $groups = [
            [
                'title' => 'Admin & Finance',
                'icon' => 'bi-briefcase-fill',
                'modules' => [
                    [
                        'href' => 'finalized_students.php',
                        'roles' => ['admin'],
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
            ],
            [
                'title' => 'Mentor',
                'icon' => 'bi-person-workspace',
                'modules' => [
                    [
                        'href' => 'my_students.php',
                        'match' => ['student_schedule.php'],
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
                        'title' => 'Mentor Salary Management',
                        'description' => 'Manage Mentors',
                    ],
                    [
                        'href' => 'mentor_activity.php',
                        'roles' => ['admin', 'qualitycontroller', 'supervisor'],
                        'class' => 'card-monitoring',
                        'icon' => 'bi-calendar2-check-fill',
                        'title' => 'Mentor Activity',
                        'description' => $role === 'supervisor'
                            ? 'Sessions for your assigned mentors'
                            : 'All mentor sessions and attendance',
                    ],
                ],
            ],
            [
                'title' => 'Demo Department',
                'icon' => 'bi-easel2-fill',
                'modules' => [
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
                        'match' => ['view_demo_details.php'],
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
                        'icon' => 'bi-bar-chart-line-fill',
                        'title' => 'Monthly Analytics',
                        'description' => 'Demo Performance Report',
                    ],
                ],
            ],
            [
                'title' => 'Supervisor',
                'icon' => 'bi-binoculars-fill',
                'modules' => [
                    [
                        'href' => 'supervisor_monitoring.php',
                        'match' => ['supervisor_audit_mentor_detail.php'],
                        'roles' => ['admin', 'supervisor'],
                        'class' => 'card-monitoring',
                        'icon' => 'bi-activity',
                        'title' => 'Supervisor Module',
                        'description' => 'Analysis Mentor Performance',
                    ],
                ],
            ],
            [
                'title' => 'User Management',
                'icon' => 'bi-people-fill',
                'modules' => [
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
            ],
            [
                'title' => 'Accounts',
                'icon' => 'bi-cash-coin',
                'modules' => [
                    [
                        'href' => 'quidei_payouts.php',
                        'match' => ['quidei_payout_detail.php'],
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
            ],
            [
                'title' => 'HR Department',
                'icon' => 'bi-person-vcard-fill',
                'modules' => [
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
            ],
        ];

        $visible = [];
        foreach ($groups as $group) {
            $group['modules'] = array_values(array_filter(
                $group['modules'],
                static fn(array $m): bool => in_array($role, $m['roles'], true)
            ));
            if ($group['modules']) {
                $visible[] = $group;
            }
        }
        return $visible;
    }

    /** True when $page is the module's page or one of its sub-pages. */
    function quidei_nav_is_active(array $module, string $page): bool
    {
        return $module['href'] === $page || in_array($page, $module['match'] ?? [], true);
    }
}
