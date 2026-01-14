<?php
$host = 'localhost';
$dbname = 'postgres';
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
// Получаем статистику
$stats = $pdo->query("
    SELECT 
        DATE(start_time) as day,
        COUNT(*) as total_operations,
        AVG(EXTRACT(EPOCH FROM (end_time - start_time))) as avg_duration_sec,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM recommendation_logs
    GROUP BY DATE(start_time)
    ORDER BY day DESC
    LIMIT 7
")->fetchAll();

// Последние операции
$lastOperations = $pdo->query("
    SELECT rl.*, c.username
    FROM recommendation_logs rl
    LEFT JOIN client c ON rl.client_id = c.id
    ORDER BY start_time DESC
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Мониторинг рекомендаций</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard { display: flex; flex-wrap: wrap; gap: 20px; }
        .card { flex: 1; min-width: 300px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        tr:hover { background-color: #f5f5f5; }
        .success { color: green; }
        .failed { color: red; }
        .processing { color: orange; }
    </style>
</head>
<body>
    <h1>Мониторинг системы рекомендаций</h1>
    
    <div class="dashboard">
        <div class="card">
            <h2>Статистика за 7 дней</h2>
            <canvas id="statsChart"></canvas>
        </div>
        
        <div class="card">
            <h2>Последние операции</h2>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Пользователь</th>
                        <th>Тип</th>
                        <th>Длительность</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lastOperations as $log): ?>
                    <tr>
                        <td><?= $log['start_time'] ?></td>
                        <td><?= htmlspecialchars($log['username'] ?? 'Система') ?></td>
                        <td><?= $log['action_type'] ?></td>
                        <td>
                            <?= $log['end_time'] 
                                ? round($log['execution_time'] ?? strtotime($log['end_time']) - strtotime($log['start_time'])) . ' сек'
                                : 'В процессе' ?>
                        </td>
                        <td class="<?= $log['status'] ?>">
                            <?= $log['status'] ?>
                            <?php if ($log['status'] == 'failed' && $log['error_message']): ?>
                                <br><small><?= htmlspecialchars($log['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($stats, 'day')) ?>,
                datasets: [
                    {
                        label: 'Успешные',
                        data: <?= json_encode(array_column($stats, 'success_count')) ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)'
                    },
                    {
                        label: 'Ошибки',
                        data: <?= json_encode(array_column($stats, 'failed_count')) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)'
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Количество операций' }
                    },
                    x: {
                        title: { display: true, text: 'Дата' }
                    }
                }
            }
        });
    </script>
</body>
</html>