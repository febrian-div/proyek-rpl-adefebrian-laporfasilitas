<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class User {
    /**
     * Find user by ID (includes is_active)
     */
    public static function findById(int $id): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT id, name, email, role, is_active, telegram_chat_id, created_at, updated_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Find user by email (includes password hash for auth and is_active)
     */
    public static function findByEmail(string $email): ?array {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * Get all users, optionally filtered by role, search query, and status
     */
    public static function all(?string $role = null, string $search = '', string $status = ''): array {
        $db = Database::getConnection();
        if (!$db) return [];

        $conditions = [];
        $params = [];

        if ($role !== null && $role !== '') {
            $conditions[] = "role = :role";
            $params['role'] = $role;
        }
        if ($search !== '') {
            $conditions[] = "(name LIKE :search_name OR email LIKE :search_email)";
            $params['search_name'] = '%' . $search . '%';
            $params['search_email'] = '%' . $search . '%';
        }
        if ($status === 'aktif') {
            $conditions[] = "is_active = 1";
        } elseif ($status === 'nonaktif') {
            $conditions[] = "is_active = 0";
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT id, name, email, role, is_active, created_at, updated_at FROM users $where ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Create a new user
     */
    public static function create(array $data): ?int {
        $db = Database::getConnection();
        if (!$db) return null;

        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, is_active)
            VALUES (:name, :email, :password, :role, 1)
        ");
        $stmt->execute([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => $data['role'] ?? 'masyarakat',
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Update user details (name, email, role; password via resetPassword)
     */
    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $fields = [];
        $params = ['id' => $id];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params['email'] = $data['email'];
        }
        if (!empty($data['password'])) {
            $fields[] = "password = :password";
            $params['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (isset($data['role'])) {
            $fields[] = "role = :role";
            $params['role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = (int)(bool)$data['is_active'];
        }

        if (empty($fields)) return false;

        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Reset a user's password securely (bcrypt)
     */
    public static function resetPassword(int $id, string $newPassword): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
        return $stmt->execute([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'id'       => $id,
        ]);
    }

    /**
     * Deactivate a user account (soft delete)
     */
    public static function deactivate(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Re-activate a user account
     */
    public static function activate(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Count active admins
     */
    public static function countActiveAdmins(): int {
        $db = Database::getConnection();
        if (!$db) return 0;

        $stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if email is used by another user (excluding $excludeId)
     */
    public static function emailExistsForOther(string $email, int $excludeId): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id LIMIT 1");
        $stmt->execute(['email' => $email, 'id' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Delete user (permanent — do NOT use; prefer deactivate())
     */
    public static function delete(int $id): bool {
        $db = Database::getConnection();
        if (!$db) return false;

        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship: User (Masyarakat) has many Reports
     */
    public static function reports(int $userId): array {
        return Report::getByUser($userId);
    }

    /**
     * Relationship: User (Petugas) has one Officer profile
     */
    public static function officerProfile(int $userId): ?array {
        return Officer::findByUserId($userId);
    }
}
