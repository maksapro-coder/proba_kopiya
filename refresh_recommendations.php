<?php
session_start();
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit;
}

$userId = $_SESSION['user_id'];
$recommendations = generateRecommendations($pdo, $userId, $cacheEnabled, $cacheTTL);

// Генерация HTML для новых рекомендаций
$html = '';
if (empty($recommendations)) {
    $html = '<div class="no-recommendations"><p>Новых рекомендаций не найдено</p></div>';
} else {
    foreach ($recommendations as $rec) {
        $html .= '
        <div class="product-card">
            <img src="'.htmlspecialchars($rec['product_data']['image_url'] ?? 'placeholder.jpg').'" 
                 alt="'.htmlspecialchars($rec['product_data']['name']).'">
            <h3>'.htmlspecialchars($rec['product_data']['name']).'</h3>
            <div class="product-price">'.number_format($rec['product_data']['price'], 0, '', ' ').' руб.</div>
            <div class="product-reason">'.$rec['reasons'].'</div>
        </div>';
    }
}

echo json_encode(['success' => true, 'html' => $html]);
?>