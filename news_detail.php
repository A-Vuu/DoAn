<?php include 'includes/header.php'; ?>

<?php
// Lấy ID từ URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sqlDetail = "SELECT * FROM TinTuc WHERE Id = $id";
    $resDetail = mysqli_query($conn, $sqlDetail);
    $post = mysqli_fetch_assoc($resDetail);

    // Cập nhật lượt xem
    mysqli_query($conn, "UPDATE TinTuc SET LuotXem = LuotXem + 1 WHERE Id = $id");
}

if (!$post) {
    echo "<div class='container py-5 text-center'><h3>Bài viết không tồn tại!</h3><a href='index.php' class='btn btn-primary'>Về trang chủ</a></div>";
    include 'includes/footer.php';
    exit();
}
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="news.php">Tin tức</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bài viết</li>
                </ol>
            </nav>

            <h1 class="mb-3"><?php echo $post['TieuDe']; ?></h1>
            <p class="text-muted">
                <i class="far fa-user"></i> <?php echo $post['TacGia']; ?> | 
                <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($post['NgayDang'])); ?> | 
                <i class="far fa-eye"></i> <?php echo $post['LuotXem']; ?> lượt xem
            </p>
            <hr>

            <div class="fw-bold mb-4 fst-italic bg-light p-3 border-start border-4 border-primary">
                <?php echo $post['TomTat']; ?>
            </div>

            <div class="content-body" style="font-size: 1.1rem; line-height: 1.8;">
                <?php 
                // Hiển thị nội dung (cho phép HTML)
                echo nl2br($post['NoiDung']); 
                ?>
            </div>
            
            <!-- <div class="mt-5">
                <a href="news.php" class="btn btn-secondary">&larr; Quay lại danh sách tin</a>
            </div> -->
        </div>


    </div>
</div>

<?php include 'includes/footer.php'; ?>