<?php
define('TELEGRAM_BOT_TOKEN', '7126100332:AAFH0rYgC_uQSlLc1BdPPmwD4FxVz30sxs8');
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_bot');
define('DB_USER', 'root');
define('DB_PASS', '');

function connectDB() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
    return new PDO($dsn, DB_USER, DB_PASS);
}

function getUpdates($offset) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getUpdates?offset=" . $offset;
    return json_decode(file_get_contents($url), true);
}

// Отправка сообщения пользователю
function sendMessage($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . $chat_id . "&text=" . urlencode($text);
    file_get_contents($url);
}

// Обработка входящих сообщений
function handleMessage($message) {
    $chat_id = $message['chat']['id'];
    $text = trim($message['text']);

    $db = connectDB();

    // Проверка существует ли пользователь
    $exist = $db->prepare("SELECT * FROM users WHERE chat_id = :chat_id");
    $exist->execute(['chat_id' => $chat_id]);
    $user = $exist->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $exist = $db->prepare("INSERT INTO users (chat_id) VALUES (:chat_id)");
        $exist->execute(['chat_id' => $chat_id]);
        $balance = 0.00;
        sendMessage($chat_id, "Здравствуйте, Ваш баланс = $" . number_format($balance, 2));
    } else {
        $balance = $user['balance'];
        
        if (preg_match('/^-?\d+([.,]\d+)?$/', $text)) {
            $amount = (float)str_replace(',', '.', $text);
            
            // Обновление баланса
            if ($amount < 0 && abs($amount) > $balance) {
                sendMessage($chat_id, "Недостаточно средств на балансе.");
            } else {
                $new_balance = $balance + $amount;
                $exist = $db->prepare("UPDATE users SET balance = :balance WHERE chat_id = :chat_id");
                $exist->execute(['balance' => $new_balance, 'chat_id' => $chat_id]);
                sendMessage($chat_id, "Ваш баланс =  $" . number_format($new_balance, 2));
            }
        } else {
            sendMessage($chat_id, "Для изменения баланса отправьте число.");
        }
    }
}

$offset = 0;
while (true) {
    $updates = getUpdates($offset);
    
    foreach ($updates['result'] as $update) {
        if (isset($update['message'])) {
            handleMessage($update['message']);
            $offset = $update['update_id'] + 1;
        }
    }
    
    sleep(1);
}
