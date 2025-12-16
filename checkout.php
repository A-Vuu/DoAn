<?php
// Trang thanh toán / tạo đơn hàng
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Bảo vệ: yêu cầu đăng nhập
if (!isset($_SESSION['user_id'])) {
    $_SESSION['need_login_message'] = 'Vui lòng đăng nhập để thanh toán.';
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$errors = [];
$success = '';
$user = null;
$addressRow = null;

// Lấy thông tin người dùng hiện tại (theo schema novaWear1: bảng nguoidung)
$stmtUser = $conn->prepare('SELECT HoTen, Email, SoDienThoai FROM nguoidung WHERE Id = ? LIMIT 1');
if ($stmtUser) {
    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();
}

// Lấy địa chỉ giao hàng mặc định (schema: diachigiaohang)
$stmtAddr = $conn->prepare('SELECT TenNguoiNhan, SoDienThoai, DiaChi FROM diachigiaohang WHERE IdNguoiDung = ? AND LaDiaChiMacDinh = 1 ORDER BY Id DESC LIMIT 1');
if ($stmtAddr) {
    $stmtAddr->bind_param('i', $userId);
    $stmtAddr->execute();
    $addressRow = $stmtAddr->get_result()->fetch_assoc();
    $stmtAddr->close();
}

// Lấy giỏ hàng (ưu tiên DB nếu có)
function fetch_cart_for_checkout($conn, $userId) {
    $cart = [];

    // Lấy Id giỏ hàng từ DB (schema: giohang/chitietgiohang)
    $cartId = 0;
    $resCart = mysqli_query($conn, "SELECT Id FROM giohang WHERE IdNguoiDung = $userId LIMIT 1");
    if ($resCart && ($row = mysqli_fetch_assoc($resCart))) {
        $cartId = intval($row['Id']);
    }

    if ($cartId > 0) {
        $sql = "SELECT ct.*, sp.TenSanPham, sp.GiaGoc, sp.GiaKhuyenMai, asp.DuongDanAnh,
                       var.IdMauSac, var.IdKichThuoc, ms.TenMau, kt.TenKichThuoc
                FROM chitietgiohang ct
                JOIN sanpham sp ON ct.IdSanPham = sp.Id
                LEFT JOIN anhsanpham asp ON sp.Id = asp.IdSanPham AND asp.LaAnhChinh = 1
                LEFT JOIN chitietsanpham var ON ct.IdChiTietSanPham = var.Id
                LEFT JOIN mausac ms ON var.IdMauSac = ms.Id
                LEFT JOIN kichthuoc kt ON var.IdKichThuoc = kt.Id
                WHERE ct.IdGioHang = $cartId";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            $price = ($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']) ? $row['GiaKhuyenMai'] : $row['GiaGoc'];
            $cId = $row['IdMauSac'] ? intval($row['IdMauSac']) : 0;
            $sId = $row['IdKichThuoc'] ? intval($row['IdKichThuoc']) : 0;
            $key = $row['IdSanPham'] . '_' . $cId . '_' . $sId;
            $cart[$key] = [
                'key' => $key,
                'product_id' => intval($row['IdSanPham']),
                'name' => $row['TenSanPham'],
                'price' => floatval($price),
                'image' => $row['DuongDanAnh'] ? $row['DuongDanAnh'] : 'default.png',
                'qty' => intval($row['SoLuong']),
                'color_name' => $row['TenMau'] ?? '',
                'size_name' => $row['TenKichThuoc'] ?? '',
                'variant_id' => $row['IdChiTietSanPham'] ? intval($row['IdChiTietSanPham']) : null
            ];
        }
    } else {
        // Không có cart DB: thử lấy từ session
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $cart = $_SESSION['cart'];
        }
    }

    return $cart;
}

$cart = fetch_cart_for_checkout($conn, $userId);

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $payment = trim($_POST['payment'] ?? 'COD');

    if ($name === '') $errors[] = 'Vui lòng nhập họ tên người nhận.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if ($phone === '' || !preg_match('/^[0-9+().\-\s]{8,20}$/', $phone)) $errors[] = 'Số điện thoại không hợp lệ.';
    if ($address === '') $errors[] = 'Vui lòng nhập địa chỉ giao hàng.';
    if (empty($cart)) $errors[] = 'Giỏ hàng trống, không thể đặt hàng.';

    if (empty($errors)) {
        $orderCode = 'NW' . date('YmdHis') . rand(100, 999);
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }
        $shippingCost = 0;
        $discountAmount = 0;
        $grandTotal = $subtotal + $shippingCost - $discountAmount;

        // Lưu đơn hàng theo schema donhang
        $stmt = $conn->prepare("INSERT INTO donhang (MaDonHang, IdNguoiDung, TenNguoiNhan, EmailNguoiNhan, SoDienThoai, DiaChiGiaoHang, GhiChu, TongTienHang, PhiVanChuyen, GiamGia, TongThanhToan, PhuongThucThanhToan, TrangThaiThanhToan, TrangThaiDonHang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ChuaThanhToan', 'ChoXacNhan')");
        if ($stmt) {
            $stmt->bind_param('sisssssdddds', $orderCode, $userId, $name, $email, $phone, $address, $note, $subtotal, $shippingCost, $discountAmount, $grandTotal, $payment);
            if ($stmt->execute()) {
                $orderId = $stmt->insert_id;
                $stmt->close();

                // Lưu chi tiết đơn hàng (schema: chitietdonhang)
                $stmtDetail = $conn->prepare("INSERT INTO chitietdonhang (IdDonHang, IdSanPham, IdChiTietSanPham, TenSanPham, MauSac, KichThuoc, SKU, SoLuong, DonGia, ThanhTien) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmtDetail) {
                    foreach ($cart as $item) {
                        $pid = $item['product_id'];
                        $vid = $item['variant_id'] ?? null;
                        $pname = $item['name'];
                        $color = $item['color_name'];
                        $size = $item['size_name'];
                        $sku = '';
                        $qty = $item['qty'];
                        $price = $item['price'];
                        $lineTotal = $qty * $price;
                        $stmtDetail->bind_param('iiissssidd', $orderId, $pid, $vid, $pname, $color, $size, $sku, $qty, $price, $lineTotal);
                        $stmtDetail->execute();
                    }
                    $stmtDetail->close();
                }

                // Xóa giỏ hàng session & DB (schema: giohang/chitietgiohang)
                $_SESSION['cart'] = [];
                $resCart = mysqli_query($conn, "SELECT Id FROM giohang WHERE IdNguoiDung = $userId LIMIT 1");
                if ($resCart && ($row = mysqli_fetch_assoc($resCart))) {
                    $cid = intval($row['Id']);
                    mysqli_query($conn, "DELETE FROM chitietgiohang WHERE IdGioHang = $cid");
                    mysqli_query($conn, "DELETE FROM giohang WHERE Id = $cid");
                }

                $_SESSION['order_success'] = 'Đặt hàng thành công! Mã đơn ' . $orderCode . ' đang chờ xác nhận.';
                header('Location: orders.php');
                exit;
            } else {
                $errors[] = 'Không thể tạo đơn hàng. Vui lòng thử lại.';
                $stmt->close();
            }
        } else {
            $errors[] = 'Không thể chuẩn bị truy vấn đặt hàng.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
$subtotal = 0;
foreach ($cart as $it) { $subtotal += ($it['price'] * $it['qty']); }
$shippingCost = 0;
$discountAmount = 0;
$grandTotal = $subtotal + $shippingCost - $discountAmount;

$prefillName = $_POST['name'] ?? ($addressRow['TenNguoiNhan'] ?? ($user['HoTen'] ?? ($_SESSION['user_name'] ?? '')));
$prefillEmail = $_POST['email'] ?? ($user['Email'] ?? ($_SESSION['user_email'] ?? ''));
$prefillPhone = $_POST['phone'] ?? ($addressRow['SoDienThoai'] ?? ($user['SoDienThoai'] ?? ''));
$prefillAddress = $_POST['address'] ?? ($addressRow['DiaChi'] ?? ($_SESSION['user_address'] ?? ''));
$prefillNote = $_POST['note'] ?? '';
?>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="mb-0">Thông tin giao hàng</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3">
                        <input type="hidden" name="place_order" value="1">
                        <div class="col-md-6">
                            <label class="form-label">Họ tên người nhận</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($prefillName); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($prefillEmail); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($prefillPhone); ?>" placeholder="(+84) ..." required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Địa chỉ giao hàng</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" required><?php echo htmlspecialchars($prefillAddress); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ghi chú (tuỳ chọn)</label>
                            <textarea name="note" class="form-control" rows="2" placeholder="Ví dụ: Giao giờ hành chính, gọi trước khi giao..."><?php echo htmlspecialchars($prefillNote); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phương thức thanh toán</label>
                            <select name="payment" class="form-select">
                                <option value="COD">Thanh toán khi nhận hàng (COD)</option>
                                <option value="VNPAY">VNPay (sắp hỗ trợ)</option>
                                <option value="MOMO">Momo (sắp hỗ trợ)</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-between align-items-center pt-2">
                            <a href="cart.php" class="btn btn-link text-decoration-none px-0">← Quay lại giỏ hàng</a>
                            <button type="submit" class="btn btn-dark">Đặt hàng</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tóm tắt đơn hàng</h5>
                    <a href="cart.php" class="small text-decoration-none">Chỉnh sửa giỏ</a>
                </div>
                <div class="card-body">
                    <?php if (empty($cart)): ?>
                        <div class="alert alert-light border">Giỏ hàng trống.</div>
                    <?php else: ?>
                        <?php foreach ($cart as $it): ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="border rounded overflow-hidden me-3" style="width:56px; height:56px;">
                                    <img src="uploads/<?php echo htmlspecialchars($it['image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small mb-1"><?php echo htmlspecialchars($it['name']); ?></div>
                                    <div class="text-muted small">
                                        <?php if (!empty($it['color_name'])): ?>Màu: <?php echo htmlspecialchars($it['color_name']); ?><?php endif; ?>
                                        <?php if (!empty($it['size_name'])): ?> | Size: <?php echo htmlspecialchars($it['size_name']); ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-danger small"><?php echo number_format($it['price'], 0, ',', '.'); ?>đ</div>
                                    <div class="text-muted small">x<?php echo $it['qty']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính</span>
                        <strong><?php echo number_format($subtotal, 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển</span>
                        <strong><?php echo number_format($shippingCost, 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Mã giảm giá</span>
                        <strong>-<?php echo number_format($discountAmount, 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                        <span class="fw-bold">Tổng thanh toán</span>
                        <span class="h5 mb-0 text-danger fw-bold"><?php echo number_format($grandTotal, 0, ',', '.'); ?>đ</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
