<?php
session_start();
require_once 'config.php';

// --- [FIXED] LOGIC CHẶN USER: KHÔNG ẢNH HƯỞNG ADMIN ---
if (isset($_SESSION['user_id'])) {
    // [QUAN TRỌNG] Nếu là Admin đang đăng nhập thì KHÔNG chạy logic này
    if (!isset($_SESSION['admin_login'])) { 
        
        $currentUserId = $_SESSION['user_id'];
        $sqlStatus = "SELECT TrangThai FROM nguoidung WHERE Id = $currentUserId";
        $resStatus = mysqli_query($conn, $sqlStatus);
        
        if ($resStatus && mysqli_num_rows($resStatus) > 0) {
            $userStatus = mysqli_fetch_assoc($resStatus);
            
            // Nếu bị chặn (TrangThai = 0)
            if ($userStatus['TrangThai'] == 0) {
                // 1. Xóa tất cả các biến trong session hiện tại
                $_SESSION = array();

                // 2. Xóa Cookie Session trên trình duyệt (để ngăn trình duyệt gửi lại ID cũ)
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }

                // 3. Hủy session trên server
                session_destroy();
                
                // 4. Chuyển hướng về trang chủ
                header("Location: index.php"); 
                exit(); 
            }
        }
    }
}
// -------------------------------------------------------------
// -------------------------------------------------------------

include 'includes/header.php';

// --- 1. LẤY ID SẢN PHẨM ---
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($productId == 0) {
    echo "<div class='container py-5 text-center'><h3>Sản phẩm không hợp lệ</h3></div>";
    include 'includes/footer.php'; exit();
}

// --- 2. LẤY THÔNG TIN SẢN PHẨM ---
$sql = "SELECT * FROM SanPham WHERE Id = $productId AND HienThi = 1";
$res = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($res);
if (!$product) {
    echo "<div class='container py-5 text-center'><h3>Không tìm thấy sản phẩm</h3></div>";
    include 'includes/footer.php'; exit();
}

// --- 2.0 KIỂM TRA NGƯỜI DÙNG ĐÃ ĐÁNH GIÁ CHƯA ---
$hasReviewed = false;
if (isset($_SESSION['user_id'])) {
    $checkUid = $_SESSION['user_id'];
    $sqlCheck = "SELECT Id FROM danhgiasanpham WHERE IdNguoiDung = $checkUid AND IdSanPham = $productId";
    $resCheck = mysqli_query($conn, $sqlCheck);
    if (mysqli_num_rows($resCheck) > 0) {
        $hasReviewed = true;
    }
}

// --- 2.1 XỬ LÝ GỬI ĐÁNH GIÁ ---
$reviewMsg = "";
if (isset($_POST['submit_review'])) {
    if (isset($_SESSION['user_id'])) { 
        
        if ($hasReviewed) {
             $reviewMsg = "<div class='alert alert-warning'>Bạn đã đánh giá sản phẩm này rồi!</div>";
        } else {
            $userId = $_SESSION['user_id'];
            $rating = intval($_POST['rating']);
            $title = mysqli_real_escape_string($conn, $_POST['title']);
            $content = mysqli_real_escape_string($conn, $_POST['content']);
            $currentDate = date('Y-m-d H:i:s');
            
            if ($rating < 1 || $rating > 5) $rating = 5;

            $sqlReview = "INSERT INTO danhgiasanpham (IdSanPham, IdNguoiDung, IdDonHang, SoSao, TieuDe, NoiDung, TrangThai, NgayDanhGia) 
                          VALUES ($productId, $userId, NULL, $rating, '$title', '$content', 1, '$currentDate')";
            
            if (mysqli_query($conn, $sqlReview)) {
                $reviewMsg = "<div class='alert alert-success'>Cảm ơn bạn đã đánh giá sản phẩm!</div>";
                $hasReviewed = true; 
            } else {
                $reviewMsg = "<div class='alert alert-danger'>Lỗi: " . mysqli_error($conn) . "</div>";
            }
        }
    } else {
        $reviewMsg = "<div class='alert alert-warning'>Vui lòng đăng nhập để đánh giá.</div>";
    }
}

// --- 2.2 LẤY DANH SÁCH ĐÁNH GIÁ ---
$sqlGetReviews = "SELECT d.*, n.HoTen 
                  FROM danhgiasanpham d 
                  LEFT JOIN nguoidung n ON d.IdNguoiDung = n.Id 
                  WHERE d.IdSanPham = $productId AND d.TrangThai = 1 
                  ORDER BY d.NgayDanhGia DESC";
$resReviews = mysqli_query($conn, $sqlGetReviews);
$reviewsList = [];
$totalStars = 0;
$countReviews = 0;

while ($row = mysqli_fetch_assoc($resReviews)) {
    $reviewsList[] = $row;
    $totalStars += $row['SoSao'];
    $countReviews++;
}

$avgRating = ($countReviews > 0) ? round($totalStars / $countReviews, 1) : 0;

// --- 3. LẤY ẢNH GALLERY ---
$resImgs = mysqli_query($conn, "SELECT * FROM AnhSanPham WHERE IdSanPham = $productId ORDER BY LaAnhChinh DESC");
$galleryImages = [];
while ($row = mysqli_fetch_assoc($resImgs)) { $galleryImages[] = $row['DuongDanAnh']; }
if (empty($galleryImages)) $galleryImages[] = "default.png";

// --- 4. LẤY BIẾN THỂ VÀ MAP ẢNH ---
$sqlVariants = "SELECT ct.IdMauSac, ct.IdKichThuoc, ct.SoLuong, ct.AnhBienThe,
                        m.TenMau, k.TenKichThuoc 
                 FROM chitietsanpham ct
                 JOIN mausac m ON ct.IdMauSac = m.Id
                 JOIN kichthuoc k ON ct.IdKichThuoc = k.Id
                 WHERE ct.IdSanPham = $productId AND ct.HienThi = 1";
$resVar = mysqli_query($conn, $sqlVariants);

$variants = [];      
$listColors = [];    
$listSizes = [];
$colorImageMap = []; 

while ($row = mysqli_fetch_assoc($resVar)) {
    $variants[] = $row;
    
    if (!isset($listColors[$row['IdMauSac']])) {
        $listColors[$row['IdMauSac']] = $row['TenMau'];
    }
    if (!isset($listSizes[$row['IdKichThuoc']])) {
        $listSizes[$row['IdKichThuoc']] = $row['TenKichThuoc'];
    }
    
    if (!empty($row['AnhBienThe']) && !isset($colorImageMap[$row['IdMauSac']])) {
        $colorImageMap[$row['IdMauSac']] = $row['AnhBienThe'];
    }
}

$variantImagesOnly = array_values($colorImageMap);
$variantImagesOnly = array_unique($variantImagesOnly);

if (!empty($variantImagesOnly)) {
    $displayImages = $variantImagesOnly;
} else {
    $displayImages = $galleryImages;
}

$jsonVariants = json_encode($variants);
$jsonColorMap = json_encode($colorImageMap);
?>


<div class="container py-5">
    <div class="row g-5">
        <div class="col-md-6">
            <div class="detail-gallery-container">
                <div class="gallery-sidebar no-scrollbar">
                    <?php 
                    $idx = 0;
                    foreach ($displayImages as $img): 
                        $activeClass = ($idx === 0) ? 'active' : '';
                    ?>
                        <img src="uploads/<?php echo $img; ?>" 
                             class="gallery-thumb <?php echo $activeClass; ?>" 
                             onclick="changeMainImage('uploads/<?php echo $img; ?>', this)"
                             data-filename="<?php echo $img; ?>"> 
                    <?php 
                        $idx++;
                    endforeach; 
                    ?>
                </div>
                <div class="gallery-main">
                    <img id="mainImage" src="uploads/<?php echo $displayImages[0]; ?>">
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <h2 class="fw-bold"><?php echo $product['TenSanPham']; ?></h2>
            <div class="text-muted small mb-3">
                Mã SP: <strong><?php echo $product['MaSanPham']; ?></strong> | 
                Đã bán: <?php echo $product['DaBan']; ?> | 
                <span class="star-display"><i class="fas fa-star"></i> <?php echo $avgRating; ?>/5</span> (<?php echo $countReviews; ?> đánh giá)
            </div>
            
            <div class="fs-3 fw-bold text-danger mb-4">
                <?php echo number_format($product['GiaGoc'], 0, ',', '.'); ?>đ
            </div>

            <form action="add_to_cart.php" method="POST" id="formAddToCart">
                <input type="hidden" name="product_id" value="<?php echo $product['Id']; ?>">
                <input type="hidden" name="color_id" id="inputColorId" required>
                <input type="hidden" name="size_id" id="inputSizeId" required>

                <?php if (!empty($listColors)): ?>
                <div class="mb-3">
                    <label class="fw-bold mb-2">Màu sắc:</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($listColors as $id => $name): ?>
                            <button type="button" class="btn btn-outline-dark btn-option btn-color" 
                                    data-id="<?php echo $id; ?>" 
                                    onclick="selectColor(this, <?php echo $id; ?>)">
                                <?php echo $name; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($listSizes)): ?>
                <div class="mb-3">
                    <label class="fw-bold mb-2">Kích thước:</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($listSizes as $id => $name): ?>
                            <button type="button" class="btn btn-outline-dark btn-option btn-size disabled" 
                                    data-id="<?php echo $id; ?>" 
                                    onclick="selectSize(this, <?php echo $id; ?>)" disabled>
                                <?php echo $name; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="d-flex align-items-center mb-4 mt-4">
                    <label class="fw-bold me-3">Số lượng:</label>
                    <div class="input-group" style="width: 130px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(-1)">-</button>
                        <input type="number" name="quantity" id="qtyInput" class="form-control text-center" value="1" min="1" max="1" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(1)">+</button>
                    </div>
                    <span class="ms-3 text-muted small" id="stockLabel" style="display: none;">(Có sẵn: <span id="stockQty">0</span>)</span>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="add_to_cart" id="btnSubmit" class="btn btn-dark flex-grow-1 py-3 fw-bold" disabled>
                        <i class="fas fa-shopping-cart me-2"></i> THÊM VÀO GIỎ
                    </button>
                    <button type="submit" name="buy_now" id="btnBuyNow" class="btn btn-outline-dark py-3 fw-bold" disabled>
                        MUA NGAY
                    </button>
                </div>
            </form>
            <div id="msgBox" class="text-danger mt-2 fw-bold"></div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTab" role="tablist">
                <li class="nav-item"><button class="nav-link active fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#desc">MÔ TẢ SẢN PHẨM</button></li>
                <li class="nav-item"><button class="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#policy">CHÍNH SÁCH ĐỔI TRẢ</button></li>
                <li class="nav-item"><button class="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#review">ĐÁNH GIÁ (<?php echo $countReviews; ?>)</button></li>
            </ul>
            
            <div class="tab-content p-4 border border-top-0 bg-white">
                
                <div class="tab-pane fade show active" id="desc">
                    <div class="product-description-content">
                         <?php echo !empty($product['MoTaChiTiet']) ? $product['MoTaChiTiet'] : "<p>Đang cập nhật...</p>"; ?>
                    </div>
                </div>

                <div class="tab-pane fade" id="policy">
                    <h6>1. Điều kiện đổi trả</h6>
                    <p>Khách hàng có thể đổi sản phẩm trong vòng 10 ngày kể từ ngày nhận hàng.</p>
                    <p>Sản phẩm phải chưa qua sử dụng, còn nguyên tem mác như ban đầu.</p>
                    <h6>2. Chi phí đổi hàng</h6>
                    <p>Phí vận chuyển đổi hàng chỉ 20.000 VNĐ (áp dụng khi khách hàng đổi ý).</p>
                    <p>SomeHow sẽ miễn phí giao lại hàng mới khi khách hàng đã gửi hàng đổi trả.</p>
                </div>

                <div class="tab-pane fade" id="review">
                    <div class="row">
                        <div class="col-md-4 mb-4 text-center border-end">
                            <h1 class="display-3 fw-bold text-warning"><?php echo $avgRating; ?>/5</h1>
                            <div class="star-display fs-4 mb-2">
                                <?php 
                                for($i=1; $i<=5; $i++) {
                                    if($i <= round($avgRating)) echo '<i class="fas fa-star"></i>';
                                    else echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <p class="text-muted">Dựa trên <?php echo $countReviews; ?> đánh giá</p>
                        </div>

                        <div class="col-md-8">
                            <?php echo $reviewMsg; ?>
                            
                            <div class="card mb-4 bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold mb-3">Gửi đánh giá của bạn</h5>
                                    
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                        <?php if($hasReviewed): ?>
                                            <div class="alert alert-info text-center border-0 shadow-sm">
                                                <i class="fas fa-check-circle text-success fs-3 mb-2"></i><br>
                                                <strong>Bạn đã đánh giá sản phẩm này rồi.</strong><br>
                                                Cảm ơn bạn đã chia sẻ ý kiến!
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="">
                                                <div class="mb-2">
                                                    <label class="fw-bold">Chọn mức đánh giá:</label>
                                                    <div class="rating">
                                                        <input type="radio" name="rating" id="star5" value="5" checked onchange="updateTitle(5)"><label for="star5" title="Tuyệt vời">★</label>
                                                        <input type="radio" name="rating" id="star4" value="4" onchange="updateTitle(4)"><label for="star4" title="Tốt">★</label>
                                                        <input type="radio" name="rating" id="star3" value="3" onchange="updateTitle(3)"><label for="star3" title="Bình thường">★</label>
                                                        <input type="radio" name="rating" id="star2" value="2" onchange="updateTitle(2)"><label for="star2" title="Kém">★</label>
                                                        <input type="radio" name="rating" id="star1" value="1" onchange="updateTitle(1)"><label for="star1" title="Rất tệ">★</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <input type="text" name="title" id="reviewTitle" class="form-control" value="Rất hài lòng" readonly required>
                                                </div>

                                                <div class="mb-3">
                                                    <textarea name="content" class="form-control" rows="3" placeholder="Chia sẻ cảm nhận chi tiết..." required></textarea>
                                                </div>
                                                <button type="submit" name="submit_review" class="btn btn-primary">Gửi đánh giá</button>
                                            </form>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <p class="mb-0">Vui lòng <a href="login.php" class="text-primary fw-bold">đăng nhập</a> để viết đánh giá.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>

                            <div class="review-list mt-4">
                                <?php if($countReviews > 0): ?>
                                    <?php foreach($reviewsList as $rv): ?>
                                        <div class="mb-4 pb-3 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="fw-bold mb-0">
                                                    <?php echo !empty($rv['HoTen']) ? htmlspecialchars($rv['HoTen']) : 'Khách hàng ẩn danh'; ?>
                                                </h6>
                                                <span class="review-date"><?php echo date('d/m/Y H:i', strtotime($rv['NgayDanhGia'])); ?></span>
                                            </div>
                                            <div class="star-display small mb-2">
                                                <?php for($k=1; $k<=5; $k++) {
                                                    echo ($k <= $rv['SoSao']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                                } ?>
                                            </div>

                                            <?php if(!empty($rv['TieuDe'])): ?>
                                                <strong class="d-block mb-1"><?php echo htmlspecialchars($rv['TieuDe']); ?></strong>
                                            <?php endif; ?>

                                            <p class="mb-2 text-secondary"><?php echo nl2br(htmlspecialchars($rv['NoiDung'])); ?></p>
                                            
                                            <?php if(!empty($rv['PhanHoiQuanTri'])): ?>
                                                <div class="admin-reply p-3 mt-2 rounded">
                                                    <strong class="text-primary"><i class="fas fa-headset me-1"></i> NovaWear phản hồi:</strong>
                                                    <p class="mb-0 mt-1 text-dark small"><?php echo nl2br(htmlspecialchars($rv['PhanHoiQuanTri'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted">Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php
    $currentCatId = $product['IdDanhMuc'];
    $sqlRelated = "SELECT s.*, a.DuongDanAnh 
                   FROM SanPham s 
                   LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
                   WHERE s.IdDanhMuc = $currentCatId AND s.Id != $productId AND s.HienThi = 1 
                   ORDER BY RAND() LIMIT 10"; 
    $resRelated = mysqli_query($conn, $sqlRelated);
    
    if (mysqli_num_rows($resRelated) > 0):
    ?>
    <div class="mt-5 mb-5 position-relative">
        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h3 class="fw-bold text-uppercase m-0">Sản phẩm cùng loại</h3>
            <div class="d-flex gap-2">
                <button class="slider-btn" onclick="scrollRelated(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="slider-btn" onclick="scrollRelated(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>

        <div class="d-flex overflow-auto gap-3 product-slider pb-2" id="relatedSlider" style="scroll-behavior: smooth;">
            <?php while($rel = mysqli_fetch_assoc($resRelated)): ?>
                <?php 
                    $imgRel = !empty($rel['DuongDanAnh']) ? "uploads/".$rel['DuongDanAnh'] : "https://via.placeholder.com/300x300";
                    $priceRel = ($rel['GiaKhuyenMai'] > 0 && $rel['GiaKhuyenMai'] < $rel['GiaGoc']) ? $rel['GiaKhuyenMai'] : $rel['GiaGoc'];
                ?>
                <div class="card border-0 shadow-sm slider-item">
                    <a href="product_detail.php?id=<?php echo $rel['Id']; ?>" class="overflow-hidden position-relative d-block">
                        <img src="<?php echo $imgRel; ?>" class="card-img-top" alt="<?php echo $rel['TenSanPham']; ?>" style="height: 300px; object-fit: cover;">
                        <?php if($rel['SanPhamMoi']): ?>
                            <span class="badge bg-primary position-absolute top-0 end-0 m-2">Mới</span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body text-center">
                        <h6 class="card-title text-truncate">
                            <a href="product_detail.php?id=<?php echo $rel['Id']; ?>" class="text-decoration-none text-dark fw-bold">
                                <?php echo $rel['TenSanPham']; ?>
                            </a>
                        </h6>
                        <div class="text-danger fw-bold">
                            <?php echo number_format($priceRel, 0, ',', '.'); ?>đ
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // 1. Slider Logic
    function scrollRelated(direction) {
        const container = document.getElementById('relatedSlider');
        const scrollAmount = 300; 
        container.scrollLeft += direction * scrollAmount;
    }

    // 2. Hàm đổi ảnh chính
    function changeMainImage(src, thumbEl) {
        document.getElementById('mainImage').src = src;
        if(thumbEl) {
            document.querySelectorAll('.gallery-thumb').forEach(el => el.classList.remove('active'));
            thumbEl.classList.add('active');
        }
    }

    // 3. Logic Biến thể
    const variants = <?php echo $jsonVariants; ?>;
    const colorMap = <?php echo $jsonColorMap; ?>; 
    let selectedColor = null;
    let selectedSize = null;

    function selectColor(btn, colorId) {
        document.querySelectorAll('.btn-color').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        selectedColor = colorId;
        document.getElementById('inputColorId').value = colorId;

        if(colorMap[colorId]) {
            const imgFileName = colorMap[colorId];
            const fullSrc = 'uploads/' + imgFileName;
            changeMainImage(fullSrc, null); 
            const thumbs = document.querySelectorAll('.gallery-thumb');
            thumbs.forEach(thumb => {
                if(thumb.dataset.filename === imgFileName) {
                    thumb.classList.add('active');
                    thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    thumb.classList.remove('active');
                }
            });
        }

        selectedSize = null;
        document.getElementById('inputSizeId').value = '';
        document.querySelectorAll('.btn-size').forEach(el => {
            el.classList.remove('active');
            el.classList.add('disabled');
            el.disabled = true;
        });

        const availableSizes = [];
        variants.forEach(v => {
            if (v.IdMauSac == colorId && parseInt(v.SoLuong) > 0) availableSizes.push(v.IdKichThuoc);
        });

        document.querySelectorAll('.btn-size').forEach(el => {
            if (availableSizes.includes(el.getAttribute('data-id'))) {
                el.classList.remove('disabled');
                el.disabled = false;
            }
        });
        checkStock();
    }

    function selectSize(btn, sizeId) {
        document.querySelectorAll('.btn-size').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        selectedSize = sizeId;
        document.getElementById('inputSizeId').value = sizeId;
        checkStock();
    }

    function checkStock() {
        const stockLabel = document.getElementById('stockLabel');
        const stockQty = document.getElementById('stockQty');
        const btnSubmit = document.getElementById('btnSubmit');
        const btnBuyNow = document.getElementById('btnBuyNow');
        const qtyInput = document.getElementById('qtyInput');

        if (selectedColor && selectedSize) {
            const variant = variants.find(v => v.IdMauSac == selectedColor && v.IdKichThuoc == selectedSize);
            if (variant) {
                const qty = parseInt(variant.SoLuong);
                stockLabel.style.display = 'inline';
                stockQty.innerText = qty;
                qtyInput.max = qty;
                qtyInput.value = 1;
                btnSubmit.disabled = false;
                btnBuyNow.disabled = false;
            }
        } else {
            stockLabel.style.display = 'none';
            btnSubmit.disabled = true;
            btnBuyNow.disabled = true;
        }
    }

    function changeQty(delta) {
        const input = document.getElementById('qtyInput');
        let newVal = parseInt(input.value) + delta;
        let max = parseInt(input.max) || 1;
        if (newVal >= 1 && newVal <= max) input.value = newVal;
    }

    // 4. Update Title Review
    const titles = {
        1: "Rất không hài lòng",
        2: "Không hài lòng",
        3: "Bình thường",
        4: "Hài lòng",
        5: "Rất hài lòng"
    };
    function updateTitle(star) {
        document.getElementById('reviewTitle').value = titles[star];
    }
</script>

<?php include 'includes/footer.php'; ?>