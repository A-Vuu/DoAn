<?php
session_start();
require_once 'config.php';
include 'includes/header.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$currentDate = date('Y-m-d H:i:s');
$promos = [];
$claimedPromos = [];
$activePromos = [];
$upcomingPromos = [];

// Lấy mã đã claim của user
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $stmtClaimed = $conn->prepare("SELECT IdMaGiamGia FROM MaGiamGia_NguoiDung WHERE IdNguoiDung = ?");
    if ($stmtClaimed) {
        $stmtClaimed->bind_param('i', $userId);
        $stmtClaimed->execute();
        $resultClaimed = $stmtClaimed->get_result();
        while ($row = $resultClaimed->fetch_assoc()) {
            $claimedPromos[] = (int)$row['IdMaGiamGia'];
        }
        $stmtClaimed->close();
    }
}

$stmt = $conn->prepare(
    "SELECT Id, MaCode, TenChuongTrinh, LoaiGiam, GiaTriGiam, GiamToiDa, GiaTriDonHangToiThieu, SoLuongMa, DaSuDung, NgayBatDau, NgayKetThuc
     FROM MaGiamGia
     WHERE HienThi = 1
     ORDER BY NgayBatDau ASC, Id DESC"
);
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $promos[] = $row;
        }
        $nowTs = strtotime($currentDate);
        foreach ($promos as $promo) {
            $startTs = !empty($promo['NgayBatDau']) ? strtotime($promo['NgayBatDau']) : null;
            if ($startTs && $startTs > $nowTs) {
                $upcomingPromos[] = $promo;
            } else {
                $activePromos[] = $promo;
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi hiện hành - NovaWear</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container py-5" style="max-width: 1080px;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">Khuyến mãi hiện hành</h2>
            <p class="text-muted mb-0">Danh sách mã giảm giá đang bật. Sao chép và áp dụng tại bước thanh toán.</p>
        </div>
        <a href="checkout.php" class="btn btn-dark">Đi tới thanh toán</a>
    </div>

    <?php if (empty($promos)): ?>
        <div class="alert alert-secondary">Hiện chưa có mã khuyến mãi nào khả dụng.</div>
    <?php else: ?>

        <?php if (!empty($upcomingPromos)): ?>
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-bold">Mã sắp diễn ra</h5>
                    <div class="text-muted small">Hiển thị để xem trước, chưa thể nhận hoặc sử dụng.</div>
                </div>
            </div>
            <div class="d-flex flex-nowrap gap-3 overflow-auto pb-2">
                <?php foreach ($upcomingPromos as $p):
                    $remaining = isset($p['SoLuongMa'], $p['DaSuDung']) ? max(0, (int)$p['SoLuongMa'] - (int)$p['DaSuDung']) : null;
                    $startTs = !empty($p['NgayBatDau']) ? strtotime($p['NgayBatDau']) : null;
                    $endTs   = !empty($p['NgayKetThuc']) ? strtotime($p['NgayKetThuc']) : null;
                    $isPercent = in_array($p['LoaiGiam'], ['PhanTram', '%'], true);
                    $typeLabel = $isPercent ? 'Giảm %' : 'Giảm tiền';
                    $valueText = $isPercent
                        ? (rtrim(rtrim(number_format($p['GiaTriGiam'], 2, '.', ''), '0'), '.') . '%')
                        : number_format($p['GiaTriGiam']) . 'đ';
                    $capText = ($isPercent && $p['GiamToiDa'] > 0)
                        ? 'Tối đa ' . number_format($p['GiamToiDa']) . 'đ'
                        : null;
                    $minText = ($p['GiaTriDonHangToiThieu'] > 0)
                        ? 'Đơn tối thiểu ' . number_format($p['GiaTriDonHangToiThieu']) . 'đ'
                        : 'Không yêu cầu đơn tối thiểu';
                    $dateText = [];
                    if (!empty($p['NgayBatDau'])) $dateText[] = 'Từ ' . date('d/m/Y H:i', strtotime($p['NgayBatDau']));
                    if (!empty($p['NgayKetThuc'])) $dateText[] = 'Đến ' . date('d/m/Y H:i', strtotime($p['NgayKetThuc']));
                    $dateLine = implode(' · ', $dateText);
                ?>
                <div class="card shadow-sm" style="min-width: 280px;">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="badge bg-dark me-2">Mã: <?php echo htmlspecialchars($p['MaCode']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($p['TenChuongTrinh']); ?></div>
                                <span class="badge bg-warning text-dark mt-1">Sắp diễn ra</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-secondary btn-sm" disabled>Chưa mở</button>
                            </div>
                        </div>
                        <div class="fw-bold fs-5"><?php echo $typeLabel; ?> · <?php echo $valueText; ?><?php echo $capText ? ' · ' . $capText : ''; ?></div>
                        <div class="text-muted small"><?php echo $minText; ?></div>
                        <?php if (!empty($dateLine)): ?>
                            <div class="text-muted small">
                                <i class="bi bi-clock me-1"></i><?php echo $dateLine; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($remaining !== null): ?>
                            <div class="text-muted small">Còn lại: <?php echo $remaining; ?> lượt</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($activePromos)): ?>
        <div class="row g-3">
            <?php foreach ($activePromos as $p):
                $remaining = isset($p['SoLuongMa'], $p['DaSuDung']) ? max(0, (int)$p['SoLuongMa'] - (int)$p['DaSuDung']) : null;
                $startTs = !empty($p['NgayBatDau']) ? strtotime($p['NgayBatDau']) : null;
                $endTs   = !empty($p['NgayKetThuc']) ? strtotime($p['NgayKetThuc']) : null;
                $nowTs   = strtotime($currentDate);
                $isExpired  = ($endTs && $endTs < $nowTs);
                $isUpcoming = ($startTs && $startTs > $nowTs);
                $isDepleted = ($remaining !== null && $remaining <= 0);

                $isPercent = in_array($p['LoaiGiam'], ['PhanTram', '%'], true);
                $typeLabel = $isPercent ? 'Giảm %' : 'Giảm tiền';
                $valueText = $isPercent
                    ? (rtrim(rtrim(number_format($p['GiaTriGiam'], 2, '.', ''), '0'), '.') . '%')
                    : number_format($p['GiaTriGiam']) . 'đ';
                $capText = ($isPercent && $p['GiamToiDa'] > 0)
                    ? 'Tối đa ' . number_format($p['GiamToiDa']) . 'đ'
                    : null;
                $minText = ($p['GiaTriDonHangToiThieu'] > 0)
                    ? 'Đơn tối thiểu ' . number_format($p['GiaTriDonHangToiThieu']) . 'đ'
                    : 'Không yêu cầu đơn tối thiểu';
                $dateText = [];
                if (!empty($p['NgayBatDau'])) $dateText[] = 'Từ ' . date('d/m/Y H:i', strtotime($p['NgayBatDau']));
                if (!empty($p['NgayKetThuc'])) $dateText[] = 'Đến ' . date('d/m/Y H:i', strtotime($p['NgayKetThuc']));
                $dateLine = implode(' · ', $dateText);

                if ($isExpired) {
                    $badgeClass = 'bg-secondary';
                    $badgeText  = 'Hết hạn';
                } elseif ($isDepleted) {
                    $badgeClass = 'bg-secondary';
                    $badgeText  = 'Hết lượt';
                } elseif ($isUpcoming) {
                    $badgeClass = 'bg-warning text-dark';
                    $badgeText  = 'Sắp diễn ra';
                } else {
                    $badgeClass = 'bg-success';
                    $badgeText  = 'Đang áp dụng';
                }
            ?>
            <div class="col-12 col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="badge bg-dark me-2">Mã: <?php echo htmlspecialchars($p['MaCode']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($p['TenChuongTrinh']); ?></div>
                                <span class="badge <?php echo $badgeClass; ?> mt-1"><?php echo $badgeText; ?></span>
                            </div>
                            <?php 
                            $isClaimed = in_array((int)$p['Id'], $claimedPromos);
                            $canClaim = !($isExpired || $isDepleted || $isUpcoming) && !$isClaimed;
                            ?>
                            <div class="d-flex gap-2">
                                <?php if ($isClaimed): ?>
                                    <button class="btn btn-success btn-sm" disabled>
                                        <i class="fas fa-check me-1"></i>Đã nhận
                                    </button>
                                    <button class="btn btn-outline-dark btn-sm" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($p['MaCode']); ?>')">Sao chép</button>
                                <?php elseif ($canClaim && isset($_SESSION['user_id'])): ?>
                                    <button class="btn btn-primary btn-sm" onclick="claimPromo(<?php echo (int)$p['Id']; ?>)">
                                        <i class="fas fa-gift me-1"></i>Nhận mã
                                    </button>
                                <?php elseif (!isset($_SESSION['user_id'])): ?>
                                    <a href="login.php" class="btn btn-primary btn-sm">Đăng nhập để nhận</a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Không thể nhận</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="fw-bold fs-5"><?php echo $typeLabel; ?> · <?php echo $valueText; ?><?php echo $capText ? ' · ' . $capText : ''; ?></div>
                        <div class="text-muted small"><?php echo $minText; ?></div>
                        <?php if (!empty($dateLine)): ?>
                            <div class="text-muted small">
                                <i class="bi bi-clock me-1"></i><?php echo $dateLine; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($remaining !== null): ?>
                            <div class="text-muted small">Còn lại: <?php echo $remaining; ?> lượt</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function claimPromo(promoId) {
    const formData = new FormData();
    formData.append('promo_id', promoId);
    
    fetch('claim_promo.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('✗ ' + data.message);
        }
    })
    .catch(err => {
        alert('✗ Lỗi kết nối. Vui lòng thử lại.');
    });
}
</script>
</body>
</html>
