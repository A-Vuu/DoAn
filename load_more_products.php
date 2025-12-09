<?php
require_once 'config.php';

// Nhận dữ liệu từ nút bấm gửi sang
$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
$limit  = 12; // Số lượng lấy thêm mỗi lần
$type   = isset($_POST['type']) ? $_POST['type'] : 'all';
$id     = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Xây dựng câu SQL dựa trên "loại trang" đang xem
$whereClause = "WHERE s.HienThi = 1";
$orderClause = "ORDER BY s.Id DESC";

if ($type == 'bestseller') {
    // Logic của trang Bán chạy
    $orderClause = "ORDER BY s.DaBan DESC";
} elseif ($type == 'category' && $id > 0) {
    // Logic của trang Danh mục
    $whereClause .= " AND s.IdDanhMuc = $id";
} elseif ($type == 'new') {
    $whereClause .= " AND s.SanPhamMoi = 1";
}

// Câu truy vấn lấy dữ liệu (LIMIT và OFFSET quan trọng để phân trang)
$sqlQuery = "SELECT s.*, a.DuongDanAnh 
             FROM SanPham s 
             LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
             $whereClause 
             $orderClause 
             LIMIT $limit OFFSET $offset";

$resList = mysqli_query($conn, $sqlQuery);

// --- BẮT ĐẦU VÒNG LẶP HTML (Giống hệt product_list_partial.php) ---
if (mysqli_num_rows($resList) > 0) {
    while($row = mysqli_fetch_assoc($resList)) {
        $img = !empty($row['DuongDanAnh']) ? "uploads/".$row['DuongDanAnh'] : "https://via.placeholder.com/300x400";
        $isOutOfStock = ($row['SoLuongTonKho'] <= 0);
        
        // HTML hiển thị từng sản phẩm
        ?>
        <div class="col product-item">
            <div class="card-product position-relative h-100">
                <?php
                $badgeTopOffset = 0;
                $spacing = 38;
                ?>

                <?php if ($isOutOfStock): ?>
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

                <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="overflow-hidden">
                    <img src="<?php echo $img; ?>" class="card-img-top <?php echo $isOutOfStock ? 'opacity-50' : ''; ?>" alt="<?php echo $row['TenSanPham']; ?>">
                </a>
                
                <div class="card-body d-flex flex-column">
                    <div class="text-center flex-grow-1">
                        <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="product-title text-dark text-decoration-none fw-bold">
                            <?php echo $row['TenSanPham']; ?>
                        </a>
                        <div class="price-box mt-2">
                            <?php if($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']): ?>
                                <span class="price-new fw-bold text-danger fs-5"><?php echo number_format($row['GiaKhuyenMai']); ?>đ</span>
                                <span class="price-old text-decoration-line-through text-muted ms-2 small"><?php echo number_format($row['GiaGoc']); ?>đ</span>
                            <?php else: ?>
                                <span class="price-new fw-bold text-dark fs-5"><?php echo number_format($row['GiaGoc']); ?>đ</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small mt-1">Đã bán: <?php echo $row['DaBan']; ?></div>
                    </div>

                    <?php if ($isOutOfStock): ?>
                        <button class="btn btn-secondary w-100 mt-3 rounded-0" disabled>Tạm hết hàng</button>
                    <?php else: ?>
                        <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="btn btn-dark w-100 mt-3 rounded-0">Xem chi tiết</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
?>