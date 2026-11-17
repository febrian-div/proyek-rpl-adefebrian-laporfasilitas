<?php
namespace App\Commands;

/**
 * CLI Command: Fetch and Configure Telegram Admin Chat ID
 * Usage: php src/Commands/FetchAdminChatId.php
 */

@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$rootPath = dirname(__DIR__, 2);
$envPath = $rootPath . '/.env';

if (!file_exists($envPath)) {
    echo "[ERROR] File .env tidak ditemukan.\n";
    exit(1);
}

// 1. Read .env safely without printing secrets
$envLines = file($envPath, FILE_IGNORE_NEW_LINES);
$env = [];
foreach ($envLines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) continue;
    if (str_contains($line, '=')) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$botToken = $env['TELEGRAM_BOT_TOKEN'] ?? '';
$botUsername = $env['TELEGRAM_BOT_USERNAME'] ?? '';

if (empty($botToken)) {
    echo "[ERROR] TELEGRAM_BOT_TOKEN belum terisi di .env.\n";
    exit(1);
}

// 2. Test bot connection via getMe
$ch = curl_init("https://api.telegram.org/bot{$botToken}/getMe");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$res = curl_exec($ch);
curl_close($ch);

$me = json_decode($res, true);
if (!($me['ok'] ?? false)) {
    echo "[ERROR] Bot token tidak valid atau tidak dapat terhubung ke Telegram API.\n";
    exit(1);
}

$botName = $me['result']['username'] ?? $botUsername;

// 3. Fetch updates from Telegram (long-polling if needed)
echo "======================================================\n";
echo " Memeriksa pesan /start di Telegram Bot: @{$botName}\n";
echo "======================================================\n";

$ch = curl_init("https://api.telegram.org/bot{$botToken}/getUpdates?timeout=10&limit=50");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$results = $data['result'] ?? [];

$adminChatId = null;
$senderName = '';
$messageText = '';

if (!empty($results)) {
    // Find latest /start or latest message
    for ($i = count($results) - 1; $i >= 0; $i--) {
        $msg = $results[$i]['message'] ?? $results[$i]['channel_post'] ?? null;
        if (!$msg || !isset($msg['chat']['id'])) continue;

        $cId = (string)$msg['chat']['id'];
        $text = trim($msg['text'] ?? '');
        $name = trim(($msg['chat']['first_name'] ?? '') . ' ' . ($msg['chat']['last_name'] ?? ''));
        if (empty($name)) $name = $msg['chat']['title'] ?? 'User';

        if (str_starts_with($text, '/start')) {
            $adminChatId = $cId;
            $senderName = $name;
            $messageText = $text;
            break;
        } elseif ($adminChatId === null) {
            $adminChatId = $cId;
            $senderName = $name;
            $messageText = $text;
        }
    }
}

if ($adminChatId === null) {
    echo "[INFO] Belum ada pesan /start baru yang terdeteksi dari Telegram.\n";
    echo "Penyebab: Telegram server hanya menyimpan pesan selama 24 jam.\n";
    echo "Langkah: Buka Telegram -> cari @{$botName} -> kirim /start -> jalankan perintah ini kembali.\n";
    exit(2);
}

echo "[SUKSES] Pesan terdeteksi!\n";
echo "- Chat ID : {$adminChatId}\n";
echo "- Pengirim: {$senderName}\n";
echo "- Pesan   : {$messageText}\n\n";

// 4. Create backup of .env before updating
$backupDir = $rootPath . '/backup_telegram_unit';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
$backupFile = $backupDir . '/.env.backup_' . date('Ymd_His') . '.bak';
copy($envPath, $backupFile);
echo "[BACKUP] File .env telah dibackup ke: " . basename($backupFile) . "\n";

// 5. Update TELEGRAM_ADMIN_CHAT_ID in .env
$updatedContent = [];
$found = false;
foreach ($envLines as $line) {
    if (preg_match('/^TELEGRAM_ADMIN_CHAT_ID\s*=/i', $line)) {
        $updatedContent[] = "TELEGRAM_ADMIN_CHAT_ID={$adminChatId}";
        $found = true;
    } else {
        $updatedContent[] = $line;
    }
}

if (!$found) {
    $updatedContent[] = "TELEGRAM_ADMIN_CHAT_ID={$adminChatId}";
}

file_put_contents($envPath, implode("\n", $updatedContent));
echo "[UPDATE] TELEGRAM_ADMIN_CHAT_ID berhasil disimpan di .env.\n";
echo "======================================================\n";

