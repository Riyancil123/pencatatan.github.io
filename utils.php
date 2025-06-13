<?php
// utils.php
// PASTIKAN TIDAK ADA SATU PUN KARAKTER (SPASI, BARIS KOSONG) SEBELUM BARIS INI.

if (!function_exists('isActive')) {
    function isActive($pageName) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        return ($currentPage === $pageName) ? 'active' : '';
    }
}

// PASTIKAN TIDAK ADA SATU PUN KARAKTER (SPASI, BARIS KOSONG) SETELAH BARIS INI.
// LEBIH BAIK TIDAK ADA TAG PENUTUP ?> UNTUK FILE HANYA BERISI PHP.