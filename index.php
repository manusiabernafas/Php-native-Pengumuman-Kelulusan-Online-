<?php
require __DIR__ . '/includes/bootstrap.php';

// Halaman yang bisa dibuka tanpa login (pengumuman untuk siswa)
$publik = ['beranda', 'hasil', 'skl.saya', 'verifikasi', 'waktu', 'login', 'keluar.siswa'];

$routes = [
    'beranda'      => 'pages/beranda.php',
    'hasil'        => 'pages/hasil.php',
    'skl.saya'     => 'pages/skl.php',
    'verifikasi'   => 'pages/verifikasi.php',
    'waktu'        => 'pages/waktu.php',
    'keluar.siswa' => 'pages/keluar_siswa.php',
    'login'        => 'pages/login.php',
    'logout'       => 'pages/logout.php',
    'dashboard'    => 'pages/dashboard.php',
    'siswa'        => 'pages/siswa/index.php',
    'siswa.form'   => 'pages/siswa/form.php',
    'siswa.aksi'   => 'pages/siswa/aksi.php',
    'siswa.impor'  => 'pages/siswa/impor.php',
    'skl'          => 'pages/skl.php',
    'mapel'        => 'pages/mapel.php',
    'log'          => 'pages/log.php',
    'pengaturan'   => 'pages/pengaturan.php',
    'pengguna'     => 'pages/pengguna.php',
    'profil'       => 'pages/profil.php',
];

$page = $_GET['page'] ?? 'beranda';
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'beranda';
}
if (!in_array($page, $publik, true)) require_login();
verify_csrf();

require __DIR__ . '/' . $routes[$page];
