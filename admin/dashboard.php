<?php
session_start();

require_once __DIR__ . '/../db.php';

// التأكد من تسجيل دخول الأدمن
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = $_SESSION['admin_username'] ?? 'Admin';

// إحصائيات لوحة التحكم
$totalProducts = 0;
$totalUsers = 0;
$totalOrders = 0;

$latestProducts = [];
$latestOrders = [];

try {

    // إجمالي المنتجات
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = (int) $stmt->fetchColumn();

    // إجمالي المستخدمين
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = (int) $stmt->fetchColumn();

    // إجمالي الطلبات
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = (int) $stmt->fetchColumn();

    // أحدث المنتجات
    $stmt = $pdo->query("
        SELECT product_id, product_name, price, quantity
        FROM products
        ORDER BY product_id DESC
        LIMIT 5
    ");

    $latestProducts = $stmt->fetchAll();

    // أحدث الطلبات
    $stmt = $pdo->query("
        SELECT order_id, total_amount, status, created_at
        FROM orders
        ORDER BY order_id DESC
        LIMIT 5
    ");

    $latestOrders = $stmt->fetchAll();

} catch (PDOException $e) {
    exit(
        'حدث خطأ في قاعدة البيانات. تأكدي من أسماء الجداول والأعمدة.'
    );
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>لوحة التحكم | تمكين</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f4f7fb;
            color: #24364b;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* القائمة الجانبية */

        .sidebar {
            width: 245px;
            background: #103b63;
            color: white;
            padding: 25px 15px;
            flex-shrink: 0;
        }

        .logo {
            font-size: 27px;
            font-weight: bold;
            text-align: center;
            padding: 10px 0 30px;
            border-bottom: 1px solid #ffffff30;
        }

        .logo span {
            color: #ff8a24;
        }

        .menu-title {
            color: #b9ccdf;
            font-size: 12px;
            margin: 28px 12px 12px;
        }

        .menu a {
            display: block;
            padding: 13px 14px;
            margin-bottom: 7px;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #245a83;
        }

        .logout {
            margin-top: 30px;
            color: #ffd0c8;
        }

        /* المحتوى الرئيسي */

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            background: white;
            padding: 22px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #e8edf3;
        }

        .topbar h1 {
            font-size: 23px;
            margin: 0 0 8px;
            color: #103b63;
        }

        .topbar p {
            color: #7b8796;
            font-size: 13px;
            margin: 0;
        }

        .admin-badge {
            background: #edf4fb;
            color: #103b63;
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 13px;
        }

        .content {
            padding: 28px;
        }

        .section-heading {
            margin-bottom: 20px;
        }

        .section-heading h2 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #103b63;
        }

        .section-heading p {
            margin: 0;
            font-size: 13px;
            color: #7b8796;
        }

        /* بطاقات الإحصائيات */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 23px;
            border: 1px solid #e8edf3;
            box-shadow: 0 3px 12px #103b6308;
        }

        .stat-title {
            color: #7b8796;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .stat-number {
            color: #103b63;
            font-size: 30px;
            font-weight: bold;
        }

        .stat-icon {
            float: left;
            font-size: 24px;
        }

        /* الجداول */

        .table-card {
            background: white;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .table-header h3 {
            margin: 0;
            color: #103b63;
            font-size: 17px;
        }

        .view-link {
            color: #245a83;
            font-size: 13px;
            font-weight: bold;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th {
            background: #f5f8fc;
            color: #66778b;
            font-size: 13px;
            font-weight: normal;
        }

        th,
        td {
            padding: 15px 12px;
            text-align: right;
            border-bottom: 1px solid #edf1f6;
        }

        td {
            font-size: 13px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .empty {
            text-align: center;
            color: #8995a5;
            padding: 25px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            background: #edf4fb;
            color: #245a83;
            border-radius: 6px;
            font-size: 12px;
        }

        .price {
            color: #e87819;
            font-weight: bold;
        }

        /* الجوال */

        @media (max-width: 900px) {
            .sidebar {
                width: 190px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 20px;
            }
        }

        @media (max-width: 600px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 15px;
            }

            .logo {
                padding: 5px 0 15px;
            }

            .menu-title {
                margin-top: 15px;
            }

            .menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .menu a {
                margin: 0;
                padding: 12px 8px;
                text-align: center;
                font-size: 13px;
            }

            .topbar {
                padding: 20px;
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar h1 {
                font-size: 21px;
            }

            .content {
                padding: 16px;
            }

            .stat-card {
                padding: 20px;
            }

            .table-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="layout">

    <!-- القائمة الجانبية -->

    <aside class="sidebar">

        <div class="logo">
            Tamkeen<span>.</span>
        </div>

        <div class="menu-title">القائمة الرئيسية</div>

        <nav class="menu">

            <a href="dashboard.php" class="active">
                🏠 الرئيسية
            </a>

            <a href="products.php">
                📦 المنتجات
            </a>

            <a href="users.php">
                👥 المستخدمون
            </a>

            <a href="orders.php">
                🛒 الطلبات
            </a>

            <a href="../index.php">
                🌐 زيارة المتجر
            </a>

            <a href="logout.php" class="logout">
                ↪ تسجيل الخروج
            </a>

        </nav>

    </aside>


    <!-- المحتوى الرئيسي -->

    <main class="main">

        <header class="topbar">

            <div>
                <h1>لوحة التحكم</h1>
                <p>مرحبًا بك في لوحة إدارة متجر تمكين.</p>
            </div>

            <div class="admin-badge">
                👤 <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
            </div>

        </header>


        <div class="content">

            <!-- الإحصائيات -->

            <section>

                <div class="section-heading">
                    <h2>نظرة عامة</h2>
                    <p>ملخص سريع عن المتجر.</p>
                </div>

                <div class="stats">

                    <div class="stat-card">
                        <div class="stat-title">
                            إجمالي المنتجات
                            <span class="stat-icon">📦</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalProducts ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">
                            إجمالي المستخدمين
                            <span class="stat-icon">👥</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalUsers ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-title">
                            إجمالي الطلبات
                            <span class="stat-icon">🛒</span>
                        </div>

                        <div class="stat-number">
                            <?= $totalOrders ?>
                        </div>
                    </div>

                </div>

            </section>


            <!-- أحدث المنتجات -->

            <section class="table-card">

                <div class="table-header">
                    <h3>أحدث المنتجات</h3>

                    <a href="products.php" class="view-link">
                        إدارة المنتجات ←
                    </a>
                </div>

                <div class="table-scroll">

                    <table>
                        <thead>
                            <tr>
                                <th>رقم المنتج</th>
                                <th>اسم المنتج</th>
                                <th>السعر</th>
                                <th>الكمية المتوفرة</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (empty($latestProducts)): ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    لا توجد منتجات حتى الآن.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($latestProducts as $product): ?>

                                <tr>
                                    <td>
                                        <?= (int) $product['product_id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $product['product_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td class="price">
                                        <?= number_format(
                                            (float) $product['price'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= (int) $product['quantity'] ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </section>


            <!-- أحدث الطلبات -->

            <section class="table-card">

                <div class="table-header">
                    <h3>أحدث الطلبات</h3>

                    <a href="orders.php" class="view-link">
                        إدارة الطلبات ←
                    </a>
                </div>

                <div class="table-scroll">

                    <table>
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>تاريخ الطلب</th>
                                <th>المبلغ الإجمالي</th>
                                <th>حالة الطلب</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (empty($latestOrders)): ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    لا توجد طلبات حتى الآن.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($latestOrders as $order): ?>

                                <tr>
                                    <td>
                                        #<?= (int) $order['order_id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) $order['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td class="price">
                                        <?= number_format(
                                            (float) $order['total_amount'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="status">
                                            <?= htmlspecialchars(
                                                (string) $order['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>
                    </table>

                </div>

            </section>

        </div>

    </main>

</div>

</body>
</html>