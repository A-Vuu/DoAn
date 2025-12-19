<?php
session_start();
require_once '../config.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    // Kiểm tra tài khoản (lưu ý: code này demo, thực tế nên mã hóa password)
    $sql = "SELECT * FROM Admin WHERE Email = '$email' AND MatKhau = '$pass' AND TrangThai = 1";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    $_SESSION['admin_login'] = true;
    $_SESSION['admin_id'] = $row['Id'];
    $_SESSION['admin_name'] = $row['HoTen'];

    // ===== GHI LỊCH SỬ ĐĂNG NHẬP =====
    $adminId = $row['Id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $sqlLog = "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES
        (NULL, '$adminId', 'admin', 'Login', 'Admin', '$adminId', 'Admin đăng nhập hệ thống', '$ip')";

    mysqli_query($conn, $sqlLog);
    // =================================

    header("Location: product/product.php");
    exit();
}
 else {
        $error = "Sai thông tin đăng nhập!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Đăng nhập Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-box { width: 360px; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="login-box">
        <h3 class="text-center mb-4">NovaWear Admin</h3>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Đăng nhập</button>
            <a href="register.php">Đăng ký</a>
        </form>
    </div>
</body>
</html>