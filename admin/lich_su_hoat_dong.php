<?php
session_start();
require_once '../config.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

// ==============================
// 2. Lấy dữ liệu filter
// ==============================
$filter_admin = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : 0;
$from_date    = $_GET['from_date'] ?? '';
$to_date      = $_GET['to_date'] ?? '';

// ==============================
// 3. Build SQL động
// ==============================
$sql = "SELECT l.*, a.HoTen
        FROM lichsuhoatdong l
        LEFT JOIN admin a ON l.IdAdmin = a.Id
        WHERE 1=1";

if ($filter_admin > 0) {
    $sql .= " AND l.IdAdmin = $filter_admin";
}

if ($from_date != '') {
    $sql .= " AND DATE(l.NgayThucHien) >= '$from_date'";
}

if ($to_date != '') {
    $sql .= " AND DATE(l.NgayThucHien) <= '$to_date'";
}

$sql .= " ORDER BY l.NgayThucHien DESC";

$logs = mysqli_query($conn, $sql);

// ==============================
// 4. Danh sách admin cho dropdown
// ==============================
$admins = mysqli_query($conn, "SELECT Id, HoTen FROM admin");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch sử hoạt động</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="sidebar">
    <h4 class="text-center mb-4">NovaWear Admin</h4>
    <div class="px-3 mb-3 text-white">
        Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
    </div>
    <hr style="border-color: #4f5962;">
    <nav>
        <a href="category.php">Danh mục sản phẩm</a>
        <a href="product/product.php">Quản lý sản phẩm</a>
        <a href="orders/orders.php">Quản lý đơn hàng</a>
        <a href="news/news.php">Tin tức</a>
        <a href="promotion/promotion.php">Quản lý Khuyến mãi</a>
        <a href="banner/banner.php">Quảng cáo</a>
        <a href="danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
        <a href="lich_su_hoat_dong.php" class="active">Lịch sử hoạt động</a>
        <a href="logout.php">Đăng xuất</a>
    </nav>
</div>

<div class="main-content">
    <h3 class="mb-4">📜 Lịch sử hoạt động Admin</h3>

    <!-- ================= FILTER ================= -->
    <form method="get" class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label">Người thực hiện</label>
            <select name="admin_id" class="form-select">
                <option value="0">-- Tất cả admin --</option>
                <?php while ($a = mysqli_fetch_assoc($admins)) { ?>
                    <option value="<?= $a['Id'] ?>"
                        <?= ($filter_admin == $a['Id']) ? 'selected' : '' ?>>
                        <?= $a['HoTen'] ?>
                    </option>
                <?php } ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Từ ngày</label>
            <input type="date" name="from_date" value="<?= $from_date ?>" class="form-control">
        </div>

        <div class="col-md-3">
            <label class="form-label">Đến ngày</label>
            <input type="date" name="to_date" value="<?= $to_date ?>" class="form-control">
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Lọc</button>
        </div>
    </form>

    <!-- ================= TABLE ================= -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Admin</th>
                        <th>Hành động</th>
                        <th>Bảng</th>
                        <th>ID</th>
                        <th>Nội dung</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $i = 1;
                if (mysqli_num_rows($logs) > 0) {
                    while ($row = mysqli_fetch_assoc($logs)) {
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= $row['HoTen'] ?></td>
                        <td><?= $row['HanhDong'] ?></td>
                        <td><?= $row['BangDuLieu'] ?></td>
                        <td><?= $row['IdBanGhi'] ?></td>
                        <td><?= $row['NoiDung'] ?></td>
                        <td><?= $row['NgayThucHien'] ?></td>
                    </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='8' class='text-center text-muted py-3'>Không có dữ liệu</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
