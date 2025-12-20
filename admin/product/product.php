<?php
session_start();
require_once '../../config.php';

function log_product_action($conn, $action, $productId, $content) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($stmt = $conn->prepare(
        "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES (?, ?, 'admin', ?, 'SanPham', ?, ?, ?)"
    )) {
        $nullUser = null;
        $stmt->bind_param(
            'ississ',
            $nullUser,
            $adminId,
            $action,
            $productId,
            $content,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}


if (!isset($_SESSION['admin_login'])) header("Location: login.php");

// Lấy danh sách sản phẩm + Tên danh mục
$sql = "SELECT p.*, c.TenDanhMuc 
        FROM SanPham p 
        LEFT JOIN DanhMucSanPham c ON p.IdDanhMuc = c.Id 
        ORDER BY p.Id DESC";
$products = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quản lý sản phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css"> </head>
<body>

    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3 text-white">
             Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
        </div>
        <hr style="border-color: #4f5962;">
        <nav>
            <a href="../category.php">Danh mục sản phẩm</a>
            <a href="product.php" class="active">Quản lý sản phẩm</a>
            <a href="../orders/orders.php">Quản lý đơn hàng</a>
            <a href="../news/news.php" >Tin tức</a>
            <a href="../promotion/promotion.php">Quản lý Khuyến mãi</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="../danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
            <a href="../lich_su_hoat_dong.php">Lịch sử hoạt động</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Quản lý sản phẩm</h3>
            <a href="product_add.php" class="btn btn-primary">+ Thêm mới</a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Tồn kho</th>
                            <th>Giá</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php while($row = mysqli_fetch_assoc($products)): ?>
    <?php
        // --- XỬ LÝ ẢNH ---
        $idSP = $row['Id'];
        $sqlAnh = "SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = $idSP ORDER BY LaAnhChinh DESC LIMIT 1";
        $resultAnh = mysqli_query($conn, $sqlAnh);
        $anhData = mysqli_fetch_assoc($resultAnh);

        if ($anhData && !empty($anhData['DuongDanAnh'])) {
            $imgSrc = "../../uploads/" . $anhData['DuongDanAnh'];
        } else {
            $imgSrc = "https://via.placeholder.com/50?text=No+Img";
        }
    ?>
    <tr>
        <td>#<?php echo $row['Id']; ?></td>
        <td>
            <img src="<?php echo $imgSrc; ?>" width="50" height="50" style="object-fit: cover; border-radius: 5px; border: 1px solid #eee;">
        </td> 
        <td>
            <b><?php echo $row['TenSanPham']; ?></b>
            <br>
            <small class="text-muted">Ngày tạo: <?php echo $row['NgayTao']; ?></small>
        </td>
        <td><span class="badge bg-info text-dark"><?php echo $row['TenDanhMuc']; ?></span></td>
        <td><span class="badge bg-success"><?php echo $row['SoLuongTonKho']; ?></span></td>
        
        <td>
            <?php if ($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']): ?>
                <del class="text-secondary" style="font-size: 0.9em;"><?php echo number_format($row['GiaGoc']); ?> đ</del>
                <br>
                <span class="text-danger fw-bold"><?php echo number_format($row['GiaKhuyenMai']); ?> đ</span>
            <?php else: ?>
                <span class="text-danger fw-bold"><?php echo number_format($row['GiaGoc']); ?> đ</span>
            <?php endif; ?>
        </td>
        
        <td>
            <?php if ($row['HienThi'] == 1): ?>
                <span class="badge bg-success">Hiển thị</span>
            <?php else: ?>
                <span class="badge bg-secondary">Ẩn</span>
            <?php endif; ?>

            <?php if ($row['SanPhamNoiBat'] == 1): ?>
                <br style="margin-bottom: 4px;"> 
                <span class="badge bg-warning text-dark">HOT</span>
            <?php endif; ?>

            <?php if (isset($row['SanPhamMoi']) && $row['SanPhamMoi'] == 1): ?>
                <br style="margin-bottom: 4px;"> 
                <span class="badge bg-primary">NEW</span>
            <?php endif; ?>

            <?php if ($row['GiaKhuyenMai'] > 0): ?>
                <br style="margin-bottom: 4px;"> 
                <span class="badge bg-danger">SALE</span>
            <?php endif; ?>
        </td>

        <td>
            <a href="product_edit.php?id=<?php echo $row['Id']; ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i> Sửa </a>
            <a href="product_delete.php?id=<?php echo $row['Id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm: <?php echo $row['TenSanPham']; ?>? \n(Lưu ý: Ảnh và các biến thể cũng sẽ bị xóa!)');">
                <i class="fas fa-trash"></i> Xóa</a>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>