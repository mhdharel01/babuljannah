<?php
// config/telegram.php

require_once __DIR__ . '/env.php';

if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', ''));
}

if (!defined('TELEGRAM_GROUP_ID')) {
    define('TELEGRAM_GROUP_ID', env('TELEGRAM_GROUP_ID', ''));
}

/**
 * Fungsi untuk mengirim pesan ke Grup Telegram
 * 
 * @param string $message Pesan yang akan dikirim
 * @return bool True jika berhasil, False jika gagal
 */
function send_telegram_msg($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $data = [
        'chat_id' => TELEGRAM_GROUP_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'ignore_errors' => true // Membaca response meskipun HTTP code 400/500
        ]
    ];
    
    $context  = stream_context_create($options);
    
    try {
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            return false;
        }
        
        $json = json_decode($result, true);
        return $json['ok'] ?? false;
    } catch (Exception $e) {
        return false;
    }
}
?>
