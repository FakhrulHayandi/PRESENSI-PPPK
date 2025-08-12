<?php
// db_config.php
// Isi dengan kredensial MySQL-mu sebelum digunakan
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'presensi_pppk';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>"Failed to connect to MySQL: " . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');
?>