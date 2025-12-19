<?php
session_start();
require_once '../config.php';

// ===== GHI LỊCH SỬ ĐĂNG XUẤT =====
if (isset($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $sqlLog = "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES
        (NULL, '$adminId', 'admin', 'Logout', 'Admin', '$adminId', 'Admin đăng xuất hệ thống', '$ip')";

    mysqli_query($conn, $sqlLog);
}
// =================================

session_unset();
session_destroy();

header("Location: login.php");
exit();

?>