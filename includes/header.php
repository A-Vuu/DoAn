<?php
// =================================================================
// SỬA LỖI: Kiểm tra và gọi file config.php nếu biến $conn chưa có
// =================================================================
if (!isset($conn)) {
    // __DIR__ là đường dẫn tuyệt đối đến thư mục 'includes'
    // '/../config.php' có nghĩa là lùi lại 1 cấp để tìm file config
    if (file_exists(__DIR__ . '/../config.php')) {
        require_once __DIR__ . '/../config.php';
    } else {
        die("Lỗi: Không tìm thấy file config.php");
    }
}

// Khởi động session nếu chưa có để sử dụng $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================
// Code xử lý lấy danh mục menu (Giữ nguyên logic của bạn)
// =================================================================
$sql = "SELECT * FROM DanhMucSanPham WHERE HienThi = 1 ORDER BY ThuTuHienThi ASC";
$result = $conn->query($sql);
$menuData = []; 

if ($result && $result->num_rows > 0) { // Thêm kiểm tra $result tồn tại
    $allCats = [];
    while($row = $result->fetch_assoc()) { $allCats[] = $row; }
    
    foreach ($allCats as $cat) {
        if ($cat['IdDanhMucCha'] == NULL) {
            $cat['children'] = []; 
            $menuData[$cat['Id']] = $cat; 
        }
    }
    foreach ($allCats as $cat) {
        if ($cat['IdDanhMucCha'] != NULL) {
            $parentId = $cat['IdDanhMucCha'];
            if (isset($menuData[$parentId])) {
                $menuData[$parentId]['children'][] = $cat;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NovaWear - MLB Style</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css"> 
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top py-3 shadow-sm">
        <div class="container-fluid px-4 px-lg-5">
            
            <a class="navbar-brand fw-bolder fst-italic fs-3 me-5" href="index.php" style="letter-spacing: -1px;">
                NOVAWEAR
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 fw-bold text-uppercase" style="font-size: 0.9rem;">
                    <li class="nav-item"><a class="nav-link" href="index.php">Trang Chủ</a></li>

                    <li class="nav-item dropdown position-static">
                        <a class="nav-link dropdown-toggle remove-arrow" href="index.php#tat-ca-san-pham" id="navbarDropdown" role="button">
                            Sản Phẩm ⮟
                        </a>
                        
                        <div class="dropdown-menu w-100 mt-0 border-0 shadow-lg" aria-labelledby="navbarDropdown" style="border-top: 1px solid #eee;">
                            <div class="container">
                                <div class="row pt-4 pb-4">
                                    <?php foreach ($menuData as $parentCat): ?>
                                        <div class="col-6 col-md-3 col-lg-2 mb-4">
                                            <h6 class="mega-menu-title">
                                                <a href="category.php?id=<?= $parentCat['Id'] ?>">
                                                    <?= $parentCat['TenDanhMuc'] ?>
                                                </a>
                                            </h6>
                                            <ul class="list-unstyled mb-0">
                                                <?php if (!empty($parentCat['children'])): ?>
                                                    <?php foreach ($parentCat['children'] as $childCat): ?>
                                                        <li>
                                                            <a href="category.php?id=<?= $childCat['Id'] ?>" class="mega-menu-link">
                                                                <?= $childCat['TenDanhMuc'] ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </li>
                    
                    <li class="nav-item"><a class="nav-link" href="index.php#hang-moi-ve">Hàng Mới</a></li>
                    <li class="nav-item"><a class="nav-link" href="best_sellers.php">Hàng Bán Chạy</a></li>
                </ul>

                <div class="d-flex align-items-center gap-3 ms-auto">

                    <div class="dropdown">
                        <a class="text-dark fs-5 nav-icon dropdown-toggle" href="#" id="searchMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="searchMenu" style="min-width:260px;">
                            <form class="d-flex" action="search.php" method="get">
                                <input name="q" id="search-input" class="form-control me-2" type="search" placeholder="Tìm sản phẩm, danh mục..." aria-label="Search">
                                <button class="btn btn-dark" type="submit">Tìm</button>
                            </form>
                        </div>
                    </div>

                    <a href="cart.php" class="text-dark fs-5 position-relative nav-icon">
                        <i class="fas fa-shopping-bag"></i> <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white" style="font-size: 0.6rem;">0</span>
                    </a>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="dropdown">
                            <a class="text-dark fs-5 nav-icon dropdown-toggle d-flex align-items-center" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-2"></i>
                                <span class="d-none d-md-inline fw-bold"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                                <li><a class="dropdown-item" href="profile.php">Hồ sơ</a></li>
                                <li><a class="dropdown-item" href="change_password.php">Đổi mật khẩu</a></li>
                                <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-dark fs-5 nav-icon">
                            <i class="fas fa-user"></i>
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <script>
    // Focus search input when dropdown opens (Bootstrap 5 event)
    (function(){
        var searchToggle = document.getElementById('searchMenu');
        var searchInput = document.getElementById('search-input');
        if (searchToggle && searchInput && typeof bootstrap !== 'undefined') {
            searchToggle.addEventListener('shown.bs.dropdown', function(){
                setTimeout(function(){ searchInput.focus(); }, 50);
            });
        }
    })();
    </script>