<?php
session_start();
require_once 'config.php';
include 'includes/header.php';

// 1. NHẬN ID DANH MỤC TỪ URL
// Kiểm tra xem trên thanh địa chỉ có ?id=... không
$catId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Các biến mặc định (nếu không có ID hoặc ID = 0)
$categoryName = "Tất cả sản phẩm";
$categoryDesc = "Khám phá bộ sưu tập đầy đủ của NOVAWEAR";
$whereClause = "WHERE s.HienThi = 1"; // Mặc định lấy hết

// 2. XỬ LÝ LOGIC KHI CÓ ID
if ($catId > 0) {
    // Bước A: Lấy tên danh mục từ database để sửa lại Tiêu Đề
    $sqlCat = "SELECT TenDanhMuc, MoTa FROM DanhMucSanPham WHERE Id = $catId";
    $resCat = mysqli_query($conn, $sqlCat);
    
    if ($rowCat = mysqli_fetch_assoc($resCat)) {
        $categoryName = $rowCat['TenDanhMuc']; // Ví dụ: "Áo thun"
        // Nếu danh mục có mô tả thì lấy, không thì để trống
        $categoryDesc = !empty($rowCat['MoTa']) ? $rowCat['MoTa'] : "Các sản phẩm thuộc danh mục " . $categoryName;
    }

    // Bước B: Tạo điều kiện lọc cho câu lệnh SQL lấy sản phẩm
    // Lọc theo IdDanhMuc
    $whereClause .= " AND s.IdDanhMuc = $catId";
}

// 3. THIẾT LẬP BIẾN ĐỂ FILE PARTIAL HIỂN THỊ
// Biến này sẽ được file 'includes/product_list_partial.php' sử dụng để in ra màn hình
$sectionTitle = $categoryName; 
$sectionDesc  = $categoryDesc;

// 4. TRUY VẤN SẢN PHẨM
// Cấu hình Load More
$enableLoadMore = true;
$pageType = 'category';
$pageId = $catId; // Truyền ID danh mục cho API

// Query ban đầu (Thêm LIMIT 12)
$sqlQuery = "SELECT s.*, a.DuongDanAnh 
             FROM SanPham s 
             LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
             $whereClause 
             ORDER BY s.Id DESC LIMIT 12"; // <-- Quan trọng

?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-dark">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $categoryName; ?></li>
        </ol>
    </nav>

    <?php include 'includes/product_list_partial.php'; ?>
    
</div>

<?php include 'includes/footer.php'; ?>