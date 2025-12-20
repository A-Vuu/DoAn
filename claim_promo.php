<?php
/**
 * API để user claim/nhận mã khuyến mãi
 * POST /claim_promo.php
 * 
 * Parameters:
 *   - promo_id: ID của mã khuyến mãi
 * 
 * Response: JSON
 *   - success: true/false
 *   - message: Thông báo
 */
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để nhận mã'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'config.php';

$response = [
    'success' => false,
    'message' => ''
];

try {
    $promoId = (int)($_POST['promo_id'] ?? 0);
    $userId = (int)$_SESSION['user_id'];

    if ($promoId <= 0) {
        throw new Exception('ID mã khuyến mãi không hợp lệ');
    }

    // Kiểm tra mã tồn tại và còn lượt
    $stmt = $conn->prepare("SELECT MaCode, TenChuongTrinh, SoLuongMa, DaSuDung FROM MaGiamGia WHERE Id = ?");
    $stmt->bind_param('i', $promoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Mã khuyến mãi không tồn tại');
    }

    $promo = $result->fetch_assoc();
    $stmt->close();

    // Kiểm tra mã còn lượt
    if ($promo['SoLuongMa'] && $promo['DaSuDung'] >= $promo['SoLuongMa']) {
        throw new Exception('Mã khuyến mãi đã hết lượt');
    }

    // Kiểm tra user đã claim chưa
    $stmtCheck = $conn->prepare("SELECT Id FROM MaGiamGia_NguoiDung WHERE IdNguoiDung = ? AND IdMaGiamGia = ?");
    $stmtCheck->bind_param('ii', $userId, $promoId);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows > 0) {
        $stmtCheck->close();
        throw new Exception('Bạn đã nhận mã này rồi');
    }
    $stmtCheck->close();

    // Insert claim record với thông tin mã
    $stmtInsert = $conn->prepare("INSERT INTO MaGiamGia_NguoiDung (IdNguoiDung, IdMaGiamGia, MaCode, TenChuongTrinh) VALUES (?, ?, ?, ?)");
    if (!$stmtInsert) {
        throw new Exception('Lỗi database: ' . $conn->error);
    }

    $stmtInsert->bind_param('iiss', $userId, $promoId, $promo['MaCode'], $promo['TenChuongTrinh']);
    if (!$stmtInsert->execute()) {
        throw new Exception('Không thể nhận mã. Vui lòng thử lại.');
    }
    $stmtInsert->close();

    $response['success'] = true;
    $response['message'] = '✓ Nhận mã thành công!';

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
