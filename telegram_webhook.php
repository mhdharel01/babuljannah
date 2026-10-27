<?php
// telegram_webhook.php
require_once 'config/db.php';
require_once 'config/telegram.php';

// Daftar Chat ID yang diizinkan (Keamanan)
$allowed_chats = [
    TELEGRAM_GROUP_ID, 
    '1817003341' // ID Pribadi Admin (Muhammad Harel)
];

// Ambil input JSON dari Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

// ==========================================
// FUNGSI HELPER
// ==========================================

// Fungsi membalas pesan baru
function reply_msg($chat_id, $msg, $inline_keyboard = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];
    if ($inline_keyboard !== null) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $inline_keyboard]);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

// Fungsi edit pesan (saat tombol dipencet)
function edit_msg($chat_id, $message_id, $msg, $inline_keyboard = null) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/editMessageText";
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $msg,
        'parse_mode' => 'HTML'
    ];
    if ($inline_keyboard !== null) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $inline_keyboard]);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

// ==========================================
// ROUTING UPDATE (TEXT / BUTTON CLICK)
// ==========================================

$cmd = null;
$chat_id = null;
$message_id = null;
$is_callback = false;

// 1. Jika ini adalah pesan teks biasa (Ketik Manual)
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $text = trim($update["message"]["text"] ?? '');
    
    // Keamanan: Tolak jika ID tidak terdaftar
    if (!in_array((string)$chat_id, $allowed_chats)) {
        exit;
    }
    
    $cmd = strtolower($text);

// 2. Jika ini adalah klik tombol (Inline Keyboard Callback)
} elseif (isset($update["callback_query"])) {
    $chat_id = $update["callback_query"]["message"]["chat"]["id"];
    $message_id = $update["callback_query"]["message"]["message_id"];
    $cmd = $update["callback_query"]["data"];
    $is_callback = true;
    
    // Keamanan: Tolak jika ID tidak terdaftar
    if (!in_array((string)$chat_id, $allowed_chats)) {
        exit;
    }
    
    // Wajib lapor ke Telegram bahwa klik tombol sudah diterima agar loading spinner hilang
    $ans_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/answerCallbackQuery";
    $ans_data = ['callback_query_id' => $update["callback_query"]["id"]];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ans_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($ans_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
} else {
    exit;
}

// ==========================================
// COMMAND LOGIC PROCESSING
// ==========================================

if ($cmd) {
    
    // MENU UTAMA
    if ($cmd === '/menu' || $cmd === '/start' || $cmd === 'cmd_menu') {
        $reply = "🤖 <b>Sistem PSB Babul Jannah</b>\n\n";
        $reply .= "Halo! Saya adalah asisten virtual pendaftaran santri baru. Silakan pilih menu di bawah ini dengan menekan tombol:";
        
        $keyboard = [
            [
                ['text' => '📊 Rekap Total', 'callback_data' => 'cmd_rekap'],
                ['text' => '🆕 3 Terbaru', 'callback_data' => 'cmd_terbaru']
            ],
            [
                ['text' => '🌐 Buka Web Admin', 'url' => 'https://ponpesbabuljannah.com/admin/login.php']
            ]
        ];
        
        if ($is_callback && $cmd === 'cmd_menu') {
            edit_msg($chat_id, $message_id, $reply, $keyboard);
        } else {
            reply_msg($chat_id, $reply, $keyboard);
        }
    }
    
    // REKAP
    elseif ($cmd === '/rekap' || $cmd === '/rekap@PSBBabulJannahBot' || $cmd === 'cmd_rekap') {
        global $db;
        try {
            $smp = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE jalur_pendaftaran LIKE '%SMP%'")->fetchColumn();
            $sma = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE jalur_pendaftaran LIKE '%SMA%'")->fetchColumn();
            $lunas = $db->query("SELECT COUNT(*) FROM pendaftaran WHERE status_pembayaran = 'Lunas'")->fetchColumn();
            
            $reply = "📊 <b>REKAP PENDAFTARAN PSB</b>\n";
            $reply .= "--------------------------------------\n";
            $reply .= "Total SMP: <b>$smp Santri</b>\n";
            $reply .= "Total SMA: <b>$sma Santri</b>\n";
            $reply .= "Sudah Lunas Biaya Tes: <b>$lunas Orang</b>\n";
            
            $keyboard = null;
            if ($is_callback) {
                // Beri tombol "Kembali"
                $keyboard = [
                    [['text' => '🔙 Kembali ke Menu', 'callback_data' => 'cmd_menu']]
                ];
                edit_msg($chat_id, $message_id, $reply, $keyboard);
            } else {
                reply_msg($chat_id, $reply);
            }
        } catch (Exception $e) {
            // Abaikan error di webhook
        }
    }
    
    // TERBARU
    elseif ($cmd === '/terbaru' || $cmd === '/terbaru@PSBBabulJannahBot' || $cmd === 'cmd_terbaru') {
        global $db;
        try {
            $stmt = $db->query("SELECT nama_santri, jalur_pendaftaran, status_pembayaran FROM pendaftaran ORDER BY id DESC LIMIT 3");
            $data = $stmt->fetchAll();
            
            if (count($data) > 0) {
                $reply = "🆕 <b>3 Pendaftar Terakhir:</b>\n\n";
                foreach ($data as $i => $row) {
                    $no = $i + 1;
                    $bayar = $row['status_pembayaran'] == 'Lunas' ? '✅ Lunas' : '⏳ Belum';
                    $reply .= "<b>$no. " . $row['nama_santri'] . "</b>\n";
                    $reply .= "└ " . $row['jalur_pendaftaran'] . " (" . $bayar . ")\n\n";
                }
            } else {
                $reply = "Belum ada pendaftar.";
            }
            
            $keyboard = null;
            if ($is_callback) {
                $keyboard = [
                    [['text' => '🔙 Kembali ke Menu', 'callback_data' => 'cmd_menu']]
                ];
                edit_msg($chat_id, $message_id, $reply, $keyboard);
            } else {
                reply_msg($chat_id, $reply);
            }
        } catch (Exception $e) {
            // Abaikan error di webhook
        }
    }
}
?>
