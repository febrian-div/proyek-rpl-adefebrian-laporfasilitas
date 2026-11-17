<?php
namespace App\Services\AI;

interface AIProviderInterface {

    /**
     * Identifier unik provider (e.g. 'nvidia', 'gemini').
     */
    public function getName(): string;

    /**
     * Nama tampilan ramah pengguna (e.g. 'NVIDIA NIM', 'Google Gemini').
     */
    public function getDisplayName(): string;

    /**
     * Model bawaan yang direkomendasikan.
     */
    public function getDefaultModel(): string;

    /**
     * Daftar pilihan model resmi yang populer untuk provider ini.
     */
    public function getAvailableModels(): array;

    /**
     * Mengirim chat completion ke provider AI.
     * Mengembalikan string teks jawaban, atau null jika gagal.
     * Tidak boleh melempar exception ke caller.
     */
    public function chatText(string $systemPrompt, string $userMessage, string $contextSummary = '', array $history = [], array $config = []): ?string;

    /**
     * Menguji konektivitas ke provider API secara aktual.
     * Mengembalikan array dengan format:
     * ['success' => bool, 'message' => string, 'details' => ?array]
     */
    public function testConnection(array $config): array;

    /**
     * Mengambil daftar model langsung dari API provider jika didukung.
     */
    public function fetchModels(array $config): array;
}
