<?php
// Trang đổi mật khẩu
require_once __DIR__ . '/includes/header.php';

// Nếu chưa đăng nhập thì chuyển tới trang đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new === '' || $confirm === '' || $current === '') {
        $msg = 'Vui lòng điền đủ các trường.';
    } elseif ($new !== $confirm) {
        $msg = 'Mật khẩu mới và xác nhận không khớp.';
    } elseif (strlen($new) < 6) {
        $msg = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } else {
        // Lấy mật khẩu hiện tại từ DB
        $res = mysqli_query($conn, "SELECT MatKhau FROM NguoiDung WHERE Id = $userId LIMIT 1");
        $row = mysqli_fetch_assoc($res);
        $stored = $row['MatKhau'] ?? '';

        // Hệ thống hiện đang lưu mật khẩu theo cách plaintext (tương thích với login.php)
        if ($current !== $stored) {
            $msg = 'Mật khẩu hiện tại không đúng.';
        } else {
            $newEsc = mysqli_real_escape_string($conn, $new);
            mysqli_query($conn, "UPDATE NguoiDung SET MatKhau = '$newEsc' WHERE Id = $userId");
            $msg = 'Đổi mật khẩu thành công.';
        }
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Đổi mật khẩu</h5>
                </div>
                <div class="card-body">
                    <?php if ($msg): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu hiện tại</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="change_password" class="btn btn-dark">Đổi mật khẩu</button>
                            <a href="profile.php" class="btn btn-outline-secondary">Quay lại</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
