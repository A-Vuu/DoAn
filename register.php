<?php include 'includes/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h4>ĐĂNG KÝ TÀI KHOẢN</h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_POST['register'])) {
                        $name = $_POST['hoten'];
                        $email = $_POST['email'];
                        $pass = $_POST['password']; // Thực tế nên dùng password_hash($pass, PASSWORD_DEFAULT)
                        $phone = $_POST['phone'];

                        // Kiểm tra email trùng
                        $check = mysqli_query($conn, "SELECT Id FROM NguoiDung WHERE Email='$email'");
                        if (mysqli_num_rows($check) > 0) {
                            echo "<div class='alert alert-danger'>Email này đã được sử dụng!</div>";
                        } else {
                            $sql = "INSERT INTO NguoiDung (HoTen, Email, MatKhau, SoDienThoai, TrangThai) 
                                    VALUES ('$name', '$email', '$pass', '$phone', 1)";
                            if (mysqli_query($conn, $sql)) {
                                echo "<div class='alert alert-success'>Đăng ký thành công! <a href='login.php'>Đăng nhập ngay</a></div>";
                            }
                        }
                    }
                    ?>
                    <form method="POST">
                        <div class="mb-3"><label>Họ và tên</label><input type="text" name="hoten" class="form-control" required></div>
                        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-3"><label>Số điện thoại</label><input type="text" name="phone" class="form-control" required></div>
                        <div class="mb-3"><label>Mật khẩu</label><input type="password" name="password" class="form-control" required></div>
                        <button type="submit" name="register" class="btn btn-dark w-100">Đăng ký</button>
                        <small><a href="login.php">Quay lại</a></small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>