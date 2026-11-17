<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Notification {
    /**
     * Create a new notification
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, is_read, created_at)
            VALUES (:user_id, :title, :message, 0, :created_at)
        ");

        $stmt->execute([
            'user_id'    => $data['user_id'],
            'title'      => $data['title'],
            'message'    => $data['message'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Get unread notifications for a specific user
     */
    public static function getUnreadByUser(int $userId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id AND is_read = 0 
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all notifications for a specific user (read and unread)
     */
    public static function getAllByUser(int $userId, int $limit = 20): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        // Bind integer parameter properly
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Mark a specific notification as read
     */
    public static function markAsRead(int $id, int $userId): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    /**
     * Mark all notifications as read for a specific user
     */
    public static function markAllAsRead(int $userId): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        return $stmt->execute(['user_id' => $userId]);
    }
}
