<?php
// 1. Khởi động session và kết nối DB ngay đầu file
session_start();
require_once 'config.php'; 

// 2. Xử lý Đăng Nhập (Logic PHP nằm trên cùng)
$error = ''; // Biến để lưu thông báo lỗi
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // Sử dụng Prepared Statement để bảo mật (tránh lỗi SQL Injection)
    $sql = "SELECT * FROM NguoiDung WHERE Email = ? AND MatKhau = ? AND TrangThai = 1";
    
    // Chuẩn bị câu lệnh
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $email, $pass);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            
            // Lưu thông tin vào Session
            $_SESSION['user_id'] = $user['Id'];
            $_SESSION['user_name'] = $user['HoTen'];
            $_SESSION['user_email'] = $user['Email'];

            // QUAN TRỌNG: Chuyển hướng ngay lập tức bằng PHP header
            header("Location: index.php");
            exit(); // Dừng code ngay tại đây để chuyển trang
        } else {
            $error = "Email hoặc mật khẩu không đúng!";
        }
        $stmt->close();
    }
}

// 3. Gọi Header giao diện (Chỉ gọi sau khi đã xử lý logic xong)
include 'includes/header.php'; 
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h4>ĐĂNG NHẬP</h4>
                </div>
                <div class="card-body">
                    <?php
                    // Hiển thị thông báo nếu người dùng bị chuyển tới trang đăng nhập do cần đăng nhập trước khi thao tác
                    if (isset($_SESSION['need_login_message'])) {
                        echo "<div class='alert alert-warning'>" . htmlspecialchars($_SESSION['need_login_message']) . "</div>";
                        unset($_SESSION['need_login_message']);
                    }

                    if (isset($_POST['login'])) {
                        $email = $_POST['email'];
                        $pass = $_POST['password'];
                        $redirectParam = $_POST['redirect'] ?? null;

                        $sql = "SELECT * FROM NguoiDung WHERE Email='$email' AND MatKhau='$pass' AND TrangThai=1";
                        $res = mysqli_query($conn, $sql);

                        if (mysqli_num_rows($res) > 0) {
                            $user = mysqli_fetch_assoc($res);
                            // Lưu session
                            $_SESSION['user_id'] = $user['Id'];
                            $_SESSION['user_name'] = $user['HoTen'];
                            $_SESSION['user_email'] = $user['Email'];

                            // Nếu có giỏ hàng session cũ, gộp vào DB cho user này
                            if (file_exists(__DIR__ . '/includes/cart_functions.php')) {
                                require_once __DIR__ . '/includes/cart_functions.php';
                                merge_session_cart_to_db(intval($user['Id']));
                            }

                            // Xử lý redirect an toàn (chỉ cho phép đường dẫn nội bộ)
                            $redirect = $redirectParam ?? ($_GET['redirect'] ?? null);
                            if ($redirect) {
                                // Chống open-redirect: nếu là URL tuyệt đối có http(s) thì bỏ qua
                                if (preg_match('#^https?://#i', $redirect)) {
                                    $redirect = 'index.php';
                                }
                            } else {
                                $redirect = 'index.php';
                            }

                            echo "<script>alert('Đăng nhập thành công!'); window.location='" . htmlspecialchars($redirect) . "';</script>";
                        } else {
                            echo "<div class='alert alert-danger'>Email hoặc mật khẩu không đúng!</div>";
                        }
                    }
                    ?>
                    <form method="POST">
                        <?php if (!empty($_GET['redirect'])): ?>
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="Nhập email của bạn...">
                        </div>
                        <div class="mb-3">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required placeholder="Nhập mật khẩu...">
                        </div>
                        <button type="submit" name="login" class="btn btn-dark w-100">Đăng nhập</button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small>Chưa có tài khoản? <a href="register.php" class="text-decoration-none">Đăng ký ngay</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>