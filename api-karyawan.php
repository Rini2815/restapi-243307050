<?php
mysqli_report(MYSQLI_REPORT_OFF); // error ditangani manual, bukan lewat exception
require_once "connection.php";


// kirim response JSON sekaligus mengatur HTTP status code
function sendResponse($status, $message, $data = null) {
    http_response_code($status);
    header('Content-Type: application/json');
    $response = array('status' => $status, 'message' => $message);
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Memastikan method HTTP sesuai
function checkMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendResponse(405, "Method Not Allowed, gunakan $method");
    }
}

// ambil dan validasi parameter id dari URL
function getValidId() {
    if (!isset($_GET['id']) || $_GET['id'] === '') {
        sendResponse(400, 'Parameter id wajib diisi');
    }
    if (!ctype_digit($_GET['id'])) {
        sendResponse(400, 'Parameter id harus berupa angka');
    }
    return (int)$_GET['id'];
}

// cek apakah karyawan dengan id tersebut ada
function karyawanExists($id) {
    global $connect;
    $stmt = $connect->prepare("SELECT id FROM tbl_karyawan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

// validasi data nama, jenis_kelamin, handphone, alamat dari body POST
function validateKaryawanInput() {
    $fields = array('nama', 'jenis_kelamin', 'handphone', 'alamat');
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($_POST[$f]) || trim($_POST[$f]) === '') {
            $missing[] = $f;
        }
    }
    if ($missing) {
        sendResponse(400, 'Parameter wajib diisi: ' . implode(', ', $missing));
    }

    $nama = trim($_POST['nama']);
    $jk = trim($_POST['jenis_kelamin']);
    $hp = trim($_POST['handphone']);
    $alamat = trim($_POST['alamat']);

    if (strlen($nama) > 50) {
        sendResponse(422, 'Nama maksimal 50 karakter');
    }
    if (!in_array($jk, array('Laki-laki', 'Perempuan'), true)) {
        sendResponse(422, "jenis_kelamin harus 'Laki-laki' atau 'Perempuan'");
    }
    if (!ctype_digit($hp) || strlen($hp) > 15) {
        sendResponse(422, 'handphone harus berupa angka, maksimal 15 digit');
    }
    if (strlen($alamat) > 100) {
        sendResponse(422, 'Alamat maksimal 100 karakter');
    }

    return array('nama' => $nama, 'jenis_kelamin' => $jk, 'handphone' => $hp, 'alamat' => $alamat);
}

// ===== Router =====
$allowed = array('getKaryawan', 'insertKaryawan', 'getKaryawanById', 'updateKaryawan', 'deleteKaryawan');

if (!isset($_GET['function']) || $_GET['function'] === '') {
    sendResponse(400, 'Parameter function wajib diisi');
}
if (!in_array($_GET['function'], $allowed, true)) {
    sendResponse(404, 'Function Not Found');
}
$_GET['function']();

// fungsi ambil data karyawan
function getKaryawan() {
    global $connect;
    checkMethod('GET');
    $data = [];
    $query = $connect->query("SELECT * FROM tbl_karyawan");
    if (!$query) {
        sendResponse(500, 'Query Failed');
    }
    while ($row = mysqli_fetch_object($query)) {
        $data[] = $row;
    }
    sendResponse(200, 'Success', $data);
}

// fungsi tambah data karyawan
function insertKaryawan() {
    global $connect;
    checkMethod('POST');

    if (!isset($_POST['id']) || $_POST['id'] === '') {
        sendResponse(400, 'Parameter wajib diisi: id');
    }
    if (!ctype_digit($_POST['id'])) {
        sendResponse(400, 'Parameter id harus berupa angka');
    }
    $id = (int)$_POST['id'];
    $in = validateKaryawanInput();

    if (karyawanExists($id)) {
        sendResponse(409, "ID $id sudah digunakan");
    }

    $stmt = $connect->prepare("INSERT INTO tbl_karyawan (id, nama, jenis_kelamin, handphone, alamat) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $id, $in['nama'], $in['jenis_kelamin'], $in['handphone'], $in['alamat']);
    if ($stmt->execute()) {
        sendResponse(201, 'Insert Success');
    }
    sendResponse(500, 'Insert Failed.');
}

// fungsi ambil data karyawan by id
function getKaryawanById() {
    global $connect;
    checkMethod('GET');
    $id = getValidId();

    $stmt = $connect->prepare("SELECT * FROM tbl_karyawan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = mysqli_fetch_object($result)) {
        $data[] = $row;
    }
    if ($data) {
        sendResponse(200, 'Success', $data);
    }
    sendResponse(404, 'No Data Found');
}

// fungsi perbarui data karyawan
function updateKaryawan() {
    global $connect;
    checkMethod('POST');
    $id = getValidId();

    if (!karyawanExists($id)) {
        sendResponse(404, "Data dengan ID $id tidak ditemukan");
    }
    $in = validateKaryawanInput();

    $stmt = $connect->prepare("UPDATE tbl_karyawan SET nama = ?, jenis_kelamin = ?, handphone = ?, alamat = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $in['nama'], $in['jenis_kelamin'], $in['handphone'], $in['alamat'], $id);
    if ($stmt->execute()) {
        sendResponse(200, 'Update Success');
    }
    sendResponse(500, 'Update Failed');
}

// fungsi hapus data karyawan
function deleteKaryawan() {
    global $connect;
    checkMethod('DELETE');
    $id = getValidId();

    if (!karyawanExists($id)) {
        sendResponse(404, "Data dengan ID $id tidak ditemukan");
    }

    $stmt = $connect->prepare("DELETE FROM tbl_karyawan WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        sendResponse(200, 'Delete Success');
    }
    sendResponse(500, 'Delete Fail.');
}
?>