<?php
namespace App\Services\AI;

class GeminiProviderAdapter implements AIProviderInterface {

    public function getName(): string {
        return 'gemini';
    }

    public function getDisplayName(): string {
        return 'Google Gemini';
    }

    public function getDefaultModel(): string {
        return 'gemini-1.5-flash';
    }

    public function getAvailableModels(): array {
        return [
            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Cepat, Efisien & Direkomendasikan)',
            'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Kemampuan Penalaran Kompleks)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash (Eksperimental/Terbaru)',
            'gemini-1.0-pro'   => 'Gemini 1.0 Pro (Klasik)',
        ];
    }

    public function chatText(string $systemPrompt, string $userMessage, string $contextSummary = '', array $history = [], array $config = []): ?string {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            error_log('[GeminiAdapter] API Key kosong.');
            return null;
        }

        $model = trim((string)($config['model'] ?? ''));
        if ($model === '') $model = $this->getDefaultModel();

        $temperature = isset($config['temperature']) ? (float)$config['temperature'] : 0.40;
        $maxTokens = isset($config['max_tokens']) ? (int)$config['max_tokens'] : 600;

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

        $fullPrompt = $systemPrompt . "\n\n";
        if ($contextSummary !== '') {
            $fullPrompt .= "KONTEKS DATA AKTUAL DARI DATABASE (sumber kebenaran, jangan karang di luar ini):\n" . $contextSummary . "\n\n";
        }

        if (!empty($history)) {
            $sliced = array_slice($history, -6);
            $fullPrompt .= "RIWAYAT PERCAKAPAN TERAKHIR:\n";
            foreach ($sliced as $h) {
                $r = ($h['role'] ?? 'user') === 'bot' ? 'Asisten' : 'Pengguna';
                $c = mb_substr((string)($h['content'] ?? ''), 0, 500);
                $fullPrompt .= $r . ': ' . $c . "\n";
            }
            $fullPrompt .= "\n";
        }

        $fullPrompt .= 'PERTANYAAN PENGGUNA: ' . $userMessage;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }

            error_log('[GeminiAdapter] HTTP ' . $httpCode . ' model=' . $model . ' error=' . ($curlError ?: 'response payload issue'));
            return null;
        } catch (\Throwable $e) {
            error_log('[GeminiAdapter] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function testConnection(array $config): array {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'API Key Google Gemini belum diisi. Harap masukkan API Key terlebih dahulu.',
            ];
        }

        $model = trim((string)($config['model'] ?? ''));
        if ($model === '') $model = $this->getDefaultModel();

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Tes koneksi API. Jawab dengan satu kata: Terhubung.']
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 20,
            ],
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return [
                    'success' => false,
                    'message' => 'Gagal menghubungi server Google Gemini: ' . htmlspecialchars($curlError),
                ];
            }

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $reply = trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
                return [
                    'success' => true,
                    'message' => "Koneksi ke Google Gemini API berhasil! Model '{$model}' merespons dengan baik.",
                    'details' => [
                        'http_code' => 200,
                        'model'     => $model,
                        'reply'     => $reply,
                    ],
                ];
            }

            if ($httpCode === 400 || $httpCode === 403) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi gagal: API Key Google Gemini tidak valid atau tidak memiliki izin akses (HTTP ' . $httpCode . ').',
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success' => false,
                    'message' => "Model '{$model}' tidak ditemukan atau belum tersedia di region Anda (HTTP 404).",
                ];
            }

            $errorMsg = 'Terjadi kesalahan pada respon server Google Gemini (HTTP ' . $httpCode . ').';
            if ($response) {
                $decoded = json_decode($response, true);
                if (!empty($decoded['error']['message'])) {
                    $errorMsg .= ' Pesan: ' . htmlspecialchars($decoded['error']['message']);
                }
            }

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan internal saat menguji koneksi Google Gemini: ' . $e->getMessage(),
            ];
        }
    }

    public function fetchModels(array $config): array {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') return array_keys($this->getAvailableModels());

        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['models']) && is_array($data['models'])) {
                    $list = [];
                    foreach ($data['models'] as $m) {
                        $name = str_replace('models/', '', (string)($m['name'] ?? ''));
                        // Hanya model yang mendukung generateContent
                        $methods = $m['supportedGenerationMethods'] ?? [];
                        if (in_array('generateContent', $methods, true)) {
                            $list[] = $name;
                        }
                    }
                    if (!empty($list)) {
                        sort($list);
                        return $list;
                    }
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        return array_keys($this->getAvailableModels());
    }
}
