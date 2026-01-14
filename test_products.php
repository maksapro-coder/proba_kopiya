<?php
session_start();

// ----------------------------
// Настройки подключения к базе
// ----------------------------
$host = 'db';         // Имя сервиса PostgreSQL в docker-compose
$port = 5432;         
$dbname = 'Mary';     
$user = 'postgres';   
$password = '1234';   

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// ----------------------------
// Безопасный пример выборки данных из таблицы product
// ----------------------------
try {
    $stmt = $pdo->query("SELECT id, name, price, gender, quantity FROM product ORDER BY id LIMIT 10");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($products) === 0) {
        echo "В таблице product пока нет записей.<br>";
    } else {
        echo "<h3>Список товаров (до 10 штук):</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Название</th><th>Цена</th><th>Пол</th><th>Количество</th></tr>";

        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($product['id']) . "</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['price']) . "</td>";
            echo "<td>" . htmlspecialchars($product['gender']) . "</td>";
            echo "<td>" . htmlspecialchars($product['quantity']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }
} catch (PDOException $e) {
    echo "Ошибка при выполнении запроса: " . $e->getMessage();
}
