<?php
session_start();
require_once '../../config.php';


function log_product_action($conn, $action, $productId, $content) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($stmt = $conn->prepare(
        "INSERT INTO lichsuhoatdong
        (IdNguoiDung, IdAdmin, LoaiNguoiThucHien, HanhDong, BangDuLieu, IdBanGhi, NoiDung, DiaChiIP)
        VALUES (?, ?, 'admin', ?, 'SanPham', ?, ?, ?)"
    )) {
        $nullUser = null;
        $stmt->bind_param(
            'ississ',
            $nullUser,
            $adminId,
            $action,
            $productId,
            $content,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}


// Lấy dữ liệu cho các ô chọn
$categories = mysqli_query($conn, "SELECT * FROM DanhMucSanPham"); 
$colors = mysqli_query($conn, "SELECT * FROM MauSac"); 
$sizes = mysqli_query($conn, "SELECT * FROM KichThuoc ORDER BY ThuTuSapXep ASC"); 

if (isset($_POST['submit'])) {
    // 1. XỬ LÝ DỮ LIỆU CƠ BẢN
    $tenSP = mysqli_real_escape_string($conn, $_POST['ten_sp']);
    $gia = (int)$_POST['gia'];
    
    // Logic Khuyến mãi
    $isSale = isset($_POST['khuyenmai']); 
    $giaKM = ($isSale && !empty($_POST['gia_km']) && $_POST['gia_km'] > 0) ? (int)$_POST['gia_km'] : 'NULL'; 
    
    $danhmuc = mysqli_real_escape_string($conn, $_POST['danhmuc']);
    $mota = mysqli_real_escape_string($conn, $_POST['mota']); 
    $noidungChiTiet = mysqli_real_escape_string($conn, $_POST['noidung_chitiet']);
    
    $tonkho = (int)$_POST['kho']; 
    $hienthi = isset($_POST['hienthi']) ? 1 : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    $sanphammoi = isset($_POST['sanphammoi']) ? 1 : 0;

    // Tự tạo SKU nếu bỏ trống
    $sku = mysqli_real_escape_string($conn, $_POST['sku']);
    if(empty($sku)) $sku = "SP" . date("ymdHi") . rand(10, 99);

    // 2. INSERT SẢN PHẨM CHA
    $sqlInsert = "INSERT INTO SanPham (MaSanPham, TenSanPham, GiaGoc, GiaKhuyenMai, IdDanhMuc, MoTaNgan, MoTaChiTiet, SoLuongTonKho, HienThi, SanPhamNoiBat, SanPhamMoi) 
                  VALUES ('$sku', '$tenSP', '$gia', $giaKM, '$danhmuc', '$mota', '$noidungChiTiet', '$tonkho', '$hienthi', '$noibat', '$sanphammoi')";
    
    if (mysqli_query($conn, $sqlInsert)) {
        $idSPMoi = mysqli_insert_id($conn);
        log_product_action(
            $conn,
            'Create',
            $idSPMoi,
            'Thêm sản phẩm mới: ' . $tenSP
        );


        // 3. UPLOAD ẢNH ĐẠI DIỆN CHÍNH
        if (isset($_FILES['anh_sp']) && $_FILES['anh_sp']['name'] != "") {
            $fileName = time() . "_main_" . basename($_FILES["anh_sp"]["name"]);
            if (!file_exists("../../uploads/")) mkdir("../../uploads/", 0777, true);
            move_uploaded_file($_FILES["anh_sp"]["tmp_name"], "../../uploads/" . $fileName);
            mysqli_query($conn, "INSERT INTO AnhSanPham (IdSanPham, DuongDanAnh, LaAnhChinh) VALUES ('$idSPMoi', '$fileName', 1)");
        }

       // 4. LƯU BIẾN THỂ (SIZE & MÀU & ẢNH BIẾN THỂ)
        if (isset($_POST['size']) && isset($_POST['color'])) {
            
            // A. TÍNH TOÁN SỐ LƯỢNG CHIA ĐỀU
            $countColor = count($_POST['color']);
            $countSize = count($_POST['size']);
            $totalVariants = $countColor * $countSize; // Tổng số dòng biến thể (VD: 9 dòng)
            $totalStockInput = (int)$_POST['kho'];     // Tổng kho nhập vào (VD: 500)

            $baseQty = 0;
            $remainder = 0;

            if ($totalVariants > 0 && $totalStockInput > 0) {
                $baseQty = floor($totalStockInput / $totalVariants); // Số lượng cơ bản (500/9 = 55)
                $remainder = $totalStockInput % $totalVariants;      // Số dư cần chia thêm (500%9 = 5)
            }

            $counter = 0; // Biến đếm để rải số dư
            $realTotalQty = 0; // Tính lại tổng thực tế để update bảng cha
            
            // B. DUYỆT VÒNG LẶP ĐỂ LƯU
            foreach ($_POST['color'] as $mauId) {
                
                // --- XỬ LÝ ẢNH RIÊNG CHO MÀU NÀY ---
                $anhBienThe = 'NULL'; 
                $inputName = "img_color_" . $mauId; 

                if (isset($_FILES[$inputName]) && $_FILES[$inputName]['name'] != "") {
                    $variantFileName = time() . "_var_" . $mauId . "_" . basename($_FILES[$inputName]["name"]);
                    if (move_uploaded_file($_FILES[$inputName]["tmp_name"], "../../uploads/" . $variantFileName)) {
                        $anhBienThe = "'$variantFileName'"; 
                    }
                }

                // Duyệt từng size
                foreach ($_POST['size'] as $kichthuocId) {
                    $skuVariant = $sku . "-M" . $mauId . "-S" . $kichthuocId;
                    
                    // --- LOGIC CHIA KHO THÔNG MINH ---
                    $qtyThisVariant = $baseQty;
                    if ($counter < $remainder) {
                        $qtyThisVariant += 1; // Cộng thêm 1 cho các biến thể đầu tiên cho đến khi hết số dư
                    }
                    $counter++;
                    // ---------------------------------

                    $sqlVar = "INSERT INTO ChiTietSanPham (IdSanPham, IdMauSac, IdKichThuoc, SKU, SoLuong, AnhBienThe) 
                               VALUES ('$idSPMoi', '$mauId', '$kichthuocId', '$skuVariant', '$qtyThisVariant', $anhBienThe)";
                    
                    mysqli_query($conn, $sqlVar);
                    $realTotalQty += $qtyThisVariant;
                }
            }
            
            // Cập nhật tổng tồn kho cho sản phẩm cha (Chắc chắn bằng 500)
            if ($realTotalQty > 0) mysqli_query($conn, "UPDATE SanPham SET SoLuongTonKho = $realTotalQty WHERE Id=$idSPMoi");
        }   
        echo "<script>alert('Đã thêm sản phẩm và biến thể thành công!'); window.location='product.php';</script>";
    } else {
        $err = mysqli_error($conn);
        echo "<script>alert('LỖI DATABASE: $err');</script>"; 
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm sản phẩm mới</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>

    <style>
        /* --- CSS TÙY CHỈNH CHO FORM GỌN GÀNG --- */
        body { background-color: #f4f6f9; font-size: 14px; } /* Chữ nhỏ vừa phải */
        
        .card { 
            border: none; 
            box-shadow: 0 0 10px rgba(0,0,0,0.05); 
            margin-bottom: 15px; 
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #0d6efd; /* Đường kẻ màu xanh tạo điểm nhấn */
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 10px 15px;
            color: #333;
        }
        
        /* Thu nhỏ khoảng cách giữa các ô input */
        .mb-3 { margin-bottom: 10px !important; }
        
        /* Input nhỏ gọn hơn (chiều cao 34px thay vì mặc định to đùng) */
        .form-control, .form-select {
            font-size: 13px;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
        }
        
        label { font-weight: 600; font-size: 13px; margin-bottom: 3px; color: #555; }
        
        /* Style cho ô màu sắc */
        .color-checkbox { display: none; }
        .color-label {
            width: 30px; height: 30px; 
            border-radius: 4px; 
            border: 2px solid #eee; 
            cursor: pointer; 
            position: relative;
            transition: all 0.2s;
        }
        .color-checkbox:checked + .color-label {
            border: 2px solid #0d6efd;
            transform: scale(1.1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .color-checkbox:checked + .color-label::after {
            content: '\f00c'; /* Icon dấu tích */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            color: #fff;
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            text-shadow: 0 0 2px #000;
        }

        /* Style cho ô Size */
        .size-checkbox { display: none; }
        .size-label {
            display: inline-block;
            padding: 4px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            background: #fff;
            min-width: 40px; text-align: center;
        }
        .size-checkbox:checked + .size-label {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
    </style>
</head>
<body>

    <div class="container-fluid py-3">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="m-0 fw-bold text-primary"><i class="fas fa-plus-circle"></i> Thêm Sản Phẩm Mới</h4>
                <div>
                    <a href="product.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Quay lại</a>
                    <button type="submit" name="submit" class="btn btn-success btn-sm fw-bold px-4"><i class="fas fa-save"></i> LƯU SẢN PHẨM</button>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    
                    <div class="card">
                        <div class="card-header">Thông tin cơ bản</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label>Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" name="ten_sp" class="form-control" placeholder="Nhập tên sản phẩm..." required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Mã SKU</label>
                                    <input type="text" name="sku" class="form-control" placeholder="Để trống tự sinh mã">
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
                                <textarea name="mota" class="form-control" rows="2" placeholder="Hiển thị ở danh sách sản phẩm..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Phân loại hàng (Màu & Size)</span>
                            <small class="text-muted fw-normal">Chọn ít nhất 1 nếu có</small>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 border-end">
                                    <label class="d-block mb-2">Kích thước (Size):</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php while($s = mysqli_fetch_assoc($sizes)): ?>
                                            <div>
                                                <input type="checkbox" name="size[]" value="<?php echo $s['Id']; ?>" id="size_<?php echo $s['Id']; ?>" class="size-checkbox">
                                                <label for="size_<?php echo $s['Id']; ?>" class="size-label"><?php echo $s['TenKichThuoc']; ?></label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 ps-md-4">
    <label class="d-block mb-2">Màu sắc & Ảnh biến thể:</label>
    <div class="d-flex flex-wrap gap-3">
        <?php 
        // Reset lại con trỏ dữ liệu màu sắc để dùng lại
        mysqli_data_seek($colors, 0);
        while($m = mysqli_fetch_assoc($colors)): 
        ?>
            <div class="text-center p-2 border rounded bg-white" style="width: 100px;">
                <div class="mb-2">
                    <input type="checkbox" name="color[]" value="<?php echo $m['Id']; ?>" 
                           id="color_<?php echo $m['Id']; ?>" 
                           class="color-checkbox" 
                           onchange="toggleImageInput(<?php echo $m['Id']; ?>)">
                    
                    <label for="color_<?php echo $m['Id']; ?>" class="color-label" 
                           style="background-color: <?php echo $m['MaMau']; ?>; display:block; margin:0 auto;" 
                           title="<?php echo $m['TenMau']; ?>">
                    </label>
                    <div class="small fw-bold mt-1"><?php echo $m['TenMau']; ?></div>
                </div>

                <div id="box_img_<?php echo $m['Id']; ?>" style="display:none;">
                    <label class="btn btn-outline-secondary btn-sm p-0 w-100" style="font-size: 10px; height: 25px; line-height: 23px;">
                        <i class="fas fa-camera"></i> Chọn ảnh
                        <input type="file" name="img_color_<?php echo $m['Id']; ?>" hidden onchange="checkFile(this, <?php echo $m['Id']; ?>)">
                    </label>
                    <small id="fname_<?php echo $m['Id']; ?>" class="d-block text-truncate text-muted" style="font-size: 9px; max-width: 80px;">Chưa có ảnh</small>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>


                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Nội dung chi tiết</div>
                        <div class="card-body p-0">
                            <textarea name="noidung_chitiet" id="editor1"></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    
                    <div class="card">
                        <div class="card-header text-success"><i class="fas fa-tag"></i> Thiết lập Giá & Kho</div>
                        <div class="card-body bg-light">
                            <div class="mb-3">
                                <label>Giá bán gốc (VNĐ) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="gia" id="gia_goc" class="form-control fw-bold text-dark" required min="0" placeholder="0">
                                    <span class="input-group-text">đ</span>
                                </div>
                            </div>
                            
                            <div class="p-2 mb-3 bg-white border rounded">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="khuyenmai" id="saleSwitch">
                                    <label class="form-check-label text-danger fw-bold" for="saleSwitch">Bật Khuyến mãi (Sale)</label>
                                </div>
                                
                                <div id="box_khuyenmai" style="display: none;">
                                    <div class="row g-1">
                                        <div class="col-5">
                                            <small class="text-muted">Giảm %</small>
                                            <input type="number" id="phantram_giam" class="form-control form-control-sm text-danger" placeholder="%">
                                        </div>
                                        <div class="col-7">
                                            <small class="text-muted">Giá sau giảm</small>
                                            <input type="number" name="gia_km" id="gia_km" class="form-control form-control-sm text-danger fw-bold" placeholder="VNĐ">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label>Tổng tồn kho</label>
                                <input type="number" name="kho" class="form-control" value="100">
                                <small class="text-muted fst-italic" style="font-size: 11px;">*Nếu chọn biến thể, kho sẽ tự tính tổng.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Trạng thái hiển thị</div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="hienthi" id="sw_hienthi" checked>
                                <label class="form-check-label" for="sw_hienthi">Hiển thị lên Web</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="sanphammoi" id="sw_new" checked>
                                <label class="form-check-label text-primary" for="sw_new">Đánh dấu Mới (New)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="noibat" id="sw_hot">
                                <label class="form-check-label text-warning fw-bold" for="sw_hot">Đánh dấu Nổi bật (Hot)</label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">Ảnh đại diện</div>
                        <div class="card-body text-center">
                            <input type="file" name="anh_sp" class="form-control form-control-sm mb-2" onchange="previewImage(this)">
                            <div id="imagePreview" style="height: 150px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; color: #999;">
                                <span>Chưa chọn ảnh</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>

    <script>
        // Cấu hình CKEditor gọn gàng hơn
        CKEDITOR.replace('editor1', {
            height: 250, // Chiều cao cố định
            toolbarGroups: [
                { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                { name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align' ] },
                { name: 'styles' },
                { name: 'colors' },
                { name: 'insert' }
            ]
        });

        // --- ẨN HIỆN BOX KHUYẾN MÃI ---
        const saleSwitch = document.getElementById('saleSwitch');
        const boxKhuyenMai = document.getElementById('box_khuyenmai');
        function toggleSale() {
            boxKhuyenMai.style.display = saleSwitch.checked ? 'block' : 'none';
        }
        saleSwitch.addEventListener('change', toggleSale);
        toggleSale(); // Chạy lúc load

        // --- TÍNH GIÁ TỰ ĐỘNG ---
        const giaGoc = document.getElementById('gia_goc');
        const phanTram = document.getElementById('phantram_giam');
        const giaKm = document.getElementById('gia_km');

        giaGoc.addEventListener('input', updatePrice);
        phanTram.addEventListener('input', updatePrice);
        
        function updatePrice() {
            let g = parseFloat(giaGoc.value) || 0;
            let p = parseFloat(phanTram.value) || 0;
            if(g > 0 && p > 0) {
                if(p > 100) p = 100;
                giaKm.value = Math.round(g - (g * p / 100));
            }
        }

        giaKm.addEventListener('input', function() {
            let g = parseFloat(giaGoc.value) || 0;
            let k = parseFloat(this.value) || 0;
            if(g > 0 && k < g) {
                phanTram.value = Math.round(((g - k) / g * 100) * 10) / 10;
            }
        });

        // --- XEM TRƯỚC ẢNH ---
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" style="max-height: 100%; max-width: 100%;">`;
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '<span>Chưa chọn ảnh</span>';
            }
        }
    </script>
    <script>
function toggleImageInput(id) {
    const checkbox = document.getElementById('color_' + id);
    const box = document.getElementById('box_img_' + id);
    box.style.display = checkbox.checked ? 'block' : 'none';
}

function checkFile(input, id) {
    if(input.files.length > 0) {
        document.getElementById('fname_' + id).innerText = 'Đã chọn';
        document.getElementById('fname_' + id).classList.add('text-success');
    }
}
</script>
</body>
</html>