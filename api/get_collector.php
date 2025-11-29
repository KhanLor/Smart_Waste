<?php
require_once __DIR__ . '/../config/config.php';
require_login();

// Only authority can fetch collector details
if (($_SESSION['role'] ?? '') !== 'authority') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$stmt = $conn->prepare("SELECT id, username, first_name, middle_name, last_name, email, phone, address, num_trucks, truck_equipment FROM users WHERE id = ? AND role = 'collector'");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Collector not found']);
}
