<?php
session_start();
require_once __DIR__ . '/../db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
    } else {
        try {
            $sql = "SELECT admin_id, username, password
                    FROM admins
                    WHERE username = ?
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
            }
        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء تسجيل الدخول. تحققي من قاعدة البيانات.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الأدمن | تمكين</title>
    <link rel="stylesheet" href="admin.css">
</head>
<script>
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', function () {
    const isHidden = passwordInput.type === 'password';

    passwordInput.type = isHidden ? 'text' : 'password';

    togglePassword.textContent = isHidden ? '🙈' : '👁️';

    togglePassword.setAttribute(
        'aria-label',
        isHidden ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'
    );
});
</script>
<body class="login-page">
<main class="login-container">
    <div class="login-logo">
        Tamkeen<span>.</span>
    </div>
    <h1>تسجيل دخول الأدمن</h1>
    <p class="login-description">
        سجّلي الدخول للوصول إلى لوحة التحكم وإدارة المتجر.
    </p>
    <?php if ($error !== ''): ?>
        <div class="error-message">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">اسم المستخدم</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="أدخلي اسم المستخدم"
                autocomplete="username"
                required
            >
        </div>
        <div class="form-group">
            <label for="password">كلمة المرور</label>
        <div class="password-wrapper">
    <input
        type="password"
        id="password"
        name="password"
        placeholder="أدخلي كلمة المرور"
        autocomplete="current-password"
        required
    >

    <button
        type="button"
        id="togglePassword"
        class="toggle-password"
        aria-label="إظهار كلمة المرور"
    >
        
    </button>
</div>
        </div>
        <button type="submit" class="login-button">
            تسجيل الدخول
        </button>
    </form>
    <a href="../index.php" class="back-link">
        العودة إلى المتجر
    </a>
</main>
</body>
</html>