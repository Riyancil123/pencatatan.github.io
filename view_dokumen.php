<?php
session_start();
require_once 'koneksi.php'; // Pastikan koneksi.php tersedia

// Aktifkan error reporting untuk debugging. HAPUS ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$document_path = '';
$document_original_name = 'Dokumen Tidak Ditemukan';
$document_type = ''; // Untuk menentukan bagaimana menampilkan dokumen (image, pdf, dll.)

if (isset($_GET['id']) && $conn instanceof mysqli && !$conn->connect_error) {
    $document_id = $_GET['id'];

    // Ambil detail dokumen dari database
    $stmt = $conn->prepare("SELECT original_name, file_path, file_ext FROM uploaded_files WHERE id = ?");
    if ($stmt === false) {
        // Handle error, perhaps redirect or show generic message
        $document_original_name = "Error database: Gagal menyiapkan query.";
        error_log("Database query prepare failed: " . $conn->error);
    } else {
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $doc = $result->fetch_assoc();
            $document_path = $doc['file_path']; // Ini harusnya sudah path relatif: uploads/namadokumen.ext
            $document_original_name = $doc['original_name'];
            $document_type = $doc['file_ext'];
        } else {
            $document_original_name = "Dokumen dengan ID tersebut tidak ditemukan.";
        }
        $stmt->close();
    }
} else {
    $document_original_name = "ID dokumen tidak valid atau koneksi database gagal.";
}

// Tutup koneksi database
if ($conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Dokumen: <?= htmlspecialchars($document_original_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #2c52ed;
            --text-dark: #333d47;
            --neutral-light: #f0f2f5;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--neutral-light);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container-view {
            background-color: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 1000px;
            width: 100%;
            margin: auto;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center; /* Center items horizontally */
            justify-content: center; /* Center items vertically */
        }
        .header-view {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .header-view h3 {
            color: var(--primary-blue);
            font-weight: 700;
            margin: 0;
            flex-grow: 1; /* Allow title to take available space */
            text-align: center; /* Center the title */
        }
        .btn-back {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover {
            background-color: #4a77ff;
            border-color: #4a77ff;
        }
        .document-viewer {
            width: 100%;
            height: 100%; /* Take full height of parent container */
            flex-grow: 1; /* Allow viewer to take remaining space */
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .document-viewer img, .document-viewer iframe, .document-viewer embed {
            max-width: 100%;
            max-height: 80vh; /* Limit height to 80% of viewport height */
            height: auto;
            width: auto;
            object-fit: contain; /* Ensure content fits without stretching */
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-top: 20px; /* Space between title and document */
        }
        /* Specific height for iframe/embed for PDFs */
        .document-viewer iframe[src$=".pdf"], .document-viewer embed[src$=".pdf"] {
            width: 100%;
            height: 700px; /* Adjust height as needed for PDF viewers */
        }
        .error-message {
            color: var(--accent-red);
            font-weight: 600;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .container-view {
                padding: 20px;
                border-radius: 10px;
            }
            .header-view {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .header-view h3 {
                font-size: 1.8rem;
            }
            .btn-back {
                width: 100%;
                justify-content: center;
            }
            .document-viewer iframe[src$=".pdf"], .document-viewer embed[src$=".pdf"] {
                height: 500px; /* Smaller height for mobile PDFs */
            }
        }
    </style>
</head>
<body>
    <div class="container container-view">
        <div class="header-view">
            <a href="upload_dokumen_page.php" class="btn btn-back">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <h3><?= htmlspecialchars($document_original_name) ?></h3>
            <div></div> 
        </div>

        <div class="document-viewer">
            <?php if ($document_path && file_exists($document_path)): // Cek keberadaan file fisik juga ?>
                <?php
                $is_image = in_array($document_type, ['jpg', 'jpeg', 'png', 'gif']);
                $is_pdf = ($document_type === 'pdf');
                $is_office = in_array($document_type, ['doc', 'docx', 'xls', 'xlsx']);
                ?>

                <?php if ($is_image): ?>
                    <img src="<?= htmlspecialchars($document_path) ?>" alt="<?= htmlspecialchars($document_original_name) ?>">
                <?php elseif ($is_pdf): ?>
                    <embed src="<?= htmlspecialchars($document_path) ?>" type="application/pdf" width="100%" height="700px">
                    <p class="mt-3">Jika PDF tidak muncul, <a href="<?= htmlspecialchars($document_path) ?>" target="_blank">klik di sini untuk mengunduh atau melihat di tab baru</a>.</p>
                <?php elseif ($is_office): ?>
                    <p class="error-message">File Office (DOC, DOCX, XLS, XLSX) tidak dapat ditampilkan langsung di browser tanpa layanan pihak ketiga (seperti Google Docs Viewer). Silakan unduh file untuk melihatnya.</p>
                    <a href="<?= htmlspecialchars($document_path) ?>" class="btn btn-primary mt-3"><i class="bi bi-download"></i> Unduh File</a>
                <?php else: ?>
                    <p class="error-message">Format file tidak didukung untuk tampilan langsung. Silakan unduh file untuk melihatnya.</p>
                    <a href="<?= htmlspecialchars($document_path) ?>" class="btn btn-primary mt-3"><i class="bi bi-download"></i> Unduh File</a>
                <?php endif; ?>
            <?php else: ?>
                <p class="error-message">Dokumen tidak ditemukan atau file fisik tidak ada di server.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>