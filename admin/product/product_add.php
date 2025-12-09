<?php
session_start();
require_once '../../config.php';
if (!isset($_SESSION['admin_login'])) header("Location: ../login.php");

$categories = mysqli_query($conn, "SELECT * FROM DanhMucSanPham"); 
$colors = mysqli_query($conn, "SELECT * FROM MauSac"); 
$sizes = mysqli_query($conn, "SELECT * FROM KichThuoc ORDER BY ThuTuSapXep ASC"); 

if (isset($_POST['submit'])) {
    $tenSP = $_POST['ten_sp'];
    $gia = $_POST['gia'];
    $giaKM = !empty($_POST['gia_km']) ? $_POST['gia_km'] : 'NULL'; 
    $danhmuc = $_POST['danhmuc'];
    $mota = $_POST['mota']; 
    $tonkho = $_POST['kho']; 
    
    // --- LẤY TRẠNG THÁI TỪ CHECKBOX ---
    $hienthi = isset($_POST['hienthi']) ? 1 : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    
    // 1. Lấy trạng thái Sản phẩm Mới (Thêm dòng này)
    $sanphammoi = isset($_POST['sanphammoi']) ? 1 : 0;

    $sku = $_POST['sku'];
    if(empty($sku)) $sku = "SP" . date("YmdHis") . rand(10, 99);

    // 2. Sửa câu lệnh INSERT: Thêm cột SanPhamMoi và giá trị $sanphammoi
    $sqlInsert = "INSERT INTO SanPham (MaSanPham, TenSanPham, GiaGoc, GiaKhuyenMai, IdDanhMuc, MoTaNgan, SoLuongTonKho, HienThi, SanPhamNoiBat, SanPhamMoi) 
                  VALUES ('$sku', '$tenSP', '$gia', $giaKM, '$danhmuc', '$mota', '$tonkho', '$hienthi', '$noibat', '$sanphammoi')";
    
    try {
        if (mysqli_query($conn, $sqlInsert)) {
            $idSPMoi = mysqli_insert_id($conn);

            // Xử lý ảnh
            if (isset($_FILES['anh_sp']) && $_FILES['anh_sp']['name'] != "") {
                $fileName = time() . "_" . basename($_FILES["anh_sp"]["name"]);
                if (move_uploaded_file($_FILES["anh_sp"]["tmp_name"], "../../uploads/" . $fileName)) {
                    mysqli_query($conn, "INSERT INTO AnhSanPham (IdSanPham, DuongDanAnh, LaAnhChinh) VALUES ('$idSPMoi', '$fileName', 1)");
                }
            }

            // Xử lý biến thể (Size & Màu)
            if (isset($_POST['size']) && isset($_POST['color'])) {
                $qtyPerVariant = 10;
                $totalQty = 0;
                foreach ($_POST['color'] as $mau) {
                    foreach ($_POST['size'] as $kichthuoc) {
                        $skuVariant = $sku . "-M" . $mau . "-S" . $kichthuoc;
                        try {
                            mysqli_query($conn, "INSERT INTO ChiTietSanPham (IdSanPham, IdMauSac, IdKichThuoc, SKU, SoLuong) VALUES ('$idSPMoi', '$mau', '$kichthuoc', '$skuVariant', '$qtyPerVariant')");
                            $totalQty += $qtyPerVariant;
                        } catch (Exception $e) { continue; }
                    }
                }
                if ($totalQty > 0) mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = $totalQty WHERE Id=$idSPMoi");
            }
            echo "<script>alert('Thêm sản phẩm thành công!'); window.location='product.php';</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Lỗi: " . mysqli_error($conn) . "');</script>"; // Hiển thị lỗi rõ hơn
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Thêm sản phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>.color-option { width: 30px; height: 30px; display: inline-block; border: 1px solid #ddd; }</style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear</h4>
        <div class="px-3 mb-3">
            <img> <?php echo $_SESSION['admin_name']; ?>
        </div>
        <hr style="border-color: #4f5962;">
        <nav><a href="product.php">Quay lại danh sách</a></nav>
    </div>

    <div class="main-content">
        <form method="POST" enctype="multipart/form-data">
            <h3 class="mb-3">Thêm mới sản phẩm</h3>
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Thông tin chung</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label>Tên sản phẩm *</label>
                                <input type="text" name="ten_sp" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Mã SKU</label>
                                    <input type="text" name="sku" class="form-control" placeholder="Tự động">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Danh mục</label>
                                    <select name="danhmuc" class="form-select">
                                        <?php while($c = mysqli_fetch_assoc($categories)) echo "<option value='{$c['Id']}'>{$c['TenDanhMuc']}</option>"; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Mô tả ngắn</label>
                                <textarea name="mota" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-secondary text-white">Chọn Màu & Size (Tạo tự động)</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="d-block">Size:</label>
                                <?php while($s = mysqli_fetch_assoc($sizes)): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="size[]" value="<?php echo $s['Id']; ?>"> <?php echo $s['TenKichThuoc']; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="d-block">Màu:</label>
                                <div class="d-flex gap-2">
                                <?php while($m = mysqli_fetch_assoc($colors)): ?>
                                    <label>
                                        <input type="checkbox" name="color[]" value="<?php echo $m['Id']; ?>">
                                        <div class="color-option" style="background:<?php echo $m['MaMau']; ?>;" title="<?php echo $m['TenMau']; ?>"></div>
                                    </label>
                                <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header bg-white"><strong>Giá & Thiết lập</strong></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label>Giá gốc (VNĐ) *</label>
                                <input type="number" name="gia" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="text-danger">Giá khuyến mãi (VNĐ)</label>
                                <input type="number" name="gia_km" class="form-control" placeholder="Bỏ trống nếu không giảm">
                            </div>
                            <div class="mb-3">
                                <label>Tồn kho</label>
                                <input type="number" name="kho" class="form-control" value="100">
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="hienthi" id="hienthiSwitch" checked>
                                <label class="form-check-label" for="hienthiSwitch">Hiển thị sản phẩm</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="noibat" id="hotSwitch">
                                <label class="form-check-label fw-bold text-warning" for="hotSwitch">Sản phẩm Nổi bật (HOT)</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="sanphammoi" id="newSwitch" checked>
                                <label class="form-check-label fw-bold text-primary" for="newSwitch">Sản phẩm Mới (NEW)</label>
                            </div>

                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">Ảnh đại diện</div>
                        <div class="card-body"><input type="file" name="anh_sp" class="form-control"></div>
                    </div>
                    <button type="submit" name="submit" class="btn btn-success w-100 py-2">LƯU SẢN PHẨM</button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>