<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'حدّثي الصفحة ثم حاولي مرة أخرى.';
    } else {
        // إزالة بيانات جلسة الإدارة
        foreach (array_keys($_SESSION) as $key) {
            if (strpos($key, 'admin_') === 0) {
                unset($_SESSION[$key]);
            }
        }

        unset(
            $_SESSION['product_success'],
            $_SESSION['order_success']
        );

        session_regenerate_id(true);

        header('Location: login.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تسجيل الخروج | تمكين</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: #f4f7fb;
            color: #24364b;
            font-family: Tahoma, Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 35px 25px;
            border: 1px solid #e8edf3;
            border-radius: 14px;
            text-align: center;
        }

        .logo {
            color: #103b63;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        .logo span {
            color: #ff8a24;
        }

        h1 {
            font-size: 23px;
            color: #103b63;
        }

        p {
            color: #728095;
            font-size: 14px;
            line-height: 1.9;
        }

        button {
            width: 100%;
            margin-top: 15px;
            padding: 14px;
            background: #103b63;
            color: white;
            border: none;
            border-radius: 8px;
            font: inherit;
            cursor: pointer;
        }

        button:hover {
            background: #245a83;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #103b63;
            font-size: 14px;
            text-decoration: none;
        }

        .error {
            background: #ffeded;
            color: #ad3434;
            padding: 12px;
            border-radius: 8px;
        }
    </style>
</head>

<body>
<main class="card">

    <div class="logo">Tamkeen<span>.</span></div>

    <h1>تسجيل الخروج</h1>
    <p>هل تريدين تسجيل الخروج من لوحة الإدارة؟</p>

    <?php if ($error !== ''): ?>
        <p class="error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="logout.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <button type="submit">تسجيل الخروج</button>
    </form>

    <a href="dashboard.php" class="back">
        العودة إلى لوحة التحكم
    </a>

</main>
</body>
</html>