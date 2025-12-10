<?php
// Thêm sản phẩm vào giỏ hàng (session)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$productId = intval($_POST['product_id'] ?? 0);
$qty = max(1, intval($_POST['quantity'] ?? 1));

if ($productId <= 0) {
    $_SESSION['cart_error'] = 'Sản phẩm không hợp lệ.';
    header('Location: index.php');
    exit;
}

// Yêu cầu đăng nhập trước khi thêm vào giỏ hàng
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    // Lưu thông báo tạm thời để hiển thị sau khi chuyển tới trang đăng nhập
    $_SESSION['need_login_message'] = 'Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng.';
    // Chuyển hướng người dùng về trang sản phẩm (nếu biết) hoặc trang trước đó
    $redirectTo = $productId > 0 ? "product.php?id={$productId}" : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php');
    header('Location: login.php?redirect=' . urlencode($redirectTo));
    exit;
}

// Lấy thông tin sản phẩm từ DB
$res = mysqli_query($conn, "SELECT Id, TenSanPham, GiaGoc, GiaKhuyenMai, SoLuongTonKho FROM SanPham WHERE Id = $productId AND HienThi = 1 LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['cart_error'] = 'Không tìm thấy sản phẩm.';
    header('Location: index.php');
    exit;
}
$p = mysqli_fetch_assoc($res);

// Kiểm tra tồn kho
if ($p['SoLuongTonKho'] <= 0) {
    $_SESSION['cart_error'] = 'Sản phẩm này hiện không có sẵn.';
    header('Location: index.php');
    exit;
}

$price = $p['GiaKhuyenMai'] ? floatval($p['GiaKhuyenMai']) : floatval($p['GiaGoc']);

// Ảnh chính (nếu có)
$imgRes = mysqli_query($conn, "SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = $productId AND LaAnhChinh = 1 LIMIT 1");
$img = '';
if ($imgRes && mysqli_num_rows($imgRes) > 0) {
    $img = mysqli_fetch_assoc($imgRes)['DuongDanAnh'];
}

// Khởi tạo giỏ
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
// Nếu user chưa đăng nhập: thêm vào giỏ hàng session tạm thời, sau đó yêu cầu đăng nhập
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    // Khóa duy nhất cho item (session)
    $key = 'p' . $productId;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'key' => $key,
            'product_id' => $productId,
            'name' => $p['TenSanPham'],
            'price' => $price,
            'qty' => $qty,
            'image' => $img
        ];
    }

    // Thông báo tạm thời
    $_SESSION['cart_success'] = 'Đã thêm "' . $p['TenSanPham'] . '" vào giỏ hàng (tạm). Vui lòng đăng nhập để lưu.';
    $_SESSION['need_login_message'] = 'Bạn đã thêm sản phẩm vào giỏ. Vui lòng đăng nhập để lưu giỏ hàng.';

    // Chuyển hướng về trang đã yêu cầu (nếu biết) để người dùng quay lại sau khi đăng nhập
    $redirectTo = $productId > 0 ? "product.php?id={$productId}" : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php');
    header('Location: login.php?redirect=' . urlencode($redirectTo));
    exit;
}

// Nếu user đã đăng nhập: lưu trực tiếp vào DB
require_once __DIR__ . '/includes/cart_functions.php';
$userId = intval($_SESSION['user_id']);
add_or_update_cart_item_db($userId, $productId, $qty, $price, null);
$_SESSION['cart_success'] = 'Đã thêm "' . $p['TenSanPham'] . '" vào giỏ hàng.';
header('Location: cart.php');
exit;
?>
