<?php
session_start();
require_once '../../config.php';
if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($orderId <= 0) {
    header("Location: orders.php");
    exit();
}

$msg = '';
$error = '';

// Handle status update
if (isset($_POST['update_status'])) {
    $newStatus = mysqli_real_escape_string($conn, $_POST['new_status']);
    $adminNote = mysqli_real_escape_string($conn, $_POST['admin_note']);

    $updateSql = "UPDATE donhang SET TrangThaiDonHang = '$newStatus'";
    if (!empty($adminNote)) {
        $updateSql .= ", GhiChu = CONCAT(IFNULL(GhiChu, ''), '\n[Admin] $adminNote')";
    }
    $updateSql .= " WHERE Id = $orderId";

    if (mysqli_query($conn, $updateSql)) {
        $msg = "Cập nhật trạng thái thành công!";
    } else {
        $error = "Lỗi: " . mysqli_error($conn);
    }
}

// Fetch order
$sql = "SELECT d.*, n.HoTen, n.Email as UserEmail, n.SoDienThoai as UserPhone
        FROM donhang d
        LEFT JOIN NguoiDung n ON d.IdNguoiDung = n.Id
        WHERE d.Id = $orderId";
$result = mysqli_query($conn, $sql);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch items
$sqlItems = "SELECT ct.*, 
             (SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = ct.IdSanPham AND LaAnhChinh = 1 LIMIT 1) as AnhSanPham
             FROM chitietdonhang ct
             WHERE ct.IdDonHang = $orderId";
$itemsResult = mysqli_query($conn, $sqlItems);
$items = [];
while ($row = mysqli_fetch_assoc($itemsResult)) {
    $items[] = $row;
}

// Status map
$statusMap = [
    'ChoXacNhan' => ['label' => 'Chờ xác nhận', 'class' => 'warning', 'icon' => 'clock'],
    'DaXacNhan' => ['label' => 'Đã xác nhận', 'class' => 'info', 'icon' => 'check-circle'],
    'DangGiao' => ['label' => 'Đang giao', 'class' => 'primary', 'icon' => 'truck'],
    'HoanThanh' => ['label' => 'Hoàn thành', 'class' => 'success', 'icon' => 'check-double'],
    'DaHuy' => ['label' => 'Đã hủy', 'class' => 'danger', 'icon' => 'times-circle']
];

$currentStatus = $statusMap[$order['TrangThaiDonHang']] ?? ['label' => 'N/A', 'class' => 'secondary', 'icon' => 'question'];
$orderDate = date('d/m/Y H:i', strtotime($order['NgayDatHang']));

$availableNextStatuses = [];
switch ($order['TrangThaiDonHang']) {
    case 'ChoXacNhan':
        $availableNextStatuses = ['DaXacNhan', 'DaHuy'];
        break;
    case 'DaXacNhan':
        $availableNextStatuses = ['DangGiao', 'DaHuy'];
        break;
    case 'DangGiao':
        $availableNextStatuses = ['HoanThanh', 'DaHuy'];
        break;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết Đơn hàng #<?php echo $order['MaDonHang']; ?> - NovaWear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --bg: #f7f9fb;
            --border: #e5e7eb;
            --text: #0f172a;
            --muted: #6b7280;
            --radius: 6px;
            --pad: 10px;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-size: 13px;
        }
        .main-content { padding: 10px 14px; }
        .page-shell {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 6px 18px rgba(15,23,42,0.06);
            padding: 10px;
        }
        .top-bar h3 { font-size: 16px; margin: 0; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eef2ff;
            color: #312e81;
            padding: 4px 8px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        .layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 10px;
        }
        @media (max-width: 991px) {
            .layout { grid-template-columns: 1fr; }
        }
        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: var(--pad);
            box-shadow: 0 2px 8px rgba(15,23,42,0.04);
        }
        .panel + .panel { margin-top: 8px; }
        .panel h5 { font-size: 13px; margin: 0 0 6px 0; font-weight: 700; }
        .section-title { margin: 0 0 4px 0; font-weight: 700; font-size: 12px; color: var(--muted); }
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-chip.warning { background: #fef3c7; color: #92400e; }
        .status-chip.info { background: #e0f2fe; color: #075985; }
        .status-chip.primary { background: #e0e7ff; color: #312e81; }
        .status-chip.success { background: #dcfce7; color: #166534; }
        .status-chip.danger { background: #fee2e2; color: #991b1b; }
        .timeline {
            display: grid;
            gap: 6px;
            padding-left: 0;
            margin: 0;
        }
        .timeline-item {
            display: grid;
            grid-template-columns: 14px 1fr;
            gap: 8px;
            align-items: center;
        }
        .timeline-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: #fff;
        }
        .timeline-dot.active { background: #3b82f6; border-color: #3b82f6; }
        .timeline-dot.completed { background: #16a34a; border-color: #16a34a; }
        .timeline-label { font-weight: 700; font-size: 12px; }
        .timeline-time { font-size: 11px; color: var(--muted); }
        .info-pair { margin-bottom: 6px; }
        .info-label { font-size: 11px; color: var(--muted); text-transform: uppercase; margin: 0 0 2px 0; letter-spacing: 0; }
        .info-value { font-size: 13px; margin: 0; }
        .amount-summary {
            background: #f9fafb;
            border: 1px dashed var(--border);
            border-radius: 6px;
            padding: 8px;
            font-size: 12px;
        }
        .total-amount { font-size: 16px; font-weight: 800; color: #111827; }
        .product-list { display: grid; gap: 6px; }
        .product-row {
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px;
            display: grid;
            grid-template-columns: 64px 1fr 80px;
            gap: 8px;
            align-items: center;
            background: #fff;
        }
        .product-row img { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
        .product-title { font-weight: 700; margin: 0 0 2px 0; font-size: 13px; }
        .product-meta { font-size: 11px; color: var(--muted); display: flex; gap: 6px; flex-wrap: wrap; }
        .product-price { text-align: right; }
        .product-price .info-label { text-align: right; }
        .badge-light { background: #f3f4f6; color: #111827; border-radius: 10px; padding: 2px 6px; font-size: 11px; }
        .note-box { background: #f9fafb; border: 1px dashed var(--border); border-radius: 6px; padding: 8px; font-size: 12px; }
        .form-control, .form-select { font-size: 13px; padding: 6px 8px; }
        .btn { font-size: 13px; }
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .main-content { margin-left: 0; padding: 0; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-shell">
            <div class="d-flex justify-content-between align-items-center mb-3 top-bar no-print">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <a href="orders.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Quay lại
                        </a>
                        <span class="pill"><i class="fas fa-hashtag"></i><?php echo $order['MaDonHang']; ?></span>
                    </div>
                    <h3 class="mb-1">Chi tiết đơn hàng</h3>
                    <div class="text-muted small"><i class="far fa-clock me-1"></i><?php echo $orderDate; ?></div>
                </div>
                <div class="d-flex align-items-center gap-2 no-print">
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-print me-1"></i>In hóa đơn
                    </button>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="layout">
                <!-- Sidebar stack -->
                <div class="panel-stack">
                    <div class="panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-1"></i>Trạng thái</h5>
                            <span class="status-chip <?php echo $currentStatus['class']; ?>">
                                <i class="fas fa-<?php echo $currentStatus['icon']; ?>"></i><?php echo $currentStatus['label']; ?>
                            </span>
                        </div>
                        <div class="timeline">
                            <?php 
                            $statuses = ['ChoXacNhan', 'DaXacNhan', 'DangGiao', 'HoanThanh'];
                            $currentIdx = array_search($order['TrangThaiDonHang'], $statuses);
                            if ($order['TrangThaiDonHang'] == 'DaHuy') {
                                $currentIdx = -1;
                            }
                            foreach ($statuses as $idx => $status):
                                $sInfo = $statusMap[$status];
                                $isCompleted = ($idx < $currentIdx || ($idx == $currentIdx && $order['TrangThaiDonHang'] != 'DaHuy'));
                                $isActive = ($idx == $currentIdx);
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-dot <?php echo $isCompleted ? 'completed' : ($isActive ? 'active' : ''); ?>"></div>
                                <div>
                                    <div class="timeline-label <?php echo $isActive ? 'text-primary' : ($isCompleted ? 'text-success' : ''); ?>"><?php echo $sInfo['label']; ?></div>
                                    <div class="timeline-time">
                                        <?php if ($isActive || $isCompleted) { echo date('d/m/Y H:i', strtotime($order['NgayDatHang'])); } ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if ($order['TrangThaiDonHang'] == 'DaHuy'): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot" style="background:#ef4444; border-color:#ef4444;"></div>
                                <div class="timeline-label text-danger">Đơn hàng đã bị hủy</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="panel">
                        <h5><i class="fas fa-user me-1"></i>Khách hàng</h5>
                        <div class="info-pair">
                            <div class="info-label">Họ tên</div>
                            <p class="info-value mb-0"><?php echo htmlspecialchars($order['TenNguoiNhan']); ?></p>
                        </div>
                        <div class="info-pair">
                            <div class="info-label">Số điện thoại</div>
                            <p class="info-value mb-0"><a href="tel:<?php echo $order['SoDienThoai']; ?>" class="text-decoration-none"><i class="fas fa-phone me-1"></i><?php echo $order['SoDienThoai']; ?></a></p>
                        </div>
                        <div class="info-pair">
                            <div class="info-label">Email</div>
                            <p class="info-value mb-0"><a href="mailto:<?php echo $order['EmailNguoiNhan']; ?>" class="text-decoration-none"><i class="fas fa-envelope me-1"></i><?php echo $order['EmailNguoiNhan']; ?></a></p>
                        </div>
                        <div class="info-pair mb-0">
                            <div class="info-label">Địa chỉ giao hàng</div>
                            <p class="info-value mb-0"><i class="fas fa-map-marker-alt me-1"></i><?php echo nl2br(htmlspecialchars($order['DiaChiGiaoHang'])); ?></p>
                        </div>
                    </div>

                    <div class="panel">
                        <h5><i class="fas fa-calculator me-1"></i>Tổng kết thanh toán</h5>
                        <div class="amount-summary mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Tiền hàng</span>
                                <span class="fw-semibold"><?php echo number_format($order['TongTienHang'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Phí vận chuyển</span>
                                <span class="fw-semibold"><?php echo number_format($order['PhiVanChuyen'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <?php if ($order['GiamGia'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Giảm giá</span>
                                <span class="fw-semibold text-success">-<?php echo number_format($order['GiamGia'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <?php endif; ?>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Tổng thanh toán</span>
                                <span class="total-amount"><?php echo number_format($order['TongThanhToan'], 0, ',', '.'); ?>đ</span>
                            </div>
                        </div>
                        <div class="info-pair">
                            <div class="info-label">Phương thức</div>
                            <p class="info-value mb-0"><i class="fas fa-credit-card me-1"></i><?php echo $order['PhuongThucThanhToan'] == 'COD' ? 'Thanh toán khi nhận hàng (COD)' : $order['PhuongThucThanhToan']; ?></p>
                        </div>
                        <div class="info-pair mb-0">
                            <div class="info-label">Trạng thái thanh toán</div>
                            <span class="badge bg-<?php echo $order['TrangThaiThanhToan'] == 'DaThanhToan' ? 'success' : 'warning'; ?>">
                                <?php echo $order['TrangThaiThanhToan'] == 'DaThanhToan' ? 'Đã thanh toán' : 'Chưa thanh toán'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if (count($availableNextStatuses) > 0): ?>
                    <div class="panel no-print">
                        <h5><i class="fas fa-edit me-1"></i>Cập nhật trạng thái</h5>
                        <form method="POST" action="">
                            <div class="mb-2">
                                <label class="form-label fw-semibold">Trạng thái mới</label>
                                <select name="new_status" class="form-select" required>
                                    <option value="">-- Chọn --</option>
                                    <?php foreach ($availableNextStatuses as $nextStatus): ?>
                                        <option value="<?php echo $nextStatus; ?>"><?php echo $statusMap[$nextStatus]['label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Ghi chú (tùy chọn)</label>
                                <textarea name="admin_note" class="form-control" rows="2" placeholder="Thêm ghi chú..."></textarea>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i>Cập nhật
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Main stack -->
                <div class="panel-stack">
                    <div class="panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0"><i class="fas fa-box me-1"></i>Sản phẩm (<?php echo count($items); ?>)</h5>
                        </div>
                        <div class="product-list">
                            <?php foreach ($items as $item): ?>
                            <div class="product-row">
                                <div>
                                    <img src="../../uploads/<?php echo !empty($item['AnhSanPham']) ? $item['AnhSanPham'] : 'default.png'; ?>" alt="product image">
                                </div>
                                <div>
                                    <div class="product-title"><?php echo htmlspecialchars($item['TenSanPham']); ?></div>
                                    <div class="product-meta">
                                        <?php if (!empty($item['MauSac'])): ?>
                                            <span class="badge-light">Màu: <?php echo $item['MauSac']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['KichThuoc'])): ?>
                                            <span class="badge-light">Size: <?php echo $item['KichThuoc']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['SKU'])): ?>
                                            <span class="badge-light">SKU: <?php echo $item['SKU']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="product-price">
                                    <div class="info-label">Đơn giá</div>
                                    <div class="info-value mb-1"><?php echo number_format($item['DonGia'], 0, ',', '.'); ?>đ</div>
                                    <div class="info-label">Số lượng</div>
                                    <div class="info-value mb-1">×<?php echo $item['SoLuong']; ?></div>
                                    <div class="info-label">Thành tiền</div>
                                    <div class="fw-bold text-primary"><?php echo number_format($item['ThanhTien'], 0, ',', '.'); ?>đ</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($order['GhiChu'])): ?>
                    <div class="panel">
                        <h5 class="mb-2"><i class="fas fa-sticky-note me-1"></i>Ghi chú</h5>
                        <div class="note-box">
                            <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit;">&ndash; <?php echo htmlspecialchars($order['GhiChu']); ?></pre>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
