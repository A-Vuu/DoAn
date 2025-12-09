<?php
session_start(); // 1. Khởi động session để xác định đang đăng nhập là ai

// 2. Xóa tất cả các biến session
session_unset(); 

// 3. Hủy hoàn toàn session trên server
session_destroy(); 

// 4. Quan trọng nhất: Chuyển hướng về lại trang đăng nhập
header("Location: login.php");
exit(); // Ngắt luồng xử lý ngay lập tức
?>