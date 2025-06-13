<?php
session_start();
require_once 'koneksi.php'; // Memanggil file koneksi database

// Cek login. Pastikan hanya user yang login yang bisa akses API
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inisialisasi variabel untuk menampung error (untuk debugging API)
$api_error_message = '';

// Fungsi pembantu untuk eksekusi prepared statement dengan error handling
function executeStatement($conn, $sql, $bindTypes = '', $bindParams = []) {
    global $api_error_message; 

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $api_error_message = "Error preparing statement: " . $conn->error . " SQL: " . $sql;
        error_log($api_error_message);
        return false;
    }

    if (!empty($bindParams)) {
        $tmpParams = [];
        foreach ($bindParams as $key => $value) {
            $tmpParams[$key] = &$bindParams[$key];
        }
        array_unshift($tmpParams, $bindTypes);
        call_user_func_array([$stmt, 'bind_param'], $tmpParams);
    }
    
    if (!$stmt->execute()) {
        $api_error_message = "Error executing statement: " . $stmt->error . " SQL: " . $sql;
        error_log($api_error_message);
        $stmt->close();
        return false;
    }
    return $stmt;
}

// Ambil filter tanggal dari GET request (jika ada, untuk filter chart)
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

// --- Contoh Perhitungan Presentase ---
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

// Jika ada filter tanggal dari request API, gunakan itu untuk grafik
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

$conn->close(); 

// Siapkan data untuk respons JSON
$responseData = [
    'status' => 'success',
    'totalPemasukan' => number_format($totalPemasukan, 0, ',', '.'),
    'persenPemasukan' => $persenPemasukan,
    'totalPengeluaran' => number_format($totalPengeluaran, 0, ',', '.'),
    'persenPengeluaran' => $persenPengeluaran,
    'saldoAkhir' => number_format($saldoAkhir, 0, ',', '.'),
    'persenSaldoUntukProgressBar' => $persenSaldoUntukProgressBar,
    'persenSaldoDariTotal' => ($saldoAkhir >= 0 ? '' : '-') . abs($persenSaldoDariTotal),
    'chartLabels' => $labels,
    'chartDataPemasukan' => $dataPemasukanBulanan,
    'chartDataPengeluaran' => $dataPengeluaranBulanan,
    'error' => $api_error_message // Sertakan pesan error PHP jika ada
];

// Set header Content-Type agar browser tahu ini adalah JSON
header('Content-Type: application/json');
echo json_encode($responseData);
?>