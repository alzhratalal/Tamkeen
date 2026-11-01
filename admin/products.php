<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$search = is_string($_GET['search'] ?? null)
    ? trim($_GET['search']) : '';

$filter = is_string($_GET['availability'] ?? null)
    ? $_GET['availability'] : '';

$products = [];
$error = '';

try {
    $sql = "
        SELECT product_id, product_name, brand, price,
               quantity, status, created_at, image
        FROM products
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (product_name LIKE ? OR brand LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($filter === 'available') {
        $sql .= " AND quantity > 0";
    } elseif ($filter === 'unavailable') {
        $sql .= " AND quantity <= 0";
    }

    $sql .= " ORDER BY product_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'تعذّر تحميل المنتجات.';
}

$success = $_SESSION['product_success'] ?? '';
unset($_SESSION['product_success']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المنتجات | تمكين</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #24364b;
            font-family: Tahoma, Arial, sans-serif;
        }

        .container {
            max-width: 1300px;
            margin: auto;
            padding: 30px 20px;
        }

        .header, .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        h1 { color: #103b63; margin: 0 0 10px; font-size: 25px; }
        .description, .count { color: #728095; font-size: 14px; }
        a { color: #103b63; text-decoration: none; }

        .button {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 8px;
            background: #103b63;
            color: white;
            border: none;
            font: inherit;
            font-size: 14px;
            cursor: pointer;
        }

        .card {
            background: white;
            padding: 22px;
            border: 1px solid #e8edf3;
            border-radius: 12px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        input, select {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #dce3ec;
            font: inherit;
            background: white;
        }

        input { flex: 1; min-width: 200px; }
        .reset { font-size: 13px; }
        .table-scroll { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        th, td {
            padding: 15px 12px;
            text-align: right;
            border-bottom: 1px solid #edf1f6;
            font-size: 13px;
        }

        th { background: #f5f8fc; color: #52677e; }
        .price { color: #d96912; font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .available { background: #e7f7ee; color: #217448; }
        .unavailable { background: #ffeded; color: #ad3434; }

        .edit-link {
            display: inline-block;
            background: #edf4fb;
            color: #103b63;
            padding: 9px 15px;
            border-radius: 7px;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .empty { text-align: center; padding: 30px; color: #728095; }
        .message { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #ffeded; color: #ad3434; }
        .success { background: #e7f7ee; color: #217448; }

        @media (max-width: 600px) {
            .container { padding: 20px 12px; }
            .card { padding: 14px; }
            .filters { flex-direction: column; align-items: stretch; }
            input { min-width: 0; width: 100%; }
        }
    </style>
</head>

<body>
<main class="container">

    <header class="header">
        <div>
            <h1>إدارة المنتجات</h1>
            <p class="description">
                عرض أجهزة المتجر والبحث عنها وتعديل بياناتها.
            </p>
        </div>

        <a href="dashboard.php">العودة إلى لوحة التحكم</a>
    </header>

    <?php if ($error !== ''): ?>
        <div class="message error" role="alert">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="message success" role="status">
            <?= escape($success) ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="add-product.php" class="button">＋ إضافة جهاز جديد</a>
    </div>

    <section class="card">

        <form method="GET" class="filters">
            <input
                type="search"
                name="search"
                placeholder="ابحثي باسم الجهاز أو الماركة"
                aria-label="البحث عن جهاز"
                value="<?= escape($search) ?>"
            >

            <select name="availability" aria-label="التوفر حسب الكمية">
                <option value="">جميع المنتجات</option>
                <option value="available"
                    <?= $filter === 'available' ? 'selected' : '' ?>>
                    متوفر بالمخزون
                </option>
                <option value="unavailable"
                    <?= $filter === 'unavailable' ? 'selected' : '' ?>>
                    نفدت الكمية
                </option>
            </select>

            <button type="submit" class="button">بحث وتصفية</button>
            <a href="products.php" class="reset">إعادة تعيين</a>
        </form>

        <?php if ($error === ''): ?>
            <p class="count">عدد النتائج: <?= count($products) ?></p>

            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>رقم الجهاز</th>
                        <th>اسم الجهاز</th>
                        <th>الماركة</th>
                        <th>السعر</th>
                        <th>الكمية</th>
                        <th>التوفر حسب الكمية</th>
                        <th>الحالة المسجلة</th>
                        <th>تاريخ الإضافة</th>
                        <th>الإجراءات</th>
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
                            ?>
                            <tr>
                                <td>
                                    <?php if ($validImage): ?>
                                        <img
                                            src="<?= escape('../' . $image) ?>"
                                            alt="<?= escape($product['product_name']) ?>"
                                            class="product-image"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td><?= (int) $product['product_id'] ?></td>
                                <td><?= escape($product['product_name']) ?></td>
                                <td><?= escape($product['brand'] ?: '—') ?></td>

                                <td class="price">
                                    <?= number_format((float) $product['price'], 2) ?>
                                </td>

                                <td><?= (int) $product['quantity'] ?></td>

                                <td>
                                    <?php if ((int) $product['quantity'] > 0): ?>
                                        <span class="badge available">متوفر</span>
                                    <?php else: ?>
                                        <span class="badge unavailable">نفدت الكمية</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= escape($product['status']) ?></td>
                                <td><?= escape($product['created_at']) ?></td>

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
        <?php endif; ?>

    </section>
</main>
</body>
</html>