<?php
session_start();
require_once '../../config.php';

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit;
}

// Log hoạt động
function log_promo_action($conn, $action, $promoId, $content) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if ($stmt = $conn->prepare(
        "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES (?, ?, 'admin', ?, 'MaGiamGia', ?, ?, ?)"
    )) {
        $nullUser = null;
        $stmt->bind_param('ississ', $nullUser, $adminId, $action, $promoId, $content, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// Xóa mã giảm giá
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($stmt = $conn->prepare("DELETE FROM MaGiamGia WHERE Id = ?")) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            log_promo_action($conn, 'Xóa', $id, '');
            $_SESSION['success'] = 'Xóa mã giảm giá thành công!';
        }
        $stmt->close();
    }
    header("Location: promotion.php");
    exit;
}

// Lấy danh sách mã giảm giá
$sql = "SELECT * FROM MaGiamGia ORDER BY NgayKetThuc DESC, Id DESC";
$promos = mysqli_query($conn, $sql);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Mã Giảm Giá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="sidebar">
    <h4 class="text-center mb-4">NovaWear Admin</h4>
    <div class="px-3 mb-3 text-white">
         Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
    </div>

    <hr style="border-color: #4f5962;">
    <nav>
        <a href="../category.php">Danh mục sản phẩm</a>
        <a href="../product/product.php">Quản lý sản phẩm</a>
        <a href="../orders/orders.php">Quản lý đơn hàng</a>
        <a href="../news/news.php">Tin tức</a>
        <a href="promotion.php" class="active">Quản lý Khuyến mãi</a>
        <a href="../banner/banner.php">Quảng cáo</a>
        <a href="../danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
        <a href="../lich_su_hoat_dong.php">Lịch sử hoạt động</a>
        <a href="../logout.php">Đăng xuất</a>
    </nav>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="fas fa-tag"></i> Quản lý Mã Giảm Giá</h3>
            <a href="promotion_add.php" class="btn btn-success btn-lg">
                <i class="fas fa-plus"></i> Thêm Mã Giảm Giá
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="10%">Mã Code</th>
                                <th width="20%">Tên Chương Trình</th>
                                <th width="12%">Giá Trị Giảm</th>
                                <th width="15%">Giảm Tối Đa</th>
                                <th width="15%">Đơn Hàng Tối Thiểu</th>
                                <th width="10%">Lượt Dùng</th>
                                <th width="10%">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($promos) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($promos)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['MaCode']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['TenChuongTrinh']); ?></td>
                                    <td><?php echo htmlspecialchars($row['LoaiGiam']); ?> - <?php echo number_format($row['GiaTriGiam']); ?></td>
                                    <td><?php echo number_format($row['GiamToiDa']); ?>đ</td>
                                    <td><?php echo number_format($row['GiaTriDonHangToiThieu']); ?>đ</td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $row['DaSuDung']; ?></span>
                                        / <?php echo $row['SoLuongMa']; ?>
                                    </td>
                                    <td>
                                        <a href="promotion_add.php?id=<?php echo $row['Id']; ?>" class="btn btn-sm btn-primary" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="promotion.php?action=delete&id=<?php echo $row['Id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xác nhận xóa?')" title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Chưa có mã giảm giá nào. <a href="promotion_add.php">Tạo mới</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
