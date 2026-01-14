<?php
session_start();

// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

// Настройки кэширования
$cacheEnabled = true;
$cacheTTL = 3600; // 1 час

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Подключение функций рекомендаций
require_once 'recommendation_functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Проверка роли пользователя
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM client WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userRole = $stmt->fetchColumn();
        $isAdmin = ($userRole === 'admin');
    } catch (PDOException $e) {
        error_log("Ошибка проверки роли: " . $e->getMessage());
    }
}

// Генерация рекомендаций
$userId = $_SESSION['user_id'];
$recommendations = generateRecommendations($pdo, $userId, $cacheEnabled, $cacheTTL);

// Проверяем, есть ли товары в избранном
$hasLikes = false;
try {
    $stmt = $pdo->prepare("SELECT 1 FROM liked_product WHERE client_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hasLikes = (bool)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Ошибка проверки избранного: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Персонализированные рекомендации</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding: 20px;
        }
        .product-card {
            border: 1px solid #e1e1e1;
            border-radius: 10px;
            padding: 15px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .product-card:hover {
            transform: translateY(-5px);
        }
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .product-title {
            font-size: 1.1em;
            margin: 0 0 5px 0;
            color: #333;
        }
        .product-price {
            font-weight: bold;
            color: #e53935;
            margin: 10px 0;
        }
        .product-reason {
            font-size: 0.9em;
            color: #666;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 5px;
            margin-top: 10px;
        }
        .no-recommendations {
            text-align: center;
            padding: 40px;
            font-size: 1.2em;
            color: #666;
        }
        .refresh-btn {
            background-color: #4285f4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 20px auto;
            display: block;
        }
        .refresh-btn:hover {
            background-color: #3367d6;
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
        
        <?php if ($isAdmin): ?>
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

    <h1 style="text-align: center; margin: 20px 0 30px 0;">Ваши персонализированные рекомендации</h1>
    
    <button class="refresh-btn" onclick="window.location.reload()">Обновить рекомендации</button>
    
    <?php if (empty($recommendations) && !$hasLikes): ?>
        <div class="no-recommendations">
            <p>Добавьте товары в избранное, чтобы получить рекомендации</p>
        </div>
    <?php elseif (empty($recommendations)): ?>
        <div class="no-recommendations">
            <p>Изучаем ваши предпочтения... Попробуйте позже</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($recommendations as $rec): ?>
                <div class="product-card">
                    <?php if (!empty($rec['product_data']['image'])): ?>
                        <img src="<?= htmlspecialchars($rec['product_data']['image']) ?>" 
                             alt="<?= htmlspecialchars($rec['product_data']['name']) ?>">
                    <?php else: ?>
                        <img src="placeholder.jpg" alt="Нет изображения">
                    <?php endif; ?>
                    <h3 class="product-title"><?= htmlspecialchars($rec['product_data']['name']) ?></h3>
                    <div class="product-price"><?= number_format($rec['product_data']['price'], 0, '', ' ') ?> руб.</div>
                    <div class="product-reason">
                        <?= $rec['reasons'] ?>
                    </div>
                    <div class="product-actions">
                        <button class="like-btn" data-product="<?= $rec['product_id'] ?>">❤️</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
        // Обработка лайков
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.product;
                // Здесь можно добавить AJAX-запрос для сохранения лайка
                this.classList.toggle('active');
            });
        });
    </script>
</body>
</html>