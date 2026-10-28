<?php
session_start();
require __DIR__ . '/db.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.*, c.category_name
     FROM products p
     INNER JOIN categories c ON c.category_id = p.category_id
     WHERE p.product_id = ? AND p.status = 'available'
     LIMIT 1"
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('المنتج غير موجود أو غير متاح.');
}