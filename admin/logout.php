<?php
session_start();

header('Cache-Control: no-store');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['admin_logout_csrf'])) {
    $_SESSION['admin_logout_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['admin_logout_csrf'], $token)
    ) {
        $error = 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مرة أخرى.';
    } else {
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الخروج | تمكين</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');

:root {
    --navy: #103B63;
    --blue: #245A83;
    --heading: #102C4C;
    --text: #344054;
    --muted: #687789;
    --radius: 20px;
}

*, *::before, *::after {
    box-sizing: border-box;
}

html {
    min-height: 100%;
}

body {
    margin: 0;
    min-height: 100vh;
    direction: rtl;
    background:
        radial-gradient(
            ellipse 70% 55% at 50% 5%,
            #EAF1F8 0%,
            transparent 80%
        ),
        #F8FAFC;
    color: var(--text);
    font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif;
    font-size: 15px;
    line-height: 1.75;
    -webkit-font-smoothing: antialiased;
}

a {
    color: inherit;
    text-decoration: none;
}

button {
    font: inherit;
}

button, a {
    -webkit-tap-highlight-color: transparent;
}

button:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(36, 90, 131, .24);
    outline-offset: 3px;
}

.page {
    width: min(468px, calc(100% - 36px));
    min-height: calc(100vh - 40px);
    margin: 0 auto;
    padding: 40px 0 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card {
    width: 100%;
    min-width: 0;
    padding: clamp(30px, 6vw, 46px);
    background: rgba(255, 255, 255, .98);
    border: 1px solid #EDF1F5;
    border-radius: var(--radius);
    box-shadow: 0 12px 35px rgba(16, 44, 76, .035);
    direction: rtl;
}

.card h1 {
    margin: 0 0 15px;
    color: var(--heading);
    font-size: clamp(25px, 4vw, 29px);
    font-weight: 700;
    line-height: 1.55;
    letter-spacing: -.45px;
    text-align: center;
}

.subtitle {
    margin: 0 0 40px;
    color: var(--muted);
    font-size: 14px;
    line-height: 2;
    font-weight: 400;
    text-align: center;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 54px;
    padding: 12px 18px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: linear-gradient(110deg, #103B63, #1C507A);
    color: #FFFFFF;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(16, 59, 99, .06);
    transition: background .18s ease, box-shadow .18s ease;
}

.btn:hover {
    background: linear-gradient(110deg, #174C77, #28618A);
    box-shadow: 0 4px 12px rgba(16, 59, 99, .08);
}

.secondary {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 52px;
    padding: 11px 16px;
    margin-top: 14px;
    border: 1px solid #DCE5EE;
    border-radius: 12px;
    background: #FFFFFF;
    color: var(--navy);
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    transition: background .2s ease, border-color .2s ease;
}

.secondary:hover {
    background: #F6F9FC;
    border-color: #B9CADB;
}

.alert {
    padding: 13px 16px;
    margin: 0 0 22px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.9;
    overflow-wrap: anywhere;
}

.error {
    background: #FFF5F5;
    border: 1px solid #F2D7D7;
    color: #9B3030;
}

.footer {
    padding: 0 16px 20px;
    text-align: center;
    color: #8A9AA9;
    font-size: 11px;
}

@media (max-width: 480px) {
    .page {
        width: calc(100% - 26px);
        min-height: calc(100vh - 32px);
        padding: 20px 0 12px;
    }

    .card {
        padding: 31px 24px;
        border-radius: 18px;
    }

    .card h1 {
        font-size: 26px;
    }
}

@media (max-width: 360px) {
    .page {
        width: calc(100% - 18px);
    }

    .card {
        padding: 26px 17px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        transition: none !important;
    }
}
</style>
</head>

<body>
<main class="page">
    <section class="card" aria-labelledby="logout-title">

        <h1 id="logout-title">تسجيل الخروج</h1>

        <p class="subtitle">
            هل تريد تسجيل الخروج من لوحة الإدارة؟
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert error" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="logout.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['admin_logout_csrf']) ?>"
            >

            <button class="btn" type="submit">
                تسجيل الخروج
            </button>
        </form>

        <a class="secondary" href="dashboard.php">
            العودة إلى لوحة التحكم
        </a>

    </section>
</main>

<footer class="footer">
    © 2026 جميع الحقوق محفوظة.
</footer>
</body>
</html>