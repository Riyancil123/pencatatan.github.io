<?php
// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php'; // Memanggil file koneksi database

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil username user yang sedang login
// Menggunakan 'nama_lengkap' jika tersedia, jika tidak 'email', jika tidak 'User'.
$loggedInUsername = $_SESSION['nama_lengkap'] ?? $_SESSION['email'] ?? 'User';

// Inisialisasi variabel untuk menampung error yang akan ditampilkan di HTML
$php_error_message = '';

// --- Logika Pengambilan dan Perhitungan Data Dashboard ---
// Ambil filter tanggal dari form, jika ada
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Inisialisasi klausa WHERE.
$whereClause = " WHERE 1=1";
$bindTypes = "";
$bindParams = [];

if ($startDate && $endDate) {
    $whereClause .= " AND tanggal BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindParams[] = $startDate;
    $bindParams[] = $endDate;
}

// Fungsi pembantu untuk eksekusi prepared statement dengan error handling
function executeStatement($conn, $sql, $bindTypes = '', $bindParams = []) {
    global $php_error_message; // Akses variabel global untuk pesan error

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $php_error_message = "Error preparing statement: " . $conn->error . " SQL: " . $sql;
        error_log($php_error_message);
        return false; // Mengembalikan false jika persiapan gagal
    }

    if (!empty($bindParams)) {
        // Menggunakan reference for bind_param (penting untuk PHP < 8.0 tanpa spread operator)
        // Alternatif untuk PHP 8.0+: $stmt->bind_param($bindTypes, ...$bindParams);
        $tmpParams = [];
        foreach ($bindParams as $key => $value) {
            $tmpParams[$key] = &$bindParams[$key]; // Pass by reference
        }
        array_unshift($tmpParams, $bindTypes);
        call_user_func_array([$stmt, 'bind_param'], $tmpParams);
    }
    
    if (!$stmt->execute()) {
        $php_error_message = "Error executing statement: " . $stmt->error . " SQL: " . $sql;
        error_log($php_error_message);
        $stmt->close();
        return false;
    }
    return $stmt;
}


// Hitung Total Pemasukan
$totalPemasukan = 0;
$sqlPemasukan = "SELECT SUM(nominal) AS total FROM transaksi" . $whereClause . " AND jenis = 'pemasukan'";
$stmtPemasukan = executeStatement($conn, $sqlPemasukan, $bindTypes, $bindParams);
if ($stmtPemasukan) {
    $resultPemasukan = $stmtPemasukan->get_result();
    if ($rowPemasukan = $resultPemasukan->fetch_assoc()) {
        $totalPemasukan = $rowPemasukan['total'] ?? 0;
    }
    $stmtPemasukan->close();
}


// Hitung Total Pengeluaran
$totalPengeluaran = 0;
$sqlPengeluaran = "SELECT SUM(nominal) AS total FROM transaksi" . $whereClause . " AND jenis = 'pengeluaran'";
$stmtPengeluaran = executeStatement($conn, $sqlPengeluaran, $bindTypes, $bindParams);
if ($stmtPengeluaran) {
    $resultPengeluaran = $stmtPengeluaran->get_result();
    if ($rowPengeluaran = $resultPengeluaran->fetch_assoc()) {
        $totalPengeluaran = $rowPengeluaran['total'] ?? 0;
    }
    $stmtPengeluaran->close();
}


// Hitung Saldo Akhir (dalam periode filter atau keseluruhan)
$saldoAkhir = $totalPemasukan - $totalPengeluaran;

// --- Contoh Perhitungan Presentase (perlu disesuaikan dengan kebutuhan) ---
$targetPemasukan = 5000000; 
$targetPengeluaran = 3000000; 

$totalGlobalTransaksi = 0;
$stmtGlobalTotal = executeStatement($conn, "SELECT SUM(nominal) AS total FROM transaksi");
if ($stmtGlobalTotal) {
    $resultGlobalTotal = $stmtGlobalTotal->get_result();
    if ($rowGlobalTotal = $resultGlobalTotal->fetch_assoc()) {
        $totalGlobalTransaksi = $rowGlobalTotal['total'] ?? 0;
    }
    $stmtGlobalTotal->close();
}


$persenPemasukan = ($targetPemasukan > 0) ? round(($totalPemasukan / $targetPemasukan) * 100, 2) : 0;
$persenPengeluaran = ($targetPengeluaran > 0) ? round(($totalPengeluaran / $targetPengeluaran) * 100, 2) : 0;
$persenSaldoDariTotal = ($totalGlobalTransaksi > 0) ? round(($saldoAkhir / $totalGlobalTransaksi) * 100, 2) : 0;

$persenPemasukan = min(100, $persenPemasukan);
$persenPengeluaran = min(100, $persenPengeluaran);
$persenSaldoUntukProgressBar = max(0, min(100, $persenSaldoDariTotal));


// --- Data untuk Grafik Tren Keuangan (per bulan) ---
$labels = []; 
$dataPemasukanBulanan = []; 
$dataPengeluaranBulanan = []; 

$startGraphMonth = date('Y-m-01', strtotime('-5 months')); 
$endGraphMonth = date('Y-m-t'); 

if ($startDate && $endDate) {
    $startGraphMonth = date('Y-m-01', strtotime($startDate));
    $endGraphMonth = date('Y-m-t', strtotime($endDate));
}

$currentGraphMonth = $startGraphMonth;
while (strtotime($currentGraphMonth) <= strtotime($endGraphMonth)) {
    $monthYear = date('Y-m', strtotime($currentGraphMonth));
    $labels[] = date('M Y', strtotime($currentGraphMonth)); 

    $sqlMonthPemasukan = "SELECT SUM(nominal) AS total FROM transaksi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? AND jenis = 'pemasukan'";
    $stmtMonthPemasukan = executeStatement($conn, $sqlMonthPemasukan, "s", [$monthYear]);
    if ($stmtMonthPemasukan) {
        $resultMonthPemasukan = $stmtMonthPemasukan->get_result();
        $rowMonthPemasukan = $resultMonthPemasukan->fetch_assoc();
        $dataPemasukanBulanan[] = $rowMonthPemasukan['total'] ?? 0;
        $stmtMonthPemasukan->close();
    }


    $sqlMonthPengeluaran = "SELECT SUM(nominal) AS total FROM transaksi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? AND jenis = 'pengeluaran'";
    $stmtMonthPengeluaran = executeStatement($conn, $sqlMonthPengeluaran, "s", [$monthYear]);
    if ($stmtMonthPengeluaran) {
        $resultMonthPengeluaran = $stmtMonthPengeluaran->get_result();
        $rowMonthPengeluaran = $resultMonthPengeluaran->fetch_assoc();
        $dataPengeluaranBulanan[] = $rowMonthPengeluaran['total'] ?? 0;
        $stmtMonthPengeluaran->close();
    }

    $currentGraphMonth = date('Y-m-01', strtotime($currentGraphMonth . ' +1 month'));
}

// Konversi data PHP ke JSON untuk JavaScript
$chartLabelsJson = json_encode($labels);
$chartDataPemasukanJson = json_encode($dataPemasukanBulanan);
$chartDataPengeluaranJson = json_encode($dataPengeluaranBulanan);

$conn->close(); 

// Fungsi untuk menentukan kelas 'active' pada sidebar
function isActive($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage === $pageName) ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Dashboard Keuangan Karang Taruna - Juara!</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Global Color Palette & Variables */
    :root {
        --primary-blue: #2c52ed; /* Deep Blue, slightly darker and richer */
        --primary-blue-light: #4a77ff; /* Lighter shade of primary blue */
        --accent-green: #28a745; /* Success green */
        --accent-red: #dc3545; /* Danger red */
        --accent-orange: #ff9100; /* Warm orange for saldo */
        --neutral-light: #f0f2f5; /* Very light gray for background */
        --neutral-medium: #e0e5ec; /* Medium gray */
        --text-dark: #333d47; /* Darker text for better contrast */
        --text-secondary: #6b7a8d; /* Muted text */
        --white: #ffffff;
        --card-bg: #ffffff;
        --card-shadow-light: rgba(0, 0, 0, 0.05);
        --card-shadow-medium: rgba(0, 0, 0, 0.1);
        --card-shadow-strong: rgba(0, 0, 0, 0.15);
    }

    /* Base Body Styling */
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

    /* Loader Overlay */
    .loader-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.98); /* Almost opaque white */
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        z-index: 9999;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.6s ease-out, visibility 0.6s ease-out;
    }
    .loader-overlay.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none; /* Allows interaction beneath after fading */
    }
    .spinner-border {
        color: var(--primary-blue);
        width: 3.5rem; height: 3.5rem;
        border-width: 0.35em;
    }
    .loader-text {
        color: var(--primary-blue);
        font-size: 1.3rem;
        font-weight: 600;
        margin-top: 1.5rem;
        animation: pulseText 1.5s infinite alternate;
    }
    @keyframes pulseText {
        from { opacity: 0.7; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1.05); }
    }

    /* Sidebar Styling */
    nav.sidebar {
        background-image: linear-gradient(180deg, var(--primary-blue), var(--primary-blue-light));
        min-height: 100vh;
        width: 260px; /* Slightly wider */
        position: fixed;
        top: 0; left: 0;
        padding: 2rem 1.2rem;
        color: var(--white);
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 20px var(--card-shadow-medium);
        z-index: 1000;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
    }
    .sidebar-logo {
        font-family: 'Montserrat', sans-serif;
        font-size: 2rem; /* Larger logo text */
        font-weight: 900; /* Extra bold */
        margin-bottom: 3rem;
        display: flex;
        align-items: center;
        gap: 10px;
        user-select: none;
        padding: 0.5rem 0;
        text-shadow: 2px 2px 5px rgba(0,0,0,0.3); /* Stronger shadow */
        color: var(--white);
    }
    .sidebar-logo i {
        font-size: 3rem; /* Larger icon */
        color: var(--accent-orange); /* Use accent orange */
        text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
    }
    nav.sidebar a {
        color: rgba(255, 255, 255, 0.9); /* Slightly more opaque */
        text-decoration: none;
        padding: 1rem 1.4rem; /* More padding */
        border-radius: 12px; /* More rounded */
        margin-bottom: 0.8rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    nav.sidebar a:hover {
        background-color: rgba(255, 255, 255, 0.15); /* Subtle white overlay */
        color: var(--white);
        transform: scale(1.02) translateX(5px); /* Scale and slide */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }
    nav.sidebar a.active {
        background-color: var(--accent-orange); /* Active color is accent orange */
        color: var(--primary-blue); /* Dark blue text on active */
        font-weight: 700;
        box-shadow: 0 4px 20px rgba(255, 145, 0, 0.5); /* Stronger active shadow */
        transform: scale(1.03) translateX(8px); /* More pronounced active slide */
        border-left: 6px solid var(--white); /* White border for active */
        padding-left: calc(1.4rem - 6px);
    }

    /* Main Content Wrapper */
    main.content-wrapper {
        margin-left: 260px; /* Adjust for wider sidebar */
        padding: 1.5rem 3.5rem 3rem 3.5rem; /* Padding atas diperkecil menjadi 1.5rem */
        min-height: 100vh;
        background: var(--neutral-light);
        box-shadow: -2px 0 25px rgba(0, 0, 0, 0.08);
        border-top-left-radius: 30px; /* More rounded corner */
        position: relative;
        z-index: 1;
    }
    h3 {
        color: var(--primary-blue);
        margin-bottom: 1.5rem; /* Margin bawah diperkecil */
        user-select: none;
        font-size: 2.2rem; /* Ukuran judul diperkecil */
        font-weight: 800;
        text-shadow: 1px 1px 4px rgba(0,0,0,0.1);
        font-family: 'Montserrat', sans-serif;
    }
    /* General Card Styling */
    .card {
        border-radius: 25px; /* More rounded corners */
        box-shadow: 0 18px 50px var(--card-shadow-light); /* Softer, wider shadow */
        margin-bottom: 3rem; /* More bottom margin */
        background-color: var(--card-bg);
        border: none;
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden;
        position: relative;
        transform-style: preserve-3d;
    }
    .card:hover {
        transform: translateY(-10px); /* Lift higher */
        box-shadow: 0 25px 60px var(--card-shadow-medium); /* More pronounced shadow */
    }

    /* Statistic Cards (Refined Design) */
    .card-statistic {
        text-align: center;
        padding: 0.5rem; /* Padding lebih kecil lagi */
        position: relative;
        z-index: 1;
        background-color: var(--card-bg);
        overflow: visible;
        padding-top: 40px; /* Sesuaikan untuk lingkaran yang lebih kecil */
        border: 1px solid var(--neutral-medium);
    }

    /* The glowing circular element with image */
    .card-statistic-circle {
        position: absolute;
        top: -30px; /* Angkat sedikit lagi */
        left: 50%;
        transform: translate(-50%, 0);
        width: 110px; /* Ukuran lingkaran diperkecil */
        height: 110px;
        border-radius: 50%;
        display: flex; /* Untuk memusatkan ikon */
        justify-content: center;
        align-items: center;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25); /* Sesuaikan bayangan */
        z-index: 2;
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden; /* Hide overflowing parts of the image */
        border: 3px solid var(--white); /* Border putih lebih tipis */
    }
    .card-statistic:hover .card-statistic-circle {
        transform: translate(-50%, -10px) scale(1.08); /* Lift and scale more on hover */
        box-shadow: 0 12px 35px rgba(0,0,0,0.4);
    }
    /* GAYA IMG DIHAPUS KARENA TAG IMG DIHILANGKAN DARI HTML */
    /* Icon inside the circle */
    .card-statistic .icon {
        font-size: 3.5rem; /* Ukuran ikon diperkecil agar pas */
        color: var(--white); /* Ikon putih */
        text-shadow: 2px 2px 5px rgba(0,0,0,0.5);
        transition: all 0.3s ease;
    }
    .card-statistic:hover .icon {
        transform: scale(1.1) rotate(5deg);
    }

    /* Text elements inside statistic cards */
    .card-statistic h5 {
        font-family: 'Montserrat', sans-serif;
        font-size: 1.1rem; /* Ukuran judul diperkecil */
        color: var(--text-secondary);
        margin-top: 35px; /* Sesuaikan jarak dari lingkaran */
        margin-bottom: 0.6rem; /* Sesuaikan margin */
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
    }
    .card-statistic p.amount {
        font-size: 2.5rem; /* Ukuran nominal diperkecil */
        font-weight: 900;
        color: var(--text-dark);
        margin-bottom: 1rem; /* Sesuaikan margin */
        text-shadow: 1px 1px 6px rgba(0,0,0,0.1);
        display: flex;
        justify-content: center;
        align-items: baseline;
        gap: 3px; /* Jarak antara "Rp" dan angka diperkecil */
    }
    .card-statistic p.amount .currency-symbol {
        font-size: 0.4em; /* Ukuran "Rp" lebih kecil lagi */
        font-weight: 600;
        color: var(--text-secondary);
        margin-right: 0;
        position: relative;
        top: -0.1em;
    }
    .card-statistic p.percentage {
        font-size: 1rem; /* Ukuran persentase diperkecil */
        font-weight: 600;
        margin-top: 0.6rem; /* Sesuaikan margin */
        display: block;
        color: var(--text-secondary);
        text-shadow: none;
    }

    /* Specific color accents for each card */
    /* background-color ditaruh di sini untuk card-statistic-circle */
    .card-statistic.pemasukan .card-statistic-circle { background-color: var(--accent-green); }
    .card-statistic.pengeluaran .card-statistic-circle { background-color: var(--accent-red); }
    .card-statistic.saldo .card-statistic-circle { background-color: var(--accent-orange); }

    .card-statistic.pemasukan h5 { color: var(--accent-green); }
    .card-statistic.pengeluaran h5 { color: var(--accent-red); }
    .card-statistic.saldo h5 { color: var(--accent-orange); }

    /* Progress bar */
    .progress {
        height: 10px; /* Tinggi progress bar diperkecil */
        border-radius: 6px;
        background-color: var(--neutral-medium);
        margin-top: 1.2rem;
        box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    .progress-bar {
        border-radius: 6px;
        transition: width 1s ease-in-out;
        background-image: linear-gradient(45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%, transparent);
        background-size: 1.5rem 1.5rem;
        animation: progress-stripe 2s linear infinite;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    @keyframes progress-stripe {
        0% { background-position: 1.5rem 0; }
        100% { background-position: 0 0; }
    }
    .progress-pemasukan .progress-bar { background-color: var(--accent-green); }
    .progress-pengeluaran .progress-bar { background-color: var(--danger-red); }
    .progress-saldo .progress-bar { background-color: var(--accent-orange); }

    /* Filter Section */
    .filter-section {
        background-color: var(--card-bg);
        border-radius: 25px;
        padding: 1.8rem 2.5rem;
        margin-bottom: 2.5rem;
        box-shadow: 0 8px 25px var(--card-shadow-light);
        border: 1px solid var(--neutral-medium);
        animation: fadeInSlideUp 0.8s ease-out forwards;
        animation-delay: 0.9s;
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

    /* Chart Section */
    .chart-section {
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        font-style: italic;
        flex-direction: column;
        padding: 1.5rem;
    }
    .chart-section canvas {
        max-width: 100%;
        height: 350px;
        transition: all 0.5s ease-in-out;
    }
    .chart-section canvas:hover {
        transform: scale(1.01);
    }

    /* Welcome Message */
    .welcome-message {
        font-size: 1.1rem; /* Ukuran welcome message disesuaikan */
        margin-bottom: 1rem; /* Margin bawah diperkecil */
    }

    /* Animations */
    .card-animated {
        opacity: 0;
        transform: translateY(30px);
        animation: slideUpFadeIn 0.8s ease-out forwards;
    }
    .card-statistic:nth-child(1).card-animated { animation-delay: 0.6s; }
    .card-statistic:nth-child(2).card-animated { animation-delay: 0.7s; }
    .card-statistic:nth-child(3).card-animated { animation-delay: 0.8s; }
    .filter-section.card-animated { animation-delay: 0.9s; }
    .chart-section-card.card-animated { animation-delay: 1.0s; }


    @keyframes slideUpFadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }


    /* Responsive Adjustments */
    @media (max-width: 1200px) { /* Larger desktops/laptops */
        main.content-wrapper {
            padding: 2.5rem 3rem;
        }
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
        .sidebar-logo i {
            font-size: 2.5rem;
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
        .card {
            margin-bottom: 2rem;
            border-radius: 20px;
        }
        /* Statistik cards pada tablet */
        .card-statistic {
            padding-top: 35px;
        }
        .card-statistic-circle {
            width: 100px; height: 100px;
            top: -25px;
        }
        .card-statistic .icon {
            font-size: 3rem;
        }
        .card-statistic h5 {
            font-size: 1.1rem;
            margin-top: 30px;
        }
        .card-statistic p.amount {
            font-size: 2.5rem;
        }
        .filter-section {
            padding: 1.5rem 2rem;
            border-radius: 20px;
        }
        .filter-section .form-label,
        .filter-section .form-control,
        .filter-section .btn {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
        }
        .chart-section {
            min-height: 350px;
            padding: 1.5rem;
        }
        .chart-section canvas {
            height: 300px;
        }
        .welcome-message {
            font-size: 1.1rem;
        }
    }

    @media (max-width: 768px) { /* Mobile */
        nav.sidebar {
            width: 220px;
            transform: translateX(-240px);
            position: fixed;
            height: 100vh;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4);
        }
        nav.sidebar.active {
            transform: translateX(0);
        }
        main.content-wrapper {
            margin-left: 0;
            padding: 1.5rem 1rem;
            border-top-left-radius: 0;
            box-shadow: none;
        }
        .toggle-sidebar-btn {
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
        .welcome-message {
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
        /* Statistik cards pada mobile */
        .card-statistic {
            padding-top: 30px;
        }
        .card-statistic-circle {
            width: 90px; height: 90px;
            top: -20px;
        }
        .card-statistic .icon {
            font-size: 2.8rem;
        }
        .card-statistic h5 {
            font-size: 1rem;
            margin-top: 25px;
        }
        .card-statistic p.amount {
            font-size: 2.2rem;
        }
        .filter-section .row.g-3 > div {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .filter-section .btn {
            width: 100%;
            margin-top: 1rem;
        }
    }

    @media (max-width: 576px) { /* Smaller Mobile */
        main.content-wrapper {
            padding: 1rem;
        }
        h3 {
            font-size: 1.5rem;
        }
        .sidebar-logo {
            font-size: 1.4rem;
        }
        .sidebar-logo i {
            font-size: 2.2rem;
        }
        .card-statistic-circle {
            width: 70px; height: 70px;
            top: -15px;
        }
        .card-statistic .icon {
            font-size: 2.2rem;
        }
        .card-statistic h5 {
            font-size: 0.9rem;
            margin-top: 20px;
        }
        .card-statistic p.amount {
            font-size: 1.8rem;
        }
        .welcome-message {
            font-size: 0.95rem;
        }
    }
</style>
</head>
<body>

<div class="loader-overlay" id="loaderOverlay">
    <div class="text-center">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="loader-text">Memuat Dashboard...</p>
    </div>
</div>

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
    <p class="welcome-message">Selamat datang kembali, <span><?= htmlspecialchars($loggedInUsername) ?></span>!</p>
    
    <?php if (!empty($php_error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Terjadi kesalahan fatal: <?= htmlspecialchars($php_error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h3>Dashboard Keuangan</h3>

    <div class="filter-section card card-animated">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            <div class="col-md-5 col-lg-4">
                <label for="start_date" class="form-label">Tanggal Mulai:</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-5 col-lg-4">
                <label for="end_date" class="form-label">Tanggal Akhir:</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-2 col-lg-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
            </div>
            <div class="col-md-2 col-lg-2">
                <a href="dashboard.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card card-statistic pemasukan card-animated">
                <div class="card-statistic-circle">
                    <i class="bi bi-currency-dollar icon"></i>
                </div>
                <h5>Total Pemasukan</h5>
                <p class="amount"><span class="currency-symbol">Rp</span><?= number_format($totalPemasukan, 0, ',', '.') ?></p>
                <div class="progress progress-pemasukan">
                    <div class="progress-bar" role="progressbar" style="width: <?= $persenPemasukan ?>%;" aria-valuenow="<?= $persenPemasukan ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="percentage"><?= $persenPemasukan ?>% dari Target</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card card-statistic pengeluaran card-animated">
                <div class="card-statistic-circle">
                    <i class="bi bi-graph-down-arrow icon"></i>
                </div>
                <h5>Total Pengeluaran</h5>
                <p class="amount"><span class="currency-symbol">Rp</span><?= number_format($totalPengeluaran, 0, ',', '.') ?></p>
                <div class="progress progress-pengeluaran">
                    <div class="progress-bar" role="progressbar" style="width: <?= $persenPengeluaran ?>%;" aria-valuenow="<?= $persenPengeluaran ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="percentage"><?= $persenPengeluaran ?>% dari Anggaran</p>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card card-statistic saldo card-animated">
                <div class="card-statistic-circle">
                    <i class="bi bi-wallet2 icon"></i>
                </div>
                <h5>Saldo Akhir</h5>
                <p class="amount"><span class="currency-symbol">Rp</span><?= number_format($saldoAkhir, 0, ',', '.') ?></p>
                <div class="progress progress-saldo">
                    <div class="progress-bar" role="progressbar" style="width: <?= $persenSaldoUntukProgressBar ?>%;" aria-valuenow="<?= $persenSaldoUntukProgressBar ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="percentage"><?= ($saldoAkhir >= 0 ? '' : '-') . abs($persenSaldoDariTotal) ?>% dari Total Transaksi</p>
            </div>
        </div>
    </div>

    <div class="card chart-section-card card-animated">
        <h5 class="card-title text-primary fw-bold mb-3">Analisis Tren Keuangan</h5>
        <div class="chart-section">
            <canvas id="myChart"></canvas>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- Data dari PHP untuk Chart.js ---
    const chartLabels = <?= $chartLabelsJson ?>;
    const chartDataPemasukan = <?= $chartDataPemasukanJson ?>;
    const chartDataPengeluaran = <?= $chartDataPengeluaranJson ?>;

    // --- JavaScript untuk Loader ---
    document.addEventListener('DOMContentLoaded', function() {
        const loaderOverlay = document.getElementById('loaderOverlay');
        if (loaderOverlay) {
            loaderOverlay.classList.add('hidden');
        }
    });
    window.addEventListener('load', function() {
        const loaderOverlay = document.getElementById('loaderOverlay');
        if (loaderOverlay && !loaderOverlay.classList.contains('hidden')) {
            loaderOverlay.classList.add('hidden');
        }
    });

    // --- JavaScript untuk Validasi Filter Tanggal ---
    document.getElementById('filterForm').addEventListener('submit', function(event) {
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        if (startDateInput.value && endDateInput.value && new Date(startDateInput.value) > new Date(endDateInput.value)) {
            alert('Tanggal mulai tidak boleh lebih besar dari tanggal akhir!');
            event.preventDefault(); // Mencegah form disubmit
        }
    });

    // --- Dynamic Active Sidebar Class ---
    const currentPageForSidebar = window.location.pathname.split('/').pop();
    document.querySelectorAll('nav.sidebar a').forEach(link => {
        const linkHref = link.getAttribute('href');
        if (linkHref) {
            const fileName = linkHref.split('/').pop();
            if (fileName === currentPageForSidebar) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }
    });

    // --- Toggle Sidebar ---
    function toggleSidebar() {
        document.querySelector('nav.sidebar').classList.toggle('active');
    }
    document.querySelector('.toggle-sidebar-btn').addEventListener('click', toggleSidebar);

    // --- Inisialisasi Chart.js ---
    const ctx = document.getElementById('myChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Pemasukan',
                    data: chartDataPemasukan,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                    barThickness: 25,
                },
                {
                    label: 'Pengeluaran',
                    data: chartDataPengeluaran,
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                    barThickness: 25,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1800,
                    easing: 'easeInOutQuart'
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Poppins',
                                size: 13,
                                weight: '600'
                            },
                            color: 'var(--text-dark)'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.08)'
                        },
                        ticks: {
                            callback: function(value, index, ticks) {
                                return 'Rp' + value.toLocaleString('id-ID');
                            },
                            font: {
                                family: 'Poppins',
                                size: 13,
                                weight: '500'
                            },
                            color: 'var(--text-secondary)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Poppins',
                                size: 15,
                                weight: '600'
                            },
                            color: 'var(--text-dark)',
                            padding: 25
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        titleFont: {
                            family: 'Poppins',
                            size: 15,
                            weight: '700'
                        },
                        bodyFont: {
                            family: 'Poppins',
                            size: 13,
                            weight: '500'
                        },
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'Rp' + context.parsed.y.toLocaleString('id-ID');
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
</script>

</body>
</html>