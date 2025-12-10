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

// --- 3. LẤY ẢNH SẢN PHẨM ---
$resImgs = mysqli_query($conn, "SELECT * FROM AnhSanPham WHERE IdSanPham = $productId ORDER BY LaAnhChinh DESC");
$images = [];
while ($row = mysqli_fetch_assoc($resImgs)) { $images[] = $row['DuongDanAnh']; }
if (empty($images)) $images[] = "default.png";

// --- 4. LẤY BIẾN THỂ (MÀU/SIZE) ---
// Dùng tên bảng chữ thường 'chitietsanpham' để khớp với ảnh Database bạn gửi
$sqlVariants = "SELECT ct.IdMauSac, ct.IdKichThuoc, ct.SoLuong, 
                       m.TenMau, k.TenKichThuoc 
                FROM chitietsanpham ct
                JOIN mausac m ON ct.IdMauSac = m.Id
                JOIN kichthuoc k ON ct.IdKichThuoc = k.Id
                WHERE ct.IdSanPham = $productId AND ct.HienThi = 1";
$resVar = mysqli_query($conn, $sqlVariants);

$variants = [];      
$listColors = [];    
$listSizes = [];     

while ($row = mysqli_fetch_assoc($resVar)) {
    $variants[] = $row;
    
    // Lưu màu duy nhất
    if (!isset($listColors[$row['IdMauSac']])) {
        $listColors[$row['IdMauSac']] = $row['TenMau'];
    }
    // Lưu size duy nhất
    if (!isset($listSizes[$row['IdKichThuoc']])) {
        $listSizes[$row['IdKichThuoc']] = $row['TenKichThuoc'];
    }
}
$jsonVariants = json_encode($variants);
?>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-md-6">
            <div class="border rounded overflow-hidden mb-2">
                <img id="mainImage" src="uploads/<?php echo $images[0]; ?>" class="w-100" style="height: 500px; object-fit: contain; background-color: #fff;">
            </div>
            <?php if (count($images) > 1): ?>
                <div class="d-flex gap-2 overflow-auto">
                    <?php foreach ($images as $img): ?>
                        <img src="uploads/<?php echo $img; ?>" class="border rounded thumbnail-img" style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;" onclick="document.getElementById('mainImage').src=this.src">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

            <form action="cart.php" method="POST" id="formAddToCart">
                <input type="hidden" name="product_id" value="<?php echo $product['Id']; ?>">
                <input type="hidden" name="color_id" id="inputColorId" required>
                <input type="hidden" name="size_id" id="inputSizeId" required>

                <?php if (!empty($listColors)): ?>
                <div class="mb-3">
                    <label class="fw-bold mb-2">Màu sắc:</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($listColors as $id => $name): ?>
                            <button type="button" 
                                    class="btn btn-outline-dark btn-option btn-color" 
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
                            <button type="button" 
                                    class="btn btn-outline-dark btn-option btn-size disabled" 
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
                    <span class="ms-3 text-muted small" id="stockLabel" style="display: none;">
                        (Có sẵn: <span id="stockQty">0</span>)
                    </span>
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
</div>

<script>
    const variants = <?php echo $jsonVariants; ?>;
    let selectedColor = null;
    let selectedSize = null;

    // 1. KHI CHỌN MÀU
    function selectColor(btn, colorId) {
        document.querySelectorAll('.btn-color').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        
        selectedColor = colorId;
        document.getElementById('inputColorId').value = colorId;

        // Reset Size
        selectedSize = null;
        document.getElementById('inputSizeId').value = '';
        document.querySelectorAll('.btn-size').forEach(el => {
            el.classList.remove('active');
            el.classList.add('disabled');
            el.disabled = true;
        });

        // Lọc size còn hàng
        const availableSizes = [];
        variants.forEach(v => {
            if (v.IdMauSac == colorId && parseInt(v.SoLuong) > 0) {
                availableSizes.push(v.IdKichThuoc);
            }
        });

        // Mở khóa size hợp lệ
        document.querySelectorAll('.btn-size').forEach(el => {
            const sizeId = el.getAttribute('data-id');
            if (availableSizes.includes(sizeId)) {
                el.classList.remove('disabled');
                el.disabled = false;
            }
        });

        checkStock();
    }

    // 2. KHI CHỌN SIZE
    function selectSize(btn, sizeId) {
        document.querySelectorAll('.btn-size').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        
        selectedSize = sizeId;
        document.getElementById('inputSizeId').value = sizeId;
        
        checkStock();
    }

    // 3. KIỂM TRA TỒN KHO & MỞ KHÓA NÚT
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

                // Mở khóa cả 2 nút
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-shopping-cart me-2"></i> THÊM VÀO GIỎ';
                
                btnBuyNow.disabled = false; // Mở khóa nút Mua ngay
            }
        } else {
            stockLabel.style.display = 'none';
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = 'Vui lòng chọn phân loại';
            btnBuyNow.disabled = true; // Khóa nút Mua ngay
        }
    }

    // 4. TĂNG GIẢM SỐ LƯỢNG
    function changeQty(delta) {
        const input = document.getElementById('qtyInput');
        let newVal = parseInt(input.value) + delta;
        let max = parseInt(input.max) || 1;
        
        if (newVal >= 1 && newVal <= max) {
            input.value = newVal;
        }
    }
</script>

<?php include 'includes/footer.php'; ?> 