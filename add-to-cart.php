<?php
session_start();
require __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    // Keep the product ID so the user can return after logging in.
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $_SESSION['after_login_product_id'] = $productId ?: null;
    $_SESSION['after_login_quantity'] = max(1, (int)$quantity);
    header('Location: login.php?next=cart');
    exit;
}

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$productId || !$quantity || $quantity < 1) {
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $productStmt = $pdo->prepare(
        "SELECT product_id, quantity, status
         FROM products
         WHERE product_id = ?
         FOR UPDATE"
    );
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch();

    if (!$product || $product['status'] !== 'available' || (int)$product['quantity'] < $quantity) {
        $pdo->rollBack();
        header('Location: index.php?cart_error=unavailable');
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    $cartStmt = $pdo->prepare("SELECT cart_id FROM cart WHERE user_id = ?");
    $cartStmt->execute([$userId]);
    $cartId = $cartStmt->fetchColumn();

    if (!$cartId) {
        $createCart = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
        $createCart->execute([$userId]);
        $cartId = (int)$pdo->lastInsertId();
    }

    $itemStmt = $pdo->prepare(
        "SELECT cart_item_id, quantity FROM cart_items
         WHERE cart_id = ? AND product_id = ?"
    );
    $itemStmt->execute([$cartId, $productId]);
    $existing = $itemStmt->fetch();

    if ($existing) {
        $newQuantity = (int)$existing['quantity'] + $quantity;
        if ($newQuantity > (int)$product['quantity']) {
            $pdo->rollBack();
            header('Location: index.php?cart_error=quantity');
            exit;
        }
        $update = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
        $update->execute([$newQuantity, $existing['cart_item_id']]);
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)"
        );
        $insert->execute([$cartId, $productId, $quantity]);
    }

    $pdo->commit();
    header('Location: cart.php?added=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('تعذر إضافة المنتج إلى السلة. تحققي من إعدادات قاعدة البيانات.');
}
