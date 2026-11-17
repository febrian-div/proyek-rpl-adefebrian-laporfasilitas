<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class ReportTimeline
{
    /**
     * Create a new timeline entry
     */
    public static function create(array $data): ?int
    {
        $db = Database::getConnection();
        if (!$db)
            return null;

        $stmt = $db->prepare("
            INSERT INTO report_timelines (report_id, status, note, created_at)
            VALUES (:report_id, :status, :note, :created_at)
        ");

        $stmt->execute([
            'report_id' => $data['report_id'],
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get all timelines for a specific report
     */
    public static function getByReport(int $reportId): array
    {
        $db = Database::getConnection();
        if (!$db)
            return [];

        $stmt = $db->prepare("SELECT * FROM report_timelines WHERE report_id = :report_id ORDER BY created_at ASC");
        $stmt->execute(['report_id' => $reportId]);
        return $stmt->fetchAll() ?: [];
    }
}
