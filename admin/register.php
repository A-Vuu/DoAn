<?php
// Kết nối CSDL
require_once '../config.php';

$err = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hoten = trim($_POST["hoten"]);
    $email = trim($_POST["email"]);
    $sdt   = trim($_POST["sdt"]);
    $pass  = trim($_POST["matkhau"]);

    if ($hoten == "" || $email == "" || $pass == "") {
        $err = "Vui lòng nhập đầy đủ thông tin";
    } else {
        // Kiểm tra email đã tồn tại chưa
        $check = mysqli_query($conn, "SELECT Id FROM admin WHERE Email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $err = "Email đã tồn tại";
        } else {
            // Mã hóa mật khẩu (nên dùng)
            // $passHash = password_hash($pass, PASSWORD_DEFAULT);
            $passHash = $pass; // nếu bạn đang dùng mật khẩu thường

            $quyen = json_encode(["all" => true], JSON_UNESCAPED_UNICODE);

            $sql = "INSERT INTO admin
                    (HoTen, Email, SoDienThoai, MatKhau, ChucVu, Quyen, TrangThai, NgayTao)
                    VALUES
                    ('$hoten', '$email', '$sdt', '$passHash',
                     'Quản trị viên', '$quyen', 1, NOW())";

            if (mysqli_query($conn, $sql)) {
                $success = "Đăng ký admin thành công";
            } else {
                $err = "Lỗi: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Đăng ký Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 500px;">
    <h3 class="text-center mb-4">Đăng ký Admin</h3>

    <?php if ($err): ?>
        <div class="alert alert-danger"><?= $err ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label>Họ tên</label>
            <input type="text" name="hoten" class="form-control">
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>

        <div class="mb-3">
            <label>Số điện thoại</label>
            <input type="text" name="sdt" class="form-control">
        </div>

        <div class="mb-3">
            <label>Mật khẩu</label>
            <input type="password" name="matkhau" class="form-control">
        </div>

        <button class="btn btn-primary w-100">Đăng ký</button>
        <a href="login.php">Quay lại</a>
    </form>
</div>

</body>
</html>
