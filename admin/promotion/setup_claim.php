<?php
/**
 * Setup bảng MaGiamGia_NguoiDung để track mã đã claim của user
 */
require_once '../../config.php';

$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia_NguoiDung'");
if (mysqli_num_rows($checkTable) > 0) {
    echo "✓ Bảng MaGiamGia_NguoiDung đã tồn tại.\n";
    
    // Kiểm tra và thêm cột MaCode nếu chưa có
    $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM MaGiamGia_NguoiDung LIKE 'MaCode'");
    if (mysqli_num_rows($checkCol) == 0) {
        echo "Thêm cột MaCode...\n";
        if (mysqli_query($conn, "ALTER TABLE MaGiamGia_NguoiDung ADD COLUMN MaCode VARCHAR(50) AFTER IdMaGiamGia")) {
            echo "✓ Thêm cột MaCode thành công!\n";
        } else {
            echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
        }
    }
    
    // Kiểm tra và thêm cột TenChuongTrinh nếu chưa có
    $checkCol2 = mysqli_query($conn, "SHOW COLUMNS FROM MaGiamGia_NguoiDung LIKE 'TenChuongTrinh'");
    if (mysqli_num_rows($checkCol2) == 0) {
        echo "Thêm cột TenChuongTrinh...\n";
        if (mysqli_query($conn, "ALTER TABLE MaGiamGia_NguoiDung ADD COLUMN TenChuongTrinh VARCHAR(200) AFTER MaCode")) {
            echo "✓ Thêm cột TenChuongTrinh thành công!\n";
        } else {
            echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
        }
    }
} else {
    echo "Tạo bảng MaGiamGia_NguoiDung...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS MaGiamGia_NguoiDung (
        Id INT PRIMARY KEY AUTO_INCREMENT,
        IdNguoiDung INT NOT NULL,
        IdMaGiamGia INT NOT NULL,
        MaCode VARCHAR(50),
        TenChuongTrinh VARCHAR(200),
        NgayNhan DATETIME DEFAULT CURRENT_TIMESTAMP,
        DaSuDung TINYINT(1) DEFAULT 0,
        NgayDaSuDung DATETIME,
        FOREIGN KEY (IdNguoiDung) REFERENCES nguoidung(Id) ON DELETE CASCADE,
        FOREIGN KEY (IdMaGiamGia) REFERENCES MaGiamGia(Id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_promo (IdNguoiDung, IdMaGiamGia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Tạo bảng MaGiamGia_NguoiDung thành công!\n";
    } else {
        echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
    }
}
?>
