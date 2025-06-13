<?php
// Pastikan tidak ada spasi, karakter, atau baris kosong di atas tag <?php ini
// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'koneksi.php'; 
require_once 'utils.php'; // Pastikan file utils.php sudah ada dan benar


// --- Inisialisasi Variabel Global di Awal Script ---
$php_error_message = '';
$conn_status = false; 
$loggedInUserId = $_SESSION['user_id'] ?? null; // Inisialisasi loggedInUserId dari sesi
$loggedInUserRole = '';
$searchTerm = $_GET['search'] ?? ''; // Inisialisasi searchTerm
$usersData = []; // Inisialisasi usersData sebagai array kosong
$success = '';   // Inisialisasi success
$error = '';     // Inisialisasi error

// Cek koneksi database setelah require_once
if ($conn && !$conn->connect_error) {
    $conn_status = true;
} else {
    $php_error_message = "Koneksi database GAGAL: " . ($conn ? $conn->connect_error : "Objek koneksi tidak terbentuk.");
    error_log($php_error_message);
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil role pengguna yang sedang login (penting untuk otorisasi)
// Hanya coba query jika koneksi sukses dan user_id di sesi ada
if ($conn_status && isset($loggedInUserId)) { // Menggunakan $loggedInUserId yang sudah diinisialisasi
    $stmtUserRole = $conn->prepare("SELECT role FROM users WHERE id = ?");
    if ($stmtUserRole === false) {
        $php_error_message = "Error preparing role query: " . $conn->error;
        error_log($php_error_message);
    } else {
        $param_id = $loggedInUserId;
        // Bind parameter for PHP 7.x compatible way
        $stmtUserRole->bind_param("i", $param_id); 
        
        if (!$stmtUserRole->execute()) {
            $php_error_message = "Error executing role query: " . $stmtUserRole->error;
            error_log($php_error_message);
        } else {
            $resultUserRole = $stmtUserRole->get_result();
            if ($rowUserRole = $resultUserRole->fetch_assoc()) {
                $loggedInUserRole = $rowUserRole['role'];
            }
        }
        $stmtUserRole->close();
    }
} else if (!isset($loggedInUserId)) { // Ini seharusnya tidak tercapai jika check login di atas sudah bekerja
    // Jika user_id is not set, user will be redirected by the check login above
} else {
    $php_error_message = "Tidak dapat mengambil peran pengguna karena koneksi DB gagal.";
}


// Hanya admin ATAU bendahara yang boleh mengakses halaman ini
if ($loggedInUserRole !== 'admin' && $loggedInUserRole !== 'bendahara') {
    header("Location: dashboard.php?access_denied=true");
    exit();
}

// Ambil data user dari DB
$searchCondition = '';
$searchBindParams = []; 
$searchBindType = '';

if (!empty($searchTerm)) {
    $searchCondition = " WHERE nama_lengkap LIKE ? OR email LIKE ?";
    $searchTermWildcard = '%' . $searchTerm . '%';
    $searchBindParams[] = $searchTermWildcard;
    $searchBindParams[] = $searchTermWildcard;
    $searchBindType = 'ss';
}

if ($conn_status) { // Hanya coba query jika koneksi sukses
    $query = "SELECT id, nama_lengkap, email, role FROM users" . $searchCondition . " ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        $php_error_message = "Error preparing user list query: " . $conn->error;
        error_log($php_error_message);
    } else {
        if (!empty($searchBindParams)) {
            // Correct way to bind_param for PHP 7.x using call_user_func_array
            $tmpParams = [];
            foreach ($searchBindParams as $key => $value) {
                $tmpParams[$key] = &$searchBindParams[$key];
            }
            array_unshift($tmpParams, $searchBindType);
            call_user_func_array([$stmt, 'bind_param'], $tmpParams);
        }
        if (!$stmt->execute()) {
            $php_error_message = "Error executing user list query: " . $stmt->error;
            error_log($php_error_message);
        } else {
            $usersData = $stmt->get_result(); 
        }
        $stmt->close();
    }
}


// Ambil pesan sukses/error dari URL jika ada (setelah redirect) - logic ini tidak perlu di awal script lagi
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added') {
        $success = "Pengguna berhasil ditambahkan!";
    } elseif ($_GET['status'] === 'deleted') {
        $success = "Pengguna berhasil dihapus!";
    } elseif ($_GET['status'] === 'updated') {
        $success = "Pengguna berhasil diperbarui!";
    } elseif ($_GET['status'] === 'self_delete_error') {
        $error = "Anda tidak bisa menghapus akun sendiri.";
    } elseif ($_GET['status'] === 'access_denied') {
        $error = "Akses ditolak! Anda tidak memiliki izin untuk melihat halaman ini.";
    } elseif ($_GET['status'] === 'email_exist') {
        $error = "Email sudah digunakan.";
    } elseif ($_GET['status'] === 'db_error') {
        $error = "Terjadi kesalahan database. Mohon coba lagi."; 
    }
}


// Proses tambah user
if (isset($_POST['add_user']) && $conn_status) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if ($nama_lengkap == '' || $email == '' || $password == '') {
        $error = "Nama Lengkap, Email, dan Password wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email=?");
        if ($stmt_check === false) { 
            header("Location: manajemen_user.php?status=db_error"); 
            exit();
        } 
        $param_email_check = $email;
        $stmt_check->bind_param("s", $param_email_check);
        if (!$stmt_check->execute()) { 
            header("Location: manajemen_user.php?status=db_error");
            exit();
        }
        $check_result = $stmt_check->get_result();

        if ($check_result->num_rows > 0) {
            header("Location: manajemen_user.php?status=email_exist");
            exit();
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO users (nama_lengkap, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt_insert === false) { 
                header("Location: manajemen_user.php?status=db_error");
                exit();
            } 
            $param_namalengkap = $nama_lengkap;
            $param_email_insert = $email;
            $param_hash = $hash;
            $param_role_insert = $role;
            $stmt_insert->bind_param("ssss", $param_namalengkap, $param_email_insert, $param_hash, $param_role_insert);
            if ($stmt_insert->execute()) {
                header("Location: manajemen_user.php?status=added");
                exit();
            } else {
                header("Location: manajemen_user.php?status=db_error");
                exit();
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// Proses hapus user
if (isset($_GET['delete']) && $conn_status) { 
    $id = intval($_GET['delete']);
    if ($id == $loggedInUserId) {
        header("Location: manajemen_user.php?status=self_delete_error");
        exit();
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM users WHERE id=?");
        if ($stmt_delete === false) { 
            header("Location: manajemen_user.php?status=db_error");
            exit();
        } 
        $param_id_delete = $id;
        $stmt_delete->bind_param("i", $param_id_delete);
        if ($stmt_delete->execute()) {
            header("Location: manajemen_user.php?status=deleted");
            exit();
        } else {
            header("Location: manajemen_user.php?status=db_error");
            exit();
        }
        $stmt_delete->close();
    }
}

// Proses edit user
if (isset($_POST['edit_user']) && $conn_status) { 
    $id = intval($_POST['user_id']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];

    if ($nama_lengkap == '' || $email == '') {
        $error = "Nama Lengkap dan Email tidak boleh kosong.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        $stmt_check_edit = $conn->prepare("SELECT id FROM users WHERE email=? AND id <> ?");
        if ($stmt_check_edit === false) { 
            header("Location: manajemen_user.php?status=db_error");
            exit();
        } 
        $param_email_check_edit = $email;
        $param_id_check_edit = $id;
        $stmt_check_edit->bind_param("si", $param_email_check_edit, $param_id_check_edit);
        if (!$stmt_check_edit->execute()) { 
            header("Location: manajemen_user.php?status=db_error");
            exit();
        }
        $check_edit_result = $stmt_check_edit->get_result();

        if ($check_edit_result->num_rows > 0) {
            $error = "Email sudah digunakan user lain.";
        } else {
            $stmt_update = $conn->prepare("UPDATE users SET nama_lengkap=?, email=?, role=? WHERE id=?");
            if ($stmt_update === false) { 
                header("Location: manajemen_user.php?status=db_error");
                exit();
            } 
            $param_namalengkap_update = $nama_lengkap;
            $param_email_update = $email;
            $param_role_update = $role;
            $param_id_update = $id;
            $stmt_update->bind_param("sssi", $param_namalengkap_update, $param_email_update, $param_role_update, $param_id_update);
            if ($stmt_update->execute()) {
                if ($id == $loggedInUserId) {
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = $role;
                }
                header("Location: manajemen_user.php?status=updated");
                exit();
            } else {
                header("Location: manajemen_user.php?status=db_error");
                exit();
            }
            $stmt_update->close();
        }
        $stmt_check_edit->close();
    }
}
// Tutup koneksi hanya jika berhasil dibuka
if ($conn_status && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manajemen Pengguna - Pencatatan Keuangan Karang Taruna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Global Color Palette & Variables (diambil dari dashboard.php yang sudah dipercantik) */
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

            /* Warna spesifik untuk tabel users */
            --table-header-bg: linear-gradient(90deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
            --table-row-bg-odd: var(--white);
            --table-row-bg-even: var(--neutral-light);
            --table-row-hover-bg: #e6f0ff; /* Light blue on hover */
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

        /* Sidebar Styling (diambil dari dashboard.php) */
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

        /* Main Content Wrapper (dari dashboard.php) */
        main.content-wrapper {
            margin-left: 260px; /* Adjust for wider sidebar */
            padding: 3rem 3.5rem; /* More generous padding */
            min-height: 100vh;
            background: var(--neutral-light);
            box-shadow: -2px 0 25px rgba(0, 0, 0, 0.08);
            border-top-left-radius: 30px; /* More rounded corner */
            position: relative;
            z-index: 1;
        }
        h3 {
            color: var(--primary-blue);
            margin-bottom: 2.5rem; /* More space */
            user-select: none;
            font-size: 2.5rem; /* Larger title */
            font-weight: 800; /* Bolder */
            text-shadow: 1px 1px 4px rgba(0,0,0,0.1);
            font-family: 'Montserrat', sans-serif;
        }
        /* General Card Styling (dari dashboard.php) */
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
        
        label.form-label {
            font-weight: 600;
            color: var(--text-dark); /* Ubah ke text-dark */
            font-size: 0.95rem;
            margin-bottom: 0.4rem;
        }
        input.form-control,
        select.form-select {
            border-radius: 10px;
            border: 1.5px solid var(--primary-blue-light); /* Ubah ke primary-blue-light */
            padding: 0.5rem 0.75rem;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input.form-control:focus,
        select.form-select:focus {
            border-color: var(--accent-orange); /* Ubah ke accent-orange */
            box-shadow: 0 0 8px rgba(255,145,0,0.5); /* Ubah ke accent-orange */
            outline: none;
        }
        button.btn-primary {
            background-color: var(--primary-blue);
            border: none;
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            border-radius: 10px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            user-select: none;
            box-shadow: 0 4px 12px rgba(44, 82, 237, 0.3); /* Ubah ke primary-blue */
        }
        button.btn-primary:hover {
            background-color: var(--primary-blue-light); /* Ubah ke primary-blue-light */
            box-shadow: 0 6px 18px rgba(74, 119, 255, 0.45); /* Ubah ke primary-blue-light */
        }
        button.btn-warning {
            background-color: var(--accent-orange); /* Ubah ke accent-orange */
            border: none;
            color: var(--text-dark); /* Ubah ke text-dark */
            padding: 0.5rem 1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 10px rgba(255,145,0,0.3); /* Ubah ke accent-orange */
        }
        button.btn-warning:hover {
            background-color: #e07b00; /* Darker orange */
            box-shadow: 0 5px 15px rgba(255,145,0,0.45); /* Ubah ke accent-orange */
        }
        button.btn-danger {
            background-color: var(--accent-red); /* Ubah ke accent-red */
            border: none;
            padding: 0.5rem 1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 10px rgba(220,53,69,0.3); /* Ubah ke accent-red */
        }
        button.btn-danger:hover {
            background-color: #c82333; /* Darker red */
            box-shadow: 0 5px 15px rgba(220,53,69,0.45); /* Ubah ke accent-red */
        }

        /* Tabel Styling */
        table.table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
            user-select: none;
            border-radius: 15px; /* Tambah border-radius tabel */
            overflow: hidden; /* Penting untuk border-radius tabel */
        }
        table.table thead {
            background-image: var(--table-header-bg); /* Menggunakan variabel */
            color: var(--white);
            border-radius: 15px; /* Sudut header */
        }
        table.table th {
            padding: 15px 20px;
            font-weight: 700;
            font-size: 1.05rem;
        }
        table.table thead tr:first-child th:first-child {
            border-top-left-radius: 15px;
        }
        table.table thead tr:first-child th:last-child {
            border-top-right-radius: 15px;
        }
        table.table tbody tr {
            background: #eef5ff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.1);
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        table.table tbody tr:nth-child(even) {
            background: var(--neutral-light); /* Gunakan warna genap yang lebih muted */
        }
        table.table tbody tr:hover {
            background-color: #d8e6ff;
            transform: translateY(-3px);
        }
        table.table td {
            padding: 12px 20px;
            vertical-align: middle;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        table.table td:first-child {
            border-top-left-radius: 15px;
            border-bottom-left-radius: 15px;
        }
        table.table td:last-child {
            border-top-right-radius: 15px;
            border-bottom-right-radius: 15px;
        }
        .action-buttons button, .action-buttons a {
            margin-right: 8px;
            min-width: 80px;
            text-align: center;
        }
        
        /* Message styling (alert Bootstrap) */
        .alert {
            border-radius: 12px;
            font-weight: 500;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
        }
        .alert-success { background-color: #d1e7dd; border-color: #badbcc; color: #0f5132; }
        .alert-danger { background-color: #f8d7da; border-color: #f5c2c7; color: #842029; }

        /* Modal Tambah Pengguna Baru */
        .modal-add-user .modal-header {
            background-color: var(--primary-blue);
            color: var(--white); border-bottom: none; padding: 1.5rem 2rem;
            border-top-left-radius: calc(1rem - 1px); border-top-right-radius: calc(1rem - 1px);
        }
        .modal-add-user .modal-title { font-family: 'Montserrat', sans-serif; font-weight: 700; font-size: 1.5rem; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .modal-add-user .btn-close { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.8; transition: opacity 0.3s ease; }
        .modal-add-user .btn-close:hover { opacity: 1; }
        .modal-add-user .modal-content { border-radius: 1rem; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); }
        .modal-add-user .modal-body { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; padding: 2rem; }
        .modal-add-user .modal-footer { border-top: none; padding: 1.5rem 2rem; justify-content: flex-end; gap: 1rem; }
        .modal-add-user .modal-footer .btn { min-width: 120px; font-weight: 600; border-radius: 0.75rem; }
        .modal-add-user .modal-footer .btn-secondary { background-color: var(--text-secondary); border-color: var(--text-secondary); color: var(--white); box-shadow: 0 4px 10px rgba(108,117,125,0.3); }
        .modal-add-user .modal-footer .btn-secondary:hover { background-color: #5a6268; border-color: #545b62; box-shadow: 0 6px 15px rgba(90,98,104,0.4); }
        .modal-add-user .modal-footer .btn-primary { background-color: var(--primary-blue); border-color: var(--primary-blue); box-shadow: 0 4px 12px rgba(44, 82, 237, 0.3); }
        .modal-add-user .modal-footer .btn-primary:hover { background-color: var(--primary-blue-light); border-color: var(--primary-blue-light); box-shadow: 0 6px 18px rgba(74, 119, 255, 0.45); }
        
        /* Header section users */
        .header-section-users {
            background-color: var(--card-bg); padding: 1.5rem 2rem; border-radius: 15px;
            box-shadow: 0 8px 25px var(--card-shadow-light); margin-bottom: 2.5rem; align-items: center;
        }
        .header-section-users h3 { margin-bottom: 0.5rem; font-size: 2rem; color: var(--primary-blue); text-shadow: none; }
        .header-section-users .text-secondary { font-size: 0.95rem; }
        .header-section-users .search-form { max-width: 300px; margin-left: auto; margin-right: 0; }
        .search-form .form-control { border-radius: 20px; padding-right: 65px; padding-left: 15px; border-color: var(--primary-blue); font-size: 0.95rem; }
        .search-form .form-control:focus { box-shadow: 0 0 0 3px rgba(13,110,253,0.25); border-color: var(--primary-blue); }
        .search-form .btn-search { position: absolute; right: 35px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--primary-blue); font-size: 1.2rem; padding: 0; z-index: 5; cursor: pointer; }
        .search-form .btn-clear-search { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); font-size: 1.1rem; padding: 0; z-index: 5; cursor: pointer; }
        .search-form .btn-search:hover, .search-form .btn-clear-search:hover { color: var(--accent-orange); }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            main.content-wrapper { padding: 2rem; }
            .header-section-users { padding: 1.25rem 1.5rem; flex-direction: column; align-items: flex-start; gap: 1rem; }
            .header-section-users h3 { font-size: 1.8rem; }
            .header-section-users .text-secondary { font-size: 0.9rem; }
            .header-section-users .search-form { max-width: 100%; margin-left: 0; margin-right: 0; }
            .header-section-users > div:last-child { width: 100%; justify-content: space-between; }
            .search-form { flex-grow: 1; }
            .header-section-users .btn-primary { white-space: nowrap; flex-shrink: 0; }
        }

        @media (max-width: 768px) {
            nav.sidebar { transform: translateX(-240px); }
            nav.sidebar.active { transform: translateX(0); }
            main.content-wrapper { margin-left: 0; padding: 1.5rem 1rem; }
            .toggle-sidebar-btn { display: block !important; }
            h3 { font-size: 1.8rem; text-align: center; }
            .card { padding: 1rem; }
            .modal-add-user .modal-body { grid-template-columns: 1fr; gap: 1rem; }
            .modal-add-user .modal-footer { flex-direction: column; gap: 0.75rem; }
            .modal-add-user .modal-footer .btn { width: 100%; min-width: auto; }

            table.user-table { display: block; overflow-x: auto; white-space: nowrap; width: 100%; border-radius: 10px; }
            table.user-table thead, table.user-table tbody, table.user-table th, table.user-table td, table.user-table tr { display: block; }
            table.user-table th, table.user-table td { width: auto !important; white-space: normal; }
            table.user-table tbody tr { margin-bottom: 10px; border-radius: 10px; }
            table.user-table thead tr:first-child th:first-child { border-top-left-radius: 10px; }
            table.user-table thead tr:first-child th:last-child { border-top-right-radius: 10px; }
            table.table tbody tr td:first-child { border-bottom-left-radius: 10px; }
            table.table tbody tr td:last-child { border-bottom-right-radius: 10px; }
            .action-buttons { white-space: normal; display: flex; flex-wrap: wrap; gap: 5px; }
            .action-buttons button, .action-buttons a { min-width: auto; flex-grow: 1; margin-right: 0; }
        }

        @media (max-width: 576px) {
            main.content-wrapper { padding: 1rem 0.8rem; }
            h3 { font-size: 1.5rem; }
            .header-section-users .search-form { margin-top: 1rem; }
            .header-section-users > div:last-child {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            .search-form .btn-search,
            .search-form .btn-clear-search { right: 15px; top: 50%; transform: translateY(-50%); }
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
    <div class="header-section-users d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h3 class="mb-1">Manajemen Pengguna</h3>
            <p class="text-secondary mb-0">Kelola semua akun pengguna **website** Anda dengan mudah di sini.</p>
        </div>
        <div class="d-flex align-items-center mt-3 mt-md-0 gap-3 flex-grow-1 justify-content-md-end">
            <form method="GET" class="search-form position-relative flex-grow-1 flex-md-grow-0" onsubmit="return validateSearch()">
                <input type="text" name="search" id="searchInput" class="form-control" placeholder="Cari pengguna..." value="<?= htmlspecialchars($searchTerm) ?>">
                <button type="submit" class="btn btn-search" aria-label="Cari"><i class="bi bi-search"></i></button>
                 <?php if (!empty($searchTerm)): ?>
                    <button type="button" class="btn btn-clear-search" onclick="clearSearch()" aria-label="Hapus Pencarian"><i class="bi bi-x-circle-fill"></i></button>
                <?php endif; ?>
            </form>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru
            </button>
        </div>
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

    <div class="card card-table">
        <h5 class="card-header border-0 pb-0 pt-4 px-4 text-primary fw-bold">Daftar Pengguna</h5>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($conn_status && is_object($usersData) && mysqli_num_rows($usersData) > 0): ?>
                            <?php while ($user = mysqli_fetch_assoc($usersData)): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-warning btn-edit" 
                                                data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                data-id="<?= $user['id'] ?>" 
                                                data-namalengkap="<?= htmlspecialchars($user['nama_lengkap'], ENT_QUOTES) ?>" 
                                                data-email="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" 
                                                data-role="<?= $user['role'] ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="manajemen_user.php?delete=<?= $user['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus user <?= htmlspecialchars($user['nama_lengkap']) ?>?');">
                                                <i class="bi bi-trash"></i> Hapus
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-danger" disabled><i class="bi bi-trash"></i> Hapus</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    <?php if (!empty($php_error_message)): ?>
                                        Terjadi masalah: <?= htmlspecialchars($php_error_message) ?>
                                    <?php else: ?>
                                        Belum ada data pengguna atau tidak ditemukan.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div class="modal fade modal-add-user" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="manajemen_user.php" class="modal-content rounded-4 shadow">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="add-namalengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" id="add-namalengkap" name="nama_lengkap" class="form-control" placeholder="Masukkan Nama Lengkap" required>
                </div>
                <div class="mb-3">
                    <label for="add-email" class="form-label">Email</label>
                    <input type="email" id="add-email" name="email" class="form-control" placeholder="Masukkan Alamat Email" required>
                </div>
                <div class="mb-3">
                    <label for="add-password" class="form-label">Password</label>
                    <input type="password" id="add-password" name="password" class="form-control" placeholder="Masukkan Password" required>
                </div>
                <div class="mb-3">
                    <label for="add-role" class="form-label">Peran</label>
                    <select id="add-role" name="role" class="form-select" required>
                        <option value="bendahara">Bendahara</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" name="add_user" class="btn btn-primary">Tambah Pengguna</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="manajemen_user.php" class="modal-content rounded-4 shadow">
            <input type="hidden" name="user_id" id="edit-user-id" />
            <div class="modal-header">
                <h5 class="modal-title text-primary fw-bold" id="editUserLabel">Edit Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit-namalengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit-namalengkap" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="edit-email" class="form-label">Email</label>
                    <input type="email" name="email" id="edit-email" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="edit-role" class="form-label">Peran</label>
                    <select name="role" id="edit-role" class="form-select" required>
                        <option value="bendahara">Bendahara</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_user" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Batal</button>
            </div>
        </form>
    </div>
</div>

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
    
    // JavaScript untuk modal edit
    const editButtons = document.querySelectorAll('.btn-edit');
    const modalEditUser = new bootstrap.Modal(document.getElementById('editUserModal'));

    const inputId = document.getElementById('edit-user-id');
    const inputNamaLengkap = document.getElementById('edit-namalengkap');
    const inputEmail = document.getElementById('edit-email');
    const selectRole = document.getElementById('edit-role');

    editButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            inputId.value = btn.dataset.id;
            inputNamaLengkap.value = btn.dataset.namalengkap;
            inputEmail.value = btn.dataset.email;
            selectRole.value = btn.dataset.role;
            modalEditUser.show();
        });
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

        // Set focus ke search input jika ada search term di URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search')) {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.focus();
            }
        }
    });

    // Validasi Search Bar (opsional, jika Anda ingin validasi minimum)
    function validateSearch() {
        const searchInput = document.getElementById('searchInput');
        return true;
    }

    // Fungsi untuk mengosongkan search bar
    function clearSearch() {
        document.getElementById('searchInput').value = '';
        window.location.href = window.location.pathname; // Kembali ke halaman tanpa parameter search
    }

    // Dynamic Active Sidebar Class
    // Fungsi ini tidak perlu di dalam DOMContentLoaded
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
</script>

</body>
</html>