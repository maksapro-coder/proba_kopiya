 <?php
session_start();
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

$orderId = $_GET['order_id'] ?? 0;
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

        // Создаем заказ
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
        // Уменьшаем количество товара на складе
$stmtStock = $pdo->prepare("
    UPDATE product 
    SET quantity = quantity - ? 
    WHERE id = ? AND quantity >= ?
");
$stmtStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);


        // Очищаем корзину
        $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ?");
        $stmt->execute([$userId]);

        // Редирект на страницу успеха
        header("Location: order_success.php?order_id=$orderId");
        exit;
    }
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($orderId > 0) {
        // Обновляем статус заказа на "оформлен"
        $stmt = $pdo->prepare("UPDATE orders SET status = 'оформлен' WHERE id = ?");
        $stmt->execute([$orderId]);
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
<title>Заказ успешно оформлен</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/style_clothes.css">
<link rel="stylesheet" href="css/admin_styles.css">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
<style>
.order-success-container {
    max-width: 600px;
    margin: 100px auto;
    padding: 40px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    text-align: center;
    font-family: 'Titillium Web', sans-serif;
}

.order-success-container h2 {
    color: #5f1456;
    margin-bottom: 20px;
    font-size: 28px;
}

.order-success-container p {
    font-size: 18px;
    color: #333;
    margin-bottom: 15px;
}

.order-success-container .btn {
    display: inline-block;
    padding: 12px 25px;
    background: #5f1456;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.3s;
}

.order-success-container .btn:hover {
    background: #7d1c7d;
}

.icons {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 20px;
}

.icons img {
    width: 40px;
    height: 40px;
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

<main class="order-success-container">
    <h2>🎉 Заказ успешно оформлен!</h2>
    <p>Номер вашего заказа: <strong>#<?= htmlspecialchars($orderId) ?></strong></p>
    <p>Мы свяжемся с вами для подтверждения заказа.</p>
    <a href="index.php" class="btn">Вернуться к покупкам</a>
</main>
</body>
</html>
