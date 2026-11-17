<?php
namespace App\Services\AI;

class NvidiaProviderAdapter implements AIProviderInterface {

    public function getName(): string {
        return 'nvidia';
    }

    public function getDisplayName(): string {
        return 'NVIDIA (NVIDIA NIM)';
    }

    public function getDefaultModel(): string {
        return 'meta/llama-3.1-8b-instruct';
    }

    public function getAvailableModels(): array {
        return [
            'meta/llama-3.1-8b-instruct'        => 'Meta Llama 3.1 8B Instruct (Cepat & Direkomendasikan)',
            'meta/llama-3.1-70b-instruct'       => 'Meta Llama 3.1 70B Instruct (Akurasi Tinggi)',
            'meta/llama-3.3-70b-instruct'       => 'Meta Llama 3.3 70B Instruct (Model Terbaru)',
            'mistralai/mistral-7b-instruct-v0.3'=> 'Mistral 7B Instruct v0.3',
            'mistralai/mixtral-8x7b-instruct-v0.1' => 'Mixtral 8x7B Instruct',
            'nvidia/nemotron-4-340b-instruct'   => 'NVIDIA Nemotron-4 340B Instruct',
            'nvidia/nemotron-3-super-120b-a12b' => 'NVIDIA Nemotron-3 Super',
        ];
    }

    public function chatText(string $systemPrompt, string $userMessage, string $contextSummary = '', array $history = [], array $config = []): ?string {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            error_log('[NvidiaAdapter] API Key kosong.');
            return null;
        }

        $model = trim((string)($config['model'] ?? ''));
        if ($model === '') $model = $this->getDefaultModel();

        $baseUrl = rtrim((string)($config['base_url'] ?? 'https://integrate.api.nvidia.com/v1'), '/');
        if ($baseUrl === '') $baseUrl = 'https://integrate.api.nvidia.com/v1';

        $temperature = isset($config['temperature']) ? (float)$config['temperature'] : 0.40;
        $maxTokens = isset($config['max_tokens']) ? (int)$config['max_tokens'] : 600;

        // Susun messages OpenAI-compatible
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($contextSummary !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => "KONTEKS DATA AKTUAL DARI DATABASE (sumber kebenaran, jangan karang di luar ini):\n" . $contextSummary
            ];
        }

        foreach (array_slice($history, -6) as $h) {
            $messages[] = [
                'role'    => ($h['role'] ?? 'user') === 'bot' ? 'assistant' : 'user',
                'content' => mb_substr((string)($h['content'] ?? ''), 0, 500),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        try {
            $ch = curl_init($baseUrl . '/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
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
                $text = $data['choices'][0]['message']['content'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }

            error_log('[NvidiaAdapter] HTTP ' . $httpCode . ' model=' . $model . ' error=' . ($curlError ?: 'response payload issue'));
            return null;
        } catch (\Throwable $e) {
            error_log('[NvidiaAdapter] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function testConnection(array $config): array {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'API Key NVIDIA belum diisi. Harap masukkan API Key terlebih dahulu.',
            ];
        }

        $model = trim((string)($config['model'] ?? ''));
        if ($model === '') $model = $this->getDefaultModel();

        $baseUrl = rtrim((string)($config['base_url'] ?? 'https://integrate.api.nvidia.com/v1'), '/');
        if ($baseUrl === '') $baseUrl = 'https://integrate.api.nvidia.com/v1';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Kamu adalah asisten penguji koneksi API.'],
                ['role' => 'user', 'content' => 'Tes koneksi API. Jawab dengan satu kata: Terhubung.']
            ],
            'temperature' => 0.1,
            'max_tokens' => 20,
        ];

        try {
            $ch = curl_init($baseUrl . '/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
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
                    'message' => 'Gagal menghubungi server NVIDIA: ' . htmlspecialchars($curlError),
                ];
            }

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $reply = trim((string)($data['choices'][0]['message']['content'] ?? ''));
                return [
                    'success' => true,
                    'message' => "Koneksi ke NVIDIA API berhasil! Model '{$model}' merespons dengan baik.",
                    'details' => [
                        'http_code' => 200,
                        'model'     => $model,
                        'reply'     => $reply,
                    ],
                ];
            }

            if ($httpCode === 401 || $httpCode === 403) {
                return [
                    'success' => false,
                    'message' => 'Autentikasi gagal: API Key NVIDIA tidak valid atau tidak memiliki izin akses (HTTP ' . $httpCode . ').',
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success' => false,
                    'message' => "Model '{$model}' tidak ditemukan atau endpoint NVIDIA tidak valid (HTTP 404).",
                ];
            }

            $errorMsg = 'Terjadi kesalahan pada respon server NVIDIA (HTTP ' . $httpCode . ').';
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
                'message' => 'Terjadi kesalahan internal saat menguji koneksi NVIDIA: ' . $e->getMessage(),
            ];
        }
    }

    public function fetchModels(array $config): array {
        $apiKey = trim((string)($config['api_key'] ?? ''));
        if ($apiKey === '') return array_keys($this->getAvailableModels());

        $baseUrl = rtrim((string)($config['base_url'] ?? 'https://integrate.api.nvidia.com/v1'), '/');
        if ($baseUrl === '') $baseUrl = 'https://integrate.api.nvidia.com/v1';

        try {
            $ch = curl_init($baseUrl . '/models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['data']) && is_array($data['data'])) {
                    $list = [];
                    foreach ($data['data'] as $item) {
                        if (!empty($item['id'])) {
                            $list[] = $item['id'];
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
