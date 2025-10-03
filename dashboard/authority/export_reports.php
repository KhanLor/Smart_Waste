<?php
require_once '../../config/config.php';
require_login();

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="waste_reports_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Report ID',
    'Title',
    'Description',
    'Location',
    'Report Type',
    'Priority',
    'Status',
    'Resident Name',
    'Resident Email',
    'Resident Phone',
    'Assigned Collector',
    'Created Date',
    'Updated Date'
]);

// Get reports data
$sql = "
    SELECT wr.*, 
           u.first_name, u.last_name, u.email, u.phone,
           c.first_name as collector_first_name, c.last_name as collector_last_name
    FROM waste_reports wr 
    JOIN users u ON wr.user_id = u.id 
    LEFT JOIN users c ON wr.assigned_to = c.id
    ORDER BY wr.created_at DESC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['title'],
        $row['description'],
        $row['location'],
        $row['report_type'],
        $row['priority'],
        $row['status'],
        $row['first_name'] . ' ' . $row['last_name'],
        $row['email'],
        $row['phone'],
        $row['collector_first_name'] ? ($row['collector_first_name'] . ' ' . $row['collector_last_name']) : 'Not Assigned',
        format_ph_date($row['created_at']),
        $row['updated_at'] ? format_ph_date($row['updated_at']) : 'Not Updated'
    ]);
}

fclose($output);
exit;
?>
