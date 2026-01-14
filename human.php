<?php
session_start();

// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Проверка авторизации
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Обработка обновления данных пользователя
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $deliveryAddress = $_POST['delivery_address'];

        $stmt = $pdo->prepare("
            UPDATE client 
            SET name = ?, email = ?, phone = ?, delivery_address = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $deliveryAddress, $userId]);
        
        $_SESSION['user_name'] = $name;
        header("Location: human.php"); // Перезагружаем страницу после сохранения
        exit;
    }

    // Получаем информацию о пользователе
    $stmt = $pdo->prepare("SELECT * FROM client WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Получаем заказы пользователя
    
    $stmt = $pdo->prepare("
        SELECT 
            o.id as order_id,
            o.created_at,
            o.status,
            o.total_price as order_total,
            oi.product_id,
            oi.quantity,
            oi.price as item_price,
            (oi.price * oi.quantity) as item_total,
            p.name,
            p.image
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN product p ON oi.product_id = p.id
        WHERE o.client_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$userId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Группируем товары по заказам
    $orders = [];
    foreach ($orderItems as $item) {
        $orderId = $item['order_id'];
        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                'order_id' => $item['order_id'],
                'created_at' => $item['created_at'],
                'status' => $item['status'],
                'order_total' => $item['order_total'],
                'items' => []
            ];
        }
        $orders[$orderId]['items'][] = $item;
    }

} catch (PDOException $e) {
    die("<div class='error-message'>Ошибка базы данных: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 80px auto 20px;
            padding: 20px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .user-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            position: relative;
        }
        
        .edit-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #5f1456;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .save-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
        }
        
        .user-info h2 {
            color: #5f1456;
            margin-top: 0;
        }
        
        .user-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .user-detail {
            margin-bottom: 10px;
        }
        
        .user-detail strong {
            display: inline-block;
            width: 120px;
            color: #666;
        }
        
        .user-detail input, 
        .user-detail textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            display: none; /* Скрываем по умолчанию */
        }
        
        .user-detail textarea {
            min-height: 60px;
            resize: vertical;
        }
        
        .user-detail span {
            display: inline-block;
            min-height: 20px;
            padding: 4px 0;
        }
        
        .edit-mode .user-detail span {
            display: none;
        }
        
        .edit-mode .user-detail input,
        .edit-mode .user-detail textarea {
            display: block;
        }
        
        .edit-mode .save-btn {
            display: inline-block !important;
        }
        
        .orders-title {
            color: #5f1456;
            margin: 30px 0 20px;
        }
        
        .orders-list {
            display: grid;
            gap: 20px;
        }
        
        .order {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .order-id {
            font-weight: bold;
            color: #5f1456;
        }
        
        .order-status {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .status-process {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .order-items {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .order-item-info {
            flex-grow: 1;
        }
        
        .order-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .order-total {
            text-align: right;
            font-weight: bold;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .empty-orders {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <header>
        <div class="top">
            <a href="index.php"><img src="photo/logo.png" class="icon" alt="Лого"></a>
        </div>
        
        <ul class="main-nav">
            <li><a href="women.php">women</a></li>
            <li><a href="men.php">men</a></li>
            <li><a href="unisex.php">unisexual</a></li>
            <li><a href="recommendations.php">rec</a></li>
        </ul>
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="admin-buttons-container">
            <div class="admin-buttons">
                <ul>
                    <li><a href="add_product.php">Добавить товар</a></li>
                    <li><a href="stats.php">Аналитика</a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="icons">
            <a href="likes.php"><img src="photo/liked.png" alt="Избранное"></a>
            <a href="bag.php"><img src="photo/корзина.png" alt="Корзина"></a>
            <a href="human.php"><img src="photo/череп.png" alt="Профиль"></a>
        </div>
    </header>
    
    <main class="profile-container">
        <div class="profile-header">
            <h1>Мой профиль</h1>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        </div>
        
        <form method="POST" class="user-info">
            <button type="button" id="edit-btn" class="edit-btn">Редактировать</button>
            <h2>Личная информация</h2>
            <div class="user-details">
                <div class="user-detail">
                    <strong>Имя:</strong>
                    <span><?= htmlspecialchars($userInfo['name'] ?? 'Не указано') ?></span>
                    <input type="text" name="name" value="<?= htmlspecialchars($userInfo['name'] ?? '') ?>">
                </div>
                <div class="user-detail">
                    <strong>Email:</strong>
                    <span><?= htmlspecialchars($userInfo['email'] ?? 'Не указан') ?></span>
                    <input type="email" name="email" value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>">
                </div>
                <div class="user-detail">
                    <strong>Телефон:</strong>
                    <span><?= htmlspecialchars($userInfo['phone'] ?? 'Не указан') ?></span>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>">
                </div>
                <div class="user-detail">
                    <strong>Адрес доставки:</strong>
                    <span><?= htmlspecialchars($userInfo['delivery_address'] ?? 'Не указан') ?></span>
                    <textarea name="delivery_address"><?= htmlspecialchars($userInfo['delivery_address'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" name="update_profile" class="save-btn" style="display: none;">Сохранить</button>
        </form>
        
        <h2 class="orders-title">Мои заказы</h2>
        
        <?php if (!empty($orders)): ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order">
                        <div class="order-header">
                            <div class="order-id">Заказ #<?= $order['order_id'] ?> от <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></div>
                            <div class="order-status status-<?= $order['status'] === 'оформлен' ? 'completed' : 'process' ?>">
                                <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <div class="order-item-info">
                                        <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div>Цена: <?= htmlspecialchars($item['item_price']) ?> руб.</div>
                                        <div>Кол-во: <?= htmlspecialchars($item['quantity']) ?></div>
                                        <div>Сумма: <?= htmlspecialchars($item['item_total']) ?> руб.</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            Итого: <?= number_format($order['order_total'], 2, '.', ' ') ?> руб.
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-orders">
                У вас пока нет заказов.<br>
                <a href="women.php">Посмотреть женскую коллекцию</a> | 
                <a href="men.php">Посмотреть мужскую коллекцию</a>
            </div>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editBtn = document.getElementById('edit-btn');
            const userInfoForm = document.querySelector('.user-info');
            const saveBtn = document.querySelector('.save-btn');
            
            if (editBtn && userInfoForm) {
                editBtn.addEventListener('click', function() {
                    userInfoForm.classList.toggle('edit-mode');
                    
                    if (userInfoForm.classList.contains('edit-mode')) {
                        editBtn.textContent = 'Отменить';
                    } else {
                        editBtn.textContent = 'Редактировать';
                    }
                });
            }
        });
    </script>
</body>
</html>