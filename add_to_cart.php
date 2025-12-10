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

// Khóa duy nhất cho item
$key = 'p' . $productId;

if (isset($_SESSION['cart'][$key])) {
    // Tăng số lượng
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

$_SESSION['cart_success'] = 'Đã thêm "' . $p['TenSanPham'] . '" vào giỏ hàng.';
header('Location: cart.php');
exit;
?>
