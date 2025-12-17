<?php
session_start();
require_once '../../config.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['admin_login'])) {
    header("Location: ../login.php");
    exit();
}

$msg = "";

// --- 1. XỬ LÝ ACTION: XÓA ĐÁNH GIÁ LẺ ---
if (isset($_POST['delete_review'])) {
    $reviewId = intval($_POST['review_id']);
    $sqlDel = "DELETE FROM danhgiasanpham WHERE Id = $reviewId";
    if (mysqli_query($conn, $sqlDel)) {
        $msg = "<div class='alert alert-success mb-3'>Đã xóa đánh giá #$reviewId thành công!</div>";
    } else {
        $msg = "<div class='alert alert-danger mb-3'>Lỗi: " . mysqli_error($conn) . "</div>";
    }
}

// --- [UPDATED] 2. XỬ LÝ ACTION: CHẶN / BỎ CHẶN USER & XÓA ĐÁNH GIÁ ---
if (isset($_POST['toggle_block_user'])) {
    $userId = intval($_POST['user_id']);
    $currentStatus = intval($_POST['current_status']);
    
    // Logic: Nếu đang 1 (Active) -> 0 (Block). Ngược lại 0 -> 1.
    $newStatus = ($currentStatus == 1) ? 0 : 1;
    
    // Cập nhật trạng thái User
    $sqlBlock = "UPDATE nguoidung SET TrangThai = $newStatus WHERE Id = $userId";
    
    if (mysqli_query($conn, $sqlBlock)) {
        if ($newStatus == 0) {
            // [NEW] NẾU LÀ HÀNH ĐỘNG CHẶN -> XÓA TOÀN BỘ ĐÁNH GIÁ CỦA USER NÀY
            $sqlDelAll = "DELETE FROM danhgiasanpham WHERE IdNguoiDung = $userId";
            mysqli_query($conn, $sqlDelAll); // Thực thi xóa
            
            $msg = "<div class='alert alert-success mb-3'>Đã CHẶN người dùng (ID: $userId) và XÓA TOÀN BỘ đánh giá của họ!</div>";
        } else {
            $msg = "<div class='alert alert-success mb-3'>Đã BỎ CHẶN người dùng (ID: $userId) thành công!</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger mb-3'>Lỗi cập nhật trạng thái người dùng.</div>";
    }
}

// --- 3. XỬ LÝ ACTION: TRẢ LỜI ĐÁNH GIÁ ---
if (isset($_POST['reply_review'])) {
    $reviewId = intval($_POST['review_id']);
    $replyContent = mysqli_real_escape_string($conn, $_POST['reply_content']);
    $currentDate = date('Y-m-d H:i:s');
    
    $sqlReply = "UPDATE danhgiasanpham SET PhanHoiQuanTri = '$replyContent', NgayPhanHoi = '$currentDate' WHERE Id = $reviewId";
    
    if (mysqli_query($conn, $sqlReply)) {
        $msg = "<div class='alert alert-success mb-3'>Đã gửi phản hồi cho đánh giá #$reviewId!</div>";
    } else {
        $msg = "<div class='alert alert-danger mb-3'>Lỗi gửi phản hồi: " . mysqli_error($conn) . "</div>";
    }
}

// --- 4. LỌC VÀ TÌM KIẾM DỮ LIỆU ---

// Bộ lọc đánh giá
$filterStar = isset($_GET['star']) ? intval($_GET['star']) : 0;
$filterProduct = isset($_GET['product_keyword']) ? mysqli_real_escape_string($conn, $_GET['product_keyword']) : '';
$filterUserReview = isset($_GET['user_keyword']) ? mysqli_real_escape_string($conn, $_GET['user_keyword']) : '';

$sqlReviews = "SELECT d.*, s.TenSanPham, s.Id as IdSP, n.HoTen, n.TrangThai as TrangThaiUser 
               FROM danhgiasanpham d
               JOIN sanpham s ON d.IdSanPham = s.Id
               JOIN nguoidung n ON d.IdNguoiDung = n.Id
               WHERE 1=1";

if ($filterStar > 0) {
    $sqlReviews .= " AND d.SoSao = $filterStar";
}
if (!empty($filterProduct)) {
    $sqlReviews .= " AND s.TenSanPham LIKE '%$filterProduct%'";
}
if (!empty($filterUserReview)) {
    $sqlReviews .= " AND (n.HoTen LIKE '%$filterUserReview%' OR n.Id = '$filterUserReview')";
}

$sqlReviews .= " ORDER BY d.NgayDanhGia DESC";
$resReviews = mysqli_query($conn, $sqlReviews);


// Bộ lọc User
$searchUser = isset($_GET['search_user']) ? mysqli_real_escape_string($conn, $_GET['search_user']) : '';
$filterUserStatus = isset($_GET['user_status']) ? $_GET['user_status'] : 'all';

$resUsers = null;
if (!empty($searchUser) || $filterUserStatus !== 'all') {
    $sqlUser = "SELECT * FROM nguoidung WHERE 1=1";
    
    if (!empty($searchUser)) {
        $sqlUser .= " AND (Id = '$searchUser' OR HoTen LIKE '%$searchUser%')";
    }
    
    if ($filterUserStatus === 'blocked') {
        $sqlUser .= " AND TrangThai = 0";
    } elseif ($filterUserStatus === 'active') {
        $sqlUser .= " AND TrangThai = 1";
    }
    
    $resUsers = mysqli_query($conn, $sqlUser);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Đánh giá & Chặn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css"> 
    <style>
        .star-gold { color: #ffc107; }
        .text-small { font-size: 0.85rem; }
        .admin-reply-box { background-color: #f8f9fa; border-left: 3px solid #0d6efd; padding: 10px; margin-top: 5px; font-size: 0.9em; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h4 class="text-center mb-4">NovaWear Admin</h4>
        <div class="px-3 mb-3 text-white">
             Xin chào, <strong><?php echo $_SESSION['admin_name']; ?></strong>
        </div>
        <hr style="border-color: #4f5962;">
        <nav>
            <a href="../category.php">Danh mục sản phẩm</a>
            <a href="../product/product.php">Quản lý sản phẩm</a>
            <a href="../orders/orders.php">Quản lý đơn hàng</a>
            <a href="../news/news.php">Tin tức</a>
            <a href="../banner/banner.php">Quảng cáo</a>
            <a href="danhgia_chan.php" class="active">Đánh giá & chặn</a>
            <a href="../logout.php">Đăng xuất</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Quản lý Đánh giá & Chặn người dùng</h3>
        </div>

        <?php echo $msg; ?>

        <div class="row g-4">
            
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-danger text-white fw-bold">
                        <i class="fas fa-user-lock me-2"></i> Tìm & Chặn User
                    </div>
                    <div class="card-body">
                        <form method="GET" class="mb-3">
                            <label class="form-label fw-bold">Tìm kiếm:</label>
                            <div class="input-group mb-2">
                                <input type="text" name="search_user" class="form-control" placeholder="Nhập ID hoặc Tên..." value="<?php echo htmlspecialchars($searchUser); ?>">
                                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i></button>
                            </div>
                            
                            <div class="input-group input-group-sm">
                                <label class="input-group-text">Trạng thái:</label>
                                <select name="user_status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo ($filterUserStatus == 'all') ? 'selected' : ''; ?>>-- Tất cả --</option>
                                    <option value="blocked" <?php echo ($filterUserStatus == 'blocked') ? 'selected' : ''; ?>>Đã chặn (Blocked)</option>
                                    <option value="active" <?php echo ($filterUserStatus == 'active') ? 'selected' : ''; ?>>Hoạt động (Active)</option>
                                </select>
                            </div>
                        </form>

                        <?php if ($resUsers && mysqli_num_rows($resUsers) > 0): ?>
                            <ul class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                                <?php while($u = mysqli_fetch_assoc($resUsers)): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <strong><?php echo $u['HoTen']; ?></strong> <br>
                                            <small class="text-muted">ID: <?php echo $u['Id']; ?></small>
                                            <?php if($u['TrangThai'] == 1): ?>
                                                <span class="badge bg-success" style="font-size: 0.7em;">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger" style="font-size: 0.7em;">Blocked</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="user_id" value="<?php echo $u['Id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $u['TrangThai']; ?>">
                                            
                                            <?php if($u['TrangThai'] == 1): ?>
                                                <button type="submit" name="toggle_block_user" class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('CẢNH BÁO: Chặn người dùng này sẽ:\n1. Khóa tài khoản vĩnh viễn.\n2. XÓA TOÀN BỘ đánh giá của họ.\n\nBạn có chắc chắn muốn tiếp tục?')">
                                                    Chặn & Xóa CMT
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="toggle_block_user" class="btn btn-sm btn-outline-success">
                                                    Bỏ chặn
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        <?php elseif (isset($_GET['search_user']) || $filterUserStatus != 'all'): ?>
                            <p class="text-muted text-center small mt-3">Không tìm thấy user phù hợp.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <input type="text" name="product_keyword" class="form-control form-control-sm" placeholder="Tìm theo tên SP..." value="<?php echo htmlspecialchars($filterProduct); ?>">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="user_keyword" class="form-control form-control-sm" placeholder="Tìm User (Tên/ID)..." value="<?php echo htmlspecialchars($filterUserReview); ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="star" class="form-select form-select-sm">
                                    <option value="0">-- Tất cả sao --</option>
                                    <option value="5" <?php echo ($filterStar == 5)?'selected':''; ?>>5 Sao</option>
                                    <option value="4" <?php echo ($filterStar == 4)?'selected':''; ?>>4 Sao</option>
                                    <option value="3" <?php echo ($filterStar == 3)?'selected':''; ?>>3 Sao</option>
                                    <option value="2" <?php echo ($filterStar == 2)?'selected':''; ?>>2 Sao</option>
                                    <option value="1" <?php echo ($filterStar == 1)?'selected':''; ?>>1 Sao</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Lọc</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">ID</th>
                                    <th width="25%">Sản phẩm / User</th>
                                    <th width="10%">Đánh giá</th>
                                    <th width="40%">Nội dung & Phản hồi</th>
                                    <th width="20%">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($resReviews) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($resReviews)): ?>
                                    <tr>
                                        <td>#<?php echo $row['Id']; ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark text-wrap mb-1" style="max-width: 150px;">
                                                <?php echo $row['TenSanPham']; ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">Bởi:</small> <b><?php echo $row['HoTen']; ?></b>
                                            <?php if($row['TrangThaiUser'] == 0): ?>
                                                <i class="fas fa-ban text-danger" title="User này đang bị chặn"></i>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('d/m/y H:i', strtotime($row['NgayDanhGia'])); ?></small>
                                        </td>
                                        
                                        <td>
                                            <span class="fw-bold text-warning"><?php echo $row['SoSao']; ?></span> <i class="fas fa-star star-gold small"></i>
                                        </td>

                                        <td>
                                            <?php if(!empty($row['TieuDe'])): ?>
                                                <b><?php echo $row['TieuDe']; ?></b><br>
                                            <?php endif; ?>
                                            <span class="text-muted text-small"><?php echo $row['NoiDung']; ?></span>

                                            <?php if(!empty($row['PhanHoiQuanTri'])): ?>
                                                <div class="admin-reply-box rounded">
                                                    <strong>Admin:</strong> <?php echo htmlspecialchars($row['PhanHoiQuanTri']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <form method="POST" onsubmit="return confirm('Xóa đánh giá này?');" class="d-inline-block mb-1">
                                                <input type="hidden" name="review_id" value="<?php echo $row['Id']; ?>">
                                                <button type="submit" name="delete_review" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Xóa
                                                </button>
                                            </form>

                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $row['Id']; ?>">
                                                <i class="fas fa-reply"></i> Trả lời
                                            </button>

                                            <div class="modal fade" id="replyModal<?php echo $row['Id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Trả lời đánh giá #<?php echo $row['Id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <p><strong>Khách hàng:</strong> <?php echo $row['HoTen']; ?></p>
                                                                <p><strong>Nội dung:</strong> <?php echo $row['NoiDung']; ?></p>
                                                                <hr>
                                                                <label class="form-label fw-bold">Phản hồi của Admin:</label>
                                                                <input type="hidden" name="review_id" value="<?php echo $row['Id']; ?>">
                                                                <textarea name="reply_content" class="form-control" rows="4" required><?php echo $row['PhanHoiQuanTri']; ?></textarea>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                                <button type="submit" name="reply_review" class="btn btn-primary">Gửi phản hồi</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Không tìm thấy đánh giá nào phù hợp.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> 

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>