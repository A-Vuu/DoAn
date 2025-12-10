<?php
// Trang hồ sơ người dùng
require_once __DIR__ . '/includes/header.php';

// Nếu chưa đăng nhập thì chuyển tới trang đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$msg = '';

// Xử lý cập nhật thông tin (chỉ tên và email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
    $field = $_POST['update_field'];
    if ($field === 'name') {
        $newName = trim($_POST['name'] ?? '');
        if ($newName === '') {
            $msg = 'Tên không được để trống.';
        } else {
            $nameEsc = mysqli_real_escape_string($conn, $newName);
            mysqli_query($conn, "UPDATE NguoiDung SET HoTen = '$nameEsc' WHERE Id = $userId");
            $_SESSION['user_name'] = $newName;
            $msg = 'Cập nhật tên thành công.';
        }
    } elseif ($field === 'email') {
        $newEmail = trim($_POST['email'] ?? '');
        if ($newEmail === '') {
            $msg = 'Email không được để trống.';
        } else {
            $emailEsc = mysqli_real_escape_string($conn, $newEmail);
            mysqli_query($conn, "UPDATE NguoiDung SET Email = '$emailEsc' WHERE Id = $userId");
            $_SESSION['user_email'] = $newEmail;
            $msg = 'Cập nhật email thành công.';
        }
    }
}

// Lấy thông tin người dùng từ DB để hiển thị
// Lấy thông tin người dùng từ DB để hiển thị (lấy lại sau POST nếu có)
$res = mysqli_query($conn, "SELECT Id, HoTen, Email FROM NguoiDung WHERE Id = $userId LIMIT 1");
$user = mysqli_fetch_assoc($res);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Hồ Sơ Người Dùng</h5>
                </div>
                <div class="card-body">
                    <?php if ($msg): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endif; ?>

                    <?php if ($msg): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tên</label>
                        <form method="post" id="form-name" class="d-flex gap-2 align-items-start">
                            <input type="hidden" name="update_field" value="name">
                            <input type="text" name="name" id="input-name" class="form-control-plaintext" value="<?php echo htmlspecialchars($user['HoTen'] ?? ''); ?>" disabled>
                            <div>
                                <button type="button" id="edit-name" class="btn btn-sm btn-link">Chỉnh sửa</button>
                                <button type="submit" id="save-name" class="btn btn-sm btn-primary d-none">Lưu</button>
                                <button type="button" id="cancel-name" class="btn btn-sm btn-secondary d-none">Hủy</button>
                            </div>
                        </form>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <form method="post" id="form-email" class="d-flex gap-2 align-items-start">
                            <input type="hidden" name="update_field" value="email">
                            <input type="email" name="email" id="input-email" class="form-control-plaintext" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" disabled>
                            <div>
                                <button type="button" id="edit-email" class="btn btn-sm btn-link">Chỉnh sửa</button>
                                <button type="submit" id="save-email" class="btn btn-sm btn-primary d-none">Lưu</button>
                                <button type="button" id="cancel-email" class="btn btn-sm btn-secondary d-none">Hủy</button>
                            </div>
                        </form>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">Quay lại</a>
                    </div>

                    <script>
                    // JS để bật/tắt chế độ chỉnh sửa inline
                    (function(){
                        function setup(field){
                            const editBtn = document.getElementById('edit-'+field);
                            const saveBtn = document.getElementById('save-'+field);
                            const cancelBtn = document.getElementById('cancel-'+field);
                            const input = document.getElementById('input-'+field);
                            const form = document.getElementById('form-'+field);
                            let original = input.value;

                            editBtn.addEventListener('click', function(){
                                input.disabled = false;
                                input.classList.remove('form-control-plaintext');
                                input.classList.add('form-control');
                                editBtn.classList.add('d-none');
                                saveBtn.classList.remove('d-none');
                                cancelBtn.classList.remove('d-none');
                                input.focus();
                            });

                            cancelBtn.addEventListener('click', function(){
                                input.value = original;
                                input.disabled = true;
                                input.classList.remove('form-control');
                                input.classList.add('form-control-plaintext');
                                editBtn.classList.remove('d-none');
                                saveBtn.classList.add('d-none');
                                cancelBtn.classList.add('d-none');
                            });

                            // Khi submit thành công trang sẽ reload; giữ logic đơn giản
                        }

                        setup('name');
                        setup('email');
                    })();
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
