<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class RepairProof {
    /**
     * Create a repair proof record
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("
            INSERT INTO repair_proofs (report_id, photo, notes)
            VALUES (:report_id, :photo, :notes)
        ");

        $stmt->execute([
            'report_id' => $data['report_id'],
            'photo'     => $data['photo'],
            'notes'     => $data['notes'] ?? null,
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Find repair proof by ID
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT * FROM repair_proofs WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Get all repair proofs for a specific report
     */
    public static function getByReport(int $reportId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $stmt = $db->prepare("SELECT * FROM repair_proofs WHERE report_id = :report_id ORDER BY created_at DESC");
        $stmt->execute(['report_id' => $reportId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Delete repair proof
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM repair_proofs WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship: RepairProof belongs to Report
     */
    public static function report(int $reportId): ?array {
        return Report::findById($reportId);
    }
}
