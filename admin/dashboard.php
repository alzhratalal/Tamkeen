<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

header('Cache-Control: no-store');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';

$totalProducts = 0;
$totalUsers = 0;
$totalOrders = 0;

$latestProducts = [];
$latestOrders = [];
$databaseError = '';

try {
    $totalProducts = (int) $pdo
        ->query('SELECT COUNT(*) FROM products')
        ->fetchColumn();

    $totalUsers = (int) $pdo
        ->query('SELECT COUNT(*) FROM users')
        ->fetchColumn();

    $totalOrders = (int) $pdo
        ->query('SELECT COUNT(*) FROM orders')
        ->fetchColumn();

    $latestProducts = $pdo->query(
        'SELECT product_id, product_name, price, quantity
         FROM products
         ORDER BY product_id DESC
         LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);

    $latestOrders = $pdo->query(
        'SELECT order_id, total_amount, status, created_at
         FROM orders
         ORDER BY order_id DESC
         LIMIT 5'
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard database error: ' . $e->getMessage());

    $databaseError = 'تعذّر تحميل بعض بيانات المتجر. حاول تحديث الصفحة.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>لوحة التحكم | تمكين</title>

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

button, input {
    font: inherit;
}

button, a, input {
    -webkit-tap-highlight-color: transparent;
}

button {
    cursor: pointer;
}

[hidden] {
    display: none !important;
}

a:focus-visible, button:focus-visible, input:focus-visible {
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

/* المحتوى */

.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 22px 32px;
    background: rgba(255, 255, 255, .95);
    border-bottom: 1px solid var(--border);
}

.topbar-start, .topbar-actions {
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

.menu-toggle, .refresh-button {
    padding: 10px 15px;
    min-height: 44px;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    background: #FFFFFF;
    color: var(--navy);
    font-size: 13px;
    transition: background .2s, transform .2s;
}

.menu-toggle:hover, .refresh-button:hover {
    background: #F2F6FA;
    transform: translateY(-2px);
}

.refresh-button:disabled {
    cursor: wait;
    opacity: .7;
}

.content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 30px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 22px;
}

.section-heading h2 {
    margin: 0 0 5px;
    color: var(--heading);
    font-size: 21px;
}

.section-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}

/* الإحصائيات */

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.stat-card {
    display: block;
    padding: 25px;
    background: rgba(255, 255, 255, .98);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    transition: transform .2s, box-shadow .2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 35px rgba(16, 44, 76, .065);
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 15px;
}

.stat-title {
    color: var(--muted);
    font-size: 14px;
}

.stat-icon {
    display: grid;
    place-items: center;
    width: 43px;
    height: 43px;
    background: #F1F6FA;
    border-radius: 12px;
    font-size: 20px;
}

.stat-number {
    color: var(--navy);
    font-size: 34px;
    font-weight: 700;
    line-height: 1.4;
    font-variant-numeric: tabular-nums;
}

.stat-note {
    margin-top: 9px;
    color: #8A9AA9;
    font-size: 12px;
}

/* الجداول */

.table-card {
    margin-bottom: 25px;
    padding: 25px;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}

.table-header h2 {
    margin: 0 0 4px;
    color: var(--heading);
    font-size: 18px;
}

.table-description {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}

.view-link {
    display: inline-block;
    color: var(--blue);
    font-size: 13px;
    font-weight: 600;
    transition: transform .2s;
}

.view-link:hover {
    transform: translateX(-3px);
}

.search-field {
    display: block;
    margin-bottom: 18px;
}

.search-field span {
    display: block;
    margin-bottom: 7px;
    color: var(--muted);
    font-size: 12px;
}

.search-field input {
    width: 100%;
    min-height: 48px;
    padding: 11px 16px;
    background: #FCFDFE;
    border: 1px solid #E8EDF3;
    border-radius: 12px;
    color: var(--heading);
    text-align: right;
    direction: rtl;
    font-size: 13px;
}

.search-field input::placeholder {
    color: #9AA9B9;
}

.search-field input:focus {
    outline: none;
    border-color: #B8CADB;
    box-shadow: 0 0 0 3px rgba(36, 90, 131, .045);
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th, td {
    padding: 15px 14px;
    text-align: right;
    border-bottom: 1px solid #EDF1F5;
    font-size: 13px;
}

th {
    color: var(--muted);
    background: #F6F9FC;
    font-weight: 500;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr[data-row] {
    transition: background .2s;
}

tbody tr[data-row]:hover {
    background: #FAFCFE;
}

.price {
    color: var(--navy);
    font-weight: 600;
}

.status {
    display: inline-block;
    max-width: 260px;
    padding: 5px 11px;
    background: #EDF4FB;
    color: var(--blue);
    border-radius: 8px;
    font-size: 12px;
    white-space: normal;
}

.empty {
    padding: 28px;
    text-align: center;
    color: #8995A5;
}

.alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 24px;
    padding: 14px 17px;
    border: 1px solid #F2D7D7;
    border-radius: 12px;
    background: #FFF5F5;
    color: #9B3030;
    font-size: 13px;
}

.alert button {
    padding: 5px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: inherit;
    font-size: 20px;
}

.footer {
    padding: 5px 16px 22px;
    text-align: center;
    color: #8A9AA9;
    font-size: 11px;
}

/* طي القائمة عند تشغيل JavaScript */

.sidebar-collapsed .sidebar {
    display: none;
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

    .stat-card {
        padding: 20px;
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

    .topbar {
        flex-wrap: wrap;
    }

    .topbar-start {
        align-items: flex-start;
    }

    .topbar-actions {
        margin-right: auto;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .content {
        padding: 18px;
    }

    .table-card {
        padding: 19px;
        border-radius: 18px;
    }

    h1 {
        font-size: 22px;
    }
}

@media (max-width: 420px) {
    .section-heading {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .menu a {
        gap: 7px;
        padding: 12px 9px;
        font-size: 13px;
    }

    .content {
        padding: 14px;
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
<div class="layout" id="dashboardLayout">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="logo" lang="en" dir="ltr">
                Tamkeen<span>.</span>
            </div>
            <small>لوحة إدارة المتجر</small>
        </div>

        <div class="menu-title">القائمة الرئيسية</div>

        <nav class="menu" aria-label="صفحات الإدارة">
            <a href="dashboard.php" class="active" aria-current="page">
                <span class="menu-icon" aria-hidden="true">🏠</span>
                الرئيسية
            </a>

            <a href="products.php">
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
                    <h1>لوحة التحكم</h1>
                    <p>مرحبًا بك في لوحة إدارة متجر تمكين.</p>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="admin-badge">
                    <?= h($adminName) ?>
                </div>
            </div>
        </header>

        <div class="content">

            <?php if ($databaseError !== ''): ?>
                <div class="alert" role="alert" id="databaseAlert">
                    <span><?= h($databaseError) ?></span>
                    <button
                        type="button"
                        id="dismissAlert"
                        aria-label="إغلاق التنبيه"
                        hidden
                    >×</button>
                </div>
            <?php endif; ?>

            <section aria-labelledby="overviewTitle">
                <div class="section-heading">
                    <div>
                        <h2 id="overviewTitle">نظرة عامة</h2>
                        <p>ملخص سريع عن بيانات المتجر.</p>
                    </div>

                    <button
                        type="button"
                        class="refresh-button"
                        id="refreshButton"
                        hidden
                    >
                        ↻ تحديث البيانات
                    </button>
                </div>

                <div class="stats">
                    <a class="stat-card" href="products.php">
                        <div class="stat-top">
                            <span class="stat-title">إجمالي المنتجات</span>
                            <span class="stat-icon" aria-hidden="true">📦</span>
                        </div>
                        <div class="stat-number">
                            <?= $databaseError === '' ? number_format($totalProducts) : '—' ?>
                        </div>
                        <div class="stat-note">الانتقال إلى إدارة المنتجات ←</div>
                    </a>

                    <a class="stat-card" href="users.php">
                        <div class="stat-top">
                            <span class="stat-title">إجمالي المستخدمين</span>
                            <span class="stat-icon" aria-hidden="true">👥</span>
                        </div>
                        <div class="stat-number">
                            <?= $databaseError === '' ? number_format($totalUsers) : '—' ?>
                        </div>
                        <div class="stat-note">الانتقال إلى إدارة المستخدمين ←</div>
                    </a>

                    <a class="stat-card" href="orders.php">
                        <div class="stat-top">
                            <span class="stat-title">إجمالي الطلبات</span>
                            <span class="stat-icon" aria-hidden="true">🛒</span>
                        </div>
                        <div class="stat-number">
                            <?= $databaseError === '' ? number_format($totalOrders) : '—' ?>
                        </div>
                        <div class="stat-note">الانتقال إلى إدارة الطلبات ←</div>
                    </a>
                </div>
            </section>

            <section class="table-card" aria-labelledby="productsTitle">
                <div class="table-header">
                    <div>
                        <h2 id="productsTitle">أحدث المنتجات</h2>
                        <p class="table-description">
                            آخر خمسة منتجات مضافة إلى المتجر.
                        </p>
                    </div>

                    <a href="products.php" class="view-link">
                        إدارة المنتجات ←
                    </a>
                </div>

                <label class="search-field" hidden>
                    <span>البحث ضمن المنتجات المعروضة</span>
                    <input
                        type="search"
                        id="productSearch"
                        placeholder="أدخل اسم المنتج أو رقمه"
                        aria-controls="productsTable"
                    >
                </label>

                <div class="table-scroll">
                    <table id="productsTable">
                        <thead>
                            <tr>
                                <th scope="col">رقم المنتج</th>
                                <th scope="col">اسم المنتج</th>
                                <th scope="col">السعر</th>
                                <th scope="col">الكمية المتوفرة</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($latestProducts as $product): ?>
                            <tr data-row>
                                <td><?= (int) $product['product_id'] ?></td>
                                <td><?= h($product['product_name']) ?></td>
                                <td class="price">
                                    <?= number_format((float) $product['price'], 2) ?>
                                </td>
                                <td><?= (int) $product['quantity'] ?></td>
                            </tr>
                        <?php endforeach; ?>

                            <tr
                                data-empty
                                <?= $latestProducts ? 'hidden' : '' ?>
                            >
                                <td colspan="4" class="empty" role="status">
                                    <?= $databaseError !== ''
                                        ? 'تعذّر تحميل بيانات المنتجات.'
                                        : 'لا توجد منتجات حتى الآن.' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="table-card" aria-labelledby="ordersTitle">
                <div class="table-header">
                    <div>
                        <h2 id="ordersTitle">أحدث الطلبات</h2>
                        <p class="table-description">
                            آخر خمسة طلبات مسجلة في المتجر.
                        </p>
                    </div>

                    <a href="orders.php" class="view-link">
                        إدارة الطلبات ←
                    </a>
                </div>

                <label class="search-field" hidden>
                    <span>البحث ضمن الطلبات المعروضة</span>
                    <input
                        type="search"
                        id="orderSearch"
                        placeholder="أدخل رقم الطلب أو حالته"
                        aria-controls="ordersTable"
                    >
                </label>

                <div class="table-scroll">
                    <table id="ordersTable">
                        <thead>
                            <tr>
                                <th scope="col">رقم الطلب</th>
                                <th scope="col">تاريخ الطلب</th>
                                <th scope="col">المبلغ الإجمالي</th>
                                <th scope="col">حالة الطلب</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($latestOrders as $order): ?>
                            <tr data-row>
                                <td>#<?= (int) $order['order_id'] ?></td>
                                <td><?= h($order['created_at']) ?></td>
                                <td class="price">
                                    <?= number_format((float) $order['total_amount'], 2) ?>
                                </td>
                                <td>
                                    <span class="status">
                                        <?= h($order['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                            <tr
                                data-empty
                                <?= $latestOrders ? 'hidden' : '' ?>
                            >
                                <td colspan="4" class="empty" role="status">
                                    <?= $databaseError !== ''
                                        ? 'تعذّر تحميل بيانات الطلبات.'
                                        : 'لا توجد طلبات حتى الآن.' ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

        <footer class="footer">
            © <?= date('Y') ?> جميع الحقوق محفوظة لتمكين.
        </footer>
    </main>
</div>

<script>
(() => {
    const layout = document.getElementById('dashboardLayout');
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const mobile = window.matchMedia('(max-width: 760px)');

    function setMenu(open) {
        layout.classList.toggle('sidebar-collapsed', !open);
        sidebar.hidden = !open;

        menuToggle.setAttribute('aria-expanded', String(open));
        menuToggle.textContent = open ? 'إخفاء القائمة' : '☰ القائمة';
    }

    menuToggle.hidden = false;
    setMenu(!mobile.matches);

    menuToggle.addEventListener('click', () => {
        const open = menuToggle.getAttribute('aria-expanded') === 'true';
        setMenu(!open);
    });

    mobile.addEventListener('change', () => {
        setMenu(!mobile.matches);
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape' &&
            mobile.matches &&
            !sidebar.hidden
        ) {
            setMenu(false);
            menuToggle.focus();
        }
    });

    // البحث في الصفوف الخمسة المعروضة فقط.
    function enableSearch(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        const rows = [...table.querySelectorAll('[data-row]')];
        const emptyRow = table.querySelector('[data-empty]');
        const emptyCell = emptyRow.querySelector('td');
        const originalMessage = emptyCell.textContent.trim();

        if (!rows.length) return;

        input.closest('.search-field').hidden = false;

        function normalize(value) {
            return value
                .toLocaleLowerCase('ar')
                .replace(/[أإآ]/g, 'ا')
                .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
                .replace(/[٠-٩]/g, digit => '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))
                .replace(/\s+/g, ' ')
                .trim();
        }

        input.addEventListener('input', () => {
            const query = normalize(input.value);
            let matches = 0;

            rows.forEach(row => {
                const text = [...row.cells]
                    .map(cell => cell.textContent)
                    .join(' ');

                const visible = normalize(text).includes(query);

                row.hidden = !visible;
                if (visible) matches++;
            });

            emptyRow.hidden = matches > 0;
            emptyCell.textContent = query
                ? 'لا توجد نتائج مطابقة ضمن الصفوف المعروضة.'
                : originalMessage;
        });
    }

    enableSearch('productSearch', 'productsTable');
    enableSearch('orderSearch', 'ordersTable');

    // إعادة تحميل الصفحة لجلب أحدث بيانات قاعدة البيانات.
    const refreshButton = document.getElementById('refreshButton');
    refreshButton.hidden = false;

    refreshButton.addEventListener('click', () => {
        refreshButton.disabled = true;
        refreshButton.textContent = 'جارٍ تحديث البيانات…';
        window.location.reload();
    });

    const dismissAlert = document.getElementById('dismissAlert');

    if (dismissAlert) {
        dismissAlert.hidden = false;

        dismissAlert.addEventListener('click', () => {
            document.getElementById('databaseAlert').hidden = true;
            refreshButton.focus();
        });
    }
})();
</script>
</body>
</html>