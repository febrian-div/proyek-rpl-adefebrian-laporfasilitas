<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Assignment {
    /**
     * Create a new assignment
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("
            INSERT INTO assignments (report_id, officer_id, assigned_at, status)
            VALUES (:report_id, :officer_id, :assigned_at, :status)
        ");

        $stmt->execute([
            'report_id'   => $data['report_id'],
            'officer_id'  => $data['officer_id'],
            'assigned_at' => $data['assigned_at'] ?? date('Y-m-d H:i:s'),
            'status'      => $data['status'] ?? 'Ditugaskan',
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Find assignment by ID with report and officer info
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $sql = "
            SELECT a.*, 
                   r.title AS report_title, r.status AS report_status, r.photo AS report_photo,
                   r.latitude, r.longitude, r.address AS report_address,
                   o.phone AS officer_phone, o.service_area AS officer_service_area,
                   u.name AS officer_name, u.email AS officer_email
            FROM assignments a
            JOIN reports r ON a.report_id = r.id
            JOIN officers o ON a.officer_id = o.id
            JOIN users u ON o.user_id = u.id
            WHERE a.id = :id
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Get all assignments for a specific report
     */
    public static function getByReport(int $reportId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT a.*, 
                   o.phone AS officer_phone, o.service_area AS officer_service_area,
                   u.name AS officer_name, u.email AS officer_email
            FROM assignments a
            JOIN officers o ON a.officer_id = o.id
            JOIN users u ON o.user_id = u.id
            WHERE a.report_id = :report_id
            ORDER BY a.assigned_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['report_id' => $reportId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all assignments for a specific officer
     */
    public static function getByOfficer(int $officerId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT a.*, 
                   r.title AS report_title, r.description AS report_description,
                   r.photo AS report_photo, r.latitude, r.longitude, r.address AS report_address,
                   r.status AS report_status, r.priority AS report_priority, r.facility_category,
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

    /**
     * Update assignment status
     */
    public static function updateStatus(int $id, string $status): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE assignments SET status = :status WHERE id = :id");
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }

    /**
     * Delete assignment
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM assignments WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship: Assignment belongs to Report
     */
    public static function report(int $reportId): ?array {
        return Report::findById($reportId);
    }

    /**
     * Relationship: Assignment belongs to Officer
     */
    public static function officer(int $officerId): ?array {
        return Officer::findById($officerId);
    }
}
