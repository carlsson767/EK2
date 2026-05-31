<?php
session_start();
// Подключаем базу данных
require_once 'db.php';

$page = $_GET['page'] ?? 'requests';

// Выход из панели
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$error = '';
// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    // password_verify автоматически сверяет пароль с зашифрованным хэшем из БД
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}

// Если администратор не авторизован — показываем красивую форму входа
if (empty($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - EKstudio Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #111; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-box { background: #1a1a1a; padding: 3rem; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333; }
        h2 { margin-bottom: 2rem; text-align: center; font-weight: 500; letter-spacing: 0.05em; color: #fff; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; color: #aaa; }
        .form-group input { width: 100%; padding: 0.9rem 1rem; background: #222; border: 1px solid #333; color: #fff; border-radius: 6px; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: #cc0000; }
        .btn { width: 100%; padding: 1rem; background: #cc0000; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 500; transition: background 0.2s; margin-top: 1rem; }
        .btn:hover { background: #a80000; }
        .error { color: #ff4444; font-size: 0.85rem; margin-bottom: 1rem; text-align: center; background: rgba(255,68,68,0.1); padding: 0.8rem; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>EKstudio Admin</h2>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="login" value="1">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>
    </div>
</body>
</html>
<?php
    exit; // Останавливаем выполнение скрипта, чтобы скрыть саму админку от гостей
}

// Обработка AJAX запроса на изменение статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        
        if ($id > 0 && in_array($new_status, ['new', 'processed', 'completed'])) {
            try {
                $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $id]);
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
        }
        exit;
    }

    // Удаление проекта из портфолио
    if ($action === 'delete_portfolio') {
        $id = (int)($_POST['id'] ?? 0);
        // Благодаря CASCADE в БД, картинки из portfolio_images удалятся автоматически
        $pdo->prepare("DELETE FROM portfolio_projects WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Добавление нового проекта
    if ($action === 'add_portfolio') {
        try {
            $title = trim($_POST['title'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            $cat_names = ['commercial'=>'Коммерческое', 'seasonal'=>'Сезонное', 'events'=>'Событие', 'sketches'=>'Эскиз', 'green'=>'Озеленение', 'design'=>'Дизайн', 'restoration'=>'Реставрация'];
            $category_name = $cat_names[$category] ?? 'Другое';

            // Создаем папку для загрузки, если её нет
            $upload_dir = 'uploads/portfolio/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $cover_path = '';
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
                $filename = uniqid('cov_') . '.' . $ext;
                move_uploaded_file($_FILES['cover']['tmp_name'], $upload_dir . $filename);
                $cover_path = $upload_dir . $filename;
            }

            $stmt = $pdo->prepare("INSERT INTO portfolio_projects (title, category, category_name, description, cover_image) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $category, $category_name, $description, $cover_path]);
            $project_id = $pdo->lastInsertId();

            if (isset($_FILES['gallery'])) {
                $stmt_img = $pdo->prepare("INSERT INTO portfolio_images (project_id, image_path, sort_order) VALUES (?, ?, ?)");
                foreach ($_FILES['gallery']['tmp_name'] as $idx => $tmp_name) {
                    if ($_FILES['gallery']['error'][$idx] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['gallery']['name'][$idx], PATHINFO_EXTENSION));
                        $filename = uniqid('gal_') . '.' . $ext;
                        move_uploaded_file($tmp_name, $upload_dir . $filename);
                        $stmt_img->execute([$project_id, $upload_dir . $filename, $idx]);
                    }
                }
            }
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // Добавление или редактирование отзыва
    if ($action === 'save_review') {
        $id = (int)($_POST['id'] ?? 0);
        $author_name = trim($_POST['author_name'] ?? '');
        $author_role = trim($_POST['author_role'] ?? '');
        $avatar_text = trim($_POST['avatar_text'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare("UPDATE reviews SET author_name=?, author_role=?, avatar_text=?, text=?, is_active=? WHERE id=?")->execute([$author_name, $author_role, $avatar_text, $text, $is_active, $id]);
        } else {
            $pdo->prepare("INSERT INTO reviews (author_name, author_role, avatar_text, text, is_active) VALUES (?, ?, ?, ?, ?)")->execute([$author_name, $author_role, $avatar_text, $text, $is_active]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    // Удаление отзыва
    if ($action === 'delete_review') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора - EKstudio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f4f6f8; 
            color: #333;
            display: flex; 
            height: 100vh; 
            overflow: hidden;
        }
        /* Боковая панель */
        .sidebar { 
            width: 260px; 
            background: #111; 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
        }
        .sidebar-brand { 
            padding: 1.5rem; 
            font-size: 1.2rem; 
            font-weight: 600; 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            letter-spacing: 0.05em;
        }
        .nav-menu { display: flex; flex-direction: column; padding: 1rem 0; }
        .nav-link { 
            padding: 1rem 1.5rem; 
            color: #aaa; 
            text-decoration: none; 
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        .nav-link:hover, .nav-link.active { background: #cc0000; color: #fff; }
        .nav-link.site-link { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); }
        
        /* Основная область */
        .main-content { flex: 1; padding: 2.5rem; overflow-y: auto; }
        h1 { margin-bottom: 2rem; font-size: 1.8rem; font-weight: 600; }
        
        .card { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        /* Стили для таблицы */
        .requests-table { width: 100%; border-collapse: collapse; }
        .requests-table th, .requests-table td { 
            padding: 1rem 1.2rem; 
            text-align: left; 
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .requests-table th { 
            background: #f8f9fa; 
            font-weight: 600; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 0.05em;
            color: #6c757d;
        }
        .requests-table tbody tr:hover { background: #f1f3f5; }
        .status-badge {
            padding: 0.25em 0.6em;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
        }
        .status-new { background: #d4edda; color: #155724; }
        .status-processed { background: #cce5ff; color: #004085; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        
        /* Стили для сортировки и кликабельных строк */
        .requests-table th[data-sort] { cursor: pointer; user-select: none; transition: background 0.2s; }
        .requests-table th[data-sort]:hover { background: #e9ecef; color: #333; }
        .requests-table th[data-sort]::after { content: ' ↕'; font-size: 0.8em; opacity: 0.5; }
        .requests-table th:not([data-sort])::after { content: ' ↕'; font-size: 0.8em; visibility: hidden; }
        .requests-table th.asc::after { content: ' ↑'; opacity: 1; }
        .requests-table th.desc::after { content: ' ↓'; opacity: 1; }
        .request-row { cursor: pointer; }

        /* Стили модального окна заявки */
        .admin-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .admin-modal.active { display: flex; animation: fadeIn 0.2s ease; }
        .admin-modal-content { background: #fff; padding: 2.5rem; border-radius: 12px; width: 100%; max-width: 600px; position: relative; transform: translateY(20px); animation: slideUp 0.3s ease forwards; }
        @keyframes slideUp { to { transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .admin-modal-close { position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.5rem; cursor: pointer; color: #888; transition: color 0.2s; line-height: 1; }
        .admin-modal-close:hover { color: #000; }
        
        .modal-meta { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .modal-date { color: #666; font-size: 0.9rem; }
        
        .modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .modal-col { background: #f8f9fa; padding: 1rem; border-radius: 8px; }
        .modal-label { display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.3rem; }
        .modal-val { font-size: 0.95rem; color: #333; font-weight: 500; }
        
        .modal-message-box { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .modal-message-box p { font-size: 0.95rem; color: #333; line-height: 1.6; white-space: pre-wrap; }
        
        .modal-actions { display: flex; gap: 1rem; }
        .btn-status { padding: 0.6rem 1.2rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: background 0.2s; }
        .btn-status-process { background: #111; color: #fff; }
        .btn-status-process:hover { background: #333; }
        
        /* Скрыть пустые блоки в модалке */
        .hidden-empty { display: none !important; }

        /* Портфолио стили */
        .flex-between { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .btn-primary { background: #cc0000; color: #fff; border: none; padding: 0.8rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 500; transition: background 0.2s; }
        .btn-primary:hover { background: #a80000; }
        .portfolio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .port-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; flex-direction: column; transition: transform 0.2s; }
        .port-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .port-img { width: 100%; aspect-ratio: 16/11; background-size: cover; background-position: center; background-color: #f0f0f0; border-bottom: 1px solid #eee; }
        .port-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .port-cat { font-size: 0.75rem; color: #cc0000; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem; font-weight: 600; }
        .port-title { font-size: 1.1rem; font-weight: 600; color: #333; margin-bottom: 1.5rem; line-height: 1.3; }
        .port-actions { margin-top: auto; display: flex; gap: 0.5rem; }
        .btn-delete { background: #ffeeee; color: #cc0000; border: none; padding: 0.6rem 1rem; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background 0.2s; font-size: 0.85rem; width: 100%; }
        .btn-delete:hover { background: #ffcccc; }
        
        /* Формы внутри модалки добавления */
        .admin-form-group { margin-bottom: 1.2rem; }
        .admin-form-group label { display: block; font-size: 0.85rem; color: #666; margin-bottom: 0.4rem; font-weight: 500; }
        .admin-form-group input[type="text"], .admin-form-group select, .admin-form-group textarea { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ddd; border-radius: 6px; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; }
        .admin-form-group input[type="text"]:focus, .admin-form-group select:focus, .admin-form-group textarea:focus { border-color: #cc0000; }
        .file-upload-box { background: #f8f9fa; border: 2px dashed #ddd; border-radius: 8px; padding: 1.5rem; text-align: center; color: #666; transition: border-color 0.2s; cursor: pointer; }
        .file-upload-box:hover { border-color: #cc0000; color: #cc0000; }
        .file-upload-box input[type="file"] { display: block; margin: 0.5rem auto 0; font-size: 0.85rem; cursor: pointer; }

        /* Адаптив для админ-панели (Мобильная версия) */
        @media (max-width: 768px) {
            body { flex-direction: column; height: 100dvh; }
            .sidebar { width: 100%; flex-shrink: 0; display: block; }
            .sidebar-brand { padding: 1rem; text-align: center; }
            .nav-menu { flex-direction: row; overflow-x: auto; padding: 0; }
            .nav-menu::-webkit-scrollbar { height: 4px; }
            .nav-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
            .nav-link { white-space: nowrap; padding: 1rem; font-size: 0.95rem; border: none !important; }
            
            .sidebar .site-link, .sidebar a[href*="logout"] { display: inline-block; width: 49%; text-align: center; padding: 0.8rem 0; font-size: 0.85rem; border-top: 1px solid rgba(255,255,255,0.05) !important; margin: 0; box-sizing: border-box; }
            
            .main-content { padding: 1rem; }
            h1 { font-size: 1.5rem; margin-bottom: 1rem; text-align: center; }
            .card { padding: 1rem; overflow-x: auto; }
            
            .requests-table th, .requests-table td { padding: 0.8rem 0.6rem; font-size: 0.85rem; }
            
            .admin-modal { align-items: flex-end; }
            .admin-modal-content { padding: 1.5rem; width: 100%; max-width: none; margin: 0; border-radius: 16px 16px 0 0; max-height: 85vh; transform: translateY(100%); }
            .modal-grid { grid-template-columns: 1fr; gap: 0.8rem; }
            
            .flex-between { flex-direction: column; align-items: stretch; gap: 1rem; }
            .btn-primary, .btn-status { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">EKstudio Admin</div>
        <div class="nav-menu">
            <a href="admin.php?page=requests" class="nav-link <?= $page === 'requests' ? 'active' : '' ?>">Входящие заявки</a>
            <a href="admin.php?page=portfolio" class="nav-link <?= $page === 'portfolio' ? 'active' : '' ?>">Управление портфолио</a>
            <a href="admin.php?page=reviews" class="nav-link <?= $page === 'reviews' ? 'active' : '' ?>">Отзывы клиентов</a>
        </div>
        <a href="/" class="nav-link site-link" target="_blank">Вернуться на сайт ↗</a>
        <a href="admin.php?logout=1" class="nav-link" style="color: #666; border-top: 1px solid rgba(255,255,255,0.05);">Выйти из панели</a>
    </div>
    <div class="main-content">
        <?php if ($page === 'requests'): ?>
        <h1>Входящие заявки</h1>
        <?php
            // Получаем все заявки из БД, сортируя по дате (новые сверху)
            $stmt = $pdo->query("SELECT * FROM requests ORDER BY CASE WHEN status = 'completed' THEN 1 ELSE 0 END, created_at DESC");
            $requests = $stmt->fetchAll();
        ?>
        <div class="card">
            <?php if (empty($requests)): ?>
                <p style="color: #666;">Новых заявок пока нет.</p>
            <?php else: ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th data-sort="timestamp">Дата</th>
                            <th data-sort="name">Клиент</th>
                            <th data-sort="service">Услуга</th>
                            <th data-sort="status">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $statusLabels = ['new' => 'Новая', 'processed' => 'В работе', 'completed' => 'Завершена']; ?>
                        <?php foreach ($requests as $request): ?>
                            <tr class="request-row"
                                data-id="<?= $request['id'] ?>"
                                data-timestamp="<?= strtotime($request['created_at']) ?>"
                                data-date="<?= date('d.m.Y H:i', strtotime($request['created_at'])) ?>"
                                data-name="<?= htmlspecialchars($request['name']) ?>"
                                data-phone="<?= htmlspecialchars($request['phone']) ?>"
                                data-org="<?= htmlspecialchars($request['org']) ?>"
                                data-position="<?= htmlspecialchars($request['position']) ?>"
                                data-service="<?= htmlspecialchars($request['service']) ?>"
                                data-message="<?= htmlspecialchars($request['message']) ?>"
                                data-status="<?= htmlspecialchars($request['status']) ?>">
                                
                                <!-- Краткий вывод (без времени, без телефона) -->
                                <td><?= date('d.m.Y', strtotime($request['created_at'])) ?></td>
                                <td><?= htmlspecialchars($request['name']) ?></td>
                                <td><?= htmlspecialchars($request['service'] ?: 'Не указана') ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($request['status']) ?>"><?= $statusLabels[$request['status']] ?? htmlspecialchars($request['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <!-- Модальное окно для заявки -->
    <div class="admin-modal" id="request-modal">
        <div class="admin-modal-content">
            <span class="admin-modal-close" id="modal-close">&times;</span>
            
            <h2 id="modal-name">Имя клиента</h2>
            <div class="modal-meta">
                <span class="modal-date" id="modal-date"></span>
                <span id="modal-status-badge" class="status-badge"></span>
            </div>
            
            <div class="modal-grid">
                <div class="modal-col">
                    <span class="modal-label">Телефон</span>
                    <span class="modal-val" id="modal-phone"></span>
                </div>
                <div class="modal-col">
                    <span class="modal-label">Интересующая услуга</span>
                    <span class="modal-val" id="modal-service"></span>
                </div>
                <div class="modal-col" id="col-org">
                    <span class="modal-label">Организация</span>
                    <span class="modal-val" id="modal-org"></span>
                </div>
                <div class="modal-col" id="col-position">
                    <span class="modal-label">Должность</span>
                    <span class="modal-val" id="modal-position"></span>
                </div>
            </div>
            
            <div class="modal-message-box" id="box-message">
                <span class="modal-label">Комментарий к проекту</span>
                <p id="modal-message"></p>
            </div>
            
            <!-- Кнопка изменения статуса -->
            <div class="modal-actions" id="modal-actions">
            </div>
        </div>
    </div>

    <script>
        // 1. ЛОГИКА СОРТИРОВКИ ТАБЛИЦЫ
        document.querySelectorAll('th[data-sort]').forEach(th => {
            th.addEventListener('click', () => {
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const sortBy = th.dataset.sort;
                
                const isAsc = th.classList.contains('asc');
                table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));
                th.classList.add(isAsc ? 'desc' : 'asc');
                
                const order = isAsc ? -1 : 1;

                rows.sort((a, b) => {
                    let valA = a.dataset[sortBy] || '';
                    let valB = b.dataset[sortBy] || '';
                    
                    if (sortBy === 'timestamp') {
                        return (parseInt(valA) - parseInt(valB)) * order;
                    }
                    return valA.localeCompare(valB) * order;
                });
                tbody.append(...rows);
            });
        });

        // 2. ЛОГИКА МОДАЛЬНОГО ОКНА (Подробности заявки)
        const modal = document.getElementById('request-modal');
        const statusLabels = { 'new': 'Новая', 'processed': 'В работе', 'completed': 'Завершена' };
        let currentRow = null;
        
        document.querySelectorAll('.request-row').forEach(row => {
            row.addEventListener('click', () => {
                const d = row.dataset;
                
                document.getElementById('modal-name').textContent = d.name;
                document.getElementById('modal-date').textContent = d.date;
                document.getElementById('modal-phone').textContent = d.phone;
                document.getElementById('modal-service').textContent = d.service || 'Не выбрана';
                
                // Скрываем блоки, если клиент не заполнил эти поля
                document.getElementById('col-org').classList.toggle('hidden-empty', !d.org);
                document.getElementById('col-position').classList.toggle('hidden-empty', !d.position);
                document.getElementById('box-message').classList.toggle('hidden-empty', !d.message);
                
                document.getElementById('modal-org').textContent = d.org;
                document.getElementById('modal-position').textContent = d.position;
                document.getElementById('modal-message').textContent = d.message;
                
                const badge = document.getElementById('modal-status-badge');
                badge.textContent = statusLabels[d.status] || d.status;
                badge.className = `status-badge status-${d.status}`;
                
                currentRow = row;
                updateStatusButtons(d.status);
                
                modal.classList.add('active');
            });
        });
        
        function updateStatusButtons(status) {
            const container = document.getElementById('modal-actions');
            container.innerHTML = '';
            
            if (status === 'new') {
                container.innerHTML = `
                    <button class="btn-status btn-status-process change-status-btn" data-status="processed">Взять в работу</button>
                    <button class="btn-status change-status-btn" style="background:#eee; color:#333;" data-status="completed">Завершить</button>
                `;
            } else if (status === 'processed') {
                container.innerHTML = `
                    <button class="btn-status btn-status-process change-status-btn" data-status="completed">Завершить</button>
                    <button class="btn-status change-status-btn" style="background:#eee; color:#333;" data-status="new">Вернуть в "Новые"</button>
                `;
            } else if (status === 'completed') {
                container.innerHTML = `
                    <button class="btn-status change-status-btn" style="background:#eee; color:#333;" data-status="processed">Вернуть в работу</button>
                `;
            }

            container.querySelectorAll('.change-status-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (!currentRow) return;
                    
                    const id = currentRow.dataset.id;
                    const targetStatus = btn.dataset.status;
                    const originalText = btn.textContent;
                    
                    btn.textContent = 'Сохранение...';
                    btn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_status');
                        formData.append('id', id);
                        formData.append('status', targetStatus);

                        const response = await fetch('admin.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        
                        if (result.success) {
                            currentRow.dataset.status = targetStatus;
                            const badgeCell = currentRow.querySelector('.status-badge');
                            badgeCell.textContent = statusLabels[targetStatus] || targetStatus;
                            badgeCell.className = `status-badge status-${targetStatus}`;
                            
                            const modalBadge = document.getElementById('modal-status-badge');
                            modalBadge.textContent = statusLabels[targetStatus] || targetStatus;
                            modalBadge.className = `status-badge status-${targetStatus}`;
                            
                            updateStatusButtons(targetStatus);

                            // Сортировка: перенос строки вниз или вверх
                            const tbody = currentRow.parentNode;
                            if (targetStatus === 'completed') tbody.appendChild(currentRow);
                            else if (targetStatus === 'new') tbody.insertBefore(currentRow, tbody.firstChild);
                        } else {
                            alert('Ошибка: ' + (result.message || 'Не удалось обновить статус'));
                            btn.textContent = originalText;
                            btn.disabled = false;
                        }
                    } catch (error) {
                        alert('Произошла ошибка соединения с сервером.');
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                });
            });
        }

        // Закрытие окна
        document.getElementById('modal-close').addEventListener('click', () => modal.classList.remove('active'));
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
    </script>

    <?php elseif ($page === 'portfolio'): ?>
        <div class="flex-between">
            <h1>Управление портфолио</h1>
            <button class="btn-primary" id="btn-add-portfolio">+ Добавить проект</button>
        </div>
        
        <div class="portfolio-grid">
            <?php
            $projects = $pdo->query("SELECT * FROM portfolio_projects ORDER BY created_at DESC")->fetchAll();
            if (empty($projects)): ?>
                <p style="color: #666; grid-column: 1/-1;">Проектов пока нет. Нажмите «Добавить проект», чтобы загрузить первую работу.</p>
            <?php else:
                foreach ($projects as $p): ?>
                    <div class="port-card">
                        <div class="port-img" style="background-image: url('<?= htmlspecialchars($p['cover_image']) ?>')"></div>
                        <div class="port-body">
                            <span class="port-cat"><?= htmlspecialchars($p['category_name']) ?></span>
                            <h3 class="port-title"><?= htmlspecialchars($p['title']) ?></h3>
                            <div class="port-actions">
                                <button class="btn-primary btn-edit-portfolio" style="flex: 1; padding: 0.6rem; font-size: 0.85rem;" data-project='<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>✎ Изменить</button>
                                <button class="btn-delete" style="flex: 1; padding: 0.6rem; width: auto;" data-id="<?= $p['id'] ?>">✕ Удалить</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; 
            endif; ?>
        </div>

        <!-- Модальное окно добавления проекта -->
        <div class="admin-modal" id="portfolio-add-modal">
            <div class="admin-modal-content">
                <span class="admin-modal-close" id="port-modal-close">&times;</span>
                <h2 style="margin-bottom: 1.5rem;" id="port-modal-title">Новый проект</h2>
                
                <form id="portfolio-form" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="port-id" value="0">
                    <div class="admin-form-group">
                        <label>Название проекта</label>
                        <input type="text" name="title" required placeholder="Например: Ресторан «Архитектура»">
                    </div>
                    <div class="admin-form-group">
                        <label>Категория (Фильтр на сайте)</label>
                        <select name="category" required>
                            <option value="commercial">Коммерческие пространства</option>
                            <option value="seasonal">Сезонное оформление</option>
                            <option value="events">Событийные проекты</option>
                            <option value="sketches">Эскизы</option>
                            <option value="green">Озеленение</option>
                            <option value="design">Дизайн</option>
                            <option value="restoration">Реставрация декора</option>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>Описание (Выводится в модальном окне на сайте)</label>
                        <textarea name="description" rows="4" required placeholder="Опишите задачу и результат..."></textarea>
                    </div>
                    <div class="admin-form-group">
                        <label>Обложка (Превью-квадратик в сетке)</label>
                        <div class="file-upload-box">
                            <span id="port-cover-help">Нажмите, чтобы выбрать 1 обложку</span>
                            <input type="file" name="cover" id="port-cover" accept="image/*" required>
                        </div>
                    </div>
                    <div class="admin-form-group">
                        <label>Галерея внутри проекта (Можно выбрать сразу много фото)</label>
                        <div class="file-upload-box">
                            <span id="port-gallery-help">Нажмите, чтобы выбрать несколько фотографий</span>
                            <input type="file" name="gallery[]" id="port-gallery" accept="image/*" multiple required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem; padding: 1rem; font-size: 1rem;">Загрузить и сохранить</button>
                </form>
            </div>
        </div>

        <script>
            const portModal = document.getElementById('portfolio-add-modal');
            const portForm = document.getElementById('portfolio-form');

            document.getElementById('btn-add-portfolio').addEventListener('click', () => {
                portForm.reset(); document.getElementById('port-id').value = '0';
                document.getElementById('port-modal-title').textContent = 'Новый проект';
                document.getElementById('port-cover').required = true; document.getElementById('port-gallery').required = true;
                document.getElementById('port-cover-help').textContent = 'Нажмите, чтобы выбрать 1 обложку';
                document.getElementById('port-gallery-help').textContent = 'Нажмите, чтобы выбрать несколько фотографий';
                portModal.classList.add('active');
            });
            document.getElementById('port-modal-close').addEventListener('click', () => portModal.classList.remove('active'));
            portModal.addEventListener('click', e => { if (e.target === portModal) portModal.classList.remove('active'); });

            document.querySelectorAll('.btn-edit-portfolio').forEach(btn => {
                btn.addEventListener('click', () => {
                    const data = JSON.parse(btn.dataset.project);
                    portForm.reset(); document.getElementById('port-id').value = data.id;
                    portForm.querySelector('[name="title"]').value = data.title;
                    portForm.querySelector('[name="category"]').value = data.category;
                    portForm.querySelector('[name="description"]').value = data.description;
                    document.getElementById('port-modal-title').textContent = 'Редактировать проект';
                    document.getElementById('port-cover').required = false; document.getElementById('port-gallery').required = false;
                    document.getElementById('port-cover-help').textContent = 'Заменить обложку (оставьте пустым, чтобы не менять)';
                    document.getElementById('port-gallery-help').textContent = 'Добавить новые фото в галерею (необязательно)';
                    portModal.classList.add('active');
                });
            });

            portForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = portForm.querySelector('button[type="submit"]');
                btn.textContent = 'Идёт загрузка фото...';
                btn.disabled = true;

                const formData = new FormData(portForm);
                formData.append('action', 'save_portfolio');
                try {
                    const res = await fetch('admin.php', { method: 'POST', body: formData });
                    const result = await res.json();
                    if (result.success) location.reload();
                    else { alert('Ошибка: ' + result.message); btn.textContent = 'Загрузить и сохранить'; btn.disabled = false; }
                } catch (err) { alert('Ошибка сети'); btn.textContent = 'Загрузить и сохранить'; btn.disabled = false; }
            });

            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if(confirm('Точно удалить этот проект из базы?')) {
                        const fd = new FormData(); fd.append('action', 'delete_portfolio'); fd.append('id', btn.dataset.id);
                        await fetch('admin.php', { method: 'POST', body: fd }); location.reload();
                    }
                });
            });
        </script>

    <?php elseif ($page === 'reviews'): ?>
        <div class="flex-between">
            <h1>Отзывы клиентов</h1>
            <button class="btn-primary" id="btn-add-review">+ Добавить отзыв</button>
        </div>
        <div class="card">
            <?php
            $reviews = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC")->fetchAll();
            if (empty($reviews)): ?>
                <p style="color: #666;">Отзывов пока нет. Нажмите «Добавить отзыв», чтобы заполнить слайдер.</p>
            <?php else: ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th data-sort="name">Имя</th>
                            <th data-sort="active">На сайте</th>
                            <th style="width:150px">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr class="review-row" style="cursor: pointer;" data-name="<?= htmlspecialchars($r['author_name']) ?>" data-active="<?= $r['is_active'] ? '1' : '0' ?>" data-review='<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
                                <td><strong><?= htmlspecialchars($r['author_name']) ?></strong><br><small style="color:#888;"><?= htmlspecialchars($r['author_role']) ?></small></td>
                                <td><?= $r['is_active'] ? '<span class="status-badge status-new">Да</span>' : '<span class="status-badge status-completed">Скрыт</span>' ?></td>
                                <td style="display:flex; gap:.5rem;">
                                    <button class="btn-primary btn-edit-review" style="padding: .4rem .8rem; font-size: .8rem;">✎</button>
                                    <button class="btn-delete btn-del-review" style="padding: .4rem .8rem; font-size: .8rem; width:auto;" data-id="<?= $r['id'] ?>">✕</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Модальное окно отзыва -->
        <div class="admin-modal" id="review-add-modal">
            <div class="admin-modal-content" style="max-width: 500px;">
                <span class="admin-modal-close" id="rev-modal-close">&times;</span>
                <h2 style="margin-bottom: 1.5rem;" id="rev-modal-title">Новый отзыв</h2>
                <form id="review-form">
                    <input type="hidden" name="id" id="rev-id" value="0">
                    <div class="admin-form-group">
                        <label>Имя клиента</label>
                        <input type="text" name="author_name" id="rev-name" required placeholder="Например: Анна Коваль">
                    </div>
                    <div class="admin-form-group">
                        <label>Должность / Подпись</label>
                        <input type="text" name="author_role" id="rev-role" placeholder="Владелец ресторана «Модерн»">
                    </div>
                    <div class="admin-form-group">
                        <label>Буквы для аватарки (необязательно, макс. 2)</label>
                        <input type="text" name="avatar_text" id="rev-avatar" maxlength="2" placeholder="АК">
                    </div>
                    <div class="admin-form-group">
                        <label>Текст отзыва</label>
                        <textarea name="text" id="rev-text" rows="5" required placeholder="Напишите текст..."></textarea>
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:.5rem;"><input type="checkbox" name="is_active" id="rev-active" checked> <b>Показывать на сайте</b></label>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Сохранить отзыв</button>
                </form>
            </div>
        </div>

        <script>
            const revModal = document.getElementById('review-add-modal');
            const revForm = document.getElementById('review-form');
            
            document.getElementById('btn-add-review').addEventListener('click', () => {
                revForm.reset(); document.getElementById('rev-id').value = '0'; document.getElementById('rev-active').checked = true;
                document.getElementById('rev-modal-title').textContent = 'Новый отзыв'; revModal.classList.add('active');
            });
            document.getElementById('rev-modal-close').addEventListener('click', () => revModal.classList.remove('active'));
            revModal.addEventListener('click', e => { if(e.target === revModal) revModal.classList.remove('active'); });

            // Сортировка таблицы
            document.querySelectorAll('th[data-sort]').forEach(th => {
                th.addEventListener('click', () => {
                    const table = th.closest('table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const sortBy = th.dataset.sort;
                    
                    const isAsc = th.classList.contains('asc');
                    table.querySelectorAll('th').forEach(h => h.classList.remove('asc', 'desc'));
                    th.classList.add(isAsc ? 'desc' : 'asc');
                    
                    const order = isAsc ? -1 : 1;
                    rows.sort((a, b) => (a.dataset[sortBy] || '').localeCompare(b.dataset[sortBy] || '') * order);
                    tbody.append(...rows);
                });
            });

            // Открытие окна по клику на строку (или кнопку редактирования)
            document.querySelectorAll('.review-row').forEach(row => {
                row.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-del-review')) return; // Игнорируем клик по кнопке удаления
                    const data = JSON.parse(row.dataset.review);
                    document.getElementById('rev-id').value = data.id; document.getElementById('rev-name').value = data.author_name;
                    document.getElementById('rev-role').value = data.author_role; document.getElementById('rev-avatar').value = data.avatar_text;
                    document.getElementById('rev-text').value = data.text; document.getElementById('rev-active').checked = (data.is_active == 1);
                    document.getElementById('rev-modal-title').textContent = 'Редактировать отзыв'; revModal.classList.add('active');
                });
            });

            revForm.addEventListener('submit', async (e) => {
                e.preventDefault(); const fd = new FormData(revForm); fd.append('action', 'save_review');
                await fetch('admin.php', { method: 'POST', body: fd }); location.reload();
            });

            document.querySelectorAll('.btn-del-review').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if(confirm('Точно удалить отзыв?')) { const fd = new FormData(); fd.append('action', 'delete_review'); fd.append('id', btn.dataset.id); await fetch('admin.php', { method: 'POST', body: fd }); location.reload(); }
                });
            });
        </script>
    <?php endif; ?>
    </div>
</body>
</html>