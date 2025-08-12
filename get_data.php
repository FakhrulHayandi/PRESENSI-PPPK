<?php
// get_data.php
header('Content-Type: application/json');
require 'db_config.php';

// optional date filter: expected format dd/mm/YYYY
$date = isset($_GET['date']) ? $_GET['date'] : null;
// optional month filter: mm-YYYY
$month = isset($_GET['month']) ? $_GET['month'] : null;

if ($date) {
    $stmt = $mysqli->prepare('SELECT * FROM absensi WHERE tanggal = ? ORDER BY id DESC');
    $stmt->bind_param('s', $date);
} elseif ($month) {
    $like = '%/' . $month;
    $stmt = $mysqli->prepare('SELECT * FROM absensi WHERE tanggal LIKE ? ORDER BY id DESC');
    $stmt->bind_param('s', $like);
} else {
    $today = date('d/m/Y');
    $stmt = $mysqli->prepare('SELECT * FROM absensi WHERE tanggal = ? ORDER BY id DESC');
    $stmt->bind_param('s', $today);
}

$stmt->execute();
$res = $stmt->get_result();
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode(['ok'=>true,'data'=>$data]);
?>