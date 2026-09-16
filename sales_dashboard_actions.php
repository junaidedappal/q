<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json');
require 'includes/db.php';

// Authorization Check: Ensure only authorized users can access this data
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied.']);
    exit;
}

// Get the requested period from the frontend, default to 'this_month'
$period = $_GET['period'] ?? 'this_month';

// Base SQL query. We use a LEFT JOIN to include users who may have 0 enquiries
// in the selected period, ensuring all sales staff are always listed.
$sql = 'SELECT 
            u.name, 
            u.role, 
            COUNT(e.id) AS enquiry_count
        FROM users u
        LEFT JOIN enquiries e ON u.id = e.created_by';

// Dynamically add a date filter to the ON clause of the LEFT JOIN
// This is the correct way to filter the right side of a LEFT JOIN without excluding users
$date_filter_sql = '';
switch ($period) {
    case 'this_month':
        $date_filter_sql = "AND YEAR(e.created_at) = YEAR(CURDATE()) AND MONTH(e.created_at) = MONTH(CURDATE())";
        break;
    case 'previous_month':
        $date_filter_sql = "AND YEAR(e.created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(e.created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
        break;
    case 'this_year':
        $date_filter_sql = "AND YEAR(e.created_at) = YEAR(CURDATE())";
        break;
    case 'previous_year':
        $date_filter_sql = "AND YEAR(e.created_at) = YEAR(CURDATE()) - 1";
        break;
    case 'all_time':
        // No extra date filter needed
        break;
    default:
        // Default to this month for security
        $date_filter_sql = "AND YEAR(e.created_at) = YEAR(CURDATE()) AND MONTH(e.created_at) = MONTH(CURDATE())";
        break;
}

// Append the date filter to the JOIN condition
$sql .= " " . $date_filter_sql;

// We only want to see stats for 'admin' and 'sales' roles
$sql .= " WHERE u.role IN ('admin', 'sales')";

// Group by user to get individual counts and order by performance
$sql .= ' GROUP BY u.id, u.name, u.role
          ORDER BY enquiry_count DESC, u.name ASC';

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database query failed: ' . $conn->error]);
    exit;
}

$sales_data = [];
$total_enquiries = 0;

// Loop through results and build the data array and total
while ($row = $result->fetch_assoc()) {
    $sales_data[] = $row;
    $total_enquiries += $row['enquiry_count'];
}

// Prepare the final JSON response
$response = [
    'status' => 'success',
    'period_queried' => $period,
    'total_enquiries' => $total_enquiries,
    'sales_performance' => $sales_data
];

echo json_encode($response);
?>