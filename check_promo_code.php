<?php
/**
 * API kiểm tra và lấy thông tin mã giảm giá
 * POST /check_promo_code.php
 * 
 * Parameters:
 *   - code: Mã giảm giá
 *   - subtotal: Tổng tiền hàng (VNĐ)
 * 
 * Response: JSON
 *   - success: true/false
 *   - message: Thông báo
 *   - discount: Số tiền giảm (VNĐ)
 *   - discount_text: Hiển thị giảm giá (VD: "50,000đ" hoặc "20%")
 *   - final_total: Tổng tiền sau giảm
 */

header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
session_start();

date_default_timezone_set('Asia/Ho_Chi_Minh');

$response = [
    'success' => false,
    'message' => '',
    'discount' => 0,
    'discount_text' => '',
    'final_total' => 0
];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Vui lòng đăng nhập để sử dụng mã khuyến mãi');
    }

    $userId = (int)$_SESSION['user_id'];
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $subtotal = (float)($_POST['subtotal'] ?? 0);

    if (empty($code)) {
        throw new Exception('Vui lòng nhập mã giảm giá');
    }

    if ($subtotal <= 0) {
        throw new Exception('Số tiền không hợp lệ');
    }

    // Kiểm tra bảng claim tồn tại
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia_NguoiDung'");
    $tableExists = mysqli_num_rows($checkTable) > 0;

    // Tìm mã - verify user claim nếu bảng tồn tại
    if ($tableExists) {
        $stmt = $conn->prepare(
            "SELECT m.* FROM MaGiamGia m
             INNER JOIN MaGiamGia_NguoiDung mu ON m.Id = mu.IdMaGiamGia
             WHERE m.MaCode = ? AND m.HienThi = 1 AND mu.IdNguoiDung = ?"
        );
        
        if (!$stmt) {
            throw new Exception('Lỗi database: ' . $conn->error);
        }

        $stmt->bind_param('si', $code, $userId);
    } else {
        $stmt = $conn->prepare(
            "SELECT * FROM MaGiamGia 
             WHERE MaCode = ? AND HienThi = 1"
        );
        
        if (!$stmt) {
            throw new Exception('Lỗi database: ' . $conn->error);
        }

        $stmt->bind_param('s', $code);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception($tableExists ? 'Mã không hợp lệ hoặc bạn chưa nhận mã này' : 'Mã giảm giá không tồn tại');
    }

    $promo = $result->fetch_assoc();
    $stmt->close();

    // Kiểm tra thời hạn
    if ($promo['NgayBatDau'] && strtotime($promo['NgayBatDau']) > time()) {
        throw new Exception('Mã này chưa có hiệu lực');
    }

    if ($promo['NgayKetThuc'] && strtotime($promo['NgayKetThuc']) < time()) {
        throw new Exception('Mã giảm giá đã hết hạn');
    }

    // Kiểm tra số lượt dùng
    if ($promo['SoLuongMa'] && $promo['DaSuDung'] >= $promo['SoLuongMa']) {
        throw new Exception('Mã giảm giá đã hết lượt dùng');
    }

    // Kiểm tra đơn hàng tối thiểu
    if ($promo['GiaTriDonHangToiThieu'] > 0 && $subtotal < $promo['GiaTriDonHangToiThieu']) {
        throw new Exception(
            'Đơn hàng phải từ ' . number_format($promo['GiaTriDonHangToiThieu']) . 'đ ' .
            '(hiện tại: ' . number_format($subtotal) . 'đ)'
        );
    }

    // Tính toán giảm giá
    $discount = 0;
    $isPercent = in_array($promo['LoaiGiam'], ['PhanTram', '%'], true);
    if (!$isPercent) {
        $discount = min($promo['GiaTriGiam'], $subtotal);
        $discountText = number_format($discount) . 'đ';
    } else {
        $discount = ($subtotal * $promo['GiaTriGiam']) / 100;
        if ($promo['GiamToiDa'] > 0) {
            $discount = min($discount, $promo['GiamToiDa']);
        }
        $discountText = $promo['GiaTriGiam'] . '%';
    }

    $finalTotal = max(0, $subtotal - $discount);

    $response['success'] = true;
    $response['message'] = '✓ Áp dụng thành công!';
    $response['discount'] = $discount;
    $response['discount_text'] = $discountText;
    $response['final_total'] = $finalTotal;
    $response['promo_id'] = $promo['Id'];
    $response['promo_name'] = $promo['TenChuongTrinh'];

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
