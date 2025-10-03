<?php
require_once '../../config/config.php';
require_login();

// Check if user is an authority
if (($_SESSION['role'] ?? '') !== 'authority') {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Set headers for CSV download (Excel can open CSV files)
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="collection_schedules_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Schedule ID',
    'Area',
    'Street Name',
    'Collection Day',
    'Collection Time',
    'Waste Type',
    'Status',
    'Assigned Collector',
    'Created Date',
    'Updated Date'
]);

// Get schedules data
$sql = "
    SELECT cs.*, 
           u.first_name, u.last_name
    FROM collection_schedules cs 
    LEFT JOIN users u ON cs.assigned_collector = u.id
    ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), cs.collection_time
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['area'],
        $row['street_name'],
        ucfirst($row['collection_day']),
        format_ph_date($row['collection_time'], 'g:i A'),
        ucfirst($row['waste_type']),
        ucfirst($row['status']),
        $row['first_name'] ? ($row['first_name'] . ' ' . $row['last_name']) : 'Not Assigned',
        format_ph_date($row['created_at']),
        $row['updated_at'] ? format_ph_date($row['updated_at']) : 'Not Updated'
    ]);
}

fclose($output);
exit;
?>
