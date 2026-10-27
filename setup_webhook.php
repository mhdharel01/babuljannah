<?php
// setup_webhook.php
require_once 'config/telegram.php';

// PERHATIAN: Ganti domain ini dengan domain asli Niagahoster Anda
// Contoh: https://ponpesbabuljannah.com/telegram_webhook.php
$webhook_url = "https://" . $_SERVER['HTTP_HOST'] . "/telegram_webhook.php";

$api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook?url=" . urlencode($webhook_url);

$response = file_get_contents($api_url);

echo "<h1>Setup Webhook Telegram</h1>";
echo "<p>URL Webhook: <b>$webhook_url</b></p>";
echo "<hr>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>
