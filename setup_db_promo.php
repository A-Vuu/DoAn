<?php
/**
 * Script kiểm tra và thêm cột MaGiamGia vào bảng DonHang nếu chưa có
 * Chạy file này 1 lần
 */
require_once 'config.php';

// Kiểm tra xem cột MaGiamGia đã tồn tại chưa
$result = mysqli_query($conn, "DESCRIBE DonHang");
$columnExists = false;

while ($col = mysqli_fetch_assoc($result)) {
    if ($col['Field'] === 'MaGiamGia') {
        $columnExists = true;
        break;
    }
}

if ($columnExists) {
    echo "✓ Cột MaGiamGia đã tồn tại trong bảng DonHang\n";
} else {
    echo "Thêm cột MaGiamGia vào bảng DonHang...\n";
    
    if (mysqli_query($conn, "ALTER TABLE DonHang ADD COLUMN MaGiamGia VARCHAR(50) NULL AFTER GiamGia")) {
        echo "✓ Thêm cột MaGiamGia thành công!\n";
    } else {
        echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
    }
}
?>
