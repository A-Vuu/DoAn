<?php
session_start();
require_once '../../config.php'; // Lùi 2 cấp để lấy config

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
        echo "<script>alert('Xóa sản phẩm thành công!'); window.location='product.php';</script>";
    } else {
        echo "<script>alert('Lỗi xóa: " . mysqli_error($conn) . "'); window.location='product.php';</script>";
    }
} else {
    header("Location: product.php");
}
?>