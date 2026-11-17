<?php
namespace App\Controllers;

use App\Helpers\Response;
use App\Services\NotificationService;
use App\Services\TelegramService;

/**
 * TelegramWebhookController — Menerima update dari Telegram Bot API via webhook.
 *
 * Endpoint ini hanya untuk menerima notifikasi dari Telegram. Tidak memerlukan
 * login pengguna karena request berasal dari server Telegram. Keselamatan
 * dijamin melalui validasi header X-Telegram-Bot-Api-Secret-Token.
 *
 * Endpoint memproses perintah /start TOKEN dari bot untuk pairing akun pengguna
 * dengan akun Telegram.
 */
class TelegramWebhookController {

    /**
     * Handle webhook update dari Telegram.
     *
     * @return void
     */
    public function handle(): void {
        // Hanya menerima metode POST
        if (( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST') {
            Response::json([
                'error' => 'Method Not Allowed',
                'message' => 'Hanya metode POST yang diperbolehkan.',
            ], 405);
        }

        // Validasi secret token dari header
        $secretToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        $configuredSecret = ( string ) env( 'TELEGRAM_WEBHOOK_SECRET', '' );

        if ( $configuredSecret === '' || ! hash_equals( $configuredSecret, $secretToken ) ) {
            http_response_code( 403 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo json_encode( [
                'error' => 'Forbidden',
                'message' => 'Secret token webhook tidak valid atau belum dikonfigurasi.',
            ] );
            exit;
        }

        // Baca input JSON
        $rawInput = file_get_contents( 'php://input' );
        if ( $rawInput === false || $rawInput === '' ) {
            Response::json([
                'error' => 'Bad Request',
                'message' => 'Body request kosong.',
            ], 400);
        }

        $update = json_decode( $rawInput, true );
        if ( $update === null || ! is_array( $update ) ) {
            Response::json([
                'error' => 'Bad Request',
                'message' => 'Format JSON tidak valid.',
            ], 400);
        }

        // Ambil pesan dari update
        $message = $update['message'] ?? null;
        if ( ! is_array( $message ) ) {
            // Update tidak relevan (misalnya edited_message, channel_post, dll.)
            // Tetap beri respons 200 agar Telegram tidak retry.
            Response::json([
                'status' => 'ignored',
                'message' => 'Update tidak relevan untuk diproses.',
            ], 200);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        // Jika tidak ada chat_id atau text, abaikan
        if ( empty( $chatId ) || empty( $text ) ) {
            Response::json([
                'status' => 'ignored',
                'message' => 'Pesan tidak memiliki chat_id atau teks.',
            ], 200);
        }

        // Proses perintah /start TOKEN
        // Format yang didukung:
        // /start TOKEN
        // /start@NamaBot TOKEN
        $command = null;
        $token = null;

        if ( preg_match( '/^\/start(?:@[a-zA-Z0-9_]+)?\s+(.+)$/', $text, $matches ) ) {
            $command = '/start';
            $token = trim( $matches[1] );
        }

        if ( $command === null || empty( $token ) ) {
            // Bukan perintah /start yang valid, abaikan
            Response::json([
                'status' => 'ignored',
                'message' => 'Bukan perintah pairing yang valid.',
            ], 200);
        }

        // Panggil service untuk verifikasi dan link chat_id
        $telegram = new TelegramService();
        $notif = new NotificationService($telegram);

        $result = $notif->verifyAndLinkTelegram($token, (string)$chatId);

        if ($result['success']) {
            Response::json([
                'status' => 'paired',
                'message' => 'Akun berhasil dipasangkan.',
            ], 200);
        } else {
            // Pairing gagal, tapi tetap beri 200 ke Telegram agar tidak retry
            Response::json([
                'status' => 'pairing_failed',
                'message' => $result['message'] ?? 'Pairing gagal.',
            ], 200);
        }
    }
}
