<?php
session_start();
require_once '../config.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

// 2. Xử lý thêm mới (Giữ nguyên)
$msg = "";
$error = "";

if (isset($_POST['add_category'])) {
    $ten = $_POST['ten_danhmuc'];
    $thutu = $_POST['thutu'];
    $mota = $_POST['mota'];
    $cha = ($_POST['danhmuc_cha'] == 0) ? "NULL" : $_POST['danhmuc_cha'];

    $sql = "INSERT INTO DanhMucSanPham (TenDanhMuc, IdDanhMucCha, ThuTuHienThi, MoTa)
            VALUES ('$ten', $cha, '$thutu', '$mota')";
   
    if (mysqli_query($conn, $sql)) {
        $msg = "Thêm danh mục thành công!";
    } else {
        $error = "Lỗi: " . mysqli_error($conn);
    }
}

// 3. Xử lý xóa (Giữ nguyên)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM DanhMucSanPham WHERE Id = $id");
    
    // Xóa xong thì quay lại trang danh sách (giữ nguyên trạng thái parent nếu có thì tốt hơn, nhưng để đơn giản ta về trang gốc)
    header("Location: category.php");
    exit();
}

// =================================================================
// 4. Lấy dữ liệu (PHẦN SỬA ĐỔI QUAN TRỌNG)
// =================================================================

// Kiểm tra xem người dùng đang muốn xem danh mục con của ai
$parentId = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
$parentInfo = null;

if ($parentId == 0) {
    // TRƯỜNG HỢP 1: Lấy danh mục GỐC (Cha)
    // Chỉ lấy những dòng mà IdDanhMucCha là NULL hoặc = 0
    $sqlList = "SELECT * FROM DanhMucSanPham 
                WHERE IdDanhMucCha IS NULL OR IdDanhMucCha = 0 
                ORDER BY ThuTuHienThi ASC";
} else {
    // TRƯỜNG HỢP 2: Lấy danh mục CON
    // Lấy thông tin của cha để hiển thị tiêu đề
    $rsParent = mysqli_query($conn, "SELECT TenDanhMuc FROM DanhMucSanPham WHERE Id = $parentId");
    $parentInfo = mysqli_fetch_assoc($rsParent);

    // Lấy danh sách con
    $sqlList = "SELECT * FROM DanhMucSanPham 
                WHERE IdDanhMucCha = $parentId 
                ORDER BY ThuTuHienThi ASC";
}

$categories = mysqli_query($conn, $sqlList);

// Lấy danh sách cho Dropdown thêm mới (Vẫn cần lấy tất cả danh mục cha)
$catDropdown = mysqli_query($conn, "SELECT * FROM DanhMucSanPham WHERE IdDanhMucCha IS NULL OR IdDanhMucCha = 0");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý danh mục</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3">
             <img> <?php echo isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin'; ?>
        </div>
        <hr style="border-color: #4f5962;">
        <nav>
            <a href="category.php" class="active">Danh mục sản phẩm</a>
            <a href="product/product.php" >Quản lý sản phẩm</a>
            <a href="news/news.php">Tin tức</a>
            <a href="banner/banner.php">Quảng cáo</a>
            <a href="logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <h3 class="mb-4">Quản lý danh mục sản phẩm</h3>

        <?php if($msg != "") { echo "<div class='alert alert-success'>$msg</div>"; } ?>
        <?php if($error != "") { echo "<div class='alert alert-danger'>$error</div>"; } ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white"><strong>Thêm danh mục mới</strong></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                                <input type="text" name="ten_danhmuc" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Danh mục cha</label>
                                <select name="danhmuc_cha" class="form-select">
                                    <option value="0">-- Là danh mục gốc --</option>
                                    <?php 
                                    // Reset pointer vì đã dùng ở trên nếu cần, hoặc query lại.
                                    // Ở đây biến $catDropdown độc lập nên dùng thoải mái
                                    if(mysqli_num_rows($catDropdown) > 0){
                                        mysqli_data_seek($catDropdown, 0); // Reset về đầu
                                        while($c = mysqli_fetch_assoc($catDropdown)) { 
                                            // Tự động chọn danh mục cha nếu đang ở trang chi tiết
                                            $selected = ($parentId == $c['Id']) ? 'selected' : '';
                                    ?>
                                            <option value="<?php echo $c['Id']; ?>" <?php echo $selected; ?>>
                                                <?php echo $c['TenDanhMuc']; ?>
                                            </option>
                                    <?php 
                                        } 
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" name="thutu" class="form-control" value="0">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea name="mota" class="form-control" rows="2"></textarea>
                            </div>

                            <button type="submit" name="add_category" class="btn btn-success w-100">Thêm mới</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($parentId == 0): ?>
                                <strong>Danh sách Danh mục gốc</strong>
                            <?php else: ?>
                                <strong>Danh mục con của: <span class="text-primary"><?php echo $parentInfo['TenDanhMuc']; ?></span></strong>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($parentId != 0): ?>
                            <a href="category.php" class="btn btn-sm btn-secondary">
                                <i class="fa-solid fa-arrow-left"></i> Quay lại
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">STT</th>
                                    <th>Tên danh mục</th>
                                    <th class="text-center">Thứ tự</th>
                                    <th class="text-center">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stt = 1;
                                if (mysqli_num_rows($categories) > 0) {
                                    while($row = mysqli_fetch_assoc($categories)) {
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $stt++; ?></td>
                                    <td>
                                        <strong><?php echo $row['TenDanhMuc']; ?></strong><br>
                                        <small class="text-muted"><?php echo $row['MoTa']; ?></small>
                                    </td>
                                    
                                    <td class="text-center"><?php echo $row['ThuTuHienThi']; ?></td>
                                    
                                    <td class="text-center">
                                        
                                        <?php if ($parentId == 0): ?>
                                            <a href="category.php?parent_id=<?php echo $row['Id']; ?>" 
                                               class="btn btn-sm btn-info text-white me-1">
                                                <i class="fa-solid fa-list"></i> Chi tiết
                                            </a>
                                        <?php endif; ?>

                                        <a href="category.php?delete=<?php echo $row['Id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Xóa danh mục này?');">
                                            <i class="fa-solid fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                                <?php 
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='text-center py-3 text-muted'>Chưa có danh mục nào</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>