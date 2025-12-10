<?php
require_once __DIR__ . '/includes/header.php';

// Lấy từ khóa tìm kiếm từ GET
$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    // Tìm kiếm trong tên sản phẩm và mô tả
    $qEsc = mysqli_real_escape_string($conn, $q);
    $sql = "
        SELECT sp.Id, sp.MaSanPham, sp.TenSanPham, sp.MoTaNgan, sp.GiaGoc, sp.GiaKhuyenMai, sp.SoLuongTonKho, sp.DaBan,
               a.DuongDanAnh
        FROM SanPham sp
        LEFT JOIN AnhSanPham a ON a.IdSanPham = sp.Id AND a.LaAnhChinh = 1
        WHERE sp.HienThi = 1 AND (
            sp.TenSanPham LIKE '%$qEsc%'
            OR sp.MoTaNgan LIKE '%$qEsc%'
            OR sp.MoTaChiTiet LIKE '%$qEsc%'
        )
        ORDER BY sp.TenSanPham ASC
    ";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $results[] = $row;
        }
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h4>Kết quả tìm kiếm cho: <strong><?php echo htmlspecialchars($q); ?></strong></h4>
            
            <?php if ($q === ''): ?>
                <div class="alert alert-warning">Vui lòng nhập từ khóa để tìm kiếm.</div>
            <?php elseif (empty($results)): ?>
                <div class="alert alert-info">Không tìm thấy sản phẩm nào phù hợp.</div>
            <?php else: ?>
                <p class="text-muted">Tìm thấy <?php echo count($results); ?> sản phẩm</p>
                
                <div class="row">
                    <?php foreach ($results as $product): ?>
                        <?php
                            $price = $product['GiaKhuyenMai'] ? floatval($product['GiaKhuyenMai']) : floatval($product['GiaGoc']);
                            $img = $product['DuongDanAnh'] ?? 'images/no-image.png';
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 mb-4">
                            <a href="product.php?id=<?php echo $product['Id']; ?>" class="text-decoration-none text-dark">
                                <div class="card card-product h-100">
                                    <div class="card-img-top overflow-hidden" style="height: 250px;">
                                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['TenSanPham']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($product['TenSanPham']); ?></h6>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($product['MoTaNgan'] ?? '', 0, 60)) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark"><?php echo number_format($price, 0, ',', '.'); ?>đ</strong>
                                            <small class="text-success">Đã bán: <?php echo $product['DaBan']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="index.php" class="btn btn-outline-secondary">Quay lại trang chủ</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
