<?php
// Các hàm trợ giúp cho giỏ hàng lưu vào DB (bảng GioHang + ChiTietGioHang)
// Yêu cầu: trước khi gọi các hàm này cần include `config.php` để có biến $conn (mysqli)

function get_or_create_cart_id($userId) {
    global $conn;
    $userId = intval($userId);
    // Kiểm tra xem user đã có giỏ hàng (GioHang) chưa
    $res = mysqli_query($conn, "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return intval($row['Id']);
    }
    // Nếu chưa có thì tạo mới
    mysqli_query($conn, "INSERT INTO GioHang (IdNguoiDung, NgayTao) VALUES ($userId, NOW())");
    return intval(mysqli_insert_id($conn));
}

function get_cart_items_db($userId) {
    global $conn;
    $userId = intval($userId);
    // Lấy Id của giỏ hàng của user
    $cartIdRes = mysqli_query($conn, "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1");
    if (!$cartIdRes || mysqli_num_rows($cartIdRes) === 0) return [];
    $cartId = intval(mysqli_fetch_assoc($cartIdRes)['Id']);

    // Lấy danh sách chi tiết giỏ hàng kèm thông tin sản phẩm và ảnh chính
    $sql = "SELECT ct.Id as CartItemId, ct.IdSanPham, ct.IdChiTietSanPham, ct.SoLuong, ct.Gia,
                   sp.TenSanPham, sp.GiaGoc, sp.GiaKhuyenMai, a.DuongDanAnh
            FROM ChiTietGioHang ct
            JOIN SanPham sp ON sp.Id = ct.IdSanPham
            LEFT JOIN AnhSanPham a ON a.IdSanPham = sp.Id AND a.LaAnhChinh = 1
            WHERE ct.IdGioHang = $cartId";
    $res = mysqli_query($conn, $sql);
    $items = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            // Lựa giá hiển thị: nếu có giá lưu trong chi tiết giỏ hàng thì ưu tiên, nếu không dùng giá khuyến mãi hoặc giá gốc
            $price = $r['Gia'] ? floatval($r['Gia']) : ($r['GiaKhuyenMai'] ? floatval($r['GiaKhuyenMai']) : floatval($r['GiaGoc']));
            $items[] = [
                // 'key' dùng để đồng nhất với key của session cart (ví dụ 'db123')
                'key' => 'db'.$r['CartItemId'],
                'cart_item_id' => $r['CartItemId'],
                'product_id' => $r['IdSanPham'],
                'variant_id' => $r['IdChiTietSanPham'],
                'name' => $r['TenSanPham'],
                'price' => $price,
                'qty' => intval($r['SoLuong']),
                'image' => $r['DuongDanAnh'] ? $r['DuongDanAnh'] : ''
            ];
        }
    }
    return $items;
}

function add_or_update_cart_item_db($userId, $productId, $qty, $price = null, $variantId = null) {
    global $conn;
    $userId = intval($userId);
    $productId = intval($productId);
    $qty = max(1, intval($qty));
    $variantId = $variantId !== null ? intval($variantId) : null;

    $cartId = get_or_create_cart_id($userId);

    // Tìm xem đã có dòng ChiTietGioHang cho sản phẩm + biến thể này chưa
    if ($variantId !== null) {
        $whereVar = "AND IdChiTietSanPham = $variantId";
    } else {
        $whereVar = "AND IdChiTietSanPham IS NULL";
    }

    $checkSql = "SELECT Id, SoLuong FROM ChiTietGioHang WHERE IdGioHang = $cartId AND IdSanPham = $productId $whereVar LIMIT 1";
    $res = mysqli_query($conn, $checkSql);
    if ($res && mysqli_num_rows($res) > 0) {
        // Nếu đã tồn tại thì cộng dồn số lượng
        $row = mysqli_fetch_assoc($res);
        $newQty = intval($row['SoLuong']) + $qty;
        mysqli_query($conn, "UPDATE ChiTietGioHang SET SoLuong = $newQty WHERE Id = " . intval($row['Id']) );
    } else {
        // Nếu chưa có thì chèn mới
        $priceVal = $price !== null ? floatval($price) : 'NULL';
        $variantPart = $variantId !== null ? $variantId : 'NULL';
        $insertSql = "INSERT INTO ChiTietGioHang (IdGioHang, IdSanPham, IdChiTietSanPham, SoLuong, Gia, NgayThem) VALUES ($cartId, $productId, $variantPart, $qty, $priceVal, NOW())";
        mysqli_query($conn, $insertSql);
    }
}

function update_cart_item_qty_db($userId, $cartItemId, $qty) {
    global $conn;
    $cartItemId = intval($cartItemId);
    $qty = intval($qty);
    if ($qty <= 0) {
        // Xóa mục nếu số lượng <= 0
        mysqli_query($conn, "DELETE FROM ChiTietGioHang WHERE Id = $cartItemId");
    } else {
        // Cập nhật số lượng
        mysqli_query($conn, "UPDATE ChiTietGioHang SET SoLuong = $qty WHERE Id = $cartItemId");
    }
}

function remove_cart_item_db($userId, $cartItemId) {
    global $conn;
    $cartItemId = intval($cartItemId);
    // Xóa dòng chi tiết giỏ hàng
    mysqli_query($conn, "DELETE FROM ChiTietGioHang WHERE Id = $cartItemId");
}

function merge_session_cart_to_db($userId) {
    global $conn;
    // Nếu không có giỏ hàng lưu trên session thì không làm gì
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) return;
    // Duyệt từng item trong session cart và thêm/gộp vào DB
    foreach ($_SESSION['cart'] as $key => $it) {
        $productId = intval($it['product_id'] ?? $it['IdSanPham'] ?? 0);
        $qty = intval($it['qty'] ?? $it['quantity'] ?? 1);
        $price = isset($it['price']) ? floatval($it['price']) : null;
        if ($productId > 0) {
            add_or_update_cart_item_db($userId, $productId, $qty, $price, $it['variant_id'] ?? null);
        }
    }
    // Sau khi gộp xong, xóa giỏ trên session để tránh trùng lặp
    unset($_SESSION['cart']);
}

?>
