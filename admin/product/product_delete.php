<?php
session_start();
require_once '../../config.php'; // Lùi 2 cấp để lấy config


function log_product_action($conn, $action, $productId, $content) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($stmt = $conn->prepare(
        "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES (?, ?, 'admin', ?, 'SanPham', ?, ?, ?)"
    )) {
        $nullUser = null;
        $stmt->bind_param(
            'ississ',
            $nullUser,
            $adminId,
            $action,
            $productId,
            $content,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}


// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // ===============================
    // 1. KIỂM TRA SẢN PHẨM CÓ TRONG GIỎ HÀNG KHÔNG
    // ===============================
    $checkSql = "
        SELECT COUNT(*) AS total 
        FROM ChiTietGioHang 
        WHERE IdSanPham = $id
    ";
    $checkRes = mysqli_query($conn, $checkSql);
    $checkRow = mysqli_fetch_assoc($checkRes);

    if ($checkRow['total'] > 0) {
        // 🚫 Có user đang để sản phẩm trong giỏ → KHÔNG CHO XÓA
        echo "<script>
            alert('Không thể xóa sản phẩm vì đang tồn tại trong giỏ hàng. Vui lòng chọn Ẩn sản phẩm.');
            window.location='product.php';
        </script>";
        exit();
    }

    // ===============================
    // 2. KHÔNG CÓ TRONG GIỎ → CHO XÓA
    // ===============================
    $sql = "DELETE FROM SanPham WHERE Id = $id";

    if (mysqli_query($conn, $sql)) {
        log_product_action(
            $conn,
            'Delete',
            $id,
            'Xóa sản phẩm'
        );

        echo "<script>
            alert('Xóa sản phẩm thành công!');
            window.location='product.php';
        </script>";
    } else {
        echo "<script>
            alert('Lỗi xóa: " . mysqli_error($conn) . "');
            window.location='product.php';
        </script>";
    }
} else {
    header("Location: product.php");
}

?>