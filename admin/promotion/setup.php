<?php
/**
 * Setup bảng MaGiamGia - Chạy file này 1 lần
 */
require_once '../../config.php';

echo "<h2>Setup Database Khuyến Mãi</h2>";

// 1. Kiểm tra bảng MaGiamGia
$checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'MaGiamGia'");

if (mysqli_num_rows($checkTable) > 0) {
    echo "<div style='color: green; padding: 10px; background: #f0f0f0; margin: 10px 0;'>";
    echo "✓ Bảng MaGiamGia đã tồn tại!<br>";
    echo "Cấu trúc các cột:<br>";
    $desc = mysqli_query($conn, "DESCRIBE MaGiamGia");
    echo "<ul>";
    while ($col = mysqli_fetch_assoc($desc)) {
        echo "<li><strong>" . $col['Field'] . "</strong>: " . $col['Type'] . "</li>";
    }
    echo "</ul></div>";
} else {
    echo "<div style='color: blue; padding: 10px; background: #f0f0f0; margin: 10px 0;'>";
    echo "Tạo bảng MaGiamGia...<br>";
    
    $sql = "CREATE TABLE MaGiamGia (
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
        echo "✓ Tạo bảng MaGiamGia thành công!<br>";
        echo "</div>";
    } else {
        echo "✗ Lỗi: " . mysqli_error($conn) . "<br>";
        echo "</div>";
    }
}

// 2. Kiểm tra cột MaGiamGia trong bảng DonHang
echo "<hr>";
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM DonHang WHERE Field = 'MaGiamGia'");

if (mysqli_num_rows($checkCol) > 0) {
    echo "<div style='color: green; padding: 10px; background: #f0f0f0; margin: 10px 0;'>";
    echo "✓ Cột MaGiamGia đã tồn tại trong bảng DonHang!";
    echo "</div>";
} else {
    echo "<div style='color: blue; padding: 10px; background: #f0f0f0; margin: 10px 0;'>";
    echo "Thêm cột MaGiamGia vào bảng DonHang...<br>";
    
    if (mysqli_query($conn, "ALTER TABLE DonHang ADD COLUMN MaGiamGia VARCHAR(50) NULL AFTER GiamGia")) {
        echo "✓ Thêm cột MaGiamGia thành công!<br>";
        echo "</div>";
    } else {
        echo "✗ Lỗi: " . mysqli_error($conn) . "<br>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<p style='color: #666; font-size: 14px;'>Setup hoàn tất! Bạn có thể đóng trang này.</p>";
?>
