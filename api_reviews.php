<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    // Запрашиваем только активные отзывы, сортируем от новых к старым
    $stmt = $pdo->query("SELECT * FROM reviews WHERE is_active = 1 ORDER BY created_at DESC");
    $reviews = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $reviews]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}