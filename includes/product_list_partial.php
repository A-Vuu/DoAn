<?php
// Kiểm tra biến đầu vào
if (!isset($sqlQuery) || empty($sqlQuery)) return;

$resList = mysqli_query($conn, $sqlQuery);

// Hiển thị tiêu đề
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
            // ... (Logic hiển thị giống hệt file cũ, giữ nguyên phần hiển thị PHP ở đây) ...
            // ĐỂ TIẾT KIỆM DIỆN TÍCH, TÔI VIẾT TÓM TẮT LOGIC HIỂN THỊ
            // BẠN HÃY GIỮ NGUYÊN CODE HIỂN THỊ CARD SẢN PHẨM NHƯ CÂU TRẢ LỜI TRƯỚC
            // CHỈ CẦN COPY PHẦN TRONG VÒNG LẶP WHILE CỦA CÂU TRẢ LỜI TRƯỚC VÀO ĐÂY
            
            // --- Copy Logic Card từ câu trả lời trước ---
            $img = !empty($row['DuongDanAnh']) ? "uploads/".$row['DuongDanAnh'] : "https://via.placeholder.com/300x400";
            $isOutOfStock = ($row['SoLuongTonKho'] <= 0);
    ?>
        <div class="col product-item"> <div class="card-product position-relative h-100">
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
                        <div class="d-flex gap-2 mt-3">
                            <a href="product_detail.php?id=<?php echo $row['Id']; ?>" class="btn btn-outline-dark flex-grow-1 rounded-0">Xem chi tiết</a>
                            <form method="post" action="add_to_cart.php" style="flex-grow: 1;">
                                <input type="hidden" name="product_id" value="<?php echo $row['Id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-dark w-100 rounded-0">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <?php if(!isset($isHomePage)): ?>
            <div class="col-12 text-center py-5"><h4 class="text-muted">Không tìm thấy sản phẩm nào.</h4></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (isset($enableLoadMore) && $enableLoadMore == true && mysqli_num_rows($resList) >= 12): ?>
    <div class="text-center mb-5">
        <button id="btn-load-more" class="btn btn-outline-dark px-5 py-2" 
            data-offset="12" 
            data-type="<?php echo isset($pageType) ? $pageType : 'all'; ?>" 
            data-id="<?php echo isset($pageId) ? $pageId : 0; ?>">
            Xem thêm <i class="fas fa-chevron-down ms-2"></i>
        </button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#btn-load-more').click(function() {
                var btn = $(this);
                var offset = btn.data('offset');
                var type = btn.data('type');
                var id = btn.data('id');

                // Hiệu ứng loading
                btn.html('Đang tải... <span class="spinner-border spinner-border-sm"></span>');
                btn.prop('disabled', true);

                $.ajax({
                    url: 'load_more_products.php',
                    method: 'POST',
                    data: { offset: offset, type: type, id: id },
                    success: function(response) {
                        if ($.trim(response) != '') {
                            // Thêm sản phẩm mới vào danh sách
                            $('#product-list-container').append(response);
                            // Tăng offset lên 12 cho lần bấm tiếp theo
                            btn.data('offset', offset + 12);
                            // Trả lại nút bình thường
                            btn.html('Xem thêm <i class="fas fa-chevron-down ms-2"></i>');
                            btn.prop('disabled', false);
                        } else {
                            // Nếu không còn dữ liệu
                            btn.html('Đã hiển thị tất cả sản phẩm');
                            btn.addClass('disabled border-0 text-muted');
                        }
                    },
                    error: function() {
                        alert('Có lỗi xảy ra, vui lòng thử lại.');
                        btn.html('Xem thêm');
                        btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
<?php endif; ?>