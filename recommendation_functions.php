<?php
/**
 * Функции для системы рекомендаций
 */

/**
 * Логирование действий системы рекомендаций
 */
function logRecommendationAction($pdo, $data) {
    $stmt = $pdo->prepare("
        INSERT INTO recommendation_logs 
        (client_id, action_type, start_time, end_time, products_count, status, error_message, execution_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['client_id'] ?? null,
        $data['action_type'],
        $data['start_time'] ?? date('Y-m-d H:i:s'),
        $data['end_time'] ?? null,
        $data['products_count'] ?? null,
        $data['status'],
        $data['error_message'] ?? null,
        $data['execution_time'] ?? null
    ]);
}

/**
 * Обновление предпочтений с весом
 */
function updatePreferences(&$preferences, $item, $weight) {
    if (!isset($item['category'])) return;
    
    $preferences['category'][$item['category']] = ($preferences['category'][$item['category']] ?? 0) + $weight;
    $preferences['gender'][$item['gender']] = ($preferences['gender'][$item['gender']] ?? 0) + $weight;
    
    $priceRange = floor($item['price'] / 1000) * 1000;
    $preferences['price_range'][$priceRange] = ($preferences['price_range'][$priceRange] ?? 0) + $weight;
    
    if (isset($item['color'])) {
        $preferences['color'][$item['color']] = ($preferences['color'][$item['color']] ?? 0) + $weight * 0.7;
    }
    
    if (isset($item['season'])) {
        $preferences['season'][$item['season']] = ($preferences['season'][$item['season']] ?? 0) + $weight * 0.7;
    }
    
    if (isset($item['clothing_type'])) {
        $preferences['style'][$item['clothing_type']] = ($preferences['style'][$item['clothing_type']] ?? 0) + $weight * 0.7;
    }
}

/**
 * Анализ предпочтений пользователя
 */
function analyzeUserPreferences($pdo, $userId) {
    $preferences = [
        'category' => [], 
        'gender' => [], 
        'price_range' => [],
        'color' => [], 
        'season' => [], 
        'style' => []
    ];
    
    try {
        // 1. Лайки пользователя
        $stmt = $pdo->prepare("SELECT p.* FROM liked_product lp JOIN product p ON lp.product_id = p.id WHERE lp.client_id = ?");
        $stmt->execute([$userId]);
        $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($likes as $item) {
            updatePreferences($preferences, $item, 1.5);
        }
        
        // 2. Покупки пользователя
        $stmt = $pdo->prepare("
            SELECT p.* FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            JOIN product p ON oi.product_id = p.id 
            WHERE o.client_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$userId]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($purchases as $item) {
            updatePreferences($preferences, $item, 2.0);
        }
    } catch (PDOException $e) {
        error_log("Error analyzing preferences: " . $e->getMessage());
    }
    
    return $preferences;
}

/**
 * Проверка взаимодействий пользователя с товаром
 */
function hasUserInteracted($pdo, $userId, $productId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM liked_product WHERE client_id = ? AND product_id = ?
            UNION
            SELECT 1 FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.client_id = ? AND oi.product_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$userId, $productId, $userId, $productId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error checking user interactions: " . $e->getMessage());
        return false;
    }
}

/**
 * Анализ причин рекомендации товара
 */
function getRecommendationReasons($product, $preferences) {
    $reasons = [];
    
    if (!empty($preferences['category'][$product['category']])) {
        $reasons[] = "вам нравятся товары категории '{$product['category']}'";
    }
    
    $priceRange = floor($product['price'] / 1000) * 1000;
    if (!empty($preferences['price_range'][$priceRange])) {
        $reasons[] = "подходит под ваш ценовой диапазон (~{$priceRange} руб.)";
    }
    
    if (!empty($product['color']) && !empty($preferences['color'][$product['color']])) {
        $reasons[] = "вам нравится цвет '{$product['color']}'";
    }
    
    if (!empty($product['clothing_type']) && !empty($preferences['style'][$product['clothing_type']])) {
        $reasons[] = "соответствует вашему стилю '{$product['clothing_type']}'";
    }
    
    return !empty($reasons) ? "Рекомендуем, потому что " . implode(", ", $reasons) : "Популярный новый товар";
}

/**
 * Генерация персонализированных рекомендаций
 */
function generateRecommendations($pdo, $userId, $cacheEnabled, $cacheTTL) {
    $startTime = microtime(true);
    
    try {
        logRecommendationAction($pdo, [
            'client_id' => $userId,
            'action_type' => 'generate',
            'status' => 'processing'
        ]);
        
        // Удаляем старые рекомендации
        $pdo->prepare("DELETE FROM recommendations WHERE client_id = ?")->execute([$userId]);
        
        // Получаем предпочтения
        $preferences = analyzeUserPreferences($pdo, $userId);
        
        // Получаем все товары
        $stmt = $pdo->query("SELECT * FROM product");
        $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Рассчитываем score для каждого товара
        $recommendations = [];
        foreach ($allProducts as $product) {
            $score = 0;
            
            $score += $preferences['category'][$product['category']] ?? 0;
            $score += $preferences['gender'][$product['gender']] ?? 0;
            
            $priceRange = floor($product['price'] / 1000) * 1000;
            $score += $preferences['price_range'][$priceRange] ?? 0;
            
            if (isset($product['color'])) {
                $score += ($preferences['color'][$product['color']] ?? 0) * 0.7;
            }
            
            if (isset($product['season'])) {
                $score += ($preferences['season'][$product['season']] ?? 0) * 0.7;
            }
            
            if (isset($product['clothing_type'])) {
                $score += ($preferences['style'][$product['clothing_type']] ?? 0) * 0.7;
            }
            
            if (!hasUserInteracted($pdo, $userId, $product['id']) && $score > 0) {
                $recommendations[] = [
                    'product_id' => $product['id'],
                    'score' => $score,
                    'product_data' => $product,
                    'reasons' => getRecommendationReasons($product, $preferences)
                ];
            }
        }
        
        // Сортируем и выбираем топ-5
        usort($recommendations, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $recommendations = array_slice($recommendations, 0, 5);
        
        // Сохраняем рекомендации
        $stmt = $pdo->prepare("INSERT INTO recommendations (client_id, product_id, score) VALUES (?, ?, ?)");
        foreach ($recommendations as $rec) {
            $stmt->execute([$userId, $rec['product_id'], $rec['score']]);
        }
        
        logRecommendationAction($pdo, [
            'client_id' => $userId,
            'action_type' => 'generate',
            'status' => 'success',
            'products_count' => count($recommendations),
            'execution_time' => (microtime(true) - $startTime) . ' sec',
            'end_time' => date('Y-m-d H:i:s')
        ]);
        
        return $recommendations;
        
    } catch (Exception $e) {
        logRecommendationAction($pdo, [
            'client_id' => $userId,
            'action_type' => 'generate',
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'execution_time' => (microtime(true) - $startTime) . ' sec',
            'end_time' => date('Y-m-d H:i:s')
        ]);
        return [];
    }
}

/**
 * Рендер рекомендаций в HTML
 */
function renderRecommendations($recommendations) {
    if (empty($recommendations)) {
        return '<div class="no-recommendations"><p>Рекомендации не найдены</p></div>';
    }

    $html = '';
    foreach ($recommendations as $rec) {
        $html .= sprintf('
            <div class="product-card fade-in">
                <img src="%s" alt="%s" class="product-image">
                <div class="product-info">
                    <h3 class="product-title">%s</h3>
                    <div class="product-price">%s ₽</div>
                    <div class="product-reason">%s</div>
                    <div class="product-actions">
                        <button class="like-btn" data-product="%s">❤️</button>
                    </div>
                </div>
            </div>',
            htmlspecialchars($rec['product_data']['image_url'] ?? 'placeholder.jpg'),
            htmlspecialchars($rec['product_data']['name']),
            htmlspecialchars($rec['product_data']['name']),
            number_format($rec['product_data']['price'], 0, '', ' '),
            $rec['reasons'],
            $rec['product_id']
        );
    }
    return $html;
}
?>