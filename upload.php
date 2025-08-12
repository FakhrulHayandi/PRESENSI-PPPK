<?php
// upload.php
header('Content-Type: application/json');
require 'db_config.php';

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
    exit;
}

// Helper to respond
function fail($msg){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// Get fields
$nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
$nip = isset($_POST['nip']) ? trim($_POST['nip']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Hadir';
$mode = isset($_POST['mode']) ? trim($_POST['mode']) : 'masuk'; // 'masuk' or 'pulang'
// latitude/longitude may be provided (optional) as strings
$lat = isset($_POST['lat']) && $_POST['lat']!=='' ? trim($_POST['lat']) : null;
$lon = isset($_POST['lon']) && $_POST['lon']!=='' ? trim($_POST['lon']) : null;

// validate
if ($nama === '' || $nip === '') {
    fail('Nama dan NIP wajib diisi');
}

// handle photo: either file upload 'photo' or base64 string 'photoBase64'
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$photoFilename = null;
if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    $allowed = ['image/jpeg','image/png','image/jpg'];
    $mime = mime_content_type($_FILES['photo']['tmp_name']);
    if (!in_array($mime, $allowed)) {
        fail('Tipe file tidak didukung: ' . $mime);
    }
    $ext = $mime === 'image/png' ? '.png' : '.jpg';
    $photoFilename = uniqid('photo_') . $ext;
    $dest = $uploadDir . '/' . $photoFilename;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        fail('Gagal menyimpan file');
    }
} elseif (!empty($_POST['photoBase64'])) {
    $b64 = $_POST['photoBase64'];
    if (preg_match('/^data:image\/(jpeg|png);base64,/', $b64)) {
        $parts = explode(',', $b64, 2);
        if (count($parts) === 2) {
            $meta = $parts[0];
            $data = $parts[1];
            $isPng = strpos($meta, 'png') !== false;
            $ext = $isPng ? '.png' : '.jpg';
            $photoFilename = uniqid('photo_') . $ext;
            $dest = $uploadDir . '/' . $photoFilename;
            $decoded = base64_decode($data);
            if ($decoded === false) fail('Invalid base64 image');
            if (file_put_contents($dest, $decoded) === false) fail('Gagal menyimpan file base64');
        }
    } else {
        fail('photoBase64 harus berupa data URI image');
    }
}

// date handling
$date = date('d/m/Y');
$time = date('H:i:s');

// Database: if mode == 'masuk' then insert or update existing row for same nama+nip+date.
// if mode == 'pulang' then update jamPulang, photoPulang, latPulang, lonPulang for today's row.
if ($mode === 'masuk') {
    // check existing row for today for same nama+nip
    $stmt = $mysqli->prepare("SELECT id FROM absensi WHERE nama=? AND nip=? AND tanggal=? LIMIT 1");
    $stmt->bind_param('sss', $nama, $nip, $date);
    $stmt->execute();
    $stmt->bind_result($existingId);
    $stmt->fetch();
    $stmt->close();

    if ($existingId) {
        // update masuk fields
        $sql = "UPDATE absensi SET jam_masuk=?, status=?, keterangan=COALESCE(NULLIF(keterangan,''), ?), foto_masuk=COALESCE(?, foto_masuk), lat_masuk=COALESCE(?, lat_masuk), lon_masuk=COALESCE(?, lon_masuk) WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $keterangan = ($status==='Terlambat' ? 'Terlambat' : '');
        $stmt->bind_param('ssssssi', $time, $status, $keterangan, $photoFilename, $lat, $lon, $existingId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) fail('DB update gagal: ' . $mysqli->error);
        echo json_encode(['ok'=>true,'mode'=>'masuk','updated'=>true]);
        exit;
    } else {
        $sql = "INSERT INTO absensi (nama,nip,status,keterangan,foto_masuk,lat_masuk,lon_masuk,tanggal,jam_masuk,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())";
        $stmt = $mysqli->prepare($sql);
        $keterangan = ($status==='Terlambat' ? 'Terlambat' : '');
        $stmt->bind_param('sssssssss', $nama, $nip, $status, $keterangan, $photoFilename, $lat, $lon, $date, $time);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) fail('DB insert gagal: ' . $mysqli->error);
        echo json_encode(['ok'=>true,'mode'=>'masuk','inserted'=>true]);
        exit;
    }
} elseif ($mode === 'pulang') {
    // find today's row
    $stmt = $mysqli->prepare("SELECT id FROM absensi WHERE nama=? AND nip=? AND tanggal=? LIMIT 1");
    $stmt->bind_param('sss', $nama, $nip, $date);
    $stmt->execute();
    $stmt->bind_result($existingId);
    $stmt->fetch();
    $stmt->close();
    if (!$existingId) {
        // no masuk found, create new row with jam_pulang only
        $sql = "INSERT INTO absensi (nama,nip,status,jam_pulang,foto_pulang,lat_pulang,lon_pulang,tanggal,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssssssss', $nama, $nip, $status, $time, $photoFilename, $lat, $lon, $date);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) fail('DB insert pulang gagal: ' . $mysqli->error);
        echo json_encode(['ok'=>true,'mode'=>'pulang','inserted'=>true]);
        exit;
    } else {
        $sql = "UPDATE absensi SET jam_pulang=?, foto_pulang=COALESCE(?, foto_pulang), lat_pulang=COALESCE(?, lat_pulang), lon_pulang=COALESCE(?, lon_pulang) WHERE id=?";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ssssi', $time, $photoFilename, $lat, $lon, $existingId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) fail('DB update pulang gagal: ' . $mysqli->error);
        echo json_encode(['ok'=>true,'mode'=>'pulang','updated'=>true]);
        exit;
    }
} else {
    fail('mode tidak dikenal');
}
?>