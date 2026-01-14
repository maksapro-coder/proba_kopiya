<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

$error_message = '';

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = trim($_POST["email"] ?? '');
        $password = trim($_POST["password"] ?? '');
        
        if (empty($email) || empty($password)) {
            throw new Exception("Заполните все поля");
        }

        $stmt = $conn->prepare("SELECT id, email, password, role FROM client WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role']; // Используем поле из БД
            $_SESSION['login_success'] = true; // Добавляем флаг успешного входа
            
            header("Location: index.php");
            exit();
        }else {
            $error_message = "Неверные учетные данные";
        }
    }
} catch(PDOException $e) {
    $error_message = "Ошибка системы. Попробуйте позже.";
    error_log("DB Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <h1>Вход</h1>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Email:</label>
            <input type="email" name="email" required>
            
            <label>Пароль:</label>
            <input type="password" name="password" required>
            
            <input type="submit" value="Войти">
        </form>

        <p class="register-link">Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a></p>
    </div>
</body>
</html>