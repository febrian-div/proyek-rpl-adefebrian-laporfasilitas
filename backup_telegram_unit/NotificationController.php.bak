<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Notification;

class NotificationController {
    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $id): void {
        $user = AuthMiddleware::user();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            http_response_code(401);
            exit;
        }

        $success = Notification::markAsRead($id, (int)$user['id']);
        
        // Output JSON response if requested via AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }

    /**
     * Mark all notifications as read for current user
     */
    public function markAllAsRead(): void {
        $user = AuthMiddleware::user();
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            http_response_code(401);
            exit;
        }

        $success = Notification::markAllAsRead((int)$user['id']);
        
        // Output JSON response if requested via AJAX
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }
}
