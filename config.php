<?php
$conn = mysqli_connect("localhost", "root", "", "NovaWear1");
mysqli_set_charset($conn, 'utf8');
if (!$conn) { die("Kết nối thất bại: " . mysqli_connect_error()); }
?>