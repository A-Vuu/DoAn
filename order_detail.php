<?php
// Trang chi tiết đơn hàng với quản lý (hủy, tải hóa đơn)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['need_login_message'] = 'Vui lòng đăng nhập để xem chi tiết đơn hàng.';
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = intval($_SESSION['user_id']);
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = '';

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// Lấy thông tin đơn hàng (schema: donhang)
$stmtOrder = $conn->prepare('SELECT * FROM donhang WHERE Id = ? AND IdNguoiDung = ? LIMIT 1');
$order = null;
if ($stmtOrder) {
    $stmtOrder->bind_param('ii', $orderId, $userId);
    $stmtOrder->execute();
    $order = $stmtOrder->get_result()->fetch_assoc();
    $stmtOrder->close();
}

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Xử lý hủy đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    // Chỉ cho hủy nếu trạng thái là ChoXacNhan
    if ($order['TrangThaiDonHang'] === 'ChoXacNhan') {
        $cancelReason = trim($_POST['cancel_reason'] ?? '');
        if ($cancelReason === '') {
            $errors[] = 'Vui lòng nhập lý do hủy đơn.';
        } else {
            $stmt = $conn->prepare('UPDATE donhang SET TrangThaiDonHang = ?, LyDoHuy = ? WHERE Id = ?');
            if ($stmt) {
                $stmt->bind_param('ssi', $newStatus, $cancelReason, $orderId);
                $newStatus = 'DaHuy';
                if ($stmt->execute()) {
                    $success = 'Đã hủy đơn hàng thành công.';
                    $order['TrangThaiDonHang'] = 'DaHuy';
                    $order['LyDoHuy'] = $cancelReason;
                } else {
                    $errors[] = 'Không thể hủy đơn hàng. Vui lòng thử lại.';
                }
                $stmt->close();
            }
        }
    } else {
        $errors[] = 'Chỉ có thể hủy các đơn hàng chờ xác nhận.';
    }
}

// Lấy chi tiết sản phẩm trong đơn hàng
$items = [];
$stmtItems = $conn->prepare('SELECT * FROM chitietdonhang WHERE IdDonHang = ?');
if ($stmtItems) {
    $stmtItems->bind_param('i', $orderId);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    while ($it = $resItems->fetch_assoc()) {
        $items[] = $it;
    }
    $stmtItems->close();
}

// Helper render badge
function render_status_badge($status) {
    $map = [
        'ChoXacNhan'  => ['text' => 'Chờ xác nhận', 'class' => 'badge-soft-warning'],
        'DaXacNhan'   => ['text' => 'Đã xác nhận', 'class' => 'badge-soft-info'],
        'DangGiao'    => ['text' => 'Đang giao', 'class' => 'badge-soft-primary'],
        'HoanThanh'   => ['text' => 'Hoàn thành', 'class' => 'badge-soft-success'],
        'DaHuy'       => ['text' => 'Đã hủy', 'class' => 'badge-soft-danger'],
    ];
    $info = $map[$status] ?? ['text' => $status, 'class' => 'badge-soft-secondary'];
    return '<span class="badge ' . $info['class'] . '">' . $info['text'] . '</span>';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="orders.php" class="text-decoration-none text-muted small">← Quay lại</a>
            <h3 class="fw-bold mb-0">Chi tiết đơn hàng</h3>
        </div>
        <div>
            <?php echo render_status_badge($order['TrangThaiDonHang']); ?>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success mb-3"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Thông tin đơn hàng -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Thông tin đơn hàng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Mã đơn hàng</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($order['MaDonHang']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Ngày đặt hàng</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($order['NgayDatHang'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Trạng thái đơn hàng</div>
                            <div><?php echo render_status_badge($order['TrangThaiDonHang']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Trạng thái thanh toán</div>
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($order['TrangThaiThanhToan'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Phương thức thanh toán</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($order['PhuongThucThanhToan'] ?? 'COD'); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Ghi chú</div>
                            <div class="small"><?php echo htmlspecialchars($order['GhiChu'] ?? '(không có)'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Thông tin giao hàng -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Thông tin giao hàng</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Người nhận</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($order['TenNguoiNhan']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Email</div>
                            <div><?php echo htmlspecialchars($order['EmailNguoiNhan'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="text-muted small mb-1">Số điện thoại</div>
                            <div class="fw-bold"><?php echo htmlspecialchars($order['SoDienThoai']); ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="text-muted small mb-1">Địa chỉ giao hàng</div>
                            <div><?php echo htmlspecialchars($order['DiaChiGiaoHang']); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danh sách sản phẩm -->
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Chi tiết sản phẩm</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($items)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th class="text-center">SL</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?php echo htmlspecialchars($it['TenSanPham']); ?></div>
                                                <?php if (!empty($it['MauSac']) || !empty($it['KichThuoc'])): ?>
                                                    <div class="text-muted small">
                                                        <?php if (!empty($it['MauSac'])): ?>Màu: <?php echo htmlspecialchars($it['MauSac']); ?><?php endif; ?>
                                                        <?php if (!empty($it['KichThuoc'])): ?> | Size: <?php echo htmlspecialchars($it['KichThuoc']); ?><?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo intval($it['SoLuong']); ?></td>
                                            <td class="text-end"><?php echo number_format(floatval($it['DonGia']), 0, ',', '.'); ?>đ</td>
                                            <td class="text-end fw-bold"><?php echo number_format(floatval($it['ThanhTien'] ?? ($it['DonGia'] * $it['SoLuong'])), 0, ',', '.'); ?>đ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Không có sản phẩm nào trong đơn hàng.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Tóm tắt thanh toán -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Tóm tắt thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính</span>
                        <strong><?php echo number_format(floatval($order['TongTienHang'] ?? 0), 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển</span>
                        <strong><?php echo number_format(floatval($order['PhiVanChuyen'] ?? 0), 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Mã giảm giá</span>
                        <strong>-<?php echo number_format(floatval($order['GiamGia'] ?? 0), 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                        <span class="fw-bold">Tổng thanh toán</span>
                        <span class="h5 mb-0 text-danger fw-bold"><?php echo number_format(floatval($order['TongThanhToan'] ?? 0), 0, ',', '.'); ?>đ</span>
                    </div>
                </div>
            </div>

            <!-- Hành động quản lý -->
            <?php if ($order['TrangThaiDonHang'] === 'ChoXacNhan'): ?>
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-warning">Hủy đơn hàng</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">Bạn có thể hủy đơn hàng này nếu nó vẫn chờ xác nhận.</p>
                        <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?');">
                            <div class="mb-3">
                                <label class="form-label small">Lý do hủy đơn</label>
                                <textarea name="cancel_reason" class="form-control" rows="3" placeholder="Vui lòng cho biết lý do hủy đơn hàng..." required></textarea>
                            </div>
                            <input type="hidden" name="cancel_order" value="1">
                            <button type="submit" class="btn btn-outline-danger w-100">Hủy đơn hàng</button>
                        </form>
                    </div>
                </div>
            <?php elseif ($order['TrangThaiDonHang'] === 'DaHuy'): ?>
                <div class="card shadow-sm border-danger">
                    <div class="card-header bg-danger bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-danger">Lý do hủy</h5>
                    </div>
                    <div class="card-body">
                        <p class="small"><?php echo htmlspecialchars($order['LyDoHuy'] ?? 'Chưa cập nhật'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info bg-opacity-10 border-0 py-3">
                        <h5 class="mb-0 text-info">Thông tin</h5>
                    </div>
                    <div class="card-body small">
                        <p class="mb-2">Đơn hàng của bạn đang được xử lý.</p>
                        <p class="mb-0">Liên hệ với chúng tôi nếu cần hỗ trợ.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
