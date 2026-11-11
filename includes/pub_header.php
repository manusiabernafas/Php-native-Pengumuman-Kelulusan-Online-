<?php $_s = setelan(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Pengumuman Kelulusan') ?> · <?= e($_s['nama_sekolah']) ?></title>
<meta name="description" content="Pengumuman kelulusan <?= e($_s['nama_sekolah']) ?> tahun ajaran <?= e($_s['tahun_ajaran']) ?>.">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,500;6..96,600;6..96,700&family=Nunito+Sans:opsz,wght@6..12,400;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body class="publik">
<header class="pub-top">
  <a class="pub-merek" href="<?= url('beranda') ?>">
    <?php if ($_s['logo']): ?><img src="uploads/<?= e($_s['logo']) ?>" alt="" width="44" height="44"><?php else: ?><span class="lambang"><?= icon('toga', 26) ?></span><?php endif; ?>
    <span><b><?= e($_s['nama_sekolah']) ?></b><small>Pengumuman Kelulusan · TA <?= e($_s['tahun_ajaran']) ?></small></span>
  </a>
  <a class="pub-masuk" href="<?= url('login') ?>"><?= icon('kunci', 16) ?><span>Panitia</span></a>
</header>
