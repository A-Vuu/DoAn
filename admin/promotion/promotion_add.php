<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit;
}

$isEdit = false;
$promo = [
    'Id' => '',
    'MaCode' => '',
    'TenChuongTrinh' => '',
    'LoaiGiam' => 'TienMat',
    'GiaTriGiam' => '',
    'GiamToiDa' => '',
    'GiaTriDonHangToiThieu' => '',
    'SoLuongMa' => '',
    'NgayBatDau' => '',
    'NgayKetThuc' => '',
    'HienThi' => 1
];
$errors = [];

// Nếu edit
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM MaGiamGia WHERE Id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $promo = $result->fetch_assoc();
        $isEdit = true;
    }
    $stmt->close();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maCode = trim($_POST['ma_code'] ?? '');
    $tenChuongTrinh = trim($_POST['ten_chuong_trinh'] ?? '');
    $loaiGiam = trim($_POST['loai_giam'] ?? '');
    $giaTriGiam = (float)($_POST['gia_tri_giam'] ?? 0);
    $giamToiDa = (float)($_POST['giam_toi_da'] ?? 0);
    $giaTriDonHangToiThieu = (float)($_POST['gia_tri_don_hang'] ?? 0);
    $soLuongMa = (int)($_POST['so_luong_ma'] ?? 0);
    $ngayBatDau = !empty($_POST['ngay_bat_dau']) ? $_POST['ngay_bat_dau'] : null;
    $ngayKetThuc = !empty($_POST['ngay_ket_thuc']) ? $_POST['ngay_ket_thuc'] : null;
    $hienThi = isset($_POST['hien_thi']) ? 1 : 0;

    // Validate
    if ($maCode === '') $errors[] = 'Mã code không được để trống';
    if ($tenChuongTrinh === '') $errors[] = 'Tên chương trình không được để trống';
    if ($giaTriGiam <= 0) $errors[] = 'Giá trị giảm phải > 0';

    // Kiểm tra mã trùng (khi thêm mới)
    if (!$isEdit && !empty($maCode)) {
        $checkStmt = $conn->prepare("SELECT Id FROM MaGiamGia WHERE MaCode = ?");
        if ($checkStmt) {
            $checkStmt->bind_param('s', $maCode);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = 'Mã code này đã tồn tại';
            }
            $checkStmt->close();
        }
    }

    if (empty($errors)) {
        if ($isEdit) {
            $id = $promo['Id'];
            $stmt = $conn->prepare(
                "UPDATE MaGiamGia 
                SET TenChuongTrinh=?, LoaiGiam=?, GiaTriGiam=?, GiamToiDa=?, 
                    GiaTriDonHangToiThieu=?, NgayBatDau=?, NgayKetThuc=?, HienThi=?
                WHERE Id=?"
            );
            $stmt->bind_param(
                'ssdddssii',
                $tenChuongTrinh, $loaiGiam, $giaTriGiam, $giamToiDa, $giaTriDonHangToiThieu, 
                $ngayBatDau, $ngayKetThuc, $hienThi, $id
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO MaGiamGia 
                (MaCode, TenChuongTrinh, LoaiGiam, GiaTriGiam, GiamToiDa, GiaTriDonHangToiThieu, SoLuongMa, NgayBatDau, NgayKetThuc, HienThi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'sssdddissi',
                $maCode, $tenChuongTrinh, $loaiGiam, $giaTriGiam, $giamToiDa, $giaTriDonHangToiThieu, 
                $soLuongMa, $ngayBatDau, $ngayKetThuc, $hienThi
            );
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = $isEdit ? 'Cập nhật thành công!' : 'Tạo mã giảm giá thành công!';
            $stmt->close();
            header("Location: promotion.php");
            exit;
        } else {
            $errors[] = 'Lỗi: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Sửa' : 'Thêm'; ?> Mã Giảm Giá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="sidebar">
    <h4 class="text-center mb-4">NovaWear Admin</h4>
    <nav>
        <a href="promotion.php"><i class="fas fa-arrow-left"></i> Quay lại</a>
    </nav>
</div>

<div class="main-content">
    <div class="container">
        <h2 class="mb-4"><?php echo $isEdit ? 'Sửa Mã Giảm Giá' : 'Tạo Mã Giảm Giá Mới'; ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã Code *</label>
                            <input type="text" name="ma_code" class="form-control" value="<?php echo htmlspecialchars($promo['MaCode']); ?>" 
                                   <?php echo $isEdit ? 'readonly' : ''; ?> placeholder="VD: SUMMER2024" required>
                            <small class="text-muted">Mã độc nhất, không thể sửa nếu đã tạo</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tên Chương Trình *</label>
                            <input type="text" name="ten_chuong_trinh" class="form-control" value="<?php echo htmlspecialchars($promo['TenChuongTrinh']); ?>" 
                                   placeholder="VD: Giảm 20% Hè 2024" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Loại Giảm *</label>
                            <select name="loai_giam" class="form-select" required>
                                <option value="TienMat" <?php echo ($promo['LoaiGiam'] === 'TienMat') ? 'selected' : ''; ?>>Tiền mặt (số tiền)</option>
                                <option value="PhanTram" <?php echo ($promo['LoaiGiam'] === 'PhanTram') ? 'selected' : ''; ?>>Phần trăm (%)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Giá Trị Giảm *</label>
                            <input type="number" name="gia_tri_giam" class="form-control" value="<?php echo $promo['GiaTriGiam']; ?>" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Giảm Tối Đa</label>
                            <input type="number" name="giam_toi_da" class="form-control" value="<?php echo $promo['GiamToiDa']; ?>" 
                                   step="0.01" min="0">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Đơn Hàng Tối Thiểu</label>
                            <input type="number" name="gia_tri_don_hang" class="form-control" value="<?php echo $promo['GiaTriDonHangToiThieu']; ?>" 
                                   step="1" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Số Lượng Mã</label>
                            <input type="number" name="so_luong_ma" class="form-control" value="<?php echo $promo['SoLuongMa']; ?>" 
                                   step="1" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hiển Thị</label>
                            <div class="form-check">
                                <input type="checkbox" name="hien_thi" class="form-check-input" value="1" 
                                       <?php echo $promo['HienThi'] ? 'checked' : ''; ?> id="hienThi">
                                <label class="form-check-label" for="hienThi">Bật hiển thị</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày Bắt Đầu</label>
                            <input type="datetime-local" name="ngay_bat_dau" class="form-control" 
                                   value="<?php echo $promo['NgayBatDau'] ? date('Y-m-d\TH:i', strtotime($promo['NgayBatDau'])) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày Kết Thúc</label>
                            <input type="datetime-local" name="ngay_ket_thuc" class="form-control" 
                                   value="<?php echo $promo['NgayKetThuc'] ? date('Y-m-d\TH:i', strtotime($promo['NgayKetThuc'])) : ''; ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Cập Nhật' : 'Tạo Mới'; ?>
                        </button>
                        <a href="promotion.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Hủy
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
