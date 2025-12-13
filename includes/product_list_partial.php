<?php
// product_list_partial.php

if (!isset($sqlQuery) || empty($sqlQuery)) return;
$resList = mysqli_query($conn, $sqlQuery);

if (isset($sectionTitle) && !empty($sectionTitle)): ?>
    <div class="section-header text-center mt-5 mb-4">
        <h3 class="fw-bold text-uppercase"><?php echo $sectionTitle; ?></h3>
        <?php if(isset($sectionDesc)) echo '<p class="text-muted">'.$sectionDesc.'</p>'; ?>
    </div>
<?php endif; ?>

<div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-5" id="product-list-container">
    <?php
    if (mysqli_num_rows($resList) > 0):
        while($row = mysqli_fetch_assoc($resList)):
            $pId = $row['Id'];
            $price = $row['GiaGoc'];
            $salePrice = $row['GiaKhuyenMai'];
            $finalPrice = ($salePrice > 0 && $salePrice < $price) ? $salePrice : $price;
            $discount = ($salePrice > 0 && $salePrice < $price) ? round((($price - $salePrice) / $price) * 100) : 0;
            
            $mainImg = !empty($row['DuongDanAnh']) ? $row['DuongDanAnh'] : 'default.png';
            $isOutOfStock = ($row['SoLuongTonKho'] <= 0);

            // --- LẤY MÀU SẮC & ẢNH BIẾN THỂ (Để dùng cho chấm màu) ---
            $sqlColors = "SELECT DISTINCT m.Id, m.TenMau, m.MaMau, ct.AnhBienThe 
                          FROM ChiTietSanPham ct
                          JOIN MauSac m ON ct.IdMauSac = m.Id
                          WHERE ct.IdSanPham = $pId 
                          GROUP BY m.Id ORDER BY m.Id ASC";
            $resColors = mysqli_query($conn, $sqlColors);
            
            $colorsArr = [];
            if ($resColors && mysqli_num_rows($resColors) > 0) {
                while($c = mysqli_fetch_assoc($resColors)) {
                    $colorsArr[] = $c;
                }
            }
            $hasColors = count($colorsArr) > 0;
            $badgeTopOffset = 10; $spacing = 35;
    ?>
    <div class="col product-item">
        <div class="card h-100 border-0 shadow-sm product-card position-relative">
            
            <div class="card-img-wrapper position-relative overflow-hidden" style="height: 320px;">
                <img id="img-<?php echo $pId; ?>" 
                     src="uploads/<?php echo $mainImg; ?>" 
                     class="card-img-top product-img <?php echo $isOutOfStock ? 'opacity-50' : ''; ?>" 
                     alt="<?php echo $row['TenSanPham']; ?>">

                <div class="position-absolute top-0 start-0 w-100 h-100 pointer-events-none">
                    <?php if ($isOutOfStock): ?>
                        <span class="position-absolute top-50 start-50 translate-middle badge bg-secondary fs-6 shadow z-3 opacity-75">HẾT HÀNG</span>
                    <?php endif; ?>
                    <?php if ($discount > 0): ?>
                        <span class="position-absolute top-0 end-0 badge bg-danger m-2 shadow-sm z-2">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                    <?php if ($row['SanPhamNoiBat'] == 1 && !$isOutOfStock): ?>
                        <span class="position-absolute start-0 badge bg-warning text-dark m-2 shadow-sm z-2" style="top: <?php echo $badgeTopOffset; ?>px;">HOT</span>
                        <?php $badgeTopOffset += $spacing; ?>
                    <?php endif; ?>
                    <?php if ($row['SanPhamMoi'] == 1 && !$isOutOfStock): ?>
                            <span class="position-absolute start-0 badge bg-primary m-2 shadow-sm z-2" style="top: <?php echo $badgeTopOffset; ?>px;">NEW</span>
                    <?php endif; ?>
                </div>
                <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="stretched-link"></a>
            </div>

            <div class="card-body text-center d-flex flex-column pt-3 pb-3">
                <h6 class="mb-2" style="min-height: 44px;">
                    <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="product-title text-decoration-none" style="z-index: 2; position: relative;">
                        <?php echo $row['TenSanPham']; ?>
                    </a>
                </h6>

                <div class="d-flex justify-content-center align-items-center gap-2 mb-2" style="min-height: 24px; z-index: 2; position: relative;">
                    <?php if ($hasColors): 
                        $countC = 0; 
                        foreach($colorsArr as $color): 
                            if($countC >= 5) { echo '<span class="small text-muted">+</span>'; break; }
                            
                            // Logic: Nếu màu có ảnh biến thể thì dùng ảnh đó, nếu không thì dùng ảnh gốc
                            $imgVariant = !empty($color['AnhBienThe']) ? $color['AnhBienThe'] : $mainImg;
                            $maMau = !empty($color['MaMau']) ? $color['MaMau'] : '#ccc';
                        ?>
                            <div class="color-dot-wrapper" 
                                 title="<?php echo $color['TenMau']; ?>"
                                 onclick="event.preventDefault(); changeMainImage('img-<?php echo $pId; ?>', '<?php echo $imgVariant; ?>', this);">
                                <span class="color-dot" style="background-color: <?php echo $maMau; ?>;"></span>
                            </div>
                        <?php $countC++; endforeach; 
                    else: ?>
                        <span class="d-inline-block" style="height: 24px;"></span>
                    <?php endif; ?>
                </div>

                <div class="price-box mb-3 mt-auto">
                    <?php if($discount > 0): ?>
                        <span class="price-new"><?php echo number_format($finalPrice); ?>đ</span>
                        <span class="price-old"><?php echo number_format($price); ?>đ</span>
                    <?php else: ?>
                        <span class="price-new" style="color: #333;"><?php echo number_format($price); ?>đ</span>
                    <?php endif; ?>
                </div>
                
                <div class="text-muted small mb-2" style="font-size: 11px;">Đã bán: <?php echo $row['DaBan']; ?></div>

                <?php if (!$isOutOfStock): ?>
                    <div class="btn btn-outline-dark w-100 btn-view-detail" style="z-index: 2; position: relative;">
                        <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="text-decoration-none text-inherit d-block">Xem chi tiết</a>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 rounded-0" disabled style="font-size: 13px;">Tạm hết hàng</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; else: ?>
        <?php if(!isset($isHomePage)): ?><div class="col-12 text-center py-5">Không có sản phẩm.</div><?php endif; ?>
    <?php endif; ?>
</div>

<?php if (isset($enableLoadMore) && $enableLoadMore == true && mysqli_num_rows($resList) >= 12): ?>
    <div class="text-center mb-5">
        <button id="btn-load-more" class="btn btn-outline-dark px-5 py-2 btn-view-detail" 
            data-offset="12" data-type="<?php echo isset($pageType) ? $pageType : 'all'; ?>" data-id="<?php echo isset($pageId) ? $pageId : 0; ?>">
            Xem thêm <i class="fas fa-chevron-down ms-2"></i>
        </button>
    </div>
<?php endif; ?>

<script>
// Hàm đổi ảnh chính khi click vào chấm màu (Đơn giản hóa)
function changeMainImage(imgId, imgFileName, dotElement) {
    const imgElement = document.getElementById(imgId);
    if(imgElement && imgFileName) {
        // 1. Hiệu ứng mờ nhẹ
        imgElement.style.opacity = 0.6;
        setTimeout(() => {
            imgElement.src = 'uploads/' + imgFileName;
            imgElement.style.opacity = 1;
        }, 150);

        // 2. Highlight chấm màu đang chọn
        let parent = dotElement.parentElement; 
        let siblings = parent.getElementsByClassName('color-dot-wrapper');
        for(let item of siblings) {
            item.classList.remove('active');
        }
        dotElement.classList.add('active');
    }
}
</script>