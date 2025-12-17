<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$regError = '';
$regSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $regError = 'Phiên không hợp lệ, vui lòng thử lại.';
    } else {
        $name = trim($_POST['hoten'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $regError = 'Email không hợp lệ.';
        } elseif ($name === '' || $pass === '' || $phone === '') {
            $regError = 'Vui lòng nhập đầy đủ thông tin.';
        } else {
            // Kiểm tra email trùng
            if ($stmt = $conn->prepare('SELECT Id FROM NguoiDung WHERE Email = ? LIMIT 1')) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $regError = 'Email này đã được sử dụng!';
                }
                $stmt->close();
            }

            // Thêm mới nếu không trùng
            if ($regError === '') {
                if ($stmt = $conn->prepare('INSERT INTO NguoiDung (HoTen, Email, MatKhau, SoDienThoai, TrangThai) VALUES (?, ?, ?, ?, 1)')) {
                    $stmt->bind_param('ssss', $name, $email, $pass, $phone);
                    if ($stmt->execute()) {
                        $regSuccess = "Đăng ký thành công! <a href='login.php'>Đăng nhập ngay</a>";
                    } else {
                        $regError = 'Không thể đăng ký, vui lòng thử lại.';
                    }
                    $stmt->close();
                } else {
                    $regError = 'Không thể đăng ký, vui lòng thử lại.';
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h4>ĐĂNG KÝ TÀI KHOẢN</h4>
                </div>
                <div class="card-body">
                    <?php if ($regError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($regError); ?></div>
                    <?php endif; ?>
                    <?php if ($regSuccess): ?>
                        <div class="alert alert-success"><?php echo $regSuccess; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3"><label>Họ và tên</label><input type="text" name="hoten" class="form-control" required value="<?php echo htmlspecialchars($_POST['hoten'] ?? ''); ?>"></div>
                        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
                        <div class="mb-3"><label>Số điện thoại</label><input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"></div>
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