<?php
// FILE: includes/cart_functions.php
require_once __DIR__ . '/../config.php'; // Đảm bảo đường dẫn này đúng với cấu trúc thư mục của bạn

function add_or_update_cart_item_db($userId, $productId, $qty, $price, $options = null) {
    global $conn;

    // 1. LẤY HOẶC TẠO GIỎ HÀNG CHO USER
    $cartId = 0;
    // Kiểm tra xem user đã có giỏ hàng chưa
    $resCart = mysqli_query($conn, "SELECT Id FROM GioHang WHERE IdNguoiDung = $userId LIMIT 1");
    
    if ($resCart && mysqli_num_rows($resCart) > 0) {
        $rowCart = mysqli_fetch_assoc($resCart);
        $cartId = $rowCart['Id'];
    } else {
        // Tạo giỏ hàng mới
        mysqli_query($conn, "INSERT INTO GioHang (IdNguoiDung, NgayTao) VALUES ($userId, NOW())");
        $cartId = mysqli_insert_id($conn);
    }

    // 2. TÌM ID BIẾN THỂ (IdChiTietSanPham) TỪ MÀU VÀ SIZE
    // Đây là bước quan trọng để sửa lỗi Foreign Key = 0
    $idChiTietSanPham = "NULL"; // Mặc định là NULL nếu không có biến thể
    
    if ($options) {
        // Giải mã chuỗi JSON từ add_to_cart.php gửi sang
        $opt = json_decode($options, true);
        
        if (isset($opt['color_id']) && isset($opt['size_id']) && $opt['color_id'] > 0 && $opt['size_id'] > 0) {
            $cId = intval($opt['color_id']);
            $sId = intval($opt['size_id']);
            
            // Truy vấn bảng ChiTietSanPham để lấy ID thực tế
            $sqlFindVar = "SELECT Id FROM ChiTietSanPham 
                           WHERE IdSanPham = $productId 
                           AND IdMauSac = $cId 
                           AND IdKichThuoc = $sId 
                           LIMIT 1";
            $resFindVar = mysqli_query($conn, $sqlFindVar);
            
            if ($resFindVar && $rowVar = mysqli_fetch_assoc($resFindVar)) {
                $idChiTietSanPham = $rowVar['Id']; // Lấy được ID thực (ví dụ: 15)
            }
        }
    }

    // 3. KIỂM TRA SẢN PHẨM ĐÃ CÓ TRONG GIỎ CHƯA (ĐỂ CỘNG DỒN)
    $checkQuery = "SELECT Id, SoLuong FROM ChiTietGioHang 
                   WHERE IdGioHang = $cartId 
                   AND IdSanPham = $productId";
    
    // So sánh IdChiTietSanPham (Xử lý trường hợp NULL và số)
    if ($idChiTietSanPham !== "NULL") {
        $checkQuery .= " AND IdChiTietSanPham = $idChiTietSanPham";
    } else {
        $checkQuery .= " AND (IdChiTietSanPham IS NULL OR IdChiTietSanPham = 0)";
    }

    $resCheck = mysqli_query($conn, $checkQuery);

    if ($resCheck && mysqli_num_rows($resCheck) > 0) {
        // A. NẾU ĐÃ CÓ -> UPDATE CỘNG DỒN SỐ LƯỢNG
        $rowItem = mysqli_fetch_assoc($resCheck);
        $newQty = $rowItem['SoLuong'] + $qty;
        $itemId = $rowItem['Id'];
        
        $sqlUpdate = "UPDATE ChiTietGioHang SET SoLuong = $newQty WHERE Id = $itemId";
        mysqli_query($conn, $sqlUpdate);
        
    } else {
        // B. NẾU CHƯA CÓ -> INSERT MỚI
        // Lưu ý: $idChiTietSanPham ở đây là số hoặc chuỗi "NULL", nên ta đưa trực tiếp vào SQL
        $sqlInsert = "INSERT INTO ChiTietGioHang (IdGioHang, IdSanPham, IdChiTietSanPham, SoLuong, Gia, NgayThem) 
                      VALUES ($cartId, $productId, $idChiTietSanPham, $qty, $price, NOW())";
                      
        if (!mysqli_query($conn, $sqlInsert)) {
            // In lỗi ra màn hình để debug nếu vẫn bị lỗi
            echo "Lỗi SQL: " . mysqli_error($conn);
            exit; 
        }
    }
}
?>