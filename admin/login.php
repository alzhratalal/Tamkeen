<?php
session_start();

header('Cache-Control: no-store');

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null)
        ? trim($_POST['username'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['admin_login_csrf'], $token)
    ) {
        $error = 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مرة أخرى.';
    } elseif ($username === '' || $password === '') {
        $error = 'أدخل اسم المستخدم وكلمة المرور.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT admin_id, username, password
                 FROM admins
                 WHERE username = ?
                 LIMIT 1'
            );

            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $admin &&
                password_verify($password, $admin['password'])
            ) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_logout_csrf'] = bin2hex(random_bytes(32));

                unset($_SESSION['admin_login_csrf']);

                header('Location: dashboard.php');
                exit;
            }

            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        } catch (PDOException $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = 'تعذّر تسجيل الدخول حاليًا. حاول مرة أخرى لاحقًا.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل دخول الإدارة | تمكين</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');


   #password::-ms-reveal,
    #password::-ms-clear{
        display: none;
    }

    
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

button, input {
    font: inherit;
}

button, a, input {
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
    margin: 0 0 30px;
    color: var(--muted);
    font-size: 14px;
    line-height: 2;
    font-weight: 400;
    text-align: center;
}

.field {
    margin-bottom: 20px;
}

.field label {
    display: block;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--heading);
    text-align: right;
}

.field input {
    display: block;
    width: 100%;
    min-height: 54px;
    padding: 12px 17px;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    background: #FCFDFE;
    color: var(--heading);
    font-size: 14px;
    font-weight: 500;
    direction: rtl;
    text-align: right;
    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}

.field input:hover {
    border-color: #B9C9D9;
}

.field input:focus {
    outline: none;
    border-color: #B8CADB;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(36, 90, 131, .045);
}

.field input::placeholder {
    color: #9AA9B9;
    font-weight: 400;
    direction: rtl;
    text-align: right;
}

.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-left: 54px;
}

.toggle-password {
    position: absolute;
    left: 7px;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    padding: 9px;
    display: grid;
    place-items: center;
    border: none;
    border-radius: 9px;
    background: transparent;
    color: #7B8FA2;
    cursor: pointer;
}

.toggle-password[hidden] {
    display: none;
}

.toggle-password:hover {
    background: #F1F6FA;
    color: var(--navy);
}

.toggle-password svg {
    width: 22px;
    height: 22px;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 54px;
    margin-top: 28px;
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

.bottom-note {
    margin: 27px 0 0;
    font-size: 13px;
    text-align: center;
}

.text-link {
    color: var(--navy);
    font-weight: 600;
}

.text-link:hover {
    color: #39759F;
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

    .subtitle {
        margin-bottom: 25px;
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
    <section class="card" aria-labelledby="login-title">

        <h1 id="login-title">تسجيل دخول الإدارة</h1>

        <p class="subtitle">
            أدخل اسم المستخدم وكلمة المرور للوصول إلى لوحة التحكم.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert error" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($_SESSION['admin_login_csrf']) ?>"
            >

            <div class="field">
                <label for="username">اسم المستخدم</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    placeholder="أدخل اسم المستخدم"
                    autocomplete="username"
                    value="<?= h($username) ?>"
                    required
                >
            </div>

            <div class="field">
                <label for="password">كلمة المرور</label>

                <div class="password-wrapper">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="أدخل كلمة المرور"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        id="togglePassword"
                        class="toggle-password"
                        aria-label="إظهار كلمة المرور"
                        aria-controls="password"
                        aria-pressed="false"
                        hidden
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                            <path
                                id="eyeSlash"
                                d="M3 3 21 21"
                                style="display: none"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <button class="btn" type="submit">
                تسجيل الدخول
            </button>
        </form>

        <p class="bottom-note">
            <a class="text-link" href="../index.php">
                العودة إلى المتجر
            </a>
        </p>

    </section>
</main>

<footer class="footer">
    © 2026 جميع الحقوق محفوظة.
</footer>

<script>
(() => {
    const input = document.getElementById('password');
    const button = document.getElementById('togglePassword');
    const slash = document.getElementById('eyeSlash');

    button.hidden = false;

    button.addEventListener('click', () => {
        const showPassword = input.type === 'password';

        input.type = showPassword ? 'text' : 'password';

        button.setAttribute(
            'aria-label',
            showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'
        );

        button.setAttribute('aria-pressed', String(showPassword));
        slash.style.display = showPassword ? '' : 'none';
    });
})();
</script>
</body>
</html>