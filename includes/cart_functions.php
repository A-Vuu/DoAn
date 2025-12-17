<?php
// FILE: includes/cart_functions.php
require_once __DIR__ . '/../config.php'; // Đảm bảo đường dẫn này đúng với cấu trúc thư mục của bạn

function add_or_update_cart_item_db($userId, $productId, $qty, $price, $options = null) {
    global $conn;

    $qty = max(1, (int)$qty);
    $price = (float)$price;

    // 1. LẤY HOẶC TẠO GIỎ HÀNG CHO USER
    $cartId = 0;
    if ($stmt = $conn->prepare('SELECT Id FROM GioHang WHERE IdNguoiDung = ? LIMIT 1')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $cartId = (int)$row['Id'];
        }
        $stmt->close();
    }

    if ($cartId === 0) {
        if ($stmt = $conn->prepare('INSERT INTO GioHang (IdNguoiDung, NgayTao) VALUES (?, NOW())')) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $cartId = $stmt->insert_id;
            $stmt->close();
        }
    }

    // 2. TÌM ID BIẾN THỂ (IdChiTietSanPham) TỪ MÀU VÀ SIZE
    // Đây là bước quan trọng để sửa lỗi Foreign Key = 0
    $idChiTietSanPham = null; // Mặc định là NULL nếu không có biến thể
    
    if ($options) {
        // Giải mã chuỗi JSON từ add_to_cart.php gửi sang
        $opt = json_decode($options, true);
        
        if (isset($opt['color_id']) && isset($opt['size_id']) && $opt['color_id'] > 0 && $opt['size_id'] > 0) {
            $cId = intval($opt['color_id']);
            $sId = intval($opt['size_id']);
            if ($stmt = $conn->prepare('SELECT Id FROM ChiTietSanPham WHERE IdSanPham = ? AND IdMauSac = ? AND IdKichThuoc = ? LIMIT 1')) {
                $stmt->bind_param('iii', $productId, $cId, $sId);
                $stmt->execute();
                $resVar = $stmt->get_result();
                if ($rowVar = $resVar->fetch_assoc()) {
                    $idChiTietSanPham = (int)$rowVar['Id'];
                }
                $stmt->close();
            }
        }
    }

    // 3. KIỂM TRA SẢN PHẨM ĐÃ CÓ TRONG GIỎ CHƯA (ĐỂ CỘNG DỒN)
    $rowItem = null;
    if ($idChiTietSanPham !== null) {
        if ($stmt = $conn->prepare('SELECT Id, SoLuong FROM ChiTietGioHang WHERE IdGioHang = ? AND IdSanPham = ? AND IdChiTietSanPham = ? LIMIT 1')) {
            $stmt->bind_param('iii', $cartId, $productId, $idChiTietSanPham);
            $stmt->execute();
            $resCheck = $stmt->get_result();
            $rowItem = $resCheck->fetch_assoc();
            $stmt->close();
        }
    } else {
        if ($stmt = $conn->prepare('SELECT Id, SoLuong FROM ChiTietGioHang WHERE IdGioHang = ? AND IdSanPham = ? AND (IdChiTietSanPham IS NULL OR IdChiTietSanPham = 0) LIMIT 1')) {
            $stmt->bind_param('ii', $cartId, $productId);
            $stmt->execute();
            $resCheck = $stmt->get_result();
            $rowItem = $resCheck->fetch_assoc();
            $stmt->close();
        }
    }

    if ($rowItem) {
        $newQty = (int)$rowItem['SoLuong'] + $qty;
        if ($stmt = $conn->prepare('UPDATE ChiTietGioHang SET SoLuong = ? WHERE Id = ?')) {
            $stmt->bind_param('ii', $newQty, $rowItem['Id']);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        if ($stmt = $conn->prepare('INSERT INTO ChiTietGioHang (IdGioHang, IdSanPham, IdChiTietSanPham, SoLuong, Gia, NgayThem) VALUES (?, ?, ?, ?, ?, NOW())')) {
            $stmt->bind_param('iiiid', $cartId, $productId, $idChiTietSanPham, $qty, $price);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>