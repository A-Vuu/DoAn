<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ghiLichSuAdmin(
    $conn,
    $hanhDong,        // Ví dụ: "Thêm danh mục"
    $bangDuLieu,      // Ví dụ: "DanhMucSanPham"
    $idBanGhi = null, // Id bản ghi tác động
    $noiDung = ""     // Mô tả chi tiết
) {
    $idAdmin = $_SESSION['admin_id'] ?? null;
    if ($idAdmin === null) return;

    $ip = $_SERVER['REMOTE_ADDR'];

    $sql = "INSERT INTO lichsuhoatdong
            (IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP, NgayThucHien)
            VALUES
            ($idAdmin, 'admin', '$hanhDong', '$bangDuLieu',
             " . ($idBanGhi ? $idBanGhi : "NULL") . ",
             '$noiDung', '$ip', NOW())";

    mysqli_query($conn, $sql);
}
