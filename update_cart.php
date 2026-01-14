<?php
session_start();
// Подключение к базе данных PostgreSQL
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

if (!isset($_SESSION['user_id']) || !isset($_POST['product_id']) || !isset($_POST['action'])) {
    echo json_encode(['error' => 'Неверный запрос']);
    exit;
}

$userId = $_SESSION['user_id'];
$productId = $_POST['product_id'];
$action = $_POST['action'];

try {
    // Получаем текущее количество
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE client_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        echo json_encode(['error' => 'Товар не найден в корзине']);
        exit;
    }
    
    $newQuantity = $current['quantity'];
    
    // Изменяем количество
    if ($action === 'increase') {
        $newQuantity++;
    } elseif ($action === 'decrease' && $newQuantity > 1) {
        $newQuantity--;
    }
    
    // Обновляем количество
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
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных']);
}
?>