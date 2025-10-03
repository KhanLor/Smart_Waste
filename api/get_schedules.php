<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// For testing purposes, allow access without login
// In production, you should uncomment the require_login() line below
// require_login();

$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

try {
    // Get user's address if logged in
    $user_address = '';
    if ($user_id) {
        $stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            $user_address = $user['address'];
        }
    }
    
    // Get schedules based on role
    if ($user_role === 'authority') {
        // Authorities can see all schedules
        $sql = "
            SELECT cs.*, u.first_name, u.last_name 
            FROM collection_schedules cs 
            LEFT JOIN users u ON cs.assigned_collector = u.id
            ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), cs.collection_time
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } elseif ($user_role === 'collector') {
        // Collectors see only their assigned schedules
        if (!$user_id) {
            $sql = "SELECT * FROM collection_schedules WHERE 1=0";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "
                SELECT cs.*, u.first_name, u.last_name 
                FROM collection_schedules cs 
                LEFT JOIN users u ON cs.assigned_collector = u.id
                WHERE cs.assigned_collector = ?
                ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), cs.collection_time
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    } else {
        // Residents see schedules for their area
        if ($user_address) {
            $address_search = '%' . $user_address . '%';
            $sql = "
                SELECT cs.*, u.first_name, u.last_name 
                FROM collection_schedules cs 
                LEFT JOIN users u ON cs.assigned_collector = u.id
                WHERE cs.street_name LIKE ? OR cs.area LIKE ?
                ORDER BY FIELD(cs.collection_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'), cs.collection_time
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $address_search, $address_search);
        } else {
            // If no address, return empty result
            $sql = "SELECT * FROM collection_schedules WHERE 1=0";
            $stmt = $conn->prepare($sql);
        }
        $stmt->execute();
    }
    
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get next collection for residents
    $next_collection = null;
    if ($user_role === 'resident' && $user_address && count($schedules) > 0) {
        $today = strtolower(date('l'));
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $today_index = array_search($today, $days);
        
        // Find next collection day
        for ($i = 0; $i < 7; $i++) {
            $check_day = $days[($today_index + $i) % 7];
            foreach ($schedules as $schedule) {
                if ($schedule['collection_day'] === $check_day) {
                    $next_collection = $schedule;
                    $next_collection['days_until'] = $i;
                    break 2;
                }
            }
        }
    }
    
    // Format the response
    $response = [
        'success' => true,
        'schedules' => array_map(function($schedule) {
            return [
                'id' => $schedule['id'],
                'area' => $schedule['area'],
                'street_name' => $schedule['street_name'],
                'collection_day' => $schedule['collection_day'],
                'collection_time' => format_ph_date($schedule['collection_time'], 'g:i A'),
                'waste_type' => $schedule['waste_type'],
                'status' => $schedule['status'],
                'assigned_collector' => $schedule['first_name'] ? [
                    'name' => $schedule['first_name'] . ' ' . $schedule['last_name']
                ] : null,
                'created_at' => format_ph_date($schedule['created_at'])
            ];
        }, $schedules),
        'next_collection' => $next_collection ? [
            'id' => $next_collection['id'],
            'area' => $next_collection['area'],
            'street_name' => $next_collection['street_name'],
            'collection_day' => $next_collection['collection_day'],
            'collection_time' => format_ph_date($next_collection['collection_time'], 'g:i A'),
            'waste_type' => $next_collection['waste_type'],
            'days_until' => $next_collection['days_until'],
            'assigned_collector' => $next_collection['first_name'] ? [
                'name' => $next_collection['first_name'] . ' ' . $next_collection['last_name']
            ] : null
        ] : null,
        'user_address' => $user_address,
        'total_schedules' => count($schedules)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?>
