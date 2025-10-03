<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotifications {
    private $webPush;
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];
        
        $this->webPush = new WebPush($auth);
    }
    
    /**
     * Send notification to all residents in a specific area
     */
    public function notifyArea($area, $title, $body, $data = []) {
        // Improved query to handle JSON-formatted addresses
        $queryString = "
            SELECT ps.*, u.address 
            FROM push_subscriptions ps 
            JOIN users u ON ps.user_id = u.id 
            WHERE u.role = 'resident' 
            AND u.address LIKE ?
        ";
        
        $area_search = '%' . $area . '%';
        $stmt = $this->conn->prepare($queryString);
        $stmt->bind_param('s', $area_search);
        $stmt->execute();
        $subscriptions = $stmt->get_result();
        
        $results = [];
        while ($sub = $subscriptions->fetch_assoc()) {
            $results[] = $this->sendToSubscription($sub, $title, $body, $data);
        }
        
        // Log number of subscribers found
        error_log("Found " . count($results) . " subscribers for area: $area");
        
        // Log query for debugging
        error_log("Push notification query: $queryString with params: $area_search, $area_search");
        
        $stmt->close();
        return $results;
    }
    
    /**
     * Send notification to all residents
     */
    public function notifyAllResidents($title, $body, $data = []) {
        $stmt = $this->conn->prepare("
            SELECT ps.* 
            FROM push_subscriptions ps 
            JOIN users u ON ps.user_id = u.id 
            WHERE u.role = 'resident'
        ");
        
        $stmt->execute();
        $subscriptions = $stmt->get_result();
        
        $results = [];
        while ($sub = $subscriptions->fetch_assoc()) {
            $results[] = $this->sendToSubscription($sub, $title, $body, $data);
        }
        
        $stmt->close();
        return $results;
    }
    
    /**
     * Send notification to specific user
     */
    public function notifyUser($user_id, $title, $body, $data = []) {
        $stmt = $this->conn->prepare("
            SELECT * FROM push_subscriptions WHERE user_id = ?
        ");
        
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $subscription = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($subscription) {
            return $this->sendToSubscription($subscription, $title, $body, $data);
        }
        
        return false;
    }
    
    /**
     * Send notification to a specific subscription
     */
    public function sendToSubscription($subscription, $title, $body, $data = []) {
        try {
            $sub = Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'keys' => [
                    'p256dh' => $subscription['p256dh'],
                    'auth' => $subscription['auth']
                ]
            ]);
            
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => BASE_URL . 'assets/collector.png',
                'data' => $data
            ]);
            
            // Send with explicit Content-Encoding header
            $report = $this->webPush->sendOneNotification(
                $sub, 
                $payload,
                ['contentEncoding' => 'aesgcm']
            );
            
            // Improved error handling
            if (!$report->isSuccess()) {
                $reason = $report->getReason() ?: 'Unknown error';
                error_log("Push failed to {$subscription['endpoint']}: $reason");
                
                // Only remove if it's an expired subscription
                if (strpos($reason, '410 Gone') !== false || 
                    strpos($reason, '404 Not Found') !== false) {
                    $this->removeExpiredSubscription($subscription['id']);
                }
            }
            
            return $report->isSuccess();
            
        } catch (Exception $e) {
            error_log("Push error to {$subscription['endpoint']}: " . $e->getMessage());
            
            // Retry once for temporary errors
            try {
                $report = $this->webPush->sendOneNotification(
                    $sub, 
                    $payload,
                    ['contentEncoding' => 'aesgcm']
                );
                return $report->isSuccess();
            } catch (Exception $retryEx) {
                error_log("Push retry failed to {$subscription['endpoint']}: " . $retryEx->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Remove expired subscription
     */
    private function removeExpiredSubscription($subscription_id) {
        $stmt = $this->conn->prepare("DELETE FROM push_subscriptions WHERE id = ?");
        $stmt->bind_param('i', $subscription_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Clean up expired subscriptions
     */
    public function cleanupExpiredSubscriptions() {
        // This would be called periodically to clean up expired subscriptions
        // For now, we'll clean up when sending fails
    }
}
