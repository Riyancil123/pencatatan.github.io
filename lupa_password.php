<?php
// Pastikan tidak ada spasi, karakter, atau baris kosong di atas tag <?php ini
session_start();
// Tidak memerlukan koneksi.php langsung di sini untuk tampilan,
// tapi jika Anda ingin memeriksa email di database saat ini juga,
// maka require_once 'koneksi.php'; bisa ditambahkan.
// Untuk keperluan tampilan dan pesan, kita akan simulasikan.

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Silakan masukkan alamat email Anda.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } else {
        // --- LOGIKA SIMULASI UNTUK LUPA PASSWORD ---
        // Di sini Anda akan menambahkan logika sebenarnya untuk:
        // 1. Mencari email di database.
        // 2. Jika ditemukan, membuat token reset password.
        // 3. Menyimpan token di database dengan timestamp.
        // 4. Mengirim email ke pengguna dengan tautan reset password yang berisi token.
        // Contoh:
        // require_once 'koneksi.php';
        // $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        // $stmt_check->bind_param("s", $email);
        // $stmt_check->execute();
        // $stmt_check->store_result();
        // if ($stmt_check->num_rows > 0) {
        //     // Simulasi berhasil, di dunia nyata kirim email
        //     $success = "Jika email Anda terdaftar, instruksi reset password telah dikirimkan ke alamat email Anda.";
        // } else {
        //     // Untuk keamanan, pesan error untuk email tidak ditemukan seringkali disamarkan
        //     $success = "Jika email Anda terdaftar, instruksi reset password telah dikirimkan ke alamat email Anda.";
        // }
        // $stmt_check->close();
        // $conn->close();

        $success = "Jika email Anda terdaftar, instruksi reset password telah dikirimkan ke alamat email Anda. Mohon cek inbox atau folder spam Anda.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Aplikasi Karang Taruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        /* Variabel CSS untuk konsistensi warna (SAMA DENGAN LOGIN.PHP & REGIS.PHP) */
        :root {
            --primary-blue: rgb(22, 76, 253); /* Biru utama dari gambar */
            --white: #ffffff;
            --light-blue-bg: rgb(183, 217, 253); /* Latar belakang body dari gambar */
            --text-dark: #333333; /* Warna teks gelap */
            --text-light: #f0f0f0;
            --border-color: #cccccc; /* Border input dari gambar */
            --input-bg: #f9f9f9; /* Background input dari gambar */
            --accent-yellow: #ffc107; /* Tidak digunakan */
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
            max-width: 800px;
        }

        .form-card {
            background-color: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            display: flex;
            flex-direction: row;
            border: none;
        }

        .left-section {
            flex: 1;
            background-color: var(--light-blue-bg);
            position: relative;
            border-radius: 20px 0 0 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            text-align: center;
            color: var(--primary-blue);
        }
        .left-section::before {
            content: none;
        }
        .left-section .content { 
            position: relative;
            z-index: 2;
            color: var(--primary-blue);
            text-align: center;
        }
        .left-section .content .bi {
            font-size: 5rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
        }
        .left-section .content h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 15px;
            line-height: 1.2;
            color: var(--primary-blue);
        }
        .left-section .content p {
            font-size: 1rem;
            line-height: 1.5;
            color: var(--text-dark);
            max-width: 90%;
            margin: 0 auto;
        }

        /* Hapus animasi gradient */
        .left-section {
            background: var(--light-blue-bg);
            background-size: auto;
            animation: none;
        }

        .right-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .form-title { /* Menggunakan form-title */
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
        .input-group input[type="text"] {
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
            margin-bottom: 25px;
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

        /* Link kembali ke login */
        .back-to-login {
            text-align: center;
            margin-top: 30px;
            font-size: 0.95rem;
            color: var(--text-dark);
        }
        .back-to-login a {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .back-to-login a:hover {
            color: #2a52cc;
            text-decoration: underline;
        }

        /* Responsive adjustments (SAMA DENGAN LOGIN.PHP & REGIS.PHP) */
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
            .form-title {
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
            .form-title {
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
            .back-to-login {
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
                <h1 class="form-title">Lupa Password</h1> <?php if ($error): ?>
                    <div class="message error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="message success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="lupa_password.php">
                    <div class="input-group">
                        <label for="email">Masukkan Email Anda</label>
                        <input type="email" id="email" name="email" required autocomplete="email" placeholder="contoh@gmail.com">
                    </div>
                    <button type="submit" class="btn-submit">Kirim Reset Link</button>
                </form>
                <p class="back-to-login">
                    Ingat password Anda? <a href="login.php">Kembali ke Login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>