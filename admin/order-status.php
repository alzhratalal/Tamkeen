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

// الانتقالات المتاحة لكل حالة
$transitions = [
    'pending' => ['processing'],
    'processing' => ['shipped'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'completed' => [],
    'cancelled' => [],
    'canceled' => [],
];

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('رقم الطلب غير صحيح. ارجعي إلى صفحة الطلبات.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$order = null;

// حفظ الحالة الجديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $newStatus = $_POST['status'] ?? '';
    $previousStatus = $_POST['previous_status'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'حدّثي الصفحة ثم حاولي مرة أخرى.';

    } elseif (
        !is_string($newStatus) ||
        !is_string($previousStatus)
    ) {
        $error = 'الحالة المرسلة غير صحيحة.';

    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT status
                FROM orders
                WHERE order_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$id]);
            $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentOrder) {
                $error = 'الطلب غير موجود.';

            } elseif ($currentOrder['status'] !== $previousStatus) {
                $error = 'تغيّرت حالة الطلب منذ فتح الصفحة. راجعي الحالة الحالية.';

            } elseif (
                !in_array(
                    $newStatus,
                    $transitions[$currentOrder['status']] ?? [],
                    true
                )
            ) {
                $error = 'اختاري الحالة التالية المتاحة للطلب.';

            } else {
                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET status = ?
                    WHERE order_id = ?
                ");

                $stmt->execute([$newStatus, $id]);
            }

            if ($error !== '') {
                $pdo->rollBack();
            } else {
                $pdo->commit();

                $_SESSION['order_success'] =
                    'تم تحديث حالة الطلب بنجاح.';

                header(
                    'Location: orders.php?id=' . $id . '#order-details'
                );
                exit;
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = 'تعذّر تحديث حالة الطلب. حاولي مرة أخرى.';
        }
    }
}

// تحميل بيانات الطلب الحالية
try {
    $stmt = $pdo->prepare("
        SELECT order_id, full_name, total_amount, status, created_at
        FROM orders
        WHERE order_id = ?
    ");

    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'تعذّر تحميل الطلب.';
}

if (!$order && $error === '') {
    http_response_code(404);
    $error = 'الطلب غير موجود.';
}

$nextStatuses = $order
    ? ($transitions[$order['status']] ?? [])
    : [];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تحديث حالة الطلب | تمكين</title>

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

        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 16px;
        }

        h1 {
            color: #103b63;
            font-size: 25px;
            margin-bottom: 12px;
        }

        a {
            color: #103b63;
            text-decoration: none;
        }

        .card {
            background: white;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            padding: 25px;
            margin-top: 22px;
        }

        .info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .info span {
            display: block;
            color: #728095;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .info strong {
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .badge {
            display: inline-block;
            background: #edf4fb;
            color: #103b63;
            padding: 9px 12px;
            border-radius: 7px;
        }

        label {
            display: block;
            font-size: 14px;
            margin-bottom: 10px;
        }

        select {
            width: 100%;
            padding: 13px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
            font: inherit;
            background: white;
        }

        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 22px;
        }

        button {
            background: #103b63;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 13px 22px;
            font: inherit;
            cursor: pointer;
        }

        .error {
            background: #ffeded;
            color: #ad3434;
            padding: 16px;
            border-radius: 8px;
            margin-top: 20px;
            line-height: 1.8;
        }

        .notice {
            background: #edf4fb;
            color: #103b63;
            padding: 16px;
            border-radius: 8px;
            line-height: 1.8;
        }

        @media (max-width: 500px) {
            .info {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
<main class="container">

    <h1>تحديث حالة الطلب</h1>
    <a href="orders.php">العودة إلى الطلبات</a>

    <?php if ($error !== ''): ?>
        <div class="error" role="alert">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($order): ?>
        <section class="card">

            <div class="info">
                <div>
                    <span>رقم الطلب</span>
                    <strong>#<?= (int) $order['order_id'] ?></strong>
                </div>

                <div>
                    <span>اسم العميل</span>
                    <strong><?= escape($order['full_name']) ?></strong>
                </div>

                <div>
                    <span>إجمالي الطلب</span>
                    <strong>
                        <?= number_format(
                            (float) $order['total_amount'],
                            2
                        ) ?>
                    </strong>
                </div>

                <div>
                    <span>الحالة الحالية</span>
                    <strong class="badge">
                        <?= escape(
                            $statusNames[$order['status']]
                            ?? $order['status']
                        ) ?>
                    </strong>
                </div>
            </div>

            <?php if (!empty($nextStatuses)): ?>

                <form
                    method="POST"
                    action="order-status.php?id=<?= (int) $id ?>"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="previous_status"
                        value="<?= escape($order['status']) ?>"
                    >

                    <label for="status">الحالة التالية</label>

                    <select id="status" name="status" required>
                        <option value="">اختاري الحالة التالية</option>

                        <?php foreach ($nextStatuses as $status): ?>
                            <option value="<?= escape($status) ?>">
                                <?= escape($statusNames[$status]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="actions">
                        <button type="submit">حفظ الحالة</button>

                        <a href="orders.php?id=<?= (int) $id ?>#order-details">
                            إلغاء والعودة
                        </a>
                    </div>
                </form>

            <?php else: ?>

                <p class="notice">
                    لا توجد خطوة تالية متاحة لهذه الحالة.
                </p>

            <?php endif; ?>

        </section>
    <?php endif; ?>

</main>
</body>
</html>