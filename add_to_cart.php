<?php
// FILE: uploads/add_to_cart.php
session_start();
require_once __DIR__ . '/config.php';

// 1. CHỈ NHẬN METHOD POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 2. LẤY DỮ LIỆU TỪ FORM
$productId = intval($_POST['product_id'] ?? 0);
$qty       = max(1, intval($_POST['quantity'] ?? 1));
$colorId   = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
$sizeId    = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;

// --- CHECK LOGIN ---
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    $_SESSION['need_login_message'] = 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ.';
    $backLink = ($productId > 0) ? "product_detail.php?id=$productId" : "index.php";
    header('Location: login.php?redirect=' . urlencode($backLink));
    exit();
}

// 3. XỬ LÝ KHI ĐÃ ĐĂNG NHẬP
$userId = intval($_SESSION['user_id']);

if ($productId <= 0) {
    header('Location: index.php'); exit;
}

// Lấy thông tin sản phẩm bằng prepared statement
$p = null;
if ($stmt = $conn->prepare("SELECT Id, TenSanPham, GiaGoc, GiaKhuyenMai, SoLuongTonKho FROM SanPham WHERE Id = ? AND HienThi = 1 LIMIT 1")) {
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $res = $stmt->get_result();
    $p = $res->fetch_assoc();
    $stmt->close();
}

if (!$p || $p['SoLuongTonKho'] <= 0) {
    $_SESSION['cart_error'] = 'Sản phẩm không khả dụng.';
    header("Location: product_detail.php?id=$productId");
    exit;
}

// Tính giá
$price = ($p['GiaKhuyenMai'] > 0 && $p['GiaKhuyenMai'] < $p['GiaGoc']) ? floatval($p['GiaKhuyenMai']) : floatval($p['GiaGoc']);

// Kiểm tra tồn kho theo biến thể (nếu có)
if ($colorId > 0 && $sizeId > 0) {
    if ($stmt = $conn->prepare('SELECT SoLuong FROM ChiTietSanPham WHERE IdSanPham = ? AND IdMauSac = ? AND IdKichThuoc = ? LIMIT 1')) {
        $stmt->bind_param('iii', $productId, $colorId, $sizeId);
        $stmt->execute();
        $rs = $stmt->get_result();
        if ($rowStock = $rs->fetch_assoc()) {
            if ($rowStock['SoLuong'] < $qty) {
                $_SESSION['cart_error'] = 'Số lượng tồn không đủ cho biến thể đã chọn.';
                header("Location: product_detail.php?id=$productId");
                exit;
            }
        } else {
            $_SESSION['cart_error'] = 'Biến thể không khả dụng.';
            header("Location: product_detail.php?id=$productId");
            exit;
        }
        $stmt->close();
    }
} else {
    // Kiểm tra tồn kho tổng của sản phẩm
    if ($p['SoLuongTonKho'] < $qty) {
        $_SESSION['cart_error'] = 'Số lượng tồn không đủ.';
        header("Location: product_detail.php?id=$productId");
        exit;
    }
}

// Lấy tên màu/size (để lưu vào ghi chú nếu cần)
$colorName = "";
if ($colorId > 0 && ($stmt = $conn->prepare("SELECT TenMau FROM mausac WHERE Id = ? LIMIT 1"))) {
    $stmt->bind_param('i', $colorId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $colorName = $row['TenMau']; }
    $stmt->close();
}
$sizeName = "";
if ($sizeId > 0 && ($stmt = $conn->prepare("SELECT TenKichThuoc FROM kichthuoc WHERE Id = ? LIMIT 1"))) {
    $stmt->bind_param('i', $sizeId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { $sizeName = $row['TenKichThuoc']; }
    $stmt->close();
}

// Lưu vào DB
require_once __DIR__ . '/includes/cart_functions.php';

// Tạo JSON options
$options = json_encode([
    'color_id' => $colorId, 
    'size_id' => $sizeId, 
    'color_name' => $colorName, 
    'size_name' => $sizeName
]);

// Gọi hàm thêm vào giỏ (Đảm bảo bạn đã sửa file cart_functions.php như hướng dẫn trước để tránh lỗi)
add_or_update_cart_item_db($userId, $productId, $qty, $price, $options);

$_SESSION['cart_success'] = 'Đã thêm "' . $p['TenSanPham'] . '" vào giỏ hàng.';

// --- [SỬA ĐOẠN NÀY] ---
// Luôn chuyển hướng về trang Giỏ hàng dù bấm nút nào
header('Location: cart.php');
exit;
?>