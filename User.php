<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="Navbar.css">
</head>
<body>
    <nav class="navbar">
    <div class="logo">VÝLETOS</div>
    <div class="search-box">
        <input type="text" placeholder="Vyhledejte výlet...">
        <button>hledat</button>
    </div>

    <ul class="menu">
        <li><button>Moje výlety</button></li>
        <li><button>Oblíbené výlety</button></li>
        <li>
            <button>
                Přihlášen jako: <?php echo $_SESSION['email']; ?>
            </button>
        </li>
    </ul>
</nav>

</body>
</html>