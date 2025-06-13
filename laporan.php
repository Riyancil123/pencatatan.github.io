<?php
session_start();
require_once 'koneksi.php'; // Memanggil file koneksi database

// Cek login. Jika belum login, redirect ke login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fungsi untuk menentukan kelas 'active' pada sidebar
function isActive($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage === $pageName) ? 'active' : '';
}

// Untuk pesan sukses/error (jika ada)
$success = "";
$error = "";

// Bulan list untuk dropdown
$bulanListUntukInput = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// --- Proses Tambah Laporan (jika form modal disubmit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laporan'])) {
    $bulan_input = $_POST['bulan_laporan'] ?? '';
    $tahun_input = $_POST['tahun_laporan'] ?? '';
    $keterangan_input = $_POST['keterangan_laporan'] ?? '';

    $bukti_laporan = '';
    if (!empty($_FILES['bukti_laporan']['name'])) {
        $targetDir = 'uploads/';
        $targetDirAbsolute = __DIR__ . DIRECTORY_SEPARATOR . $targetDir;

        if (!is_dir($targetDirAbsolute)) {
            if (!mkdir($targetDirAbsolute, 0755, true)) {
                $error = "Gagal membuat direktori upload: " . $targetDirAbsolute . ". Pastikan izin server.";
            }
        }

        if (empty($error)) {
            $fileName = time() . '_' . basename($_FILES['bukti_laporan']['name']);
            $targetFileAbsolute = $targetDirAbsolute . $fileName;
            $targetFileRelative = $targetDir . $fileName;

            $fileType = strtolower(pathinfo($targetFileAbsolute, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg','jpeg','png','gif','pdf'];
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES['bukti_laporan']['tmp_name'], $targetFileAbsolute)) {
                    $bukti_laporan = $targetFileRelative;
                } else {
                    $error = "Gagal mengunggah file bukti. Error code: " . $_FILES['bukti_laporan']['error'];
                }
            } else {
                $error = "Tipe file bukti tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, atau PDF.";
            }
        }
    }

    $nama_bulan_untuk_db = $bulanListUntukInput[$bulan_input] ?? '';

    if (empty($nama_bulan_untuk_db) || empty($tahun_input) || empty($keterangan_input)) {
        $error = "Bulan, Tahun, dan Keterangan laporan wajib diisi.";
    } else {
        if (empty($error)) {
            // PERBAIKAN: Gunakan 's' untuk $nama_bulan_untuk_db, 'i' untuk $tahun_input, 's' untuk $keterangan_input, dan 's' untuk $bukti_laporan
            $stmt = $conn->prepare("INSERT INTO laporan (bulan, tahun, keterangan, bukti) VALUES (?, ?, ?, ?)");
            if ($stmt === false) { $error = "DB Error: " . $conn->error; }
            else {
                $stmt->bind_param("siss", $nama_bulan_untuk_db, $tahun_input, $keterangan_input, $bukti_laporan); 
                if ($stmt->execute()) {
                    header("Location: laporan.php?status=success");
                    exit();
                } else {
                    $error = "Gagal menambahkan laporan: " . $stmt->error;
                    if (!empty($bukti_laporan) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $bukti_laporan)) {
                        unlink(__DIR__ . DIRECTORY_SEPARATOR . $bukti_laporan);
                    }
                }
                $stmt->close();
            }
        }
    }
}

// --- Proses Edit Laporan (jika form modal edit disubmit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_laporan'])) {
    $id_laporan = $_POST['id_laporan'] ?? 0;
    $bulan_input = $_POST['bulan_laporan_edit'] ?? '';
    $tahun_input = $_POST['tahun_laporan_edit'] ?? '';
    $keterangan_input = $_POST['keterangan_laporan_edit'] ?? '';
    $bukti_lama = $_POST['bukti_lama'] ?? ''; // Path bukti lama dari hidden input

    $bukti_laporan_baru = $bukti_lama; // Defaultnya pakai bukti lama

    // Cek apakah ada file bukti baru diupload
    if (!empty($_FILES['bukti_laporan_edit']['name'])) {
        $targetDir = 'uploads/';
        $targetDirAbsolute = __DIR__ . DIRECTORY_SEPARATOR . $targetDir;

        if (!is_dir($targetDirAbsolute)) {
            mkdir($targetDirAbsolute, 0755, true); // Buat jika tidak ada
        }

        $fileName = time() . '_' . basename($_FILES['bukti_laporan_edit']['name']);
        $targetFileAbsolute = $targetDirAbsolute . $fileName;
        $targetFileRelative = $targetDir . $fileName;

        $fileType = strtolower(pathinfo($targetFileAbsolute, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg','jpeg','png','gif','pdf'];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['bukti_laporan_edit']['tmp_name'], $targetFileAbsolute)) {
                // Hapus bukti lama jika ada dan berhasil upload yang baru
                if (!empty($bukti_lama) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $bukti_lama)) {
                    unlink(__DIR__ . DIRECTORY_SEPARATOR . $bukti_lama);
                }
                $bukti_laporan_baru = $targetFileRelative;
            } else {
                $error = "Gagal mengunggah file bukti baru. Error code: " . $_FILES['bukti_laporan_edit']['error'];
            }
        } else {
            $error = "Tipe file bukti baru tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, atau PDF.";
        }
    } else if (isset($_POST['remove_bukti_edit']) && $_POST['remove_bukti_edit'] == '1') {
        // Jika checkbox 'hapus bukti' dicentang
        if (!empty($bukti_lama) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $bukti_lama)) {
            unlink(__DIR__ . DIRECTORY_SEPARATOR . $bukti_lama);
        }
        $bukti_laporan_baru = ''; // Kosongkan path bukti di DB
    }


    $nama_bulan_untuk_db = $bulanListUntukInput[$bulan_input] ?? '';

    if (empty($nama_bulan_untuk_db) || empty($tahun_input) || empty($keterangan_input) || $id_laporan == 0) {
        $error = "Bulan, Tahun, Keterangan laporan, dan ID laporan wajib diisi.";
    } else {
        if (empty($error)) {
            // PERBAIKAN: Gunakan 's' untuk $nama_bulan_untuk_db, 'i' untuk $tahun_input, 's' untuk $keterangan_input, dan 's' untuk $bukti_laporan_baru
            $stmt = $conn->prepare("UPDATE laporan SET bulan = ?, tahun = ?, keterangan = ?, bukti = ? WHERE id = ?");
            if ($stmt === false) { $error = "DB Error: " . $conn->error; }
            else {
                $stmt->bind_param("sissi", $nama_bulan_untuk_db, $tahun_input, $keterangan_input, $bukti_laporan_baru, $id_laporan);
                if ($stmt->execute()) {
                    header("Location: laporan.php?status=edited");
                    exit();
                } else {
                    $error = "Gagal memperbarui laporan: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}


// --- Proses Hapus Laporan ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);

    $stmt_get_bukti = $conn->prepare("SELECT bukti FROM laporan WHERE id = ?");
    if ($stmt_get_bukti === false) { 
        $error = "DB Error: " . $conn->error; 
    } else {
        $stmt_get_bukti->bind_param("i", $id_to_delete);
        $stmt_get_bukti->execute();
        $result_get_bukti = $stmt_get_bukti->get_result();
        $row_bukti = $result_get_bukti->fetch_assoc();
        $bukti_filename_relative = $row_bukti['bukti'] ?? null;
        $stmt_get_bukti->close();

        $stmt_delete = $conn->prepare("DELETE FROM laporan WHERE id = ?");
        if ($stmt_delete === false) { 
            $error = "DB Error: " . $conn->error; 
        } else {
            $stmt_delete->bind_param("i", $id_to_delete);
            if ($stmt_delete->execute()) {
                if ($bukti_filename_relative && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $bukti_filename_relative)) {
                    unlink(__DIR__ . DIRECTORY_SEPARATOR . $bukti_filename_relative);
                }
                header("Location: laporan.php?status=deleted");
                exit();
            } else {
                $error = "Gagal menghapus laporan: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }
    }
}


// Ambil bulan dan tahun dari URL, jika tidak ada pakai bulan dan tahun sekarang
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Konversi angka bulan ke nama Indonesia untuk tampilan dan query
$bulanList = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$nama_bulan = $bulanList[$bulan];

// Query ambil data dari tabel laporan
$laporan = [];
if ($conn) {
    $query = "SELECT * FROM laporan WHERE bulan = ? AND tahun = ? ORDER BY id DESC";
    $stmt_select = $conn->prepare($query);
    if ($stmt_select === false) { $error = "DB Error: " . $conn->error; }
    else {
        $stmt_select->bind_param("si", $nama_bulan, $tahun);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        $laporan = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $stmt_select->close();
    }
} else {
    $error = "Koneksi database tidak tersedia untuk mengambil laporan.";
}


// Tampilkan pesan sukses dari redirect
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success = "Laporan berhasil ditambahkan!";
} elseif (isset($_GET['status']) && $_GET['status'] === 'deleted') {
    $success = "Laporan berhasil dihapus!";
} elseif (isset($_GET['status']) && $_GET['status'] === 'edited') {
    $success = "Laporan berhasil diperbarui!";
}

if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Laporan Bulanan - Pencatatan Keuangan Karang Taruna</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* DEBUG CSS: AKTIFKAN INI UNTUK MELIHAT BATAS ELEMEN! */
    /* Cukup hapus komentar di baris di bawah */
    /* * { outline: 1px solid rgba(255, 0, 0, 0.2) !important; } */
    /* td { outline: 1px solid blue !important; } */
    /* th { outline: 1px solid green !important; } */


    /* Global Color Palette & Variables (DISAMAKAN DENGAN DASHBOARD.PHP) */
    :root {
        --primary-blue: #2c52ed; /* Deep Blue, slightly darker and richer */
        --primary-blue-light: #4a77ff; /* Lighter shade of primary blue */
        --accent-green: #28a745; /* Success green */
        --accent-red: #dc3545; /* Danger red */
        --accent-orange: #ff9100; /* Warm orange for saldo / sidebar active */
        --neutral-light: #f0f2f5; /* Very light gray for background */
        --neutral-medium: #e0e5ec; /* Medium gray */
        --text-dark: #333d47; /* Darker text for better contrast */
        --text-secondary: #6b7a8d; /* Muted text */
        --white: #ffffff;
        --card-bg: #ffffff;
        --card-shadow-light: rgba(0, 0, 0, 0.05);
        --card-shadow-medium: rgba(0, 0, 0, 0.1);
        --card-shadow-strong: rgba(0, 0, 0, 0.15);

        /* Warna spesifik untuk tabel (disesuaikan dengan laporan.php versi sebelumnya) */
        --table-header-bg: linear-gradient(90deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%); /* Diubah menjadi primary blue range */
        --table-row-hover-bg: #e6f0ff; 
        --info-blue: #17a2b8; /* Pastikan ini didefinisikan jika digunakan */
        --success-green: #28a745; /* Pastikan ini didefinisikan jika digunakan */
        --danger-red: #dc3545; /* Pastikan ini didefinisikan jika digunakan */
    }

    /* Universal box-sizing for consistency */
    html { box-sizing: border-box; }
    *, *::before, *::after { box-sizing: inherit; }

    body {
        background: var(--neutral-light); /* DISAMAKAN: dari dashboard.php */
        font-family: 'Poppins', sans-serif;
        color: var(--text-dark); /* DISAMAKAN: dari dashboard.php */
        overflow-x: hidden;
        display: flex;
        min-height: 100vh;
        flex-direction: column;
        line-height: 1.6;
    }

    /* Sidebar Styling (DISAMAKAN DENGAN DASHBOARD.PHP) */
    nav.sidebar {
        background-image: linear-gradient(180deg, var(--primary-blue), var(--primary-blue-light)); /* DISAMAKAN */
        min-height: 100vh;
        width: 260px; /* DISAMAKAN */
        position: fixed;
        top: 0; left: 0;
        padding: 2rem 1.2rem; /* DISAMAKAN */
        color: var(--white);
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 20px var(--card-shadow-medium); /* DISAMAKAN */
        z-index: 1000;
        border-right: 1px solid rgba(255, 255, 255, 0.08); /* DISAMAKAN */
        transition: transform 0.3s ease-in-out;
    }
    .sidebar-logo {
        font-family: 'Montserrat', sans-serif;
        font-size: 2rem; /* DISAMAKAN */
        font-weight: 900; /* DISAMAKAN */
        margin-bottom: 3rem; /* DISAMAKAN */
        display: flex;
        align-items: center;
        gap: 10px; /* DISAMAKAN */
        user-select: none;
        padding: 0.5rem 0;
        text-shadow: 2px 2px 5px rgba(0,0,0,0.3); /* DISAMAKAN */
        color: var(--white);
        flex-wrap: nowrap; /* Ditambahkan agar tidak pecah baris untuk logo */
    }
    /* Logo ICON (Disamakan dengan transaksi.php dan laporan.php) */
    .sidebar-logo .logo-icon { /* Class BARU untuk ikon */
        font-size: 3rem; /* Ukuran ikon */
        color: var(--accent-orange); /* Warna ikon */
        text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
        vertical-align: middle;
        flex-shrink: 0;
        margin-right: 10px; /* Jarak antara ikon dan teks "Karang" */
    }
    /* Style untuk teks "Karang" dan "Taruna" agar bertumpuk */
    .sidebar-logo .logo-text-wrapper { /* Class BARU untuk teks bertumpuk */
        display: flex;
        flex-direction: column; /* Membuat teks bertumpuk */
        align-items: flex-start; /* Rata kiri untuk teks bertumpuk */
        line-height: 1; /* Rapatan baris */
    }
    .sidebar-logo .logo-text-wrapper span { /* Styling untuk setiap baris teks */
        font-size: 2rem; /* Sesuaikan ukuran font jika perlu */
        font-weight: inherit; /* Ikuti ketebalan font .sidebar-logo */
    }
    nav.sidebar a {
        color: rgba(255, 255, 255, 0.9); /* DISAMAKAN */
        text-decoration: none;
        padding: 1rem 1.4rem; /* DISAMAKAN */
        border-radius: 12px; /* DISAMAKAN */
        margin-bottom: 0.8rem; /* DISAMAKAN */
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px; /* DISAMAKAN */
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1); /* DISAMAKAN */
    }
    nav.sidebar a:hover {
        background-color: rgba(255, 255, 255, 0.15); /* DISAMAKAN */
        color: var(--white); /* DISAMAKAN */
        transform: scale(1.02) translateX(5px); /* DISAMAKAN */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); /* DISAMAKAN */
    }
    nav.sidebar a.active {
        background-color: var(--accent-orange); /* DISAMAKAN */
        color: var(--primary-blue); /* DISAMAKAN */
        font-weight: 700;
        box-shadow: 0 4px 20px rgba(255, 145, 0, 0.5); /* DISAMAKAN */
        transform: scale(1.03) translateX(8px); /* DISAMAKAN */
        border-left: 6px solid var(--white); /* DISAMAKAN */
        padding-left: calc(1.4rem - 6px); /* DISAMAKAN */
    }

    /* Main Content Wrapper (DISAMAKAN DENGAN DASHBOARD.PHP) */
    main.content-wrapper {
        margin-left: 260px; /* DISAMAKAN */
        padding: 1.5rem 3.5rem 3rem 3.5rem; /* DISAMAKAN */
        min-height: 100vh;
        background: var(--neutral-light); /* DISAMAKAN */
        box-shadow: -2px 0 25px rgba(0, 0, 0, 0.08); /* DISAMAKAN */
        border-top-left-radius: 30px; /* DISAMAKAN */
        position: relative;
        z-index: 1;
        transition: margin-left 0.3s ease-in-out;
    }
    h3 {
        color: var(--primary-blue); /* DISAMAKAN */
        margin-bottom: 1.5rem; /* DISAMAKAN */
        user-select: none;
        font-size: 2.2rem; /* DISAMAKAN */
        font-weight: 800; /* DISAMAKAN */
        text-shadow: 1px 1px 4px rgba(0,0,0,0.1); /* DISAMAKAN */
        font-family: 'Montserrat', sans-serif;
    }
    /* General Card Styling (DISAMAKAN DENGAN DASHBOARD.PHP) */
    .card {
        border-radius: 25px; /* DISAMAKAN */
        box-shadow: 0 18px 50px var(--card-shadow-light); /* DISAMAKAN */
        margin-bottom: 3rem; /* DISAMAKAN */
        background-color: var(--card-bg); /* DISAMAKAN */
        border: none;
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden;
        position: relative;
        transform-style: preserve-3d;
    }
    .card:hover {
        transform: translateY(-10px); /* DISAMAKAN */
        box-shadow: 0 25px 60px var(--card-shadow-medium); /* DISAMAKAN */
    }
    
    /* Tombol Aksi (TIDAK BERUBAH) */
    .btn-action {
        border-radius: 6px;
        font-weight: 500;
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        transform: translateZ(0);
    }
    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    .btn-action i {
        font-size: 0.85rem;
    }

    /* Ripple effect for buttons (TIDAK BERUBAH) */
    .btn-action::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        width: 100px;
        height: 100px;
        margin-top: -50px;
        margin-left: -50px;
        opacity: 0;
        transform: scale(0);
        transition: transform 0.5s, opacity 0.5s;
    }
    .btn-action:active::after {
        transform: scale(1);
        opacity: 1;
        transition: 0s;
    }

    /* Form Filter Styling (DISAMAKAN DENGAN DASHBOARD.PHP) */
    .filter-section {
        background-color: var(--card-bg); /* DISAMAKAN */
        border-radius: 25px; /* DISAMAKAN */
        padding: 1.8rem 2.5rem; /* DISAMAKAN */
        margin-bottom: 2.5rem; /* DISAMAKAN */
        box-shadow: 0 8px 25px var(--card-shadow-light); /* DISAMAKAN */
        border: 1px solid var(--neutral-medium); /* DISAMAKAN */
        /* animation: fadeInSlideUp 0.8s ease-out forwards;  Dihapus jika tidak perlu animasi awal */
        /* animation-delay: 0.2s; Dihapus jika tidak perlu animasi awal */
    }
    .filter-section .form-label {
        font-weight: 600;
        color: var(--text-dark); /* DISAMAKAN */
        font-size: 0.95rem; /* DISAMAKAN */
        margin-bottom: 0.5rem;
    }
    .filter-section .form-control,
    .filter-section .form-select {
        border-radius: 12px;
        border: 2px solid var(--primary-blue-light); /* DISAMAKAN */
        padding: 0.6rem 1rem;
        font-size: 0.95rem; /* DISAMAKAN */
        transition: all 0.3s ease;
    }
    .filter-section .form-control:focus,
    .filter-section .form-select:focus {
        border-color: var(--accent-orange); /* DISAMAKAN */
        box-shadow: 0 0 0 0.3rem rgba(255, 145, 0, 0.2); /* DISAMAKAN */
    }
    .filter-section .btn {
        border-radius: 12px;
        font-weight: 600;
        padding: 0.6rem 1.2rem;
        font-size: 0.95rem;
        box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    }
    .filter-section .btn-primary {
        background-color: var(--primary-blue); /* DISAMAKAN */
        border-color: var(--primary-blue); /* DISAMAKAN */
        box-shadow: 0 5px 15px rgba(44, 82, 237, 0.3); /* DISAMAKAN */
    }
    .filter-section .btn-primary:hover {
        background-color: var(--primary-blue-light); /* DISAMAKAN */
        border-color: var(--primary-blue-light); /* DISAMAKAN */
        box-shadow: 0 7px 20px rgba(74, 119, 255, 0.4); /* DISAMAKAN */
    }
    /* Ganti warna tombol edit (DIUBAH UNTUK SESUAI DASHBOARD) */
    .filter-section .btn-outline-secondary { /* Ini untuk tombol Reset */
        border-color: var(--text-secondary); /* DISAMAKAN */
        color: var(--text-secondary); /* DISAMAKAN */
        box-shadow: 0 3px 8px rgba(0,0,0,0.1); /* DISAMAKAN */
    }
    .filter-section .btn-outline-secondary:hover { /* Ini untuk tombol Reset */
        background-color: var(--text-secondary); /* DISAMAKAN */
        color: var(--white); /* DISAMAKAN */
        box-shadow: 0 5px 12px rgba(0,0,0,0.2); /* DISAMAKAN */
    }


    /* Tabel Styling yang Ditingkatkan (VERSI SEBELUMNYA HANYA KOREKSI PERATAAN) */
    table.table {
        width: 100%;
        border-collapse: collapse; /* Menggabungkan border sel */
        user-select: none;
        box-shadow: none; 
        border-radius: 0;
        border: 1px solid #dee2e6; /* Border tabel umum */
    }

    table.table thead tr {
        background-image: linear-gradient(90deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%); /* DISAMAKAN DENGAN HEADERS DASHBOARD */
        color: var(--white);
        border-bottom: 2px solid var(--primary-blue); /* Garis bawah header */
    }

    table.table th,
    table.table td {
        padding: 15px 20px;
        vertical-align: middle; /* Memastikan keselarasan vertikal */
        font-size: 0.95rem;
        color: var(--text-dark);
        border: 1px solid #dee2e6; /* Border sel individual */
    }
    
    /* Perataan Teks untuk Header (TH) - KOREKSI PERATAAN AGAR SAMA DENGAN PERATAAN DI TD MOBILE */
    table.table th:first-child { text-align: left; } /* Bulan: rata kiri */
    table.table th:nth-child(2) { text-align: center; } /* Tahun: rata tengah */
    table.table th:nth-child(3) { text-align: left; } /* Keterangan: rata kiri */
    table.table th:nth-child(4) { text-align: center; } /* Bukti: rata tengah */
    table.table th:last-child { text-align: right; } /* Aksi: rata kanan */

    /* Perataan Konten di Sel (TD) untuk Desktop - KOREKSI PERATAAN */
    table.table td:first-child { text-align: left; } /* Bulan: rata kiri */
    table.table td:nth-child(2) { text-align: center; } /* Tahun: rata tengah */
    table.table td:nth-child(3) { text-align: left; } /* Keterangan: rata kiri */
    table.table td:nth-child(4) { text-align: center; } /* Bukti: rata tengah */
    table.table td:last-child { text-align: right; } /* Aksi: rata kanan */


    table.table tbody tr {
        background-color: var(--white);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        border-radius: 0; /* Hapus radius per baris */
        /* Menghapus margin-bottom di tbody tr */
        transition: background-color 0.3s ease;
    }
    table.table tbody tr:hover {
        background-color: #f2f2f2; /* Warna hover yang lebih sederhana */
    }
    table.table tbody tr::before {
        content: none;
    }


    /* Visualisasi Nominal dan Jenis (dari transaksi, bukan laporan) - Tetap sama */
    .status-indicator {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        margin-right: 10px;
        box-shadow: 0 0 8px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        border: 3px solid var(--white);
        position: relative;
        top: -1px;
        vertical-align: middle;
    }
    .nominal-pemasukan {
        color: var(--success-green);
        font-weight: 700;
        font-family: 'Montserrat', sans-serif;
        font-size: 1.05rem;
        text-shadow: 0.5px 0.5px 1.5px rgba(0,0,0,0.1);
        display: inline-block;
        vertical-align: middle;
        white-space: nowrap;
    }
    .nominal-pengeluaran {
        color: var(--danger-red);
        font-weight: 700;
        font-family: 'Montserrat', sans-serif;
        font-size: 1.05rem;
        text-shadow: 0.5px 0.5px 1.5px rgba(0,0,0,0.1);
        display: inline-block;
        vertical-align: middle;
        white-space: nowrap;
    }

    .bukti-img {
        width: 120px;
        height: 90px;
        object-fit: cover;
        border-radius: 15px;
        box-shadow: 0 6px 15px rgba(13,110,253,0.3);
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 3px solid rgba(13,110,253,0.1);
        display: inline-block;
        vertical-align: middle;
    }
    .bukti-img:hover {
        transform: scale(1.2) rotate(3deg) translateZ(15px);
        box-shadow: 0 8px 25px rgba(13,110,253,0.6);
        border-color: var(--accent-orange); /* DISAMAKAN */
    }
    /* Warna tombol PDF */
    table.table tbody td a.btn-outline-info { /* DIUBAH AGAR MENGGUNAKAN WARNA DASHBOARD */
        background-color: var(--primary-blue); /* Menggunakan primary blue */
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
    table.table tbody td a.btn-outline-info:hover { /* DIUBAH AGAR MENGGUNAKAN WARNA DASHBOARD */
        background-color: var(--primary-blue-light); /* Menggunakan primary blue light */
        border-color: var(--primary-blue-light);
        box-shadow: 0 5px 12px rgba(0,0,0,0.25);
        transform: translateY(-2px) translateZ(5px);
    }
    .action-buttons-group {
        display: inline-flex;
        gap: 5px;
        vertical-align: middle;
    }

    /* BARU: Warna Tombol Edit (btn-warning) */
    .btn-warning {
        background-color: var(--accent-orange); /* Menggunakan warna orange untuk edit */
        border-color: var(--accent-orange);
        color: var(--white); /* Teks putih agar kontras */
    }
    .btn-warning:hover {
        background-color: #e07b00; /* Sedikit lebih gelap saat hover */
        border-color: #e07b00;
        color: var(--white);
    }


    /* Styling untuk Modal (Tambah Laporan) - DISAMAKAN DENGAN DASHBOARD.PHP) */
    .modal-content {
        border-radius: 25px; /* DISAMAKAN */
        box-shadow: 0 20px 60px rgba(0,0,0,0.2); /* DISAMAKAN */
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        background-color: rgba(255, 255, 255, 0.95); /* DISAMAKAN */
    }
    .modal-header {
        background-image: linear-gradient(90deg, var(--primary-blue), var(--primary-blue-light)); /* DISAMAKAN */
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
    .modal-body .form-label {
        font-weight: 600;
        color: var(--text-dark); /* DISAMAKAN */
        font-size: 1rem;
        margin-bottom: 0.4rem;
        transition: all 0.3s ease;
    }
    .modal-body .form-control,
    .modal-body .form-select,
    .modal-body textarea.form-control { /* Tambahan textarea */
        border-radius: 12px;
        border: 2px solid var(--primary-blue); /* DISAMAKAN */
        padding: 0.6rem 1rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    .modal-body .form-control:focus,
    .modal-body .form-select:focus,
    .modal-body textarea.form-control:focus { /* Tambahan textarea */
        border-color: var(--accent-orange); /* DISAMAKAN */
        box-shadow: 0 0 0 0.25rem rgba(255, 145, 0, 0.25); /* DISAMAKAN */
        transform: scale(1.01);
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
    .modal-footer .btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); color: var(--white); } /* DISAMAKAN */
    .modal-footer .btn-secondary { background-color: var(--text-secondary); border-color: var(--text-secondary); color: var(--white); } /* DISAMAKAN */
    .modal-footer .btn-outline-info { 
        background-color: var(--primary-blue); /* DIUBAH agar sesuai warna primary */
        border-color: var(--primary-blue);
        color: var(--white); 
    }


    /* Responsif Umum (DISAMAKAN DENGAN DASHBOARD.PHP) */
    @media (max-width: 1200px) { /* Larger desktops/laptops */
        main.content-wrapper { padding: 2.5rem 3rem; }
    }
    @media (max-width: 992px) { /* Tablets */
        nav.sidebar {
            width: 220px;
            padding: 1.5rem 0.8rem;
        }
        main.content-wrapper {
            margin-left: 220px;
            padding: 2rem;
        }
        .sidebar-logo {
            font-size: 1.6rem;
            margin-bottom: 2rem;
        }
        .sidebar-logo .logo-icon { /* Ukuran ikon font di tablet */
            font-size: 2.5rem;
        }
        .sidebar-logo .logo-text-wrapper span {
            font-size: 1.6rem; 
        }
        nav.sidebar a {
            padding: 0.9rem 1.1rem;
            font-size: 0.95rem;
        }
        nav.sidebar a.active {
            padding-left: calc(1.1rem - 6px);
        }
        h3 {
            font-size: 2rem;
            margin-bottom: 2rem;
        }
        .card { margin-bottom: 2rem; border-radius: 20px; }
        .filter-section { padding: 1.5rem 2rem; border-radius: 20px; }
        .filter-section .form-label,
        .filter-section .form-control,
        .filter-section .btn { font-size: 0.9rem; padding: 0.6rem 1rem; }
    }

    @media (max-width: 768px) { /* Mobile */
        nav.sidebar {
            width: 220px;
            transform: translateX(-240px); /* Sembunyikan sidebar secara default */
            position: fixed; /* Penting untuk mobile */
            height: 100vh;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4);
        }
        nav.sidebar.active {
            transform: translateX(0); /* Tampilkan sidebar saat aktif */
        }
        main.content-wrapper {
            margin-left: 0; /* Tidak ada margin-left di mobile */
            padding: 1.5rem 1rem;
            border-top-left-radius: 0;
            box-shadow: none;
        }
        .toggle-sidebar-btn { /* Tombol untuk toggle sidebar di mobile */
            display: block !important;
            position: fixed;
            top: 15px; left: 15px;
            background: var(--primary-blue);
            border: none;
            color: var(--white);
            font-size: 1.8rem;
            border-radius: 12px;
            padding: 8px 12px;
            z-index: 1200;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(44, 82, 237, 0.4);
            user-select: none;
            transition: background-color 0.3s ease;
        }
        .toggle-sidebar-btn:hover {
            background: var(--primary-blue-light);
        }
        h3 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .welcome-message { /* Ini di dashboard.php, tapi untuk referensi ukuran */
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .card {
            margin-bottom: 1.5rem;
            border-radius: 18px;
            box-shadow: 0 8px 25px var(--card-shadow-light);
            transform: translateY(0) rotateX(0);
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px var(--card-shadow-medium);
        }
        
        /* Mobile Table (Flexbox structure) - KOREKSI: Lebar Label Mobile */
        table.table thead { display: none; }
        table.table tbody tr {
            display: block !important; /* Setiap baris menjadi blok penuh */
            margin-bottom: 1rem;
            padding: 0.8rem;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05) !important;
            position: relative;
        }
        table.table tbody tr:hover {
            box-shadow: 0 2px 15px rgba(0,0,0,0.1) !important;
        }
        table.table tbody td {
            display: flex; /* Menggunakan flexbox untuk setiap sel */
            align-items: center;
            padding: 0.4rem 0;
            border-bottom: 1px dashed rgba(0,0,0,0.1);
            flex-wrap: nowrap;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
            border: none; /* Hapus border sel individual di mobile */
        }
        table.table tbody td:last-child {
            border-bottom: none;
            justify-content: flex-end; /* Aksi: rata kanan */
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-direction: row;
        }
        /* Label untuk sel di mobile (dari data-label attribute) */
        table.table tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--primary-blue);
            min-width: 80px; /* Lebar minimum untuk label */
            text-align: left;
            flex-shrink: 0;
            margin-right: 10px;
        }
        /* Spesifik label width untuk mobile (sesuai kebutuhan laporan.php) */
        table.table tbody td[data-label="Bulan"]::before { content: 'Bulan:'; flex-basis: 70px; } 
        table.table tbody td[data-label="Tahun"]::before { content: 'Tahun:'; flex-basis: 70px; } 
        table.table tbody td[data-label="Keterangan"]::before { content: 'Keterangan:'; flex-basis: 90px; } 
        table.table tbody td[data-label="Bukti"]::before { content: 'Bukti:'; flex-basis: 70px; } 
        table.table tbody td[data-label="Aksi"]::before { display: none; } /* Label Aksi disembunyikan */
        
        .action-buttons-group {
            width: 100%;
            display: flex;
            justify-content: flex-end; /* Aksi: rata kanan */
            gap: 0.5rem;
        }
        /* Konten dalam TD (setelah label) bisa diberi flex-grow agar mengisi ruang */
        table.table tbody td > span, 
        table.table tbody td > div:not(.action-buttons-group),
        table.table tbody td > a:not(.btn-outline-info) { 
            flex-grow: 1;
            text-align: right;
        }
        /* Khusus untuk kolom keterangan yang mungkin panjang */
        table.table tbody td[data-label="Keterangan"] {
            flex-direction: column;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        table.table tbody td[data-label="Keterangan"]::before {
            width: 100%;
            margin-bottom: 5px;
            flex-basis: auto;
        }
        table.table tbody td[data-label="Keterangan"] > span {
            text-align: left;
            width: 100%;
        }

        /* Perbaikan untuk Bukti agar tidak terlalu ke kanan jika ada label */
        table.table tbody td[data-label="Bukti"] {
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 10px;
        }
        table.table tbody td[data-label="Bukti"]::before {
            flex-grow: 1;
            text-align: left;
        }
        table.table tbody td[data-label="Bukti"] img,
        table.table tbody td[data-label="Bukti"] a.btn-outline-info {
            flex-shrink: 0;
            margin-left: auto;
        }
    }

    @media (max-width: 576px) { /* Extra small devices */
        main.content-wrapper { padding: 1rem 0.8rem; }
        h3 { font-size: 1.5rem; }
        /* Logo di mobile kecil */
        .sidebar-logo { font-size: 1.4rem; }
        .sidebar-logo .logo-icon {
            font-size: 2.2rem;
        }
        .sidebar-logo .logo-text-wrapper span {
            font-size: 1.4rem;
        }
        .filter-section .col-md-3, .filter-section .col-md-2 { flex: 0 0 100%; max-width: 100%; margin-bottom: 0.5rem; }
        .filter-section .btn { width: 100%; }
        .filter-section .btn-outline-secondary { margin-top: 0 !important; }
    }
</style>
</head>
<body>

<button class="toggle-sidebar-btn d-md-none" aria-label="Toggle sidebar" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<nav class="sidebar" role="navigation" aria-label="Sidebar menu">
    <div class="sidebar-logo" aria-hidden="true">
        <i class="bi bi-cash-stack logo-icon"></i> 
        <div class="logo-text-wrapper">
            <span>Karang</span>
            <span>Taruna</span>
        </div>
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
        <h3>Laporan Bulanan</h3>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLaporanModal">
            <i class="bi bi-plus-circle"></i> Tambah Laporan
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card filter-section">
        <form method="GET" class="row g-3 align-items-end mb-0">
            <div class="col-md-3">
                <label for="bulan" class="form-label">Bulan</label>
                <select id="bulan" name="bulan" class="form-select">
                    <?php foreach ($bulanList as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= $val == $bulan ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tahun" class="form-label">Tahun</label>
                <select id="tahun" name="tahun" class="form-select">
                    <?php for ($i = 2022; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= ($i == date('Y')) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Tampilkan</button>
            </div>
            <div class="col-md-2">
                <a href="laporan.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
            </form>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table" id="laporanTable">
                <thead>
                    <tr>
                        <th style="width: 15%;">Bulan</th> 
                        <th style="width: 10%;">Tahun</th> 
                        <th style="width: 40%;">Keterangan</th> 
                        <th style="width: 15%;">Bukti</th> 
                        <th style="width: 20%;">Aksi</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($laporan) > 0): ?>
                        <?php
                        foreach ($laporan as $l):
                        ?>
                        <tr> 
                            <td data-label="Bulan"> <?= htmlspecialchars($l['bulan']) ?></td>
                            <td data-label="Tahun"> <?= htmlspecialchars($l['tahun']) ?></td>
                            <td data-label="Keterangan"><span><?= nl2br(htmlspecialchars($l['keterangan'])) ?></span></td>
                            <td data-label="Bukti">
                                <?php
                                $file_extension = !empty($l['bukti']) ? pathinfo($l['bukti'], PATHINFO_EXTENSION) : '';
                                $is_image = in_array(strtolower($file_extension), ['jpg','jpeg','png','gif']);
                                ?>
                                <?php if (!empty($l['bukti']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $l['bukti'])): ?>
                                    <?php if ($is_image): ?>
                                        <img src="<?= htmlspecialchars($l['bukti']) ?>" class="bukti-img" alt="Bukti"
                                            onclick="showModalBukti('<?= htmlspecialchars($l['bukti']) ?>')" tabindex="0" role="button" aria-label="Lihat bukti laporan" />
                                    <?php else: /* Asumsi PDF jika bukan gambar dan ada file */ ?>
                                        <a href="<?= htmlspecialchars($l['bukti']) ?>" target="_blank" class="btn btn-sm btn-info text-white"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi">
                                <div class="action-buttons-group">
                                    <button type="button" class="btn btn-warning btn-action" 
                                            data-bs-toggle="modal" data-bs-target="#editLaporanModal"
                                            data-id="<?= htmlspecialchars($l['id']) ?>"
                                            data-bulan-num="<?= array_search($l['bulan'], $bulanListUntukInput) ?>"
                                            data-tahun="<?= htmlspecialchars($l['tahun']) ?>"
                                            data-keterangan="<?= htmlspecialchars($l['keterangan']) ?>"
                                            data-bukti="<?= htmlspecialchars($l['bukti']) ?>"
                                            onclick="prepareEditModal(this)">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <a href="laporan.php?action=delete&id=<?= $l['id'] ?>" class="btn btn-danger btn-action"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.');">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php
                        endforeach;
                        ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Belum ada laporan untuk bulan dan tahun ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div class="modal fade" id="addLaporanModal" tabindex="-1" aria-labelledby="addLaporanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="addLaporanModalLabel">Tambah Laporan Bulanan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <form method="POST" enctype="multipart/form-data" action="laporan.php">
        <div class="modal-body">
          <input type="hidden" name="add_laporan" value="1">
          <div class="row g-3">
            <div class="col-md-6">
                <label for="bulan_laporan" class="form-label">Bulan Laporan</label>
                <select id="bulan_laporan" name="bulan_laporan" class="form-select" required>
                    <?php foreach ($bulanListUntukInput as $val => $nama): ?>
                        <option value="<?= $val ?>" <?= ($val == date('m')) ? 'selected' : '' ?>><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="tahun_laporan" class="form-label">Tahun Laporan</label>
                <select id="tahun_laporan" name="tahun_laporan" class="form-select" required>
                    <?php for ($i = 2022; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>" <?= ($i == date('Y')) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12">
                <label for="keterangan_laporan" class="form-label">Keterangan Laporan</label>
                <textarea id="keterangan_laporan" name="keterangan_laporan" class="form-control" rows="4" placeholder="Tuliskan ringkasan laporan keuangan bulanan..." required></textarea>
            </div>
            <div class="col-12">
                <label for="bukti_laporan" class="form-label">Upload Bukti (Opsional)</label>
                <input type="file" id="bukti_laporan" name="bukti_laporan" class="form-control" accept="image/*,application/pdf" />
                <div class="form-text">Format: Gambar (JPG, JPEG, PNG, GIF) atau PDF.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Batal</button>
          <button type="submit" class="btn btn-primary" name="add_laporan"><i class="bi bi-plus-circle"></i> Simpan Laporan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editLaporanModal" tabindex="-1" aria-labelledby="editLaporanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="editLaporanModalLabel">Edit Laporan Bulanan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <form method="POST" enctype="multipart/form-data" action="laporan.php">
        <div class="modal-body">
          <input type="hidden" name="edit_laporan" value="1">
          <input type="hidden" name="id_laporan" id="edit_id_laporan">
          <input type="hidden" name="bukti_lama" id="edit_bukti_lama">
          <div class="row g-3">
            <div class="col-md-6">
                <label for="bulan_laporan_edit" class="form-label">Bulan Laporan</label>
                <select id="bulan_laporan_edit" name="bulan_laporan_edit" class="form-select" required>
                    <?php foreach ($bulanListUntukInput as $val => $nama): ?>
                        <option value="<?= $val ?>"><?= $nama ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="tahun_laporan_edit" class="form-label">Tahun Laporan</label>
                <select id="tahun_laporan_edit" name="tahun_laporan_edit" class="form-select" required>
                    <?php for ($i = 2022; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12">
                <label for="keterangan_laporan_edit" class="form-label">Keterangan Laporan</label>
                <textarea id="keterangan_laporan_edit" name="keterangan_laporan_edit" class="form-control" rows="4" placeholder="Tuliskan ringkasan laporan keuangan bulanan..." required></textarea>
            </div>
            <div class="col-12">
                <label for="bukti_laporan_edit" class="form-label">Upload Bukti Baru (Opsional)</label>
                <input type="file" id="bukti_laporan_edit" name="bukti_laporan_edit" class="form-control" accept="image/*,application/pdf" />
                <div class="form-text">Biarkan kosong jika tidak ingin mengubah bukti. Format: Gambar (JPG, JPEG, PNG, GIF) atau PDF.</div>
                <div class="mt-2" id="current_bukti_info">
                    <span class="text-muted">Bukti saat ini: </span> <a href="#" id="current_bukti_link" target="_blank">Lihat Bukti</a>
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
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Batal</button>
          <button type="submit" class="btn btn-primary" name="edit_laporan"><i class="bi bi-pencil-square"></i> Perbarui Laporan</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="modalBukti" tabindex="-1" aria-labelledby="modalBuktiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="modalBuktiLabel">Preview Bukti Laporan</h5>
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
    // --- Global Variables (for consistent timing) ---
    // Animasi tabel dihapus, jadi bagian ini tidak lagi diperlukan untuk table.table tbody tr
    // const ANIMATION_DELAY_ROW_BASE = 0.07; 

    // --- Helper function for staggered animation ---
    function applyStaggeredAnimation(selector, delayMultiplier, reset = false) {
        // Fungsi ini hanya akan digunakan untuk .card, bukan untuk tabel
        document.querySelectorAll(selector).forEach((el, index) => {
            if (reset) {
                el.style.animation = 'none';
                el.offsetHeight; // Trigger reflow
                el.style.animation = null;
            }
            el.style.setProperty('--row-delay', `${index * delayMultiplier}s`);
        });
    }

    // --- Initial Animations on Load ---
    document.addEventListener('DOMContentLoaded', function() {
        // applyStaggeredAnimation('.card', 0.1); // Di laporan tidak ada card animated, jadi di skip
        // Baris untuk animasi tabel dihapus
        // applyStaggeredAnimation('#laporanTable tbody tr', ANIMATION_DELAY_ROW_BASE);

        // Menghilangkan pesan alert setelah beberapa detik
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000); // Alert akan hilang setelah 5 detik
        });
    });

    // --- Dynamic Active Sidebar Class ---
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('nav.sidebar a').forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref) {
            const fileName = linkHref.split('/').pop();
            if (fileName === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active'); // Pastikan menghapus kelas active dari yang lain
            }
        }
    });


    // Toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('nav.sidebar').classList.toggle('active');
    }
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    // Instance Modal Preview Gambar Bukti
    const modalBuktiPreview = new bootstrap.Modal(document.getElementById('modalBukti'));
    function showModalBukti(filename) {
        const img = document.getElementById('modalImage');
        const downloadLink = document.getElementById('modalDownloadLink');
        const fileExtension = filename.split('.').pop().toLowerCase();

        img.src = filename; // Path sudah lengkap (misal: uploads/namafile.jpg)
        downloadLink.href = filename; // Path sudah lengkap
        
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);

        if (isImage) {
            img.style.display = 'block';
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = 'Unduh Gambar';
        } else if (fileExtension === 'pdf') {
            img.style.display = 'none'; // Sembunyikan img untuk PDF
            window.open(filename, '_blank'); // Buka PDF di tab baru
            modalBuktiPreview.hide(); // Sembunyikan modal jika PDF dibuka di tab baru
            return; // Hentikan fungsi agar tidak menampilkan modal
        } else {
            img.style.display = 'none';
            downloadLink.style.display = 'inline-block';
            downloadLink.textContent = 'Unduh File';
        }
        
        modalBuktiPreview.show();
    }


    // --- Function to prepare Edit Modal ---
    function prepareEditModal(button) {
        const id = button.dataset.id;
        const bulanNum = button.dataset.bulanNum; // ini adalah nomor bulan (misal '01', '02')
        const tahun = button.dataset.tahun;
        const keterangan = button.dataset.keterangan;
        const bukti = button.dataset.bukti; // ini adalah path relatif

        document.getElementById('edit_id_laporan').value = id;
        document.getElementById('bulan_laporan_edit').value = bulanNum;
        document.getElementById('tahun_laporan_edit').value = tahun;
        document.getElementById('keterangan_laporan_edit').value = keterangan;
        document.getElementById('edit_bukti_lama').value = bukti; // Simpan bukti lama

        const currentBuktiInfo = document.getElementById('current_bukti_info');
        const currentBuktiLink = document.getElementById('current_bukti_link');
        const removeBuktiCheckbox = document.getElementById('remove_bukti_edit');
        
        // Reset checkbox hapus bukti
        removeBuktiCheckbox.checked = false;

        if (bukti && bukti !== 'null' && bukti !== '') { // Pastikan bukti bukan null atau string kosong
            currentBuktiInfo.style.display = 'block';
            currentBuktiLink.href = bukti; // Path sudah lengkap
            currentBuktiLink.textContent = 'Lihat Bukti Saat Ini (' + bukti.split('/').pop() + ')'; // Tampilkan nama file
            currentBuktiLink.onclick = function(e) { // Tambahkan event listener untuk link bukti lama
                e.preventDefault();
                const fileExtension = bukti.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
                if (isImage) {
                    showModalBukti(bukti); // Tampilkan di modal jika gambar
                } else {
                    window.open(bukti, '_blank'); // Buka di tab baru jika PDF
                }
            };
            // Pastikan jika ada bukti, checkbox hapus tidak disable.
            removeBuktiCheckbox.disabled = false;
        } else {
            currentBuktiInfo.style.display = 'none'; // Sembunyikan jika tidak ada bukti
            currentBuktiLink.href = '#';
            currentBuktiLink.textContent = ''; // Kosongkan teks
            currentBuktiLink.onclick = null; // Hapus event listener
            // Jika tidak ada bukti, checkbox hapus harus disable (tidak ada yang bisa dihapus)
            removeBuktiCheckbox.disabled = true;
        }
    }


    // --- Ripple Effect for Buttons ---
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            // Check if it's a delete button, if so, let the confirm handle it
            if (this.classList.contains('btn-danger') && this.getAttribute('onclick') && !e.target.closest('.modal-footer')) {
                    // Only trigger ripple if confirm was successful (or if not a delete button)
                    if (confirm('Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.')) {
                        // If confirmed, proceed with default action (which is the link href)
                        // No need for ripple here, as page will redirect.
                        return;
                    } else {
                        // If cancelled, prevent default action
                        e.preventDefault();
                        return;
                    }
            }

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