<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Report {
    /**
     * Create a new facility report
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $aiResultJson = isset($data['ai_result']) 
            ? (is_string($data['ai_result']) ? $data['ai_result'] : json_encode($data['ai_result'], JSON_UNESCAPED_UNICODE))
            : null;

        $stmt = $db->prepare("
            INSERT INTO reports (
                user_id, title, description, photo, latitude, longitude, 
                address, facility_category, damage_type, priority, ai_result, status
            ) VALUES (
                :user_id, :title, :description, :photo, :latitude, :longitude,
                :address, :facility_category, :damage_type, :priority, :ai_result, :status
            )
        ");

        $stmt->execute([
            'user_id'           => $data['user_id'],
            'title'             => $data['title'],
            'description'       => $data['description'],
            'photo'             => $data['photo'],
            'latitude'          => $data['latitude'],
            'longitude'         => $data['longitude'],
            'address'           => $data['address'],
            'facility_category' => $data['facility_category'] ?? null,
            'damage_type'       => $data['damage_type'] ?? null,
            'priority'          => $data['priority'] ?? null,
            'ai_result'         => $aiResultJson,
            'status'            => $data['status'] ?? 'Menunggu Verifikasi',
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Find single report by ID with reporter & current assignment details
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $sql = "
            SELECT r.*, 
                   u.name AS reporter_name, u.email AS reporter_email,
                   a.id AS assignment_id, a.status AS assignment_status, a.assigned_at,
                   o.id AS officer_id, o.phone AS officer_phone, o.service_area AS officer_service_area,
                   ou.name AS officer_name, ou.email AS officer_email
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN (
                SELECT a1.*
                FROM assignments a1
                JOIN (
                    SELECT report_id, MAX(id) AS max_id 
                    FROM assignments 
                    GROUP BY report_id
                ) a2 ON a1.id = a2.max_id
            ) a ON a.report_id = r.id
            LEFT JOIN officers o ON a.officer_id = o.id
            LEFT JOIN users ou ON o.user_id = ou.id
            WHERE r.id = :id
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $report = $stmt->fetch();

        if ($report && !empty($report['ai_result'])) {
            $report['ai_result_data'] = json_decode($report['ai_result'], true);
        }

        return $report ?: null;
    }

    /**
     * Get all reports by a specific citizen/user
     */
    public static function getByUser(int $userId): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT r.*
            FROM reports r
            WHERE r.user_id = :user_id
            ORDER BY r.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['ai_result'])) {
                $row['ai_result_data'] = json_decode($row['ai_result'], true);
            }
        }

        return $rows;
    }

    /**
     * Count all reports with optional filtering (for pagination)
     */
    public static function countAll(array $filters = []): int {
        $db = Database::getConnection();
        if (!$db) return 0;

        $sql = "SELECT COUNT(*) FROM reports r WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND r.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['facility_category'])) {
            $sql .= " AND r.facility_category = :category";
            $params['category'] = $filters['facility_category'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.title LIKE :search1 OR r.address LIKE :search2 OR r.description LIKE :search3)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get all reports with optional filtering, sorting, and pagination
     */
    public static function getAll(array $filters = []): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $sql = "
            SELECT r.*, u.name AS reporter_name, u.email AS reporter_email,
                   ou.name AS officer_name
            FROM reports r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN (
                SELECT a1.report_id, a1.officer_id
                FROM assignments a1
                JOIN (
                    SELECT report_id, MAX(id) AS max_id 
                    FROM assignments 
                    GROUP BY report_id
                ) a2 ON a1.id = a2.max_id
            ) a ON a.report_id = r.id
            LEFT JOIN officers o ON a.officer_id = o.id
            LEFT JOIN users ou ON o.user_id = ou.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND r.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['facility_category'])) {
            $sql .= " AND r.facility_category = :category";
            $params['category'] = $filters['facility_category'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.title LIKE :search1 OR r.address LIKE :search2 OR r.description LIKE :search3)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params['search1'] = $searchTerm;
            $params['search2'] = $searchTerm;
            $params['search3'] = $searchTerm;
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = strtoupper($filters['direction'] ?? 'DESC');
        if (!in_array($sortDirection, ['ASC', 'DESC'])) {
            $sortDirection = 'DESC';
        }
        
        $allowedSorts = ['created_at', 'status', 'priority'];
        if (in_array($sortField, $allowedSorts)) {
            $sql .= " ORDER BY r.{$sortField} {$sortDirection}";
        } else {
            $sql .= " ORDER BY r.created_at DESC";
        }

        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            if (!empty($row['ai_result'])) {
                $row['ai_result_data'] = json_decode($row['ai_result'], true);
            }
        }

        return $rows;
    }

    /**
     * Update report data
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $fields = [];
        $params = ['id' => $id];

        $allowedColumns = ['title', 'description', 'photo', 'latitude', 'longitude', 'address', 'facility_category', 'damage_type', 'priority', 'status'];

        foreach ($allowedColumns as $col) {
            if (isset($data[$col])) {
                $fields[] = "{$col} = :{$col}";
                $params[$col] = $data[$col];
            }
        }

        if (isset($data['ai_result'])) {
            $fields[] = "ai_result = :ai_result";
            $params['ai_result'] = is_string($data['ai_result']) ? $data['ai_result'] : json_encode($data['ai_result'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($fields)) return false;

        $sql = "UPDATE reports SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update report status and priority
     */
    public static function updateStatus(int $id, string $status, ?string $priority = null): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $fields = ["status = :status"];
        $params = ['id' => $id, 'status' => $status];

        if ($priority !== null) {
            $fields[] = "priority = :priority";
            $params['priority'] = $priority;
        }

        $sql = "UPDATE reports SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete report
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM reports WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship: Report belongs to User (Masyarakat)
     */
    public static function user(int $userId): ?array {
        return User::findById($userId);
    }

    /**
     * Relationship: Report has many Assignments
     */
    public static function assignments(int $reportId): array {
        return Assignment::getByReport($reportId);
    }

    /**
     * Relationship: Report has many RepairProofs
     */
    public static function repairProofs(int $reportId): array {
        return RepairProof::getByReport($reportId);
    }
}
