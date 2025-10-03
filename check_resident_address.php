<?php
require_once __DIR__ . '/config/config.php';

// Use the database connection from config.php
global $conn;
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get address for resident ID 5
$resident_id = 5;
$stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
$stmt->bind_param("i", $resident_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Resident ID {$resident_id} address: {$row['address']}\n";
} else {
    echo "No resident found with ID {$resident_id}\n";
}

// Close connection
$conn->close();
