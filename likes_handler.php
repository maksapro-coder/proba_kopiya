<?php
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Для выполнения действия необходимо авторизоваться']);
    exit;
}

// Проверка данных
if (!isset($_POST['product_id'], $_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Неверный запрос']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = (int)$_POST['product_id'];
$action = $_POST['action'];

// Параметры подключения к базе
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($action === 'unlike') {
        // Удаление из избранного
        $stmt = $pdo->prepare("DELETE FROM liked_product WHERE client_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'like') {
        // Проверка, есть ли уже лайк
        $check = $pdo->prepare("SELECT id FROM liked_product WHERE client_id = ? AND product_id = ?");
        $check->execute([$userId, $productId]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Товар уже в избранном']);
            exit;
        }
        // Добавление в избранное
        $stmt = $pdo->prepare("INSERT INTO liked_product (client_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
