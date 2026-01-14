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

    // Если форма оформления заказа отправлена
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // 1. Проверяем, есть ли товары в корзине
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE client_id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() == 0) {
            die("<div class='error-message'>Корзина пуста</div>");
        }

        // 2. Создаем заказ
        $pdo->beginTransaction();
        
        try {
            // Вставляем запись о заказе
            $stmt = $pdo->prepare("
                INSERT INTO orders (client_id, total_price)
                SELECT ?, SUM(p.price * c.quantity)
                FROM cart c
                JOIN product p ON c.product_id = p.id
                WHERE c.client_id = ?
                RETURNING id
            ");
            $stmt->execute([$userId, $userId]);
            $orderId = $stmt->fetchColumn();

            // Переносим товары в order_items (с расчетом total_price)
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price, total_price)
                SELECT ?, c.product_id, c.quantity, p.price, (p.price * c.quantity)
                FROM cart c
                JOIN product p ON c.product_id = p.id
                WHERE c.client_id = ?
            ");
            $stmt->execute([$orderId, $userId]);

            // Очищаем корзину
            $pdo->prepare("DELETE FROM cart WHERE client_id = ?")->execute([$userId]);

            $pdo->commit();
            
            // Перенаправляем на страницу успешного оформления
            header("Location: order_success.php?order_id=$orderId");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<div class='error-message'>Ошибка оформления заказа: " . $e->getMessage() . "</div>");
        }
    }

    // Получаем товары для отображения
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.image, c.quantity
        FROM cart c
        JOIN product p ON c.product_id = p.id
        WHERE c.client_id = ?
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Считаем общую сумму
    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }

} catch (PDOException $e) {
    die("<div class='error-message'>Ошибка базы данных: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!-- HTML-код остается без изменений -->

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        .checkout-container {
            max-width: 800px;
            margin: 80px auto;
            padding: 20px;
        }
        
        .checkout-items {
            margin-bottom: 30px;
        }
        
        .checkout-item {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .checkout-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .checkout-total {
            font-size: 1.2em;
            font-weight: bold;
            text-align: right;
            margin: 20px 0;
            padding: 15px;
            background: #f8f8f8;
            border-radius: 5px;
        }
        
        .checkout-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #5f1456;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        .btn:hover {
            background-color: #7a1a6d;
        }
        
        .error-message {
            color: red;
            margin-top: 20px;
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
    
    <main class="checkout-container">
        <?php if (!empty($cartItems)): ?>
            <h2>Оформление заказа</h2>
            
            <div class="checkout-items">
                <?php foreach ($cartItems as $item): ?>
                    <div class="checkout-item">
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        <div>
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p>Цена: <?= htmlspecialchars($item['price']) ?> руб. × <?= htmlspecialchars($item['quantity']) ?> = 
                            <strong><?= htmlspecialchars($item['price'] * $item['quantity']) ?> руб.</strong></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="checkout-total">
                Общая сумма: <?= number_format($total, 2, '.', ' ') ?> руб.
            </div>
            
            <form class="checkout-form" method="POST">
                <div class="form-group">
                    <label for="address">Адрес доставки:</label>
                    <textarea id="address" name="address" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="phone">Телефон:</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label for="payment">Способ оплаты:</label>
                    <select id="payment" name="payment" required>
                        <option value="cash">Наличными при получении</option>
                        <option value="card">Картой онлайн</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">Подтвердить заказ</button>
            </form>
        <?php else: ?>
            <div class="error-message">
                Ваша корзина пуста.<br><br>
                <a href="women.php">Посмотреть женскую коллекцию</a> | 
                <a href="men.php">Посмотреть мужскую коллекцию</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>