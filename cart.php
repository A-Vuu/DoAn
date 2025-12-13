<?php
// FILE: cart.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// ==========================================================================
// 1. HÀM LẤY CHI TIẾT GIỎ HÀNG
// ==========================================================================
function get_db_cart_details($conn, $userId) {
    $cart = [];
    
    // Tìm ID giỏ hàng
    $sqlCart = "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1";
    $resCart = mysqli_query($conn, $sqlCart);
    
    if ($rowCart = mysqli_fetch_assoc($resCart)) {
        $cartId = $rowCart['Id'];
        
        // Cập nhật ngày
        mysqli_query($conn, "UPDATE GioHang SET NgayCapNhat = NOW() WHERE Id = $cartId");
        
        // Lấy sản phẩm và thông tin biến thể
        $sql = "SELECT ct.*, 
                       sp.TenSanPham, sp.GiaGoc, sp.GiaKhuyenMai, 
                       asp.DuongDanAnh,
                       -- Thông tin biến thể từ bảng ChiTietSanPham
                       var.IdMauSac, var.IdKichThuoc,
                       ms.TenMau, kt.TenKichThuoc
                FROM ChiTietGioHang ct
                JOIN SanPham sp ON ct.IdSanPham = sp.Id
                LEFT JOIN AnhSanPham asp ON sp.Id = asp.IdSanPham AND asp.LaAnhChinh = 1
                
                -- JOIN để lấy Màu/Size chính xác
                LEFT JOIN ChiTietSanPham var ON ct.IdChiTietSanPham = var.Id
                LEFT JOIN MauSac ms ON var.IdMauSac = ms.Id
                LEFT JOIN KichThuoc kt ON var.IdKichThuoc = kt.Id
                
                WHERE ct.IdGioHang = $cartId";
                
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $price = ($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']) ? $row['GiaKhuyenMai'] : $row['GiaGoc'];
                
                $cId = $row['IdMauSac'] ? $row['IdMauSac'] : 0;
                $sId = $row['IdKichThuoc'] ? $row['IdKichThuoc'] : 0;
                
                // Key duy nhất: IDSP_Màu_Size
                $key = $row['IdSanPham'] . '_' . $cId . '_' . $sId;
                
                $cart[$key] = [
                    'key' => $key,
                    'product_id' => $row['IdSanPham'],
                    'name' => $row['TenSanPham'],
                    'price' => $price,
                    'image' => $row['DuongDanAnh'] ? $row['DuongDanAnh'] : 'default.png',
                    'qty' => $row['SoLuong'],
                    'color_id' => $cId,
                    'size_id' => $sId,
                    'color_name' => $row['TenMau'],
                    'size_name' => $row['TenKichThuoc']
                ];
            }
        }
    }
    return $cart;
}

// ==========================================================================
// 2. KHỞI TẠO
// ==========================================================================
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;


// ==========================================================================
// 3. XỬ LÝ CẬP NHẬT GIỎ HÀNG (QUAN TRỌNG)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    
    // Lấy ID Giỏ hàng nếu đã đăng nhập
    $dbCartId = 0;
    if ($userId > 0) {
        $r = mysqli_query($conn, "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1");
        if ($row = mysqli_fetch_assoc($r)) $dbCartId = $row['Id'];
    }

    foreach ($_POST['qty'] as $key => $q) {
        $q = intval($q);
        
        // --- A. XỬ LÝ CHO KHÁCH (SESSION) ---
        if (isset($_SESSION['cart'][$key])) {
            if ($q <= 0) unset($_SESSION['cart'][$key]); 
            else $_SESSION['cart'][$key]['qty'] = $q;
        }
        
        // --- B. XỬ LÝ CHO THÀNH VIÊN (DATABASE) ---
        if ($userId > 0 && $dbCartId > 0) {
            $parts = explode('_', $key);
            if (count($parts) == 3) {
                $pId = intval($parts[0]);
                $cId = intval($parts[1]);
                $sId = intval($parts[2]);
                
                // 1. Tìm lại ID biến thể (IdChiTietSanPham) dựa trên Màu/Size trong Key
                $varId = "NULL";
                if ($cId > 0 && $sId > 0) {
                    $sqlFindVar = "SELECT Id FROM ChiTietSanPham WHERE IdSanPham=$pId AND IdMauSac=$cId AND IdKichThuoc=$sId LIMIT 1";
                    $resVar = mysqli_query($conn, $sqlFindVar);
                    if ($rowVar = mysqli_fetch_assoc($resVar)) {
                        $varId = $rowVar['Id'];
                    }
                }
                
                // 2. Tạo điều kiện WHERE để tìm đúng dòng trong ChiTietGioHang
                $whereClause = "IdGioHang = $dbCartId AND IdSanPham = $pId";
                
                if ($varId !== "NULL") {
                    $whereClause .= " AND IdChiTietSanPham = $varId";
                } else {
                    $whereClause .= " AND (IdChiTietSanPham IS NULL OR IdChiTietSanPham = 0)";
                }
                
                // 3. Thực hiện UPDATE hoặc DELETE
                if ($q <= 0) {
                    mysqli_query($conn, "DELETE FROM ChiTietGioHang WHERE $whereClause");
                } else {
                    mysqli_query($conn, "UPDATE ChiTietGioHang SET SoLuong = $q WHERE $whereClause");
                }
            }
        }
    }
    
    $_SESSION['cart_success'] = 'Đã cập nhật giỏ hàng!';
    header('Location: cart.php'); exit;
}


// ==========================================================================
// 4. HIỂN THỊ
// ==========================================================================
if ($userId > 0) {
    $cart = get_db_cart_details($conn, $userId);
    $_SESSION['cart'] = $cart; // Đồng bộ ngược lại session
} else {
    $cart = $_SESSION['cart'];
}

require_once __DIR__ . '/includes/header.php';
$subtotal = 0;
foreach ($cart as $it) { $subtotal += ($it['price'] * $it['qty']); }
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <h3 class="fw-bold text-uppercase mb-4">Giỏ hàng của bạn</h3>
            
            <?php if (isset($_SESSION['cart_success'])): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['cart_success']; unset($_SESSION['cart_success']); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart)): ?>
                <div class="text-center py-5 border rounded bg-white shadow-sm">
                    <div class="mb-3"><i class="fas fa-shopping-bag fa-4x text-muted opacity-25"></i></div>
                    <h5 class="text-muted mb-4">Giỏ hàng chưa có sản phẩm nào.</h5>
                    <a href="index.php" class="btn btn-dark px-4 py-2 fw-bold">TIẾP TỤC MUA SẮM</a>
                </div>
            <?php else: ?>
                <form method="post" action="cart.php" id="cartForm">
                    <input type="hidden" name="update_cart" value="1">
                    
                    <div class="table-responsive mb-4 shadow-sm border rounded bg-white">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr class="text-uppercase small fw-bold text-muted">
                                    <th class="ps-4 py-3">Sản phẩm</th>
                                    <th>Phân loại</th>
                                    <th>Đơn giá</th>
                                    <th class="text-center">Số lượng</th>
                                    <th>Thành tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $it): ?>
                                    <?php $uniqueKey = $it['key']; ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="border rounded overflow-hidden" style="width:70px; height:70px;">
                                                    <img src="uploads/<?php echo htmlspecialchars($it['image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                </div>
                                                <div class="ms-3">
                                                    <a href="product_detail.php?id=<?php echo $it['product_id']; ?>" class="fw-bold text-dark text-decoration-none"><?php echo htmlspecialchars($it['name']); ?></a>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($it['color_name'])): ?>
                                                <div class="small text-muted">Màu: <span class="text-dark fw-bold"><?php echo $it['color_name']; ?></span></div>
                                            <?php endif; ?>
                                            <?php if (!empty($it['size_name'])): ?>
                                                <div class="small text-muted">Size: <span class="text-dark fw-bold"><?php echo $it['size_name']; ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-secondary"><?php echo number_format($it['price'], 0, ',', '.'); ?>đ</td>
                                        <td class="text-center">
                                            <input type="number" name="qty[<?php echo $uniqueKey; ?>]" value="<?php echo $it['qty']; ?>" min="1" class="form-control text-center fw-bold" style="width:70px; margin:auto;">
                                        </td>
                                        <td class="fw-bold text-danger"><?php echo number_format($it['price'] * $it['qty'], 0, ',', '.'); ?>đ</td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn text-danger" onclick="deleteItem('<?php echo $uniqueKey; ?>')"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-dark">Tiếp tục mua</a>
                        <div class="text-end">
                            <div class="h4 text-danger fw-bold mb-2">Tổng: <?php echo number_format($subtotal, 0, ',', '.'); ?>đ</div>
                            <button type="submit" class="btn btn-dark">Cập nhật giỏ</button>
                            <a href="checkout.php" class="btn btn-danger ms-2">Thanh toán</a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="post" action="cart.php" style="display:none;">
    <input type="hidden" name="update_cart" value="1">
</form>

<script>
function deleteItem(key) {
    if(confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) {
        var f = document.getElementById('deleteForm');
        // Tạo input ẩn chứa key của sản phẩm cần xóa với số lượng = 0
        var i = document.createElement('input'); 
        i.type = 'hidden'; 
        i.name = 'qty[' + key + ']'; 
        i.value = '0'; // Số lượng 0 nghĩa là xóa
        f.appendChild(i); 
        f.submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>