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
        die("<div class='error-message'>Для просмотра избранного необходимо <a href='login.php'>авторизоваться</a></div>");
    }

    $userId = $_SESSION['user_id'];
    
    // Получаем понравившиеся товары
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM product p 
        JOIN liked_product lp ON p.id = lp.product_id 
        WHERE lp.client_id = ?
    ");
    $stmt->execute([$userId]);
    $likedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем рекомендации на основе лайков (первые 4)
    $recommendations = $pdo->prepare("
        SELECT p.* 
        FROM recommendations r
        JOIN product p ON r.product_id = p.id
        WHERE r.client_id = ?
        ORDER BY r.score DESC
        LIMIT 4
    ");
    $recommendations->execute([$userId]);
    $recommendations = $recommendations->fetchAll(PDO::FETCH_ASSOC);

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
    
    <main class="liked-container">
        <?php if (!empty($likedProducts)): ?>
            <h1>Ваши понравившиеся товары</h1>
            <div class="liked-products-grid">
                <?php foreach ($likedProducts as $product): ?>
                    <div class="product-card">
                        <img src="<?= htmlspecialchars($product['image'] ?? 'photo/no-image.jpg') ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             class="product-image">
                        <div class="product-info">
                            <p class="product-quantity">В наличии: <?= (int)$product['quantity'] ?> шт.</p>

                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-price"><?= number_format($product['price'], 2) ?> руб.</p>
                            <div class="action-buttons">
                                <form action="bag.php" method="POST" class="add-to-cart-form">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="action-btn add-to-cart">В корзину</button>
                                </form>
                                <form method="POST" class="remove-like-form">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <button type="submit" class="action-btn remove-like">Удалить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-message">
                У вас пока нет понравившихся товаров.<br>
                <a href="women.php">Посмотреть женскую коллекцию</a> | 
                <a href="men.php">Посмотреть мужскую коллекцию</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($recommendations)): ?>
            <div class="recommendations-section">
                <h2 class="section-title">Рекомендуем вам</h2>
                <div class="liked-products-grid">
                    <?php foreach ($recommendations as $product): ?>
                        <div class="product-card">
                            <img src="<?= htmlspecialchars($product['image'] ?? 'photo/no-image.jpg') ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image">
                            <div class="product-info">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="product-price"><?= number_format($product['price'], 2) ?> руб.</p>
                                <div class="action-buttons">
                                    <form action="bag.php" method="POST" class="add-to-cart-form">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="action-btn add-to-cart">В корзину</button>
                                    </form>
                                    <form method="POST" class="like-form">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <button type="submit" class="action-btn add-to-cart">
                                            <img src="photo/like.png" alt="Like" style="width:16px; vertical-align:middle;">
                                            Нравится
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="recommendations.php" class="action-btn add-to-cart" style="display: inline-block; padding: 10px 20px;">
                        Показать все рекомендации
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
    $(document).ready(function() {
        // Обработка добавления в корзину
        $('.add-to-cart-form').submit(function(e) { 
    e.preventDefault();
    var form = $(this);
    var data = form.serialize() + '&action_type=add'; // обязательный параметр

    $.post(form.attr('action'), data, function(response){
        if(response.success){
            alert('Товар добавлен в корзину. Количество: ' + response.quantity);
        } else {
            alert('Ошибка: ' + response.message);
        }
    }, 'json');
});


        // Обработка удаления из избранного
        $('.remove-like-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            var productCard = form.closest('.product-card');
            
            $.ajax({
                type: 'POST',
                url: 'likes_handler.php',
                data: {
                    product_id: form.find('input[name="product_id"]').val(),
                    action: 'unlike'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        productCard.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.message || 'Ошибка при удалении');
                    }
                },
                error: function() {
                    alert('Ошибка соединения');
                }
            });
        });

        // Обработка добавления в избранное для рекомендаций
        $('.like-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            
            $.ajax({
                type: 'POST',
                url: 'likes_handler.php',
                data: {
                    product_id: form.find('input[name="product_id"]').val(),
                    action: 'like'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Товар добавлен в избранное');
                    } else {
                        alert(response.message || 'Ошибка при добавлении');
                    }
                },
                error: function() {
                    alert('Ошибка соединения');
                }
            });
        });
        function refreshLikedQuantities() {
        $.getJSON('likes_quantity.php', function(data){
            data.forEach(function(item){
                $('.product-card[data-id="'+item.id+'"] .product-quantity')
                    .text('В наличии: ' + item.quantity + ' шт.');
            });
        });
    }

    // Вызовем при загрузке страницы, чтобы подгрузить актуальные данные
    refreshLikedQuantities();
    });
    </script>
</body>
</html>