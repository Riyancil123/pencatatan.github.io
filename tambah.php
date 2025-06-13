<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$bulanList = [
  '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
  '04' => 'April', '05' => 'Mei', '06' => 'Juni',
  '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
  '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $bulan = $_POST['bulan']; // ini masih dalam format angka (01 - 12)
  $tahun = $_POST['tahun'];
  $nama_bulan = $bulanList[$bulan]; // konversi ke nama bulan Indonesia
  $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
  $bukti = '';

  if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
    $uploadDir = 'uploads/';
    $filename = basename($_FILES['bukti']['name']);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $newName = uniqid() . '.' . $ext;
    $uploadPath = $uploadDir . $newName;

    if (move_uploaded_file($_FILES['bukti']['tmp_name'], $uploadPath)) {
      $bukti = $newName;
    } else {
      $error = "Gagal mengunggah bukti.";
    }
  }

  if (!$error) {
    $query = "INSERT INTO laporan (bulan, tahun, keterangan, bukti) VALUES ('$nama_bulan', '$tahun', '$keterangan', '$bukti')";
    if (mysqli_query($conn, $query)) {
      header("Location: laporan.php?bulan=$bulan&tahun=$tahun");
      exit();
    } else {
      $error = "Gagal menyimpan laporan: " . mysqli_error($conn);
    }
  }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Tambah Laporan - Karang Taruna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f4f6f8; }
    .sidebar {
      background-color: #0d6efd;
      color: white;
      min-height: 100vh;
    }
    .sidebar h4 {
      padding: 20px 0;
      margin: 0;
      background-color: #0b5ed7;
    }
    .sidebar a {
      color: white;
      text-decoration: none;
      display: block;
      padding: 12px 20px;
    }
    .sidebar a:hover, .sidebar a.active {
      background-color: #0b5ed7;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 sidebar p-0">
      <h4 class="text-center">Karang Taruna</h4>
      <a href="index.php">Dashboard</a>
      <a href="users.php">Manajemen Pengguna</a>
      <a href="transaksi.php">Pencatatan Keuangan</a>
      <a href="laporan.php">Laporan Bulanan</a>
      <a href="tambah_laporan.php" class="active">Tambah Laporan</a>
      <a href="logout.php">Logout</a>
    </div>

    <!-- Konten -->
    <div class="col-md-9 col-lg-10 p-4">
      <h3 class="mb-4">Tambah Laporan Bulanan</h3>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Bulan</label>
          <select name="bulan" class="form-select" required>
            <?php
            foreach ($bulanList as $num => $nama) {
              echo "<option value='$num'>$nama</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Tahun</label>
          <select name="tahun" class="form-select" required>
            <?php
            for ($i = 2022; $i <= date('Y'); $i++) {
              echo "<option value='$i'>$i</option>";
            }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Keterangan</label>
          <textarea name="keterangan" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Bukti (opsional)</label>
          <input type="file" name="bukti" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Simpan Laporan</button>
        <a href="laporan.php" class="btn btn-secondary">Kembali</a>
      </form>
    </div>
  </div>
</div>

</body>
</html>
