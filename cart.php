<?php
// Khởi động session trước require header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Lấy cart từ session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = &$_SESSION['cart'];

// Xử lý cập nhật số lượng (POST) TRƯỚC khi include header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $key => $q) {
        $q = intval($q);
        if (isset($cart[$key])) {
            if ($q <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['qty'] = $q;
            }
        }
    }
    $_SESSION['cart_success'] = 'Cập nhật giỏ hàng thành công.';
    header('Location: cart.php');
    exit;
}

// Giờ mới require header
require_once __DIR__ . '/includes/header.php';

$msg = '';
if (isset($_SESSION['cart_success'])) {
    $msg = $_SESSION['cart_success'];
    unset($_SESSION['cart_success']);
}
if (isset($_SESSION['cart_error'])) {
    $msg = $_SESSION['cart_error'];
    unset($_SESSION['cart_error']);
}

// Tính tổng
$subtotal = 0;
foreach ($cart as $it) {
    $subtotal += ($it['price'] ?? 0) * ($it['qty'] ?? 1);
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12">
            <h3>Giỏ hàng</h3>
            <?php if ($msg): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>

            <?php if (empty($cart)): ?>
                <div class="card p-4">
                    <p>Giỏ hàng của bạn đang trống.</p>
                    <a href="index.php" class="btn btn-dark">Tiếp tục mua sắm</a>
                </div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="update_cart" value="1">
                    <div class="table-responsive mb-4">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th style="width:100px">Giá</th>
                                    <th style="width:100px">Số lượng</th>
                                    <th style="width:100px">Thành tiền</th>
                                    <th style="width:80px">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $key => $it): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div style="width:60px; height:60px; overflow:hidden; margin-right:10px;">
                                                    <?php if (!empty($it['image'])): ?>
                                                        <img src="uploads/<?php echo htmlspecialchars($it['image']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary" style="width:100%; height:100%;"></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($it['name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($it['price'], 0, ',', '.'); ?>đ</td>
                                        <td>
                                            <input type="number" name="qty[<?php echo htmlspecialchars($key); ?>]" value="<?php echo intval($it['qty']); ?>" min="0" class="form-control" style="width:80px;">
                                        </td>
                                        <td><?php echo number_format($it['price'] * $it['qty'], 0, ',', '.'); ?>đ</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteItem('<?php echo htmlspecialchars($key); ?>')">Xóa</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-8">
                            <a href="index.php" class="btn btn-outline-secondary">Tiếp tục mua sắm</a>
                            <button type="submit" class="btn btn-primary">Cập nhật giỏ hàng</button>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="mb-3">
                                <h5>Tổng tiền: <strong class="text-danger"><?php echo number_format($subtotal, 0, ',', '.'); ?>đ</strong></h5>
                            </div>
                            <a href="checkout.php" class="btn btn-success btn-lg">Thanh toán</a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden form để xóa sản phẩm -->
<form id="deleteForm" method="post" action="remove_from_cart.php" style="display:none;">
    <input type="hidden" name="key" id="deleteKey" value="">
</form>

<script>
function deleteItem(key) {
    if (confirm('Bạn muốn xóa sản phẩm này khỏi giỏ hàng?')) {
        document.getElementById('deleteKey').value = key;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
