<?php
session_start();
require_once __DIR__ . '/db.php';
if (empty($_SESSION['tamkeen_csrf'])) {
    $_SESSION['tamkeen_csrf'] = bin2hex(random_bytes(32));
}
function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function csrf_ok(): bool {
    return isset($_POST['csrf_token'], $_SESSION['tamkeen_csrf'])
        && hash_equals($_SESSION['tamkeen_csrf'], (string)$_POST['csrf_token']);
}
$views = [
    'login',
    'register',
    'registered',
    'forgot',
    'verify',
    'reset',
    'success'
];
$view = $_GET['view'] ?? 'login';
if (!in_array($view, $views, true)) {
    $view = 'login';
}
$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'انتهت صلاحية الطلب. حدّثي الصفحة ثم حاولي مرة أخرى.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'login') {
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($phone === '' || $password === '') {
                $errors[] = 'أدخلي رقم الجوال وكلمة المرور.';
            } else {
                $errors[] = 'تسجيل الدخول غير مفعّل بعد. يلزم ربطه بجدول المستخدمين في قاعدة البيانات.';
            }
            $view = 'login';
        } elseif ($action === 'register') {
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if ($name === '' || $phone === '' || $password === '' || $confirm === '') {
                $errors[] = 'يرجى تعبئة جميع الحقول.';
            }
            if ($name !== '' && mb_strlen($name) < 3) {
                $errors[] = 'يجب أن يتكون الاسم من 3 أحرف على الأقل.';
            }
            if ($phone !== '' && !preg_match('/^\+?[0-9\s()\-]{7,20}$/', $phone)) {
                $errors[] = 'أدخلي رقم جوال صحيحًا.';
            }
            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.';
            }
            if ($password !== $confirm) {
                $errors[] = 'كلمتا المرور غير متطابقتين.';
            }
            if (!$errors) {
                $_SESSION['tamkeen_register_name'] = $name;
                $_SESSION['tamkeen_register_phone'] = $phone;
                $errors[] = 'واجهة التسجيل جاهزة، لكن حفظ الحساب غير مفعّل حتى الآن. يلزم ربط نموذج التسجيل بجدول المستخدمين في قاعدة البيانات.';
            }
            $view = 'register';
        } elseif ($action === 'send_code') {
            $phone = trim((string)($_POST['phone'] ?? ''));
            if (!preg_match('/^\+?[0-9\s()\-]{7,20}$/', $phone)) {
                $errors[] = 'أدخلي رقم جوال صحيحًا.';
            } else {
                $_SESSION['tamkeen_pending_phone'] =
                    preg_replace('/[\s()\-]/', '', $phone);
                $notice = 'واجهة طلب رمز التحقق جاهزة، لكن لم يتم إرسال رسالة SMS. يلزم ربط مزود رسائل.';
            }
            $view = 'forgot';
        } elseif ($action === 'verify_code') {
            $errors[] = 'التحقق من الرمز غير مفعّل بعد. يلزم ربط خدمة SMS والتحقق من رمز مؤقت آمن.';
            $view = 'verify';
        } elseif ($action === 'reset_password') {
            $password = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (strlen($password) < 8) {
                $errors[] = 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.';
            }
            if ($password !== $confirm) {
                $errors[] = 'كلمتا المرور غير متطابقتين.';
            }
            if (!$errors) {
                $errors[] = 'تغيير كلمة المرور غير مفعّل حتى الآن. يجب ربطه بقاعدة البيانات والتحقق من رمز الجوال.';
            }
            $view = 'reset';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    && isset($_GET['view'])
    && in_array($_GET['view'], $views, true)) {
    $view = $_GET['view'];
}
$titles = [
    'login' => 'تسجيل الدخول',
    'register' => 'إنشاء حساب',
    'registered' => 'تأكيد التسجيل',
    'forgot' => 'نسيت كلمة المرور؟',
    'verify' => 'التحقق من رقم الجوال',
    'reset' => 'كلمة مرور جديدة',
    'success' => 'تم تغيير كلمة المرور'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titles[$view]) ?></title>
<style>
:root {
    --navy: #103B63;
    --blue: #245A83;
    --white: #fff;
    --heading: #102C4C;
    --text: #344054;
    --muted: #687789;
    --border: #E3E8EF;
    --shadow: 0 12px 35px rgba(16,44,76,.10);
    --radius: 14px;
}
* {
    box-sizing: border-box;
}
html {
    min-height: 100%;
}
body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #fff, #F6F8FA);
    color: var(--text);
    font-family: Tahoma, Arial, sans-serif;
    font-size: 15px;
    line-height: 1.7;
}
a {
    color: inherit;
    text-decoration: none;
}
button,
input {
    font: inherit;
}
button:focus-visible,
a:focus-visible,
input:focus-visible {
    outline: 3px solid rgba(36,90,131,.25);
    outline-offset: 3px;
}
/* توسيط جميع صفحات المصادقة */
.page {
    width: min(480px, calc(100% - 32px));
    min-height: 100vh;
    margin: 0 auto;
    padding: 28px 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card {
    width: 100%;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: clamp(24px, 5vw, 42px);
    min-width: 0;
}
/* العناوين */
.card h2 {
    margin: 0 0 8px;
    color: var(--heading);
    font-size: 30px;
    line-height: 1.4;
}
.subtitle {
    margin: 0 0 26px;
    color: var(--muted);
    font-size: 15px;
    line-height: 1.9;
}
/* الحقول */
.field {
    margin-bottom: 17px;
}
.field label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--heading);
}
.field input {
    display: block;
    width: 100%;
    min-height: 52px;
    padding: 12px 15px;
    border: 1px solid #D8E0E8;
    border-radius: 10px;
    background: #fff;
    color: var(--text);
    transition: border-color .2s, box-shadow .2s;
}
.field input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(36,90,131,.1);
    outline: none;
}
.field input::placeholder {
    color: #9AA5B1;
}
/* صف التذكر واستعادة كلمة المرور */
.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin: 2px 0 22px;
    font-size: 13px;
}
.remember {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--muted);
}
.remember input {
    accent-color: var(--navy);
}
.text-link {
    color: var(--navy);
    font-weight: 700;
}
/* الأزرار */
.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 52px;
    padding: 12px 16px;
    border: 0;
    border-radius: 10px;
    background: var(--navy);
    color: #fff;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s, transform .2s;
}
.btn:hover {
    background: var(--blue);
    transform: translateY(-1px);
}
.secondary {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 49px;
    padding: 10px 15px;
    margin-top: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--navy);
    font-weight: 700;
}
.bottom-note {
    text-align: center;
    margin: 23px 0 0;
    font-size: 14px;
    color: var(--muted);
}
/* رسائل التنبيه */
.alert {
    padding: 12px 14px;
    margin: 0 0 18px;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}
.error {
    background: #FFF0F0;
    border: 1px solid #F5CACA;
    color: #9B2525;
}
.success-alert {
    background: #F0FAF3;
    border: 1px solid #CBEBD4;
    color: #236B3A;
}
.alert ul {
    margin: 0;
    padding-right: 20px;
}
/* رمز التحقق */
.code-inputs {
    display: flex;
    direction: ltr;
    gap: 8px;
    justify-content: space-between;
    margin: 20px 0;
}
.code-inputs input {
    width: calc((100% - 40px) / 6);
    min-width: 0;
    height: 52px;
    text-align: center;
    font-size: 22px;
    font-weight: 800;
    border: 1px solid #D8E0E8;
    border-radius: 9px;
}
.hint {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.9;
    margin: 0 0 18px;
}
/* نجاح العملية */
.success-mark {
    width: 66px;
    height: 66px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #EAF8EE;
    color: #208044;
    font-size: 34px;
    margin: 0 auto 20px;
}
.center {
    text-align: center;
}
.footer {
    text-align: center;
    padding: 0 16px 20px;
    color: var(--muted);
    font-size: 11px;
}
@media (max-width: 480px) {
    .page {
        width: calc(100% - 24px);
        padding: 18px 0;
    }
    .card {
        padding: 27px 21px;
    }
    .card h2 {
        font-size: 26px;
    }
    .subtitle {
        font-size: 14px;
    }
}
@media (max-width: 360px) {
    .page {
        width: calc(100% - 18px);
    }
    .card {
        padding: 22px 15px;
    }
    .code-inputs {
        gap: 5px;
    }
    .code-inputs input {
        height: 45px;
    }
}
</style>
</head>
<body>
<main class="page">
<section class="card">
<?php if ($errors): ?>
<div class="alert error" role="alert">
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= h($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<?php if ($notice !== ''): ?>
<div class="alert success-alert" role="status">
    <?= h($notice) ?>
</div>
<?php endif; ?>
<?php if ($view === 'login'): ?>
<h2>تسجيل الدخول</h2>
<p class="subtitle">
أدخلي رقم الجوال وكلمة المرور للدخول إلى حسابك.
</p>
<form method="post" action="login.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="login">
    <div class="field">
        <label for="phone">رقم الجوال</label>
        <input
            id="phone"
            name="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="أدخلي رقم الجوال"
            required
        >
    </div>
    <div class="field">
        <label for="password">كلمة المرور</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            placeholder="أدخلي كلمة المرور"
            required
        >
    </div>
    <div class="form-row">
        <label class="remember">
            <input type="checkbox" name="remember" value="1">
            تذكرني
        </label>
        <a class="text-link" href="login.php?view=forgot">
            نسيت كلمة المرور؟
        </a>
    </div>
    <button class="btn" type="submit">تسجيل الدخول</button>
</form>
<p class="bottom-note">
    ليس لديك حساب؟
    <a class="text-link" href="login.php?view=register">إنشاء حساب جديد</a>
</p>
<?php elseif ($view === 'register'): ?>
<h2>إنشاء حساب جديد</h2>
<p class="subtitle">
أنشئي حسابك لتتمكني من متابعة مشترياتك وطلباتك.
</p>
<form method="post" action="login.php?view=register" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="register">
    <div class="field">
        <label for="name">الاسم الكامل</label>
        <input
            id="name"
            name="name"
            type="text"
            autocomplete="name"
            placeholder="أدخلي الاسم الكامل"
            minlength="3"
            value="<?= h($_POST['name'] ?? '') ?>"
            required
        >
    </div>
    <div class="field">
        <label for="register_phone">رقم الجوال</label>
        <input
            id="register_phone"
            name="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="أدخلي رقم الجوال"
            value="<?= h($_POST['phone'] ?? '') ?>"
            required
        >
    </div>
    <div class="field">
        <label for="register_password">كلمة المرور</label>
        <input
            id="register_password"
            name="password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            placeholder="8 أحرف على الأقل"
            required
        >
    </div>
    <div class="field">
        <label for="confirm_password">تأكيد كلمة المرور</label>
        <input
            id="confirm_password"
            name="confirm_password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            placeholder="أعيدي كتابة كلمة المرور"
            required
        >
    </div>
    <p class="hint">
        استخدمي كلمة مرور لا تقل عن 8 أحرف.
    </p>
    <button class="btn" type="submit">إنشاء الحساب</button>
</form>
<a class="secondary" href="login.php">العودة إلى تسجيل الدخول</a>
<?php elseif ($view === 'registered'): ?>
<div class="success-mark" aria-hidden="true">✓</div>
<h2 class="center">تم إرسال بيانات التسجيل</h2>
<p class="subtitle center">
هذه صفحة تأكيد شكلية. لن يتم إنشاء الحساب فعليًا حتى يتم ربط التسجيل بقاعدة البيانات.
</p>
<a class="btn" href="login.php">العودة إلى تسجيل الدخول</a>
<?php elseif ($view === 'forgot'): ?>
<h2>نسيت كلمة المرور؟</h2>
<p class="subtitle">
أدخلي رقم الجوال المرتبط بحسابك لبدء استعادة كلمة المرور.
</p>
<form method="post" action="login.php?view=forgot" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="send_code">
    <div class="field">
        <label for="forgot_phone">رقم الجوال</label>
        <input
            id="forgot_phone"
            name="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            placeholder="أدخلي رقم الجوال مع رمز الدولة"
            value="<?= h($_POST['phone'] ?? '') ?>"
            required
        >
    </div>
    <button class="btn" type="submit">إرسال رمز التحقق</button>
</form>
<a class="secondary" href="login.php">العودة لتسجيل الدخول</a>
<?php elseif ($view === 'verify'): ?>
<h2>التحقق من رقم الجوال</h2>
<p class="subtitle">
أدخلي رمز التحقق المكوّن من 6 أرقام.
</p>
<form method="post" action="login.php?view=verify" id="verifyForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="verify_code">
    <input type="hidden" name="code" id="combinedCode">
    <div class="code-inputs" aria-label="رمز التحقق">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input
                class="code-digit"
                type="text"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="1"
                aria-label="الخانة <?= $i + 1 ?>"
                required
            >
        <?php endfor; ?>
    </div>
    <p class="hint">
        التحقق الفعلي يتطلب ربط خدمة الرسائل النصية.
    </p>
    <button class="btn" type="submit">التحقق من الرمز</button>
</form>
<a class="secondary" href="login.php?view=forgot">تغيير رقم الجوال</a>
<?php elseif ($view === 'reset'): ?>
<h2>إنشاء كلمة مرور جديدة</h2>
<p class="subtitle">
اختاري كلمة مرور جديدة ثم أعيدي كتابتها للتأكيد.
</p>
<form method="post" action="login.php?view=reset" id="resetForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="reset_password">
    <div class="field">
        <label for="new_password">كلمة المرور الجديدة</label>
        <input
            id="new_password"
            name="new_password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            placeholder="كلمة المرور الجديدة"
            required
        >
    </div>
    <div class="field">
        <label for="confirm_new_password">تأكيد كلمة المرور</label>
        <input
            id="confirm_new_password"
            name="confirm_password"
            type="password"
            autocomplete="new-password"
            minlength="8"
            placeholder="أعيدي كتابة كلمة المرور"
            required
        >
    </div>
    <p class="hint">
        يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.
    </p>
    <button class="btn" type="submit">إعادة تعيين كلمة المرور</button>
</form>
<a class="secondary" href="login.php">العودة لتسجيل الدخول</a>
<?php elseif ($view === 'success'): ?>
<div class="success-mark" aria-hidden="true">✓</div>
<h2 class="center">تم تغيير كلمة المرور بنجاح</h2>
<p class="subtitle center">
يمكنك الآن العودة إلى صفحة تسجيل الدخول.
</p>
<a class="btn" href="login.php">العودة لتسجيل الدخول</a>
<?php endif; ?>
</section>
</main>
<footer class="footer">
    © 2026 جميع الحقوق محفوظة.
</footer>
<script>
(function () {
    const digits = [...document.querySelectorAll('.code-digit')];
    const verifyForm = document.getElementById('verifyForm');
    if (verifyForm && digits.length) {
        digits.forEach((input, index) => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(-1);
                if (this.value && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !this.value && index > 0) {
                    digits[index - 1].focus();
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    digits[index - 1].focus();
                }
                if (event.key === 'ArrowRight' && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            });
            input.addEventListener('paste', function (event) {
                const pasted = (
                    event.clipboardData || window.clipboardData
                ).getData('text').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                event.preventDefault();
                pasted.split('').forEach((value, index) => {
                    if (digits[index]) {
                        digits[index].value = value;
                    }
                });
                digits[Math.min(pasted.length, digits.length) - 1]?.focus();
            });
        });
        verifyForm.addEventListener('submit', function (event) {
            const code = digits.map(input => input.value).join('');
            if (code.length !== 6) {
                event.preventDefault();
                digits.find(input => !input.value)?.focus();
                return;
            }
            document.getElementById('combinedCode').value = code;
        });
    }
    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        const password = document.getElementById('new_password');
        const confirm = document.getElementById('confirm_new_password');
        resetForm.addEventListener('submit', function (event) {
            if (password.value !== confirm.value) {
                event.preventDefault();
                confirm.setCustomValidity('كلمتا المرور غير متطابقتين.');
                confirm.reportValidity();
            } else {
                confirm.setCustomValidity('');
            }
        });
        confirm.addEventListener('input', function () {
            confirm.setCustomValidity('');
        });
    }
    const registerForm = document.querySelector(
        'form input[name="action"][value="register"]'
    )?.closest('form');
    if (registerForm) {
        const password = document.getElementById('register_password');
        const confirm = document.getElementById('confirm_password');
        registerForm.addEventListener('submit', function (event) {
            if (password.value !== confirm.value) {
                event.preventDefault();
                confirm.setCustomValidity('كلمتا المرور غير متطابقتين.');
                confirm.reportValidity();
            } else {
                confirm.setCustomValidity('');
            }
        });
        confirm.addEventListener('input', function () {
            confirm.setCustomValidity('');
        });
    }
})();
</script>
</body>
</html>