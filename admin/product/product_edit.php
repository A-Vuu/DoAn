<?php
session_start();
require_once '../../config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$id = intval($_GET['id']); // Lấy ID sản phẩm cần sửa (bảo mật thêm intval)

// --- 1. XỬ LÝ XÓA BIẾN THỂ (ĐÃ NÂNG CẤP XÓA ẢNH) ---
if (isset($_GET['del_variant'])) {
    $idVar = intval($_GET['del_variant']);
    
    // Lấy tên ảnh cũ để xóa khỏi thư mục uploads (dọn dẹp rác)
    $queryAnh = mysqli_query($conn, "SELECT AnhBienThe FROM ChiTietSanPham WHERE Id = $idVar");
    $rowAnh = mysqli_fetch_assoc($queryAnh);
    if (!empty($rowAnh['AnhBienThe'])) {
        $path = "../../uploads/" . $rowAnh['AnhBienThe'];
        if (file_exists($path)) unlink($path); // Xóa file
    }

    mysqli_query($conn, "DELETE FROM ChiTietSanPham WHERE Id = $idVar");
    
    // Cập nhật lại tổng tồn kho sau khi xóa
    mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = (SELECT IFNULL(SUM(SoLuong), 0) FROM ChiTietSanPham WHERE IdSanPham=$id) WHERE Id=$id");
    echo "<script>window.location='product_edit.php?id=$id';</script>";
    exit();
}

// --- 2. XỬ LÝ THÊM BIẾN THỂ MỚI (ĐÃ THÊM UPLOAD ẢNH) ---
if (isset($_POST['add_variant'])) {
    $mau = intval($_POST['new_color']);
    $size = intval($_POST['new_size']);
    $sl = (int)$_POST['new_qty'];
    $skuParent = $_POST['sku_parent']; 
    
    // Kiểm tra trùng
    $check = mysqli_query($conn, "SELECT Id FROM ChiTietSanPham WHERE IdSanPham=$id AND IdMauSac=$mau AND IdKichThuoc=$size");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Biến thể này (Màu + Size) đã tồn tại!');</script>";
    } else {
        $skuChild = $skuParent . "-M" . $mau . "-S" . $size;
        
        // --- LOGIC UPLOAD ẢNH BIẾN THỂ ---
        $anhBienThe = 'NULL'; // Mặc định là NULL nếu không up ảnh
        
        if (isset($_FILES['new_img']) && $_FILES['new_img']['name'] != "") {
            $target_dir = "../../uploads/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            // Đặt tên file tránh trùng: time_var_IDMàu_TênGốc
            $fileName = time() . "_var_" . $mau . "_" . basename($_FILES["new_img"]["name"]);
            
            if (move_uploaded_file($_FILES["new_img"]["tmp_name"], $target_dir . $fileName)) {
                $anhBienThe = "'$fileName'"; // Chuỗi tên file để đưa vào SQL
            }
        }
        // -----------------------------------

        $sqlAddVar = "INSERT INTO ChiTietSanPham (IdSanPham, IdMauSac, IdKichThuoc, SoLuong, SKU, AnhBienThe) 
                      VALUES ('$id', '$mau', '$size', '$sl', '$skuChild', $anhBienThe)";
        
        if(mysqli_query($conn, $sqlAddVar)){
            // Cập nhật tổng tồn kho
            mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = (SELECT SUM(SoLuong) FROM ChiTietSanPham WHERE IdSanPham=$id) WHERE Id=$id");
            echo "<script>window.location='product_edit.php?id=$id';</script>";
        } else {
            echo "<script>alert('Lỗi thêm biến thể: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// --- 3. XỬ LÝ CẬP NHẬT SỐ LƯỢNG BIẾN THỂ ---
if (isset($_POST['update_quantities'])) {
    if (isset($_POST['qty'])) {
        foreach ($_POST['qty'] as $idVar => $soLuong) {
            $sl = (int)$soLuong;
            mysqli_query($conn, "UPDATE ChiTietSanPham SET SoLuong = '$sl' WHERE Id = $idVar");
        }
    }
    // Cập nhật tổng tồn kho
    mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = (SELECT SUM(SoLuong) FROM ChiTietSanPham WHERE IdSanPham=$id) WHERE Id=$id");
    echo "<script>alert('Đã cập nhật số lượng tồn kho!'); window.location='product_edit.php?id=$id';</script>";
}

// --- 4. XỬ LÝ CẬP NHẬT THÔNG TIN CHUNG (MAIN UPDATE) ---
if (isset($_POST['update_product'])) {
    $tenSP = mysqli_real_escape_string($conn, $_POST['ten_sp']);
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    $gia = (int)$_POST['gia'];
    
    // Logic khuyến mãi
    $isSale = isset($_POST['khuyenmai']);
    $giaKM = ($isSale && !empty($_POST['gia_km']) && $_POST['gia_km'] > 0) ? (int)$_POST['gia_km'] : 'NULL';

    $danhmuc = $_POST['danhmuc'];
    $mota = mysqli_real_escape_string($conn, $_POST['mota']);
    $noidungChiTiet = mysqli_real_escape_string($conn, $_POST['noidung_chitiet']);
    $tonkho_tong = (int)$_POST['kho']; 
    
    $hienthi = isset($_POST['hienthi']) ? 1 : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    $sanphammoi = isset($_POST['sanphammoi']) ? 1 : 0;

    if (empty($sku)) $sku = "SP" . date("YmdHis") . rand(10, 99);

    $sqlUpdate = "UPDATE SanPham SET 
                    TenSanPham='$tenSP', 
                    MaSanPham='$sku', 
                    GiaGoc='$gia', 
                    GiaKhuyenMai=$giaKM,
                    IdDanhMuc='$danhmuc', 
                    MoTaNgan='$mota', 
                    MoTaChiTiet='$noidungChiTiet',
                    HienThi='$hienthi',
                    SanPhamNoiBat='$noibat',
                    SanPhamMoi='$sanphammoi'  
                  WHERE Id=$id";

    try {
        if (mysqli_query($conn, $sqlUpdate)) {
            
            // Xử lý tồn kho tổng (Nếu KHÔNG có biến thể thì cập nhật theo ô nhập)
            $checkVar = mysqli_query($conn, "SELECT Id FROM ChiTietSanPham WHERE IdSanPham=$id LIMIT 1");
            if (mysqli_num_rows($checkVar) == 0) {
                mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = $tonkho_tong WHERE Id=$id");
            }

            // --- XỬ LÝ ẢNH CHÍNH ---
            if (isset($_FILES['anh_moi']) && $_FILES['anh_moi']['name'] != "") {
                $target_dir = "../../uploads/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                $fileName = time() . "_" . basename($_FILES["anh_moi"]["name"]);
                
                if(move_uploaded_file($_FILES["anh_moi"]["tmp_name"], $target_dir . $fileName)) {
                    $checkAnh = mysqli_query($conn, "SELECT Id FROM AnhSanPham WHERE IdSanPham = $id LIMIT 1");
                    if (mysqli_num_rows($checkAnh) > 0) {
                        mysqli_query($conn, "UPDATE AnhSanPham SET DuongDanAnh = '$fileName' WHERE IdSanPham = $id");
                    } else {
                        mysqli_query($conn, "INSERT INTO AnhSanPham (IdSanPham, DuongDanAnh, LaAnhChinh) VALUES ('$id', '$fileName', 1)");
                    }
                }
            }
            echo "<script>alert('Cập nhật sản phẩm thành công!'); window.location='product_edit.php?id=$id';</script>";
        }
    } catch (Exception $e) {
        $err = mysqli_error($conn);
        echo "<script>alert('Lỗi CSDL: $err');</script>";
    }
}

// --- LẤY DỮ LIỆU HIỂN THỊ ---
$querySP = mysqli_query($conn, "SELECT * FROM SanPham WHERE Id = $id");
if(mysqli_num_rows($querySP) == 0) die("Sản phẩm không tồn tại");
$row = mysqli_fetch_assoc($querySP);

// Lấy ảnh chính
$anhData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM AnhSanPham WHERE IdSanPham = $id LIMIT 1"));
$anhHienTai = ($anhData) ? $anhData['DuongDanAnh'] : '';

// Lấy biến thể (Có thêm cột AnhBienThe)
$variants = mysqli_query($conn, "SELECT ct.*, m.TenMau, m.MaMau, k.TenKichThuoc 
                                 FROM ChiTietSanPham ct 
                                 JOIN MauSac m ON ct.IdMauSac = m.Id 
                                 JOIN KichThuoc k ON ct.IdKichThuoc = k.Id 
                                 WHERE ct.IdSanPham = $id ORDER BY m.Id, k.ThuTuSapXep");
$hasVariants = (mysqli_num_rows($variants) > 0);

$categories = mysqli_query($conn, "SELECT * FROM DanhMucSanPham");
$colors = mysqli_query($conn, "SELECT * FROM MauSac");
$sizes = mysqli_query($conn, "SELECT * FROM KichThuoc ORDER BY ThuTuSapXep");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm: <?php echo $row['TenSanPham']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
    <style>
        body { background-color: #f4f6f9; font-size: 14px; }
        .card { border: none; box-shadow: 0 0 10px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .card-header { background-color: #fff; border-bottom: 2px solid #ffc107; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; padding: 10px 15px; }
        .mb-3 { margin-bottom: 10px !important; }
        .form-control, .form-select { font-size: 13px; padding: 0.375rem 0.75rem; border-radius: 4px; }
        label { font-weight: 600; font-size: 13px; margin-bottom: 3px; color: #555; }
        .color-dot { width: 15px; height: 15px; display: inline-block; border-radius: 50%; border: 1px solid #ccc; vertical-align: middle; margin-right: 5px; }
    </style>
</head>
<body>

    <div class="container-fluid py-3">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="m-0 fw-bold text-dark"><i class="fas fa-edit"></i> Sửa sản phẩm</h4>
            <a href="product.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Quay lại</a>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_sp" value="<?php echo $id; ?>">

            <div class="row">
                <div class="col-lg-8">
                    
                    <div class="card">
                        <div class="card-header text-primary border-primary">Thông tin chung</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label>Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" name="ten_sp" class="form-control" value="<?php echo $row['TenSanPham']; ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Mã SKU</label>
                                    <input type="text" name="sku" class="form-control" value="<?php echo $row['MaSanPham']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Danh mục</label>
                                    <select name="danhmuc" class="form-select">
                                        <?php while($c = mysqli_fetch_assoc($categories)): ?>
                                            <option value="<?php echo $c['Id']; ?>" <?php echo ($c['Id'] == $row['IdDanhMuc']) ? 'selected' : ''; ?>>
                                                <?php echo $c['TenDanhMuc']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Mô tả ngắn</label>
                                <textarea name="mota" class="form-control" rows="2"><?php echo $row['MoTaNgan']; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <span><i class="fas fa-boxes"></i> Quản lý Biến thể (Size/Màu/Ảnh)</span>
                            <span class="badge bg-secondary"><?php echo mysqli_num_rows($variants); ?> phiên bản</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered table-striped mb-0 table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Màu sắc</th>
                                        <th class="text-center">Ảnh</th> <th>Size</th>
                                        <th>SKU Con</th>
                                        <th width="100">Số lượng</th>
                                        <th width="50" class="text-center">Xóa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(mysqli_num_rows($variants) > 0): ?>
                                        <?php while($v = mysqli_fetch_assoc($variants)): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="color-dot" style="background:<?php echo $v['MaMau']; ?>"></span>
                                                <?php echo $v['TenMau']; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if(!empty($v['AnhBienThe'])): ?>
                                                    <img src="../../uploads/<?php echo $v['AnhBienThe']; ?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 10px;">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-info text-dark"><?php echo $v['TenKichThuoc']; ?></span></td>
                                            <td class="text-muted small"><?php echo $v['SKU']; ?></td>
                                            <td>
                                                <input type="number" name="qty[<?php echo $v['Id']; ?>]" class="form-control form-control-sm text-center fw-bold" value="<?php echo $v['SoLuong']; ?>" min="0">
                                            </td>
                                            <td class="text-center">
                                                <a href="product_edit.php?id=<?php echo $id; ?>&del_variant=<?php echo $v['Id']; ?>" class="text-danger" onclick="return confirm('Bạn chắc chắn muốn xóa biến thể này?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">Sản phẩm này chưa có biến thể (Sản phẩm đơn)</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <div class="p-3 bg-light border-top">
                                <label class="small fw-bold text-success mb-2"><i class="fas fa-plus"></i> Thêm biến thể mới (Màu + Size + Ảnh):</label>
                                <div class="row g-2 align-items-center">
                                    <input type="hidden" name="sku_parent" value="<?php echo $row['MaSanPham']; ?>">
                                    
                                    <div class="col-md-3">
                                        <select name="new_color" class="form-select form-select-sm" >
                                            <option value="">-- Màu --</option>
                                            <?php mysqli_data_seek($colors, 0); while($m = mysqli_fetch_assoc($colors)) echo "<option value='{$m['Id']}'>{$m['TenMau']}</option>"; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="new_size" class="form-select form-select-sm" >
                                            <option value="">-- Size --</option>
                                            <?php mysqli_data_seek($sizes, 0); while($s = mysqli_fetch_assoc($sizes)) echo "<option value='{$s['Id']}'>{$s['TenKichThuoc']}</option>"; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <input type="file" name="new_img" class="form-control form-control-sm" title="Ảnh biến thể (nếu có)">
                                    </div>

                                    <div class="col-md-2">
                                        <input type="number" name="new_qty" class="form-control form-control-sm" placeholder="SL" value="10" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="add_variant" class="btn btn-success btn-sm w-100">Thêm</button>
                                    </div>
                                </div>
                            </div>

                            <?php if($hasVariants): ?>
                            <div class="p-2 text-end border-top">
                                <small class="text-muted fst-italic me-2">Đã sửa số lượng ở trên? Bấm vào đây 👉</small>
                                <button type="submit" name="update_quantities" class="btn btn-warning btn-sm fw-bold">Cập nhật Số lượng</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header text-primary border-primary">Mô tả chi tiết sản phẩm</div>
                        <div class="card-body p-0">
                            <textarea name="noidung_chitiet" id="editor1"><?php echo $row['MoTaChiTiet']; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" name="update_product" class="btn btn-primary w-100 py-2 fw-bold text-uppercase mb-3 shadow-sm">
                                <i class="fas fa-save"></i> Lưu Thay Đổi
                            </button>
                            
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="hienthi" id="sw_hienthi" <?php echo ($row['HienThi']==1)?'checked':''; ?>>
                                <label class="form-check-label" for="sw_hienthi">Hiển thị</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="sanphammoi" id="sw_new" <?php echo ($row['SanPhamMoi']==1)?'checked':''; ?>>
                                <label class="form-check-label text-primary" for="sw_new">Mới (New)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="noibat" id="sw_hot" <?php echo ($row['SanPhamNoiBat']==1)?'checked':''; ?>>
                                <label class="form-check-label text-warning fw-bold" for="sw_hot">Nổi bật (Hot)</label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header text-success border-success">Giá & Kho</div>
                        <div class="card-body bg-light">
                            <div class="mb-3">
                                <label>Giá gốc (VNĐ) <span class="text-danger">*</span></label>
                                <input type="number" name="gia" id="gia_goc" class="form-control fw-bold" value="<?php echo $row['GiaGoc']; ?>" required min="0">
                            </div>

                            <div class="p-2 mb-3 bg-white border rounded">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="khuyenmai" id="saleSwitch" 
                                           <?php echo (!empty($row['GiaKhuyenMai']) && $row['GiaKhuyenMai'] > 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label text-danger fw-bold" for="saleSwitch">Đang Sale</label>
                                </div>
                                <div id="box_khuyenmai" style="display: none;">
                                    <div class="row g-1">
                                        <div class="col-5">
                                            <small class="text-muted">Giảm %</small>
                                            <input type="number" id="phantram_giam" class="form-control form-control-sm text-danger" placeholder="%">
                                        </div>
                                        <div class="col-7">
                                            <small class="text-muted">Giá KM</small>
                                            <input type="number" name="gia_km" id="gia_km" class="form-control form-control-sm text-danger fw-bold" 
                                                   value="<?php echo ($row['GiaKhuyenMai'] > 0) ? $row['GiaKhuyenMai'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label>Tổng tồn kho</label>
                                <input type="number" name="kho" class="form-control" value="<?php echo $row['SoLuongTonKho']; ?>" <?php echo $hasVariants ? 'readonly title="Tự động tính từ biến thể"' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Ảnh đại diện</div>
                        <div class="card-body text-center">
                            <div class="mb-2 border rounded p-1 bg-white" style="height: 180px; display: flex; align-items: center; justify-content: center;">
                                <?php if($anhHienTai): ?>
                                    <img id="imgPreview" src="../../uploads/<?php echo $anhHienTai; ?>" style="max-height: 100%; max-width: 100%;">
                                <?php else: ?>
                                    <img id="imgPreview" src="" style="max-height: 100%; max-width: 100%; display: none;">
                                    <span id="noImgText" class="text-muted">Chưa có ảnh</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="anh_moi" class="form-control form-control-sm" onchange="previewImage(this)">
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <script>
        CKEDITOR.replace('editor1', { height: 250 });

        // --- 1. LOGIC ẨN HIỆN KHUYẾN MÃI ---
        const saleSwitch = document.getElementById('saleSwitch');
        const boxKhuyenMai = document.getElementById('box_khuyenmai');
        function toggleSale() {
            boxKhuyenMai.style.display = saleSwitch.checked ? 'block' : 'none';
        }
        saleSwitch.addEventListener('change', toggleSale);
        toggleSale(); 

        // --- 2. LOGIC TÍNH GIÁ ---
        const giaGoc = document.getElementById('gia_goc');
        const phanTram = document.getElementById('phantram_giam');
        const giaKm = document.getElementById('gia_km');

        function calcPercent() {
            let g = parseFloat(giaGoc.value) || 0;
            let k = parseFloat(giaKm.value) || 0;
            if(g > 0 && k > 0 && k < g) {
                phanTram.value = Math.round(((g - k) / g * 100) * 10) / 10;
            }
        }
        if(saleSwitch.checked) calcPercent(); 

        giaGoc.addEventListener('input', function() {
            let g = parseFloat(this.value) || 0;
            let p = parseFloat(phanTram.value) || 0;
            if(g > 0 && p > 0) giaKm.value = Math.round(g - (g * p / 100));
        });

        phanTram.addEventListener('input', function() {
            let g = parseFloat(giaGoc.value) || 0;
            let p = parseFloat(this.value) || 0;
            if(p > 100) p = 100;
            if(g > 0) giaKm.value = Math.round(g - (g * p / 100));
        });

        giaKm.addEventListener('input', function() {
            let g = parseFloat(giaGoc.value) || 0;
            let k = parseFloat(this.value) || 0;
            if(g > 0 && k < g) {
                phanTram.value = Math.round(((g - k) / g * 100) * 10) / 10;
            }
        });

        // --- 3. XEM TRƯỚC ẢNH ---
        function previewImage(input) {
            const img = document.getElementById('imgPreview');
            const txt = document.getElementById('noImgText');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    if(txt) txt.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>