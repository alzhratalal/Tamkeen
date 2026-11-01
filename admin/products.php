<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

header('Cache-Control: no-store');

function escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';

$search = is_string($_GET['search'] ?? null)
    ? trim($_GET['search'])
    : '';

$filter = is_string($_GET['availability'] ?? null)
    ? $_GET['availability']
    : '';

if (!in_array($filter, ['', 'available', 'unavailable'], true)) {
    $filter = '';
}

$products = [];
$error = '';

try {
    $sql = '
        SELECT product_id, product_name, brand, price,
               quantity, status, created_at, image
        FROM products
        WHERE 1 = 1
    ';

    $params = [];

    if ($search !== '') {
        $sql .= ' AND (product_name LIKE ? OR brand LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($filter === 'available') {
        $sql .= ' AND quantity > 0';
    } elseif ($filter === 'unavailable') {
        $sql .= ' AND quantity <= 0';
    }

    $sql .= ' ORDER BY product_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Products page: ' . $e->getMessage());
    $error = 'تعذّر تحميل المنتجات. حاول مرة أخرى لاحقًا.';
}

$success = $_SESSION['product_success'] ?? '';
unset($_SESSION['product_success']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إدارة المنتجات | تمكين</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap');

:root {
    --navy: #103B63;
    --blue: #245A83;
    --heading: #102C4C;
    --text: #344054;
    --muted: #687789;
    --border: #EDF1F5;
    --radius: 20px;
    --shadow: 0 12px 35px rgba(16, 44, 76, .035);
}

*, *::before, *::after {
    box-sizing: border-box;
}

body {
    margin: 0;
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

button, input, select {
    font: inherit;
}

button, a, input, select {
    -webkit-tap-highlight-color: transparent;
}

button {
    cursor: pointer;
}

[hidden] {
    display: none !important;
}

a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible {
    outline: 3px solid rgba(36, 90, 131, .24);
    outline-offset: 3px;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* القائمة الجانبية */

.sidebar {
    width: 245px;
    flex-shrink: 0;
    padding: 26px 17px;
    background: #FFFFFF;
    border-left: 1px solid var(--border);
}

.brand {
    padding: 10px 0 26px;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.logo {
    color: var(--navy);
    font-family: Arial, sans-serif;
    font-size: 30px;
    font-weight: 700;
    letter-spacing: -1px;
}

.logo span {
    color: #FF8A24;
}

.brand small {
    display: block;
    margin-top: 8px;
    color: var(--muted);
    font-size: 12px;
}

.menu-title {
    margin: 26px 14px 12px;
    color: #8A9AA9;
    font-size: 12px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 15px;
    margin-bottom: 8px;
    border-radius: 12px;
    color: var(--muted);
    font-size: 14px;
    font-weight: 500;
    transition: background .2s, color .2s, transform .2s;
}

.menu a:hover {
    background: #F2F6FA;
    color: var(--navy);
    transform: translateX(-3px);
}

.menu a.active {
    background: linear-gradient(110deg, #103B63, #1C507A);
    color: #FFFFFF;
    box-shadow: 0 4px 10px rgba(16, 59, 99, .06);
}

.menu-icon {
    width: 24px;
    text-align: center;
}

.menu a.logout {
    margin-top: 28px;
    color: #A34D4D;
}

.menu a.logout:hover {
    background: #FFF5F5;
}

/* رأس الصفحة */

.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
    padding: 22px 32px;
    background: rgba(255, 255, 255, .95);
    border-bottom: 1px solid var(--border);
}

.topbar-start {
    display: flex;
    align-items: center;
    gap: 14px;
}

h1 {
    margin: 0 0 4px;
    color: var(--heading);
    font-size: 25px;
    line-height: 1.55;
}

.topbar p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.admin-badge {
    padding: 10px 15px;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    background: #F7FAFD;
    color: var(--navy);
    font-size: 13px;
    overflow-wrap: anywhere;
}

.menu-toggle {
    min-height: 44px;
    padding: 10px 15px;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    background: #FFFFFF;
    color: var(--navy);
    font-size: 13px;
}

.menu-toggle:hover {
    background: #F2F6FA;
}

.content {
    max-width: 1600px;
    margin: 0 auto;
    padding: 30px;
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}

.toolbar p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 50px;
    padding: 11px 19px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: linear-gradient(110deg, #103B63, #1C507A);
    color: #FFFFFF;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    box-shadow: 0 4px 10px rgba(16, 59, 99, .06);
    transition: transform .18s, background .18s;
}

.button:hover {
    background: linear-gradient(110deg, #174C77, #28618A);
    transform: translateY(-2px);
}

.card {
    padding: 25px;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

/* البحث والتصفية */

.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.search-field {
    flex: 1 1 260px;
}

.field label {
    color: var(--heading);
    font-size: 13px;
    font-weight: 600;
}

.field input,
.field select {
    width: 100%;
    min-height: 50px;
    padding: 11px 16px;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    background: #FCFDFE;
    color: var(--heading);
    font-size: 13px;
    direction: rtl;
    text-align: right;
}

.field select {
    min-width: 190px;
}

.field input::placeholder {
    color: #9AA9B9;
}

.field input:focus,
.field select:focus {
    outline: none;
    border-color: #B8CADB;
    box-shadow: 0 0 0 3px rgba(36, 90, 131, .045);
}

.reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
    padding: 10px 13px;
    color: var(--blue);
    font-size: 13px;
    font-weight: 500;
}

.reset:hover {
    text-decoration: underline;
}

.results-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin: 20px 0;
}

.count {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

.count strong {
    margin-right: 5px;
    color: var(--navy);
}

.sort-hint {
    margin: 0;
    color: #8A9AA9;
    font-size: 12px;
}

/* الجدول */

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th, td {
    padding: 15px 13px;
    text-align: right;
    border-bottom: 1px solid #EDF1F5;
    font-size: 13px;
}

th {
    background: #F6F9FC;
    color: var(--muted);
    font-weight: 500;
}

.sort-button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    font-size: inherit;
    font-weight: inherit;
    text-align: inherit;
}

.sort-button:disabled {
    color: inherit;
    cursor: default;
}

.sort-indicator {
    color: #8A9AA9;
    font-size: 12px;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr {
    transition: background .2s;
}

tbody tr:hover {
    background: #FAFCFE;
}

.product-name {
    max-width: 250px;
    min-width: 160px;
    white-space: normal;
    color: var(--heading);
    font-weight: 500;
}

.price {
    color: var(--navy);
    font-weight: 600;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 12px;
}

.available {
    background: #EFF9F3;
    color: #28774C;
}

.unavailable {
    background: #FFF3F3;
    color: #A34D4D;
}

.status-badge {
    background: #EDF4FB;
    color: var(--blue);
}

.edit-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 16px;
    border: 1px solid #E3ECF4;
    border-radius: 10px;
    background: #F3F7FB;
    color: var(--navy);
    font-size: 12px;
    transition: transform .18s, background .18s;
}

.edit-link:hover {
    background: #EAF2F9;
    transform: translateY(-2px);
}

.image-link {
    display: inline-flex;
    border-radius: 12px;
}

.product-image,
.image-placeholder {
    width: 60px;
    height: 60px;
    border: 1px solid #EDF1F5;
    border-radius: 12px;
    background: #F8FAFC;
}

.product-image {
    display: block;
    object-fit: cover;
}

.image-placeholder {
    display: grid;
    place-items: center;
    color: #9AA9B9;
    font-size: 12px;
}

.empty {
    padding: 35px;
    text-align: center;
    color: var(--muted);
}

/* التنبيهات */

.message {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 22px;
    padding: 14px 17px;
    border: 1px solid transparent;
    border-radius: 12px;
    font-size: 13px;
}

.error {
    background: #FFF5F5;
    border-color: #F2D7D7;
    color: #9B3030;
}

.success {
    background: #F1FAF5;
    border-color: #D0E9DB;
    color: #236B3A;
}

.dismiss-message {
    padding: 3px 9px;
    border: 0;
    border-radius: 7px;
    background: transparent;
    color: inherit;
    font-size: 21px;
}

/* معاينة الصورة */

.image-dialog {
    width: min(650px, calc(100% - 32px));
    max-height: 90vh;
    padding: 22px;
    border: 1px solid #EDF1F5;
    border-radius: 20px;
    background: #FFFFFF;
    color: var(--heading);
    box-shadow: 0 20px 70px rgba(16, 44, 76, .15);
}

.image-dialog::backdrop {
    background: rgba(16, 44, 76, .48);
}

.dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.dialog-header h2 {
    margin: 0;
    font-size: 18px;
}

.dialog-close {
    width: 40px;
    height: 40px;
    border: 1px solid #E8EDF3;
    border-radius: 10px;
    background: #F8FAFC;
    color: var(--navy);
    font-size: 23px;
}

.preview-image {
    display: block;
    width: 100%;
    max-height: 65vh;
    object-fit: contain;
    border-radius: 12px;
}

.preview-error {
    padding: 30px 15px;
    text-align: center;
    color: var(--muted);
}

.footer {
    padding: 22px 16px;
    text-align: center;
    color: #8A9AA9;
    font-size: 11px;
}

@media (max-width: 1000px) {
    .sidebar {
        width: 205px;
    }

    .content {
        padding: 22px;
    }

    .topbar {
        padding: 20px 22px;
    }
}

@media (max-width: 760px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 18px;
        border-left: 0;
        border-bottom: 1px solid var(--border);
    }

    .brand {
        padding: 0 0 15px;
    }

    .menu-title {
        margin-top: 16px;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
    }

    .menu a, .menu a.logout {
        margin: 0;
    }

    .topbar-start {
        align-items: flex-start;
    }

    .content {
        padding: 18px;
    }

    .card {
        padding: 19px;
        border-radius: 18px;
    }

    h1 {
        font-size: 22px;
    }

    .filters {
        align-items: stretch;
    }

    .field {
        flex: 1 1 100%;
    }

    .filters .button,
    .filters .reset {
        flex: 1;
    }
}

@media (max-width: 420px) {
    .content {
        padding: 14px;
    }

    .menu a {
        gap: 7px;
        padding: 12px 9px;
        font-size: 13px;
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
<div class="layout">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="logo" lang="en" dir="ltr">
                Tamkeen<span>.</span>
            </div>
            <small>لوحة إدارة المتجر</small>
        </div>

        <div class="menu-title">القائمة الرئيسية</div>

        <nav class="menu" aria-label="صفحات الإدارة">
            <a href="dashboard.php">
                <span class="menu-icon" aria-hidden="true">🏠</span>
                الرئيسية
            </a>

            <a href="products.php" class="active" aria-current="page">
                <span class="menu-icon" aria-hidden="true">📦</span>
                المنتجات
            </a>

            <a href="users.php">
                <span class="menu-icon" aria-hidden="true">👥</span>
                المستخدمون
            </a>

            <a href="orders.php">
                <span class="menu-icon" aria-hidden="true">🛒</span>
                الطلبات
            </a>

            <a href="../index.php">
                <span class="menu-icon" aria-hidden="true">🌐</span>
                زيارة المتجر
            </a>

            <a href="logout.php" class="logout">
                <span class="menu-icon" aria-hidden="true">↪</span>
                تسجيل الخروج
            </a>
        </nav>
    </aside>

    <main class="main">

        <header class="topbar">
            <div class="topbar-start">
                <button
                    type="button"
                    class="menu-toggle"
                    id="menuToggle"
                    aria-controls="sidebar"
                    aria-expanded="true"
                    hidden
                >
                    إخفاء القائمة
                </button>

                <div>
                    <h1>إدارة المنتجات</h1>
                    <p>عرض أجهزة المتجر والبحث عنها وتعديل بياناتها.</p>
                </div>
            </div>

            <div class="admin-badge">
                <?= escape($adminName) ?>
            </div>
        </header>

        <div class="content">

            <?php if ($error !== ''): ?>
                <div class="message error" role="alert">
                    <span><?= escape($error) ?></span>
                    <button
                        type="button"
                        class="dismiss-message"
                        aria-label="إغلاق التنبيه"
                        hidden
                    >×</button>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="message success" role="status">
                    <span><?= escape($success) ?></span>
                    <button
                        type="button"
                        class="dismiss-message"
                        aria-label="إغلاق رسالة النجاح"
                        hidden
                    >×</button>
                </div>
            <?php endif; ?>

            <div class="toolbar">
                <p>أضف الأجهزة وحدّث بياناتها من مكان واحد.</p>

                <a href="add-product.php" class="button">
                    ＋ إضافة جهاز جديد
                </a>
            </div>

            <section class="card" aria-label="قائمة المنتجات">

                <form method="get" action="products.php" class="filters">
                    <div class="field search-field">
                        <label for="search">البحث عن جهاز</label>
                        <input
                            type="search"
                            id="search"
                            name="search"
                            placeholder="أدخل اسم الجهاز أو الماركة"
                            value="<?= escape($search) ?>"
                        >
                    </div>

                    <div class="field">
                        <label for="availability">التوفر حسب الكمية</label>
                        <select id="availability" name="availability">
                            <option value="">جميع المنتجات</option>

                            <option
                                value="available"
                                <?= $filter === 'available' ? 'selected' : '' ?>
                            >
                                متوفر بالمخزون
                            </option>

                            <option
                                value="unavailable"
                                <?= $filter === 'unavailable' ? 'selected' : '' ?>
                            >
                                نفدت الكمية
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="button">
                        بحث وتصفية
                    </button>

                    <a href="products.php" class="reset">
                        إعادة تعيين
                    </a>
                </form>

                <?php if ($error === ''): ?>
                    <div class="results-bar">
                        <p class="count">
                            عدد النتائج:
                            <strong><?= count($products) ?></strong>
                        </p>

                        <p class="sort-hint" id="sortHint" hidden>
                            اضغط على عنوان العمود لترتيب النتائج.
                        </p>
                    </div>

                    <div class="table-scroll">
                        <table id="productsTable">
                            <thead>
                            <tr>
                                <th scope="col">الصورة</th>

                                <th scope="col" aria-sort="none">
                                    <button
                                        type="button"
                                        class="sort-button"
                                        data-column="1"
                                        data-type="number"
                                        disabled
                                    >
                                        رقم الجهاز
                                        <span class="sort-indicator" aria-hidden="true"></span>
                                    </button>
                                </th>

                                <th scope="col" aria-sort="none">
                                    <button
                                        type="button"
                                        class="sort-button"
                                        data-column="2"
                                        data-type="text"
                                        disabled
                                    >
                                        اسم الجهاز
                                        <span class="sort-indicator" aria-hidden="true"></span>
                                    </button>
                                </th>

                                <th scope="col">الماركة</th>

                                <th scope="col" aria-sort="none">
                                    <button
                                        type="button"
                                        class="sort-button"
                                        data-column="4"
                                        data-type="number"
                                        disabled
                                    >
                                        السعر
                                        <span class="sort-indicator" aria-hidden="true"></span>
                                    </button>
                                </th>

                                <th scope="col" aria-sort="none">
                                    <button
                                        type="button"
                                        class="sort-button"
                                        data-column="5"
                                        data-type="number"
                                        disabled
                                    >
                                        الكمية
                                        <span class="sort-indicator" aria-hidden="true"></span>
                                    </button>
                                </th>

                                <th scope="col">التوفر حسب الكمية</th>
                                <th scope="col">الحالة المسجلة</th>
                                <th scope="col">تاريخ الإضافة</th>
                                <th scope="col">الإجراءات</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="10" class="empty">
                                        لا توجد منتجات مطابقة.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $image = (string) ($product['image'] ?? '');

                                    $validImage = preg_match(
                                        '~^uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$~D',
                                        $image
                                    );

                                    $quantity = (int) $product['quantity'];
                                    ?>
                                    <tr data-product-row>
                                        <td>
                                            <?php if ($validImage): ?>
                                                <a
                                                    href="<?= escape('../' . $image) ?>"
                                                    class="image-link"
                                                    data-preview
                                                    data-name="<?= escape($product['product_name']) ?>"
                                                    aria-label="<?= escape('تكبير صورة ' . $product['product_name']) ?>"
                                                >
                                                    <img
                                                        src="<?= escape('../' . $image) ?>"
                                                        alt="<?= escape($product['product_name']) ?>"
                                                        class="product-image"
                                                        loading="lazy"
                                                        width="60"
                                                        height="60"
                                                    >
                                                </a>
                                            <?php else: ?>
                                                <span class="image-placeholder">
                                                    —
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td data-sort="<?= (int) $product['product_id'] ?>">
                                            <?= (int) $product['product_id'] ?>
                                        </td>

                                        <td class="product-name">
                                            <?= escape($product['product_name']) ?>
                                        </td>

                                        <td>
                                            <?= escape($product['brand'] ?: '—') ?>
                                        </td>

                                        <td
                                            class="price"
                                            data-sort="<?= escape($product['price']) ?>"
                                        >
                                            <?= number_format((float) $product['price'], 2) ?>
                                        </td>

                                        <td data-sort="<?= $quantity ?>">
                                            <?= $quantity ?>
                                        </td>

                                        <td>
                                            <?php if ($quantity > 0): ?>
                                                <span class="badge available">
                                                    متوفر
                                                </span>
                                            <?php else: ?>
                                                <span class="badge unavailable">
                                                    نفدت الكمية
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="badge status-badge">
                                                <?= escape($product['status']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= escape($product['created_at']) ?>
                                        </td>

                                        <td>
                                            <a
                                                href="edit-product.php?id=<?= (int) $product['product_id'] ?>"
                                                class="edit-link"
                                                aria-label="<?= escape('تعديل ' . $product['product_name']) ?>"
                                            >
                                                تعديل
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <p
                        class="sort-hint"
                        id="sortStatus"
                        role="status"
                        aria-live="polite"
                    ></p>
                <?php endif; ?>

            </section>

        </div>

        <footer class="footer">
            © <?= date('Y') ?> جميع الحقوق محفوظة لتمكين.
        </footer>
    </main>
</div>

<dialog class="image-dialog" id="imageDialog" aria-labelledby="previewTitle">
    <div class="dialog-header">
        <h2 id="previewTitle">صورة الجهاز</h2>

        <button
            type="button"
            class="dialog-close"
            id="closePreview"
            aria-label="إغلاق معاينة الصورة"
        >×</button>
    </div>

    <img id="previewImage" class="preview-image" alt="" hidden>

    <p class="preview-error" id="previewError" role="status" hidden>
        تعذّر تحميل الصورة.
    </p>
</dialog>

<script>
(() => {
    // فتح وإغلاق القائمة.
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');
    const mobile = window.matchMedia('(max-width: 760px)');

    function setMenu(open) {
        sidebar.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.textContent = open ? 'إخفاء القائمة' : '☰ القائمة';
    }

    toggle.hidden = false;
    setMenu(!mobile.matches);

    toggle.addEventListener('click', () => {
        setMenu(toggle.getAttribute('aria-expanded') !== 'true');
    });

    mobile.addEventListener('change', () => {
        setMenu(!mobile.matches);
    });

    // إغلاق التنبيهات.
    document.querySelectorAll('.dismiss-message').forEach(button => {
        button.hidden = false;

        button.addEventListener('click', () => {
            button.closest('.message').hidden = true;
            document.getElementById('search').focus();
        });
    });

    // ترتيب النتائج المحمّلة من قاعدة البيانات.
    const table = document.getElementById('productsTable');
    const collator = new Intl.Collator('ar', {
        numeric: true,
        sensitivity: 'base'
    });

    if (table) {
        const tbody = table.querySelector('tbody');
        const buttons = [...table.querySelectorAll('.sort-button')];
        const rows = [...tbody.querySelectorAll('[data-product-row]')];

        if (rows.length > 1) {
            document.getElementById('sortHint').hidden = false;

            buttons.forEach(button => {
                button.disabled = false;
                button.querySelector('.sort-indicator').textContent = '↕';

                button.addEventListener('click', () => {
                    const header = button.closest('th');
                    const ascending =
                        header.getAttribute('aria-sort') !== 'ascending';

                    const column = Number(button.dataset.column);
                    const numeric = button.dataset.type === 'number';

                    const sortedRows = [...rows].sort((a, b) => {
                        const cellA = a.cells[column];
                        const cellB = b.cells[column];

                        const valueA =
                            cellA.dataset.sort ?? cellA.textContent.trim();

                        const valueB =
                            cellB.dataset.sort ?? cellB.textContent.trim();

                        const result = numeric
                            ? Number(valueA) - Number(valueB)
                            : collator.compare(valueA, valueB);

                        return ascending ? result : -result;
                    });

                    buttons.forEach(other => {
                        other.closest('th').setAttribute('aria-sort', 'none');
                        other.querySelector('.sort-indicator').textContent = '↕';
                    });

                    header.setAttribute(
                        'aria-sort',
                        ascending ? 'ascending' : 'descending'
                    );

                    button.querySelector('.sort-indicator').textContent =
                        ascending ? '↑' : '↓';

                    const fragment = document.createDocumentFragment();

                    sortedRows.forEach(row => fragment.appendChild(row));
                    tbody.appendChild(fragment);

                    const title = button.textContent.replace(/[↕↑↓]/g, '').trim();

                    document.getElementById('sortStatus').textContent =
                        'تم الترتيب حسب ' + title +
                        (ascending ? ' تصاعديًا.' : ' تنازليًا.');
                });
            });
        }
    }

    // تكبير صورة المنتج.
    const dialog = document.getElementById('imageDialog');
    const previewImage = document.getElementById('previewImage');
    const previewTitle = document.getElementById('previewTitle');
    const previewError = document.getElementById('previewError');
    const closePreview = document.getElementById('closePreview');

    let imageTrigger = null;

    if (typeof dialog.showModal === 'function') {
        document.querySelectorAll('[data-preview]').forEach(link => {
            link.addEventListener('click', event => {
                // السماح بفتح الصورة في تبويب جديد عند استخدام Ctrl أو Command.
                if (
                    event.ctrlKey ||
                    event.metaKey ||
                    event.shiftKey ||
                    event.altKey
                ) return;

                event.preventDefault();
                imageTrigger = link;

                previewTitle.textContent = link.dataset.name;
                previewImage.alt = link.dataset.name;
                previewError.hidden = true;
                previewImage.hidden = true;

                previewImage.onload = () => {
                    previewImage.hidden = false;
                };

                previewImage.onerror = () => {
                    previewImage.hidden = true;
                    previewError.hidden = false;
                };

                previewImage.src = link.href;
                dialog.showModal();
            });
        });
    }

    closePreview.addEventListener('click', () => dialog.close());

    dialog.addEventListener('close', () => {
        if (imageTrigger) imageTrigger.focus();
    });

    document.addEventListener('keydown', event => {
        if (
            event.key === 'Escape' &&
            !dialog.open &&
            mobile.matches &&
            !sidebar.hidden
        ) {
            setMenu(false);
            toggle.focus();
        }
    });
})();
</script>
</body>
</html>