<?php include 'includes/header.php'; ?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h4>ĐĂNG NHẬP</h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_POST['login'])) {
                        $email = $_POST['email'];
                        $pass = $_POST['password'];

                        $sql = "SELECT * FROM NguoiDung WHERE Email='$email' AND MatKhau='$pass' AND TrangThai=1";
                        $res = mysqli_query($conn, $sql);

                        if (mysqli_num_rows($res) > 0) {
                            $user = mysqli_fetch_assoc($res);
                            // Lưu session
                            $_SESSION['user_id'] = $user['Id'];
                            $_SESSION['user_name'] = $user['HoTen'];
                            $_SESSION['user_email'] = $user['Email'];

                            echo "<script>alert('Đăng nhập thành công!'); window.location='index.php';</script>";
                        } else {
                            echo "<div class='alert alert-danger'>Email hoặc mật khẩu không đúng!</div>";
                        }
                    }
                    ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-dark w-100">Đăng nhập</button>
                    </form>
                    <div class="text-center mt-3">
                        <small>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>