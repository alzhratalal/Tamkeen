<?php
session_start();
require_once __DIR__ . '/db.php';
/*
|--------------------------------------------------------------------------
| البحث والتصنيفات
|--------------------------------------------------------------------------
| PHP + MySQL فقط، بدون JavaScript.
*/
$search = trim($_GET['q'] ?? '');
$categoryId = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);
if (!$categoryId || $categoryId < 1) {
    $categoryId = null;
}
/*
|--------------------------------------------------------------------------
| جلب المنتجات من قاعدة البيانات
|--------------------------------------------------------------------------
*/
$products = [];
$categories = [];
$cartCount = 0;
try {
    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.price,
            p.image,
            p.quantity,
            p.description,
            p.brand,
            p.category_id,
            c.category_name
        FROM products AS p
        INNER JOIN categories AS c
            ON p.category_id = c.category_id
        WHERE p.status = 'available'
          AND p.quantity > 0
    ";
    $params = [];
    // البحث باسم المنتج أو وصفه أو علامته التجارية
    if ($search !== '') {
        $sql .= "
            AND (
                p.product_name LIKE :search_name
                OR p.description LIKE :search_description
                OR p.brand LIKE :search_brand
            )
        ";
        $searchTerm = '%' . $search . '%';
        $params['search_name'] = $searchTerm;
        $params['search_description'] = $searchTerm;
        $params['search_brand'] = $searchTerm;
    }
    // فلترة المنتجات حسب التصنيف
    if ($categoryId !== null) {
        $sql .= " AND p.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }
    $sql .= "
        ORDER BY p.created_at DESC, p.product_id DESC
    ";
    $productStatement = $pdo->prepare($sql);
    $productStatement->execute($params);
    $products = $productStatement->fetchAll(PDO::FETCH_ASSOC);
    /*
    |--------------------------------------------------------------------------
    | جلب التصنيفات
    |--------------------------------------------------------------------------
    */
    $categoryStatement = $pdo->query("
        SELECT category_id, category_name
        FROM categories
        ORDER BY category_id ASC
    ");
    $categories = $categoryStatement->fetchAll(PDO::FETCH_ASSOC);
    /*
    |--------------------------------------------------------------------------
    | حساب عدد المنتجات الموجودة في السلة
    |--------------------------------------------------------------------------
    */
    if (!empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
        $cartStatement = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0)
            FROM cart AS c
            LEFT JOIN cart_items AS ci
                ON c.cart_id = ci.cart_id
            WHERE c.user_id = :user_id
        ");
        $cartStatement->execute([
            'user_id' => $userId
        ]);
        $cartCount = (int) $cartStatement->fetchColumn();
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit(
        'حدث خطأ أثناء تحميل الصفحة. تأكدي من إعداد قاعدة البيانات والجداول.'
    );
}
/*
|--------------------------------------------------------------------------
| أسماء التصنيفات
|--------------------------------------------------------------------------
*/
$categoryNames = [
    'Restaurants' => 'المطاعم',
    'Bakeries' => 'المخابز',
    'Cafes' => 'الكافيهات',
    'Refrigeration' => 'التبريد',
    // دعم الأسماء العربية إذا كانت مخزنة بالعربية
    'المطاعم' => 'المطاعم',
    'المخابز' => 'المخابز',
    'الكافيهات' => 'الكافيهات',
    'التبريد' => 'التبريد'
];
/*
|--------------------------------------------------------------------------
| أيقونات التصنيفات
|--------------------------------------------------------------------------
*/
$categoryIcons = [
    'Restaurants' => '♨',
    'Bakeries' => '♨',
    'Cafes' => '☕',
    'Refrigeration' => '❄',
    'المطاعم' => '♨',
    'المخابز' => '♨',
    'الكافيهات' => '☕',
    'التبريد' => '❄'
];
/*
|--------------------------------------------------------------------------
| حماية النصوص عند عرضها في HTML
|--------------------------------------------------------------------------
*/
function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
/*
|--------------------------------------------------------------------------
| صورة المنتج
|--------------------------------------------------------------------------
*/
function productImage($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return 'https://placehold.co/700x500?text=Tamkeen';
    }
    return $path;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta
        name="description"
        content="تمكين لبيع المعدات التجارية الجديدة للمطاعم والمخابز والكافيهات والتبريد."
    >
    <title>تمكين | المعدات التجارية</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- ======================================================
     رأس الصفحة
====================================================== -->
<header class="site-header" id="home">
    <div class="container header-inner">
        <a
            class="logo"
            href="index.php"
            aria-label="الرئيسية - تمكين"
        >
            Tamkeen<span class="logo-dot">.</span>
        </a>
        <!-- القائمة الرئيسية -->
        <nav class="main-nav" aria-label="التنقل الرئيسي">
            <a class="nav-link active" href="index.php">
                الرئيسية
            </a>
            <a class="nav-link" href="#categories">
                التصنيفات
            </a>
            <a class="nav-link" href="#about">
                من نحن
            </a>
            <a class="nav-link" href="#contact">
                تواصل معنا
            </a>
        </nav>
        <!-- البحث والحساب والسلة -->
        <div class="header-actions">
            <form
                class="search-form"
                action="index.php"
                method="GET"
                role="search"
            >
                <span class="search-icon" aria-hidden="true">
                    ⌕
                </span>
                <input
                    type="search"
                    name="q"
                    value="<?= h($search) ?>"
                    placeholder="ابحث عن المعدات..."
                    aria-label="ابحث عن المعدات"
                >
                <?php if ($categoryId !== null): ?>
                    <input
                        type="hidden"
                        name="category"
                        value="<?= (int) $categoryId ?>"
                    >
                <?php endif; ?>
                <button type="submit" class="search-submit">
                    بحث
                </button>
            </form>
            <!-- تسجيل الدخول -->
            <a
                class="icon-link"
                href="login.php"
                aria-label="تسجيل الدخول"
                title="تسجيل الدخول"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="8" r="3.5"></circle>
                    <path d="M5 20c.6-3.6 3.1-5.5 7-5.5s6.4 1.9 7 5.5"></path>
                </svg>
            </a>
            <!-- سلة التسوق -->
            <a
                class="icon-link cart-link"
                href="cart.php"
                aria-label="سلة التسوق"
                title="سلة التسوق"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L21 8H6"></path>
                    <circle cx="10" cy="20" r="1.3"></circle>
                    <circle cx="18" cy="20" r="1.3"></circle>
                </svg>
                <span class="cart-count">
                    <?= $cartCount ?>
                </span>
            </a>
        </div>
    </div>
</header>
<!-- ======================================================
     المحتوى الرئيسي
====================================================== -->
<main>
    <!-- ==================================================
         القسم التعريفي
    ================================================== -->
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-copy">
                <p class="eyebrow">
                    شريكك الموثوق
                </p>
                <h1>
                    اعثر على المعدات المناسبة لمشروعك
                </h1>
                <p class="hero-description">
                    اكتشف المعدات التجارية الجديدة للمطاعم
                    والمخابز والكافيهات وقطاع التبريد.
                </p>
            </div>
            <div class="hero-image-wrap">
                <img
                    src="3.png"
                    alt="معدات تجارية للمشاريع"
                >
            </div>
        </div>
    </section>
    <!-- ==================================================
         المنتجات
    ================================================== -->
    <section class="section container" id="products">
        <div class="section-heading">
            <h2>
                <?php if ($search !== ''): ?>
                    نتائج البحث
                <?php elseif ($categoryId !== null): ?>
                    منتجات التصنيف المحدد
                <?php else: ?>
                    المنتجات
                <?php endif; ?>
            </h2>
            <?php if ($search !== '' || $categoryId !== null): ?>
                <a
                    class="text-link"
                    href="index.php"
                >
                    عرض جميع المنتجات
                </a>
            <?php endif; ?>
        </div>
        <?php if (empty($products)): ?>
            <!-- لا توجد منتجات -->
            <p class="empty-state">
                <?php if ($search !== ''): ?>
                    لا توجد منتجات مطابقة لبحثك.
                    جرّبي البحث بكلمات أخرى.
                <?php else: ?>
                    لا توجد منتجات متاحة في هذا التصنيف حاليًا.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <!-- شبكة المنتجات -->
            <div class="product-grid product-grid-four">
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <!--
                            الضغط على صورة المنتج يفتح صفحة التفاصيل
                        -->
                        <a
                            class="product-image product-open"
                            href="product-details.php?id=<?= (int) $product['product_id'] ?>"
                            aria-label="عرض تفاصيل <?= h($product['product_name']) ?>"
                        >
                            <img
                                src="<?= h(productImage($product['image'])) ?>"
                                alt="<?= h($product['product_name']) ?>"
                                loading="lazy"
                            >
                        </a>
                        <div class="product-info">
                            <!-- اسم المنتج -->
                            <a
                                class="product-name-link"
                                href="product-details.php?id=<?= (int) $product['product_id'] ?>"
                            >
                                <h3>
                                    <?= h($product['product_name']) ?>
                                </h3>
                            </a>
                            <!-- سعر المنتج -->
                            <p class="price">
                                <?= number_format((float) $product['price'], 2) ?>
                                <span>ر.س</span>
                            </p>
                            <!-- إضافة إلى السلة -->
                            <form
                                action="add_to_cart.php"
                                method="POST"
                                class="add-cart-form"
                            >
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int) $product['product_id'] ?>"
                                >
                                <input
                                    type="hidden"
                                    name="quantity"
                                    value="1"
                                >
                                <button
                                    class="button product-button"
                                    type="submit"
                                >
                                    إضافة إلى السلة
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <!-- ==================================================
         التصنيفات
    ================================================== -->
    <section
        class="section section-soft"
        id="categories"
    >
        <div class="container">
            <div class="section-heading">
                <h2>
                    تسوّق حسب التصنيف
                </h2>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryKey = $category['category_name'];
                    $categoryName =
                        $categoryNames[$categoryKey] ?? $categoryKey;
                    $categoryIcon =
                        $categoryIcons[$categoryKey] ?? '•';
                    ?>
                    <a
                        class="category-card"
                        href="index.php?category=<?= (int) $category['category_id'] ?>#products"
                    >
                        <span class="category-copy">
                            <span
                                class="category-icon"
                                aria-hidden="true"
                            >
                                <?= h($categoryIcon) ?>
                            </span>
                            <strong>
                                <?= h($categoryName) ?>
                            </strong>
                        </span>
                        <span
                            class="category-arrow"
                            aria-hidden="true"
                        >
                            ←
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<!-- ======================================================
     تذييل الصفحة
====================================================== -->
<footer
    class="site-footer"
    id="contact"
>
    <div class="container footer-grid">
        <!-- معلومات تمكين -->
        <div
            class="footer-brand"
            id="about"
        >
            <a
                class="logo logo-footer"
                href="index.php"
            >
                Tamkeen<span class="logo-dot">.</span>
            </a>
            <p>
                وجهتك لشراء المعدات التجارية الجديدة للمشاريع.
            </p>
        </div>
        <!-- روابط سريعة -->
        <div class="footer-column">
            <h2>
                روابط سريعة
            </h2>
            <a href="index.php">
                الرئيسية
            </a>
            <a href="#categories">
                التصنيفات
            </a>
        </div>
        <!-- الدعم -->
        <div class="footer-column">
            <h2>
                الدعم
            </h2>
            <a href="#about">
                من نحن
            </a>
            <a href="#contact">
                تواصل معنا
            </a>
        </div>
        <!-- مواقع التواصل -->
        <div class="footer-column social-column">
            <h2>
                تابعنا
            </h2>
            <div class="social-links">
                <a
                    href="https://www.instagram.com/"
                    aria-label="Instagram"
                >
                    ig
                </a>
                <a
                    href="https://www.facebook.com/"
                    aria-label="Facebook"
                >
                    f
                </a>
                <a
                    href="https://www.youtube.com/"
                    aria-label="YouTube"
                >
                    ▶
                </a>
            </div>
            <p class="copyright">
                © 2026 تمكين. جميع الحقوق محفوظة.
            </p>
        </div>
    </div>
</footer>
</body>
</html>