<?php
// Pastikan tidak ada spasi, karakter, atau baris kosong di atas tag <?php ini
session_start();
include 'koneksi.php'; // Memanggil file koneksi database

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $agree_terms = isset($_POST['agree_terms']) ? true : false;

    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!$agree_terms) {
        $error = "Anda harus menyetujui kebijakan privasi.";
    } else {
        // Cek apakah email sudah terdaftar
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        
        if ($stmt_check === false) {
            $error = "Gagal menyiapkan statement database: " . $conn->error;
        } else {
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error = "Email ini sudah terdaftar. Silakan gunakan email lain atau login.";
            } else {
                // Hash password sebelum menyimpan ke database
                $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Gunakan PASSWORD_DEFAULT

                // Masukkan data pengguna baru ke database
                // Pastikan kolom 'nama_lengkap' ada di tabel 'users' sesuai SQL yang saya berikan.
                $stmt_insert = $conn->prepare("INSERT INTO users (nama_lengkap, email, password) VALUES (?, ?, ?)");
                
                if ($stmt_insert === false) {
                    $error = "Gagal menyiapkan statement database: " . $conn->error;
                } else {
                    $stmt_insert->bind_param("sss", $fullname, $email, $hashed_password);

                    if ($stmt_insert->execute()) {
                        $success = "Registrasi berhasil! Silakan <a href='login.php'>login</a>.";
                        // Opsional: Redirect ke halaman login setelah registrasi berhasil
                        // header("Location: login.php?registered=true");
                        // exit;
                    } else {
                        $error = "Terjadi kesalahan saat registrasi: " . $stmt_insert->error . ". Silakan coba lagi.";
                    }
                    $stmt_insert->close();
                }
            }
            $stmt_check->close();
        }
    }
}
$conn->close(); // Menutup koneksi database di akhir script
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Aplikasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        /* Variabel CSS untuk konsistensi warna (SAMA DENGAN LOGIN.PHP) */
        :root {
            --primary-blue:rgb(15, 71, 253); /* Biru utama dari gambar */
            --white: #ffffff;
            --light-blue-bg:rgb(186, 219, 253); /* Latar belakang body dari gambar */
            --text-dark: #333333; /* Warna teks gelap */
            --text-light: #f0f0f0;
            --border-color: #cccccc; /* Border input dari gambar */
            --input-bg: #f9f9f9; /* Background input dari gambar */
            --accent-yellow: #ffc107; /* Tidak digunakan di login */
            --error-red: #cc0000; /* Warna error */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-blue-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            color: var(--text-dark);
        }

        .container-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            max-width: 800px; /* Lebar maksimal diperlebar untuk layout horizontal */
        }

        .form-card {
            background-color: var(--white);
            border-radius: 20px; /* Sesuai gambar */
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Bayangan seperti gambar */
            width: 100%;
            display: flex;
            flex-direction: row;
            border: none; /* Tidak ada border di gambar */
        }

        .left-section {
            flex: 1; /* Mengambil proporsi ruang yang sama */
            background-color: var(--light-blue-bg); /* Warna biru muda seperti di gambar */
            position: relative;
            border-radius: 20px 0 0 20px; /* Hanya sudut kiri yang melengkung */
            display: flex;
            flex-direction: column; /* Konten vertikal */
            justify-content: center;
            align-items: center;
            padding: 40px 20px; /* Padding disesuaikan */
            text-align: center;
            color: var(--primary-blue); /* Warna teks utama biru */
        }

        /* Hapus overlay gelap jika tidak ada di gambar asli */
        .left-section::before {
            content: none; /* Hapus pseudo-element overlay jika tidak ada di gambar */
        }

        /* Konten dalam left-section */
        .left-section .content { 
            position: relative;
            z-index: 2; /* Pastikan di atas pseudo-element jika diaktifkan */
            color: var(--primary-blue); /* Warna teks disesuaikan dengan biru utama */
            text-align: center;
        }
        .left-section .content .bi { /* Style untuk ikon di left-section */
            font-size: 5rem; /* Ukuran ikon seperti di gambar */
            color: var(--primary-blue); /* Warna ikon sama dengan teks utama */
            margin-bottom: 20px; /* Jarak bawah ikon */
        }
        .left-section .content h2 {
            font-family: 'Poppins', sans-serif; /* Kembali ke Poppins untuk konsistensi */
            font-weight: 700; /* Font lebih tebal */
            font-size: 2.2rem; /* Ukuran judul seperti di gambar */
            margin-bottom: 15px; /* Jarak bawah judul */
            line-height: 1.2;
            color: var(--primary-blue); /* Warna judul biru */
        }
        .left-section .content p {
            font-size: 1rem; /* Ukuran paragraf */
            line-height: 1.5;
            color: var(--text-dark); /* Warna teks sedikit gelap untuk keterbacaan */
            max-width: 90%; /* Batasi lebar teks agar tidak terlalu panjang */
            margin: 0 auto; /* Tengah teks */
        }

        .right-section {
            flex: 1; /* Mengambil proporsi ruang yang sama */
            padding: 40px; /* Padding seperti di gambar */
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .form-title { /* Menggunakan nama class yang lebih umum untuk judul form */
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 2rem;
            color: var(--primary-blue);
            text-align: center;
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .input-group input[type="email"],
        .input-group input[type="password"],
        .input-group input[type="text"] { /* Menambahkan input type text untuk fullname */
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(63, 107, 255, 0.2);
        }

        .checkbox-group {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: var(--input-bg);
            cursor: pointer;
            position: relative;
            flex-shrink: 0;
        }

        .checkbox-group input[type="checkbox"]:checked {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        /* Custom checkmark */
        .checkbox-group input[type="checkbox"]:checked::before {
            content: '\2713';
            color: var(--white);
            font-size: 1.1rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-weight: 400;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 20px;
            background-color: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 15px rgba(63, 107, 255, 0.3);
            margin-bottom: 25px; /* Jarak bawah tombol daftar */
        }

        .btn-submit:hover {
            background-color: #2a52cc;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(63, 107, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(63, 107, 255, 0.2);
        }

        .message {
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }
        .message.error {
            background-color: #ffe6e6;
            color: var(--error-red);
            border: 1px solid #ffb3b3;
        }
        .message.success {
            background-color: #e6ffe6;
            color: #008000;
            border: 1px solid #b3ffb3;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Register link (untuk halaman ini menjadi link login) */
        .login-link { /* Menggunakan nama class yang lebih spesifik */
            text-align: center;
            margin-top: 30px;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        .login-link a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .login-link a:hover {
            color: #2a52cc;
            text-decoration: underline;
        }

        /* Responsive adjustments (SAMA DENGAN LOGIN.PHP) */
        @media (max-width: 768px) {
            .container-wrapper {
                min-height: auto;
                padding: 10px;
            }
            .form-card {
                flex-direction: column;
                min-height: auto;
                border-radius: 20px;
            }
            .left-section {
                border-radius: 20px 20px 0 0;
                height: 200px;
                padding: 30px 20px;
            }
            
            .left-section .content h2 {
                font-size: 1.8rem;
            }
            .left-section .content p {
                font-size: 0.9rem;
            }
            .right-section {
                padding: 30px;
            }
            .form-title { /* Menggunakan form-title */
                font-size: 1.8rem;
                margin-bottom: 25px;
            }
        }

        @media (max-width: 480px) {
            .container-wrapper {
                padding: 10px;
            }
            .form-card {
                margin: 0;
                border-radius: 15px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            }
            .left-section {
                height: 150px;
                border-radius: 15px 15px 0 0;
                padding: 20px 15px;
            }
            .left-section .content .bi {
                font-size: 4rem;
            }
            .left-section .content h2 {
                font-size: 1.5rem;
            }
            .left-section .content p {
                font-size: 0.85rem;
            }
            .right-section {
                padding: 25px 20px;
            }
            .form-title { /* Menggunakan form-title */
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
            .input-group input {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            .btn-submit {
                padding: 12px 15px;
                font-size: 1rem;
            }
            .login-link { /* Menggunakan login-link */
                margin-top: 15px;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-wrapper">
        <div class="form-card">
            <div class="left-section">
                <div class="content">
                    <i class="bi bi-wallet2"></i> <h2>Selamat Datang!</h2>
                    <p>Kelola keuangan Karang Taruna Anda dengan mudah.</p>
                </div>
            </div>
            <div class="right-section">
                <h1 class="form-title">Daftar Akun</h1> <?php if ($error): ?>
                    <div class="message error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="message success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
                <?php endif; ?>

                <form method="POST" action="regis.php">
                    <div class="input-group">
                        <label for="fullname">Nama Lengkap</label>
                        <input type="text" id="fullname" name="fullname" required>
                    </div>
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="agree_terms" name="agree_terms" required>
                        <label for="agree_terms">Saya menyetujui kebijakan privasi</label>
                    </div>
                    <button type="submit" class="btn-submit">Daftar</button>
                </form>
                <p class="login-link">
                    Sudah punya akun? <a href="login.php">Login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>