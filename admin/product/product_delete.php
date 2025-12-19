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
    $id = $_GET['id'];

    // 1. (Tùy chọn) Xóa ảnh cũ khỏi thư mục upload nếu cần thiết
    // $sqlAnh = "SELECT DuongDanAnh FROM AnhSanPham WHERE IdSanPham = $id";
    // ... code xóa file vật lý ...

    // 2. Xóa dữ liệu trong database
    // Vì trong database đã cài đặt ON DELETE CASCADE (như file SQL bạn gửi), 
    // nên khi xóa SanPham, các bảng con (ChiTietSanPham, AnhSanPham...) sẽ tự động xóa theo.
    $sql = "DELETE FROM SanPham WHERE Id = $id";

    if (mysqli_query($conn, $sql)) {
        log_product_action(
            $conn,
            'Delete',
            $id,
            'Xóa sản phẩm'
        );
        echo "<script>alert('Xóa sản phẩm thành công!'); window.location='product.php';</script>";
    } else {
        echo "<script>alert('Lỗi xóa: " . mysqli_error($conn) . "'); window.location='product.php';</script>";
    }
} else {
    header("Location: product.php");
}
?>