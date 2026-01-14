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

    if (!isset($_SESSION['user_id'])) {
        // Если это AJAX-запрос, возвращаем JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Необходимо авторизоваться']);
            exit;
        } else {
            die("<div class='auth-message'>Для просмотра корзины необходимо <a href='login.php'>авторизоваться</a>.</div>");
        }
    }

    $userId = $_SESSION['user_id'];

    // --- Обработка AJAX-запросов ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
        $actionType = $_POST['action_type'];
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;

        header('Content-Type: application/json');

        // Добавление в корзину
        if ($actionType === 'add' && $productId) {
            $stmt = $pdo->prepare("SELECT price FROM product WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Товар не найден']);
                exit;
            }

            // Добавляем или обновляем количество
           // Сначала проверяем, есть ли товар в корзине
$stmt = $pdo->prepare("SELECT quantity FROM cart WHERE client_id = ? AND product_id = ?");
$stmt->execute([$userId, $productId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current) {
    // Если есть — обновляем количество
    $quantity = $current['quantity'] + 1;
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE client_id = ? AND product_id = ?");
    $stmt->execute([$quantity, $userId, $productId]);
} else {
    // Если нет — вставляем новый
    $quantity = 1;
    $stmt = $pdo->prepare("INSERT INTO cart (client_id, product_id, quantity) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $productId, $quantity]);
}

// Возвращаем результат
echo json_encode(['success' => true, 'message' => 'Товар добавлен в корзину', 'quantity' => $quantity]);
exit;


            echo json_encode(['success' => true, 'message' => 'Товар добавлен в корзину', 'quantity' => $quantity]);
            exit;
        }

        // Изменение количества
        if ($actionType === 'update' && $productId && isset($_POST['update_action'])) {
            $updateAction = $_POST['update_action'];

            $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE client_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                echo json_encode(['success' => false, 'message' => 'Товар не найден в корзине']);
                exit;
            }

            $newQuantity = $current['quantity'];
            if ($updateAction === 'increase') $newQuantity++;
            if ($updateAction === 'decrease' && $newQuantity > 1) $newQuantity--;

            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE client_id = ? AND product_id = ?");
            $stmt->execute([$newQuantity, $userId, $productId]);

            // Получаем обновленные данные
            $stmt = $pdo->prepare("
                SELECT 
                    c.quantity,
                    (p.price * c.quantity) as item_total,
                    (SELECT COALESCE(SUM(p.price * c.quantity), 0)
                     FROM cart c JOIN product p ON c.product_id = p.id
                     WHERE c.client_id = ?) as grand_total
                FROM cart c
                JOIN product p ON c.product_id = p.id
                WHERE c.client_id = ? AND c.product_id = ?
            ");
            $stmt->execute([$userId, $userId, $productId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'quantity' => $result['quantity'],
                'item_total' => number_format($result['item_total'], 2, '.', ' '),
                'grand_total' => number_format($result['grand_total'], 2, '.', ' ')
            ]);
            exit;
        }

        // Удаление товара
        if ($actionType === 'remove' && $productId) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
        exit;
    }
    // --- Оформление заказа ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $userId = $_SESSION['user_id'];

    // Получаем товары из корзины
    $stmt = $pdo->prepare("
        SELECT c.product_id, c.quantity, p.price
        FROM cart c
        JOIN product p ON c.product_id = p.id
        WHERE c.client_id = ?
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($cartItems) {
        // Считаем общую сумму
        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $totalPrice += $item['price'] * $item['quantity'];
        }

        // Создаём заказ
        $stmt = $pdo->prepare("
            INSERT INTO orders (client_id, created_at, status, total_price, shipping_address, contact_phone)
            VALUES (?, NOW(), 'в обработке', ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([$userId, $totalPrice, $_POST['delivery_address'] ?? '', $_POST['phone'] ?? '']);
        $orderId = $stmt->fetchColumn();

        // Добавляем позиции в order_items
        $stmtItem = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($cartItems as $item) {
            $stmtItem->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['price'] * $item['quantity']
            ]);
        }

        // Очищаем корзину
        $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ?");
        $stmt->execute([$userId]);

        // Редирект на страницу успеха
        header("Location: order_success.php?order_id=$orderId");
        exit;
    } else {
        $orderError = "Корзина пуста, добавить товары невозможно.";
    }
}


    // --- Получение товаров в корзине для отображения ---
    $stmt = $pdo->prepare("
        SELECT 
            c.product_id,
            p.name,
            p.price,
            p.image,
            c.quantity,
            (p.price * c.quantity) as total_price
        FROM cart c
        JOIN product p ON c.product_id = p.id
        WHERE c.client_id = ?
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($cartItems as $item) $total += $item['total_price'];

} catch (PDOException $e) {
    die("<div class='error-message'>Ошибка базы данных: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Понравившиеся товары</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .liked-container {
            max-width: 1200px;
            margin: 80px auto 0;
            padding: 20px;
        }
        
        .liked-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-title {
            margin: 0 0 10px;
            font-size: 16px;
            color: #333;
        }
        
        .product-price {
            font-weight: bold;
            color: #5f1456;
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
        }
        
        .add-to-cart {
            background-color: #5f1456;
            color: white;
        }
        
        .remove-like {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .recommendations-section {
            margin-top: 40px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #666;
        }
        /* Контейнер */
.cart-items {
    max-width: 1200px;
    margin: 60px auto;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

/* Карточка товара */
.cart-item {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e0e0e0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px;
}

.cart-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* Изображение */
.cart-item img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
}

/* Информация о товаре */
.cart-item .product-info {
    text-align: center;
}

.cart-item .product-title {
    font-size: 18px;
    margin: 5px 0;
    font-weight: 600;
    color: #333;
}

.cart-item .product-price {
    font-size: 16px;
    font-weight: bold;
    color: #5f1456;
    margin-bottom: 10px;
}

/* Контрол количества */
.quantity-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 10px;
}

.quantity-btn {
    width: 32px;
    height: 32px;
    background: #5f1456;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
    transition: background 0.2s;
}

.quantity-btn:hover {
    background: #7d1c7d;
}

.quantity {
    font-size: 16px;
    min-width: 24px;
    display: inline-block;
    text-align: center;
}

/* Сумма и кнопка удалить */
.item-total {
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.remove-btn {
    background: #f44336;
    color: #fff;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
}

.remove-btn:hover {
    background: #d32f2f;
}

/* Итоговая сумма */
#grand-total {
    font-size: 20px;
    font-weight: bold;
    color: #5f1456;
}

/* Пустая корзина */
.empty-message {
    text-align: center;
    font-size: 18px;
    color: #666;
    padding: 50px;
}

/* Мобильная адаптация */
@media (max-width: 768px) {
    .cart-items {
        grid-template-columns: 1fr;
    }
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

<div class="cart-items">
<?php if (!empty($cartItems)): ?>
    <?php foreach ($cartItems as $item): ?>
        <div class="cart-item" data-product-id="<?= $item['product_id'] ?>">
            <img src="<?= htmlspecialchars($item['image']) ?>" width="150" height="150"><br>
            <?= htmlspecialchars($item['name']) ?><br>
            Цена: <?= $item['price'] ?> руб.<br>
            <div class="quantity-controls">
                <button class="quantity-btn" data-action="decrease">-</button>
                <span class="quantity"><?= $item['quantity'] ?></span>
                <button class="quantity-btn" data-action="increase">+</button>
            </div>
            Сумма: <span class="item-total"><?= $item['total_price'] ?></span> руб.<br>
            <button class="remove-btn">Удалить</button>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>Корзина пуста</p>
<?php endif; ?>
</div>
<h3>Итого: <span id="grand-total"><?= number_format($total, 2, '.', ' ') ?></span> руб.</h3>

<!-- Кнопка оформления заказа -->
<form method="POST">
    <button type="submit" name="checkout" class="action-btn checkout-btn">Оформить заказ</button>
</form>


<style>
.checkout-btn {
    display: inline-block;
    margin-top: 20px;
    padding: 12px 25px;
    background-color: #5f1456;
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkout-btn:hover {
    background-color: #3d0f3b;
    transform: translateY(-2px);
}
</style>


<script>
$(document).ready(function(){
    // Добавление товара можно так же вызвать через AJAX с action_type='add'

    // Увеличение / уменьшение количества
    $('.quantity-btn').click(function(){
        var parent = $(this).closest('.cart-item');
        var productId = parent.data('product-id');
        var action = $(this).data('action');

        $.post('bag.php', {action_type:'update', product_id:productId, update_action:action}, function(response){
            if(response.success){
                parent.find('.quantity').text(response.quantity);
                parent.find('.item-total').text(response.item_total);
                $('#grand-total').text(response.grand_total);
            } else {
                alert(response.message);
            }
        }, 'json');
    });

    // Удаление товара
    $('.remove-btn').click(function(){
        if(!confirm('Удалить этот товар из корзины?')) return;
        var parent = $(this).closest('.cart-item');
        var productId = parent.data('product-id');

        $.post('bag.php', {action_type:'remove', product_id:productId}, function(response){
            if(response.success){
                parent.remove();
                location.reload(); // обновим итог
            } else {
                alert(response.message);
            }
        }, 'json');
    });
});
</script>
</body>
</html>
