<?php
// Сообщаем браузеру, что ответ будет в формате JSON
header('Content-Type: application/json; charset=utf-8');

// Подключаем базу данных
require_once 'db.php';

// Проверяем, что запрос пришел правильным методом (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем и очищаем данные из формы
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $org = trim($_POST['org'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Простая проверка (валидация) на сервере
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Не заполнены обязательные поля']);
        exit;
    }

    // === СОХРАНЕНИЕ В БАЗУ ДАННЫХ ===
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO requests (name, phone, org, position, service, message) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $phone, $org, $position, $service, $message]);
    } catch (\PDOException $e) {
        // Если произошла ошибка при сохранении в БД
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера при сохранении заявки.']);
        exit;
    }

    // === НАСТРОЙКИ ДЛЯ ОТПРАВКИ НА ПОЧТУ ===
    // Укажите почту, на которую должны приходить заявки
    $to = 'workplace.07@bk.ru'; 
    $subject = 'Новая заявка с сайта EKstudio';
    
    $emailBody = "Получена новая заявка с сайта:\n\n";
    $emailBody .= "ФИО: $name\n";
    $emailBody .= "Телефон: $phone\n";
    $emailBody .= "Организация: " . ($org ?: 'Не указана') . "\n";
    $emailBody .= "Должность: " . ($position ?: 'Не указана') . "\n";
    $emailBody .= "Интересующая услуга: " . ($service ?: 'Не выбрана') . "\n";
    $emailBody .= "Сообщение:\n" . ($message ?: 'Не указано') . "\n";

    // Технические заголовки письма (чтобы почта не улетала в спам)
    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n" .
               "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    // Отправка письма (на реальном хостинге отправит email)
    $mailSent = @mail($to, $subject, $emailBody, $headers);
    
    // === ОТПРАВКА ВКонтакте (ОПЦИОНАЛЬНО) ===
    // 1. Токен группы ВК (Настройки группы -> Работа с API -> Создать ключ)
    $vk_token = 'vk1.a.u6FyatZcJDQ_qNt4w-XzepmwBg8dWGojjnT8lWR15ldWlF3QQ292-BmNROLHBV4Hm4XgZTgttftnLvwfEjYO3U2sr4mVxeOG9ysdGCYj5SjQwaiZBfWcZXLar9X8FAfdjyDB1LUA-cj94A3tsT71OHGiW-HRuAaTttBn8kfB1oq3-3vJ8OT-heSSlVyitmcuguiEkUWulOrUUMioPSAlWQ'; 
    // 2. Цифровой ID пользователя ВК, которому отправлять заявку
    $vk_user_id = '497673370'; 
    
    if (!empty($vk_token) && !empty($vk_user_id)) {
        $vk_text = "🔴 Новая заявка с сайта EKstudio!\n\n";
        $vk_text .= "👤 Имя: " . htmlspecialchars($name) . "\n";
        $vk_text .= "📞 Телефон: " . htmlspecialchars($phone) . "\n";
        if ($org) $vk_text .= "🏢 Организация: " . htmlspecialchars($org) . "\n";
        if ($position) $vk_text .= "💼 Должность: " . htmlspecialchars($position) . "\n";
        if ($service) $vk_text .= "🛠 Услуга: " . htmlspecialchars($service) . "\n";
        if ($message) $vk_text .= "💬 Комментарий:\n" . htmlspecialchars($message) . "\n";

        $vk_params = [
            'user_id' => $vk_user_id,
            'message' => $vk_text,
            'random_id' => rand(100000000, 999999999), // Обязательный параметр ВК для защиты от дублей
            'access_token' => $vk_token,
            'v' => '5.131' // Версия API
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type:application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($vk_params)
            ]
        ];
        @file_get_contents('https://api.vk.com/method/messages.send', false, stream_context_create($options));
    }

    // Сообщаем скрипту на сайте, что всё прошло успешно
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
}