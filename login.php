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
                    
                    <?php if (!empty($error)): ?>
                        <div class='alert alert-danger text-center'>
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
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