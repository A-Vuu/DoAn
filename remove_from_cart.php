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
// If logged in and key indicates DB item (prefix 'db'), remove from DB
if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/cart_functions.php';
    if ($key) {
        if (strpos($key, 'db') === 0) {
            $cartItemId = intval(substr($key, 2));
            if ($cartItemId > 0) {
                remove_cart_item_db(intval($_SESSION['user_id']), $cartItemId);
                $_SESSION['cart_success'] = 'Đã xóa sản phẩm khỏi giỏ hàng.';
            }
        }
    }
    header('Location: cart.php');
    exit;
}

// Session fallback
if ($key && isset($_SESSION['cart'][$key])) {
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart_success'] = 'Đã xóa sản phẩm khỏi giỏ hàng.';
}

header('Location: cart.php');
exit;
?>
