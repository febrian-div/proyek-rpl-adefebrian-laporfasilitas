<?php
namespace App\Services;

class AIService {

    /**
     * Chat completion untuk chatbot LaporFasilitas (text-only).
     * Terintegrasi dengan AIProviderManager (NVIDIA NIM, Google Gemini, dll).
     * Membaca konfigurasi aktif dari database secara dinamis.
     * Return string jawaban atau null jika gagal. Tidak pernah melempar exception ke caller.
     */
    public function chatText(string $systemPrompt, string $userMessage, string $contextSummary = '', array $history = []): ?string {
        try {
            $response = \App\Services\AI\AIProviderManager::chatText($systemPrompt, $userMessage, $contextSummary, $history);
            if (is_string($response) && trim($response) !== '') {
                return trim($response);
            }
        } catch (\Throwable $e) {
            error_log('[AIService::chatText] Provider error: ' . $e->getMessage());
        }

        // Fallback backward-compatible ke konfigurasi environment jika database kosong
        $provider = strtolower((string)env('AI_PROVIDER', 'mock'));

        if ($provider === 'nvidia') {
            $out = $this->chatViaNvidia($systemPrompt, $userMessage, $contextSummary, $history);
            if (is_string($out) && $out !== '') return $out;
        }

        if ($provider === 'openai') {
            $out = $this->chatViaOpenAI($systemPrompt, $userMessage, $contextSummary, $history);
            if (is_string($out) && $out !== '') return $out;
        }

        if ($provider === 'gemini' || $provider === 'mock' || $provider === '') {
            $out = $this->chatViaGemini($systemPrompt, $userMessage, $contextSummary, $history);
            if (is_string($out) && $out !== '') return $out;
        }

        return null;
    }

    private function chatViaGemini(string $systemPrompt, string $userMessage, string $contextSummary, array $history): ?string {
        $apiKey = (string)env('GEMINI_API_KEY', '');
        if ($apiKey === '') return null;

        $model = (string)env('AI_MODEL', env('GEMINI_MODEL', 'gemini-1.5-flash'));
        if ($model === '') $model = 'gemini-1.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

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
            'contents' => [[ 'parts' => [[ 'text' => $fullPrompt ]] ]],
            'generationConfig' => [ 'temperature' => 0.4, 'maxOutputTokens' => 600 ],
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($text) && trim($text) !== '') return trim($text);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Chat completion via NVIDIA NIM (OpenAI-compatible API).
     * Config: NVIDIA_API_KEY, NVIDIA_MODEL, NVIDIA_BASE_URL.
     * Key hanya dipakai server-side di header Authorization, tidak pernah
     * dikirim ke frontend, response, atau log.
     */
    private function chatViaNvidia(string $systemPrompt, string $userMessage, string $contextSummary, array $history): ?string {
        $apiKey = (string)env('NVIDIA_API_KEY', '');
        if ($apiKey === '') {
            error_log('[AIService:nvidia] NVIDIA_API_KEY kosong.');
            return null;
        }

        $model = (string)env('AI_MODEL', env('NVIDIA_MODEL', 'meta/llama-3.1-8b-instruct'));
        if ($model === '' || str_starts_with($model, 'gemini') || str_starts_with($model, 'gpt-')) {
            $model = 'meta/llama-3.1-8b-instruct';
        }

        $baseUrl = rtrim((string)env('NVIDIA_BASE_URL', 'https://integrate.api.nvidia.com/v1'), '/');
        if ($baseUrl === '') $baseUrl = 'https://integrate.api.nvidia.com/v1';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($contextSummary !== '') {
            $messages[] = ['role' => 'system', 'content' => 'KONTEKS DATA AKTUAL DARI DATABASE (sumber kebenaran, jangan karang di luar ini):' . "\n" . $contextSummary];
        }
        foreach (array_slice($history, -6) as $h) {
            $messages[] = [
                'role' => ($h['role'] ?? 'user') === 'bot' ? 'assistant' : 'user',
                'content' => mb_substr((string)($h['content'] ?? ''), 0, 500),
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $ch = curl_init($baseUrl . '/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 600,
            ], JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? null;
                if (is_string($text) && trim($text) !== '') return trim($text);
            }

            // Laporkan error aktual (termasuk model ditolak/tidak tersedia) ke server log saja.
            // Tidak ada API key dalam log ini — hanya kode HTTP, nama model, dan potongan body error.
            error_log('[AIService:nvidia] HTTP ' . $httpCode . ' model=' . $model . ' body=' . mb_substr((string)$response, 0, 500));
        } catch (\Throwable $e) {
            error_log('[AIService:nvidia] exception: ' . $e->getMessage());
            return null;
        }
        return null;
    }

    private function chatViaOpenAI(string $systemPrompt, string $userMessage, string $contextSummary, array $history): ?string {
        $apiKey = (string)env('OPENAI_API_KEY', '');
        if ($apiKey === '') return null;

        $model = (string)env('AI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini'));
        if ($model === '' || str_starts_with($model, 'gemini')) $model = 'gpt-4o-mini';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($contextSummary !== '') {
            $messages[] = ['role' => 'system', 'content' => 'KONTEKS DATA AKTUAL DARI DATABASE (sumber kebenaran, jangan karang di luar ini):' . "\n" . $contextSummary];
        }
        foreach (array_slice($history, -6) as $h) {
            $messages[] = [
                'role' => ($h['role'] ?? 'user') === 'bot' ? 'assistant' : 'user',
                'content' => mb_substr((string)($h['content'] ?? ''), 0, 500),
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 600,
            ], JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $text = $data['choices'][0]['message']['content'] ?? null;
                if (is_string($text) && trim($text) !== '') return trim($text);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }
    
    /**
     * Analyze a report's photo and description using Gemini AI.
     * Returns a JSON string containing the keys: facility_category, damage_type, severity, confidence.
     * Or null if it fails.
     */
    public function analyzeReport(string $imageAbsPath, string $description, string $title): ?string {
        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            return null; // Silent fail if no API key
        }

        if (!file_exists($imageAbsPath)) {
            return null; // Silent fail if image not found
        }

        try {
            $imageData = base64_encode(file_get_contents($imageAbsPath));
            
            // Get mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imageAbsPath);
            finfo_close($finfo);

            $prompt = "Kamu adalah sistem klasifikasi fasilitas umum (LaporFasilitas).
Tugasmu adalah menganalisis foto kerusakan berikut beserta judul dan deskripsinya.
Judul Laporan: $title
Deskripsi Laporan: $description

Keluarkan output HANYA dalam format JSON baku tanpa markdown block, berisi tepat field-field berikut:
{
  \"facility_category\": \"Pilih salah satu: Jalan, Lampu Jalan, Trotoar, Taman, Tempat Sampah, Saluran Air, Lainnya\",
  \"damage_type\": \"Deskripsi singkat jenis kerusakan (contoh: Jalan Berlubang)\",
  \"severity\": \"Pilih salah satu: Rendah, Sedang, Tinggi, Darurat\",
  \"confidence\": \"Tingkat keyakinanmu berupa angka float antara 0.0 sampai 1.0\"
}";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType ?: 'image/jpeg',
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json'
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 seconds timeout
            
            // Bypass SSL verification for local dev if needed, though not recommended for production
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $jsonResult = trim($data['candidates'][0]['content']['parts'][0]['text']);
                    // Attempt to decode to ensure it's valid JSON before returning
                    $parsed = json_decode($jsonResult, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $jsonResult;
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            // Silently catch exceptions to avoid breaking report submission
            return null;
        }
    }
}
