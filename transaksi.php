<?php
session_start();

// --- KONFIGURASI DATABASE ---
$servername = "localhost";
$username_db = "root";       // Default XAMPP/WAMP
$password_db = "";           // Default XAMPP/WAMP kosong
$dbname = "karang_taruna";  // Nama database kamu (PASTIKAN KONSISTEN!)

// Buat koneksi
$conn = new mysqli($servername, $username_db, $password_db, $dbname);

// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inisialisasi variabel untuk menampung error yang akan ditampilkan di HTML
$php_error_message = '';
$conn_status = false; // Default status koneksi adalah gagal

if ($conn->connect_error) {
    $php_error_message = "Koneksi database GAGAL: " . $conn->connect_error;
    error_log($php_error_message);
    // Tidak perlu exit di sini, agar sisa halaman tetap bisa dirender meskipun tanpa data dari DB
} else {
    $conn_status = true; // Koneksi berhasil
}

// Cek login. Jika belum login, redirect ke login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- FUNGSI DARI utils.php ---
function isActive($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage === $pageName) {
        return 'active';
    }
    return '';
}

function sanitize_input_basic($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}
// --- AKHIR FUNGSI ---


$transaksiData = [];
$success = '';
$error = '';

// Ambil pesan sukses/error dari URL jika ada
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'added':
            $success = "Transaksi berhasil ditambahkan!";
            break;
        case 'deleted':
            $success = "Transaksi berhasil dihapus!";
            break;
        case 'updated':
            $success = "Transaksi berhasil diperbarui!";
            break;
        case 'upload_error':
            $error = "Gagal mengunggah file bukti. " . ($_GET['msg'] ?? 'Ukuran atau tipe file tidak sesuai.');
            break;
        case 'db_error':
            $error = "Terjadi kesalahan database. Mohon coba lagi. " . ($_GET['msg'] ?? 'Kesalahan tidak diketahui.');
            break;
        case 'invalid_id':
            $error = "ID transaksi tidak valid.";
            break;
        case 'validation_error':
            $error = "Semua kolom Tanggal, Jenis, Nominal, dan Keterangan wajib diisi dengan format yang benar.";
            break;
        default:
            break;
    }

    // Redirect setelah proses status agar URL bersih
    $currentUrl = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $currentUrl, true, 303);
    exit();
}

// --- FUNGSI BANTU UNTUK UPLOAD FILE ---
function handleFileUpload($fileInputName, $currentFileName = null, $removeOldFile = false) {
    $upload_dir = 'uploads/';
    
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Error: Gagal membuat direktori upload: " . $upload_dir . ". Pastikan server memiliki izin tulis.");
            return ['status' => false, 'msg' => "Gagal membuat direktori upload: Pastikan izin server."];
        }
    } else {
        if (!is_writable($upload_dir)) {
            error_log("Error: Direktori upload tidak dapat ditulis: " . $upload_dir . ". Pastikan izin server.");
            return ['status' => false, 'msg' => "Direktori upload tidak dapat ditulis. Periksa izin server."];
        }
    }

    $uploaded_file_name_for_db = $currentFileName; // Defaultnya pakai nama file saat ini di DB (misal: 'bukti_abc.jpg')

    if ($removeOldFile && !empty($currentFileName)) {
        $full_path_to_old_file = $upload_dir . $currentFileName;
        if (file_exists($full_path_to_old_file)) {
            if (!unlink($full_path_to_old_file)) {
                error_log("Warning: Gagal menghapus file lama: " . $full_path_to_old_file);
            }
        }
        $uploaded_file_name_for_db = null; // Set null di database jika dihapus
    }

    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$fileInputName]['tmp_name'];
        $file_name_original = $_FILES[$fileInputName]['name'];
        $file_size = $_FILES[$fileInputName]['size'];
        $file_error_code = $_FILES[$fileInputName]['error'];

        $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (!in_array($file_ext, $allowed_ext)) {
            return ['status' => false, 'msg' => "Tipe file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, atau PDF."];
        }

        $max_file_size = 5 * 1024 * 1024; // 5 MB
        if ($file_size > $max_file_size) {
            return ['status' => false, 'msg' => "Ukuran file terlalu besar. Maksimal " . ($max_file_size / (1024 * 1024)) . " MB."];
        }

        $new_file_name = uniqid('bukti_', true) . '.' . $file_ext;
        $target_file_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $target_file_path)) {
            $uploaded_file_name_for_db = $new_file_name; // Simpan HANYA NAMA FILE ke DB
        } else {
            error_log("Error: Gagal memindahkan file upload: " . $target_file_path . " Kode error: " . $file_error_code);
            return ['status' => false, 'msg' => "Gagal memindahkan file. Kode error: " . $file_error_code];
        }
    }
    return ['status' => true, 'msg' => "Upload sukses", 'file_name' => $uploaded_file_name_for_db];
}


// --- Proses Tambah Transaksi ---
if (isset($_POST['add_transaksi']) && $conn_status) {
    $tanggal = sanitize_input_basic($_POST['tanggal_transaksi'] ?? '');
    $jenis = sanitize_input_basic($_POST['jenis_transaksi'] ?? '');
    $nominal = floatval(str_replace(',', '.', $_POST['nominal_transaksi'] ?? 0));
    $keterangan = sanitize_input_basic($_POST['keterangan_transaksi'] ?? '');

    if (empty($tanggal) || empty($jenis) || !is_numeric($nominal) || $nominal < 0 || empty($keterangan)) {
        header("Location: transaksi.php?status=validation_error");
        exit();
    }

    $bukti_file_name = null;
    $upload_result = handleFileUpload('bukti_transaksi_add');
    
    if (!$upload_result['status']) {
        header("Location: transaksi.php?status=upload_error&msg=" . urlencode($upload_result['msg']));
        exit();
    }
    $bukti_file_name = $upload_result['file_name']; // Ini akan null jika tidak ada file, atau nama file jika ada

    $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, jenis, nominal, keterangan, bukti) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error prepare INSERT: " . $conn->error));
        exit();
    }
    // PERBAIKAN: String tipe data: s(tanggal), s(jenis), d(nominal), s(keterangan), s(bukti) = "ssdss"
    error_log("INSERT bind_param: types='ssdss', vars='" . implode(", ", [$tanggal, $jenis, $nominal, $keterangan, $bukti_file_name]) . "'");
    $stmt->bind_param("ssdss", $tanggal, $jenis, $nominal, $keterangan, $bukti_file_name);
    
    if ($stmt->execute()) {
        header("Location: transaksi.php?status=added");
        exit();
    } else {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error execute INSERT: " . $stmt->error));
        error_log("SQL Error INSERT: " . $stmt->error);
        exit();
    }
    $stmt->close();
}

// --- Proses Edit Transaksi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id = intval($_POST['transaksi_id']);
    $tanggal = sanitize_input_basic($_POST['tanggal_transaksi_edit'] ?? '');
    $jenis = sanitize_input_basic($_POST['jenis_transaksi_edit'] ?? '');
    $nominal = floatval(str_replace(',', '.', $_POST['nominal_transaksi_edit'] ?? 0));
    $keterangan = sanitize_input_basic($_POST['keterangan_transaksi_edit'] ?? '');
    $current_bukti = $_POST['current_bukti'] ?? null; // Nama file bukti yang sudah ada di DB
    $remove_bukti = isset($_POST['remove_bukti_edit']) && $_POST['remove_bukti_edit'] == '1';

    if (empty($tanggal) || empty($jenis) || !is_numeric($nominal) || $nominal < 0 || empty($keterangan)) {
        header("Location: transaksi.php?status=validation_error");
        exit();
    }
    if ($id <= 0) {
        header("Location: transaksi.php?status=invalid_id");
        exit();
    }

    $upload_result = handleFileUpload('bukti_transaksi_edit', $current_bukti, $remove_bukti);
    
    if (!$upload_result['status']) {
        header("Location: transaksi.php?status=upload_error&msg=" . urlencode($upload_result['msg']));
        exit();
    }
    $bukti_file_name_for_db = $upload_result['file_name']; // Ini akan null jika dihapus, atau nama file baru/lama

    $stmt = $conn->prepare("UPDATE transaksi SET tanggal=?, jenis=?, nominal=?, keterangan=?, bukti=? WHERE id=?");
    if ($stmt === false) {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error prepare UPDATE: " . $conn->error));
        exit();
    }
    // PERBAIKAN FINAL DAN PASTIKAN: String tipe data: s(tanggal), s(jenis), d(nominal), s(keterangan), s(bukti), i(id) = "ssdssi"
    error_log("UPDATE bind_param: types='ssdssi', vars='" . implode(", ", [$tanggal, $jenis, $nominal, $keterangan, $bukti_file_name_for_db, $id]) . "'");
    $stmt->bind_param("ssdssi", $tanggal, $jenis, $nominal, $keterangan, $bukti_file_name_for_db, $id);
    if ($stmt->execute()) {
        header("Location: transaksi.php?status=updated");
        exit();
    } else {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error execute UPDATE: " . $stmt->error));
        error_log("SQL Error UPDATE: " . $stmt->error);
        exit();
    }
    $stmt->close();
}

// --- Proses Hapus Transaksi ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && $conn_status) {
    $id_to_delete = intval($_GET['id']);

    if ($id_to_delete <= 0) {
        header("Location: transaksi.php?status=invalid_id");
        exit();
    }

    $stmt_get_bukti = $conn->prepare("SELECT bukti FROM transaksi WHERE id = ?");
    if ($stmt_get_bukti === false) {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error prepare DELETE get bukti: " . $conn->error));
        exit();
    }
    $stmt_get_bukti->bind_param("i", $id_to_delete);
    if (!$stmt_get_bukti->execute()) {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error execute DELETE get bukti: " . $stmt_get_bukti->error));
        exit();
    }
    $result_get_bukti = $stmt_get_bukti->get_result();
    $row_bukti = $result_get_bukti->fetch_assoc();
    $bukti_filename = $row_bukti['bukti'] ?? null; // Ini hanya nama file, misal 'bukti_abc.jpg'
    $stmt_get_bukti->close();

    $stmt_delete = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
    if ($stmt_delete === false) {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error prepare DELETE: " . $conn->error));
        exit();
    }
    $stmt_delete->bind_param("i", $id_to_delete);
    if ($stmt_delete->execute()) {
        // Hapus file fisik jika ada, dengan path lengkap
        if ($bukti_filename && file_exists('uploads/' . $bukti_filename)) {
            if (!unlink('uploads/' . $bukti_filename)) {
                error_log("Warning: Gagal menghapus file fisik setelah record dihapus: uploads/" . $bukti_filename);
            }
        }
        header("Location: transaksi.php?status=deleted");
        exit();
    } else {
        header("Location: transaksi.php?status=db_error&msg=" . urlencode("Error execute DELETE: " . $stmt_delete->error));
        error_log("SQL Error DELETE: " . $stmt_delete->error);
        exit();
    }
    $stmt_delete->close();
}


// --- Logika Pengambilan Data Transaksi untuk Tampilan ---
$bulan_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun_filter = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$bulanList = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if ($conn_status) {
    $query_transaksi = "SELECT id, tanggal, jenis, nominal, keterangan, bukti FROM transaksi WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal DESC";

    $stmt_select_transaksi = $conn->prepare($query_transaksi);

    if ($stmt_select_transaksi === false) {
        $php_error_message = "Gagal mempersiapkan query transaksi: " . $conn->error;
        error_log($php_error_message);
    } else {
        $stmt_select_transaksi->bind_param("ii", $bulan_filter, $tahun_filter);
        $stmt_select_transaksi->execute();
        $result_transaksi = $stmt_select_transaksi->get_result();
        
        if ($result_transaksi) {
            $transaksiData = mysqli_fetch_all($result_transaksi, MYSQLI_ASSOC);
            $result_transaksi->free();
        } else {
            $php_error_message = "Gagal mengambil data transaksi: " . $stmt_select_transaksi->error;
            error_log($php_error_message);
        }
        $stmt_select_transaksi->close();
    }
} else {
    $php_error_message = "Koneksi database tidak tersedia untuk mengambil data transaksi.";
}

// Tutup koneksi database di akhir script
if ($conn_status && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Keuangan - Karang Taruna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* DEBUG CSS: AKTIFKAN INI UNTUK MELIHAT BATAS ELEMEN! */
        /* * { outline: 1px solid rgba(255, 0, 0, 0.2) !important; } */

        /* Global Color Palette & Variables (Disamakan dengan dashboard.php) */
        :root {
            --primary-blue: #2c52ed;
            --primary-blue-light: #4a77ff;
            --accent-green: #28a745;
            --accent-red: #dc3545;
            --accent-orange: #ff9100;
            --neutral-light: #f0f2f5;
            --neutral-medium: #e0e5ec;
            --text-dark: #333d47;
            --text-secondary: #6b7a8d;
            --white: #ffffff;
            --card-bg: #ffffff;
            --card-shadow-light: rgba(0, 0, 0, 0.05);
            --card-shadow-medium: rgba(0, 0, 0, 0.1);
            --card-shadow-strong: rgba(0, 0, 0, 0.15);

            --info-blue: #17a2b8;
            --success-green: #28a745;
            --danger-red: #dc3545;
        }

        /* Universal box-sizing for consistency */
        html { box-sizing: border-box; }
        *, *::before, *::after { box-sizing: inherit; }

        body {
            background: var(--neutral-light);
            font-family: 'Poppins', sans-serif;
            color: var(--text-dark);
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            line-height: 1.6;
        }

        /* Sidebar Styling (DISAMAKAN DENGAN DASHBOARD.PHP) */
        nav.sidebar {
            background-image: linear-gradient(180deg, var(--primary-blue), var(--primary-blue-light));
            min-height: 100vh;
            width: 260px;
            position: fixed;
            top: 0; left: 0;
            padding: 2rem 1.2rem;
            color: var(--white);
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px var(--card-shadow-medium);
            z-index: 1000;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-logo {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            user-select: none;
            padding: 0.5rem 0;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
            color: var(--white);
            flex-wrap: nowrap;
        }
        .sidebar-logo i {
            font-size: 3rem;
            color: var(--accent-orange);
            text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
        }
        nav.sidebar a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 1rem 1.4rem;
            border-radius: 12px;
            margin-bottom: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        nav.sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: var(--white);
            transform: scale(1.02) translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        nav.sidebar a.active {
            background-color: var(--accent-orange);
            color: var(--primary-blue);
            font-weight: 700;
            box-shadow: 0 4px 20px rgba(255, 145, 0, 0.5);
            transform: scale(1.03) translateX(8px);
            border-left: 6px solid var(--white);
            padding-left: calc(1.4rem - 6px);
        }

        /* Main Content Wrapper */
        main.content-wrapper {
            margin-left: 260px;
            padding: 1.5rem 3.5rem 3rem 3.5rem;
            min-height: 100vh;
            background: var(--neutral-light);
            box-shadow: -2px 0 25px rgba(0, 0, 0, 0.08);
            border-top-left-radius: 30px;
            position: relative;
            z-index: 1;
            transition: margin-left 0.3s ease-in-out;
        }
        h3 {
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            user-select: none;
            font-size: 2.2rem;
            font-weight: 800;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.1);
            font-family: 'Montserrat', sans-serif;
        }
        /* General Card Styling (used for filter section) */
        .card {
            border-radius: 25px;
            box-shadow: 0 18px 50px var(--card-shadow-light);
            margin-bottom: 3rem;
            background-color: var(--card-bg);
            border: none;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            position: relative;
            transform-style: preserve-3d;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px var(--card-shadow-medium);
        }
        
        /* Filter Section Styling */
        .filter-section {
            background-color: var(--card-bg);
            border-radius: 25px;
            padding: 1.8rem 2.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 25px var(--card-shadow-light);
            border: 1px solid var(--neutral-medium);
        }
        .filter-section .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        .filter-section .form-control,
        .filter-section .form-select {
            border-radius: 12px;
            border: 2px solid var(--primary-blue-light);
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 0.3rem rgba(255, 145, 0, 0.2);
        }
        .filter-section .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            font-size: 0.95rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }
        .filter-section .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            box-shadow: 0 5px 15px rgba(44, 82, 237, 0.3);
        }
        .filter-section .btn-primary:hover {
            background-color: var(--primary-blue-light);
            border-color: var(--primary-blue-light);
            box-shadow: 0 7px 20px rgba(74, 119, 255, 0.4);
        }
        .filter-section .btn-outline-secondary {
            border-color: var(--text-secondary);
            color: var(--text-secondary);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .filter-section .btn-outline-secondary:hover {
            background-color: var(--text-secondary);
            color: var(--white);
            box-shadow: 0 5px 12px rgba(0,0,0,0.2);
        }

        /* Styles for badges (Pemasukan/Pengeluaran) */
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.7em;
            border-radius: 0.5em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge .bi {
            font-family: "bootstrap-icons" !important;
            font-size: 0.9em;
            line-height: 1;
        }

        /* Tombol Aksi - Dengan Ikon dan Teks (untuk Card View) */
        .action-buttons-group {
            display: flex; 
            gap: 8px;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: nowrap;
        }
        .btn-action {
            width: auto;
            height: auto;
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            text-decoration: none; 
        }

        /* Ikon di dalam tombol aksi */
        .btn-action i {
            font-family: "bootstrap-icons" !important;
            font-size: 1.1rem;
            line-height: 1;
            text-shadow: none;
        }

        /* Teks "Edit" dan "Hapus" pada tombol, pastikan tampil */
        .btn-action span.button-text {
            display: inline-block; 
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            opacity: 0.9;
        }
        
        /* Ripple effect for buttons */
        .btn-action::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            width: 100px;
            height: 100px;
            margin-top: -50px;
            margin-left: -50px;
            opacity: 0;
            transform: scale(0);
            transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.5s ease-out;
            pointer-events: none;
            z-index: 10;
        }
        .btn-action:active::after {
            transform: scale(1);
            opacity: 1;
            transition: 0s;
        }

        /* Warna Tombol CRUD */
        .btn-add { background-color: var(--primary-blue); border-color: var(--primary-blue); color: var(--white); }
        .btn-add:hover { background-color: var(--primary-blue-light); border-color: var(--primary-blue-light); }
        
        /* Edit button */
        .btn-warning {
            background-color: var(--accent-orange);
            border-color: var(--accent-orange);
            color: var(--primary-blue);
        }
        .btn-warning:hover {
            background-color: #e08300;
            border-color: #e08300;
        }

        /* Delete button */
        .btn-danger {
            background-color: var(--accent-red); 
            border-color: var(--accent-red); 
            color: var(--white);
        }
        .btn-danger:hover { background-color: #c82333; border-color: #c82333; }
        
        /* Button info small untuk PDF */
        .btn-info-small {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: var(--white);
            font-weight: 600;
            border-radius: 10px;
            padding: 0.5rem 0.9rem;
            font-size: 0.9rem;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            display: inline-block;
            vertical-align: middle;
        }
        .btn-info-small:hover {
            background-color: var(--primary-blue-light);
            border-color: var(--primary-blue-light);
            box-shadow: 0 5px 12px rgba(0,0,0,0.25);
            transform: translateY(-2px) translateZ(5px);
        }

        .bukti-img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(44, 82, 237, 0.3);
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 3px solid rgba(44, 82, 237, 0.1);
            display: inline-block;
            vertical-align: middle;
        }
        .bukti-img:hover {
            transform: scale(1.2) rotate(3deg) translateZ(15px);
            box-shadow: 0 8px 25px rgba(44, 82, 237, 0.6);
            border-color: var(--accent-orange);
        }

        /* Modal Styling (FOKUS PERBAIKAN DI SINI) */
        .modal-content {
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.95);
        }
        .modal-header {
            background-image: linear-gradient(90deg, var(--primary-blue), var(--primary-blue-light));
            color: var(--white);
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
            padding: 1.8rem;
        }
        .modal-title {
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .modal-header .btn-close:hover {
            transform: rotate(90deg) scale(1.1);
        }
        .modal-body {
            padding: 2rem;
            display: block; /* Penting: agar Bootstrap grid berfungsi di dalamnya */
        }
        /* Grid untuk layout 2 kolom di dalam modal body */
        .modal-body .row.g-3 {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* 2 kolom dengan lebar sama */
            gap: 1.5rem; /* Jarak antar elemen grid */
            align-items: flex-start;
        }
        .modal-body .col-12 { /* Untuk elemen yang ingin 100% lebar (misal keterangan, bukti) */
            grid-column: 1 / -1; /* Membentang di semua kolom grid */
        }
        .modal-body .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
            margin-bottom: 0.4rem;
        }
        .modal-body .form-control,
        .modal-body .form-select,
        .modal-body textarea.form-control {
            border-radius: 12px;
            border: 2px solid var(--primary-blue);
            padding: 0.6rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .modal-body .form-control:focus,
        .modal-body .form-select:focus,
        .modal-body textarea.form-control:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 0.25rem rgba(255, 145, 0, 0.25);
            transform: scale(1.01);
        }
        .modal-footer {
            border-top: none;
            padding: 1.5rem 2rem;
            justify-content: flex-end;
            gap: 1rem;
        }
        .modal-footer .btn {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            transform: translateZ(0);
        }
        .modal-footer .btn:hover {
            transform: translateY(-3px) translateZ(10px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        /* Style untuk tombol submit edit */
        .modal-footer .btn-edit-submit {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: var(--white);
        }
        .modal-footer .btn-edit-submit:hover {
            background-color: var(--primary-blue-light);
            border-color: var(--primary-blue-light);
        }
        .modal-footer .btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); color: var(--white); }
        .modal-footer .btn-secondary { background-color: var(--text-secondary); border-color: var(--text-secondary); color: var(--white); }


        /* --- CSS BARU UNTUK CARD VIEW --- */
        .card-view-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            padding-top: 10px;
        }

        .transaction-card {
            background-color: var(--card-bg);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--neutral-medium);
        }

        .transaction-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--neutral-medium);
            background-color: #fcfcfc;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }

        .card-id-date {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .card-content {
            padding: 20px;
            flex-grow: 1;
        }

        .card-type-nominal {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .card-nominal {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .card-nominal.pemasukan {
            color: var(--accent-green);
        }

        .card-nominal.pengeluaran {
            color: var(--accent-red);
        }

        .card-keterangan {
            font-size: 0.95rem;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .card-bukti {
            text-align: center;
            margin-bottom: 15px;
        }

        .card-bukti .bukti-img,
        .card-bukti .btn-info-small {
            width: 100%;
            max-width: 200px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid var(--primary-blue-light);
        }

        .card-actions {
            padding: 15px 20px;
            border-top: 1px solid var(--neutral-medium);
            background-color: #fcfcfc;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .card-actions .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.95rem;
        }
        .card-actions .btn-action i {
             font-size: 1rem;
        }

        /* Responsive Adjustments untuk Card View */
        @media (max-width: 768px) {
            .card-view-container {
                grid-template-columns: 1fr;
                padding-left: 0;
                padding-right: 0;
                gap: 15px;
            }
            .transaction-card {
                margin-bottom: 0;
                padding: 15px;
                border-radius: 15px;
            }
            .card-header-meta {
                padding: 10px 0;
            }
            .card-content {
                padding: 15px 0;
            }
            .card-actions {
                padding: 10px 0;
                justify-content: center;
            }
            .card-actions .btn-action {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            .card-actions .btn-action i {
                font-size: 0.9rem;
            }
        }
        @media (max-width: 576px) {
            main.content-wrapper { padding: 1rem; }
            h3 { font-size: 1.5rem; }
            .sidebar-logo { font-size: 1.4rem; }
            .sidebar-logo i { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<button class="toggle-sidebar-btn d-md-none" aria-label="Toggle sidebar" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<nav class="sidebar" role="navigation" aria-label="Sidebar menu">
    <div class="sidebar-logo" aria-hidden="true">
        <i class="bi bi-cash-stack"></i> Karang Taruna
    </div>
    <a href="dashboard.php" class="<?= isActive('dashboard.php') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="transaksi.php" class="<?= isActive('transaksi.php') ?>"><i class="bi bi-journal-text"></i> Transaksi</a>
    <a href="laporan.php" class="<?= isActive('laporan.php') ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Laporan</a>
    <a href="manajemen_user.php" class="<?= isActive('manajemen_user.php') ?>"><i class="bi bi-people"></i> Manajemen User</a>
    <a href="upload_dokumen_page.php" class="<?= isActive('upload_dokumen_page.php') ?>"><i class="bi bi-upload"></i> Upload Dokumen</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</nav>

<main class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Transaksi Keuangan</h3>
        <div>
            <button type="button" class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#addTransaksiModal">
                <i class="bi bi-plus-circle"></i> Tambah Transaksi
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($php_error_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Peringatan Sistem:</strong> <?= htmlspecialchars($php_error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card filter-section">
        <form method="GET" class="row g-3 align-items-end mb-0">
            <div class="col-md-3">
                <label for="bulan" class="form-label">Bulan</label>
                <select id="bulan" name="bulan" class="form-select">
                    <?php foreach ($bulanList as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= $val == $bulan_filter ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tahun" class="form-label">Tahun</label>
                <select id="tahun" name="tahun" class="form-select">
                    <?php for ($i = 2022; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $tahun_filter ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Tampilkan</button>
            </div>
            <div class="col-md-2">
                <a href="transaksi.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
    </div>

    <div id="cardView">
        <div class="card-view-container">
            <?php if ($conn_status && !empty($transaksiData)): ?>
                <?php foreach ($transaksiData as $transaksi): ?>
                    <div class="transaction-card">
                        <div class="card-header-meta">
                            <span class="card-id-date">ID: <?= htmlspecialchars($transaksi['id']) ?> - Tanggal: <?= htmlspecialchars($transaksi['tanggal']) ?></span>
                        </div>
                        <div class="card-content">
                            <div class="card-type-nominal">
                                <?php if ($transaksi['jenis'] == 'pemasukan'): ?>
                                    <span class="badge bg-success"><i class="bi bi-arrow-up-circle-fill"></i> Pemasukan</span>
                                    <span class="card-nominal pemasukan">Rp<?= number_format($transaksi['nominal'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="bi bi-arrow-down-circle-fill"></i> Pengeluaran</span>
                                    <span class="card-nominal pengeluaran">Rp<?= number_format($transaksi['nominal'], 0, ',', '.') ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="card-keterangan">Keterangan: <?= htmlspecialchars($transaksi['keterangan'] ?? '-') ?></p>
                            <div class="card-bukti">
                                <?php
                                $bukti_filename_from_db = $transaksi['bukti']; // Ini adalah nama file dari database
                                // Path lengkap untuk display di browser
                                $full_bukti_path_for_display = 'uploads/' . htmlspecialchars($bukti_filename_from_db); 
                                
                                $file_extension = !empty($bukti_filename_from_db) ? pathinfo($bukti_filename_from_db, PATHINFO_EXTENSION) : '';
                                $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif']);
                                ?>
                                <?php if (!empty($bukti_filename_from_db) && file_exists($full_bukti_path_for_display)): // Check if file exists on server ?>
                                    <?php if ($is_image): ?>
                                        <img src="<?= $full_bukti_path_for_display ?>" class="bukti-img" alt="Bukti"
                                             onclick="showModalBukti('<?= htmlspecialchars($full_bukti_path_for_display) ?>')" tabindex="0" role="button" aria-label="Lihat bukti transaksi" />
                                    <?php else: /* Asumsi PDF */ ?>
                                        <a href="<?= $full_bukti_path_for_display ?>" target="_blank" class="btn btn-info-small"><i class="bi bi-file-earmark-pdf-fill"></i> Lihat PDF</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Tidak ada bukti</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-actions">
                            <button type="button" class="btn btn-warning btn-action"
                                    data-bs-toggle="modal" data-bs-target="#editTransaksiModal"
                                    data-id="<?= htmlspecialchars($transaksi['id']) ?>"
                                    data-tanggal="<?= htmlspecialchars($transaksi['tanggal']) ?>"
                                    data-jenis="<?= htmlspecialchars($transaksi['jenis']) ?>"
                                    data-nominal="<?= htmlspecialchars($transaksi['nominal']) ?>"
                                    data-keterangan="<?= htmlspecialchars($transaksi['keterangan'] ?? '') ?>"
                                    data-bukti="<?= htmlspecialchars($bukti_filename_from_db) ?>" onclick="prepareEditModal(this)">
                                <i class="bi bi-pencil-fill"></i> <span class="button-text">Edit</span>
                            </button>
                            <a href="transaksi.php?action=delete&id=<?= htmlspecialchars($transaksi['id']) ?>" class="btn btn-danger btn-action"
                                onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan.');">
                                <i class="bi bi-trash-fill"></i> <span class="button-text">Hapus</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                 <div class="col-12">
                    <div class="alert alert-info text-center mt-3" role="alert">
                        <?php if (!empty($php_error_message)): ?>
                            Terjadi masalah: <?= htmlspecialchars($php_error_message) ?>
                        <?php else: ?>
                            Belum ada transaksi yang tercatat untuk bulan dan tahun ini.
                        <?php endif; ?>
                    </div>
                 </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<div class="modal fade" id="addTransaksiModal" tabindex="-1" aria-labelledby="addTransaksiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="transaksi.php" enctype="multipart/form-data" class="modal-content rounded-4 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="addTransaksiModalLabel">Tambah Transaksi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tanggal_transaksi" class="form-label">Tanggal</label>
                        <input type="date" id="tanggal_transaksi" name="tanggal_transaksi" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="jenis_transaksi" class="form-label">Jenis Transaksi</label>
                        <select id="jenis_transaksi" name="jenis_transaksi" class="form-select" required>
                            <option value="">Pilih jenis</option>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="nominal_transaksi" class="form-label">Nominal (Rp)</label>
                        <input type="number" id="nominal_transaksi" name="nominal_transaksi" class="form-control" placeholder="Contoh: 150000" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label for="keterangan_transaksi" class="form-label">Keterangan</label>
                        <textarea id="keterangan_transaksi" name="keterangan_transaksi" class="form-control" rows="2" placeholder="Deskripsi singkat..." required></textarea>
                    </div>
                    <div class="col-12">
                        <label for="bukti_transaksi_add" class="form-label">Bukti (Opsional)</label>
                        <input type="file" id="bukti_transaksi_add" name="bukti_transaksi_add" class="form-control" accept="image/*,application/pdf" />
                        <div class="form-text">Format: Gambar (JPG, PNG, GIF) atau PDF. Maks 5MB.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" name="add_transaksi">Tambah Transaksi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editTransaksiModal" tabindex="-1" aria-labelledby="editTransaksiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="transaksi.php" enctype="multipart/form-data" class="modal-content rounded-4 shadow">
            <input type="hidden" name="transaksi_id" id="edit-transaksi-id" />
            <input type="hidden" name="current_bukti" id="edit-current-bukti" />
            <div class="modal-header">
                <h5 class="modal-title" id="editTransaksiModalLabel">Edit Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="tanggal_transaksi_edit" class="form-label">Tanggal</label>
                        <input type="date" id="tanggal_transaksi_edit" name="tanggal_transaksi_edit" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="jenis_transaksi_edit" class="form-label">Jenis Transaksi</label>
                        <select id="jenis_transaksi_edit" name="jenis_transaksi_edit" class="form-select" required>
                            <option value="">Pilih jenis</option>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="nominal_transaksi_edit" class="form-label">Nominal (Rp)</label>
                        <input type="number" id="nominal_transaksi_edit" name="nominal_transaksi_edit" class="form-control" placeholder="Contoh: 150000" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label for="keterangan_transaksi_edit" class="form-label">Keterangan</label>
                        <textarea id="keterangan_transaksi_edit" name="keterangan_transaksi_edit" class="form-control" rows="2" placeholder="Deskripsi singkat..." required></textarea>
                    </div>
                    <div class="col-12">
                        <label for="bukti_transaksi_edit" class="form-label">Ganti Bukti (Opsional)</label>
                        <input type="file" id="bukti_transaksi_edit" name="bukti_transaksi_edit" class="form-control" accept="image/*,application/pdf" />
                        <div class="form-text">Kosongkan jika tidak ingin mengubah. Format: Gambar (JPG, PNG, GIF) atau PDF. Maks 5MB.</div>
                        <div class="mt-2" id="edit_bukti_info">
                            <span class="text-muted">Bukti saat ini: </span> <a href="#" id="edit_current_bukti_link" target="_blank" class="ms-1">Lihat Bukti</a>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" value="1" id="remove_bukti_edit" name="remove_bukti_edit">
                                <label class="form-check-label" for="remove_bukti_edit">
                                    Hapus bukti saat ini
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary btn-edit-submit" name="edit_transaksi">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalBukti" tabindex="-1" aria-labelledby="modalBuktiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBuktiLabel">Preview Bukti Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" id="modalImage" alt="Preview Bukti" class="img-fluid rounded" style="max-height: 80vh; object-fit: contain;">
                <a href="" id="modalDownloadLink" class="btn btn-info mt-3" download style="display: none;"><i class="bi bi-download"></i> Unduh Bukti</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        const addModal = document.getElementById('addTransaksiModal');
        addModal.addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
            document.getElementById('tanggal_transaksi').value = '<?= date('Y-m-d') ?>';
        });
    });

    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('nav.sidebar a').forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref) {
            const fileName = linkHref.split('/').pop();
            if (fileName === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }
    });

    function toggleSidebar() {
        document.querySelector('nav.sidebar').classList.toggle('active');
    }
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    const modalBuktiPreview = new bootstrap.Modal(document.getElementById('modalBukti'));
    function showModalBukti(filename) {
        const img = document.getElementById('modalImage');
        const downloadLink = document.getElementById('modalDownloadLink');
        const fileExtension = filename.split('.').pop().toLowerCase();
        
        // Path sudah lengkap dari PHP, jadi langsung gunakan.
        const filePath = filename; 
        
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);

        if (isImage) {
            img.src = filePath;
            img.style.display = 'block';
            downloadLink.href = filePath;
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = 'Unduh Gambar';
            modalBuktiPreview.show();
        } else if (fileExtension === 'pdf') {
            window.open(filePath, '_blank');
            modalBuktiPreview.hide();
            return;
        } else {
            img.style.display = 'none';
            downloadLink.href = filePath;
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = 'Unduh File';
            modalBuktiPreview.show();
        }
    }

    const editTransaksiModal = new bootstrap.Modal(document.getElementById('editTransaksiModal'));
    const editId = document.getElementById('edit-transaksi-id');
    const editTanggal = document.getElementById('tanggal_transaksi_edit');
    const editJenis = document.getElementById('jenis_transaksi_edit');
    const editNominal = document.getElementById('nominal_transaksi_edit');
    const editKeterangan = document.getElementById('keterangan_transaksi_edit');
    const editCurrentBuktiHidden = document.getElementById('edit-current-bukti');
    const editBuktiInfoDiv = document.getElementById('edit_bukti_info');
    const editCurrentBuktiLink = document.getElementById('edit_current_bukti_link');
    const removeBuktiCheckbox = document.getElementById('remove_bukti_edit');
    const editBuktiInputFile = document.getElementById('bukti_transaksi_edit');

    function prepareEditModal(button) {
        const id = button.dataset.id;
        const tanggal = button.dataset.tanggal;
        const jenis = button.dataset.jenis;
        const nominal = button.dataset.nominal;
        const keterangan = button.dataset.keterangan;
        // bukti yang diterima dari data-bukti adalah HANYA nama file (misal: 'bukti_abc.jpg')
        const bukti_nama_file_saja = button.dataset.bukti; 

        editId.value = id;
        editTanggal.value = tanggal;
        editJenis.value = jenis;
        editNominal.value = nominal;
        editKeterangan.value = keterangan;
        // Simpan nama file saja ke hidden input
        editCurrentBuktiHidden.value = bukti_nama_file_saja; 

        removeBuktiCheckbox.checked = false;
        editBuktiInputFile.value = '';

        if (bukti_nama_file_saja && bukti_nama_file_saja !== 'null' && bukti_nama_file_saja !== '') {
            editBuktiInfoDiv.style.display = 'block';
            // Path untuk link di modal edit: 'uploads/' + nama_file_saja
            editCurrentBuktiLink.href = 'uploads/' + encodeURIComponent(bukti_nama_file_saja);
            editCurrentBuktiLink.textContent = 'Lihat Bukti Saat Ini (' + bukti_nama_file_saja.split('/').pop() + ')';
            editCurrentBuktiLink.onclick = function(e) {
                e.preventDefault();
                const fileExtension = bukti_nama_file_saja.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
                // Panggil showModalBukti dengan path lengkap untuk ditampilkan
                if (isImage) {
                    modalBuktiPreview.hide();
                    showModalBukti('uploads/' + bukti_nama_file_saja); 
                } else {
                    window.open('uploads/' + encodeURIComponent(bukti_nama_file_saja), '_blank');
                }
            };
            removeBuktiCheckbox.disabled = false;
        } else {
            editBuktiInfoDiv.style.display = 'none';
            editCurrentBuktiLink.href = '#';
            editCurrentBuktiLink.textContent = 'Tidak ada bukti terunggah.';
            editCurrentBuktiLink.onclick = null;
            removeBuktiCheckbox.disabled = true;
        }
        editTransaksiModal.show();
    }

    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            // Jika ini adalah tombol hapus (<a>) dengan konfirmasi, biarkan browser menanganinya
            if (this.tagName === 'A' && this.classList.contains('btn-danger') && this.getAttribute('onclick')) {
                return; 
            }
            // Untuk semua tombol lain (termasuk tombol submit di modal), terapkan ripple
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255,255,255,0.4);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                transform: scale(0);
                opacity: 1;
                transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.5s ease-out;
                pointer-events: none;
                z-index: 10;
            `;
            this.appendChild(ripple);
            requestAnimationFrame(() => {
                ripple.style.transform = 'scale(1.5)';
                ripple.style.opacity = '0';
            });
            ripple.addEventListener('transitionend', () => {
                ripple.remove();
            });
        });
    });
</script>

</body>
</html>