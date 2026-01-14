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
    
    // Проверка авторизации и прав администратора
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        die("<div class='error'>Доступ к этой странице разрешен только администраторам</div>");
    }

    // 1. Получение списка всех таблиц в базе данных
    $tablesQuery = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);

    // 2. Получение структуры каждой таблицы
    $tablesStructure = [];
    foreach ($tables as $table) {
        $structureQuery = $pdo->query("
            SELECT column_name, data_type, is_nullable, column_default 
            FROM information_schema.columns 
            WHERE table_name = '$table'
            ORDER BY ordinal_position
        ");
        $tablesStructure[$table] = $structureQuery->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Получение данных из таблиц (первые 5 записей)
    $tablesData = [];
    foreach ($tables as $table) {
        $dataQuery = $pdo->query("SELECT * FROM $table LIMIT 5");
        $tablesData[$table] = $dataQuery->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Распределение товаров по категориям
    $categoryDistributionQuery = $pdo->query("
        SELECT 
            gender,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM product), 2) as percentage
        FROM product
        GROUP BY gender
    ");
    $categoryDistribution = $categoryDistributionQuery->fetchAll(PDO::FETCH_ASSOC);

    // Подготовка данных для круговой диаграммы
    $categoryLabels = [];
    $categoryData = [];
    $categoryColors = ['#5f1456', '#e83e8c', '#17a2b8']; // Фиолетовый, розовый, голубой

    foreach ($categoryDistribution as $category) {
        $categoryLabels[] = $category['gender'];
        $categoryData[] = $category['percentage'];
    }

    // 5. Количество заказов за период (пример для недель)
    function getOrdersByWeek($pdo, $startWeek, $endWeek, $year) {
        $stmt = $pdo->prepare("
            SELECT 
                EXTRACT(WEEK FROM created_at) AS week_number,
                COUNT(*) AS order_count,
                SUM(total_price) AS total_revenue
            FROM orders
            WHERE 
                EXTRACT(YEAR FROM created_at) = :year AND
                EXTRACT(WEEK FROM created_at) BETWEEN :start_week AND :end_week
            GROUP BY week_number
            ORDER BY week_number
        ");
        $stmt->execute([
            ':year' => $year,
            ':start_week' => $startWeek,
            ':end_week' => $endWeek
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 6. Периоды без заказов
    function getInactivePeriods($pdo, $startWeek, $endWeek, $year) {
        $allWeeks = range($startWeek, $endWeek);
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT EXTRACT(WEEK FROM created_at) AS week_number
            FROM orders
            WHERE 
                EXTRACT(YEAR FROM created_at) = :year AND
                EXTRACT(WEEK FROM created_at) BETWEEN :start_week AND :end_week
        ");
        $stmt->execute([
            ':year' => $year,
            ':start_week' => $startWeek,
            ':end_week' => $endWeek
        ]);
        $activeWeeks = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'week_number');
        
        $inactiveWeeks = array_diff($allWeeks, $activeWeeks);
        
        return $inactiveWeeks;
    }

    // 7. Пиковые значения (макс/мин)
    function getPeakValues($pdo, $year) {
        $stmt = $pdo->prepare("
            SELECT 
                EXTRACT(MONTH FROM created_at) AS month,
                COUNT(*) AS order_count,
                SUM(total_price) AS total_revenue
            FROM orders
            WHERE EXTRACT(YEAR FROM created_at) = :year
            GROUP BY month
            ORDER BY order_count DESC
            LIMIT 1
        ");
        $stmt->execute([':year' => $year]);
        $maxMonth = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT 
                EXTRACT(MONTH FROM created_at) AS month,
                COUNT(*) AS order_count,
                SUM(total_price) AS total_revenue
            FROM orders
            WHERE EXTRACT(YEAR FROM created_at) = :year
            GROUP BY month
            ORDER BY order_count ASC
            LIMIT 1
        ");
        $stmt->execute([':year' => $year]);
        $minMonth = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT 
                EXTRACT(MONTH FROM created_at) AS month,
                SUM(total_price) AS total_revenue
            FROM orders
            WHERE EXTRACT(YEAR FROM created_at) = :year
            GROUP BY month
            ORDER BY total_revenue DESC
            LIMIT 1
        ");
        $stmt->execute([':year' => $year]);
        $maxRevenueMonth = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'max_orders' => $maxMonth,
            'min_orders' => $minMonth,
            'max_revenue' => $maxRevenueMonth
        ];
    }

    // Получаем данные аналитики
    $currentYear = date('Y');
    $ordersByWeek = getOrdersByWeek($pdo, 2, 10, $currentYear);
    $inactiveWeeks = getInactivePeriods($pdo, 2, 10, $currentYear);
    $peakValues = getPeakValues($pdo, $currentYear);

    // Подготовка данных для графиков
    $chartLabels = [];
    $chartOrderData = [];
    $chartRevenueData = [];

    foreach ($ordersByWeek as $row) {
        $chartLabels[] = $row['week_number'] . ' неделя';
        $chartOrderData[] = $row['order_count'];
        $chartRevenueData[] = $row['total_revenue'];
    }

} catch (PDOException $e) {
    die("<div class='error'>Ошибка базы данных: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика заказов и структура БД</title>
        <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/style_clothes.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #5f1456;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .highlight {
            background-color: #e6f7ff;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            padding: 15px;
            background: #f8d7da;
            border-radius: 4px;
            margin: 20px 0;
        }
        .success {
            color: #28a745;
            padding: 15px;
            background: #d4edda;
            border-radius: 4px;
            margin: 20px 0;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        .chart-small {
            height: 300px;
        }
        .chart-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .chart-wrapper {
            flex: 1;
            min-width: 300px;
        }
        .database-section {
            margin-top: 40px;
        }
        .table-structure {
            margin-bottom: 30px;
        }
        .table-data {
            margin-top: 20px;
        }
        .toggle-btn {
            background: #5f1456;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .toggle-btn:hover {
            background: #4a1043;
        }
        .hidden {
            display: none;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div class="top">
            <a href="index.php"><img src="photo/logo.png" class="icon" alt="Лого"></a>
        </div>
        
        <!-- Основное меню -->
        <ul class="main-nav">
            <li><a href="women.php">women</a></li>
            <li><a href="men.php">men</a></li>
            <li><a href="unisex.php">unisexual</a></li>
            <li><a href="recommendations.php">rec</a></li>
        </ul>
        
        <!-- Кнопки администратора -->
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="admin-buttons-container">
            <div class="admin-buttons">
                <ul>
                    <li><a href="add_product.php">Добавить товар</a></li>
                    <li><a href="stats.php">Аналитика</a></li>
                    <!-- <li><a href="view_clients.php">Просмотр клиентов</a></li>
                    <li><a href="view_orders.php">Просмотр заказов</a></li> -->
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Иконки пользователя -->
        <div class="icons">
            <a href="likes.php"><img src="photo/liked.png" alt="Избранное"></a>
            <a href="bag.php"><img src="photo/корзина.png" alt="Корзина"></a>
            <a href="human.php"><img src="photo/череп.png" alt="Профиль"></a>
        </div>
    </header>
    <div class="container">
        <h1>Аналитика заказов и структура базы данных</h1>
        
        <!-- 1. Распределение товаров по категориям -->
        <div class="card">
            <h2>Распределение товаров по категориям</h2>
            <div class="chart-row">
                <div class="chart-wrapper">
                    <div class="chart-container chart-small">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Категория</th>
                                <th>Количество</th>
                                <th>Процент</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryDistribution as $category): ?>
                                <tr>
                                    <td><?= htmlspecialchars($category['gender']) ?></td>
                                    <td><?= htmlspecialchars($category['count']) ?></td>
                                    <td><?= htmlspecialchars($category['percentage']) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- 2. Количество заказов по неделям -->
        <div class="card">
            <h2>Количество заказов по неделям (с 2 по 10 неделю <?= $currentYear ?>)</h2>
            <div class="chart-row">
                <div class="chart-wrapper">
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Неделя</th>
                        <th>Количество заказов</th>
                        <th>Общая выручка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ordersByWeek as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['week_number']) ?></td>
                            <td><?= htmlspecialchars($row['order_count']) ?></td>
                            <td><?= htmlspecialchars(number_format($row['total_revenue'], 2)) ?> ₽</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 3. Недели без заказов -->
        <div class="card">
            <h2>Недели без заказов</h2>
            <?php if (!empty($inactiveWeeks)): ?>
                <p>Следующие недели не содержат заказов:</p>
                <ul>
                    <?php foreach ($inactiveWeeks as $week): ?>
                        <li><?= htmlspecialchars($week) ?> неделя</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="success">Все недели в указанном периоде содержат заказы.</p>
            <?php endif; ?>
        </div>
        
        <!-- 4. Пиковые значения -->
        <div class="card">
            <h2>Пиковые значения за <?= $currentYear ?> год</h2>
            <div class="chart-container" style="height: 300px;">
                <canvas id="peakChart"></canvas>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Тип</th>
                        <th>Месяц</th>
                        <th>Количество заказов</th>
                        <th>Общая выручка</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="highlight">
                        <td>Максимальное количество заказов</td>
                        <td><?= htmlspecialchars($peakValues['max_orders']['month'] ?? 'Н/Д') ?></td>
                        <td><?= htmlspecialchars($peakValues['max_orders']['order_count'] ?? 'Н/Д') ?></td>
                        <td><?= htmlspecialchars(number_format($peakValues['max_orders']['total_revenue'] ?? 0, 2)) ?> ₽</td>
                    </tr>
                    <tr>
                        <td>Минимальное количество заказов</td>
                        <td><?= htmlspecialchars($peakValues['min_orders']['month'] ?? 'Н/Д') ?></td>
                        <td><?= htmlspecialchars($peakValues['min_orders']['order_count'] ?? 'Н/Д') ?></td>
                        <td><?= htmlspecialchars(number_format($peakValues['min_orders']['total_revenue'] ?? 0, 2)) ?> ₽</td>
                    </tr>
                    <tr class="highlight">
                        <td>Максимальная выручка</td>
                        <td><?= htmlspecialchars($peakValues['max_revenue']['month'] ?? 'Н/Д') ?></td>
                        <td>-</td>
                        <td><?= htmlspecialchars(number_format($peakValues['max_revenue']['total_revenue'] ?? 0, 2)) ?> ₽</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- 5. Структура базы данных -->
        <div class="database-section">
            <h2>Структура базы данных</h2>
            <?php foreach ($tables as $table): ?>
                <div class="card table-structure">
                    <h3>Таблица: <?= htmlspecialchars($table) ?></h3>
                    <h4>Структура таблицы</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Имя столбца</th>
                                <th>Тип данных</th>
                                <th>NULL</th>
                                <th>Значение по умолчанию</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tablesStructure[$table] as $column): ?>
                                <tr>
                                    <td><?= htmlspecialchars($column['column_name']) ?></td>
                                    <td><?= htmlspecialchars($column['data_type']) ?></td>
                                    <td><?= htmlspecialchars($column['is_nullable']) ?></td>
                                    <td><?= htmlspecialchars($column['column_default'] ?? 'NULL') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="toggle-btn" onclick="toggleData('<?= htmlspecialchars($table) ?>')">
                        Показать/скрыть данные
                    </button>
                    <div id="data-<?= htmlspecialchars($table) ?>" class="table-data hidden">
                        <h4>Первые 5 записей</h4>
                        <?php if (!empty($tablesData[$table])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <?php foreach ($tablesStructure[$table] as $column): ?>
                                            <th><?= htmlspecialchars($column['column_name']) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tablesData[$table] as $row): ?>
                                        <tr>
                                            <?php foreach ($tablesStructure[$table] as $column): ?>
                                                <td>
                                                    <?php 
                                                    $value = $row[$column['column_name']] ?? 'NULL';
                                                    if (is_array($value)) {
                                                        echo 'ARRAY';
                                                    } else {
                                                        echo htmlspecialchars(var_export($value, true));
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Таблица пуста</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Функция для переключения отображения данных таблицы
        function toggleData(tableName) {
            const element = document.getElementById('data-' + tableName);
            element.classList.toggle('hidden');
        }

        // Круговая диаграмма распределения товаров по категориям
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($categoryLabels) ?>,
                datasets: [{
                    data: <?= json_encode($categoryData) ?>,
                    backgroundColor: <?= json_encode($categoryColors) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // График количества заказов по неделям
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Количество заказов',
                    data: <?= json_encode($chartOrderData) ?>,
                    backgroundColor: 'rgba(95, 20, 86, 0.7)',
                    borderColor: 'rgba(95, 20, 86, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Количество заказов'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Неделя'
                        }
                    }
                }
            }
        });

        // График выручки по неделям
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Выручка (₽)',
                    data: <?= json_encode($chartRevenueData) ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Выручка (₽)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Неделя'
                        }
                    }
                }
            }
        });

        // График пиковых значений
        const peakCtx = document.getElementById('peakChart').getContext('2d');
        const peakChart = new Chart(peakCtx, {
            type: 'bar',
            data: {
                labels: ['Макс заказы', 'Мин заказы', 'Макс выручка'],
                datasets: [{
                    label: 'Количество заказов',
                    data: [
                        <?= $peakValues['max_orders']['order_count'] ?? 0 ?>,
                        <?= $peakValues['min_orders']['order_count'] ?? 0 ?>,
                        0
                    ],
                    backgroundColor: 'rgba(95, 20, 86, 0.7)',
                    borderColor: 'rgba(95, 20, 86, 1)',
                    borderWidth: 1
                }, {
                    label: 'Выручка (₽)',
                    data: [
                        <?= $peakValues['max_orders']['total_revenue'] ?? 0 ?>,
                        <?= $peakValues['min_orders']['total_revenue'] ?? 0 ?>,
                        <?= $peakValues['max_revenue']['total_revenue'] ?? 0 ?>
                    ],
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Количество заказов'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Выручка (₽)'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>