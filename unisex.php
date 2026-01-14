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

    // Получаем параметры фильтрации
    $minPrice = isset($_GET['min_price']) && !empty($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) && !empty($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $sizeFilter = isset($_GET['size']) ? $_GET['size'] : '';

    // Базовый запрос
    $sql = "SELECT * FROM product WHERE gender = 'ун'";
    $params = [];

    // Добавляем фильтр по цене, если указаны значения
    if ($minPrice !== null && $maxPrice !== null) {
        $sql .= " AND price BETWEEN :minPrice AND :maxPrice";
        $params['minPrice'] = $minPrice;
        $params['maxPrice'] = $maxPrice;
    } elseif ($minPrice !== null) {
        $sql .= " AND price >= :minPrice";
        $params['minPrice'] = $minPrice;
    } elseif ($maxPrice !== null) {
        $sql .= " AND price <= :maxPrice";
        $params['maxPrice'] = $maxPrice;
    }

    // Добавляем фильтр по размеру, если выбран
    if (!empty($sizeFilter)) {
        $sql .= " AND size = :size";
        $params['size'] = $sizeFilter;
    }

    // Получаем унисекс товары с учетом фильтров
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $unisexProduct = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получаем уникальные размеры для фильтра
    $stmt = $pdo->query("SELECT DISTINCT size FROM product WHERE gender = 'ун' ORDER BY size");
    $availableSizes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Для AJAX-запросов
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'])) {
        // Проверяем авторизацию (используем user_id, который соответствует id из таблицы users)
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Для добавления в избранное необходимо авторизоваться.']);
            exit;
        }

        $userId = $_SESSION['user_id']; // ID из таблицы users
        $productId = $_POST['product_id'];

        try {
            // Проверяем, есть ли уже товар в избранном
            $stmt = $pdo->prepare("SELECT 1 FROM liked_product WHERE client_id = ? AND product_id = ?");
            $stmt->execute([$userId, $productId]);
            
            if ($stmt->fetchColumn()) {
                // Удаляем из избранного
                $stmt = $pdo->prepare("DELETE FROM liked_product WHERE client_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                $response = [
                    'message' => 'Товар удален из избранного',
                    'liked' => false,
                    'product_id' => $productId
                ];
            } else {
                // Добавляем в избранное
                $stmt = $pdo->prepare("INSERT INTO liked_product (client_id, product_id) VALUES (?, ?)");
                $stmt->execute([$userId, $productId]);
                $response = [
                    'message' => 'Товар добавлен в избранное',
                    'liked' => true,
                    'product_id' => $productId
                ];
            }
            
            echo json_encode($response);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()]);
            exit;
        }
    }

} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Унисекс товары</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    
    <main class="product-catalog">
        <!-- Фильтры -->
        <div class="filters">
            <form method="get" id="filter-form">
                <div class="filter-group">
                    <label for="min_price">Цена от:</label>
                    <input type="number" id="min_price" name="min_price" min="0" 
                           value="<?= isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : '' ?>">
                </div>
                <div class="filter-group">
                    <label for="max_price">до:</label>
                    <input type="number" id="max_price" name="max_price" min="0" 
                           value="<?= isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : '' ?>">
                </div>
                <div class="filter-group">
                    <label for="size">Размер:</label>
                    <select id="size" name="size">
                        <option value="">Все размеры</option>
                        <?php foreach ($availableSizes as $size): ?>
                            <option value="<?= htmlspecialchars($size) ?>" 
                                <?= isset($_GET['size']) && $_GET['size'] == $size ? 'selected' : '' ?>>
                                <?= htmlspecialchars($size) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="filter-button">Применить</button>
                <?php if (isset($_GET['min_price']) || isset($_GET['max_price']) || isset($_GET['size'])): ?>
                    <a href="unisex.php" class="clear-filters">Сбросить фильтры</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="product-grid">
            <?php foreach ($unisexProduct as $product): 
                // Проверяем, есть ли товар в избранном у текущего пользователя
                $isLiked = false;
                if (isset($_SESSION['user_id'])) {
                    $stmt = $pdo->prepare("SELECT 1 FROM liked_product WHERE client_id = ? AND product_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $product['id']]);
                    $isLiked = $stmt->fetchColumn();
                }
            ?>
                <div class="product-item">
                    <img src="<?= htmlspecialchars($product['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>">
                    <h3><?= htmlspecialchars($product['name'], ENT_QUOTES) ?></h3>
                    <div class="product-description">
                        <p>Состав: <?= htmlspecialchars($product['composition'], ENT_QUOTES) ?></p>
                        <p>Размер: <?= htmlspecialchars($product['size'], ENT_QUOTES) ?></p>
                        <p>Цена: <?= htmlspecialchars($product['price'], ENT_QUOTES) ?> руб.</p>
                        <form method="post" class="like-form">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id'], ENT_QUOTES) ?>">
                            <button type="submit" class="like-button">
                                <img src="<?= $isLiked ? 'photo/liked.png' : 'photo/like.png' ?>" 
                                     alt="Like" 
                                     class="like-icon"
                                     data-liked="<?= $isLiked ? 'true' : 'false' ?>">
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <style>
        .filters {
            background-color: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .filter-group {
            margin-bottom: 15px;
            display: inline-block;
            margin-right: 15px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .filter-group input, .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-button:hover {
            background-color: #0056b3;
        }
        .clear-filters {
            margin-left: 15px;
            color: #007bff;
            text-decoration: none;
        }
        .clear-filters:hover {
            text-decoration: underline;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            background-color: #28a745;
            color: white;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>

    <script>
    $(document).ready(function() {
        $('.like-form').submit(function(e) {
            e.preventDefault();
            var form = $(this);
            var likeIcon = form.find('.like-icon');
            
            $.ajax({
                type: 'POST',
                url: 'unisex.php',
                data: form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        if (response.error.includes('авторизоваться')) {
                            if (confirm(response.error + ' Перейти на страницу входа?')) {
                                window.location.href = 'login.php';
                            }
                        } else {
                            showNotification(response.error, 'error');
                        }
                    } else {
                        // Обновляем иконку лайка
                        likeIcon.attr('src', response.liked ? 'photo/liked.png' : 'photo/like.png');
                        showNotification(response.message, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    showNotification('Ошибка соединения: ' + error, 'error');
                }
            });
        });
        
        function showNotification(message, type) {
            var notification = $('<div class="notification">' + message + '</div>');
            if (type === 'error') {
                notification.css('background-color', '#d9534f');
            }
            $('body').append(notification);
            setTimeout(function() {
                notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 2000);
        }
    });
    </script>
</body>
</html>