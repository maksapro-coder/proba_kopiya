<?php
// Старт сессии с проверкой
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключение к базе данных
// Подключение к базе данных PostgreSQL
$host = 'localhost';
$dbname = 'Mary';
$user = 'postgres';
$password = '1234';
$port = 5432;

$error_message = '';
$success_message = '';

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $name = $_POST["name"] ?? '';
        $email = $_POST["email"] ?? '';
        $password = password_hash($_POST["password"] ?? '', PASSWORD_DEFAULT);
        $role = $_POST["role"] ?? 'user';
    
        // Валидация данных
        if (empty($name) || empty($email) || empty($_POST["password"])) {
            $error_message = "Все поля обязательны для заполнения!";
        } else {
            // Проверка существующего email
            $stmt = $conn->prepare("SELECT id FROM client WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = "Пользователь с таким email уже существует!";
            } else {
                // Вставка данных
                $stmt = $conn->prepare("INSERT INTO client (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $role]);
                
                $success_message = "Регистрация успешна! Теперь вы можете войти.";
                // Очищаем POST, чтобы при обновлении страницы форма не отправлялась повторно
                header("Location: login.php");
                exit();
            }
        }
    }
    
} catch(PDOException $e) {
    $error_message = "Ошибка подключения к базе данных: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
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
        </ul>
        
        <div class="icons">
            <a href="likes.php"><img src="photo/liked.png" alt="Избранное"></a>
            <a href="bag.php"><img src="photo/корзина.png" alt="Корзина"></a>
            <a href="human.php"><img src="photo/череп.png" alt="Профиль"></a>
        </div>
    </header>
    
    <main class="register-container">
        <h1>Регистрация</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success-message"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <form method="post" class="register-form">
            <div class="form-group">
                <label for="name">Имя:</label>
                <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="role">Роль:</label>
                <select id="role" name="role">
                    <option value="user" <?= ($_POST['role'] ?? '') == 'user' ? 'selected' : '' ?>>Пользователь</option>
                    <option value="admin" <?= ($_POST['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Администратор</option>
                </select>
            </div>
            
            <button type="submit" class="submit-btn">Зарегистрироваться</button>
        </form>
        
        <p class="login-link">Уже зарегистрированы? <a href="login.php">Войдите</a></p>
    </main>
</body>
</html>