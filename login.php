<?php
// Pastikan tidak ada spasi, karakter, atau baris kosong di atas tag <?php ini
session_start();
require_once 'koneksi.php'; // Memanggil file koneksi database

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validasi input kosong
    if (empty($email) || empty($password)) {
        $error = "Email dan Password harus diisi.";
    } else {
        // Menggunakan prepared statement untuk mencegah SQL Injection
        // Memilih id, nama_lengkap, email, dan password (hashed) dari tabel users
        $stmt = $conn->prepare("SELECT id, nama_lengkap, email, password FROM users WHERE email = ?");
        
        // Memeriksa apakah prepared statement berhasil dibuat
        if ($stmt === false) {
            $error = "Gagal menyiapkan statement database: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email); // 's' menandakan tipe data string
            $stmt->execute();
            $stmt->store_result(); // Menyimpan hasil query untuk memeriksa jumlah baris

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $nama_lengkap, $db_email, $hashed_password);
                $stmt->fetch(); // Mengambil baris hasil ke variabel yang diikat

                // Memverifikasi password yang diinput dengan hash password di database
                if (password_verify($password, $hashed_password)) {
                    // Set variabel sesi setelah login berhasil
                    $_SESSION['user_id'] = $id;
                    $_SESSION['nama_lengkap'] = $nama_lengkap; // Simpan nama lengkap
                    $_SESSION['email'] = $db_email;

                    // Redirect ke dashboard.php
                    header("Location: dashboard.php");
                    exit; // Sangat penting untuk menghentikan eksekusi script setelah redirect
                } else {
                    $error = "Password salah. Silakan coba lagi.";
                }
            } else {
                $error = "Email tidak ditemukan. Silakan periksa kembali email Anda.";
            }
            $stmt->close(); // Menutup statement
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
    <title>Login Aplikasi - Karang Taruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        /* Variabel CSS untuk konsistensi warna */
        :root {
            --primary-blue:rgb(22, 76, 253); /* Biru utama dari gambar */
            --white: #ffffff;
            --light-blue-bg:rgb(183, 217, 253); /* Latar belakang body dari gambar */
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
            /* min-height: 550px; Hapus atau sesuaikan, biarkan form-card yang menentukan tinggi */
        }

        .form-card {
            background-color: var(--white);
            border-radius: 20px; /* Sesuai gambar */
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Bayangan seperti gambar */
            width: 100%;
            display: flex;
            flex-direction: row;
            /* min-height: 450px; */ /* Biarkan konten menentukan tinggi */
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
            /* padding: 20px; */ /* Padding sudah di parent */
        }
        .left-section .content .bi { /* Style untuk ikon di left-section */
            font-size: 5rem; /* Ukuran ikon seperti di gambar */
            color: var(--primary-blue); /* Warna ikon sama dengan teks utama */
            margin-bottom: 20px; /* Jarak bawah ikon */
            /* text-shadow: 2px 2px 5px rgba(0,0,0,0.3); Hapus text-shadow jika tidak ada di gambar */
        }
        .left-section .content h2 {
            font-family: 'Poppins', sans-serif; /* Kembali ke Poppins untuk konsistensi */
            font-weight: 700; /* Font lebih tebal */
            font-size: 2.2rem; /* Ukuran judul seperti di gambar */
            margin-bottom: 15px; /* Jarak bawah judul */
            line-height: 1.2;
            color: var(--primary-blue); /* Warna judul biru */
            /* text-shadow: 1px 1px 4px rgba(0,0,0,0.2); Hapus text-shadow */
        }
        .left-section .content p {
            font-size: 1rem; /* Ukuran paragraf */
            line-height: 1.5;
            color: var(--text-dark); /* Warna teks sedikit gelap untuk keterbacaan */
            max-width: 90%; /* Batasi lebar teks agar tidak terlalu panjang */
            margin: 0 auto; /* Tengah teks */
        }

        /* Hapus Animasi Gradient Background jika tidak ada di gambar asli */
        /* @keyframes gradient-flow { ... } */
        .left-section {
            background: var(--light-blue-bg); /* Background statis seperti gambar */
            background-size: auto; /* Hapus background-size */
            animation: none; /* Hapus animasi */
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

        .login-title {
            font-family: 'Poppins', sans-serif; /* Kembali ke Poppins */
            font-weight: 600; /* Lebih tebal dari 500 */
            font-size: 2rem; /* Ukuran judul Login */
            color: var(--primary-blue); /* Warna biru untuk judul */
            text-align: center;
            margin-bottom: 30px;
            /* text-shadow: 1px 1px 3px rgba(0,0,0,0.05); Hapus text-shadow */
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
        .input-group input[type="password"] {
            width: 100%;
            padding: 12px 15px; /* Padding seperti di gambar */
            border: 1px solid var(--border-color); /* Border 1px seperti gambar */
            border-radius: 10px; /* Lebih membulat */
            font-size: 1rem;
            background-color: var(--input-bg);
            color: var(--text-dark);
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary-blue); /* Warna fokus utama biru */
            box-shadow: 0 0 0 3px rgba(63, 107, 255, 0.2); /* Bayangan fokus */
        }

        .forgot-password {
            text-align: right;
            margin-bottom: 25px;
        }

        .forgot-password a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #2a52cc; /* Warna hover sedikit lebih gelap */
            text-decoration: underline;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 20px; /* Padding tombol */
            background-color: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 10px; /* Lebih membulat */
            font-size: 1.1rem; /* Ukuran font tombol */
            font-weight: 600; /* Font lebih tebal */
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 15px rgba(63, 107, 255, 0.3); /* Bayangan tombol */
        }

        .btn-submit:hover {
            background-color: #2a52cc; /* Warna hover tombol */
            transform: translateY(-2px); /* Efek angkat */
            box-shadow: 0 8px 20px rgba(63, 107, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(63, 107, 255, 0.2);
        }

        .message {
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px; /* Lebih membulat */
            text-align: center;
            font-size: 0.9rem;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }
        .message.error {
            background-color: #ffe6e6; /* Latar error lebih terang */
            color: var(--error-red); /* Warna teks error */
            border: 1px solid #ffb3b3; /* Border error */
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Register link */
        .register-link {
            text-align: center;
            margin-top: 30px;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        .register-link a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600; /* Font lebih tebal */
            transition: color 0.3s ease;
        }
        .register-link a:hover {
            color: #2a52cc; /* Warna hover */
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container-wrapper {
                min-height: auto; /* Biarkan konten menentukan tinggi */
                padding: 10px;
            }
            .form-card {
                flex-direction: column;
                min-height: auto;
                border-radius: 20px; /* Sudut tetap membulat di mobile */
            }
            .left-section {
                border-radius: 20px 20px 0 0; /* Sudut atas yang melengkung */
                height: 200px; /* Tinggi tetap untuk sisi kiri di mobile */
                padding: 30px 20px; /* Sesuaikan padding */
            }
            /* Hapus atau sesuaikan pseudo-element untuk lengkungan di mobile jika tidak cocok */
            /* Jika di gambar tidak ada lengkungan di mobile, ini bisa dihapus */
            /* .left-section::after {
                content: '';
                position: absolute;
                bottom: -50px;
                left: 0;
                width: 100%;
                height: 100px;
                background-color: var(--white);
                border-radius: 50% / 0 0 100px 100px;
                transform: translateY(-50%) scaleX(1.5);
                z-index: 1;
            } */
            
            .left-section .content h2 {
                font-size: 1.8rem;
            }
            .left-section .content p {
                font-size: 0.9rem;
            }
            .right-section {
                padding: 30px;
            }
            .login-title {
                font-size: 1.8rem;
                margin-bottom: 25px;
            }
        }

        @media (max-width: 480px) {
            .container-wrapper {
                padding: 10px; /* Jaga sedikit padding di wrapper */
            }
            .form-card {
                margin: 0;
                border-radius: 15px; /* Sedikit membulat di layar sangat kecil */
                box-shadow: 0 5px 20px rgba(0,0,0,0.1); /* Bayangan lebih ringan */
            }
            .left-section {
                height: 150px;
                border-radius: 15px 15px 0 0; /* Sudut atas membulat */
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
            .login-title {
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
            .forgot-password, .register-link {
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
                <h1 class="login-title">Login</h1>
                <?php if ($error): ?>
                    <div class="message error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required autocomplete="email" value="riyan@gmail.com"> </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password" value="********"> </div>
                    <div class="forgot-password">
                       
                    </div>
                    <button type="submit" class="btn-submit">Login</button>
                </form>
                <p class="register-link">
                    Belum punya akun? <a href="regis.php">Daftar Sekarang</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>