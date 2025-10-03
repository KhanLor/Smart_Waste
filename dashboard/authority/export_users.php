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
header('Content-Disposition: attachment; filename="users_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'User ID',
    'First Name',
    'Last Name',
    'Email',
    'Phone',
    'Address',
    'Role',
    'Status',
    'Email Notifications',
    'SMS Notifications',
    'Member Since',
    'Last Updated'
]);

// Get users data
$sql = "
    SELECT * FROM users 
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'],
        $row['address'],
        ucfirst($row['role']),
        $row['status'],
        $row['email_notifications'] ? 'Yes' : 'No',
        $row['sms_notifications'] ? 'Yes' : 'No',
        format_ph_date($row['created_at']),
        $row['updated_at'] ? format_ph_date($row['updated_at']) : 'Not Updated'
    ]);
}

fclose($output);
exit;
?>
