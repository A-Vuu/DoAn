<?php
// 1. Gọi Header
require_once 'includes/header.php';

// 2. Lấy từ khóa
$keyword = '';
if (isset($_GET['q'])) {
    $keyword = trim($_GET['q']);
}

// 3. Xử lý tìm kiếm
// Lưu ý: Chúng ta không include product_list_partial.php trực tiếp được 
// vì file đó dùng mysqli_query thường, còn ở đây ta cần Prepared Statement để bảo mật tìm kiếm.
$products = [];
if ($keyword !== '') {
    // KẾT NỐI BẢNG ẢNH ĐỂ LẤY ẢNH CHÍNH (LaAnhChinh = 1)
    $sql = "SELECT s.*, a.DuongDanAnh 
            FROM SanPham s
            LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1
            WHERE (s.TenSanPham LIKE ? OR s.MoTaNgan LIKE ? OR s.MoTaChiTiet LIKE ? OR s.MaSanPham LIKE ?) 
            AND s.HienThi = 1 
            ORDER BY s.Id DESC";
    
    if ($stmt = $conn->prepare($sql)) {
        $searchTerm = "%" . $keyword . "%";
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="container py-5">
    
    <div class="section-header text-center mb-5">
        <h3 class="fw-bold text-uppercase">KẾT QUẢ TÌM KIẾM</h3>
        <p class="text-muted">
            Từ khóa: <strong>"<?php echo htmlspecialchars($keyword); ?>"</strong> 
            - Tìm thấy <span class="text-primary"><?php echo count($products); ?></span> sản phẩm.
        </p>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-warning text-center" role="alert">
            <i class="fas fa-search me-2"></i> Không tìm thấy sản phẩm nào phù hợp.
            <br>
            <a href="index.php" class="btn btn-dark mt-3">Quay lại trang chủ</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5">
            <?php foreach ($products as $row): ?>
                <?php
                    // Logic xử lý dữ liệu cho Card
                    $img = !empty($row['DuongDanAnh']) ? "uploads/".$row['DuongDanAnh'] : "https://via.placeholder.com/300x400";
                    $isOutOfStock = ($row['SoLuongTonKho'] <= 0);
                    
                    // Logic tính toán badge
                    $badgeTopOffset = 0; 
                    $spacing = 38; 
                ?>
                
                <div class="col product-item"> 
                    <div class="card-product position-relative h-100 border shadow-sm" style="overflow: hidden;"> <?php if ($isOutOfStock): ?>
                            <span class="position-absolute top-50 start-50 translate-middle badge bg-secondary fs-6 shadow z-3" style="opacity: 0.9;">HẾT HÀNG</span>
                        <?php endif; ?>

                        <?php if ($row['SanPhamNoiBat'] == 1 && !$isOutOfStock): ?>
                            <span class="position-absolute start-0 badge bg-warning text-dark m-2 shadow-sm z-2" style="top: <?php echo $badgeTopOffset; ?>px;">HOT</span>
                            <?php $badgeTopOffset += $spacing; ?>
                        <?php endif; ?>

                        <?php if ($row['SanPhamMoi'] == 1 && !$isOutOfStock): ?>
                             <span class="position-absolute start-0 badge bg-primary m-2 shadow-sm z-2" style="top: <?php echo $badgeTopOffset; ?>px;">NEW</span>
                        <?php endif; ?>

                        <?php if($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']): 
                            $percent = round((($row['GiaGoc'] - $row['GiaKhuyenMai']) / $row['GiaGoc']) * 100);
                        ?>
                            <span class="position-absolute top-0 end-0 badge bg-danger m-2 shadow-sm z-2">-<?php echo $percent; ?>%</span>
                        <?php endif; ?>

                        <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="overflow-hidden d-block">
                            <img src="<?php echo $img; ?>" class="card-img-top <?php echo $isOutOfStock ? 'opacity-50' : ''; ?>" alt="<?php echo htmlspecialchars($row['TenSanPham']); ?>" style="width: 100%; height: 300px; object-fit: cover;">
                        </a>

                        <div class="card-body d-flex flex-column text-center p-3">
                            <div class="flex-grow-1">
                                <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="product-title text-dark text-decoration-none fw-bold text-uppercase" style="font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($row['TenSanPham']); ?>
                                </a>
                                
                                <div class="price-box mt-2">
                                    <?php if($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']): ?>
                                        <span class="price-new fw-bold text-danger fs-5"><?php echo number_format($row['GiaKhuyenMai']); ?>đ</span>
                                        <span class="price-old text-decoration-line-through text-muted ms-2 small"><?php echo number_format($row['GiaGoc']); ?>đ</span>
                                    <?php else: ?>
                                        <span class="price-new fw-bold text-dark fs-5"><?php echo number_format($row['GiaGoc']); ?>đ</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-muted small mt-1 fst-italic">Đã bán: <?php echo $row['DaBan']; ?></div>
                            </div>

                            <?php if ($isOutOfStock): ?>
                                <button class="btn btn-secondary w-100 mt-3 rounded-0" disabled>Tạm hết hàng</button>
                            <?php else: ?>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="btn btn-outline-dark flex-grow-1 rounded-0" style="font-size: 0.85rem;">Xem chi tiết</a>
                                    
                                    <form method="post" action="add_to_cart.php" style="flex-grow: 0;">
                                        <input type="hidden" name="product_id" value="<?php echo $row['Id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="btn btn-dark w-100 rounded-0 px-3">
                                            <i class="fas fa-shopping-cart"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
include 'includes/footer.php';
?>