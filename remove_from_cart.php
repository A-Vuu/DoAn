<?php
// Xóa sản phẩm khỏi giỏ hàng (session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$key = $_POST['key'] ?? '';
if ($key && isset($_SESSION['cart'][$key])) {
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart_success'] = 'Đã xóa sản phẩm khỏi giỏ hàng.';
}

header('Location: cart.php');
exit;
?>
