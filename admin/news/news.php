<?php
session_start();
// Đảm bảo đường dẫn file config đúng với cấu trúc thư mục của bạn
require_once '../../config.php'; 

if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$dulieu_sua = null;

// --- XỬ LÝ THÊM / SỬA TIN TỨC ---
if (isset($_POST['save_news'])) {
    // Sử dụng mysqli_real_escape_string để tránh lỗi khi nội dung có ký tự đặc biệt (dấu nháy đơn, nháy kép)
    // Đặc biệt quan trọng khi dùng trình soạn thảo HTML
    $tieuDe  = mysqli_real_escape_string($conn, $_POST['tieude']);
    $tomTat  = mysqli_real_escape_string($conn, $_POST['tomtat']);
    $noiDung = mysqli_real_escape_string($conn, $_POST['noidung']); // CKEditor sẽ gửi mã HTML về đây
    $danhMuc = $_POST['danhmuc'];
    $tacGia  = $_SESSION['admin_name'];

    // Xử lý Upload Ảnh Đại Diện (Giữ nguyên logic của bạn)
    $hinhAnh = "";
    if (isset($_FILES['anh']) && $_FILES['anh']['name'] != "") {
        $target_dir = "../../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        // Tạo tên file mới để tránh trùng
        $file_extension = pathinfo($_FILES["anh"]["name"], PATHINFO_EXTENSION);
        $hinhAnh = time() . "_" . uniqid() . "." . $file_extension;
        
        move_uploaded_file($_FILES["anh"]["tmp_name"], $target_dir . $hinhAnh);
    }

    // Kiểm tra: Đang THÊM hay SỬA?
    if (isset($_POST['id_sua']) && !empty($_POST['id_sua'])) {
        // --- SỬA ---
        $id = $_POST['id_sua'];
        $sqlAnh = ($hinhAnh != "") ? ", AnhDaiDien='$hinhAnh'" : "";
        $sql = "UPDATE TinTuc SET TieuDe='$tieuDe', TomTat='$tomTat', NoiDung='$noiDung', IdDanhMuc='$danhMuc' $sqlAnh WHERE Id=$id";
        
        if(mysqli_query($conn, $sql)){
            echo "<script>alert('Cập nhật bài viết thành công!'); window.location='news.php';</script>";
        } else {
            echo "<script>alert('Lỗi: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        // --- THÊM MỚI ---
        $sql = "INSERT INTO TinTuc (TieuDe, TomTat, NoiDung, IdDanhMuc, AnhDaiDien, TacGia) 
                VALUES ('$tieuDe', '$tomTat', '$noiDung', '$danhMuc', '$hinhAnh', '$tacGia')";
        
        if(mysqli_query($conn, $sql)){
            echo "<script>alert('Đăng bài thành công!'); window.location='news.php';</script>";
        } else {
            echo "<script>alert('Lỗi: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- XỬ LÝ XÓA ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']); // Ép kiểu số để bảo mật
    // Có thể xóa thêm file ảnh cũ nếu cần (nâng cao)
    mysqli_query($conn, "DELETE FROM TinTuc WHERE Id=$id");
    echo "<script>alert('Đã xóa bài viết!'); window.location='news.php';</script>";
}

// --- LẤY DỮ LIỆU SỬA ---
if (isset($_GET['edit'])) {
    $id_edit = intval($_GET['edit']);
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
    
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
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
            <a href="news.php" class="active">Tin tức</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="../danhgia&chan/danhgia_chan.php">Đánh giá & chặn</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <!-- <div class="container-fluid"> -->
            <h3 class="mb-4">Quản lý Tin tức & Bài viết</h3>
            <div class="row">
                <div class="col-md-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <strong><?php echo $dulieu_sua ? 'Cập nhật bài viết' : 'Đăng bài mới'; ?></strong>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <?php if($dulieu_sua): ?>
                                    <input type="hidden" name="id_sua" value="<?php echo $dulieu_sua['Id']; ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tiêu đề bài viết *</label>
                                    <input type="text" name="tieude" class="form-control" required value="<?php echo $dulieu_sua ? htmlspecialchars($dulieu_sua['TieuDe']) : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Chuyên mục</label>
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
                                    <label class="form-label fw-bold">Ảnh đại diện (Thumbnail)</label>
                                    <input type="file" name="anh" class="form-control">
                                    <?php if($dulieu_sua && $dulieu_sua['AnhDaiDien']): ?>
                                        <div class="mt-2">
                                            <img src="../../uploads/<?php echo $dulieu_sua['AnhDaiDien']; ?>" width="100" class="img-thumbnail">
                                            <div class="small text-muted">Ảnh hiện tại</div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tóm tắt ngắn</label>
                                    <textarea name="tomtat" class="form-control" rows="3"><?php echo $dulieu_sua ? htmlspecialchars($dulieu_sua['TomTat']) : ''; ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nội dung chi tiết (Có thể chèn ảnh)</label>
                                    <textarea name="noidung" id="noidung" class="form-control" rows="10"><?php echo $dulieu_sua ? $dulieu_sua['NoiDung'] : ''; ?></textarea>
                                </div>

                                <button type="submit" name="save_news" class="btn btn-success w-100 fw-bold py-2">
                                    <i class="fa fa-save"></i> LƯU BÀI VIẾT
                                </button>
                                <?php if($dulieu_sua): ?>
                                    <a href="news.php" class="btn btn-secondary w-100 mt-2">Hủy bỏ chế độ sửa</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white"><strong>Danh sách bài viết</strong></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="10%" class="text-center">Ảnh</th>
                                            <th width="45%">Tiêu đề</th>
                                            <th width="20%">Danh mục</th>
                                            <th class="text-end pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($row = mysqli_fetch_assoc($newsList)): ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php if($row['AnhDaiDien']): ?>
                                                    <img src="../../uploads/<?php echo $row['AnhDaiDien']; ?>" width="60" height="60" style="object-fit: cover; border-radius:4px;">
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No img</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo $row['TieuDe']; ?></div>
                                                <small class="text-muted">
                                                    <i class="bi bi-person"></i> <?php echo $row['TacGia']; ?> | 
                                                    <i class="bi bi-clock"></i> <?php echo date('d/m/Y', strtotime($row['NgayDang'])); ?>
                                                </small>
                                            </td>
                                            <td><span class="badge bg-info text-dark"><?php echo $row['TenDanhMuc']; ?></span></td>
                                            <td class="text-end pe-3">
                                                <a href="news.php?edit=<?php echo $row['Id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                                <a href="news.php?delete=<?php echo $row['Id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn chắc chắn muốn xóa bài viết này?');">Xóa</a>
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
        <!-- </div> -->
    </div>

    <script>
        // Thay thế textarea có id="noidung" bằng CKEditor
        CKEDITOR.replace('noidung');
    </script>

</body>
</html>