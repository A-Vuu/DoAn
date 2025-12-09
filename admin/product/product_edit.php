<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$id = $_GET['id']; 

// --- 1. XỬ LÝ XÓA BIẾN THỂ ---
if (isset($_GET['del_variant'])) {
    $idVar = $_GET['del_variant'];
    mysqli_query($conn, "DELETE FROM ChiTietSanPham WHERE Id = $idVar");
    echo "<script>window.location='product_edit.php?id=$id';</script>";
}

// --- 2. XỬ LÝ THÊM BIẾN THỂ ---
if (isset($_POST['add_variant'])) {
    $mau = $_POST['new_color'];
    $size = $_POST['new_size'];
    $sl = $_POST['new_qty'];
    $skuParent = $_POST['sku_parent']; 
    
    // Kiểm tra trùng
    $check = mysqli_query($conn, "SELECT Id FROM ChiTietSanPham WHERE IdSanPham=$id AND IdMauSac=$mau AND IdKichThuoc=$size");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Biến thể này đã tồn tại!');</script>";
    } else {
        $skuChild = $skuParent . "-M" . $mau . "-S" . $size;
        $sqlAddVar = "INSERT INTO ChiTietSanPham (IdSanPham, IdMauSac, IdKichThuoc, SoLuong, SKU) 
                      VALUES ('$id', '$mau', '$size', '$sl', '$skuChild')";
        mysqli_query($conn, $sqlAddVar);
        echo "<script>window.location='product_edit.php?id=$id';</script>";
    }
}

// --- 3. XỬ LÝ CẬP NHẬT SỐ LƯỢNG ---
if (isset($_POST['update_quantities'])) {
    if (isset($_POST['qty'])) {
        foreach ($_POST['qty'] as $idVar => $soLuong) {
            mysqli_query($conn, "UPDATE ChiTietSanPham SET SoLuong = '$soLuong' WHERE Id = $idVar");
        }
    }
    // Cập nhật tổng tồn kho
    mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = (SELECT SUM(SoLuong) FROM ChiTietSanPham WHERE IdSanPham=$id) WHERE Id=$id");
    echo "<script>alert('Đã cập nhật số lượng!'); window.location='product_edit.php?id=$id';</script>";
}

// --- 4. XỬ LÝ CẬP NHẬT CHUNG (BAO GỒM CẢ ẢNH) ---
if (isset($_POST['update_product'])) {
    $tenSP = $_POST['ten_sp'];
    $sku = $_POST['sku'];
    $gia = $_POST['gia'];
    $danhmuc = $_POST['danhmuc'];
    $mota = $_POST['mota'];
    
    // Lấy trạng thái từ checkbox
    $hienthi = isset($_POST['hienthi']) ? 1 : 0;
    
    // --- THÊM DÒNG NÀY: Lấy trạng thái Nổi bật ---
    $noibat = isset($_POST['noibat']) ? 1 : 0;

    if (empty($sku)) $sku = "SP" . date("YmdHis") . rand(10, 99);

    // --- THÊM DÒNG NÀY: Lấy trạng thái Sản phẩm mới ---
    $sanphammoi = isset($_POST['sanphammoi']) ? 1 : 0;

    if (empty($sku)) $sku = "SP" . date("YmdHis") . rand(10, 99);

    // --- SỬA CÂU SQL: Thêm SanPhamMoi='$sanphammoi' ---
    $sqlUpdate = "UPDATE SanPham SET 
                    TenSanPham='$tenSP', 
                    MaSanPham='$sku', 
                    GiaGoc='$gia', 
                    IdDanhMuc='$danhmuc', 
                    MoTaNgan='$mota', 
                    HienThi='$hienthi',
                    SanPhamNoiBat='$noibat',
                    SanPhamMoi='$sanphammoi'  
                  WHERE Id=$id";

    try {
        if (mysqli_query($conn, $sqlUpdate)) {
            // --- XỬ LÝ ẢNH (Đoạn này giờ sẽ hoạt động) ---
            if (isset($_FILES['anh_moi']) && $_FILES['anh_moi']['name'] != "") {
                $target_dir = "../../uploads/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                $fileName = time() . "_" . basename($_FILES["anh_moi"]["name"]);
                
                if(move_uploaded_file($_FILES["anh_moi"]["tmp_name"], $target_dir . $fileName)) {
                    // Kiểm tra xem đã có ảnh chưa
                    $checkAnh = mysqli_query($conn, "SELECT Id FROM AnhSanPham WHERE IdSanPham = $id LIMIT 1");
                    if (mysqli_num_rows($checkAnh) > 0) {
                        mysqli_query($conn, "UPDATE AnhSanPham SET DuongDanAnh = '$fileName' WHERE IdSanPham = $id");
                    } else {
                        mysqli_query($conn, "INSERT INTO AnhSanPham (IdSanPham, DuongDanAnh, LaAnhChinh) VALUES ('$id', '$fileName', 1)");
                    }
                }
            }
            echo "<script>alert('Cập nhật thành công!'); window.location='product_edit.php?id=$id';</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Lỗi: Mã SKU đã tồn tại!');</script>";
    }
}

// LẤY DỮ LIỆU HIỂN THỊ
$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM SanPham WHERE Id = $id"));
$anhData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM AnhSanPham WHERE IdSanPham = $id LIMIT 1"));
$anhHienTai = ($anhData) ? $anhData['DuongDanAnh'] : '';

$variants = mysqli_query($conn, "SELECT ct.*, m.TenMau, m.MaMau, k.TenKichThuoc 
                                 FROM ChiTietSanPham ct 
                                 JOIN MauSac m ON ct.IdMauSac = m.Id 
                                 JOIN KichThuoc k ON ct.IdKichThuoc = k.Id 
                                 WHERE ct.IdSanPham = $id ORDER BY k.ThuTuSapXep");
$colors = mysqli_query($conn, "SELECT * FROM MauSac");
$sizes = mysqli_query($conn, "SELECT * FROM KichThuoc ORDER BY ThuTuSapXep");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm</title>
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
            <!-- <a href="index.php">Trang chủ</a> -->
            <a href="../category.php">Danh mục sản phẩm</a>
            <a href="product.php" class="active">Quản lý sản phẩm</a>
            <a href="../news/news.php" >Tin tức</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between mb-3">
            <h3>Sửa sản phẩm: <?php echo $row['TenSanPham']; ?></h3>
            <a href="product.php" class="btn btn-secondary">Quay lại danh sách</a>
        </div>

        <form method="POST" enctype="multipart/form-data"> 
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><strong>1. Thông tin chung</strong></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tên sản phẩm</label>
                                    <input type="text" name="ten_sp" class="form-control" value="<?php echo $row['TenSanPham']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mã SKU (Gốc)</label>
                                    <input type="text" name="sku" class="form-control" value="<?php echo $row['MaSanPham']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Danh mục</label>
                                    <select name="danhmuc" class="form-select">
                                        <?php 
                                        $cats = mysqli_query($conn, "SELECT * FROM DanhMucSanPham");
                                        while($c = mysqli_fetch_assoc($cats)) {
                                            $sel = ($c['Id'] == $row['IdDanhMuc']) ? 'selected' : '';
                                            echo "<option value='{$c['Id']}' $sel>{$c['TenDanhMuc']}</option>";
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giá bán</label>
                                    <input type="number" name="gia" class="form-control" value="<?php echo $row['GiaGoc']; ?>">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Mô tả ngắn</label>
                                    <textarea name="mota" class="form-control" rows="2"><?php echo $row['MoTaNgan']; ?></textarea>
                                    <button type="submit" name="update_product" class="btn btn-success w-100">LƯU THAY ĐỔI</button>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="hienthi" <?php echo ($row['HienThi']==1)?'checked':''; ?>>
                                        <label class="form-check-label">Hiển thị sản phẩm trên web</label>
                                    </div>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="noibat" id="hotSwitch" <?php echo ($row['SanPhamNoiBat'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold text-warning" for="hotSwitch">Sản phẩm Nổi bật (HOT)</label>
                                    </div>
                                    <div class="form-check form-switch mb-3 mt-2">
                                        <input class="form-check-input" type="checkbox" name="sanphammoi" id="newSwitch" <?php echo ($row['SanPhamMoi'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold text-success" for="newSwitch">Sản phẩm Mới (NEW)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header bg-white"><strong>Ảnh đại diện</strong></div>
                        <div class="card-body text-center">
                            <?php if($anhHienTai): ?>
                                <img src="../../uploads/<?php echo $anhHienTai; ?>" class="img-thumbnail mb-2" style="max-height: 200px;">
                            <?php else: ?>
                                <div class="alert alert-warning py-3">Chưa có ảnh</div>
                            <?php endif; ?>
                            
                            <div class="mt-2 text-start">
                                <label class="form-label small">Thay đổi ảnh:</label>
                                <input type="file" name="anh_moi" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_product" class="btn btn-warning w-100 p-2 fw-bold mb-3">LƯU THAY ĐỔI</button>
                </div>
            </div>
        </form>
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-info">
                    <div class="card-header bg-info text-white">
                        <strong>2. Quản lý Size & Màu (<?php echo mysqli_num_rows($variants); ?> biến thể)</strong>
                    </div>
                    <div class="card-body">
                        <form method="POST"> 
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Màu sắc</th>
                                        <th>Size</th>
                                        <th>SKU Con</th>
                                        <th width="120px">Số lượng</th>
                                        <th>Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($variants) > 0): ?>
                                        <?php while($v = mysqli_fetch_assoc($variants)): ?>
                                        <tr>
                                            <td>
                                                <span style="display:inline-block;width:15px;height:15px;background:<?php echo $v['MaMau']; ?>;border:1px solid #ccc;"></span>
                                                <?php echo $v['TenMau']; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo $v['TenKichThuoc']; ?></span></td>
                                            <td><small class="text-muted"><?php echo $v['SKU']; ?></small></td>
                                            <td>
                                                <input type="number" name="qty[<?php echo $v['Id']; ?>]" class="form-control form-control-sm text-center" value="<?php echo $v['SoLuong']; ?>">
                                            </td>
                                            <td>
                                                <a href="product_edit.php?id=<?php echo $id; ?>&del_variant=<?php echo $v['Id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Xóa biến thể này?');">&times;</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">Chưa có biến thể.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <?php if(mysqli_num_rows($variants) > 0): ?>
                                <div class="text-end mb-3">
                                    <button type="submit" name="update_quantities" class="btn btn-sm btn-info text-white">Cập nhật số lượng</button>
                                </div>
                            <?php endif; ?>
                        </form>

                        <hr>
                        <h6 class="text-primary"><i class="fas fa-plus-circle"></i> Thêm Size/Màu mới</h6>
                        <form method="POST" class="row g-2 align-items-end">
                            <input type="hidden" name="sku_parent" value="<?php echo $row['MaSanPham']; ?>">
                            <div class="col-md-4">
                                <select name="new_color" class="form-select form-select-sm" required>
                                    <option value="">-- Màu --</option>
                                    <?php mysqli_data_seek($colors, 0); while($m = mysqli_fetch_assoc($colors)) echo "<option value='{$m['Id']}'>{$m['TenMau']}</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="new_size" class="form-select form-select-sm" required>
                                    <option value="">-- Size --</option>
                                    <?php mysqli_data_seek($sizes, 0); while($s = mysqli_fetch_assoc($sizes)) echo "<option value='{$s['Id']}'>{$s['TenKichThuoc']}</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="new_qty" class="form-control form-control-sm" value="10" placeholder="SL">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_variant" class="btn btn-success btn-sm w-100">Thêm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>