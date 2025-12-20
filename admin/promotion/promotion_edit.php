<?php
// File này forward đến promotion_add.php để sửa
if (isset($_GET['id'])) {
    $_GET_ID = $_GET['id'];
    include 'promotion_add.php';
} else {
    header("Location: promotion.php");
    exit;
}
?>
