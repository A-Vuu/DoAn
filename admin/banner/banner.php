<?php
session_start();
require_once '../../config.php';
require_once '../helpers/log_activity.php';


if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$dulieu_sua = null;

// --- XỬ LÝ LƯU BANNER ---
if (isset($_POST['save_banner'])) {
    $tieuDe = $_POST['tieude'];
    $viTri = $_POST['vitri'];
    $lienKet = $_POST['lienket'];
    $thuTu = $_POST['thutu'];

    // Upload ảnh
    $hinhAnh = "";
    if (isset($_FILES['anh']) && $_FILES['anh']['name'] != "") {
        $target_dir = "../../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $hinhAnh = time() . "_bn_" . basename($_FILES["anh"]["name"]);
        move_uploaded_file($_FILES["anh"]["tmp_name"], $target_dir . $hinhAnh);
    }

    // ================== SỬA ==================
    if (isset($_POST['id_sua']) && !empty($_POST['id_sua'])) {
        $id = (int)$_POST['id_sua'];

        $sqlAnh = ($hinhAnh != "") ? ", HinhAnh='$hinhAnh'" : "";
        $sql = "UPDATE Banner 
                SET TieuDe='$tieuDe', ViTri='$viTri', LienKet='$lienKet', ThuTu='$thuTu' $sqlAnh
                WHERE Id=$id";
        mysqli_query($conn, $sql);

        // 👉 GHI LỊCH SỬ
        ghiLichSuAdmin(
            $conn,
            "Sửa banner",
            "Banner",
            $id,
            "Sửa banner: $tieuDe"
        );

    } 
    // ================== THÊM ==================
    else {
        $sql = "INSERT INTO Banner (TieuDe, HinhAnh, ViTri, LienKet, ThuTu, LoaiLienKet) 
                VALUES ('$tieuDe', '$hinhAnh', '$viTri', '$lienKet', '$thuTu', 'URL')";
        mysqli_query($conn, $sql);

        $idMoi = mysqli_insert_id($conn);

        // 👉 GHI LỊCH SỬ
        ghiLichSuAdmin(
            $conn,
            "Thêm banner",
            "Banner",
            $idMoi,
            "Thêm banner: $tieuDe"
        );
    }

    header("Location: banner.php");
    exit();
}


// --- XÓA BANNER ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Lấy tiêu đề trước khi xóa
    $rs = mysqli_query($conn, "SELECT TieuDe FROM Banner WHERE Id=$id");
    $tieuDe = mysqli_fetch_assoc($rs)['TieuDe'] ?? '';

    mysqli_query($conn, "DELETE FROM Banner WHERE Id=$id");

    // 👉 GHI LỊCH SỬ
    ghiLichSuAdmin(
        $conn,
        "Xóa banner",
        "Banner",
        $id,
        "Xóa banner: $tieuDe"
    );

    header("Location: banner.php");
    exit();
}


// --- LẤY DỮ LIỆU SỬA ---
if (isset($_GET['edit'])) {
    $id_edit = $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM Banner WHERE Id=$id_edit");
    $dulieu_sua = mysqli_fetch_assoc($res);
}

// --- LẤY DANH SÁCH ---
$banners = mysqli_query($conn, "SELECT * FROM Banner ORDER BY ViTri, ThuTu");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Quảng cáo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3 text-white">
             Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
        </div>

        <hr style="border-color: #4f5962;">
        <nav>
            <a href="../category.php">Danh mục sản phẩm</a>
            <a href="../product/product.php">Quản lý sản phẩm</a>
            <a href="../orders/orders.php">Quản lý đơn hàng</a>
            <a href="../news/news.php">Tin tức</a>
            <a href="banner.php" class="active">Quảng cáo</a>
            <a href="../danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
            <a href="../lich_su_hoat_dong.php">Lịch sử hoạt động</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <h3 class="mb-4">Quản lý Banner & Quảng cáo</h3>
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <strong><?php echo $dulieu_sua ? 'Sửa Banner' : 'Thêm Banner Mới'; ?></strong>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if($dulieu_sua): ?>
                                <input type="hidden" name="id_sua" value="<?php echo $dulieu_sua['Id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Tiêu đề (Tên gợi nhớ)</label>
                                <input type="text" name="tieude" class="form-control" required value="<?php echo $dulieu_sua ? $dulieu_sua['TieuDe'] : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Hình ảnh *</label>
                                <input type="file" name="anh" class="form-control" <?php echo $dulieu_sua ? '' : 'required'; ?>>
                                <?php if($dulieu_sua && $dulieu_sua['HinhAnh']) echo "<img src='../../uploads/".$dulieu_sua['HinhAnh']."' width='100' class='mt-2 border'>"; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Vị trí hiển thị</label>
                                <select name="vitri" class="form-select">
                                    <option value="TrangChu" <?php echo ($dulieu_sua && $dulieu_sua['ViTri']=='TrangChu')?'selected':''; ?>>Slide Trang Chủ</option>
                                    <!--  -->
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Link liên kết (Khi bấm vào)</label>
                                <input type="text" name="lienket" class="form-control" placeholder="https://..." value="<?php echo $dulieu_sua ? $dulieu_sua['LienKet'] : '#'; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" name="thutu" class="form-control" value="<?php echo $dulieu_sua ? $dulieu_sua['ThuTu'] : '0'; ?>">
                            </div>

                            <button type="submit" name="save_banner" class="btn btn-success w-100 fw-bold">LƯU BANNER</button>
                            <?php if($dulieu_sua): ?><a href="banner.php" class="btn btn-secondary w-100 mt-2">Hủy</a><?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ảnh</th>
                                    <th>Tiêu đề / Link</th>
                                    <th>Vị trí</th>
                                    <th>Thứ tự</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($banners)): ?>
                                <tr>
                                    <td><img src="../../uploads/<?php echo $row['HinhAnh']; ?>" height="50" style="border-radius:4px;"></td>
                                    <td>
                                        <strong><?php echo $row['TieuDe']; ?></strong><br>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 150px;"><?php echo $row['LienKet']; ?></small>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo $row['ViTri']; ?></span></td>
                                    <td><?php echo $row['ThuTu']; ?></td>
                                    <td>
                                        <a href="banner.php?edit=<?php echo $row['Id']; ?>" class="btn btn-sm btn-info text-white">Sửa</a>
                                        <a href="banner.php?delete=<?php echo $row['Id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa banner này?');">Xóa</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>