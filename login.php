<?php
session_start();

/*
 * وضع تجريبي لعرض صفحات الحساب واستعادة كلمة المرور.
 * لا يرسل SMS ولا ينشئ مستخدمًا حقيقيًا ولا يغيّر كلمات المرور.
 * يجب استبدال التدفق التجريبي بعمليات آمنة مرتبطة بقاعدة البيانات قبل النشر.
 */

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

function go(string $view): void {
    header('Location: login.php?view=' . urlencode($view));
    exit;
}

function clear_demo_reset(): void {
    unset(
        $_SESSION['demo_phone'],
        $_SESSION['demo_code'],
        $_SESSION['demo_verified'],
        $_SESSION['demo_success']
    );
}

$views = ['login', 'register', 'registered', 'forgot', 'verify', 'reset', 'success'];
$view = (string)($_GET['view'] ?? 'login');
if (!in_array($view, $views, true)) {
    $view = 'login';
}

$errors = [];
$notice = '';

/* معالجة النماذج */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'انتهت صلاحية الطلب. حدّث الصفحة ثم حاول مرة أخرى.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'login') {
            // العرض التجريبي: لا يوجد تحقق فعلي من بيانات الدخول.
            $view = 'login';
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($phone === '' || $password === '') {
                $errors[] = 'أدخل رقم الجوال وكلمة المرور.';
            }

        } elseif ($action === 'register') {
            // العرض التجريبي: لا تُحفظ بيانات التسجيل في قاعدة البيانات.
            $view = 'register';
            $name = trim((string)($_POST['name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');

            if ($name === '' || $phone === '' || $password === '' || $confirm === '') {
                $errors[] = 'أدخل جميع البيانات المطلوبة.';
            }
            if ($name !== '' && mb_strlen($name) < 3) {
                $errors[] = 'يجب أن يتكون الاسم من 3 أحرف على الأقل.';
            }
            if ($phone !== '' && !preg_match('/^\+?[0-9\s()\-]{7,20}$/', $phone)) {
                $errors[] = 'أدخل رقم جوال صحيحًا.';
            }
            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.';
            }
            if ($password !== $confirm) {
                $errors[] = 'كلمتا المرور غير متطابقتين.';
            }
            if (!$errors) {
                $_SESSION['demo_registered'] = true;
                go('registered');
            }

        } elseif ($action === 'send_code') {
            // رمز ثابت للعرض التجريبي فقط، لا يُرسل إلى الهاتف.
            $view = 'forgot';
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($phone === '') {
                $errors[] = 'أدخل رقم الجوال.';
            } elseif (!preg_match('/^\+?[0-9\s()\-]{7,20}$/', $phone)) {
                $errors[] = 'أدخل رقم جوال صحيحًا.';
            } else {
                clear_demo_reset();
                $_SESSION['demo_phone'] = $phone;
                $_SESSION['demo_code'] = '123456';
                go('verify');
            }

        } elseif ($action === 'resend_code') {
            $view = 'verify';
            if (empty($_SESSION['demo_phone'])) {
                $errors[] = 'ابدأ من صفحة استعادة كلمة المرور.';
                $view = 'forgot';
            } else {
                $notice = 'الرمز التجريبي هو 123456. لم تُرسل رسالة نصية.';
            }

        } elseif ($action === 'verify_code') {
            $view = 'verify';
            $code = trim((string)($_POST['code'] ?? ''));
            if (empty($_SESSION['demo_phone'])) {
                $errors[] = 'ابدأ من صفحة استعادة كلمة المرور.';
                $view = 'forgot';
            } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
                $errors[] = 'أدخل رمزًا مكوّنًا من 6 أرقام.';
            } elseif (!hash_equals((string)($_SESSION['demo_code'] ?? ''), $code)) {
                $errors[] = 'الرمز غير صحيح. استخدم 123456 للتجربة.';
            } else {
                $_SESSION['demo_verified'] = true;
                go('reset');
            }

        } elseif ($action === 'reset_password') {
            // العرض التجريبي: لا تتغير كلمة مرور أي حساب فعليًا.
            $view = 'reset';
            $password = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (empty($_SESSION['demo_verified'])) {
                $errors[] = 'يجب التحقق من الرمز أولًا.';
                $view = 'forgot';
            } elseif (strlen($password) < 8) {
                $errors[] = 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.';
            } elseif ($password !== $confirm) {
                $errors[] = 'كلمتا المرور غير متطابقتين.';
            } else {
                clear_demo_reset();
                $_SESSION['demo_success'] = true;
                go('success');
            }
        }
    }
}

/* حماية ترتيب الصفحات التجريبية */
if ($view === 'registered' && empty($_SESSION['demo_registered'])) {
    $view = 'register';
}
if ($view === 'verify' && empty($_SESSION['demo_phone'])) {
    $view = 'forgot';
}
if ($view === 'reset' && empty($_SESSION['demo_verified'])) {
    $view = 'forgot';
}
if ($view === 'success' && empty($_SESSION['demo_success'])) {
    $view = 'login';
}

$titles = [
    'login' => 'تسجيل الدخول',
    'register' => 'إنشاء حساب جديد',
    'registered' => 'تأكيد إنشاء الحساب',
    'forgot' => 'استعادة كلمة المرور',
    'verify' => 'التحقق من الرمز',
    'reset' => 'إنشاء كلمة مرور جديدة',
    'success' => 'اكتمال استعادة كلمة المرور'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titles[$view]) ?> | تمكين</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');

:root {
    --navy: #103B63;
    --blue: #245A83;
    --heading: #102C4C;
    --text: #344054;
    --muted: #687789;
    --border: #DFE7EF;
    --white: #FFFFFF;
    --radius: 20px;
    --shadow: 0 24px 70px rgba(16, 44, 76, .075), 0 4px 18px rgba(16, 44, 76, .035);
}
*, *::before, *::after { box-sizing: border-box; }
html { min-height: 100%; }
body {
    margin: 0;
    min-height: 100vh;
    direction: rtl;
    background: radial-gradient(ellipse 70% 55% at 50% 5%, #EAF1F8 0%, transparent 80%), #F8FAFC;
    color: var(--text);
    font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif;
    font-size: 15px;
    line-height: 1.75;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
button, input { font: inherit; }
button, a, input { -webkit-tap-highlight-color: transparent; }
button:focus-visible, a:focus-visible, input:focus-visible {
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
    border: 1px solid rgba(218, 228, 238, .95);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    direction: rtl;
}
/* العنوان والوصف في الوسط في جميع الصفحات */
.card h2 {
    margin: 0 0 9px;
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
.field { margin-bottom: 20px; }
.field label {
    display: block;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 600;
    color: var(--heading);
    text-align: right;
    direction: rtl;
}
/* هذا هو كلاس الحقول الموجود في المشروع بالفعل */
.field input {
    display: block;
    width: 100%;
    min-height: 54px;
    padding: 12px 17px;
    border: 1px solid #DCE5EE;
    border-radius: 12px;
    background: #FCFDFE;
    color: var(--heading);
    font-size: 14px;
    font-weight: 500;
    direction: rtl;
    text-align: right;
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}
.field input:hover { border-color: #B9C9D9; }
.field input:focus {
    outline: none;
    border-color: #48789F;
    background: #FFFFFF;
    box-shadow: 0 0 0 4px rgba(36, 90, 131, .09);
}
.field input::placeholder {
    color: #9AA9B9;
    font-weight: 400;
    direction: rtl;
    text-align: right;
}
.form-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin: 3px 0 26px;
    font-size: 13px;
    direction: rtl;
}
/* مربع الاختيار بجانب «تذكرني» على اليمين */
.remember {
    display: inline-flex;
    align-items: center;
    flex-direction: row;
    gap: 9px;
    color: var(--muted);
    cursor: pointer;
    direction: rtl;
}
.remember input { width: 16px; height: 16px; margin: 0; accent-color: var(--navy); }
.text-link, .resend-button { color: var(--navy); font-weight: 600; transition: color .2s ease; }
.text-link:hover, .resend-button:hover { color: #39759F; }
.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 54px;
    padding: 12px 18px;
    border: 1px solid #103B63;
    border-radius: 12px;
    background: linear-gradient(110deg, #103B63, #1C507A);
    color: #FFFFFF;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 7px 15px rgba(16, 59, 99, .13);
    transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
}
.btn:hover {
    background: linear-gradient(110deg, #174C77, #28618A);
    transform: none;
    box-shadow: 0 4px 10px rgba(16, 59, 99, 0.08);
}
.btn:active { transform: translateY(0); box-shadow: 0 4px 10px rgba(16, 59, 99, .12); }
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
    transition: background .2s ease, border-color .2s ease;
}
.secondary:hover { background: #F6F9FC; border-color: #B9CADB; }
.bottom-note { margin: 27px 0 0; color: var(--muted); font-size: 13px; text-align: center; }
.bottom-note .text-link { margin-right: 3px; }
.alert { padding: 13px 16px; margin: 0 0 22px; border-radius: 12px; font-size: 13px; line-height: 1.9; overflow-wrap: anywhere; }
.error { background: #FFF5F5; border: 1px solid #F2D7D7; color: #9B3030; }
.success-alert { background: #F1FAF5; border: 1px solid #D0E9DB; color: #236B3A; }
.alert ul { margin: 0; padding-right: 19px; }
/* خانات الرمز مستثناة من محاذاة النصوص لليمين */
.code-inputs {
    display: flex;
    direction: ltr;
    gap: 9px;
    justify-content: center;
    margin: 24px 0 27px;
}
.code-inputs input {
    width: calc((100% - 45px) / 6);
    min-width: 0;
    height: 54px;
    border: 1px solid #DCE5EE;
    border-radius: 12px;
    background: #FCFDFE;
    color: var(--navy);
    text-align: center;
    direction: ltr;
    font-size: 23px;
    font-weight: 700;
    transition: border-color .2s ease, box-shadow .2s ease;
}
.code-inputs input:focus { outline: none; border-color: #48789F; box-shadow: 0 0 0 3px rgba(36, 90, 131, .1); background: #FFFFFF; }
.hint { margin: -1px 0 21px; color: var(--muted); font-size: 12px; line-height: 2; text-align: right; }
.success-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    margin: 2px auto 23px;
    border: 1px solid #D3EBDD;
    border-radius: 50%;
    background: #EDF9F1;
    color: #23824C;
    font-size: 34px;
    font-weight: 700;
}
.center { text-align: center; }
.card h2.center { font-size: 26px; }
.subtitle.center { margin-bottom: 30px; }
.footer { padding: 0 16px 20px; text-align: center; color: #8A9AA9; font-size: 11px; }
.demo-code {
    margin-bottom: 23px;
    padding: 14px 18px;
    border: 1px solid #DEE8F1;
    border-radius: 12px;
    background: #F5F9FC;
    color: var(--muted);
    text-align: center;
    font-size: 12px;
}
.demo-code strong {
    display: block;
    margin-top: 3px;
    color: var(--navy);
    font-size: 25px;
    font-weight: 700;
    letter-spacing: 7px;
    direction: ltr;
}
/* تخفيف حدود البطاقة والظل */
.card {
    border: 1px solid #EDF1F5;
    box-shadow: 0 12px 35px rgba(16, 44, 76, 0.035);
}

/* تخفيف حدود حقول الإدخال */
.field input {
    border: 1px solid #E8EDF3;
    background: #FCFDFE;
}

/* تخفيف الحدود عند الضغط على الحقل */
.field input:focus {
    outline: none;
    border-color: #B8CADB;
    box-shadow: 0 0 0 3px rgba(36, 90, 131, 0.045);
}

/* تخفيف حدود الأزرار والظل */
.btn {
    border: 1px solid transparent;
    box-shadow: 0 4px 10px rgba(16, 59, 99, 0.06);
}

/* مسافة بين وصف النجاح والزر */
.success-mark ~ .subtitle {
    margin-bottom: 40px;
}

/* مسافة بين العنوان والوصف */
.card h2 {
    margin-bottom: 15px;
}

/* تخفيف حدود حقول رمز التحقق */
.code-inputs input {
    border-color: #E8EDF3;
}

.code-inputs input:focus {
    border-color: #B8CADB;
    box-shadow: 0 0 0 3px rgba(36, 90, 131, 0.045);
}
.resend-area { display: flex; align-items: center; justify-content: center; gap: 18px; flex-wrap: wrap; margin: 23px 0 0; color: var(--muted); font-size: 13px; }
.resend-area form { margin: 0; }
.resend-area span { white-space: nowrap; }
.resend-button { padding: 0; border: 0; background: transparent; font-size: 13px; white-space: nowrap; cursor: pointer; }
@media (max-width: 480px) {
    .page { width: calc(100% - 26px); min-height: calc(100vh - 32px); padding: 20px 0 12px; }
    .card { padding: 31px 24px; border-radius: 18px; }
    .card h2 { font-size: 26px; }
    .subtitle { margin-bottom: 25px; }
    .code-inputs { gap: 7px; }
    .code-inputs input { width: calc((100% - 35px) / 6); height: 49px; font-size: 21px; }
    .resend-area { gap: 13px; }
}
@media (max-width: 360px) {
    .page { width: calc(100% - 18px); }
    .card { padding: 26px 17px; }
    .code-inputs { gap: 5px; }
    .code-inputs input { width: calc((100% - 25px) / 6); height: 45px; border-radius: 9px; }
    .resend-area { gap: 8px; font-size: 12px; }
    .resend-button { font-size: 12px; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
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
<div class="alert success-alert" role="status"><?= h($notice) ?></div>
<?php endif; ?>

<?php if ($view === 'login'): ?>
<h2>تسجيل الدخول</h2>
<p class="subtitle">أدخل رقم الجوال وكلمة المرور لتسجيل الدخول إلى حسابك.</p>

<form method="post" action="login.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="login">
    <div class="field">
        <label for="phone">رقم الجوال</label>
        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
               placeholder="أدخل رقم الجوال" dir="rtl"
               value="<?= h((($_POST['action'] ?? '') === 'login') ? ($_POST['phone'] ?? '') : '') ?>" required>
    </div>
    <div class="field">
        <label for="password">كلمة المرور</label>
        <input id="password" name="password" type="password" autocomplete="current-password"
               placeholder="أدخل كلمة المرور" dir="rtl" required>
    </div>
    <div class="form-row">
        <label class="remember">
            <input type="checkbox" name="remember" value="1">
            <span>تذكرني</span>
        </label>
        <a class="text-link" href="login.php?view=forgot">نسيت كلمة المرور؟</a>
    </div>
    <button class="btn" type="submit">تسجيل الدخول</button>
</form>
<p class="bottom-note">
    ليس لديك حساب؟
    <a class="text-link" href="login.php?view=register">إنشاء حساب جديد</a>
</p>

<?php elseif ($view === 'register'): ?>
<h2>إنشاء حساب جديد</h2>
<p class="subtitle">أنشئ حسابًا جديدًا لمتابعة مشترياتك وطلباتك.</p>
<form method="post" action="login.php?view=register" autocomplete="on" id="registerForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="register">
    <div class="field">
        <label for="name">الاسم الكامل</label>
        <input id="name" name="name" type="text" autocomplete="name"
               placeholder="أدخل الاسم الكامل" dir="rtl" minlength="3"
               value="<?= h($_POST['name'] ?? '') ?>" required>
    </div>
    <div class="field">
        <label for="register_phone">رقم الجوال</label>
        <input id="register_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
               placeholder="أدخل رقم الجوال" dir="rtl"
               value="<?= h($_POST['phone'] ?? '') ?>" required>
    </div>
    <div class="field">
        <label for="register_password">كلمة المرور</label>
        <input id="register_password" name="password" type="password" autocomplete="new-password"
               minlength="8" placeholder="أدخل كلمة المرور" dir="rtl" required>
    </div>
    <div class="field">
        <label for="confirm_password">تأكيد كلمة المرور</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password"
               minlength="8" placeholder="أعد كتابة كلمة المرور" dir="rtl" required>
    </div>
    <p class="hint">يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.</p>
    <button class="btn" type="submit">إنشاء الحساب</button>
</form>
<a class="secondary" href="login.php">العودة إلى تسجيل الدخول</a>

<?php elseif ($view === 'registered'): ?>
<div class="success-mark" aria-hidden="true">✓</div>
<h2>اكتملت خطوات إنشاء الحساب!</h2>
<a class="btn" href="login.php">العودة إلى تسجيل الدخول</a>

<?php elseif ($view === 'forgot'): ?>
<h2>استعادة كلمة المرور</h2>
<p class="subtitle">أدخل رقم الجوال للمتابعة إلى صفحة التحقق من الرمز.</p>
<form method="post" action="login.php?view=forgot" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="send_code">
    <div class="field">
        <label for="forgot_phone">رقم الجوال</label>
        <input id="forgot_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
               placeholder="أدخل رقم الجوال" dir="rtl"
               value="<?= h($_POST['phone'] ?? '') ?>" required>
    </div>
    <button class="btn" type="submit">متابعة</button>
</form>
<a class="secondary" href="login.php">العودة إلى تسجيل الدخول</a>

<?php elseif ($view === 'verify'): ?>
<h2>التحقق من الرمز</h2>
<p class="subtitle">أدخل رمز التحقق المكوّن من 6 أرقام.</p>
<div class="demo-code">رمز التحقق للعرض التجريبي فقط:<strong>123456</strong></div>
<form method="post" action="login.php?view=verify" id="verifyForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="verify_code">
    <input type="hidden" name="code" id="combinedCode">
    <div class="code-inputs" aria-label="رمز التحقق">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input class="code-digit" type="text" inputmode="numeric" pattern="[0-9]"
                   maxlength="1" autocomplete="off" aria-label="الخانة <?= $i + 1 ?>" required>
        <?php endfor; ?>
    </div>
    <button class="btn" type="submit">التحقق من الرمز</button>
</form>
<div class="resend-area">
    <span>لم يصلك الرمز؟</span>
    <form method="post" action="login.php?view=verify">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
        <input type="hidden" name="action" value="resend_code">
        <button class="resend-button" type="submit">إعادة إرسال الرمز</button>
    </form>
</div>

<?php elseif ($view === 'reset'): ?>
<h2>إنشاء كلمة مرور جديدة</h2>
<p class="subtitle">أدخل كلمة المرور الجديدة ثم أعد كتابتها للتأكيد.</p>
<form method="post" action="login.php?view=reset" id="resetForm">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['tamkeen_csrf']) ?>">
    <input type="hidden" name="action" value="reset_password">
    <div class="field">
        <label for="new_password">كلمة المرور الجديدة</label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password"
               minlength="8" placeholder="أدخل كلمة المرور الجديدة" dir="rtl" required>
    </div>
    <div class="field">
        <label for="confirm_new_password">تأكيد كلمة المرور</label>
        <input id="confirm_new_password" name="confirm_password" type="password" autocomplete="new-password"
               minlength="8" placeholder="أعد كتابة كلمة المرور" dir="rtl" required>
    </div>
    <p class="hint">يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.</p>
    <button class="btn" type="submit">إعادة تعيين كلمة المرور</button>
</form>
<a class="secondary" href="login.php">العودة إلى تسجيل الدخول</a>

<?php elseif ($view === 'success'): ?>
<div class="success-mark" aria-hidden="true">✓</div>
<h2>اكتملت خطوات استعادة كلمة المرور!</h2>
<a class="btn" href="login.php">العودة إلى تسجيل الدخول</a>
<?php endif; ?>

</section>
</main>
<footer class="footer">© 2026 جميع الحقوق محفوظة.</footer>

<script>
(function () {
    const digits = [...document.querySelectorAll('.code-digit')];
    const verifyForm = document.getElementById('verifyForm');
    if (verifyForm && digits.length) {
        digits.forEach((input, index) => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(-1);
                if (this.value && index < digits.length - 1) digits[index + 1].focus();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !this.value && index > 0) {
                    digits[index - 1].focus();
                }
                if (event.key === 'ArrowLeft' && index > 0) digits[index - 1].focus();
                if (event.key === 'ArrowRight' && index < digits.length - 1) digits[index + 1].focus();
            });
            input.addEventListener('paste', function (event) {
                const pasted = (event.clipboardData || window.clipboardData)
                    .getData('text').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;
                event.preventDefault();
                digits.forEach(field => { field.value = ''; });
                pasted.split('').forEach((value, i) => {
                    if (digits[i]) digits[i].value = value;
                });
                digits[Math.min(pasted.length, digits.length) - 1].focus();
            });
        });
        verifyForm.addEventListener('submit', function (event) {
            const code = digits.map(input => input.value).join('');
            if (!/^\d{6}$/.test(code)) {
                event.preventDefault();
                digits.find(input => !input.value)?.focus();
                return;
            }
            document.getElementById('combinedCode').value = code;
        });
    }

    function enablePasswordMatch(formId, passwordId, confirmId) {
        const form = document.getElementById(formId);
        if (!form) return;
        const password = document.getElementById(passwordId);
        const confirm = document.getElementById(confirmId);
        form.addEventListener('submit', function (event) {
            if (password.value !== confirm.value) {
                event.preventDefault();
                confirm.setCustomValidity('كلمتا المرور غير متطابقتين.');
                confirm.reportValidity();
            } else {
                confirm.setCustomValidity('');
            }
        });
        confirm.addEventListener('input', function () { confirm.setCustomValidity(''); });
    }
    enablePasswordMatch('registerForm', 'register_password', 'confirm_password');
    enablePasswordMatch('resetForm', 'new_password', 'confirm_new_password');
})();
</script>
</body>
</html>
