<?php
namespace App\Helpers;

class Response {
    /**
     * Send JSON response
     */
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to path
     */
    public static function redirect(string $path): void {
        if (!preg_match('#^https?://#i', $path) && function_exists('base_url')) {
            $path = base_url($path);
        }
        header("Location: {$path}");
        exit;
    }

    /**
     * Flash message helper
     */
    public static function setFlash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash'] = [
            'type' => $type, // success, error, warning, info
            'message' => $message,
        ];
    }

    public static function getFlash(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}
