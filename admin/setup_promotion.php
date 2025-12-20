<?php
/**
 * Kiểm tra và tạo cấu trúc bảng MaGiamGia nếu cần
 * Chạy file này 1 lần để setup
 */
require_once 'config.php';

// Kiểm tra xem bảng đã tồn tại chưa
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia'");
if (mysqli_num_rows($checkTable) > 0) {
    echo "✓ Bảng MaGiamGia đã tồn tại. Kiểm tra cấu trúc:\n\n";
    $desc = mysqli_query($conn, "DESCRIBE MaGiamGia");
    while ($col = mysqli_fetch_assoc($desc)) {
        echo "  - " . $col['Field'] . ": " . $col['Type'] . "\n";
    }
} else {
    echo "Tạo bảng MaGiamGia...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS MaGiamGia (
        Id INT PRIMARY KEY AUTO_INCREMENT,
        Ma VARCHAR(50) UNIQUE NOT NULL,
        TenKhuyenMai VARCHAR(255) NOT NULL,
        LoaiGiam ENUM('TienMat', 'PhanTram') DEFAULT 'TienMat',
        GiaTri DECIMAL(10,2) NOT NULL,
        DonHangTuoi DECIMAL(10,2) DEFAULT 0,
        SoLuotToiDa INT DEFAULT NULL,
        SoLuotDaSuDung INT DEFAULT 0,
        NgayBatDau DATETIME,
        NgayKetThuc DATETIME,
        TrangThai ENUM('Hoat', 'Dung', 'HetHan') DEFAULT 'Hoat',
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Tạo bảng MaGiamGia thành công!\n";
    } else {
        echo "✗ Lỗi: " . mysqli_error($conn) . "\n";
    }
}
?>
