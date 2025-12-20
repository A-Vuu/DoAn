<?php
/**
 * Update dữ liệu claim cũ - thêm MaCode và TenChuongTrinh
 */
require_once '../../config.php';

echo "Cập nhật dữ liệu claim cũ...\n\n";

// Đếm số record cần update
$countSQL = "SELECT COUNT(*) as total FROM MaGiamGia_NguoiDung WHERE MaCode IS NULL OR TenChuongTrinh IS NULL";
$result = mysqli_query($conn, $countSQL);
$count = mysqli_fetch_assoc($result)['total'];

if ($count == 0) {
    echo "✓ Không có dữ liệu nào cần cập nhật!\n";
    exit;
}

echo "Tìm thấy $count record cần cập nhật...\n\n";

// Update
$updateSQL = "UPDATE MaGiamGia_NguoiDung mu
INNER JOIN MaGiamGia m ON mu.IdMaGiamGia = m.Id
SET mu.MaCode = m.MaCode, mu.TenChuongTrinh = m.TenChuongTrinh
WHERE mu.MaCode IS NULL OR mu.TenChuongTrinh IS NULL";

if (mysqli_query($conn, $updateSQL)) {
    $affected = mysqli_affected_rows($conn);
    echo "✓ Đã cập nhật $affected record thành công!\n\n";
    
    // Hiển thị kết quả
    echo "Dữ liệu sau khi update:\n";
    echo "==================================================\n";
    $showSQL = "SELECT Id, IdNguoiDung, IdMaGiamGia, MaCode, TenChuongTrinh, NgayNhan FROM MaGiamGia_NguoiDung ORDER BY Id";
    $result = mysqli_query($conn, $showSQL);
    
    echo str_pad("Id", 5) . str_pad("UserId", 8) . str_pad("PromoId", 10) . str_pad("MaCode", 15) . "TenChuongTrinh\n";
    echo "--------------------------------------------------\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo str_pad($row['Id'], 5) . 
             str_pad($row['IdNguoiDung'], 8) . 
             str_pad($row['IdMaGiamGia'], 10) . 
             str_pad($row['MaCode'], 15) . 
             $row['TenChuongTrinh'] . "\n";
    }
} else {
    echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
}
?>
