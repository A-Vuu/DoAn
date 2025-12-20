<?php
session_start();
require_once '../../config.php';
require_once '../helpers/log_activity.php';


if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

function allowed_next_statuses($current)
{
    switch ($current) {
        case 'ChoXacNhan':
            return ['DaXacNhan', 'DaHuy'];
        case 'DaXacNhan':
            return ['DangGiao', 'DaHuy'];
        case 'DangGiao':
            return ['HoanThanh', 'DaHuy'];
        default:
            return [];
    }
}

function restock_order($conn, $orderId) {
    if ($stmt = $conn->prepare('SELECT IdSanPham, IdChiTietSanPham, SoLuong FROM chitietdonhang WHERE IdDonHang = ?')) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $qty = (int)$row['SoLuong'];
            if (!empty($row['IdChiTietSanPham'])) {
                if ($up = $conn->prepare('UPDATE chitietsanpham SET SoLuong = SoLuong + ? WHERE Id = ?')) {
                    $up->bind_param('ii', $qty, $row['IdChiTietSanPham']);
                    $up->execute();
                    $up->close();
                }
            } else {
                if ($up = $conn->prepare('UPDATE sanpham SET SoLuongTonKho = SoLuongTonKho + ? WHERE Id = ?')) {
                    $up->bind_param('ii', $qty, $row['IdSanPham']);
                    $up->execute();
                    $up->close();
                }
            }
        }
        $stmt->close();
    }
}

// ============================================
// XỬ LÝ CẬP NHẬT TRẠNG THÁI NHANH
// ============================================
if (isset($_POST['quick_update_status'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        $_SESSION['admin_msg'] = "Phiên không hợp lệ.";
        header("Location: orders.php" . ($_GET ? '?' . http_build_query($_GET) : ''));
        exit();
    }

    $orderId = intval($_POST['order_id']);
    $newStatus = $_POST['new_status'] ?? '';

    // Lấy trạng thái hiện tại
    $currentStatus = null;
    if ($stmt = $conn->prepare('SELECT TrangThaiDonHang FROM donhang WHERE Id = ? LIMIT 1')) {
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $currentStatus = $row['TrangThaiDonHang'];
        }
        $stmt->close();
    }

    $allowed = allowed_next_statuses($currentStatus);
    if (!$currentStatus || !in_array($newStatus, $allowed, true)) {
        $_SESSION['admin_msg'] = "Trạng thái không hợp lệ hoặc đã hoàn tất.";
        header("Location: orders.php" . ($_GET ? '?' . http_build_query($_GET) : ''));
        exit();
    }

    // Cập nhật trạng thái + mốc thời gian
    $fields = ['TrangThaiDonHang = ?'];
    switch ($newStatus) {
        case 'DaXacNhan': $fields[] = 'NgayXacNhan = NOW()'; break;
        case 'DangGiao':  $fields[] = 'NgayGiaoHang = NOW()'; break;
        case 'HoanThanh': $fields[] = 'NgayHoanThanh = NOW()'; break;
        case 'DaHuy':     $fields[] = 'NgayHuy = NOW()'; break;
    }
    $sqlUpdate = 'UPDATE donhang SET ' . implode(', ', $fields) . ' WHERE Id = ?';
    if ($stmt = $conn->prepare($sqlUpdate)) {
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();
    }

    // Hoàn trả tồn kho nếu hủy
    if ($newStatus === 'DaHuy') {
        restock_order($conn, $orderId);
    }

    // Ghi log vào lichsuhoatdong
   ghiLichSuAdmin(
    $conn,
    "Cập nhật trạng thái đơn hàng",
    "DonHang",
    $orderId,
    "Chuyển trạng thái đơn hàng sang: $newStatus"
);


    $_SESSION['admin_msg'] = "Đã cập nhật trạng thái đơn hàng #$orderId";
    header("Location: orders.php" . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit();
}

// ============================================
// BỘ LỌC & TÌM KIẾM
// ============================================
$filterStatus = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$searchKeyword = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Xây dựng WHERE clause
$whereConditions = [];
if ($filterStatus != '' && $filterStatus != 'all') {
    $whereConditions[] = "d.TrangThaiDonHang = '$filterStatus'";
}
if ($searchKeyword != '') {
    $whereConditions[] = "(d.MaDonHang LIKE '%$searchKeyword%' OR d.TenNguoiNhan LIKE '%$searchKeyword%' OR d.SoDienThoai LIKE '%$searchKeyword%' OR n.Email LIKE '%$searchKeyword%')";
}
if ($dateFrom != '') {
    $whereConditions[] = "DATE(d.NgayDatHang) >= '$dateFrom'";
}
if ($dateTo != '') {
    $whereConditions[] = "DATE(d.NgayDatHang) <= '$dateTo'";
}

$whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Đếm tổng số đơn
$sqlCount = "SELECT COUNT(*) as total FROM donhang d 
             LEFT JOIN NguoiDung n ON d.IdNguoiDung = n.Id 
             $whereClause";
$resCount = mysqli_query($conn, $sqlCount);
$totalOrders = mysqli_fetch_assoc($resCount)['total'];
$totalPages = ceil($totalOrders / $limit);

// Lấy danh sách đơn hàng
$sql = "SELECT d.*, n.HoTen, n.Email 
        FROM donhang d 
        LEFT JOIN NguoiDung n ON d.IdNguoiDung = n.Id 
        $whereClause 
        ORDER BY d.NgayDatHang DESC 
        LIMIT $limit OFFSET $offset";
$orders = mysqli_query($conn, $sql);

// Thống kê theo trạng thái
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN TrangThaiDonHang = 'ChoXacNhan' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN TrangThaiDonHang = 'DaXacNhan' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN TrangThaiDonHang = 'DangGiao' THEN 1 ELSE 0 END) as shipping,
    SUM(CASE WHEN TrangThaiDonHang = 'HoanThanh' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN TrangThaiDonHang = 'DaHuy' THEN 1 ELSE 0 END) as cancelled
FROM donhang";
$statsRes = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsRes);

// Map trạng thái
$statusMap = [
    'ChoXacNhan' => ['label' => 'Chờ xác nhận', 'class' => 'warning', 'icon' => 'clock'],
    'DaXacNhan' => ['label' => 'Đã xác nhận', 'class' => 'info', 'icon' => 'check-circle'],
    'DangGiao' => ['label' => 'Đang giao', 'class' => 'primary', 'icon' => 'truck'],
    'HoanThanh' => ['label' => 'Hoàn thành', 'class' => 'success', 'icon' => 'check-double'],
    'DaHuy' => ['label' => 'Đã hủy', 'class' => 'danger', 'icon' => 'times-circle']
];

$msg = isset($_SESSION['admin_msg']) ? $_SESSION['admin_msg'] : '';
unset($_SESSION['admin_msg']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn hàng - NovaWear Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stats-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .stats-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .order-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #d1d5db;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filter-chip {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e5e7eb;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
        }
        .filter-chip:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .filter-chip.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        .search-box {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 10px 16px;
        }
        .search-box:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .quick-action-btn {
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        .pagination-custom .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #e5e7eb;
        }
        .pagination-custom .page-link:hover {
            background: #f3f4f6;
        }
        .pagination-custom .page-item.active .page-link {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        .order-amount {
            font-size: 16px;
            font-weight: 700;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3 text-white">
             Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
        </div>
        <hr style="border-color: #4f5962;">
        <nav>
            <a href="../category.php" >Danh mục sản phẩm</a>
            <a href="../product/product.php" >Quản lý sản phẩm</a>
            <a href="../orders/orders.php" class="active">Quản lý đơn hàng</a>
            <a href="../news/news.php">Tin tức</a>
            <a href="../promotion/promotion.php">Quản lý Khuyến mãi</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="../danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
            <a href="../lich_su_hoat_dong.php">Lịch sử hoạt động</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1"><i class="fas fa-shopping-cart me-2"></i>Quản lý Đơn hàng</h3>
                <p class="text-muted mb-0 small">Quản lý và theo dõi tất cả đơn hàng của khách hàng</p>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-light text-primary me-3">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Tổng đơn</div>
                            <div class="fs-4 fw-bold"><?php echo $stats['total']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Chờ xác nhận</div>
                            <div class="fs-4 fw-bold text-warning"><?php echo $stats['pending']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-info bg-opacity-10 text-info me-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Đã xác nhận</div>
                            <div class="fs-4 fw-bold text-info"><?php echo $stats['confirmed']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Đang giao</div>
                            <div class="fs-4 fw-bold text-primary"><?php echo $stats['shipping']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Hoàn thành</div>
                            <div class="fs-4 fw-bold text-success"><?php echo $stats['completed']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card card p-3">
                    <div class="d-flex align-items-center">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Đã hủy</div>
                            <div class="fs-4 fw-bold text-danger"><?php echo $stats['cancelled']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="orders.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Tìm kiếm</label>
                        <input type="text" name="search" class="form-control search-box" 
                               placeholder="Mã đơn, tên KH, SĐT, email..." 
                               value="<?php echo htmlspecialchars($searchKeyword); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Từ ngày</label>
                        <input type="date" name="date_from" class="form-control search-box" 
                               value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Đến ngày</label>
                        <input type="date" name="date_to" class="form-control search-box" 
                               value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold text-muted">Trạng thái</label>
                        <select name="status" class="form-select search-box">
                            <option value="all" <?php echo $filterStatus == 'all' || $filterStatus == '' ? 'selected' : ''; ?>>Tất cả</option>
                            <option value="ChoXacNhan" <?php echo $filterStatus == 'ChoXacNhan' ? 'selected' : ''; ?>>Chờ xác nhận</option>
                            <option value="DaXacNhan" <?php echo $filterStatus == 'DaXacNhan' ? 'selected' : ''; ?>>Đã xác nhận</option>
                            <option value="DangGiao" <?php echo $filterStatus == 'DangGiao' ? 'selected' : ''; ?>>Đang giao</option>
                            <option value="HoanThanh" <?php echo $filterStatus == 'HoanThanh' ? 'selected' : ''; ?>>Hoàn thành</option>
                            <option value="DaHuy" <?php echo $filterStatus == 'DaHuy' ? 'selected' : ''; ?>>Đã hủy</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i>Lọc
                        </button>
                        <a href="orders.php" class="btn btn-outline-secondary w-100 mt-2">
                            <i class="fas fa-redo me-1"></i>Làm mới
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Status Filter Chips -->
        <div class="mb-4 d-flex gap-2 flex-wrap">
            <a href="orders.php" class="filter-chip <?php echo ($filterStatus == '' || $filterStatus == 'all') ? 'active' : ''; ?>">
                <i class="fas fa-list me-1"></i>Tất cả (<?php echo $stats['total']; ?>)
            </a>
            <a href="orders.php?status=ChoXacNhan" class="filter-chip <?php echo $filterStatus == 'ChoXacNhan' ? 'active' : ''; ?>">
                <i class="fas fa-clock me-1"></i>Chờ xác nhận (<?php echo $stats['pending']; ?>)
            </a>
            <a href="orders.php?status=DaXacNhan" class="filter-chip <?php echo $filterStatus == 'DaXacNhan' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle me-1"></i>Đã xác nhận (<?php echo $stats['confirmed']; ?>)
            </a>
            <a href="orders.php?status=DangGiao" class="filter-chip <?php echo $filterStatus == 'DangGiao' ? 'active' : ''; ?>">
                <i class="fas fa-truck me-1"></i>Đang giao (<?php echo $stats['shipping']; ?>)
            </a>
            <a href="orders.php?status=HoanThanh" class="filter-chip <?php echo $filterStatus == 'HoanThanh' ? 'active' : ''; ?>">
                <i class="fas fa-check-double me-1"></i>Hoàn thành (<?php echo $stats['completed']; ?>)
            </a>
            <a href="orders.php?status=DaHuy" class="filter-chip <?php echo $filterStatus == 'DaHuy' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle me-1"></i>Đã hủy (<?php echo $stats['cancelled']; ?>)
            </a>
        </div>

        <!-- Orders List -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($orders) > 0): ?>
                    <?php while($order = mysqli_fetch_assoc($orders)): 
                        $statusInfo = $statusMap[$order['TrangThaiDonHang']];
                        $orderDate = date('d/m/Y H:i', strtotime($order['NgayDatHang']));
                    ?>
                    <div class="order-card p-3 m-3">
                        <div class="row align-items-center">
                            <!-- Order Info -->
                            <div class="col-md-3">
                                <div class="fw-bold mb-1">
                                    <i class="fas fa-receipt me-2 text-muted"></i>
                                    <a href="order_detail.php?id=<?php echo $order['Id']; ?>" class="text-decoration-none">
                                        <?php echo $order['MaDonHang']; ?>
                                    </a>
                                </div>
                                <div class="small text-muted">
                                    <i class="far fa-clock me-1"></i><?php echo $orderDate; ?>
                                </div>
                            </div>

                            <!-- Customer Info -->
                            <div class="col-md-3">
                                <div class="fw-semibold small mb-1">
                                    <i class="fas fa-user me-1 text-muted"></i>
                                    <?php echo htmlspecialchars($order['TenNguoiNhan']); ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="fas fa-phone me-1"></i><?php echo $order['SoDienThoai']; ?>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="col-md-2 text-center">
                                <div class="small text-muted mb-1">Tổng tiền</div>
                                <div class="order-amount text-primary">
                                    <?php echo number_format($order['TongThanhToan'], 0, ',', '.'); ?>đ
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-2 text-center">
                                <span class="status-badge bg-<?php echo $statusInfo['class']; ?> bg-opacity-10 text-<?php echo $statusInfo['class']; ?>">
                                    <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i>
                                    <?php echo $statusInfo['label']; ?>
                                </span>
                            </div>

                            <!-- Actions -->
                            <div class="col-md-2 text-end">
                                <div class="btn-group" role="group">
                                    <a href="order_detail.php?id=<?php echo $order['Id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($order['TrangThaiDonHang'] == 'ChoXacNhan'): ?>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="quickUpdateStatus(<?php echo $order['Id']; ?>, 'DaXacNhan')" title="Xác nhận">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="quickUpdateStatus(<?php echo $order['Id']; ?>, 'DaHuy')" title="Hủy">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php elseif ($order['TrangThaiDonHang'] == 'DaXacNhan'): ?>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="quickUpdateStatus(<?php echo $order['Id']; ?>, 'DangGiao')" title="Giao hàng">
                                            <i class="fas fa-truck"></i>
                                        </button>
                                    <?php elseif ($order['TrangThaiDonHang'] == 'DangGiao'): ?>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                onclick="quickUpdateStatus(<?php echo $order['Id']; ?>, 'HoanThanh')" title="Hoàn thành">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="p-3">
                            <nav>
                                <ul class="pagination pagination-custom justify-content-center mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page-1); ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($searchKeyword); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($searchKeyword); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page+1); ?>&status=<?php echo $filterStatus; ?>&search=<?php echo urlencode($searchKeyword); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center text-muted small mt-2">
                                Trang <?php echo $page; ?> / <?php echo $totalPages; ?> (Tổng <?php echo $totalOrders; ?> đơn hàng)
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Không tìm thấy đơn hàng nào</h5>
                        <p class="text-muted small">Thử thay đổi bộ lọc hoặc tìm kiếm</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Update Form (Hidden) -->
    <form id="quickUpdateForm" method="POST" action="orders.php" style="display: none;">
        <input type="hidden" name="quick_update_status" value="1">
        <input type="hidden" name="order_id" id="quick_order_id">
        <input type="hidden" name="new_status" id="quick_new_status">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function quickUpdateStatus(orderId, newStatus) {
            const statusLabels = {
                'DaXacNhan': 'Xác nhận',
                'DangGiao': 'Chuyển sang Đang giao',
                'HoanThanh': 'Hoàn thành',
                'DaHuy': 'Hủy'
            };
            
            if (confirm(`Bạn có chắc muốn ${statusLabels[newStatus]} đơn hàng này?`)) {
                document.getElementById('quick_order_id').value = orderId;
                document.getElementById('quick_new_status').value = newStatus;
                document.getElementById('quickUpdateForm').submit();
            }
        }
    </script>
</body>
</html>
