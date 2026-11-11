<?php
$_s = setelan();
$_menu = [['dashboard', 'Ringkasan', 'home'], ['siswa', 'Siswa & nilai', 'users'], ['mapel', 'Mapel', 'buku'], ['log', 'Log akses', 'grafik']];
if (is_admin()) array_push($_menu, ['pengaturan', 'Pengaturan', 'setel'], ['pengguna', 'Pengguna', 'kunci']);
$_menu[] = ['profil', 'Profil', 'user'];
$_cur = explode('.', $_GET['page'] ?? 'dashboard')[0];
$_flash = flash();
$_st = status_pengumuman();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Ringkasan') ?> · Panitia Kelulusan</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,500;6..96,600;6..96,700&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="panel">
<header class="bar">
  <div class="bar-isi">
    <a class="pub-merek" href="<?= url('dashboard') ?>">
      <span class="lambang"><?= icon('toga', 24) ?></span>
      <span><b><?= e($_s['nama_sekolah']) ?></b><small>Panitia Kelulusan · TA <?= e($_s['tahun_ajaran']) ?></small></span>
    </a>
    <span class="status-umum s-<?= $_st ?>"><i></i><?= ['belum' => 'Belum dibuka', 'buka' => 'Pengumuman dibuka', 'tutup' => 'Sudah ditutup'][$_st] ?></span>
    <div class="bar-kanan">
      <a class="btn garis-terang kecil" href="<?= url('beranda') ?>" target="_blank"><?= icon('mata', 16) ?><span>Halaman siswa</span></a>
      <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="ibtn" title="Keluar" aria-label="Keluar"><?= icon('keluar', 19) ?></button></form>
    </div>
  </div>
  <nav class="tab-nav" aria-label="Menu panitia">
    <?php foreach ($_menu as [$_k, $_l, $_i]): ?>
      <a href="<?= url($_k) ?>" class="<?= $_cur === $_k ? 'aktif' : '' ?>"><?= icon($_i, 17) ?><?= e($_l) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="isi">
  <div class="judul-hal">
    <div>
      <?php if (!empty($kembali)): ?><a class="kembali" href="<?= e($kembali[0]) ?>"><?= icon('kiri', 15) ?> <?= e($kembali[1]) ?></a><?php endif; ?>
      <h1><?= e($title ?? 'Ringkasan') ?></h1>
      <?php if (!empty($sub)): ?><p><?= $sub ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="aksi"><?= $actions ?></div><?php endif; ?>
  </div>
  <?php if ($_flash): ?><div class="pesan <?= e($_flash['type']) ?>" role="status"><?= icon($_flash['type'] === 'error' ? 'awas' : ($_flash['type'] === 'info' ? 'info' : 'centang')) ?> <span><?= e($_flash['msg']) ?></span></div><?php endif; ?>
