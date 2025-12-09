<?php
session_start();
require_once '../../config.php'; // Lùi 2 cấp

if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$dulieu_sua = null;

// --- XỬ LÝ THÊM / SỬA TIN TỨC ---
if (isset($_POST['save_news'])) {
    $tieuDe = $_POST['tieude'];
    $tomTat = $_POST['tomtat'];
    $noiDung = $_POST['noidung'];
    $danhMuc = $_POST['danhmuc'];
    $tacGia = $_SESSION['admin_name']; // Lấy tên Admin đang đăng nhập

    // Xử lý Upload Ảnh
    $hinhAnh = "";
    if (isset($_FILES['anh']) && $_FILES['anh']['name'] != "") {
        $target_dir = "../../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $hinhAnh = time() . "_" . basename($_FILES["anh"]["name"]); // Thêm time để tránh trùng tên
        move_uploaded_file($_FILES["anh"]["tmp_name"], $target_dir . $hinhAnh);
    }

    // Kiểm tra: Đang THÊM hay SỬA?
    if (isset($_POST['id_sua']) && !empty($_POST['id_sua'])) {
        // --- SỬA ---
        $id = $_POST['id_sua'];
        $sqlAnh = ($hinhAnh != "") ? ", AnhDaiDien='$hinhAnh'" : ""; // Chỉ cập nhật ảnh nếu có up mới
        $sql = "UPDATE TinTuc SET TieuDe='$tieuDe', TomTat='$tomTat', NoiDung='$noiDung', IdDanhMuc='$danhMuc' $sqlAnh WHERE Id=$id";
        mysqli_query($conn, $sql);
        echo "<script>alert('Cập nhật bài viết thành công!'); window.location='news.php';</script>";
    } else {
        // --- THÊM MỚI ---
        // Nếu không up ảnh thì dùng ảnh rỗng hoặc mặc định
        $sql = "INSERT INTO TinTuc (TieuDe, TomTat, NoiDung, IdDanhMuc, AnhDaiDien, TacGia) 
                VALUES ('$tieuDe', '$tomTat', '$noiDung', '$danhMuc', '$hinhAnh', '$tacGia')";
        mysqli_query($conn, $sql);
        echo "<script>alert('Đăng bài thành công!'); window.location='news.php';</script>";
    }
}

// --- XỬ LÝ XÓA ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM TinTuc WHERE Id=$id");
    echo "<script>alert('Đã xóa bài viết!'); window.location='news.php';</script>";
}

// --- LẤY DỮ LIỆU SỬA ---
if (isset($_GET['edit'])) {
    $id_edit = $_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM TinTuc WHERE Id=$id_edit");
    $dulieu_sua = mysqli_fetch_assoc($res);
}

// --- LẤY DANH SÁCH ---
$newsList = mysqli_query($conn, "SELECT t.*, d.TenDanhMuc FROM TinTuc t LEFT JOIN DanhMucTinTuc d ON t.IdDanhMuc = d.Id ORDER BY t.Id DESC");
$catList = mysqli_query($conn, "SELECT * FROM DanhMucTinTuc");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Tin tức</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3">

            <img> <?php echo $_SESSION['admin_name']; ?>

            <!-- <img src="https://via.placeholder.com/30" class="img-circle"> <?php echo $_SESSION['admin_name']; ?> -->

        </div>

        <hr style="border-color: #4f5962;">
        <nav>
            <a href="../category.php">Danh mục sản phẩm</a>
            <a href="../product/product.php">Quản lý sản phẩm</a>
            <a href="news.php" class="active">Tin tức</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <h3 class="mb-4">Quản lý Tin tức & Bài viết</h3>
        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <strong><?php echo $dulieu_sua ? 'Cập nhật bài viết' : 'Đăng bài mới'; ?></strong>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if($dulieu_sua): ?>
                                <input type="hidden" name="id_sua" value="<?php echo $dulieu_sua['Id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Tiêu đề bài viết *</label>
                                <input type="text" name="tieude" class="form-control" required value="<?php echo $dulieu_sua ? $dulieu_sua['TieuDe'] : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Chuyên mục</label>
                                <select name="danhmuc" class="form-select">
                                    <?php 
                                    mysqli_data_seek($catList, 0);
                                    while($c = mysqli_fetch_assoc($catList)): 
                                        $sel = ($dulieu_sua && $dulieu_sua['IdDanhMuc'] == $c['Id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $c['Id']; ?>" <?php echo $sel; ?>><?php echo $c['TenDanhMuc']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ảnh đại diện</label>
                                <input type="file" name="anh" class="form-control">
                                <?php if($dulieu_sua && $dulieu_sua['AnhDaiDien']) echo "<img src='../../uploads/".$dulieu_sua['AnhDaiDien']."' width='60' class='mt-2'>"; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tóm tắt ngắn</label>
                                <textarea name="tomtat" class="form-control" rows="3"><?php echo $dulieu_sua ? $dulieu_sua['TomTat'] : ''; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nội dung chi tiết</label>
                                <textarea name="noidung" class="form-control" rows="6"><?php echo $dulieu_sua ? $dulieu_sua['NoiDung'] : ''; ?></textarea>
                            </div>

                            <button type="submit" name="save_news" class="btn btn-success w-100 fw-bold">LƯU BÀI VIẾT</button>
                            <?php if($dulieu_sua): ?>
                                <a href="news.php" class="btn btn-secondary w-100 mt-2">Hủy bỏ</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><strong>Danh sách bài viết</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th width="10%">Ảnh</th>
                                    <th width="40%">Tiêu đề</th>
                                    <th width="20%">Danh mục</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($newsList)): ?>
                                <tr>
                                    <td>
                                        <?php if($row['AnhDaiDien']): ?>
                                            <img src="../../uploads/<?php echo $row['AnhDaiDien']; ?>" width="50" style="border-radius:4px;">
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No img</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $row['TieuDe']; ?></strong><br>
                                        <small class="text-muted"><?php echo date('d/m/Y', strtotime($row['NgayDang'])); ?> bởi <?php echo $row['TacGia']; ?></small>
                                    </td>
                                    <td><span class="badge bg-info"><?php echo $row['TenDanhMuc']; ?></span></td>
                                    <td>
                                        <a href="news.php?edit=<?php echo $row['Id']; ?>" class="btn btn-sm btn-info text-white">Sửa</a>
                                        <a href="news.php?delete=<?php echo $row['Id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa bài này?');">Xóa</a>
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