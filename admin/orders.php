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

function statusLabel($status, $names) {
    return $names[$status] ?? $status;
}

$search = is_string($_GET['search'] ?? null)
    ? trim($_GET['search']) : '';

$filter = is_string($_GET['status'] ?? null)
    ? $_GET['status'] : '';

$orders = [];
$availableStatuses = [];
$selectedOrder = null;
$orderItems = [];
$error = '';
$detailError = '';

$success = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']);

$stats = [
    'total' => 0,
    'pending' => 0,
    'processing' => 0,
    'finished' => 0,
];

// تحميل الإحصائيات والطلبات
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'pending'), 0) AS pending,
            COALESCE(SUM(status = 'processing'), 0) AS processing,
            COALESCE(
                SUM(status IN ('delivered', 'completed')),
                0
            ) AS finished
        FROM orders
    ");

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT DISTINCT status
        FROM orders
        ORDER BY status
    ");

    $availableStatuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = "
        SELECT
            o.order_id,
            o.full_name,
            o.phone,
            o.total_amount,
            o.status,
            o.created_at,
            (
                SELECT COUNT(*)
                FROM order_items oi
                WHERE oi.order_id = o.order_id
            ) AS item_count
        FROM orders o
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= "
            AND (
                CAST(o.order_id AS CHAR) = ?
                OR o.full_name LIKE ?
                OR o.phone LIKE ?
            )
        ";

        $params[] = ltrim($search, '#');
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($filter !== '') {
        $sql .= " AND o.status = ?";
        $params[] = $filter;
    }

    $sql .= " ORDER BY o.order_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'تعذّر تحميل الطلبات. حاولي مرة أخرى.';
}

// تحميل تفاصيل طلب محدد
if (isset($_GET['id']) && $error === '') {
    $orderId = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($orderId === false || $orderId === null) {
        $detailError = 'رقم الطلب غير صحيح.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT order_id, user_id, full_name, phone,
                       delivery_address, total_amount,
                       status, created_at
                FROM orders
                WHERE order_id = ?
            ");

            $stmt->execute([$orderId]);
            $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$selectedOrder) {
                $detailError = 'الطلب غير موجود.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT
                        oi.product_id,
                        oi.product_name,
                        oi.quantity,
                        oi.unit_price,
                        (oi.quantity * oi.unit_price) AS line_total,
                        p.image
                    FROM order_items oi
                    LEFT JOIN products p
                        ON p.product_id = oi.product_id
                    WHERE oi.order_id = ?
                    ORDER BY oi.order_item_id
                ");

                $stmt->execute([$orderId]);
                $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $selectedOrder = null;
            $detailError = 'تعذّر تحميل تفاصيل الطلب.';
        }
    }
}

$listParams = [
    'search' => $search,
    'status' => $filter,
];

$listUrl = 'orders.php?' . http_build_query($listParams);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة الطلبات | تمكين</title>

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
            color: white;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
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
            padding: 25px 30px;
            background: white;
            border-bottom: 1px solid #e8edf3;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 25px;
            color: #103b63;
        }

        .topbar p {
            margin: 0;
            color: #728095;
            font-size: 14px;
            line-height: 1.8;
        }

        .content {
            padding: 28px;
            max-width: 1500px;
            margin: auto;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
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
            margin: 0 0 15px;
            color: #728095;
            font-size: 13px;
        }

        .stat strong {
            font-size: 29px;
            color: #103b63;
        }

        .card {
            margin-bottom: 25px;
        }

        .card h2 {
            margin: 0;
            color: #103b63;
            font-size: 19px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        .header-actions,
        .row-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        input,
        select,
        button {
            font: inherit;
            font-size: 14px;
            padding: 12px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
        }

        input {
            flex: 1;
            min-width: 190px;
        }

        select {
            background: white;
            max-width: 100%;
        }

        button {
            background: #103b63;
            color: white;
            border: none;
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

        .price {
            color: #d96912;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 6px;
            background: #edf4fb;
            color: #103b63;
        }

        .details-link,
        .status-link {
            display: inline-block;
            padding: 9px 13px;
            border-radius: 7px;
            font-size: 13px;
            white-space: nowrap;
        }

        .details-link {
            background: #103b63;
            color: white;
        }

        .status-link {
            background: #edf4fb;
            color: #103b63;
        }

        .details-link:hover {
            background: #245a83;
        }

        .status-link:hover {
            background: #dceaf7;
        }

        .empty {
            text-align: center;
            color: #728095;
            padding: 28px;
        }

        .error,
        .success {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .error {
            background: #ffeded;
            color: #ad3434;
        }

        .success {
            background: #e7f7ee;
            color: #217448;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .info {
            padding: 15px;
            border-radius: 8px;
            background: #f5f8fc;
            min-width: 0;
        }

        .info span {
            display: block;
            color: #728095;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .info strong {
            font-size: 14px;
            font-weight: normal;
            overflow-wrap: anywhere;
        }

        .address {
            grid-column: 1 / -1;
        }

        .address strong {
            white-space: pre-wrap;
            line-height: 1.8;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .total {
            margin: 22px 0 0;
            color: #103b63;
            font-size: 18px;
            font-weight: bold;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sidebar {
                width: 190px;
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

            .card,
            .stat {
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
            <a href="orders.php" class="active">الطلبات</a>
            <a href="../index.php">زيارة المتجر</a>
        </nav>
    </aside>

    <main class="main">

        <header class="topbar">
            <h1>إدارة الطلبات</h1>
            <p>
                متابعة طلبات العملاء وعرض تفاصيلها وتحديث حالتها.
            </p>
        </header>

        <div class="content">

            <?php if ($success !== ''): ?>
                <div class="success" role="status">
                    <?= escape($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="error" role="alert">
                    <?= escape($error) ?>
                </div>
            <?php else: ?>

                <section class="stats" aria-label="إحصائيات جميع الطلبات">

                    <div class="stat">
                        <p>إجمالي الطلبات</p>
                        <strong><?= (int) $stats['total'] ?></strong>
                    </div>

                    <div class="stat">
                        <p>قيد الانتظار</p>
                        <strong><?= (int) $stats['pending'] ?></strong>
                    </div>

                    <div class="stat">
                        <p>قيد التجهيز</p>
                        <strong><?= (int) $stats['processing'] ?></strong>
                    </div>

                    <div class="stat">
                        <p>مكتملة أو تم تسليمها</p>
                        <strong><?= (int) $stats['finished'] ?></strong>
                    </div>

                </section>

                <?php if ($detailError !== ''): ?>
                    <div class="error" role="alert">
                        <?= escape($detailError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($selectedOrder): ?>
                    <section class="card" id="order-details">

                        <div class="card-header">
                            <h2>
                                تفاصيل الطلب
                                #<?= (int) $selectedOrder['order_id'] ?>
                            </h2>

                            <div class="header-actions">
                                <a
                                    href="order-status.php?id=<?= (int) $selectedOrder['order_id'] ?>"
                                    class="status-link"
                                >
                                    تغيير الحالة
                                </a>

                                <a href="<?= escape($listUrl) ?>">
                                    إغلاق التفاصيل
                                </a>
                            </div>
                        </div>

                        <div class="info-grid">

                            <div class="info">
                                <span>اسم العميل</span>
                                <strong>
                                    <?= escape($selectedOrder['full_name']) ?>
                                </strong>
                            </div>

                            <div class="info">
                                <span>رقم الهاتف</span>
                                <strong>
                                    <bdi dir="ltr">
                                        <?= escape($selectedOrder['phone']) ?>
                                    </bdi>
                                </strong>
                            </div>

                            <div class="info">
                                <span>تاريخ الطلب</span>
                                <strong>
                                    <?= escape($selectedOrder['created_at']) ?>
                                </strong>
                            </div>

                            <div class="info">
                                <span>حالة الطلب</span>
                                <strong>
                                    <?= escape(statusLabel(
                                        $selectedOrder['status'],
                                        $statusNames
                                    )) ?>
                                </strong>
                            </div>

                            <div class="info address">
                                <span>عنوان التوصيل</span>
                                <strong><?= escape(
                                    $selectedOrder['delivery_address']
                                ) ?></strong>
                            </div>

                        </div>

                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>الصورة الحالية</th>
                                        <th>الجهاز وقت الطلب</th>
                                        <th>الكمية</th>
                                        <th>سعر الوحدة وقت الطلب</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>

                                <tbody>
                                <?php if (empty($orderItems)): ?>
                                    <tr>
                                        <td colspan="5" class="empty">
                                            لا توجد أجهزة مسجلة لهذا الطلب.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orderItems as $item): ?>
                                        <?php
                                        $image = (string) ($item['image'] ?? '');

                                        $validImage = preg_match(
                                            '~^uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$~D',
                                            $image
                                        );
                                        ?>

                                        <tr>
                                            <td>
                                                <?php if ($validImage): ?>
                                                    <img
                                                        src="<?= escape('../' . $image) ?>"
                                                        alt="<?= escape($item['product_name']) ?>"
                                                        class="product-image"
                                                        loading="lazy"
                                                    >
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= escape($item['product_name']) ?>
                                            </td>

                                            <td>
                                                <?= (int) $item['quantity'] ?>
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (float) $item['unit_price'],
                                                    2
                                                ) ?>
                                            </td>

                                            <td class="price">
                                                <?= number_format(
                                                    (float) $item['line_total'],
                                                    2
                                                ) ?>
                                            </td>
                                        </tr>

                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="total">
                            إجمالي الطلب المسجل:
                            <?= number_format(
                                (float) $selectedOrder['total_amount'],
                                2
                            ) ?>
                        </p>

                    </section>
                <?php endif; ?>

                <section class="card">

                    <div class="card-header">
                        <h2>قائمة الطلبات</h2>
                    </div>

                    <form method="GET" action="orders.php" class="filters">

                        <input
                            type="search"
                            name="search"
                            placeholder="رقم الطلب أو اسم العميل أو الهاتف"
                            aria-label="البحث عن طلب"
                            value="<?= escape($search) ?>"
                        >

                        <select name="status" aria-label="حالة الطلب">
                            <option value="">جميع الحالات</option>

                            <?php foreach ($availableStatuses as $status): ?>
                                <option
                                    value="<?= escape($status) ?>"
                                    <?= $filter === (string) $status
                                        ? 'selected' : '' ?>
                                >
                                    <?= escape(statusLabel(
                                        $status,
                                        $statusNames
                                    )) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit">بحث وتصفية</button>
                        <a href="orders.php" class="reset">إعادة تعيين</a>

                    </form>

                    <p class="count">
                        عدد النتائج: <?= count($orders) ?>
                    </p>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>اسم العميل</th>
                                    <th>الهاتف</th>
                                    <th>عدد أصناف الأجهزة</th>
                                    <th>الإجمالي</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="empty">
                                        لا توجد طلبات مطابقة.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $detailsUrl = 'orders.php?' . http_build_query([
                                        'search' => $search,
                                        'status' => $filter,
                                        'id' => $order['order_id'],
                                    ]) . '#order-details';
                                    ?>

                                    <tr>
                                        <td>
                                            #<?= (int) $order['order_id'] ?>
                                        </td>

                                        <td>
                                            <?= escape($order['full_name']) ?>
                                        </td>

                                        <td>
                                            <bdi dir="ltr">
                                                <?= escape($order['phone']) ?>
                                            </bdi>
                                        </td>

                                        <td>
                                            <?= (int) $order['item_count'] ?>
                                        </td>

                                        <td class="price">
                                            <?= number_format(
                                                (float) $order['total_amount'],
                                                2
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="badge">
                                                <?= escape(statusLabel(
                                                    $order['status'],
                                                    $statusNames
                                                )) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= escape($order['created_at']) ?>
                                        </td>

                                        <td>
                                            <div class="row-actions">

                                                <a
                                                    href="<?= escape($detailsUrl) ?>"
                                                    class="details-link"
                                                >
                                                    عرض التفاصيل
                                                </a>

                                                <a
                                                    href="order-status.php?id=<?= (int) $order['order_id'] ?>"
                                                    class="status-link"
                                                >
                                                    تغيير الحالة
                                                </a>

                                            </div>
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