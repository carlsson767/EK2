<?php
// Настройки подключения к базе данных
$host = 'MySQL-8.4'; // Для OpenServer обычно используется
$db   = 'EK-studio'; // Имя вашей базы данных
$user = 'root';      // Имя пользователя 
$pass = '';          // Пароль (по умолчанию в OpenServer пустой)
$charset = 'utf8mb4'; // Кодировка

// Формируем строку подключения (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Оптимальные настройки для PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Выдавать ошибки в виде исключений (удобно для отладки)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Получать данные из БД в виде ассоциативных массивов
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Использовать реальные подготовленные запросы (безопасность)
];

try {
    // Создаем экземпляр подключения к БД
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Если подключиться не удалось, скрипт остановится и выведет ошибку
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}