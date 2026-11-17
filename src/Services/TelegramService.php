<?php
namespace App\Services;

class TelegramService {
    private string $botToken;
    private string $botUsername;
    private ?string $adminChatId;
    private bool $enabled;

    /**
     * Optional callable for testing: fn(string $url, array $payload): array
     * Returns: ['code' => int, 'body' => string, 'curl_error' => ?string]
     */
    private static $mockHttpHandler = null;

    public function __construct(
        ?string $botToken = null,
        ?string $botUsername = null,
        ?string $adminChatId = null,
        ?bool $enabled = null
    ) {
        $this->botToken = $botToken ?? (string)env('TELEGRAM_BOT_TOKEN', '');
        $this->botUsername = $botUsername ?? (string)env('TELEGRAM_BOT_USERNAME', '');
        $this->adminChatId = $adminChatId ?? (string)env('TELEGRAM_ADMIN_CHAT_ID', '');
        $this->enabled = $enabled ?? (bool)env('TELEGRAM_NOTIFICATIONS_ENABLED', false);
    }

    /**
     * Set a mock HTTP handler for automated testing (prevents real network calls)
     */
    public static function setMockHttpHandler(?callable $handler): void {
        self::$mockHttpHandler = $handler;
    }

    public function isEnabled(): bool {
        return $this->enabled && !empty($this->botToken);
    }

    public function getBotUsername(): string {
        return ltrim($this->botUsername, '@');
    }

    public function getAdminChatId(): ?string {
        return !empty($this->adminChatId) ? $this->adminChatId : null;
    }

    /**
     * Generate Telegram Deep Link for account pairing
     */
    public function getDeepLink(string $pairingToken): string {
        $username = $this->getBotUsername();
        if (empty($username)) {
            return '';
        }
        return "https://t.me/{$username}?start=" . urlencode($pairingToken);
    }

    /**
     * Safely escape HTML characters for Telegram HTML parse mode
     */
    public static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Send a message to a specific Telegram chat ID
     * Returns comprehensive structured status
     */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): array {
        if (!$this->isEnabled()) {
            return [
                'success'      => false,
                'http_code'    => 0,
                'error'        => 'Telegram notifications are disabled or bot token is missing.',
                'retry_after'  => null,
                'is_blocked'   => false,
                'is_not_found' => false,
                'message_id'   => null,
            ];
        }

        $chatId = trim($chatId);
        if (empty($chatId)) {
            return [
                'success'      => false,
                'http_code'    => 0,
                'error'        => 'Target Telegram chat_id is empty.',
                'retry_after'  => null,
                'is_blocked'   => false,
                'is_not_found' => false,
                'message_id'   => null,
            ];
        }

        $payload = [
            'chat_id'                  => $chatId,
            'text'                     => $text,
            'parse_mode'               => $parseMode,
            'disable_web_page_preview' => true,
        ];

        return $this->executeApi('sendMessage', $payload);
    }

    /**
     * Get updates from Telegram Bot API (for local long polling)
     */
    public function getUpdates(int $offset = 0, int $timeout = 30): array {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'Telegram notifications are disabled.', 'updates' => []];
        }

        $payload = [
            'offset'          => $offset,
            'timeout'         => $timeout,
            'allowed_updates' => ['message'],
        ];

        $res = $this->executeApi('getUpdates', $payload, $timeout + 5);
        if ($res['success']) {
            return [
                'success' => true,
                'updates' => is_array($res['result']) ? $res['result'] : [],
            ];
        }

        return [
            'success' => false,
            'error'   => $res['error'],
            'updates' => [],
        ];
    }

    /**
     * Execute an API call to Telegram Bot API
     */
    private function executeApi(string $method, array $payload, int $timeout = 10): array {
        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";

        // If mock handler is set (for testing), use it directly
        if (self::$mockHttpHandler !== null) {
            $handler = self::$mockHttpHandler;
            $res = $handler($method, $payload);
            return $this->parseResponse($res['code'] ?? 200, $res['body'] ?? '{}', $res['curl_error'] ?? null);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 seconds connect timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // dynamic timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return $this->parseResponse($httpCode, $response ?: '', $curlError);
    }

    /**
     * Parse and sanitize Telegram API response
     */
    private function parseResponse(int $httpCode, string $body, ?string $curlError): array {
        // Handle cURL connection failure
        if ($curlError !== null && $httpCode === 0) {
            return [
                'success'      => false,
                'http_code'    => 0,
                'error'        => 'cURL error: ' . $this->maskSecret($curlError),
                'retry_after'  => null,
                'is_blocked'   => false,
                'is_not_found' => false,
                'message_id'   => null,
                'result'       => null,
            ];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return [
                'success'      => false,
                'http_code'    => $httpCode,
                'error'        => "Invalid JSON response from Telegram (HTTP {$httpCode}).",
                'retry_after'  => null,
                'is_blocked'   => false,
                'is_not_found' => false,
                'message_id'   => null,
                'result'       => null,
            ];
        }

        $ok = (bool)($data['ok'] ?? false);
        if ($ok) {
            return [
                'success'      => true,
                'http_code'    => $httpCode,
                'error'        => null,
                'retry_after'  => null,
                'is_blocked'   => false,
                'is_not_found' => false,
                'message_id'   => $data['result']['message_id'] ?? null,
                'result'       => $data['result'] ?? null,
            ];
        }

        // Handle error responses
        $description = $data['description'] ?? 'Unknown Telegram API error';
        $lowerDesc = strtolower($description);
        
        $retryAfter = $data['parameters']['retry_after'] ?? null;
        $isBlocked = ($httpCode === 403) || str_contains($lowerDesc, 'blocked') || str_contains($lowerDesc, 'user is deactivated');
        $isNotFound = ($httpCode === 400 && (str_contains($lowerDesc, 'chat not found') || str_contains($lowerDesc, 'chat_id is empty')));

        return [
            'success'      => false,
            'http_code'    => $httpCode,
            'error'        => "Telegram HTTP {$httpCode}: " . $this->maskSecret($description),
            'retry_after'  => $retryAfter ? (int)$retryAfter : null,
            'is_blocked'   => $isBlocked,
            'is_not_found' => $isNotFound,
            'message_id'   => null,
        ];
    }

    /**
     * Clean any bot token pattern from error logs
     */
    private function maskSecret(string $text): string {
        if (!empty($this->botToken)) {
            $text = str_replace($this->botToken, '[PROTECTED_TOKEN]', $text);
        }
        return preg_replace('/bot[0-9]{8,10}:[a-zA-Z0-9_-]{35}/', 'bot[PROTECTED_TOKEN]', $text);
    }
}
