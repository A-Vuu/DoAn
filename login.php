<?php
session_start();
require_once 'config.php';

// CSRF token đơn giản cho form đăng nhập
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Phiên đăng nhập không hợp lệ, vui lòng thử lại.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        $sql = "SELECT Id, HoTen, Email FROM NguoiDung WHERE Email = ? AND MatKhau = ? AND TrangThai = 1 LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ss', $email, $pass);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($user = $res->fetch_assoc()) {
                session_regenerate_id(true);

                unset($_SESSION['admin_login']);
                unset($_SESSION['admin_id']);
                unset($_SESSION['admin_name']);

                $_SESSION['user_id'] = $user['Id'];
                $_SESSION['user_name'] = $user['HoTen'];
                $_SESSION['user_email'] = $user['Email'];

                // Gộp giỏ session vào DB nếu có
                if (file_exists(__DIR__ . '/includes/cart_functions.php')) {
                    require_once __DIR__ . '/includes/cart_functions.php';
                    if (function_exists('merge_session_cart_to_db')) {
                        merge_session_cart_to_db((int)$user['Id']);
                    }
                }

                // Xử lý redirect an toàn
                $redirect = $_POST['redirect'] ?? ($_GET['redirect'] ?? 'index.php');
                if (preg_match('#^https?://#i', $redirect)) {
                    $redirect = 'index.php';
                }

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Email hoặc mật khẩu không đúng!';
            }
            $stmt->close();
        } else {
            $error = 'Không thể kết nối, vui lòng thử lại.';
        }
    }
}

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
                    if (isset($_SESSION['need_login_message'])) {
                        echo "<div class='alert alert-warning'>" . htmlspecialchars($_SESSION['need_login_message']) . "</div>";
                        unset($_SESSION['need_login_message']);
                    }
                    if (!empty($error)) {
                        echo "<div class='alert alert-danger'>" . htmlspecialchars($error) . "</div>";
                    }
                    ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
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