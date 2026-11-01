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

function optionalValue($value) {
    return $value !== '' ? $value : null;
}

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false || $id === null) {
    http_response_code(400);
    exit('رقم الجهاز غير صحيح. ارجعي إلى صفحة المنتجات.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$categories = [];
$categoriesLoaded = false;

// جلب بيانات الجهاز
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM products WHERE product_id = ?"
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('تعذّر تحميل الجهاز. حاولي مرة أخرى.');
}

if (!$product) {
    http_response_code(404);
    exit('الجهاز غير موجود. ارجعي إلى صفحة المنتجات.');
}

$fields = [
    'category_id', 'product_name', 'description', 'features',
    'brand', 'model', 'manufacturing_year', 'power',
    'voltage', 'dimensions', 'weight',
    'country_of_manufacture', 'price', 'quantity',
];

$data = [];

foreach ($fields as $field) {
    $data[$field] = (string) ($product[$field] ?? '');
}

$currentImage = (string) ($product['image'] ?? '');

// تحميل التصنيفات
try {
    $stmt = $pdo->query(
        "SELECT category_id, category_name
         FROM categories
         ORDER BY category_name"
    );
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categoriesLoaded = true;

} catch (PDOException $e) {
    error_log($e->getMessage());
    $errors[] = 'تعذّر تحميل التصنيفات.';
}

$arabicNames = [
    'restaurants' => 'المطاعم',
    'restaurant' => 'المطاعم',
    'bakeries' => 'المخابز',
    'bakery' => 'المخابز',
    'cafes' => 'الكافيهات',
    'cafe' => 'الكافيهات',
    'refrigeration' => 'التبريد',
    'cooling' => 'التبريد',
];

// معالجة التعديلات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($fields as $field) {
        $data[$field] = isset($_POST[$field]) && is_string($_POST[$field])
            ? trim($_POST[$field]) : '';
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'حدّثي الصفحة ثم حاولي مجددًا.';
    }

    if ($data['product_name'] === '') {
        $errors[] = 'اسم الجهاز مطلوب.';
    }

    $limits = [
        'product_name' => 150,
        'brand' => 100,
        'model' => 100,
        'power' => 50,
        'voltage' => 50,
        'dimensions' => 100,
        'weight' => 50,
        'country_of_manufacture' => 100,
    ];

    foreach ($limits as $field => $limit) {
        if (mb_strlen($data[$field], 'UTF-8') > $limit) {
            $errors[] = 'أحد الحقول تجاوز عدد الأحرف المسموح.';
            break;
        }
    }

    foreach (['description', 'features'] as $field) {
        if (strlen($data[$field]) > 65535) {
            $errors[] = 'الوصف أو المميزات أطول من الحد المسموح.';
            break;
        }
    }

    $categoryId = filter_var(
        $data['category_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $allowedCategories = array_map(
        'intval',
        array_column($categories, 'category_id')
    );

    if (
        $categoryId === false ||
        !in_array($categoryId, $allowedCategories, true)
    ) {
        $errors[] = 'اختاري تصنيفًا موجودًا.';
    }

    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $data['price'])) {
        $errors[] = 'أدخلي سعرًا صحيحًا مثل 1500 أو 1500.50.';
    }

    $quantity = filter_var(
        $data['quantity'],
        FILTER_VALIDATE_INT,
        ['options' => [
            'min_range' => 0,
            'max_range' => 2147483647,
        ]]
    );

    if ($quantity === false) {
        $errors[] = 'الكمية يجب أن تكون عددًا صحيحًا من صفر فأعلى.';
    }

    $year = null;

    if ($data['manufacturing_year'] !== '') {
        $year = filter_var(
            $data['manufacturing_year'],
            FILTER_VALIDATE_INT
        );

        if ($year === false || $year < 1901 || $year > 2155) {
            $errors[] = 'سنة التصنيع يجب أن تكون بين 1901 و2155.';
        }
    }

    // ترك حقل الصورة فارغًا يحافظ على الصورة الحالية
    $newImagePath = null;
    $uploadedFile = null;
    $extension = null;
    $file = $_FILES['image'] ?? null;

    if ($file !== null) {
        if (
            !is_array($file) ||
            !isset($file['error']) ||
            !is_int($file['error'])
        ) {
            $errors[] = 'بيانات الصورة غير صحيحة.';

        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'تعذّر رفع الصورة. جرّبي صورة أصغر.';

            } elseif (
                !isset($file['tmp_name']) ||
                !is_string($file['tmp_name']) ||
                !is_uploaded_file($file['tmp_name'])
            ) {
                $errors[] = 'ملف الصورة غير صالح.';

            } elseif (filesize($file['tmp_name']) > 2 * 1024 * 1024) {
                $errors[] = 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.';

            } else {
                $types = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $info = @getimagesize($file['tmp_name']);

                if (
                    !isset($types[$mime]) ||
                    $info === false ||
                    ($info['mime'] ?? '') !== $mime
                ) {
                    $errors[] = 'اختاري صورة JPG أو PNG أو WEBP.';
                } else {
                    $extension = $types[$mime];
                }
            }
        }
    }

    if (empty($errors) && $categoriesLoaded && $extension !== null) {
        $directory = __DIR__ . '/../uploads/products';

        if (
            !is_dir($directory) &&
            !@mkdir($directory, 0755, true) &&
            !is_dir($directory)
        ) {
            $errors[] = 'تعذّر إنشاء مجلد الصور.';
        } else {
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $directory . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $uploadedFile = $destination;
                $newImagePath = 'uploads/products/' . $filename;
            } else {
                $errors[] = 'تعذّر حفظ الصورة.';
            }
        }
    }

    if (empty($errors) && $categoriesLoaded) {
        try {
            $sql = "
                UPDATE products SET
                    category_id = ?,
                    product_name = ?,
                    description = ?,
                    features = ?,
                    brand = ?,
                    model = ?,
                    manufacturing_year = ?,
                    power = ?,
                    voltage = ?,
                    dimensions = ?,
                    weight = ?,
                    country_of_manufacture = ?,
                    price = ?,
                    quantity = ?
            ";

            $params = [
                $categoryId,
                $data['product_name'],
                optionalValue($data['description']),
                optionalValue($data['features']),
                optionalValue($data['brand']),
                optionalValue($data['model']),
                $year,
                optionalValue($data['power']),
                optionalValue($data['voltage']),
                optionalValue($data['dimensions']),
                optionalValue($data['weight']),
                optionalValue($data['country_of_manufacture']),
                $data['price'],
                $quantity,
            ];

            if ($newImagePath !== null) {
                $sql .= ", image = ?";
                $params[] = $newImagePath;
            }

            $sql .= " WHERE product_id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['product_success'] = 'تم حفظ تعديلات الجهاز بنجاح.';
            header('Location: products.php');
            exit;

        } catch (PDOException $e) {
            if ($uploadedFile !== null) {
                @unlink($uploadedFile);
            }

            error_log($e->getMessage());
            $errors[] = 'تعذّر حفظ التعديلات. حاولي مرة أخرى.';
        }
    }
}

$technicalFields = [
    'brand' => ['الماركة', 100],
    'model' => ['الموديل', 100],
    'power' => ['القدرة', 50],
    'voltage' => ['الجهد الكهربائي', 50],
    'dimensions' => ['الأبعاد', 100],
    'weight' => ['الوزن', 50],
    'country_of_manufacture' => ['بلد التصنيع', 100],
];

$validCurrentImage = preg_match(
    '~^uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$~D',
    $currentImage
);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الجهاز | تمكين</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #24364b;
            font-family: Tahoma, Arial, sans-serif;
        }

        a { color: #103b63; text-decoration: none; }
        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 235px;
            flex-shrink: 0;
            padding: 30px 16px;
            background: #103b63;
            color: white;
        }

        .logo {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            padding-bottom: 30px;
            margin-bottom: 25px;
            border-bottom: 1px solid #ffffff30;
        }

        .logo span { color: #ff8a24; }

        .sidebar a {
            display: block;
            color: white;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-size: 14px;
        }

        .sidebar a:hover, .sidebar a.active { background: #245a83; }
        .main { flex: 1; min-width: 0; }

        .topbar {
            background: white;
            padding: 25px 30px;
            border-bottom: 1px solid #e8edf3;
        }

        h1 { margin: 0 0 10px; font-size: 25px; color: #103b63; }
        .topbar p { margin: 0; color: #728095; line-height: 1.8; }

        .content {
            max-width: 1050px;
            margin: auto;
            padding: 28px;
        }

        .card {
            background: white;
            padding: 25px;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        h2 { margin: 0 0 22px; font-size: 18px; color: #103b63; }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 9px; font-size: 14px; }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            background: white;
            color: #24364b;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid #b9d6ed;
            border-color: #245a83;
        }

        textarea { min-height: 120px; resize: vertical; }

        #category_id {
            appearance: none;
            -webkit-appearance: none;
            padding-left: 42px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23103b63' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 14px center;
            background-size: 16px;
        }

        .current-image {
            display: block;
            width: 160px;
            height: 160px;
            object-fit: contain;
            border: 1px solid #e8edf3;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .hint { color: #728095; font-size: 12px; line-height: 1.9; }

        .error {
            background: #ffeded;
            color: #ad3434;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .error p { margin: 5px 0; }

        .actions {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
        }

        button {
            padding: 14px 28px;
            background: #103b63;
            color: white;
            border: none;
            border-radius: 8px;
            font: inherit;
            cursor: pointer;
        }

        button:disabled { opacity: 0.5; cursor: not-allowed; }

        @media (max-width: 600px) {
            .layout { display: block; }
            .sidebar { width: 100%; padding: 15px; }
            .logo { padding-bottom: 15px; margin-bottom: 15px; }

            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .sidebar a { margin: 0; text-align: center; }
            .grid { grid-template-columns: 1fr; }
            .content, .topbar { padding: 20px 15px; }
            .card { padding: 18px; }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Tamkeen<span>.</span></div>

        <nav aria-label="تنقل الإدارة">
            <a href="dashboard.php">الرئيسية</a>
            <a href="products.php" class="active">المنتجات</a>
            <a href="../index.php">زيارة المتجر</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>تعديل الجهاز #<?= (int) $id ?></h1>
            <p>عدّلي البيانات ثم اضغطي حفظ التعديلات.</p>
        </header>

        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= escape($error) ?></p>
                    <?php endforeach; ?>

                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <p>إذا اخترتِ صورة جديدة، اختاريها مجددًا قبل الحفظ.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="edit-product.php?id=<?= (int) $id ?>"
                enctype="multipart/form-data"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <section class="card">
                    <h2>البيانات الأساسية</h2>

                    <div class="grid">
                        <div class="full">
                            <label for="product_name">اسم الجهاز *</label>
                            <input
                                id="product_name"
                                name="product_name"
                                maxlength="150"
                                value="<?= escape($data['product_name']) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="category_id">التصنيف *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">اختاري التصنيف</option>

                                <?php foreach ($categories as $category): ?>
                                    <?php
                                    $name = trim($category['category_name']);
                                    $label = $arabicNames[strtolower($name)] ?? $name;
                                    ?>
                                    <option
                                        value="<?= (int) $category['category_id'] ?>"
                                        <?= (string) $category['category_id'] === $data['category_id']
                                            ? 'selected' : '' ?>
                                    >
                                        <?= escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="price">السعر *</label>
                            <input
                                type="number"
                                id="price"
                                name="price"
                                min="0"
                                max="99999999.99"
                                step="0.01"
                                value="<?= escape($data['price']) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="quantity">الكمية المتوفرة *</label>
                            <input
                                type="number"
                                id="quantity"
                                name="quantity"
                                min="0"
                                max="2147483647"
                                step="1"
                                value="<?= escape($data['quantity']) ?>"
                                required
                            >
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>المواصفات الفنية</h2>

                    <div class="grid">
                        <?php foreach ($technicalFields as $field => $info): ?>
                            <div>
                                <label for="<?= escape($field) ?>">
                                    <?= escape($info[0]) ?>
                                </label>
                                <input
                                    id="<?= escape($field) ?>"
                                    name="<?= escape($field) ?>"
                                    maxlength="<?= (int) $info[1] ?>"
                                    value="<?= escape($data[$field]) ?>"
                                >
                            </div>
                        <?php endforeach; ?>

                        <div>
                            <label for="manufacturing_year">سنة التصنيع</label>
                            <input
                                type="number"
                                id="manufacturing_year"
                                name="manufacturing_year"
                                min="1901"
                                max="2155"
                                step="1"
                                value="<?= escape($data['manufacturing_year']) ?>"
                            >
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>الوصف والمميزات</h2>

                    <div class="grid">
                        <div class="full">
                            <label for="description">وصف الجهاز</label>
                            <textarea
                                id="description"
                                name="description"
                            ><?= escape($data['description']) ?></textarea>
                        </div>

                        <div class="full">
                            <label for="features">المميزات</label>
                            <textarea
                                id="features"
                                name="features"
                            ><?= escape($data['features']) ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2>صورة الجهاز</h2>

                    <?php if ($validCurrentImage): ?>
                        <img
                            src="<?= escape('../' . $currentImage) ?>"
                            alt="صورة الجهاز الحالية"
                            class="current-image"
                        >
                    <?php endif; ?>

                    <label for="image">اختيار صورة جديدة</label>
                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <p class="hint">
                        اتركي الحقل فارغًا للاحتفاظ بالصورة الحالية.
                        الصورة الجديدة: JPG أو PNG أو WEBP،
                        بحد أقصى 2 ميجابايت.
                    </p>
                </section>

                <div class="actions">
                    <button
                        type="submit"
                        <?= !$categoriesLoaded || empty($categories)
                            ? 'disabled' : '' ?>
                    >
                        حفظ التعديلات
                    </button>

                    <a href="products.php">إلغاء والعودة</a>
                </div>
            </form>
        </div>
    </main>

</div>
</body>
</html>