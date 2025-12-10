<?php
// 1. Khởi động session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Khởi tạo giỏ nếu chưa có
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ==========================================================================
// PHẦN A: XỬ LÝ THÊM VÀO GIỎ (Lấy cả Tên Màu & Tên Size)
// ==========================================================================
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
    
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty       = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $colorId   = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
    $sizeId    = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;

    if ($productId > 0) {
        // Lấy thông tin sản phẩm
        $sql = "SELECT * FROM SanPham WHERE Id = $productId LIMIT 1";
        $result = mysqli_query($conn, $sql);
        $product = mysqli_fetch_assoc($result);

        if ($product) {
            // Lấy ảnh đại diện
            $sqlImg = "SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = $productId AND LaAnhChinh = 1 LIMIT 1";
            $resImg = mysqli_query($conn, $sqlImg);
            $rowImg = mysqli_fetch_assoc($resImg);
            $image  = $rowImg ? $rowImg['DuongDanAnh'] : 'default.png';

            // Lấy giá (Ưu tiên giá khuyến mãi)
            $price = $product['GiaGoc'];
            if ($product['GiaKhuyenMai'] > 0 && $product['GiaKhuyenMai'] < $product['GiaGoc']) {
                $price = $product['GiaKhuyenMai'];
            }

            // --- QUAN TRỌNG: TRUY VẤN TÊN MÀU & SIZE TỪ DB ---
            $colorName = "";
            $sizeName  = "";
            
            // Lưu ý: Tên bảng là 'mausac' và 'kichthuoc' (chữ thường) khớp với ảnh DB của bạn
            if ($colorId > 0) {
                $rc = mysqli_query($conn, "SELECT TenMau FROM mausac WHERE Id = $colorId");
                if ($rowC = mysqli_fetch_assoc($rc)) $colorName = $rowC['TenMau'];
            }
            if ($sizeId > 0) {
                $rs = mysqli_query($conn, "SELECT TenKichThuoc FROM kichthuoc WHERE Id = $sizeId");
                if ($rowS = mysqli_fetch_assoc($rs)) $sizeName = $rowS['TenKichThuoc'];
            }

            // Tạo Key duy nhất (ID_Màu_Size)
            $cartKey = $productId . '_' . $colorId . '_' . $sizeId;

            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId,
                    'name'       => $product['TenSanPham'],
                    'price'      => $price,
                    'image'      => $image,
                    'qty'        => $qty,
                    'color_id'   => $colorId,
                    'size_id'    => $sizeId,
                    'color_name' => $colorName, // Lưu tên màu vào session
                    'size_name'  => $sizeName   // Lưu tên size vào session
                ];
            }

            if (isset($_POST['buy_now'])) {
                header('Location: checkout.php');
                exit();
            } else {
                $_SESSION['cart_success'] = "Đã thêm sản phẩm vào giỏ!";
                header('Location: cart.php'); 
                exit();
            }
        }
    }
}

// ==========================================================================
// PHẦN B: CẬP NHẬT GIỎ HÀNG
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $key => $q) {
        $q = intval($q);
        if (isset($_SESSION['cart'][$key])) {
            if ($q <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['qty'] = $q;
            }
        }
    }
    $_SESSION['cart_success'] = 'Đã cập nhật giỏ hàng!';
    header('Location: cart.php');
    exit;
}

// Lấy cart: DB khi login, session khi offline
$cart = [];
if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
    require_once __DIR__ . '/includes/cart_functions.php';
    $cart = get_cart_items_db(intval($_SESSION['user_id']));
} else {
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $cart = &$_SESSION['cart'];
}

// Giờ mới require header
require_once __DIR__ . '/includes/header.php';

$cart = &$_SESSION['cart'];
$msg = '';
if (isset($_SESSION['cart_success'])) {
    $msg = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
}

$subtotal = 0;
foreach ($cart as $it) {
    $subtotal += ($it['price'] ?? 0) * ($it['qty'] ?? 1);
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h3 class="fw-bold text-uppercase mb-4">Giỏ hàng của bạn</h3>
            
            <?php if ($msg): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart)): ?>
                <div class="text-center py-5 border rounded bg-white shadow-sm">
                    <div class="mb-3"><i class="fas fa-shopping-bag fa-4x text-muted opacity-25"></i></div>
                    <h5 class="text-muted mb-4">Giỏ hàng chưa có sản phẩm nào.</h5>
                    <a href="index.php" class="btn btn-dark px-4 py-2 fw-bold">TIẾP TỤC MUA SẮM</a>
                </div>
            <?php else: ?>
                <form method="post" action="cart.php">
                    <input type="hidden" name="update_cart" value="1">
                    
                    <div class="table-responsive mb-4 shadow-sm border rounded bg-white">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-uppercase small fw-bold text-muted">
                                    <th class="ps-4 py-3">Sản phẩm</th>
                                    <th style="width:150px">Phân loại</th>
                                    <th style="width:120px">Đơn giá</th>
                                    <th style="width:140px" class="text-center">Số lượng</th>
                                    <th style="width:120px">Thành tiền</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $key => $it): ?>
                                    <?php $itemKey = isset($it['key']) ? $it['key'] : $key; ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="border rounded overflow-hidden flex-shrink-0" style="width:70px; height:70px;">
                                                    <?php if (!empty($it['image'])): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($it['image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary w-100 h-100 d-flex align-items-center justify-content-center text-white small">No IMG</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ms-3">
                                                    <a href="product_detail.php?id=<?php echo $it['product_id']; ?>" class="text-decoration-none text-dark fw-bold">
                                                        <?php echo htmlspecialchars($it['name']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <?php if (!empty($it['color_name'])): ?>
                                                    <span class="badge bg-light text-dark border w-fit-content">
                                                        Màu: <?php echo htmlspecialchars($it['color_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($it['size_name'])): ?>
                                                    <span class="badge bg-light text-dark border w-fit-content">
                                                        Size: <?php echo htmlspecialchars($it['size_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if (empty($it['color_name']) && empty($it['size_name'])): ?>
                                                    <span class="text-muted small fst-italic">Mặc định</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="fw-bold text-secondary"><?php echo number_format($it['price'], 0, ',', '.'); ?>đ</td>
                                        
                                        <td class="text-center">
                                            <div class="input-group input-group-sm justify-content-center" style="width: 100px; margin: 0 auto;">
                                                <input type="number" name="qty[<?php echo htmlspecialchars($key); ?>]" value="<?php echo intval($it['qty']); ?>" min="1" class="form-control text-center fw-bold border-secondary">
                                            </div>
                                        </td>
                                        
                                        <td class="fw-bold text-danger"><?php echo number_format($it['price'] * $it['qty'], 0, ',', '.'); ?>đ</td>
                                        
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-sm text-danger hover-bg-light rounded-circle p-2" onclick="deleteItem('<?php echo htmlspecialchars($key); ?>')"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row align-items-center">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <a href="index.php" class="btn btn-outline-dark me-2 rounded-0"><i class="fas fa-arrow-left me-2"></i> Tiếp tục mua</a>
                            <button type="submit" class="btn btn-dark rounded-0"><i class="fas fa-sync-alt me-2"></i> Cập nhật giỏ</button>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-0 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="h5 mb-0 fw-bold">Tổng cộng:</span>
                                    <span class="h4 mb-0 fw-bold text-danger"><?php echo number_format($subtotal, 0, ',', '.'); ?>đ</span>
                                </div>
                                <a href="checkout.php" class="btn btn-dark w-100 py-2 fw-bold text-uppercase fs-6">Tiến hành thanh toán</a>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" action="remove_from_cart.php" style="display:none;">
    <input type="hidden" name="key" id="deleteKey" value="">
</form>

<script>
function deleteItem(key) {
    if (confirm('Bạn chắc chắn muốn bỏ sản phẩm này?')) {
        document.getElementById('deleteKey').value = key;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<style>
    .w-fit-content { width: fit-content; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>