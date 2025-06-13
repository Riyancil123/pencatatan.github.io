<?php
// Pastikan tidak ada spasi, karakter, atau baris kosong di atas tag <?php ini
session_start();
require_once 'koneksi.php'; // Pastikan koneksi.php tersedia
require_once 'utils.php'; // Pastikan utils.php sudah ada dan benar, atau hapus jika tidak diperlukan

// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inisialisasi variabel untuk menampung pesan status/error
$success_message = '';
$error_message = '';

// Inisialisasi status koneksi
$conn_status = false;
if ($conn instanceof mysqli && !$conn->connect_error) {
    $conn_status = true;
} else {
    $error_message = "Koneksi database GAGAL: " . ($conn instanceof mysqli ? $conn->connect_error : "Objek koneksi tidak terbentuk.");
    error_log($error_message);
}

// Cek login (jika halaman ini memerlukan login)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Logika untuk menampilkan pesan dari redirect setelah upload atau hapus
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'upload_success') {
        $success_message = "File berhasil diunggah!";
    } elseif ($_GET['status'] === 'upload_fail') {
        $error_message = "Gagal mengunggah file. " . ($_GET['msg'] ?? '');
    } elseif ($_GET['status'] === 'delete_success') {
        $success_message = "Dokumen berhasil dihapus!";
    } elseif ($_GET['status'] === 'delete_fail') {
        $error_message = "Gagal menghapus dokumen. " . ($_GET['msg'] ?? '');
    }
}

// --- Logika Penanganan Upload File ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dokumen']) && $conn_status) {
    // Tentukan direktori upload relatif dari root web
    // Pastikan ini hanya nama folder, bukan path absolut sistem
    $upload_dir_relative = 'uploads/';
    // Tentukan direktori upload absolut di sistem file server
    $upload_dir_absolute = __DIR__ . DIRECTORY_SEPARATOR . $upload_dir_relative;

    $upload_success_flag = true;
    $upload_error_msg = '';

    // Buat direktori jika belum ada
    if (!is_dir($upload_dir_absolute)) {
        if (!mkdir($upload_dir_absolute, 0755, true)) {
            $upload_error_msg = "Gagal membuat direktori upload: " . $upload_dir_absolute . ". Pastikan izin server.";
            $upload_success_flag = false;
        }
    }

    if ($upload_success_flag && isset($_FILES['dokumen_file']) && $_FILES['dokumen_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['dokumen_file']['tmp_name'];
        $file_name_original = $_FILES['dokumen_file']['name'];
        $file_size = $_FILES['dokumen_file']['size'];
        $file_error_code = $_FILES['dokumen_file']['error'];
        $user_id = $_SESSION['user_id'] ?? null; // Ambil user_id dari sesi

        $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));

        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
        if (!in_array($file_ext, $allowed_ext)) {
            $upload_error_msg = "Tipe file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX.";
            $upload_success_flag = false;
        }

        $max_file_size = 10 * 1024 * 1024; // Contoh: 10 MB untuk dokumen umum
        if ($file_size > $max_file_size) {
            $upload_error_msg = "Ukuran file terlalu besar. Maksimal " . ($max_file_size / (1024 * 1024)) . " MB.";
            $upload_success_flag = false;
        }

        if ($upload_success_flag) {
            $new_file_name = uniqid('dok_', true) . '.' . $file_ext;
            // Path absolut untuk menyimpan file di server
            $target_file_path_absolute = $upload_dir_absolute . $new_file_name;
            // Path relatif untuk disimpan ke database dan diakses via URL
            $target_file_path_relative = $upload_dir_relative . $new_file_name;


            if (move_uploaded_file($file_tmp_name, $target_file_path_absolute)) {
                // Berhasil upload. Simpan info file ini ke database
                // SIMPAN PATH RELATIF KE DATABASE
                $stmt = $conn->prepare("INSERT INTO uploaded_files (name, original_name, file_path, file_size, file_ext, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    $upload_error_msg = "Gagal menyiapkan query database: " . $conn->error;
                    $upload_success_flag = false;
                } else {
                    $stmt->bind_param("sssisi", $new_file_name, $file_name_original, $target_file_path_relative, $file_size, $file_ext, $user_id);
                    if ($stmt->execute()) {
                        header("Location: upload_dokumen_page.php?status=upload_success");
                        exit();
                    } else {
                        $upload_error_msg = "Gagal menyimpan info file ke database: " . $stmt->error;
                        // Hapus file yang sudah diunggah jika gagal disimpan ke DB
                        if (file_exists($target_file_path_absolute)) { // Hapus menggunakan path absolut
                            unlink($target_file_path_absolute);
                        }
                        $upload_success_flag = false;
                    }
                    $stmt->close();
                }
            } else {
                $upload_error_msg = "Gagal memindahkan file. Kode error: " . $file_error_code;
                $upload_success_flag = false;
            }
        }
    } elseif (isset($_FILES['dokumen_file']) && $_FILES['dokumen_file']['error'] != UPLOAD_ERR_NO_FILE) {
        $upload_error_msg = "Terjadi error saat mengunggah file. Kode error: " . $_FILES['dokumen_file']['error'];
        $upload_success_flag = false;
    } elseif (!isset($_FILES['dokumen_file']) || $_FILES['dokumen_file']['error'] == UPLOAD_ERR_NO_FILE) {
        $upload_error_msg = "Tidak ada file yang dipilih untuk diunggah.";
        $upload_success_flag = false;
    }

    if (!$upload_success_flag) {
        header("Location: upload_dokumen_page.php?status=upload_fail&msg=" . urlencode($upload_error_msg));
        exit();
    }
}
// --- Akhir Logika Penanganan Upload File Halaman Ini ---

// --- Logika Penanganan Hapus File ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && $conn_status) {
    $document_id = $_GET['id'];

    // Ambil nama file dan path dari database sebelum dihapus
    $stmt = $conn->prepare("SELECT name, file_path FROM uploaded_files WHERE id = ?");
    if ($stmt === false) {
        header("Location: upload_dokumen_page.php?status=delete_fail&msg=" . urlencode("Gagal menyiapkan query untuk hapus: " . $conn->error));
        exit();
    }
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $file_info = $result->fetch_assoc();
    $stmt->close();

    if ($file_info) {
        // Gunakan path relatif dari database untuk membangun path absolut untuk unlink
        $file_to_delete_path_relative = $file_info['file_path'];
        $file_to_delete_path_absolute = __DIR__ . DIRECTORY_SEPARATOR . $file_to_delete_path_relative;

        // Hapus entri dari database
        $stmt_delete = $conn->prepare("DELETE FROM uploaded_files WHERE id = ?");
        if ($stmt_delete === false) {
             header("Location: upload_dokumen_page.php?status=delete_fail&msg=" . urlencode("Gagal menyiapkan query delete: " . $conn->error));
             exit();
        }
        $stmt_delete->bind_param("i", $document_id);

        if ($stmt_delete->execute()) {
            // Hapus file fisik jika entri database berhasil dihapus
            if (file_exists($file_to_delete_path_absolute)) { // Hapus menggunakan path absolut
                unlink($file_to_delete_path_absolute);
            }
            header("Location: upload_dokumen_page.php?status=delete_success");
            exit();
        } else {
            header("Location: upload_dokumen_page.php?status=delete_fail&msg=" . urlencode("Gagal menghapus entri database: " . $stmt_delete->error));
            exit();
        }
        $stmt_delete->close();
    } else {
        header("Location: upload_dokumen_page.php?status=delete_fail&msg=" . urlencode("Dokumen tidak ditemukan."));
        exit();
    }
}
// --- Akhir Logika Penanganan Hapus File ---

// --- Logika Mengambil Daftar Dokumen untuk Ditampilkan ---
$uploaded_documents = [];
if ($conn_status) {
    $sql = "SELECT id, original_name, uploaded_at, file_path, file_ext FROM uploaded_files ORDER BY uploaded_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $uploaded_documents[] = $row;
            }
        }
        $result->free();
    } else {
        $error_message .= " Gagal mengambil daftar dokumen: " . $conn->error;
        error_log("SQL Error: " . $conn->error);
    }
}

// Tutup koneksi database
if ($conn_status && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Dokumen Umum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Global Color Palette & Variables (diambil dari dashboard.php) */
        :root {
            --primary-blue: #2c52ed; /* Deep Blue, slightly darker and richer */
            --primary-blue-light: #4a77ff; /* Lighter shade of primary blue */
            --accent-green: #28a745; /* Success green */
            --accent-red: #dc3545; /* Danger red */
            --accent-orange: #ff9100; /* Warm orange for accents */
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

        /* Sidebar Styling */
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
            padding: 3rem 3.5rem;
            min-height: 100vh;
            background: var(--neutral-light);
            box-shadow: -2px 0 25px rgba(0, 0, 0, 0.08);
            border-top-left-radius: 30px;
            position: relative;
            z-index: 1;
        }
        h3 {
            color: var(--primary-blue);
            margin-bottom: 2.5rem;
            user-select: none;
            font-size: 2.5rem;
            font-weight: 800;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.1);
            font-family: 'Montserrat', sans-serif;
        }

        /* General Card Styling */
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

        /* Form Upload Styling */
        .upload-form-card {
            padding: 2rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .upload-form-card .form-label {
            font-weight: 600;
            color: var(--primary-blue);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .upload-form-card .form-control {
            border-radius: 12px;
            border: 2px solid var(--primary-blue-light);
            padding: 0.8rem 1.2rem;
            font-size: 1rem;
        }
        .upload-form-card .form-control:focus {
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 0.35rem rgba(255, 145, 0, 0.2);
        }
        .upload-form-card .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            box-shadow: 0 6px 18px rgba(44, 82, 237, 0.4);
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            border-radius: 15px;
        }
        .upload-form-card .btn-primary:hover {
            background-color: var(--primary-blue-light);
            border-color: var(--primary-blue-light);
            box-shadow: 0 8px 25px rgba(74, 119, 255, 0.5);
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            font-weight: 500;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
        }
        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        
        /* Table Styling */
        .table {
            border-radius: 15px;
            overflow: hidden; /* Ensures border-radius applies to corners */
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid var(--neutral-medium);
        }
        .table thead th {
            background-color: var(--primary-blue);
            color: var(--white);
            font-weight: 600;
            padding: 1rem 1.25rem;
            border-bottom: none;
        }
        .table tbody tr:nth-child(even) {
            background-color: var(--neutral-light);
        }
        .table tbody tr:hover {
            background-color: rgba(44, 82, 237, 0.05);
        }
        .table td, .table th {
            padding: 0.8rem 1.25rem;
            vertical-align: middle;
            border-color: var(--neutral-medium);
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }


        /* Responsive Adjustments */
        @media (max-width: 992px) { /* Tablets */
            nav.sidebar { width: 220px; padding: 1.5rem 0.8rem; }
            main.content-wrapper { margin-left: 220px; padding: 2rem; }
            .sidebar-logo { font-size: 1.6rem; margin-bottom: 2rem; }
            .sidebar-logo i { font-size: 2.5rem; }
            nav.sidebar a { padding: 0.9rem 1.1rem; font-size: 0.95rem; }
            nav.sidebar a.active { padding-left: calc(1.4rem - 6px); }
            h3 { font-size: 2rem; margin-bottom: 2rem; }
            .card { margin-bottom: 2rem; border-radius: 20px; }
            .upload-form-card { padding: 1.5rem 2rem; }
        }

        @media (max-width: 768px) { /* Mobile */
            nav.sidebar { width: 220px; transform: translateX(-240px); position: fixed; height: 100vh; box-shadow: 4px 0 20px rgba(0, 0, 0, 0.4); transition: transform 0.3s ease-in-out; }
            nav.sidebar.active { transform: translateX(0); }
            main.content-wrapper { margin-left: 0; padding: 1.5rem 1rem; border-top-left-radius: 0; box-shadow: none; }
            .toggle-sidebar-btn { display: block !important; position: fixed; top: 15px; left: 15px; background: var(--primary-blue); border: none; color: var(--white); font-size: 1.8rem; border-radius: 12px; padding: 8px 12px; z-index: 1200; cursor: pointer; box-shadow: 0 4px 15px rgba(44, 82, 237, 0.4); user-select: none; transition: background-color 0.3s ease; }
            .toggle-sidebar-btn:hover { background: var(--primary-blue-light); }
            h3 { font-size: 1.8rem; margin-bottom: 1.5rem; text-align: center; }
            .card { margin-bottom: 1.5rem; border-radius: 18px; }
            .upload-form-card { padding: 1rem 1.5rem; }
            .table-responsive { /* Add this for better table handling on small screens */
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) { /* Smaller Mobile */
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
    <h3>Upload Dokumen Umum</h3>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card upload-form-card">
        <h5 class="card-title text-primary fw-bold">Unggah Dokumen Baru</h5>
        <form action="upload_dokumen_page.php" method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="dokumenFile" class="form-label">Pilih Dokumen:</label>
                <input class="form-control" type="file" id="dokumenFile" name="dokumen_file" required>
                <div class="form-text">Maks 10MB. Format: Gambar (JPG, PNG, GIF), PDF, DOC, DOCX, XLS, XLSX.</div>
            </div>
            <button type="submit" name="submit_dokumen" class="btn btn-primary">
                <i class="bi bi-cloud-arrow-up"></i> Unggah Dokumen
            </button>
        </form>
    </div>

    <div class="card mt-4">
        <h5 class="card-title text-primary fw-bold">Daftar Dokumen Terunggah</h5>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nama File</th>
                            <th>Tanggal Unggah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($uploaded_documents)): ?>
                            <?php foreach ($uploaded_documents as $doc): ?>
                                <tr>
                                    <td><?= htmlspecialchars($doc['original_name']) ?></td>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($doc['uploaded_at']))) ?></td>
                                    <td>
                                        <a href="view_dokumen.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-info" target="_blank">Lihat</a>
                                        <a href="upload_dokumen_page.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Anda yakin ingin menghapus dokumen ini? Tindakan ini tidak bisa dibatalkan!');">Hapus</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada dokumen yang diunggah.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('nav.sidebar').classList.toggle('active');
    }
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    // Dynamic Active Sidebar Class
    // Fungsi ini tidak perlu di dalam DOMContentLoaded jika dipanggil langsung
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

    // Menghilangkan pesan alert setelah beberapa detik
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000); // Alert akan hilang setelah 5 detik
        });
    });
</script>

</body>
</html>