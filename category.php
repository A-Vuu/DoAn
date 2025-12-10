<?php
session_start();
require_once 'config.php';
include 'includes/header.php';

// 1. NHẬN ID DANH MỤC TỪ URL
$catId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Các biến mặc định
$categoryName = "Tất cả sản phẩm";
$categoryDesc = "Khám phá bộ sưu tập đầy đủ của NOVAWEAR";
$whereClause = "WHERE s.HienThi = 1"; // Mặc định lấy hết

// 2. XỬ LÝ LOGIC KHI CÓ ID
if ($catId > 0) {
    // Bước A: Lấy thông tin danh mục hiện tại (để hiển thị tiêu đề)
    $sqlCat = "SELECT TenDanhMuc, MoTa FROM DanhMucSanPham WHERE Id = $catId";
    $resCat = mysqli_query($conn, $sqlCat);
    
    if ($rowCat = mysqli_fetch_assoc($resCat)) {
        $categoryName = $rowCat['TenDanhMuc'];
        $categoryDesc = !empty($rowCat['MoTa']) ? $rowCat['MoTa'] : "Các sản phẩm thuộc danh mục " . $categoryName;
    }

    // =================================================================
    // LOGIC MỚI: TÌM DANH MỤC CON ĐỂ LẤY SẢN PHẨM CỦA CẢ CHA LẪN CON
    // =================================================================
    
    // 1. Tạo mảng chứa ID cần lấy (Ban đầu chỉ chứa chính nó)
    $listCatIds = [$catId];

    // 2. Tìm các danh mục con (có IdDanhMucCha = ID hiện tại)
    $sqlChildren = "SELECT Id FROM DanhMucSanPham WHERE IdDanhMucCha = $catId";
    $resChildren = mysqli_query($conn, $sqlChildren);

    while ($child = mysqli_fetch_assoc($resChildren)) {
        $listCatIds[] = $child['Id']; // Thêm ID con vào danh sách
    }

    // 3. Chuyển mảng ID thành chuỗi (ví dụ: "1,5,6")
    $listIdsString = implode(',', $listCatIds);

    // Bước B: Sửa điều kiện lọc
    // Thay vì dùng dấu = (chỉ lấy 1), ta dùng IN (lấy danh sách)
    $whereClause .= " AND s.IdDanhMuc IN ($listIdsString)";
}

// 3. THIẾT LẬP BIẾN CHO PARTIAL
$sectionTitle = $categoryName; 
$sectionDesc  = $categoryDesc;

// 4. CẤU HÌNH LOAD MORE VÀ TRUY VẤN
$enableLoadMore = true;
$pageType = 'category';
$pageId = $catId; // Truyền ID danh mục cho API Load More

// Query chính thức (Kết hợp với $whereClause đã xử lý ở trên)
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
    
</div>

<?php include 'includes/footer.php'; ?> 