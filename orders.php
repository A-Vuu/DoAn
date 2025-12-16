<?php
// Lịch sử đơn hàng của người dùng
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['need_login_message'] = 'Vui lòng đăng nhập để xem đơn hàng.';
    header('Location: login.php?redirect=orders.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$orders = [];
$successMsg = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']);

// Lấy filter status từ GET parameter
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$validStatuses = ['all', 'ChoXacNhan', 'DaXacNhan', 'DangGiao', 'HoanThanh', 'DaHuy'];
if (!in_array($filterStatus, $validStatuses)) {
    $filterStatus = 'all';
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

// Lấy đơn hàng theo schema donhang (dùng prepared để tránh lỗi khi userId trống)
$stmtOrders = $conn->prepare('SELECT Id, MaDonHang, TongThanhToan, TrangThaiDonHang, TrangThaiThanhToan, NgayDatHang, TenNguoiNhan, SoDienThoai, DiaChiGiaoHang, PhuongThucThanhToan FROM donhang WHERE IdNguoiDung = ? ORDER BY NgayDatHang DESC');
if ($stmtOrders) {
    $stmtOrders->bind_param('i', $userId);
    $stmtOrders->execute();
    $res = $stmtOrders->get_result();
    while ($row = $res->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmtOrders->close();
}

// Lọc đơn hàng theo status nếu cần
$filteredOrders = [];
if ($filterStatus === 'all') {
    $filteredOrders = $orders;
} else {
    foreach ($orders as $order) {
        if ($order['TrangThaiDonHang'] === $filterStatus) {
            $filteredOrders[] = $order;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <div class="text-muted small">Tài khoản của: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></div>
            <h3 class="fw-bold mb-0">Đơn hàng của tôi</h3>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
    </div>

    <?php if ($successMsg): ?>
        <div class="alert alert-success d-flex align-items-center"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2">
                <a href="orders.php?status=all" class="btn btn-sm <?php echo $filterStatus === 'all' ? 'btn-dark' : 'btn-outline-dark'; ?>">
                    <i class="fas fa-list me-1"></i>Tất cả đơn
                </a>
                <a href="orders.php?status=ChoXacNhan" class="btn btn-sm <?php echo $filterStatus === 'ChoXacNhan' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                    <i class="fas fa-clock me-1"></i>Chờ xác nhận
                </a>
                <a href="orders.php?status=DaXacNhan" class="btn btn-sm <?php echo $filterStatus === 'DaXacNhan' ? 'btn-info' : 'btn-outline-info'; ?>">
                    <i class="fas fa-check me-1"></i>Đã xác nhận
                </a>
                <a href="orders.php?status=DangGiao" class="btn btn-sm <?php echo $filterStatus === 'DangGiao' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <i class="fas fa-truck me-1"></i>Đang giao
                </a>
                <a href="orders.php?status=HoanThanh" class="btn btn-sm <?php echo $filterStatus === 'HoanThanh' ? 'btn-success' : 'btn-outline-success'; ?>">
                    <i class="fas fa-check-circle me-1"></i>Đã nhận hàng
                </a>
                <a href="orders.php?status=DaHuy" class="btn btn-sm <?php echo $filterStatus === 'DaHuy' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                    <i class="fas fa-times me-1"></i>Đã hủy
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <div class="alert alert-light border">Bạn chưa có đơn hàng nào. <a href="index.php">Mua ngay</a></div>
    <?php elseif (empty($filteredOrders)): ?>
        <div class="alert alert-light border">Không có đơn hàng ở trạng thái này. <a href="orders.php">Xem tất cả</a></div>
    <?php else: ?>
        <div class="vstack gap-3">
            <?php foreach ($filteredOrders as $o): ?>
                <?php
                    $orderId = intval($o['Id']);
                    $items = [];
                    $stmtItems = $conn->prepare('SELECT TenSanPham, SoLuong, DonGia, MauSac, KichThuoc FROM chitietdonhang WHERE IdDonHang = ?');
                    if ($stmtItems) {
                        $stmtItems->bind_param('i', $orderId);
                        $stmtItems->execute();
                        $resItems = $stmtItems->get_result();
                        while ($it = $resItems->fetch_assoc()) { $items[] = $it; }
                        $stmtItems->close();
                    }
                ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <div class="text-muted small">Mã đơn: <?php echo htmlspecialchars($o['MaDonHang']); ?></div>
                                <div class="fw-bold">Ngày tạo: <?php echo htmlspecialchars($o['NgayDatHang'] ?? ''); ?></div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <?php echo render_status_badge($o['TrangThaiDonHang']); ?>
                                <span class="badge bg-light text-dark border">Thanh toán: <?php echo htmlspecialchars($o['TrangThaiThanhToan'] ?? ''); ?></span>
                                <span class="badge bg-light text-dark border">PTTT: <?php echo htmlspecialchars($o['PhuongThucThanhToan'] ?? ''); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $it): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="border rounded overflow-hidden me-3" style="width:60px; height:60px;">
                                        <img src="uploads/<?php echo htmlspecialchars($it['Anh'] ?? 'default.png'); ?>" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold small mb-1"><?php echo htmlspecialchars($it['TenSanPham']); ?></div>
                                        <div class="text-muted small">
                                            SL: <?php echo intval($it['SoLuong']); ?>
                                            <?php if (!empty($it['MauSac'])): ?> | Màu: <?php echo htmlspecialchars($it['MauSac']); ?><?php endif; ?>
                                            <?php if (!empty($it['KichThuoc'])): ?> | Size: <?php echo htmlspecialchars($it['KichThuoc']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="fw-bold text-danger small"><?php echo number_format($it['DonGia'], 0, ',', '.'); ?>đ</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div class="text-muted small">
                                Giao đến: <?php echo htmlspecialchars($o['TenNguoiNhan']); ?> - <?php echo htmlspecialchars($o['SoDienThoai']); ?> | <?php echo htmlspecialchars($o['DiaChiGiaoHang']); ?>
                            </div>
                            <div class="text-end">
                                <div class="small text-muted">Tổng tiền</div>
                                <div class="h5 text-danger mb-0"><?php echo number_format(floatval($o['TongThanhToan'] ?? 0), 0, ',', '.'); ?>đ</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3 pt-3 border-top">
                            <a href="order_detail.php?id=<?php echo intval($o['Id']); ?>" class="btn btn-sm btn-outline-dark flex-fill">Xem chi tiết</a>
                            <?php if ($o['TrangThaiDonHang'] === 'ChoXacNhan'): ?>
                                <a href="order_detail.php?id=<?php echo intval($o['Id']); ?>" class="btn btn-sm btn-outline-danger flex-fill">Hủy đơn</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
