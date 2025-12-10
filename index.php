<?php
session_start();
require_once 'config.php'; 
include 'includes/header.php'; 
?>

<div id="homeCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
       <?php
        $currentDate = date('Y-m-d H:i:s');
        $sqlBanner = "SELECT * FROM Banner 
                      WHERE ViTri = 'TrangChu' 
                      AND HienThi = 1 
                      AND (NgayBatDau IS NULL OR NgayBatDau <= '$currentDate')
                      AND (NgayKetThuc IS NULL OR NgayKetThuc >= '$currentDate')
                      ORDER BY ThuTu ASC, Id DESC";

        $resBanner = mysqli_query($conn, $sqlBanner);
        $count = 0;

        if (mysqli_num_rows($resBanner) > 0) {
            while ($rowBanner = mysqli_fetch_assoc($resBanner)) {
                $activeClass = ($count == 0) ? 'active' : '';
                $imgBanner = !empty($rowBanner['HinhAnh']) ? "uploads/".$rowBanner['HinhAnh'] : "https://via.placeholder.com/1920x800";
                
                $bannerLink = "#";
                switch ($rowBanner['LoaiLienKet']) {
                    case 'SanPham': if (!empty($rowBanner['IdSanPham'])) $bannerLink = "product_detail.php?id=" . $rowBanner['IdSanPham']; break;
                    case 'DanhMuc': if (!empty($rowBanner['IdDanhMuc'])) $bannerLink = "category.php?id=" . $rowBanner['IdDanhMuc']; break;
                    case 'URL': if (!empty($rowBanner['LienKet'])) $bannerLink = $rowBanner['LienKet']; break;
                    case 'MaGiamGia': $bannerLink = "promotions.php"; break;
                }
        ?>
            <div class="carousel-item <?php echo $activeClass; ?>">
                <a href="<?php echo $bannerLink; ?>">
                    <img src="<?php echo $imgBanner; ?>" class="d-block w-100" alt="<?php echo $rowBanner['TieuDe']; ?>" style="width: 100%; height: auto;">
                </a>
            </div>
        <?php
                $count++;
            }
        } else {
            echo '
            <div class="carousel-item active">
                <img src="https://via.placeholder.com/1920x800?text=NovaWear+Fashion" class="d-block w-100" alt="Default Banner" style="width: 100%; height: auto;">
            </div>';
        }
        ?>
    </div>
    
    <button class="carousel-control-prev" type="button" data-bs-target="#homeCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#homeCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>
<div class="container py-5">
    <?php $isHomePage = true; ?>

    <?php
    $sectionTitle = "SẢN PHẨM NỔI BẬT";
    $sectionDesc  = "Top những sản phẩm bán chạy nhất tuần qua";
    $sqlQuery     = "SELECT s.*, a.DuongDanAnh 
                      FROM SanPham s 
                      LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
                      WHERE s.HienThi = 1 AND s.SanPhamNoiBat = 1 
                      ORDER BY s.Id DESC LIMIT 4";
    include 'includes/product_list_partial.php';
    ?>

    <div id="san-pham-sale"> </div>
    <?php
    $sectionTitle = "SẢN PHẨM ĐANG SALE";
    $sectionDesc  = "Săn deal hời - Giá tốt nhất hôm nay";
    // Logic: Lấy sản phẩm có Giá KM nhỏ hơn Giá Gốc và lớn hơn 0
    $sqlQuery     = "SELECT s.*, a.DuongDanAnh 
                      FROM SanPham s 
                      LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
                      WHERE s.HienThi = 1 
                      AND s.GiaKhuyenMai > 0 
                      AND s.GiaKhuyenMai < s.GiaGoc
                      ORDER BY s.Id DESC LIMIT 4"; 
    
    include 'includes/product_list_partial.php';
    ?>

    <div id="hang-moi-ve"> </div>
    <?php
    $sectionTitle = "HÀNG MỚI VỀ";
    $sectionDesc  = "Cập nhật xu hướng thời trang mới nhất";
    $sqlQuery     = "SELECT s.*, a.DuongDanAnh 
                      FROM SanPham s 
                      LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
                      WHERE s.HienThi = 1 AND s.SanPhamMoi = 1 
                      ORDER BY s.Id DESC LIMIT 8";
    include 'includes/product_list_partial.php';
    ?>

    <div id="tat-ca-san-pham"> </div>
    <?php
    $sectionTitle = "TẤT CẢ SẢN PHẨM";
    $sectionDesc  = "Khám phá bộ sưu tập đầy đủ của NOVAWEAR";
    $sqlQuery     = "SELECT s.*, a.DuongDanAnh 
                      FROM SanPham s 
                      LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
                      WHERE s.HienThi = 1 
                      ORDER BY s.Id DESC LIMIT 12"; 
    include 'includes/product_list_partial.php';
    ?>

    <div class="text-center mb-5">
        <a href="category.php" class="btn btn-outline-dark px-5 py-2">Xem toàn bộ sản phẩm</a>
    </div>

    <div class="section-header text-center mt-5 mb-4">
        <h3 class="fw-bold text-uppercase">TIN THỜI TRANG</h3>
    </div>
    <div class="row g-4 mb-5">
        <?php
        $sqlNews = "SELECT t.*, d.TenDanhMuc 
                    FROM TinTuc t
                    LEFT JOIN DanhMucTinTuc d ON t.IdDanhMuc = d.Id
                    WHERE t.HienThi = 1 
                    ORDER BY t.GhimBaiViet DESC, t.NgayDang DESC 
                    LIMIT 3";
        $resNews = mysqli_query($conn, $sqlNews);

        if (mysqli_num_rows($resNews) > 0):
            while ($rowNews = mysqli_fetch_assoc($resNews)):
                $imgNews = !empty($rowNews['AnhDaiDien']) ? "uploads/".$rowNews['AnhDaiDien'] : "https://via.placeholder.com/400x250";
                $linkNews = "news_detail.php?id=" . $rowNews['Id'];
                $dateNews = date('d/m/Y', strtotime($rowNews['NgayDang']));
        ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition-all">
                    <a href="<?php echo $linkNews; ?>" class="overflow-hidden">
                        <img src="<?php echo $imgNews; ?>" class="card-img-top zoom-img" alt="<?php echo $rowNews['TieuDe']; ?>" style="height: 250px; object-fit: cover;">
                    </a>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span><i class="bi bi-calendar3 me-1"></i><?php echo $dateNews; ?></span>
                            <?php if($rowNews['TenDanhMuc']): ?>
                                <span class="text-uppercase fw-bold text-primary"><?php echo $rowNews['TenDanhMuc']; ?></span>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title">
                            <a href="<?php echo $linkNews; ?>" class="text-decoration-none text-dark fw-bold text-truncate-2">
                                <?php echo $rowNews['TieuDe']; ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted text-truncate-3" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo $rowNews['TomTat']; ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="<?php echo $linkNews; ?>" class="btn btn-outline-dark btn-sm">Đọc thêm</a>
                    </div>
                </div>
            </div>
        <?php 
            endwhile; 
        else:
        ?>
            <div class="col-12 text-center">
                <p class="text-muted">Chưa có tin tức nào được cập nhật.</p>
            </div>
        <?php endif; ?>

        <style>
            .text-truncate-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .zoom-img { transition: transform 0.3s ease; }
            .card:hover .zoom-img { transform: scale(1.05); }
        </style>
    </div>
</div>

<?php include 'includes/footer.php'; ?>