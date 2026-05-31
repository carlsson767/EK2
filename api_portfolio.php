<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    // Получаем все проекты из БД
    $stmt = $pdo->query("SELECT * FROM portfolio_projects ORDER BY created_at DESC");
    $projects = $stmt->fetchAll();

    // Для каждого проекта получаем его фотографии из галереи
    foreach ($projects as &$project) {
        $imgStmt = $pdo->prepare("SELECT image_path FROM portfolio_images WHERE project_id = ? ORDER BY sort_order ASC");
        $imgStmt->execute([$project['id']]);
        $project['gallery'] = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode(['success' => true, 'data' => $projects]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}