<?php
// =================================================================
// HEADER.PHP - PHIÊN BẢN ĐÃ SỬA LỖI & ĐỒNG BỘ SESSION/DB
// =================================================================

// 1. Kết nối CSDL (Nếu chưa có)
if (!isset($conn)) {
    if (file_exists(__DIR__ . '/../config.php')) require_once __DIR__ . '/../config.php';
    elseif (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
}

// 2. Khởi động session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//     // =================================================================
//     // Code xử lý chặn user
//     // =================================================================

// if (isset($_SESSION['user_id']) && !isset($_SESSION['admin_login'])) {

//     $uid = (int)$_SESSION['user_id'];
//     $sql = "SELECT TrangThai FROM nguoidung WHERE Id = $uid LIMIT 1";
//     $res = mysqli_query($conn, $sql);

//     if ($res && mysqli_num_rows($res) > 0) {
//         $u = mysqli_fetch_assoc($res);

//         if ($u['TrangThai'] == 0) {
//             session_unset();
//             session_destroy();

//             header("Location: login.php?blocked=1");
//             exit();
//         }
//     }
// }

// =================================================================
// LOGIC TÍNH SỐ LƯỢNG GIỎ HÀNG (QUAN TRỌNG)
// =================================================================
$cartCount = 0;

// Hàm hỗ trợ đếm số lượng an toàn
if (!function_exists('calculate_cart_qty')) {
    function calculate_cart_qty($items) {
        $total = 0;
        if (is_array($items)) {
            foreach ($items as $item) {
                // Kiểm tra các biến: qty (session), quantity, SoLuong (DB)
                $q = 0;
                if (isset($item['qty'])) $q = intval($item['qty']);
                elseif (isset($item['quantity'])) $q = intval($item['quantity']);
                elseif (isset($item['SoLuong'])) $q = intval($item['SoLuong']);
                
                // Nếu chưa có qty, mặc định là 1
                if ($q <= 0) $q = 1;
                $total += $q;
            }
        }
        return $total;
    }
}

// ƯU TIÊN 1: Nếu trang hiện tại (cart.php) đã có biến $cart
// Dùng luôn biến này để hiển thị -> Đồng bộ tuyệt đối với bảng bên dưới
if (isset($cart) && is_array($cart) && !empty($cart)) {
    $cartCount = calculate_cart_qty($cart);
} 
// ƯU TIÊN 2: Nếu chưa có $cart, tự tính toán
else {
    // 2.1: Thử lấy từ Database nếu đã đăng nhập
   // 2.1: Thử lấy từ Database nếu đã đăng nhập (LOGIC MỚI: GioHang -> ChiTietGioHang)
    $db_count = 0;
    if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
        $uid = intval($_SESSION['user_id']);
        
        // Truy vấn tổng số lượng từ bảng ChiTietGioHang thông qua bảng cha GioHang
        // Lưu ý: Cần biến $conn (đã được include từ config.php ở trên)
        if (isset($conn)) {
            $sqlH = "SELECT SUM(ct.SoLuong) as total 
                     FROM GioHang gh 
                     JOIN ChiTietGioHang ct ON gh.Id = ct.IdGioHang 
                     WHERE gh.IdNguoiDung = $uid";
                     
            $resH = mysqli_query($conn, $sqlH);
            if ($resH && $rowH = mysqli_fetch_assoc($resH)) {
                $db_count = intval($rowH['total']);
            }
        }
    }
    
    // 2.2: Lấy từ Session
    $session_count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $session_count = calculate_cart_qty($_SESSION['cart']);
    }

    // --- QUYẾT ĐỊNH HIỂN THỊ ---
    // Nếu DB có dữ liệu -> Dùng DB
    // Nếu DB = 0 nhưng Session có dữ liệu -> Dùng Session (Trường hợp của bạn)
    if ($db_count > 0) {
        $cartCount = $db_count;
    } else {
        $cartCount = $session_count;
    }
}

// =================================================================
// Code xử lý lấy danh mục menu (Giữ nguyên)
// =================================================================
$menuData = []; 
if (isset($conn)) {
    $sql = "SELECT * FROM DanhMucSanPham WHERE HienThi = 1 ORDER BY ThuTuHienThi ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
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
                        <i class="fas fa-shopping-bag"></i> <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark text-white" style="font-size: 0.6rem;"><?php echo $cartCount; ?></span>
                    </a>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="dropdown user-dropdown-wrapper">
                            <a class="text-dark fs-5 nav-icon d-flex align-items-center user-dropdown-trigger" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-2"></i>
                                <span class="d-none d-md-inline fw-bold"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end user-dropdown-menu" aria-labelledby="userMenu">
                                <!-- User Header -->
                                <div class="user-dropdown-header px-4 py-3 border-bottom">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm bg-gradient text-white d-flex align-items-center justify-content-center fw-bold fs-5">
                                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($_SESSION['user_email'] ?? 'user@novawear.vn') ?></div>
                                            <span class="badge bg-primary text-white" style="font-size: 0.65rem;">Thành viên</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Stats -->
                                <div class="quick-stats px-4 py-3 border-bottom d-flex gap-3 justify-content-around">
                                    <?php
                                    $uid = intval($_SESSION['user_id']);
                                    $orderCount = 0;
                                    $stmtCount = $conn->prepare('SELECT COUNT(*) as cnt FROM donhang WHERE IdNguoiDung = ?');
                                    if ($stmtCount) {
                                        $stmtCount->bind_param('i', $uid);
                                        $stmtCount->execute();
                                        $orderCount = intval($stmtCount->get_result()->fetch_assoc()['cnt']);
                                        $stmtCount->close();
                                    }
                                    ?>
                                    <a href="orders.php" class="text-center stat-item text-decoration-none small">
                                        <div class="fw-bold text-dark"><?php echo $orderCount; ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Đơn hàng</div>
                                    </a>
                                    <a href="cart.php" class="text-center stat-item text-decoration-none small">
                                        <div class="fw-bold text-dark"><?php echo $cartCount; ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Trong giỏ</div>
                                    </a>
                                    <div class="text-center stat-item">
                                        <div class="fw-bold text-dark">0đ</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Reward</div>
                                    </div>
                                </div>

                                <!-- Account Management -->
                                <div class="dropdown-header fw-bold text-muted small py-2 px-4">QUẢN LÝ TÀI KHOẢN</div>
                                <ul class="list-unstyled mb-0 px-2">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Hồ sơ cá nhân</a></li>
                                    <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>Lịch sử đơn hàng</a></li>
                                    <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-lock me-2"></i>Đổi mật khẩu</a></li>
                                </ul>

                                <!-- Shortcuts
                                <div class="dropdown-header fw-bold text-muted small py-2 px-4 mt-2">LIÊN KẾT NHANH</div>
                                <ul class="list-unstyled mb-0 px-2">
                                    <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-bag me-2"></i>Xem giỏ hàng</a></li>
                                    <li><a class="dropdown-item" href="best_sellers.php"><i class="fas fa-star me-2"></i>Hàng bán chạy</a></li>
                                    <li><a class="dropdown-item" href="index.php#hang-moi-ve"><i class="fas fa-package me-2"></i>Hàng mới về</a></li>
                                </ul> -->

                                <!-- Support
                                <div class="dropdown-header fw-bold text-muted small py-2 px-4 mt-2">HỖ TRỢ</div>
                                <ul class="list-unstyled mb-0 px-2">
                                    <li><a class="dropdown-item" href="mailto:support@novawear.vn"><i class="fas fa-envelope me-2"></i>Liên hệ hỗ trợ</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="fas fa-question-circle me-2"></i>Câu hỏi thường gặp</a></li>
                                </ul> -->

                                <!-- Divider & Logout -->
                                <hr class="dropdown-divider my-2">
                                <a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a>
                            </div>
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