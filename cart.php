<?php
// 1. Khởi động session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// ==========================================================================
// HÀM HỖ TRỢ DATABASE THEO CẤU TRÚC MỚI (GioHang -> ChiTietGioHang)
// ==========================================================================

// Hàm 1: Lấy hoặc Tạo ID Giỏ Hàng cho User
function get_or_create_cart_id($conn, $userId) {
    // 1. Tìm xem user đã có giỏ hàng chưa
    $sql = "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Đã có giỏ, cập nhật ngày và trả về ID
        $cartId = $row['Id'];
        mysqli_query($conn, "UPDATE GioHang SET NgayCapNhat = NOW() WHERE Id = $cartId");
        return $cartId;
    } else {
        // Chưa có, tạo giỏ hàng mới
        $sqlInsert = "INSERT INTO GioHang (IdNguoiDung, NgayTao, NgayCapNhat) VALUES ($userId, NOW(), NOW())";
        if (mysqli_query($conn, $sqlInsert)) {
            return mysqli_insert_id($conn); // Trả về ID vừa tạo
        }
    }
    return 0;
}

// Hàm 2: Lấy danh sách sản phẩm
function get_db_cart_details($conn, $userId) {
    $cart = [];
    
    // Tìm ID giỏ hàng
    $sqlCart = "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1";
    $resCart = mysqli_query($conn, $sqlCart);
    
    if ($rowCart = mysqli_fetch_assoc($resCart)) {
        $cartId = $rowCart['Id'];
        
        // Join bảng ChiTietGioHang với SanPham, MauSac, KichThuoc
        $sql = "SELECT ct.*, sp.TenSanPham, sp.GiaGoc, sp.GiaKhuyenMai, 
                asp.DuongDanAnh, m.TenMau, k.TenKichThuoc
                FROM ChiTietGioHang ct
                JOIN SanPham sp ON ct.IdSanPham = sp.Id
                LEFT JOIN AnhSanPham asp ON sp.Id = asp.IdSanPham AND asp.LaAnhChinh = 1
                LEFT JOIN Mausac m ON ct.IdMau = m.Id
                LEFT JOIN Kichthuoc k ON ct.IdSize = k.Id
                WHERE ct.IdGioHang = $cartId";
                
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $price = ($row['GiaKhuyenMai'] > 0 && $row['GiaKhuyenMai'] < $row['GiaGoc']) ? $row['GiaKhuyenMai'] : $row['GiaGoc'];
                
                // Key để xử lý logic session/update
                $key = $row['IdSanPham'] . '_' . $row['IdMau'] . '_' . $row['IdSize'];
                
                $cart[$key] = [
                    'key' => $key,
                    'product_id' => $row['IdSanPham'],
                    'name' => $row['TenSanPham'],
                    'price' => $price,
                    'image' => $row['DuongDanAnh'] ? $row['DuongDanAnh'] : 'default.png',
                    'qty' => $row['SoLuong'],
                    'color_id' => $row['IdMau'],
                    'size_id' => $row['IdSize'],
                    'color_name' => $row['TenMau'],
                    'size_name' => $row['TenKichThuoc']
                ];
            }
        }
    }
    return $cart;
}

// ==========================================================================
// KHỞI TẠO
// ==========================================================================
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// ==========================================================================
// PHẦN A: XỬ LÝ THÊM VÀO GIỎ
// ==========================================================================
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty       = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $colorId   = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
    $sizeId    = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;

    if ($productId > 0) {
        // 1. LẤY INFO ĐỂ LƯU SESSION (BACKUP)
        $sql = "SELECT * FROM SanPham WHERE Id = $productId LIMIT 1";
        $product = mysqli_fetch_assoc(mysqli_query($conn, $sql));
        
        if ($product) {
            // Lấy ảnh, màu, size text
            $sqlImg = "SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = $productId AND LaAnhChinh = 1 LIMIT 1";
            $rowImg = mysqli_fetch_assoc(mysqli_query($conn, $sqlImg));
            $image  = $rowImg ? $rowImg['DuongDanAnh'] : 'default.png';
            $price = ($product['GiaKhuyenMai'] > 0 && $product['GiaKhuyenMai'] < $product['GiaGoc']) ? $product['GiaKhuyenMai'] : $product['GiaGoc'];
            
            $colorName = ""; $sizeName = "";
            if ($colorId > 0) { $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT TenMau FROM Mausac WHERE Id=$colorId")); $colorName = $r['TenMau']; }
            if ($sizeId > 0)  { $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT TenKichThuoc FROM Kichthuoc WHERE Id=$sizeId")); $sizeName = $r['TenKichThuoc']; }

            $cartKey = $productId . '_' . $colorId . '_' . $sizeId;
            
            // Cập nhật Session
            if (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $productId, 'name' => $product['TenSanPham'], 'price' => $price, 
                    'image' => $image, 'qty' => $qty, 'color_id' => $colorId, 'size_id' => $sizeId, 
                    'color_name' => $colorName, 'size_name' => $sizeName
                ];
            }

            // 2. LƯU VÀO DATABASE (QUAN TRỌNG)
            if ($userId > 0) {
                // Bước 1: Lấy ID Giỏ Hàng (Bảng Cha)
                $cartId = get_or_create_cart_id($conn, $userId);
                
                if ($cartId > 0) {
                    // Bước 2: Kiểm tra sản phẩm trong ChiTietGioHang (Bảng Con)
                    $sqlCheck = "SELECT Id, SoLuong FROM ChiTietGioHang 
                                 WHERE IdGioHang = $cartId 
                                 AND IdSanPham = $productId 
                                 AND IdMau = $colorId 
                                 AND IdSize = $sizeId";
                    $rsCheck = mysqli_query($conn, $sqlCheck);
                    
                    if (mysqli_num_rows($rsCheck) > 0) {
                        // Update số lượng
                        $row = mysqli_fetch_assoc($rsCheck);
                        $newQty = $row['SoLuong'] + $qty;
                        mysqli_query($conn, "UPDATE ChiTietGioHang SET SoLuong = $newQty, Gia = $price WHERE Id = " . $row['Id']);
                    } else {
                        // Insert mới
                        $sqlInsert = "INSERT INTO ChiTietGioHang (IdGioHang, IdSanPham, SoLuong, Gia, IdMau, IdSize, NgayThem) 
                                      VALUES ($cartId, $productId, $qty, $price, $colorId, $sizeId, NOW())";
                        mysqli_query($conn, $sqlInsert);
                    }
                }
            }

            if (isset($_POST['buy_now'])) { header('Location: checkout.php'); exit(); }
            else { $_SESSION['cart_success'] = "Đã thêm vào giỏ!"; header('Location: cart.php'); exit(); }
        }
    }
}

// ==========================================================================
// PHẦN B: CẬP NHẬT GIỎ HÀNG
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $key => $q) {
        $q = intval($q);
        // 1. Session
        if (isset($_SESSION['cart'][$key])) {
            if ($q <= 0) unset($_SESSION['cart'][$key]); else $_SESSION['cart'][$key]['qty'] = $q;
        }
        // 2. Database
        if ($userId > 0) {
            $cartId = get_or_create_cart_id($conn, $userId);
            $parts = explode('_', $key);
            if (count($parts) >= 3 && $cartId > 0) {
                $pId = intval($parts[0]); $cId = intval($parts[1]); $sId = intval($parts[2]);
                if ($q <= 0) {
                    mysqli_query($conn, "DELETE FROM ChiTietGioHang WHERE IdGioHang=$cartId AND IdSanPham=$pId AND IdMau=$cId AND IdSize=$sId");
                } else {
                    mysqli_query($conn, "UPDATE ChiTietGioHang SET SoLuong=$q WHERE IdGioHang=$cartId AND IdSanPham=$pId AND IdMau=$cId AND IdSize=$sId");
                }
            }
        }
    }
    $_SESSION['cart_success'] = 'Đã cập nhật giỏ hàng!';
    header('Location: cart.php'); exit;
}

// ==========================================================================
// PHẦN C: HIỂN THỊ
// ==========================================================================
if ($userId > 0) {
    // Lấy từ DB, đồng bộ vào session
    $cart = get_db_cart_details($conn, $userId);
    $_SESSION['cart'] = $cart;
} else {
    $cart = $_SESSION['cart'];
}

// INCLUDE HEADER
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
                <form method="post" action="cart.php">
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
                                    <?php $uniqueKey = $it['product_id'] . '_' . ($it['color_id']??0) . '_' . ($it['size_id']??0); ?>
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
                                            <?php if ($it['color_name']) echo "<span class='badge bg-light text-dark border'>Màu: {$it['color_name']}</span><br>"; ?>
                                            <?php if ($it['size_name']) echo "<span class='badge bg-light text-dark border'>Size: {$it['size_name']}</span>"; ?>
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
                    <div class="row">
                        <div class="col-md-6"><a href="index.php" class="btn btn-outline-dark">Tiếp tục mua</a> <button class="btn btn-dark">Cập nhật giỏ</button></div>
                        <div class="col-md-6 text-end"><span class="h4 text-danger fw-bold">Tổng: <?php echo number_format($subtotal, 0, ',', '.'); ?>đ</span> <a href="checkout.php" class="btn btn-dark w-100 mt-2">Thanh toán</a></div>
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
    if(confirm('Xóa sản phẩm này?')) {
        var f = document.getElementById('deleteForm');
        var i = document.createElement('input'); i.type='hidden'; i.name='qty['+key+']'; i.value='0';
        f.appendChild(i); f.submit();
    }
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>