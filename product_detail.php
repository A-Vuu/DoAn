<?php
session_start();
require_once 'config.php';
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
$colorImageMap = []; // Map: IdMau => AnhBienThe

while ($row = mysqli_fetch_assoc($resVar)) {
    $variants[] = $row;
    
    if (!isset($listColors[$row['IdMauSac']])) {
        $listColors[$row['IdMauSac']] = $row['TenMau'];
    }
    if (!isset($listSizes[$row['IdKichThuoc']])) {
        $listSizes[$row['IdKichThuoc']] = $row['TenKichThuoc'];
    }
    
    // Nếu biến thể có ảnh riêng, lưu vào map để JS đổi ảnh
    if (!empty($row['AnhBienThe']) && !isset($colorImageMap[$row['IdMauSac']])) {
        $colorImageMap[$row['IdMauSac']] = $row['AnhBienThe'];
    }
}

// Gộp ảnh gallery và ảnh biến thể để hiển thị thumbnail (tránh trùng)
$displayImages = $galleryImages; 
foreach($colorImageMap as $imgVar) {
    if(!in_array($imgVar, $displayImages)) {
        $displayImages[] = $imgVar;
    }
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
                Đã bán: <?php echo $product['DaBan']; ?>
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
                    <div class="input-group" style="width: 120px;">
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
            </ul>
            <div class="tab-content p-4 border border-top-0 bg-white">
                <div class="tab-pane fade show active" id="desc">
                    <?php echo !empty($product['MoTaChiTiet']) ? nl2br($product['MoTaChiTiet']) : "<p>Đang cập nhật...</p>"; ?>
                </div>
                <div class="tab-pane fade" id="policy">
                    <h6>1. Điều kiện đổi trả</h6>
                    <p>Sản phẩm còn nguyên tem mác, chưa qua sử dụng. Đổi trả trong 7 ngày.</p>
                    <h6>2. Phí giao hàng</h6>
                    <p>Miễn phí đổi hàng lỗi. Khách chịu phí ship nếu đổi size/màu.</p>
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

    // 2. Hàm đổi ảnh chính (dùng khi click thumb hoặc click màu)
    function changeMainImage(src, thumbEl) {
        document.getElementById('mainImage').src = src;
        
        // Highlight thumb nếu được click trực tiếp
        if(thumbEl) {
            document.querySelectorAll('.gallery-thumb').forEach(el => el.classList.remove('active'));
            thumbEl.classList.add('active');
        }
    }

    // 3. Logic Biến thể
    const variants = <?php echo $jsonVariants; ?>;
    const colorMap = <?php echo $jsonColorMap; ?>; // Map: IdMau -> TenFileAnh
    let selectedColor = null;
    let selectedSize = null;

    function selectColor(btn, colorId) {
        // Highlight nút màu
        document.querySelectorAll('.btn-color').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        selectedColor = colorId;
        document.getElementById('inputColorId').value = colorId;

        // Tự động chọn ảnh theo màu
        if(colorMap[colorId]) {
            const imgFileName = colorMap[colorId];
            const fullSrc = 'uploads/' + imgFileName;
            
            // Đổi ảnh chính
            changeMainImage(fullSrc, null); 

            // Highlight thumbnail bên trái
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

        // Reset Size
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
</script>

<?php include 'includes/footer.php'; ?>