<?php
// Trang hồ sơ người dùng (nâng cấp chuyên nghiệp)
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
$errors = [];
$success = '';
$orderCount = 0;
$cartCount = 0;
$addressCount = 0;

// Lấy thông tin người dùng (map NgayDangKy -> NgayTaoTaiKhoan để hiển thị)
$stmtUser = $conn->prepare('SELECT Id, HoTen, Email, SoDienThoai, NgayDangKy AS NgayTaoTaiKhoan, LanTruyCuoiCung, TongGiaTriMuaHang, LoaiKhachHang, TrangThai FROM NguoiDung WHERE Id = ? LIMIT 1');
if ($stmtUser) {
    $stmtUser->bind_param('i', $userId);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();
} else {
    $user = null;
}

$userTotalSpent = isset($user['TongGiaTriMuaHang']) ? (float)$user['TongGiaTriMuaHang'] : 0;
$userTier = $user['LoaiKhachHang'] ?? 'Thành viên';
$userStatus = (isset($user['TrangThai']) && intval($user['TrangThai']) === 0) ? 'Tạm khóa' : 'Hoạt động';
$lastLogin = $user['LanTruyCuoiCung'] ?? null;
$lastLoginDisplay = $lastLogin ? date('d/m/Y H:i', strtotime($lastLogin)) : 'Chưa ghi nhận';
$accountCreatedDisplay = isset($user['NgayTaoTaiKhoan']) ? date('d/m/Y', strtotime($user['NgayTaoTaiKhoan'])) : 'N/A';

$openOrderCount = 0;
$recentOrders = [];
$statusCounts = [
    'ChoXacNhan' => 0,
    'DaXacNhan' => 0,
    'DangGiao' => 0,
    'HoanThanh' => 0,
    'DaHuy' => 0,
];

// Lấy số lượng đơn hàng
$stmtOrders = $conn->prepare('SELECT COUNT(*) as count FROM donhang WHERE IdNguoiDung = ?');
if ($stmtOrders) {
    $stmtOrders->bind_param('i', $userId);
    $stmtOrders->execute();
    $orderCount = intval($stmtOrders->get_result()->fetch_assoc()['count']);
    $stmtOrders->close();
}

// Lấy số lượng đơn đang mở
$stmtOpenOrders = $conn->prepare("SELECT COUNT(*) as count FROM donhang WHERE IdNguoiDung = ? AND TrangThaiDonHang IN ('ChoXacNhan','DaXacNhan','DangGiao')");
if ($stmtOpenOrders) {
    $stmtOpenOrders->bind_param('i', $userId);
    $stmtOpenOrders->execute();
    $openOrderCount = intval($stmtOpenOrders->get_result()->fetch_assoc()['count']);
    $stmtOpenOrders->close();
}

// Lấy các đơn gần đây
$stmtRecentOrders = $conn->prepare('SELECT Id, MaDonHang, TrangThaiDonHang, TongThanhToan, NgayDatHang FROM donhang WHERE IdNguoiDung = ? ORDER BY NgayDatHang DESC LIMIT 4');
if ($stmtRecentOrders) {
    $stmtRecentOrders->bind_param('i', $userId);
    $stmtRecentOrders->execute();
    $res = $stmtRecentOrders->get_result();
    while ($row = $res->fetch_assoc()) {
        $recentOrders[] = $row;
    }
    $stmtRecentOrders->close();
}

// Đếm đơn theo trạng thái
$stmtStatusCounts = $conn->prepare("SELECT TrangThaiDonHang, COUNT(*) as c FROM donhang WHERE IdNguoiDung = ? GROUP BY TrangThaiDonHang");
if ($stmtStatusCounts) {
    $stmtStatusCounts->bind_param('i', $userId);
    $stmtStatusCounts->execute();
    $res = $stmtStatusCounts->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = $row['TrangThaiDonHang'];
        if (isset($statusCounts[$key])) {
            $statusCounts[$key] = (int)$row['c'];
        }
    }
    $stmtStatusCounts->close();
}

// Lấy số lượng sản phẩm trong giỏ
$stmtCart = $conn->prepare('SELECT COUNT(*) as count FROM giohang WHERE IdNguoiDung = ?');
if ($stmtCart) {
    $stmtCart->bind_param('i', $userId);
    $stmtCart->execute();
    $cartCount = intval($stmtCart->get_result()->fetch_assoc()['count']);
    $stmtCart->close();
}

// Lấy số lượng địa chỉ
$stmtAddresses = $conn->prepare('SELECT COUNT(*) as count FROM DiaChiGiaoHang WHERE IdNguoiDung = ?');
if ($stmtAddresses) {
    $stmtAddresses->bind_param('i', $userId);
    $stmtAddresses->execute();
    $addressCount = intval($stmtAddresses->get_result()->fetch_assoc()['count']);
    $stmtAddresses->close();
}

// Lấy địa chỉ mặc định
$stmtDefaultAddr = $conn->prepare('SELECT Id, TenNguoiNhan, SoDienThoai, DiaChi FROM DiaChiGiaoHang WHERE IdNguoiDung = ? AND LaDiaChiMacDinh = 1 ORDER BY Id DESC LIMIT 1');
$addressRow = null;
$defaultAddressId = null;
if ($stmtDefaultAddr) {
    $stmtDefaultAddr->bind_param('i', $userId);
    $stmtDefaultAddr->execute();
    $addressRow = $stmtDefaultAddr->get_result()->fetch_assoc();
    if ($addressRow) {
        $defaultAddressId = intval($addressRow['Id']);
    }
    $stmtDefaultAddr->close();
}

// Lấy tất cả địa chỉ
$stmtAllAddresses = $conn->prepare('SELECT Id, TenNguoiNhan, SoDienThoai, DiaChi, LaDiaChiMacDinh FROM DiaChiGiaoHang WHERE IdNguoiDung = ? ORDER BY LaDiaChiMacDinh DESC, Id DESC LIMIT 5');
$allAddresses = [];
if ($stmtAllAddresses) {
    $stmtAllAddresses->bind_param('i', $userId);
    $stmtAllAddresses->execute();
    $result = $stmtAllAddresses->get_result();
    while ($row = $result->fetch_assoc()) {
        $allAddresses[] = $row;
    }
    $stmtAllAddresses->close();
}

// Cập nhật thông tin hồ sơ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_update'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {
        $errors[] = 'Vui lòng nhập họ và tên.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if ($phone !== '' && !preg_match('/^[0-9+().\-\s]{8,20}$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE NguoiDung SET HoTen = ?, Email = ?, SoDienThoai = ? WHERE Id = ?");
        if ($stmt) {
            $stmt->bind_param('sssi', $name, $email, $phone, $userId);
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $success = 'Cập nhật thông tin thành công!';
                $user['HoTen'] = $name;
                $user['Email'] = $email;
                $user['SoDienThoai'] = $phone;
            } else {
                $errors[] = 'Không thể cập nhật hồ sơ lúc này. Vui lòng thử lại.';
            }
            $stmt->close();
        }
    }
}

// Cập nhật địa chỉ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_update'])) {
    $address_name = trim($_POST['address_name'] ?? '');
    $address_phone = trim($_POST['address_phone'] ?? '');
    $address_detail = trim($_POST['address_detail'] ?? '');
    $address_id = isset($_POST['address_id']) ? intval($_POST['address_id']) : null;

    if ($address_name === '') {
        $errors[] = 'Vui lòng nhập tên người nhận.';
    }
    if ($address_phone === '') {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    }
    if ($address_detail === '') {
        $errors[] = 'Vui lòng nhập địa chỉ chi tiết.';
    }

    if (empty($errors)) {
        if ($address_id) {
            $stmt = $conn->prepare("UPDATE DiaChiGiaoHang SET TenNguoiNhan = ?, SoDienThoai = ?, DiaChi = ? WHERE Id = ? AND IdNguoiDung = ?");
            if ($stmt) {
                $stmt->bind_param('sssii', $address_name, $address_phone, $address_detail, $address_id, $userId);
                $stmt->execute();
                $stmt->close();
                $success = 'Cập nhật địa chỉ thành công!';
            }
        } else {
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            if ($is_default) {
                $stmtUnset = $conn->prepare("UPDATE DiaChiGiaoHang SET LaDiaChiMacDinh = 0 WHERE IdNguoiDung = ?");
                if ($stmtUnset) {
                    $stmtUnset->bind_param('i', $userId);
                    $stmtUnset->execute();
                    $stmtUnset->close();
                }
            }
            $stmt = $conn->prepare("INSERT INTO DiaChiGiaoHang (IdNguoiDung, TenNguoiNhan, SoDienThoai, DiaChi, LaDiaChiMacDinh) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param('isssi', $userId, $address_name, $address_phone, $address_detail, $is_default);
                $stmt->execute();
                $stmt->close();
                $success = 'Thêm địa chỉ thành công!';
                $addressCount++;
            }
        }
    }
}

$displayName = $user['HoTen'] ?? 'Người dùng';
$userJoinDate = isset($user['NgayTaoTaiKhoan']) ? date('M Y', strtotime($user['NgayTaoTaiKhoan'])) : 'N/A';
$initial = function_exists('mb_substr') ? mb_substr($displayName, 0, 1, 'UTF-8') : substr($displayName, 0, 1);
$rewardPoints = $orderCount * 10; // Giả định: 10 điểm reward mỗi đơn
$statusMap = [
    'ChoXacNhan' => ['label' => 'Chờ xác nhận', 'class' => 'pill-warning'],
    'DaXacNhan' => ['label' => 'Đã xác nhận', 'class' => 'pill-info'],
    'DangGiao' => ['label' => 'Đang giao', 'class' => 'pill-primary'],
    'HoanThanh' => ['label' => 'Hoàn thành', 'class' => 'pill-success'],
    'DaHuy' => ['label' => 'Đã hủy', 'class' => 'pill-danger'],
];

function formatCurrencyVN($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}
?>

<style>
.profile-hero {
    background: linear-gradient(135deg, #f6f8ff 0%, #eef2ff 100%);
    color: #1f2937;
    border: none;
}

.profile-hero__header {
  padding: 25px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.avatar-xxl {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  font-size: 32px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.badge-tier {
    display: inline-block;
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.icon-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: #eef2ff;
    border-radius: 8px;
    font-size: 16px;
    color: #4338ca;
}

.mini-stat {
  font-size: 12px;
  color: #999;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 5px;
}

.mini-stat + .h6 {
  color: #333;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
  margin: 20px 0;
}

.stat-card {
    background: #ffffff;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card-value {
    font-size: 26px;
    font-weight: 800;
    color: #4338ca;
    margin: 10px 0 5px;
}

.stat-card-label {
  font-size: 13px;
  color: #666;
  font-weight: 600;
}

.stat-card-icon {
  font-size: 24px;
  margin-bottom: 10px;
}

.nav-tabs-profile {
  border: none;
  display: flex;
  gap: 10px;
  margin: 0;
  padding: 0;
}

.nav-tabs-profile .nav-link {
  background: #f0f0f0;
  color: #666;
  border: none;
  border-radius: 8px;
  padding: 12px 20px;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.nav-tabs-profile .nav-link:hover {
  background: #e0e0e0;
  color: #333;
}

.nav-tabs-profile .nav-link.active {
    background: #4338ca;
    color: white;
}

.form-control, .form-select {
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 12px;
  font-size: 14px;
}

.form-control:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
}

.form-label {
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.address-card {
  background: #f8f9fa;
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 15px;
  margin-bottom: 15px;
  position: relative;
  transition: all 0.3s ease;
}

.address-card:hover {
  border-color: #667eea;
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
}

.address-card.default {
  border-color: #667eea;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.address-badge {
  display: inline-block;
  background: #667eea;
  color: white;
  padding: 3px 10px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  margin-top: 8px;
}

.address-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

.address-actions button {
  padding: 6px 12px;
  font-size: 12px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-edit-addr {
  background: #e3f2fd;
  color: #1976d2;
}

.btn-edit-addr:hover {
  background: #1976d2;
  color: white;
}

.btn-delete-addr {
  background: #ffebee;
  color: #c62828;
}

.btn-delete-addr:hover {
  background: #c62828;
  color: white;
}

.quick-actions-sidebar {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px;
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 10px;
  text-decoration: none;
  color: #333;
  transition: all 0.3s ease;
  font-weight: 600;
}

.quick-action-btn:hover {
  background: #f5f5f5;
  border-color: #667eea;
  transform: translateX(5px);
}

.quick-action-icon {
  font-size: 20px;
  color: #667eea;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f0f0;
  border-radius: 8px;
}

.support-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 12px;
  text-align: center;
}

.support-card h6 {
  margin-bottom: 10px;
  font-weight: 700;
}

.support-card p {
  font-size: 13px;
  margin-bottom: 15px;
  opacity: 0.9;
}

.support-card a {
  display: inline-block;
  background: white;
  color: #667eea;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
}

.support-card a:hover {
  background: #f0f0f0;
}

.account-status-list {
  display: grid;
  gap: 10px;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: #f5f5f5;
  border-radius: 8px;
  font-size: 14px;
}

.status-check {
  width: 20px;
  height: 20px;
  background: #4caf50;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
}

.status-pending {
  background: #ffc107;
  color: white;
}

.tab-content-section {
  display: none;
}

.tab-content-section.active {
  display: block;
}

.alert {
  border-radius: 8px;
  border: none;
  padding: 15px;
  margin-bottom: 20px;
}

.alert-success {
  background: #d4edda;
  color: #155724;
}

.alert-danger {
  background: #f8d7da;
  color: #721c24;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.summary-card {
    padding: 16px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.summary-card h6 {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.summary-value {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}

.summary-sub {
    color: #6b7280;
    font-size: 12px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
}

.pill-primary { background: #e0e7ff; color: #4338ca; border-color: #c7d2fe; }
.pill-success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.pill-warning { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.pill-danger  { background: #fee2e2; color: #b91c1c; border-color: #fecdd3; }
.pill-info    { background: #e0f2fe; color: #075985; border-color: #bae6fd; }

.activity-timeline {
    display: grid;
    gap: 12px;
}

.activity-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.activity-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    margin-top: 6px;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
}

.activity-meta {
    font-size: 12px;
    color: #6b7280;
}

.card-empty {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    padding: 24px;
    border-radius: 12px;
    text-align: center;
    color: #475569;
}

.tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #f1f5f9;
    border-radius: 10px;
    font-size: 12px;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.meta-list {
    display: grid;
    gap: 10px;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #475569;
}

.meta-label { color: #6b7280; }
.meta-value { font-weight: 700; color: #111827; }

.status-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.pill-link {
    text-decoration: none;
    color: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .nav-tabs-profile {
    flex-wrap: wrap;
  }
}
</style>

<div class="container-fluid py-5" style="background: #f8f9fa;">
    <div class="container">
        <div class="row g-4">
            <!-- Left Sidebar: Profile Card + Quick Stats -->
            <div class="col-lg-4">
                <div class="card profile-hero shadow-sm mb-3">
                    <div class="profile-hero__header d-flex align-items-center gap-3">
                        <div class="avatar-xxl bg-gradient text-white d-flex align-items-center justify-content-center fw-bold">
                            <?php echo htmlspecialchars(strtoupper($initial)); ?>
                        </div>
                        <div>
                            <div class="text-white-50 small mb-1">ID #<?php echo $userId; ?></div>
                            <h5 class="text-white mb-1"><?php echo htmlspecialchars($displayName); ?></h5>
                            <span class="badge-tier">Thành viên <?php echo $userJoinDate; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="icon-box me-3"><i class="fa-solid fa-envelope"></i></span>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="text-muted small">Email</div>
                                <div class="fw-semibold text-truncate" style="font-size: 12px;"><?php echo htmlspecialchars($user['Email'] ?? ''); ?></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="icon-box me-3"><i class="fa-solid fa-phone"></i></span>
                            <div>
                                <div class="text-muted small">Số điện thoại</div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($user['SoDienThoai'] ?? 'Chưa cập nhật'); ?></div>
                            </div>
                        </div>
                        <hr class="text-white-50 my-3">
                        <div class="stats-grid">
                            <div class="stat-card" style="background: #f0f0f0;">
                                <div class="stat-card-icon">📦</div>
                                <div class="stat-card-value"><?php echo $orderCount; ?></div>
                                <div class="stat-card-label">Đơn hàng</div>
                            </div>
                            <div class="stat-card" style="background: #f0f0f0;">
                                <div class="stat-card-icon">🛒</div>
                                <div class="stat-card-value"><?php echo $cartCount; ?></div>
                                <div class="stat-card-label">Giỏ hàng</div>
                            </div>
                            <div class="stat-card" style="background: #f0f0f0;">
                                <div class="stat-card-icon">⭐</div>
                                <div class="stat-card-value"><?php echo $rewardPoints; ?></div>
                                <div class="stat-card-label">Điểm</div>
                            </div>
                            <div class="stat-card" style="background: #f0f0f0;">
                                <div class="stat-card-icon">📍</div>
                                <div class="stat-card-value"><?php echo $addressCount; ?></div>
                                <div class="stat-card-label">Địa chỉ</div>
                            </div>
                        </div>
                        <hr class="text-white-50 my-3">
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-light" href="change_password.php">
                                <i class="fa-solid fa-lock me-2"></i>Đổi mật khẩu
                            </a>
                            <a class="btn btn-light text-dark fw-600" href="logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3 fw-bold">Truy cập nhanh</h6>
                        <div class="quick-actions-sidebar">
                            <a href="cart.php" class="quick-action-btn">
                                <span class="quick-action-icon">🛍️</span>
                                <span>Giỏ hàng</span>
                            </a>
                            <a href="orders.php" class="quick-action-btn">
                                <span class="quick-action-icon">📋</span>
                                <span>Đơn hàng</span>
                            </a>
                            <a href="best_sellers.php" class="quick-action-btn">
                                <span class="quick-action-icon">🔥</span>
                                <span>Sản phẩm bán chạy</span>
                            </a>
                            <a href="index.php" class="quick-action-btn">
                                <span class="quick-action-icon">🏠</span>
                                <span>Trang chủ</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Tóm tắt tài khoản</h6>
                        <div class="meta-list">
                            <div class="meta-row">
                                <span class="meta-label">Hạng</span>
                                <span class="meta-value"><?php echo htmlspecialchars($userTier); ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Tổng chi tiêu</span>
                                <span class="meta-value"><?php echo formatCurrencyVN($userTotalSpent); ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Đơn đang mở</span>
                                <span class="meta-value"><?php echo $openOrderCount; ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Lần đăng nhập cuối</span>
                                <span class="meta-value" style="font-size: 12px; font-weight: 600; color: #475569;">
                                    <?php echo htmlspecialchars($lastLoginDisplay); ?>
                                </span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Ngày đăng ký</span>
                                <span class="meta-value" style="font-size: 12px; font-weight: 600; color: #475569;">
                                    <?php echo htmlspecialchars($accountCreatedDisplay); ?>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="tag-chip">Trạng thái: <strong><?php echo htmlspecialchars($userStatus); ?></strong></span>
                            <span class="tag-chip">Đơn mở: <strong><?php echo $openOrderCount; ?></strong></span>
                            <span class="tag-chip">Điểm: <strong><?php echo $rewardPoints; ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- Support Card -->
                <div class="support-card mt-3">
                    <h6><i class="fa-solid fa-headset me-2"></i>Cần giúp đỡ?</h6>
                    <p>Liên hệ với đội hỗ trợ khách hàng 24/7 để được hỗ trợ nhanh chóng.</p>
                    <a href="mailto:support@doanchuyennghiep.com">
                        <i class="fa-solid fa-envelope me-2"></i>Liên hệ
                    </a>
                </div>
            </div>

            <!-- Right Content: Tabs & Forms -->
            <div class="col-lg-8 d-flex flex-column gap-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="summary-grid mb-3">
                            <div class="summary-card">
                                <h6>Đơn hàng</h6>
                                <div class="summary-value"><?php echo $orderCount; ?></div>
                                <div class="summary-sub">Tổng đơn đã tạo</div>
                            </div>
                            <div class="summary-card">
                                <h6>Đơn đang xử lý</h6>
                                <div class="summary-value"><?php echo $openOrderCount; ?></div>
                                <div class="summary-sub">Chờ xác nhận / đang giao</div>
                            </div>
                            <div class="summary-card">
                                <h6>Chi tiêu</h6>
                                <div class="summary-value"><?php echo formatCurrencyVN($userTotalSpent); ?></div>
                                <div class="summary-sub">Tích lũy mua hàng</div>
                            </div>
                            <div class="summary-card">
                                <h6>Đăng nhập cuối</h6>
                                <div class="summary-value" style="font-size: 16px;"><?php echo htmlspecialchars($lastLoginDisplay); ?></div>
                                <div class="summary-sub">Tình trạng: <?php echo htmlspecialchars($userStatus); ?></div>
                            </div>
                        </div>

                        <div class="status-chips">
                            <a class="status-chip pill-link" href="orders.php?status=ChoXacNhan">Chờ xác nhận: <?php echo $statusCounts['ChoXacNhan']; ?></a>
                            <a class="status-chip pill-link" href="orders.php?status=DaXacNhan">Đã xác nhận: <?php echo $statusCounts['DaXacNhan']; ?></a>
                            <a class="status-chip pill-link" href="orders.php?status=DangGiao">Đang giao: <?php echo $statusCounts['DangGiao']; ?></a>
                            <a class="status-chip pill-link" href="orders.php?status=HoanThanh">Hoàn thành: <?php echo $statusCounts['HoanThanh']; ?></a>
                            <a class="status-chip pill-link" href="orders.php?status=DaHuy">Đã hủy: <?php echo $statusCounts['DaHuy']; ?></a>
                        </div>

                        <ul class="nav nav-tabs-profile" role="tablist">
                            <li role="presentation">
                                <button class="nav-link active" id="info-tab" type="button" data-bs-toggle="tab" data-bs-target="#info-content" role="tab">
                                    <i class="fa-solid fa-id-card me-2"></i>Thông tin cá nhân
                                </button>
                            </li>
                            <li role="presentation">
                                <button class="nav-link" id="address-tab" type="button" data-bs-toggle="tab" data-bs-target="#address-content" role="tab">
                                    <i class="fa-solid fa-map-location-dot me-2"></i>Địa chỉ giao hàng
                                </button>
                            </li>
                            <li role="presentation">
                                <button class="nav-link" id="security-tab" type="button" data-bs-toggle="tab" data-bs-target="#security-content" role="tab">
                                    <i class="fa-solid fa-shield-halved me-2"></i>Bảo mật
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- TAB 1: Thông tin cá nhân -->
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="info-content" role="tabpanel" tabindex="0">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom py-3">
                                <h5 class="mb-0">
                                    <i class="fa-solid fa-user me-2"></i>Cập nhật thông tin tài khoản
                                </h5>
                                <small class="text-muted d-block mt-1">Quản lý thông tin cá nhân của bạn</small>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                                        <ul class="mb-0">
                                            <?php foreach ($errors as $err): ?>
                                                <li><?php echo htmlspecialchars($err); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($success): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="fa-solid fa-check-circle me-2"></i>
                                        <?php echo htmlspecialchars($success); ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" class="row g-4">
                                    <input type="hidden" name="profile_update" value="1">

                                    <div class="col-md-12">
                                        <label class="form-label"><i class="fa-solid fa-user me-2"></i>Họ và tên <span style="color: red;">*</span></label>
                                        <input type="text" name="name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($user['HoTen'] ?? ''); ?>" required>
                                        <small class="text-muted">Tên của bạn sẽ hiển thị trong đơn hàng</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label"><i class="fa-solid fa-envelope me-2"></i>Email <span style="color: red;">*</span></label>
                                        <input type="email" name="email" class="form-control form-control-lg" value="<?php echo htmlspecialchars($user['Email'] ?? ''); ?>" required>
                                        <small class="text-muted">Dùng để đăng nhập và nhận thông báo</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label"><i class="fa-solid fa-phone me-2"></i>Số điện thoại</label>
                                        <input type="text" name="phone" class="form-control form-control-lg" value="<?php echo htmlspecialchars($user['SoDienThoai'] ?? ''); ?>" placeholder="Ví dụ: 0987654321">
                                        <small class="text-muted">Hỗ trợ xác nhận đơn hàng và giao hàng</small>
                                    </div>

                                    <div class="col-12 d-flex justify-content-end gap-2 pt-3">
                                        <button type="reset" class="btn btn-outline-secondary btn-lg">
                                            <i class="fa-solid fa-undo me-2"></i>Hủy
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fa-solid fa-check me-2"></i>Lưu thay đổi
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: Địa chỉ giao hàng -->
                    <div class="tab-pane fade" id="address-content" role="tabpanel" tabindex="0">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">
                                        <i class="fa-solid fa-map-location-dot me-2"></i>Địa chỉ giao hàng
                                    </h5>
                                    <small class="text-muted d-block mt-1">Quản lý tất cả địa chỉ giao hàng của bạn</small>
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#add-address-form">
                                    <i class="fa-solid fa-plus me-2"></i>Thêm địa chỉ
                                </button>
                            </div>
                            <div class="card-body">
                                <!-- Add Address Form -->
                                <div id="add-address-form" class="collapse mb-4">
                                    <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                                        <h6 class="mb-3">Thêm địa chỉ giao hàng mới</h6>
                                        <form method="post" class="row g-3">
                                            <input type="hidden" name="address_update" value="1">

                                            <div class="col-md-6">
                                                <label class="form-label">Tên người nhận <span style="color: red;">*</span></label>
                                                <input type="text" name="address_name" class="form-control" required>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Số điện thoại <span style="color: red;">*</span></label>
                                                <input type="text" name="address_phone" class="form-control" required>
                                            </div>

                                            <div class="col-12">
                                                <label class="form-label">Địa chỉ chi tiết <span style="color: red;">*</span></label>
                                                <textarea name="address_detail" class="form-control" rows="2" placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành" required></textarea>
                                            </div>

                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_default" id="is_default_new">
                                                    <label class="form-check-label" for="is_default_new">
                                                        Đặt làm địa chỉ mặc định
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="col-12 d-flex justify-content-end gap-2">
                                                <button type="reset" class="btn btn-outline-secondary">Hủy</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fa-solid fa-plus me-2"></i>Thêm địa chỉ
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Display Addresses -->
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="mb-3">Danh sách địa chỉ (<?php echo count($allAddresses); ?>)</h6>
                                    </div>
                                    <?php if (count($allAddresses) > 0): ?>
                                        <?php foreach ($allAddresses as $addr): ?>
                                            <div class="col-12">
                                                <div class="address-card <?php echo $addr['LaDiaChiMacDinh'] ? 'default' : ''; ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div style="flex: 1;">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($addr['TenNguoiNhan']); ?></h6>
                                                            <p class="mb-1" style="font-size: 13px; color: #666;">
                                                                <i class="fa-solid fa-phone me-2"></i>
                                                                <?php echo htmlspecialchars($addr['SoDienThoai']); ?>
                                                            </p>
                                                            <p class="mb-0" style="font-size: 13px; color: #666;">
                                                                <i class="fa-solid fa-map-location-dot me-2"></i>
                                                                <?php echo htmlspecialchars(substr($addr['DiaChi'], 0, 60) . (strlen($addr['DiaChi']) > 60 ? '...' : '')); ?>
                                                            </p>
                                                            <?php if ($addr['LaDiaChiMacDinh']): ?>
                                                                <span class="address-badge">Địa chỉ mặc định</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="address-actions">
                                                            <button type="button" class="btn-edit-addr" data-bs-toggle="modal" data-bs-target="#editAddressModal" onclick="editAddress(<?php echo $addr['Id']; ?>, '<?php echo htmlspecialchars($addr['TenNguoiNhan'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($addr['SoDienThoai'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($addr['DiaChi'], ENT_QUOTES); ?>')">
                                                                <i class="fa-solid fa-pen-to-square me-1"></i>Sửa
                                                            </button>
                                                            <button type="button" class="btn-delete-addr" onclick="return confirm('Bạn có chắc chắn muốn xóa?')">
                                                                <i class="fa-solid fa-trash me-1"></i>Xóa
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div style="text-align: center; padding: 40px 20px; background: #f0f0f0; border-radius: 10px;">
                                                <p style="color: #999; margin-bottom: 15px;">Bạn chưa có địa chỉ giao hàng nào</p>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#add-address-form">
                                                    <i class="fa-solid fa-plus me-2"></i>Thêm địa chỉ đầu tiên
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Bảo mật -->
                    <div class="tab-pane fade" id="security-content" role="tabpanel" tabindex="0">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white border-bottom py-3">
                                <h5 class="mb-0">
                                    <i class="fa-solid fa-shield-halved me-2"></i>Cài đặt bảo mật
                                </h5>
                                <small class="text-muted d-block mt-1">Bảo vệ tài khoản của bạn</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; text-align: center; height: 100%;">
                                            <div style="font-size: 36px; margin-bottom: 15px;">🔐</div>
                                            <h6 class="mb-2">Đổi mật khẩu</h6>
                                            <p style="font-size: 13px; margin-bottom: 15px; opacity: 0.9;">Thay đổi mật khẩu để bảo vệ tài khoản của bạn. Khuyến nghị đổi mật khẩu định kỳ.</p>
                                            <a href="change_password.php" class="btn btn-light" style="color: #667eea; font-weight: 600;">
                                                Đổi mật khẩu
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div style="background: #e8f5e9; color: #2e7d32; padding: 25px; border-radius: 12px; text-align: center; height: 100%; border: 2px solid #4caf50;">
                                            <div style="font-size: 36px; margin-bottom: 15px;">✅</div>
                                            <h6 class="mb-2">Xác thực Email</h6>
                                            <p style="font-size: 13px; margin-bottom: 15px;">Email của bạn đã được xác thực và hoạt động bình thường.</p>
                                            <span style="background: #4caf50; color: white; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                <i class="fa-solid fa-check me-1"></i>Đã xác thực
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <h6 class="mb-3">Trạng thái bảo mật tài khoản</h6>
                                    <div class="account-status-list">
                                        <div class="status-item">
                                            <span class="status-check">✓</span>
                                            <span>Tài khoản đang hoạt động</span>
                                        </div>
                                        <div class="status-item">
                                            <span class="status-check">✓</span>
                                            <span>Email xác thực</span>
                                        </div>
                                        <div class="status-item">
                                            <span class="status-check">✓</span>
                                            <span>Mật khẩu được bảo vệ</span>
                                        </div>
                                        <div class="status-item">
                                            <span class="status-check">✓</span>
                                            <span>Phiên đăng nhập an toàn</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info mt-4 mb-0">
                                    <i class="fa-solid fa-circle-info me-2"></i>
                                    <strong>Lưu ý:</strong> Nếu bạn cần đăng xuất khỏi tất cả thiết bị, vui lòng đổi mật khẩu để vô hiệu tất cả phiên cũ. Đây là cách an toàn nhất để bảo vệ tài khoản của bạn.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Hoạt động gần đây</h5>
                            <small class="text-muted">4 đơn hàng mới nhất của bạn</small>
                        </div>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">Xem lịch sử</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentOrders) > 0): ?>
                            <div class="activity-timeline">
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                        $statusKey = $order['TrangThaiDonHang'] ?? '';
                                        $statusInfo = $statusMap[$statusKey] ?? ['label' => $statusKey ?: 'N/A', 'class' => 'pill-info'];
                                        $total = isset($order['TongThanhToan']) ? formatCurrencyVN($order['TongThanhToan']) : 'N/A';
                                        $orderDate = isset($order['NgayDatHang']) ? date('d/m H:i', strtotime($order['NgayDatHang'])) : '';
                                    ?>
                                    <div class="activity-item">
                                        <span class="activity-dot"></span>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <div>
                                                    <div class="fw-bold">#<?php echo htmlspecialchars($order['MaDonHang'] ?? $order['Id']); ?></div>
                                                    <div class="activity-meta">Đặt lúc <?php echo htmlspecialchars($orderDate); ?></div>
                                                </div>
                                                <div class="d-flex flex-column align-items-end" style="gap: 6px;">
                                                    <span class="status-pill <?php echo $statusInfo['class']; ?>"><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                                                    <span class="fw-semibold" style="color: #111827;"><?php echo $total; ?></span>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <a class="btn btn-sm btn-outline-secondary" href="order_detail.php?id=<?php echo intval($order['Id']); ?>">Chi tiết</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="card-empty">
                                Chưa có đơn hàng nào. Bắt đầu mua sắm để xem hoạt động gần đây.
                                <div class="mt-3">
                                    <a href="best_sellers.php" class="btn btn-primary btn-sm">Khám phá sản phẩm hot</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0"><i class="fa-solid fa-gift me-2"></i>Ưu đãi & gợi ý</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="tag-chip">Đơn mở: <strong><?php echo $openOrderCount; ?></strong></span>
                            <span class="tag-chip">Hạng: <strong><?php echo htmlspecialchars($userTier); ?></strong></span>
                            <span class="tag-chip">Điểm: <strong><?php echo $rewardPoints; ?></strong></span>
                        </div>
                        <div class="meta-list mb-3">
                            <div class="meta-row">
                                <span class="meta-label">Chi tiêu tháng này</span>
                                <span class="meta-value"><?php echo formatCurrencyVN($userTotalSpent); ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Địa chỉ đã lưu</span>
                                <span class="meta-value"><?php echo $addressCount; ?></span>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary" href="best_sellers.php"><i class="fa-solid fa-fire me-2"></i>Xem sản phẩm bán chạy</a>
                            <a class="btn btn-outline-primary" href="orders.php"><i class="fa-solid fa-receipt me-2"></i>Theo dõi đơn hàng</a>
                            <a class="btn btn-outline-secondary" href="cart.php"><i class="fa-solid fa-cart-shopping me-2"></i>Tiếp tục mua sắm</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editAddress(addressId, name, phone, address) {
    document.getElementById('edit_address_id').value = addressId;
    document.getElementById('edit_address_name').value = name;
    document.getElementById('edit_address_phone').value = phone;
    document.getElementById('edit_address_detail').value = address;
}

// Fix Bootstrap tab navigation
document.querySelectorAll('.nav-tabs-profile .nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs
        document.querySelectorAll('.nav-tabs-profile .nav-link').forEach(t => {
            t.classList.remove('active');
        });
        
        // Hide all tab panes
        document.querySelectorAll('.tab-content .tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Show corresponding tab pane
        const target = document.querySelector(this.getAttribute('data-bs-target'));
        if (target) {
            target.classList.add('show', 'active');
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
