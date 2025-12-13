<?php
session_start();
require_once 'config.php';
include 'includes/header.php';

// 1. NHẬN ID DANH MỤC HOẶC LOẠI SẢN PHẨM TỪ URL
$catId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type  = isset($_GET['type']) ? $_GET['type'] : ''; // Thêm dòng này

// Các biến mặc định
$categoryName = "Tất cả sản phẩm";
$categoryDesc = "Khám phá bộ sưu tập đầy đủ của NOVAWEAR";
$whereClause = "WHERE s.HienThi = 1"; // Mặc định lấy hết

// 2. XỬ LÝ LOGIC

if ($catId > 0) {
    // --- TRƯỜNG HỢP 1: LỌC THEO DANH MỤC (Logic cũ giữ nguyên) ---
    $sqlCat = "SELECT TenDanhMuc, MoTa FROM DanhMucSanPham WHERE Id = $catId";
    $resCat = mysqli_query($conn, $sqlCat);
    
    if ($rowCat = mysqli_fetch_assoc($resCat)) {
        $categoryName = $rowCat['TenDanhMuc'];
        $categoryDesc = !empty($rowCat['MoTa']) ? $rowCat['MoTa'] : "Các sản phẩm thuộc danh mục " . $categoryName;
    }

    // Logic lấy danh mục con
    $listCatIds = [$catId];
    $sqlChildren = "SELECT Id FROM DanhMucSanPham WHERE IdDanhMucCha = $catId";
    $resChildren = mysqli_query($conn, $sqlChildren);
    while ($child = mysqli_fetch_assoc($resChildren)) {
        $listCatIds[] = $child['Id'];
    }
    $listIdsString = implode(',', $listCatIds);
    $whereClause .= " AND s.IdDanhMuc IN ($listIdsString)";

} elseif ($type != '') {
    // --- TRƯỜNG HỢP 2: LỌC THEO LOẠI (Nổi bật, Sale, Mới) ---
    switch ($type) {
        case 'hot':
            $categoryName = "SẢN PHẨM NỔI BẬT";
            $categoryDesc = "Những sản phẩm được yêu thích nhất";
            $whereClause .= " AND s.SanPhamNoiBat = 1";
            break;
            
        case 'sale':
            $categoryName = "SẢN PHẨM ĐANG SALE";
            $categoryDesc = "Săn deal hời - Giá tốt nhất hôm nay";
            // Logic lọc sản phẩm có giá khuyến mãi hợp lệ
            $whereClause .= " AND s.GiaKhuyenMai > 0 AND s.GiaKhuyenMai < s.GiaGoc";
            break;
            
        case 'new':
            $categoryName = "HÀNG MỚI VỀ";
            $categoryDesc = "Cập nhật xu hướng thời trang mới nhất";
            $whereClause .= " AND s.SanPhamMoi = 1";
            break;
    }
}

// 3. THIẾT LẬP BIẾN CHO PARTIAL
$sectionTitle = $categoryName; 
$sectionDesc  = $categoryDesc;

// 4. CẤU HÌNH LOAD MORE VÀ TRUY VẤN
$enableLoadMore = true;
$pageType = 'category'; 
// Lưu ý: Nếu bạn có file load_more_products.php, bạn cần sửa file đó để nhận diện $_POST['type']
// Tạm thời biến $catId dùng cho danh mục, ta có thể truyền thêm input hidden nếu cần.

// Query chính thức
$sqlQuery = "SELECT s.*, a.DuongDanAnh 
             FROM SanPham s 
             LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
             $whereClause 
             ORDER BY s.Id DESC LIMIT 12";
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-dark">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $categoryName; ?></li>
        </ol>
    </nav>

    <?php include 'includes/product_list_partial.php'; ?>
    
    <?php if($type != ''): ?>
        <input type="hidden" id="filterType" value="<?php echo htmlspecialchars($type); ?>">
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>