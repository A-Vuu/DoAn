<?php
// Kiểm tra cấu trúc database NovaWear1
require_once 'config.php';

echo "<h2>DATABASE: NovaWear1</h2>";
echo "<h3>Danh sách các bảng:</h3>";

$tables = mysqli_query($conn, "SHOW TABLES");
$tableList = [];

while ($row = mysqli_fetch_array($tables)) {
    $tableName = $row[0];
    $tableList[] = $tableName;
    echo "<h4 style='color: blue;'>Bảng: $tableName</h4>";
    
    // Lấy cấu trúc từng bảng
    $columns = mysqli_query($conn, "DESCRIBE $tableName");
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($col = mysqli_fetch_assoc($columns)) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Tổng số bảng: " . count($tableList) . "</h3>";
echo "<p>Danh sách: " . implode(", ", $tableList) . "</p>";
?>
