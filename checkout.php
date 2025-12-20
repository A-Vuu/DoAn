<?php
// Trang thanh toán / tạo đơn hàng
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// CSRF token cho trang checkout
if (empty($_SESSION['csrf_checkout_token'])) {
    $_SESSION['csrf_checkout_token'] = bin2hex(random_bytes(32));
}

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
    if ($stmt = $conn->prepare('SELECT Id FROM giohang WHERE IdNguoiDung = ? LIMIT 1')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $resCart = $stmt->get_result();
        if ($row = $resCart->fetch_assoc()) {
            $cartId = intval($row['Id']);
        }
        $stmt->close();
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
                WHERE ct.IdGioHang = ?";
        if ($stmtCart = $conn->prepare($sql)) {
            $stmtCart->bind_param('i', $cartId);
            $stmtCart->execute();
            $result = $stmtCart->get_result();
            while ($row = $result->fetch_assoc()) {
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
            $stmtCart->close();
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
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_checkout_token'], $token)) {
        $errors[] = 'Phiên thanh toán không hợp lệ, vui lòng thử lại.';
    }

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
        // Kiểm tra tồn kho và tạo đơn trong transaction
        $conn->begin_transaction();

        $orderCode = 'NW' . date('YmdHis') . rand(100, 999);
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }
        $shippingCost = 0;
        $discountAmount = 0;
        $appliedPromoCode = trim($_POST['promo_code'] ?? '');
        $appliedPromoId = null;

        // Kiểm tra và áp dụng mã giảm giá nếu có
        if (!empty($appliedPromoCode)) {
            date_default_timezone_set('Asia/Ho_Chi_Minh');
            $appliedPromoCode = strtoupper($appliedPromoCode);
            
            // Kiểm tra bảng claim tồn tại
            $checkClaimTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia_NguoiDung'");
            $claimTableExists = mysqli_num_rows($checkClaimTable) > 0;
            
            if ($claimTableExists) {
                // Verify user đã claim mã
                $promoStmt = $conn->prepare(
                    "SELECT m.* FROM MaGiamGia m
                     INNER JOIN MaGiamGia_NguoiDung mu ON m.Id = mu.IdMaGiamGia
                     WHERE m.MaCode = ? AND m.HienThi = 1 AND mu.IdNguoiDung = ?
                     AND (m.NgayBatDau IS NULL OR m.NgayBatDau <= NOW())
                     AND (m.NgayKetThuc IS NULL OR m.NgayKetThuc >= NOW())"
                );
                if ($promoStmt) {
                    $promoStmt->bind_param('si', $appliedPromoCode, $userId);
                    $promoStmt->execute();
                    $promoResult = $promoStmt->get_result();
                }
            } else {
                // Không có bảng claim - cho phép dùng trực tiếp
                $promoStmt = $conn->prepare(
                    "SELECT * FROM MaGiamGia 
                     WHERE MaCode = ? AND HienThi = 1
                     AND (NgayBatDau IS NULL OR NgayBatDau <= NOW())
                     AND (NgayKetThuc IS NULL OR NgayKetThuc >= NOW())"
                );
                if ($promoStmt) {
                    $promoStmt->bind_param('s', $appliedPromoCode);
                    $promoStmt->execute();
                    $promoResult = $promoStmt->get_result();
                }
            }
            
            if ($promoStmt && $promoResult->num_rows > 0) {
                $promo = $promoResult->fetch_assoc();
                
                // Kiểm tra điều kiện
                if ($promo['SoLuongMa'] && $promo['DaSuDung'] >= $promo['SoLuongMa']) {
                    $errors[] = 'Mã giảm giá đã hết lượt dùng';
                } elseif ($promo['GiaTriDonHangToiThieu'] > 0 && $subtotal < $promo['GiaTriDonHangToiThieu']) {
                    $errors[] = 'Đơn hàng phải từ ' . number_format($promo['GiaTriDonHangToiThieu']) . 'đ';
                } else {
                    // Tính toán giảm giá
                    $isPercent = in_array($promo['LoaiGiam'], ['PhanTram', '%'], true);
                    if (!$isPercent) {
                        $discountAmount = min($promo['GiaTriGiam'], $subtotal);
                    } else {
                        $discountAmount = ($subtotal * $promo['GiaTriGiam']) / 100;
                        if ($promo['GiamToiDa'] > 0) {
                            $discountAmount = min($discountAmount, $promo['GiamToiDa']);
                        }
                    }
                    $appliedPromoId = $promo['Id'];
                }
            } else {
                $errors[] = $claimTableExists ? 'Mã không hợp lệ hoặc bạn chưa nhận mã này' : 'Mã giảm giá không tồn tại hoặc đã hết hạn';
            }
            
            if ($promoStmt) {
                $promoStmt->close();
            }
        }

        $grandTotal = $subtotal + $shippingCost - $discountAmount;

        // Kiểm tồn kho từng dòng
        foreach ($cart as $item) {
            $qtyNeed = (int)$item['qty'];
            $pid = (int)$item['product_id'];
            $vid = $item['variant_id'] ?? null;

            if ($vid) {
                $stmtStock = $conn->prepare('SELECT SoLuong FROM chitietsanpham WHERE Id = ? FOR UPDATE');
                $stmtStock->bind_param('i', $vid);
                $stmtStock->execute();
                $rs = $stmtStock->get_result();
                $rowStock = $rs->fetch_assoc();
                $stmtStock->close();
                if (!$rowStock || $rowStock['SoLuong'] < $qtyNeed) {
                    $errors[] = 'Sản phẩm ' . htmlspecialchars($item['name']) . ' không đủ tồn kho.';
                    break;
                }
                $newQty = $rowStock['SoLuong'] - $qtyNeed;
                $stmtUpdate = $conn->prepare('UPDATE chitietsanpham SET SoLuong = ? WHERE Id = ?');
                $stmtUpdate->bind_param('ii', $newQty, $vid);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                $stmtStock = $conn->prepare('SELECT SoLuongTonKho FROM sanpham WHERE Id = ? FOR UPDATE');
                $stmtStock->bind_param('i', $pid);
                $stmtStock->execute();
                $rs = $stmtStock->get_result();
                $rowStock = $rs->fetch_assoc();
                $stmtStock->close();
                if (!$rowStock || $rowStock['SoLuongTonKho'] < $qtyNeed) {
                    $errors[] = 'Sản phẩm ' . htmlspecialchars($item['name']) . ' không đủ tồn kho.';
                    break;
                }
                $newQty = $rowStock['SoLuongTonKho'] - $qtyNeed;
                $stmtUpdate = $conn->prepare('UPDATE sanpham SET SoLuongTonKho = ? WHERE Id = ?');
                $stmtUpdate->bind_param('ii', $newQty, $pid);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            }
        }

        if (!empty($errors)) {
            $conn->rollback();
        }

        if (empty($errors)) {
            // Lưu đơn hàng theo schema donhang
            $stmt = $conn->prepare("INSERT INTO donhang (MaDonHang, IdNguoiDung, TenNguoiNhan, EmailNguoiNhan, SoDienThoai, DiaChiGiaoHang, GhiChu, TongTienHang, PhiVanChuyen, GiamGia, TongThanhToan, PhuongThucThanhToan, TrangThaiThanhToan, TrangThaiDonHang, MaGiamGia) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ChuaThanhToan', 'ChoXacNhan', ?)");
            if ($stmt) {
                $appliedCode = $appliedPromoId ? $appliedPromoCode : null;
                $stmt->bind_param('sisssssddddss', $orderCode, $userId, $name, $email, $phone, $address, $note, $subtotal, $shippingCost, $discountAmount, $grandTotal, $payment, $appliedCode);
                if ($stmt->execute()) {
                    $orderId = $stmt->insert_id;
                    $stmt->close();

                    // Cập nhật số lượt dùng mã giảm giá
                    if ($appliedPromoId) {
                        $updatePromo = $conn->prepare("UPDATE MaGiamGia SET DaSuDung = DaSuDung + 1 WHERE Id = ?");
                        if ($updatePromo) {
                            $updatePromo->bind_param('i', $appliedPromoId);
                            $updatePromo->execute();
                            $updatePromo->close();
                        }
                    }

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

                    $conn->commit();

                    // Xóa giỏ hàng session & DB (schema: giohang/chitietgiohang)
                    $_SESSION['cart'] = [];
                    if ($stmtClear = $conn->prepare('SELECT Id FROM giohang WHERE IdNguoiDung = ? LIMIT 1')) {
                        $stmtClear->bind_param('i', $userId);
                        $stmtClear->execute();
                        $resCart = $stmtClear->get_result();
                        if ($row = $resCart->fetch_assoc()) {
                            $cid = intval($row['Id']);
                            if ($delDetail = $conn->prepare('DELETE FROM chitietgiohang WHERE IdGioHang = ?')) {
                                $delDetail->bind_param('i', $cid);
                                $delDetail->execute();
                                $delDetail->close();
                            }
                            if ($delCart = $conn->prepare('DELETE FROM giohang WHERE Id = ?')) {
                                $delCart->bind_param('i', $cid);
                                $delCart->execute();
                                $delCart->close();
                            }
                        }
                        $stmtClear->close();
                    }

                    $_SESSION['order_success'] = 'Đặt hàng thành công! Mã đơn ' . $orderCode . ' đang chờ xác nhận.';
                    header('Location: orders.php');
                    exit;
                } else {
                    $errors[] = 'Không thể tạo đơn hàng. Vui lòng thử lại.';
                    $stmt->close();
                    $conn->rollback();
                }
            } else {
                $errors[] = 'Không thể chuẩn bị truy vấn đặt hàng.';
                $conn->rollback();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
$subtotal = 0;
foreach ($cart as $it) { $subtotal += ($it['price'] * $it['qty']); }
$shippingCost = 0;
$discountAmount = 0;
$grandTotal = $subtotal + $shippingCost - $discountAmount;

// Lấy danh sách mã khuyến mãi hiện hành
$allPromos = [];
date_default_timezone_set('Asia/Ho_Chi_Minh');
$currentDate = date('Y-m-d H:i:s');

// Kiểm tra bảng MaGiamGia_NguoiDung tồn tại
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia_NguoiDung'");
$tableExists = mysqli_num_rows($checkTable) > 0;

// Nếu user đã claim mã, chỉ lấy các mã đã claim
if (isset($_SESSION['user_id']) && $tableExists) {
    $userId = (int)$_SESSION['user_id'];
    $stmtPromo = $conn->prepare(
        "SELECT m.Id, m.MaCode, m.TenChuongTrinh, m.LoaiGiam, m.GiaTriGiam, m.GiamToiDa, 
                m.GiaTriDonHangToiThieu, m.SoLuongMa, m.DaSuDung, m.NgayBatDau, m.NgayKetThuc
         FROM MaGiamGia m
         INNER JOIN MaGiamGia_NguoiDung mu ON m.Id = mu.IdMaGiamGia
         WHERE m.HienThi = 1 AND mu.IdNguoiDung = ?
         ORDER BY m.NgayBatDau ASC, m.Id DESC"
    );
    if ($stmtPromo) {
        $stmtPromo->bind_param('i', $userId);
        if ($stmtPromo->execute()) {
            $resultPromo = $stmtPromo->get_result();
            while ($row = $resultPromo->fetch_assoc()) {
                $allPromos[] = $row;
            }
        }
        $stmtPromo->close();
    }
}

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

                    <form method="post" class="row g-3" id="checkoutForm">
                        <input type="hidden" name="place_order" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_checkout_token']); ?>">
                        <input type="hidden" name="promo_code" id="promoCodeInput" value="">
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

                    <!-- Mã giảm giá -->
                    <div class="mb-3 pb-3 border-bottom">
                        <label class="form-label small fw-bold">Áp dụng Mã Giảm Giá</label>
                        
                        <!-- Danh sách mã khuyến mãi -->
                        <?php if (!empty($allPromos)): ?>
                        <style>
                        .promo-scroll { max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; padding: 8px; }
                        .promo-scroll::-webkit-scrollbar { width: 8px; }
                        .promo-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
                        </style>
                        <div class="mb-2">
                            <small class="text-muted">Kéo để xem và chọn mã (đã nhận).</small>
                            <div class="promo-scroll mt-2">
                                <?php foreach ($allPromos as $index => $p):
                                    $remaining = isset($p['SoLuongMa'], $p['DaSuDung']) ? max(0, (int)$p['SoLuongMa'] - (int)$p['DaSuDung']) : null;
                                    $startTs = !empty($p['NgayBatDau']) ? strtotime($p['NgayBatDau']) : null;
                                    $endTs   = !empty($p['NgayKetThuc']) ? strtotime($p['NgayKetThuc']) : null;
                                    $nowTs   = strtotime($currentDate);
                                    $isExpired  = ($endTs && $endTs < $nowTs);
                                    $isUpcoming = ($startTs && $startTs > $nowTs);
                                    $isDepleted = ($remaining !== null && $remaining <= 0);
                                    $canApply = !($isExpired || $isDepleted || $isUpcoming);

                                    if (!$canApply) continue;

                                    $isPercent = in_array($p['LoaiGiam'], ['PhanTram', '%'], true);
                                    $valueText = $isPercent
                                        ? (rtrim(rtrim(number_format($p['GiaTriGiam'], 2, '.', ''), '0'), '.') . '%')
                                        : number_format($p['GiaTriGiam']) . 'đ';
                                    $capText = ($isPercent && $p['GiamToiDa'] > 0)
                                        ? ' · Tối đa ' . number_format($p['GiamToiDa']) . 'đ'
                                        : '';
                                    $minText = ($p['GiaTriDonHangToiThieu'] > 0)
                                        ? 'Đơn tối thiểu ' . number_format($p['GiaTriDonHangToiThieu']) . 'đ'
                                        : 'Không yêu cầu đơn tối thiểu';
                                ?>
                                <div class="d-flex align-items-center justify-content-between gap-2 py-2<?php echo ($index > 0 ? ' border-top' : ''); ?>">
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="badge bg-primary">Mã: <?php echo htmlspecialchars($p['MaCode']); ?></span>
                                            <span class="small text-muted"><?php echo htmlspecialchars($p['TenChuongTrinh']); ?></span>
                                        </div>
                                        <div class="small fw-semibold mt-1"><?php echo $valueText . $capText; ?></div>
                                        <div class="small text-muted"><?php echo $minText; ?></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary promo-btn" onclick="togglePromo('<?php echo htmlspecialchars($p['MaCode']); ?>')">Chọn</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Input nhập mã -->
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" id="promoInput" class="form-control" placeholder="Nhập mã giảm giá..." value="">
                            <button type="button" class="btn btn-outline-secondary" onclick="applyPromo()">Áp dụng</button>
                        </div>
                        <div id="promoMessage" class="small"></div>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Giảm giá</span>
                        <strong id="discountDisplay">-<?php echo number_format($discountAmount, 0, ',', '.'); ?>đ</strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                        <span class="fw-bold">Tổng thanh toán</span>
                        <span class="h5 mb-0 text-danger fw-bold" id="totalDisplay"><?php echo number_format($grandTotal, 0, ',', '.'); ?>đ</span>
                    </div>

                    <script>
                    let selectedPromoCode = '';

                    function setActivePromoButton(code) {
                        document.querySelectorAll('.promo-btn').forEach(btn => {
                            if (btn.textContent.trim() === code) {
                                btn.classList.remove('btn-outline-primary');
                                btn.classList.add('btn-primary', 'text-white');
                            } else {
                                btn.classList.add('btn-outline-primary');
                                btn.classList.remove('btn-primary', 'text-white');
                            }
                        });
                    }

                    function resetPromoUI(messageText = '') {
                        const msgEl = document.getElementById('promoMessage');
                        msgEl.className = 'small text-muted';
                        msgEl.textContent = messageText;
                        document.getElementById('promoCodeInput').value = '';
                        document.getElementById('promoInput').value = '';
                        document.getElementById('discountDisplay').textContent = '-0đ';
                        document.getElementById('totalDisplay').textContent = new Intl.NumberFormat('vi-VN').format(<?php echo $subtotal; ?>) + 'đ';
                        selectedPromoCode = '';
                        setActivePromoButton('');
                    }

                    function togglePromo(code) {
                        if (selectedPromoCode === code) {
                            resetPromoUI('Đã bỏ chọn mã.');
                            return;
                        }
                        applyPromoByCode(code);
                    }

                    function applyPromo() {
                        const code = document.getElementById('promoInput').value.trim();
                        const subtotal = <?php echo $subtotal; ?>;
                        const msgEl = document.getElementById('promoMessage');

                        if (!code) {
                            msgEl.className = 'small text-danger';
                            msgEl.textContent = '⚠ Vui lòng nhập mã!';
                            return;
                        }
                        setActivePromoButton('');
                        selectedPromoCode = '';
                        applyPromoByCode(code);
                    }

                    function applyPromoByCode(code) {
                        const subtotal = <?php echo $subtotal; ?>;
                        const msgEl = document.getElementById('promoMessage');
                        
                        // Gọi API kiểm tra mã
                        const formData = new FormData();
                        formData.append('code', code);
                        formData.append('subtotal', subtotal);

                        fetch('check_promo_code.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                msgEl.className = 'small text-success';
                                msgEl.innerHTML = '✓ ' + data.message + '<br>' + data.discount_text + ' (từ mã: ' + code + ')';
                                
                                // Cập nhật hiển thị
                                document.getElementById('discountDisplay').textContent = '-' + new Intl.NumberFormat('vi-VN').format(data.discount) + 'đ';
                                document.getElementById('totalDisplay').textContent = new Intl.NumberFormat('vi-VN').format(data.final_total) + 'đ';
                                
                                // Lưu mã vào hidden input để submit form
                                document.getElementById('promoCodeInput').value = code;
                                document.getElementById('promoInput').value = code;
                                selectedPromoCode = code;
                                setActivePromoButton(code);
                            } else {
                                msgEl.className = 'small text-danger';
                                msgEl.textContent = '✗ ' + data.message;
                                resetPromoUI('');
                            }
                        })
                        .catch(err => {
                            msgEl.className = 'small text-danger';
                            msgEl.textContent = '✗ Lỗi kết nối. Vui lòng thử lại.';
                        });
                    }

                    // Enter key submit
                    document.getElementById('promoInput').addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') applyPromo();
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
