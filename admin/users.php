<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$statusNames = [
    'pending' => 'قيد الانتظار',
    'processing' => 'قيد التجهيز',
    'shipped' => 'تم الشحن',
    'delivered' => 'تم التسليم',
    'completed' => 'مكتمل',
    'cancelled' => 'ملغي',
    'canceled' => 'ملغي',
];

$search = is_string($_GET['search'] ?? null)
    ? trim($_GET['search']) : '';

$users = [];
$selectedUser = null;
$userOrders = [];
$error = '';
$detailError = '';

$stats = [
    'total' => 0,
    'with_orders' => 0,
    'without_orders' => 0,
];

// تحميل المستخدمين والإحصائيات
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(
                EXISTS (
                    SELECT 1
                    FROM orders o
                    WHERE o.user_id = u.user_id
                )
            ), 0) AS with_orders
        FROM users u
    ");

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['total'] = (int) $result['total'];
    $stats['with_orders'] = (int) $result['with_orders'];
    $stats['without_orders'] =
        $stats['total'] - $stats['with_orders'];

    $sql = "
        SELECT
            u.user_id,
            u.full_name,
            u.phone,
            u.created_at,
            (
                SELECT COUNT(*)
                FROM orders o
                WHERE o.user_id = u.user_id
            ) AS order_count
        FROM users u
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                u.full_name LIKE ?
                OR u.phone LIKE ?
                OR CAST(u.user_id AS CHAR) = ?
            )
        ";

        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = ltrim($search, '#');
    }

    $sql .= " ORDER BY u.user_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'تعذّر تحميل المستخدمين. حاولي مرة أخرى.';
}

// عرض طلبات مستخدم محدد
if (isset($_GET['id']) && $error === '') {
    $userId = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false || $userId === null) {
        $detailError = 'رقم المستخدم غير صحيح.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT user_id, full_name, phone, created_at
                FROM users
                WHERE user_id = ?
            ");

            $stmt->execute([$userId]);
            $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$selectedUser) {
                $detailError = 'المستخدم غير موجود.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT order_id, total_amount, status, created_at
                    FROM orders
                    WHERE user_id = ?
                    ORDER BY order_id DESC
                ");

                $stmt->execute([$userId]);
                $userOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $selectedUser = null;
            $detailError = 'تعذّر تحميل طلبات المستخدم.';
        }
    }
}

$listUrl = 'users.php?' . http_build_query([
    'search' => $search,
]);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة المستخدمين | تمكين</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #24364b;
            font-family: Tahoma, Arial, sans-serif;
        }

        a {
            color: #103b63;
            text-decoration: none;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 235px;
            flex-shrink: 0;
            padding: 30px 16px;
            background: #103b63;
            color: white;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            padding-bottom: 30px;
            margin-bottom: 25px;
            border-bottom: 1px solid #ffffff30;
        }

        .logo span {
            color: #ff8a24;
        }

        .sidebar a {
            display: block;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #245a83;
        }

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            background: white;
            padding: 25px 30px;
            border-bottom: 1px solid #e8edf3;
        }

        h1 {
            margin: 0 0 10px;
            color: #103b63;
            font-size: 25px;
        }

        .topbar p {
            margin: 0;
            color: #728095;
            font-size: 14px;
            line-height: 1.8;
        }

        .content {
            max-width: 1400px;
            margin: auto;
            padding: 28px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat,
        .card {
            background: white;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            padding: 22px;
        }

        .stat p {
            color: #728095;
            font-size: 13px;
            margin: 0 0 16px;
        }

        .stat strong {
            font-size: 30px;
            color: #103b63;
        }

        .card {
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        h2 {
            margin: 0;
            color: #103b63;
            font-size: 19px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        input {
            flex: 1;
            min-width: 190px;
            padding: 12px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
        }

        button {
            background: #103b63;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            cursor: pointer;
        }

        .count,
        .reset {
            color: #728095;
            font-size: 13px;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th,
        td {
            text-align: right;
            padding: 15px 12px;
            border-bottom: 1px solid #edf1f6;
            font-size: 13px;
        }

        th {
            background: #f5f8fc;
            color: #52677e;
        }

        .details-link {
            display: inline-block;
            background: #103b63;
            color: white;
            padding: 9px 13px;
            border-radius: 7px;
        }

        .details-link:hover {
            background: #245a83;
        }

        .badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 7px;
            background: #edf4fb;
            color: #103b63;
        }

        .price {
            color: #d96912;
            font-weight: bold;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .info {
            background: #f5f8fc;
            padding: 16px;
            border-radius: 8px;
            min-width: 0;
        }

        .info span {
            display: block;
            color: #728095;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .info strong {
            font-size: 14px;
            font-weight: normal;
            overflow-wrap: anywhere;
        }

        .empty {
            text-align: center;
            padding: 28px;
            color: #728095;
        }

        .error {
            background: #ffeded;
            color: #ad3434;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 190px;
            }

            .stats {
                grid-template-columns: 1fr;
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
                padding-bottom: 15px;
                margin-bottom: 15px;
            }

            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sidebar a {
                margin: 0;
                text-align: center;
            }

            .content,
            .topbar {
                padding: 20px 14px;
            }

            .card {
                padding: 16px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            input {
                min-width: 0;
                width: 100%;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Tamkeen<span>.</span></div>

        <nav aria-label="تنقل الإدارة">
            <a href="dashboard.php">الرئيسية</a>
            <a href="products.php">المنتجات</a>
            <a href="orders.php">الطلبات</a>
            <a href="users.php" class="active">المستخدمون</a>
            <a href="../index.php">زيارة المتجر</a>
        </nav>
    </aside>

    <main class="main">

        <header class="topbar">
            <h1>إدارة المستخدمين</h1>
            <p>عرض العملاء المسجلين ومتابعة طلباتهم.</p>
        </header>

        <div class="content">

            <?php if ($error !== ''): ?>
                <div class="error" role="alert">
                    <?= escape($error) ?>
                </div>
            <?php else: ?>

                <section class="stats" aria-label="إحصائيات جميع المستخدمين">
                    <div class="stat">
                        <p>إجمالي المستخدمين</p>
                        <strong><?= $stats['total'] ?></strong>
                    </div>

                    <div class="stat">
                        <p>لديهم طلبات</p>
                        <strong><?= $stats['with_orders'] ?></strong>
                    </div>

                    <div class="stat">
                        <p>لم يطلبوا بعد</p>
                        <strong><?= $stats['without_orders'] ?></strong>
                    </div>
                </section>

                <?php if ($detailError !== ''): ?>
                    <div class="error" role="alert">
                        <?= escape($detailError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($selectedUser): ?>
                    <section class="card" id="user-details">

                        <div class="card-header">
                            <h2>
                                طلبات العميل:
                                <?= escape($selectedUser['full_name']) ?>
                            </h2>

                            <a href="<?= escape($listUrl) ?>">
                                إغلاق التفاصيل
                            </a>
                        </div>

                        <div class="info-grid">
                            <div class="info">
                                <span>رقم المستخدم</span>
                                <strong>
                                    #<?= (int) $selectedUser['user_id'] ?>
                                </strong>
                            </div>

                            <div class="info">
                                <span>رقم الهاتف</span>
                                <strong>
                                    <bdi dir="ltr">
                                        <?= escape($selectedUser['phone'] ?: '—') ?>
                                    </bdi>
                                </strong>
                            </div>

                            <div class="info">
                                <span>تاريخ التسجيل</span>
                                <strong>
                                    <?= escape($selectedUser['created_at']) ?>
                                </strong>
                            </div>

                            <div class="info">
                                <span>عدد الطلبات</span>
                                <strong><?= count($userOrders) ?></strong>
                            </div>
                        </div>

                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <th>الإجمالي</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الطلب</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>

                                <tbody>
                                <?php if (empty($userOrders)): ?>
                                    <tr>
                                        <td colspan="5" class="empty">
                                            لم يسجّل هذا العميل أي طلب حتى الآن.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($userOrders as $order): ?>
                                        <tr>
                                            <td>
                                                #<?= (int) $order['order_id'] ?>
                                            </td>

                                            <td class="price">
                                                <?= number_format(
                                                    (float) $order['total_amount'],
                                                    2
                                                ) ?>
                                            </td>

                                            <td>
                                                <span class="badge">
                                                    <?= escape(
                                                        $statusNames[$order['status']]
                                                        ?? $order['status']
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= escape($order['created_at']) ?>
                                            </td>

                                            <td>
                                                <a
                                                    href="orders.php?id=<?= (int) $order['order_id'] ?>#order-details"
                                                    class="details-link"
                                                >
                                                    تفاصيل الطلب
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    </section>
                <?php endif; ?>

                <section class="card">

                    <div class="card-header">
                        <h2>قائمة المستخدمين</h2>
                    </div>

                    <form method="GET" action="users.php" class="filters">
                        <input
                            type="search"
                            name="search"
                            placeholder="اسم العميل أو الهاتف أو رقم المستخدم"
                            aria-label="البحث عن مستخدم"
                            value="<?= escape($search) ?>"
                        >

                        <button type="submit">بحث</button>
                        <a href="users.php" class="reset">إعادة تعيين</a>
                    </form>

                    <p class="count">
                        عدد النتائج: <?= count($users) ?>
                    </p>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم المستخدم</th>
                                    <th>اسم العميل</th>
                                    <th>الهاتف</th>
                                    <th>تاريخ التسجيل</th>
                                    <th>عدد الطلبات</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="empty">
                                        لا يوجد مستخدمون مطابقون.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    $detailsUrl = 'users.php?' . http_build_query([
                                        'search' => $search,
                                        'id' => $user['user_id'],
                                    ]) . '#user-details';
                                    ?>

                                    <tr>
                                        <td>
                                            #<?= (int) $user['user_id'] ?>
                                        </td>

                                        <td>
                                            <?= escape($user['full_name']) ?>
                                        </td>

                                        <td>
                                            <bdi dir="ltr">
                                                <?= escape($user['phone'] ?: '—') ?>
                                            </bdi>
                                        </td>

                                        <td>
                                            <?= escape($user['created_at']) ?>
                                        </td>

                                        <td>
                                            <span class="badge">
                                                <?= (int) $user['order_count'] ?>
                                            </span>
                                        </td>

                                        <td>
                                            <a
                                                href="<?= escape($detailsUrl) ?>"
                                                class="details-link"
                                            >
                                                عرض الطلبات
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </section>

            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>