<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Officer {
    /**
     * Find officer profile by Officer ID
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $sql = "
            SELECT o.*, u.name, u.email, u.role
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = :id
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $officer = $stmt->fetch();
        return $officer ?: null;
    }

    /**
     * Find officer profile by User ID
     */
    public static function findByUserId(int $userId): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $sql = "
            SELECT o.*, u.name, u.email, u.role
            FROM officers o
            JOIN users u ON o.user_id = u.id
            WHERE o.user_id = :user_id
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $officer = $stmt->fetch();
        return $officer ?: null;
    }

    /**
     * Get all officers with their user profile
     */
    public static function all(): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT o.*, u.name, u.email, u.role
            FROM officers o
            JOIN users u ON o.user_id = u.id
            ORDER BY u.name ASC
        ";
        $stmt = $db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Create officer profile
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("
            INSERT INTO officers (user_id, phone, service_area)
            VALUES (:user_id, :phone, :service_area)
        ");
        $stmt->execute([
            'user_id'      => $data['user_id'],
            'phone'        => $data['phone'],
            'service_area' => $data['service_area'],
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Update officer profile
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $fields = [];
        $params = ['id' => $id];

        if (isset($data['phone'])) {
            $fields[] = "phone = :phone";
            $params['phone'] = $data['phone'];
        }
        if (isset($data['service_area'])) {
            $fields[] = "service_area = :service_area";
            $params['service_area'] = $data['service_area'];
        }

        if (empty($fields)) return false;

        $sql = "UPDATE officers SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete officer profile
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM officers WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship: Officer belongs to User
     */
    public static function user(int $userId): ?array {
        return User::findById($userId);
    }

    /**
     * Relationship: Officer has many Assignments
     */
    public static function assignments(int $officerId): array {
        return Assignment::getByOfficer($officerId);
    }

    /**
     * Relationship: Officer has many Reports through Assignments
     */
    public static function assignedReports(int $officerId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT r.*, a.id AS assignment_id, a.status AS assignment_status, a.assigned_at,
                   u.name AS reporter_name
            FROM assignments a
            JOIN reports r ON a.report_id = r.id
            JOIN users u ON r.user_id = u.id
            WHERE a.officer_id = :officer_id
            ORDER BY a.assigned_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['officer_id' => $officerId]);
        return $stmt->fetchAll() ?: [];
    }
}
