<?php
session_start();

// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

$error_message = '';
$success_message = '';

// Проверка сообщений из сессии
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
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
        $_SESSION['error'] = "Ошибка проверки роли: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Получение данных товара для редактирования
$productToEdit = null;
if (isset($_GET['edit_id']) && $isAdmin) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM product WHERE id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $productToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$productToEdit) {
            $_SESSION['error'] = "Товар не найден";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Ошибка загрузки данных товара: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Обработка формы добавления/редактирования товара
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    // Общие параметры для всех действий
    $name = $_POST["name"] ?? '';
    $composition = $_POST["composition"] ?? '';
    $price = (float)($_POST["price"] ?? 0);
    $gender = $_POST["gender"] ?? 'ун';
    $quantity = (int)($_POST["quantity"] ?? 0);
    $size = $_POST["size"] ?? 'M';
    $color = $_POST["color"] ?? '';
    $season = $_POST["season"] ?? 'всесезон';
    $clothing_type = $_POST["clothing_type"] ?? 'легкая одежда';
    $size_system = $_POST["size_system"] ?? 'EU';
    $category = $_POST["category"] ?? 'одежда';
    $image = '';

    // Валидация размера
    $allowedSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    if (!in_array($size, $allowedSizes)) {
        $_SESSION['error'] = "Неверный размер. Допустимые значения: " . implode(', ', $allowedSizes);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Валидация цены
    if ($price > 9999999.99) {
        $_SESSION['error'] = "Слишком большая цена. Максимальное значение - 9,999,999.99";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    if ($price < 0) {
        $_SESSION['error'] = "Цена не может быть отрицательной";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($_POST['action'] === 'add' && $isAdmin) {
        // Обработка загрузки изображения
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $image_name = uniqid() . '.' . $ext;
                $target_path = $upload_dir . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image = $target_path;
                } else {
                    $_SESSION['error'] = "Ошибка загрузки изображения";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } else {
                $_SESSION['error'] = "Недопустимый формат изображения";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO product (name, composition, price, gender, quantity, size, image, color, season, clothing_type, size_system, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $composition, $price, $gender, $quantity, $size, $image, $color, $season, $clothing_type, $size_system, $category]);
            $_SESSION['success'] = "Товар успешно добавлен";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка добавления товара: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    elseif ($_POST['action'] === 'edit' && $isAdmin) {
        $id = (int)($_POST["id"] ?? 0);

        // Получаем текущее изображение
        try {
            $stmt = $pdo->prepare("SELECT image FROM product WHERE id = ?");
            $stmt->execute([$id]);
            $current_image = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка получения данных товара: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Обработка новой загрузки изображения
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_dir = 'uploads/';
                $image_name = uniqid() . '.' . $ext;
                $target_path = $upload_dir . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    // Удаляем старое изображение, если оно существует
                    if (!empty($current_image) && file_exists($current_image)) {
                        unlink($current_image);
                    }
                    $image = $target_path;
                } else {
                    $_SESSION['error'] = "Ошибка загрузки изображения";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
            } else {
                $_SESSION['error'] = "Недопустимый формат изображения";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } else {
            $image = $current_image;
        }

        try {
            $stmt = $pdo->prepare("UPDATE product SET name = ?, composition = ?, price = ?, gender = ?, quantity = ?, size = ?, image = ?, color = ?, season = ?, clothing_type = ?, size_system = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $composition, $price, $gender, $quantity, $size, $image, $color, $season, $clothing_type, $size_system, $category, $id]);
            $_SESSION['success'] = "Товар успешно обновлен";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Ошибка обновления товара: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    elseif ($_POST['action'] === 'delete' && $isAdmin) {
        $id = (int)($_POST["id"] ?? 0);
        
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            // 1. Удаляем связанные записи из order_items
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE product_id = ?");
            $stmt->execute([$id]);
            
            // 2. Получаем путь к изображению для удаления
            $stmt = $pdo->prepare("SELECT image FROM product WHERE id = ?");
            $stmt->execute([$id]);
            $image_path = $stmt->fetchColumn();
            
            // 3. Удаляем запись из product
            $stmt = $pdo->prepare("DELETE FROM product WHERE id = ?");
            $stmt->execute([$id]);
            
            // 4. Удаляем изображение, если оно существует
            if (!empty($image_path) && file_exists($image_path)) {
                unlink($image_path);
            }
            
            // Подтверждаем транзакцию
            $pdo->commit();
            
            $_SESSION['success'] = "Товар успешно удален";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            // Откатываем транзакцию в случае ошибки
            $pdo->rollBack();
            $_SESSION['error'] = "Ошибка удаления товара: " . $e->getMessage();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Получение списка товаров
try {
    $stmt = $pdo->query("SELECT * FROM product ORDER BY id");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка получения списка товаров: " . $e->getMessage();
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление товарами</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .product-table th, .product-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .product-table th {
            background-color: #f2f2f2;
        }
        .product-table img {
            max-width: 100px;
            max-height: 100px;
        }
        .add-product-form, .delete-form {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .error { color: red; }
        .success { color: green; }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button, .delete-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button {
            background-color: #5f1456;
            color: white;
        }
        .delete-btn {
            background-color: #d9534f;
            color: white;
        }
        .inline-form {
            display: inline-block;
            margin-right: 5px;
        }
        .size-help {
            margin: 15px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .size-table {
            width: 100%;
            border-collapse: collapse;
        }
        .size-table th, .size-table td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: center;
        }
        .edit-form {
            background-color: #e6f7ff;
            border: 1px solid #b3e0ff;
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

    <main>
        <div class="container">
            <h1>Управление товарами</h1>
            
            <?php if (!empty($error_message)): ?>
                <p class="error"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <p class="success"><?= htmlspecialchars($success_message) ?></p>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <div class="add-product-form <?= $productToEdit ? 'edit-form' : '' ?>">
                <h2><?= $productToEdit ? 'Редактировать товар' : 'Добавить новый товар' ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $productToEdit ? 'edit' : 'add' ?>">
                    <?php if ($productToEdit): ?>
                        <input type="hidden" name="id" value="<?= $productToEdit['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        
                        <label for="name">Название:</label>
                        <input type="text" id="name" name="name" value="<?= $productToEdit ? htmlspecialchars($productToEdit['name']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="composition">Состав:</label>
                        <textarea id="composition" name="composition" required><?= $productToEdit ? htmlspecialchars($productToEdit['composition']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Цена:</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" max="9999999.99" value="<?= $productToEdit ? number_format($productToEdit['price'], 2) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Пол:</label>
                        <select id="gender" name="gender" required>
                            <option value="жен" <?= ($productToEdit && $productToEdit['gender'] === 'жен') ? 'selected' : '' ?>>Женский</option>
                            <option value="муж" <?= ($productToEdit && $productToEdit['gender'] === 'муж') ? 'selected' : '' ?>>Мужской</option>
                            <option value="ун" <?= (!$productToEdit || $productToEdit['gender'] === 'ун') ? 'selected' : '' ?>>Унисекс</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Количество:</label>
                        <input type="number" id="quantity" name="quantity" min="0" value="<?= $productToEdit ? htmlspecialchars($productToEdit['quantity']) : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="size">Размер:</label>
                        <select id="size" name="size" required>
                            <option value="XS" <?= ($productToEdit && $productToEdit['size'] === 'XS') ? 'selected' : '' ?>>XS</option>
                            <option value="S" <?= ($productToEdit && $productToEdit['size'] === 'S') ? 'selected' : '' ?>>S</option>
                            <option value="M" <?= (!$productToEdit || $productToEdit['size'] === 'M') ? 'selected' : '' ?>>M</option>
                            <option value="L" <?= ($productToEdit && $productToEdit['size'] === 'L') ? 'selected' : '' ?>>L</option>
                            <option value="XL" <?= ($productToEdit && $productToEdit['size'] === 'XL') ? 'selected' : '' ?>>XL</option>
                            <option value="XXL" <?= ($productToEdit && $productToEdit['size'] === 'XXL') ? 'selected' : '' ?>>XXL</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="size_system">Система размеров:</label>
                        <select id="size_system" name="size_system" required>
                            <option value="EU" selected>EU (европейская)</option>
                        </select>
                    </div>
                    
                    <div class="size-help">
                        <h4>Соответствие размеров:</h4>
                        <table class="size-table">
                            <tr>
                                <th>EU</th>
                                <th>XS</th>
                                <th>S</th>
                                <th>M</th>
                                <th>L</th>
                                <th>XL</th>
                                <th>XXL</th>
                            </tr>
                            <tr>
                                <td>US</td>
                                <td>0-2</td>
                                <td>4-6</td>
                                <td>8-10</td>
                                <td>12-14</td>
                                <td>16-18</td>
                                <td>20-22</td>
                            </tr>
                            <tr>
                                <td>UK</td>
                                <td>4-6</td>
                                <td>8-10</td>
                                <td>12-14</td>
                                <td>16-18</td>
                                <td>20-22</td>
                                <td>24-26</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Изображение:</label>
                        <input type="file" id="image" name="image" accept="image/*">
                        <?php if ($productToEdit && !empty($productToEdit['image'])): ?>
                            <p>Текущее изображение: <?= htmlspecialchars(basename($productToEdit['image'])) ?></p>
                            <img src="<?= htmlspecialchars($productToEdit['image']) ?>" alt="Текущее изображение" style="max-width: 100px;">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Цвет:</label>
                        <select id="color" name="color">
                            <option value="Белый" <?= ($productToEdit && $productToEdit['color'] === 'Белый') ? 'selected' : '' ?>>Белый</option>
                            <option value="Черный" <?= ($productToEdit && $productToEdit['color'] === 'Черный') ? 'selected' : '' ?>>Черный</option>
                            <option value="Красный" <?= ($productToEdit && $productToEdit['color'] === 'Красный') ? 'selected' : '' ?>>Красный</option>
                            <option value="Синий" <?= ($productToEdit && $productToEdit['color'] === 'Синий') ? 'selected' : '' ?>>Синий</option>
                            <option value="Многоцветный" <?= ($productToEdit && $productToEdit['color'] === 'Многоцветный') ? 'selected' : '' ?>>Многоцветный</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="season">Сезон:</label>
                        <select id="season" name="season" required>
                            <option value="весна" <?= ($productToEdit && $productToEdit['season'] === 'весна') ? 'selected' : '' ?>>Весна</option>
                            <option value="лето" <?= ($productToEdit && $productToEdit['season'] === 'лето') ? 'selected' : '' ?>>Лето</option>
                            <option value="осень" <?= ($productToEdit && $productToEdit['season'] === 'осень') ? 'selected' : '' ?>>Осень</option>
                            <option value="зима" <?= ($productToEdit && $productToEdit['season'] === 'зима') ? 'selected' : '' ?>>Зима</option>
                            <option value="всесезон" <?= (!$productToEdit || $productToEdit['season'] === 'всесезон') ? 'selected' : '' ?>>Всесезон</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="clothing_type">Тип одежды:</label>
                        <select id="clothing_type" name="clothing_type" required>
                            <option value="верхняя одежда" <?= ($productToEdit && $productToEdit['clothing_type'] === 'верхняя одежда') ? 'selected' : '' ?>>Верхняя одежда</option>
                            <option value="демисезонная одежда" <?= ($productToEdit && $productToEdit['clothing_type'] === 'демисезонная одежда') ? 'selected' : '' ?>>Демисезонная одежда</option>
                            <option value="легкая одежда" <?= (!$productToEdit || $productToEdit['clothing_type'] === 'легкая одежда') ? 'selected' : '' ?>>Легкая одежда</option>
                            <option value="белье" <?= ($productToEdit && $productToEdit['clothing_type'] === 'белье') ? 'selected' : '' ?>>Белье</option>
                            <option value="аксессуары" <?= ($productToEdit && $productToEdit['clothing_type'] === 'аксессуары') ? 'selected' : '' ?>>Аксессуары</option>
                            <option value="обувь" <?= ($productToEdit && $productToEdit['clothing_type'] === 'обувь') ? 'selected' : '' ?>>Обувь</option>
                            <option value="другое" <?= ($productToEdit && $productToEdit['clothing_type'] === 'другое') ? 'selected' : '' ?>>Другое</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Категория:</label>
                        <select id="category" name="category" required>
                            <option value="одежда" <?= (!$productToEdit || $productToEdit['category'] === 'одежда') ? 'selected' : '' ?>>Одежда</option>
                            <option value="обувь" <?= ($productToEdit && $productToEdit['category'] === 'обувь') ? 'selected' : '' ?>>Обувь</option>
                            <option value="аксессуары" <?= ($productToEdit && $productToEdit['category'] === 'аксессуары') ? 'selected' : '' ?>>Аксессуары</option>
                        </select>
                    </div>
                    
                    <button type="submit"><?= $productToEdit ? 'Обновить товар' : 'Добавить товар' ?></button>
                    <?php if ($productToEdit): ?>
                        <a href="add_product.php" class="cancel-btn">Отмена</a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <h2>Список товаров</h2>
            <?php if (empty($products)): ?>
                <p>Нет доступных товаров</p>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Пол</th>
                            <th>Размер</th>
                            <th>Система</th>
                            <th>Цвет</th>
                            <th>Сезон</th>
                            <th>Тип</th>
                            <th>Категория</th>
                            <th>Изображение</th>
                            <?php if ($isAdmin): ?>
                            <th>Действия</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['id']) ?></td>
                            <td><?= htmlspecialchars($product['name']) ?></td>
                            <td><?= number_format($product['price'],  2, '.', '') ?></td>
                            <td><?= htmlspecialchars($product['gender']) ?></td>
                            <td><?= htmlspecialchars($product['size']) ?></td>
                            <td><?= htmlspecialchars($product['size_system']) ?></td>
                            <td><?= htmlspecialchars($product['color']) ?></td>
                            <td><?= htmlspecialchars($product['season']) ?></td>
                            <td><?= htmlspecialchars($product['clothing_type']) ?></td>
                            <td><?= htmlspecialchars($product['category']) ?></td>
                            <td>
                                <?php if (!empty($product['image'])): ?>
                                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php else: ?>
                                    Нет изображения
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td>
                                <a href="add_product.php?edit_id=<?= $product['id'] ?>" class="edit-btn">Изменить</a>
                                
                                <form method="post" class="inline-form" onsubmit="return confirm('Вы уверены, что хотите удалить этот товар?');">
                                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="delete-btn">Удалить</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <div class="delete-form">
                <h2>Удаление товара по ID</h2>
                <form method="post" onsubmit="return confirm('Вы уверены, что хотите удалить этот товар?');">
                    <input type="hidden" name="action" value="delete">
                    <div class="form-group">
                        <label for="delete_id">ID товара:</label>
                        <input type="number" id="delete_id" name="id" min="1" required>
                    </div>
                    <button type="submit" class="delete-btn">Удалить</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
    // Валидация формы перед отправкой
    document.querySelector('form').addEventListener('submit', function(e) {
        const priceInput = document.getElementById('price');
        const price = parseFloat(priceInput.value);
        
        if (price > 9999999.99) {
            alert('Максимальная цена - 9,999,999.99');
            e.preventDefault();
            return false;
        }
        
        if (price < 0) {
            alert('Цена не может быть отрицательной');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    </script>
</body>
</html>