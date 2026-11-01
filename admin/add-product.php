<?php
session_start();

// التحقق من تسجيل دخول الأدمن
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// رمز حماية النموذج
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$categories = [];
$categoriesLoaded = false;

$data = [
    'category_id' => '',
    'product_name' => '',
    'description' => '',
    'features' => '',
    'brand' => '',
    'model' => '',
    'manufacturing_year' => '',
    'power' => '',
    'voltage' => '',
    'dimensions' => '',
    'weight' => '',
    'country_of_manufacture' => '',
    'price' => '',
    'quantity' => '1',
];

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
    $errors[] = 'تعذّر تحميل التصنيفات من قاعدة البيانات.';
}

// معالجة حفظ الجهاز
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($data as $key => $value) {
        $data[$key] = isset($_POST[$key]) && is_string($_POST[$key])
            ? trim($_POST[$key])
            : '';
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'حدّثي الصفحة ثم حاولي حفظ الجهاز مجددًا.';
    }

    // اسم الجهاز
    if ($data['product_name'] === '') {
        $errors[] = 'أدخلي اسم الجهاز.';
    }

    // أطوال الحقول حسب قاعدة البيانات
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

    // التصنيف
    $categoryId = filter_var(
        $data['category_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (
        $categoryId === false ||
        !in_array(
    $categoryId,
    array_map('intval', array_column($categories, 'category_id')),
    true
)
    ) {
        $errors[] = 'اختاري تصنيفًا موجودًا.';
    }

    // السعر
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $data['price'])) {
        $errors[] = 'أدخلي سعرًا صحيحًا مثل 1500 أو 1500.50.';
    }

    // الكمية
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

    // سنة التصنيع اختيارية
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

    // فحص الصورة الاختيارية
    $imagePath = null;
    $uploadedFile = null;
    $imageExtension = null;
    $file = $_FILES['image'] ?? null;

    if ($file !== null) {
        if (
            !is_array($file) ||
            !isset($file['error']) ||
            !is_int($file['error'])
        ) {
            $errors[] = 'بيانات الصورة غير صحيحة. اختاريها مجددًا.';

        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'تعذّر رفع الصورة. جرّبي صورة أصغر.';

            } elseif (
                !isset($file['tmp_name']) ||
                !is_string($file['tmp_name']) ||
                !is_uploaded_file($file['tmp_name'])
            ) {
                $errors[] = 'ملف الصورة غير صالح.';

            } elseif (
                filesize($file['tmp_name']) > 2 * 1024 * 1024
            ) {
                $errors[] = 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.';

            } else {
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $imageInfo = @getimagesize($file['tmp_name']);

                if (
                    !isset($allowedTypes[$mime]) ||
                    $imageInfo === false ||
                    ($imageInfo['mime'] ?? '') !== $mime
                ) {
                    $errors[] = 'اختاري صورة JPG أو PNG أو WEBP.';
                } else {
                    $imageExtension = $allowedTypes[$mime];
                }
            }
        }
    }

    // حفظ الصورة بعد نجاح التحقق
    if (
        empty($errors) &&
        $categoriesLoaded &&
        $imageExtension !== null
    ) {
        $uploadDir = __DIR__ . '/../uploads/products';

        if (
            !is_dir($uploadDir) &&
            !@mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            $errors[] = 'تعذّر إنشاء مجلد صور المنتجات.';

        } else {
            $filename = bin2hex(random_bytes(16))
                . '.' . $imageExtension;

            $destination = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $uploadedFile = $destination;
                $imagePath = 'uploads/products/' . $filename;
            } else {
                $errors[] = 'تعذّر حفظ صورة الجهاز.';
            }
        }
    }

    // حفظ بيانات الجهاز
    if (empty($errors) && $categoriesLoaded) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    category_id,
                    product_name,
                    description,
                    features,
                    brand,
                    model,
                    manufacturing_year,
                    power,
                    voltage,
                    dimensions,
                    weight,
                    country_of_manufacture,
                    price,
                    quantity,
                    image
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $categoryId,
                $data['product_name'],
                $data['description'] !== '' ? $data['description'] : null,
                $data['features'] !== '' ? $data['features'] : null,
                $data['brand'] !== '' ? $data['brand'] : null,
                $data['model'] !== '' ? $data['model'] : null,
                $year,
                $data['power'] !== '' ? $data['power'] : null,
                $data['voltage'] !== '' ? $data['voltage'] : null,
                $data['dimensions'] !== '' ? $data['dimensions'] : null,
                $data['weight'] !== '' ? $data['weight'] : null,
                $data['country_of_manufacture'] !== ''
                    ? $data['country_of_manufacture'] : null,
                $data['price'],
                $quantity,
                $imagePath,
            ]);

            $_SESSION['product_success'] = 'تمت إضافة الجهاز بنجاح.';

            header('Location: products.php');
            exit;

        } catch (PDOException $e) {
            if ($uploadedFile !== null) {
                @unlink($uploadedFile);
            }

            error_log($e->getMessage());
            $errors[] = 'تعذّر حفظ الجهاز. حاولي مرة أخرى.';
        }
    }
}

$technicalFields = [
    'brand' => ['الماركة', 'مثال: اسم الشركة المصنعة', 100],
    'model' => ['الموديل', 'مثال: MX-200', 100],
    'power' => ['القدرة', 'مثال: 2000 واط', 50],
    'voltage' => ['الجهد الكهربائي', 'مثال: 220 فولت', 50],
    'dimensions' => ['الأبعاد', 'مثال: 80 × 60 × 90 سم', 100],
    'weight' => ['الوزن', 'مثال: 35 كجم', 50],
    'country_of_manufacture' => ['بلد التصنيع', 'مثال: إيطاليا', 100],
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إضافة جهاز جديد | تمكين</title>

    <style>
        #category_id {
    appearance: none;
    -webkit-appearance: none;

    padding-left: 42px;
    padding-right: 12px;

    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23103b63' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: left 14px center;
    background-size: 16px;
}
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
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            padding: 10px 0 30px;
            border-bottom: 1px solid #ffffff30;
            margin-bottom: 25px;
        }

        .logo span {
            color: #ff8a24;
        }

        .sidebar nav a {
            display: block;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
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
            max-width: 1050px;
            margin: auto;
            padding: 28px;
        }

        .card {
            background: white;
            border: 1px solid #e8edf3;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 22px;
        }

        .card h2 {
            margin: 0 0 22px;
            color: #103b63;
            font-size: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce3ec;
            border-radius: 8px;
            background: white;
            color: #24364b;
            font: inherit;
            font-size: 14px;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #b9d6ed;
            border-color: #245a83;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .hint {
            margin: 10px 0 0;
            color: #728095;
            font-size: 12px;
            line-height: 1.9;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
        }

        .save-button {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            background: #103b63;
            color: white;
            font: inherit;
            cursor: pointer;
        }

        .save-button:hover {
            background: #245a83;
        }

        .save-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .cancel-link {
            color: #52677e;
            font-size: 14px;
        }

        .error {
            background: #ffeded;
            color: #ad3434;
            padding: 16px;
            border-radius: 8px;
            line-height: 1.9;
            margin-bottom: 20px;
        }

        .error p {
            margin: 4px 0;
        }

        @media (max-width: 800px) {
            .sidebar {
                width: 185px;
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
                margin-bottom: 15px;
            }

            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar nav a {
                margin: 0;
                text-align: center;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .content,
            .topbar {
                padding: 20px 15px;
            }

            .card {
                padding: 18px;
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
            <a href="products.php" class="active">المنتجات</a>
            <a href="../index.php">زيارة المتجر</a>
        </nav>
    </aside>

    <main class="main">

        <header class="topbar">
            <h1>إضافة جهاز جديد</h1>
            <p>
                أدخلي بيانات الجهاز وصورته ومواصفاته.
                الحقول التي تحمل * مطلوبة.
            </p>
        </header>

        <div class="content">

            <?php if (!empty($errors)): ?>
                <div class="error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= escape($error) ?></p>
                    <?php endforeach; ?>

                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <p>إذا اخترتِ صورة، اختاريها مجددًا قبل الحفظ.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($categoriesLoaded && empty($categories)): ?>
                <div class="error">
                    لا توجد تصنيفات.
                    يجب إضافة تصنيف إلى جدول categories قبل حفظ جهاز.
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="add-product.php"
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
                                type="text"
                                id="product_name"
                                name="product_name"
                                maxlength="150"
                                placeholder="مثال: فرن كهربائي"
                                value="<?= escape($data['product_name']) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="category_id">التصنيف *</label>
                            <select id="category_id" name="category_id" required>
    <option value="">اختاري التصنيف</option>

    <?php foreach ($categories as $category): ?>
        <option
            value="<?= (int) $category['category_id'] ?>"
            <?= (string) $category['category_id'] === $data['category_id']
                ? 'selected' : '' ?>
        >
           <?php

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

$name = trim($category['category_name']);

echo escape($arabicNames[strtolower($name)] ?? $name);

?>
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
                    <h2>المواصفات الفنية — اختيارية</h2>

                    <div class="grid">

                        <?php foreach ($technicalFields as $name => $info): ?>
                            <div>
                                <label for="<?= escape($name) ?>">
                                    <?= escape($info[0]) ?>
                                </label>

                                <input
                                    type="text"
                                    id="<?= escape($name) ?>"
                                    name="<?= escape($name) ?>"
                                    maxlength="<?= (int) $info[2] ?>"
                                    placeholder="<?= escape($info[1]) ?>"
                                    value="<?= escape($data[$name]) ?>"
                                >
                            </div>
                        <?php endforeach; ?>

                        <div>
                            <label for="manufacturing_year">
                                سنة التصنيع
                            </label>

                            <input
                                type="number"
                                id="manufacturing_year"
                                name="manufacturing_year"
                                min="1901"
                                max="2155"
                                step="1"
                                placeholder="مثال: 2024"
                                value="<?= escape($data['manufacturing_year']) ?>"
                            >
                        </div>

                    </div>
                </section>

                <section class="card">
                    <h2>الوصف والمميزات — اختيارية</h2>

                    <div class="grid">

                        <div class="full">
                            <label for="description">وصف الجهاز</label>
                            <textarea
                                id="description"
                                name="description"
                                placeholder="اكتبي وصفًا للجهاز واستخداماته"
                            ><?= escape($data['description']) ?></textarea>
                        </div>

                        <div class="full">
                            <label for="features">مميزات الجهاز</label>
                            <textarea
                                id="features"
                                name="features"
                                placeholder="اكتبي مميزات الجهاز"
                            ><?= escape($data['features']) ?></textarea>
                        </div>

                    </div>
                </section>

                <section class="card">
                    <h2>صورة الجهاز — اختيارية</h2>

                    <label for="image">اختاري صورة الجهاز</label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <p class="hint">
                        صورة واحدة بصيغة JPG أو PNG أو WEBP،
                        بحد أقصى 2 ميجابايت.
                    </p>
                </section>

                <div class="actions">
                    <button
                        type="submit"
                        class="save-button"
                        <?= !$categoriesLoaded || empty($categories)
                            ? 'disabled' : '' ?>
                    >
                        حفظ الجهاز
                    </button>

                    <a href="products.php" class="cancel-link">
                        إلغاء والعودة
                    </a>
                </div>

            </form>
        </div>
    </main>
</div>
</body>
</html>'