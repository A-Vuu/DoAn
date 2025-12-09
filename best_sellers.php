<?php
session_start();
// Gọi file cấu hình và header
require_once 'config.php';
include 'includes/header.php';

// 1. Cấu hình tiêu đề cho trang
$sectionTitle = "TOP BÁN CHẠY";
$sectionDesc  = "Những sản phẩm được săn đón nhiều nhất tại NOVAWEAR";

// Cấu hình Load More
$enableLoadMore = true; // Bật nút xem thêm
$pageType = 'bestseller'; // Báo cho API biết đây là trang Bán chạy

// Query ban đầu CHỈ LẤY 12 SẢN PHẨM (Thêm LIMIT 12)
$sqlQuery = "SELECT s.*, a.DuongDanAnh 
             FROM SanPham s 
             LEFT JOIN AnhSanPham a ON s.Id = a.IdSanPham AND a.LaAnhChinh = 1 
             WHERE s.HienThi = 1 
             ORDER BY s.DaBan DESC 
             LIMIT 12"; // <-- Quan trọng

?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-dark">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Hàng bán chạy</li>
        </ol>
    </nav>

    <?php include 'includes/product_list_partial.php'; ?>
</div>

<?php include 'includes/footer.php'; ?>