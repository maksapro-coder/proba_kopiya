<?php
session_start();

// Обработка сообщений о входе
$message = '';
$class = '';

// }
$showWelcomeGif = false;
if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
    $showWelcomeGif = true;
    unset($_SESSION['login_success']);
}  
?>
<!DOCTYPE html>
<html>
<head>
    <title>Главная страница</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin_styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <script>
    window.onload = function() {
        <?php if($showWelcomeGif): ?>
            const gif = document.createElement('img');
            gif.src = 'photo/гифка.gif'; // Путь к вашей GIF-картинке
            gif.alt = 'Добро пожаловать';
            gif.className = 'welcome-gif';
            
            document.body.appendChild(gif);
            // Удаляем через 3 секунды
            setTimeout(() => {
                gif.remove();
            }, 3000);
        <?php endif; ?>
    };
</script>
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
    
    <main class="text">
        Rent the Runway
    </main>
    
    <div class="image-container">
        <img src="photo/опиум1.jpg" alt="кофта">
        <img src="photo/опиум3.jpg" alt="Юбка">
        <img src="photo/опиум2.jpg" alt="костюм">
    </div>
    
    <main class="past_text">
        New collection
    </main>
    
    <div class="image-container two-images">
        <img src="photo/лонгслив.jpg" alt="Юбка из новой коллекции">
        <img src="photo/свитер.jpg" alt="Юбка из новой коллекции">
    </div>
</body>
</html>