<?php
session_start();
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

if (!isset($_SESSION['user_id']) || !isset($_POST['product_id'])) {
    echo json_encode(['error' => 'Неверный запрос']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_POST['product_id'];

try {
    $stmt = $pdo->prepare("DELETE FROM cart WHERE client_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных']);
}
?>