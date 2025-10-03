<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$report_id = $_GET['id'] ?? null;
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// For testing purposes, allow access without login
// In production, you should uncomment the require_login() line below
// require_login();

if (!$report_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Report ID is required']);
    exit;
}

try {
    // Check if user has permission to view this report
    if ($user_role === 'resident' && $user_id) {
        // Residents can only view their own reports
        $stmt = $conn->prepare("SELECT id FROM waste_reports WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $report_id, $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
    }
    
    // Get report details with user information
    $sql = "
        SELECT wr.*, u.first_name, u.last_name, u.email, u.phone, u.address as user_address,
               assigned.first_name as assigned_first_name, assigned.last_name as assigned_last_name,
               assigned.email as assigned_email, assigned.phone as assigned_phone
        FROM waste_reports wr 
        JOIN users u ON wr.user_id = u.id 
        LEFT JOIN users assigned ON wr.assigned_to = assigned.id
        WHERE wr.id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }
    
    // Get report images
    $stmt = $conn->prepare("SELECT * FROM report_images WHERE report_id = ? ORDER BY uploaded_at ASC");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get status history (if you have a status_history table)
    // For now, we'll include the current status and timestamps
    $status_history = [
        [
            'status' => $report['status'],
            'timestamp' => $report['updated_at'] ?: $report['created_at'],
            'note' => 'Current status'
        ]
    ];
    
    // Format the response
    $response = [
        'success' => true,
        'report' => [
            'id' => $report['id'],
            'title' => $report['title'],
            'description' => $report['description'],
            'location' => $report['location'],
            'report_type' => $report['report_type'],
            'priority' => $report['priority'],
            'status' => $report['status'],
            'created_at' => format_ph_date($report['created_at']),
            'updated_at' => $report['updated_at'] ? format_ph_date($report['updated_at']) : null,
            'resident' => [
                'name' => $report['first_name'] . ' ' . $report['last_name'],
                'email' => $report['email'],
                'phone' => $report['phone'],
                'address' => $report['user_address']
            ],
            'assigned_to' => $report['assigned_first_name'] ? [
                'name' => $report['assigned_first_name'] . ' ' . $report['assigned_last_name'],
                'email' => $report['assigned_email'],
                'phone' => $report['assigned_phone']
            ] : null,
            'images' => array_map(function($image) {
                return [
                    'id' => $image['id'],
                    'url' => BASE_URL . $image['image_path'],
                    'filename' => basename($image['image_path']),
                    'uploaded_at' => format_ph_date($image['uploaded_at'])
                ];
            }, $images),
            'status_history' => $status_history
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
