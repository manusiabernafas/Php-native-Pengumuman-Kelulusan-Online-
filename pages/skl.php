<?php
// Surat Keterangan Lulus siap cetak A4. Panitia: satu siswa (?id=) atau satu kelas (?kelas=).
// Siswa: hanya SKL miliknya sendiri, setelah pengumuman dibuka.
if ($page === 'skl') {
    $daftar = isset($_GET['kelas'])
        ? q("SELECT * FROM siswa WHERE kelas = ? AND status = 'lulus' ORDER BY nama", [(string) $_GET['kelas']])->fetchAll()
        : q("SELECT * FROM siswa WHERE id = ? AND status = 'lulus'", [(int) ($_GET['id'] ?? 0)])->fetchAll();
} else {
    $id = (int) ($_SESSION['siswa_id'] ?? 0);
    if (!$id || status_pengumuman() !== 'buka' || setelan('unduh_skl') !== '1') redirect('beranda');
    $daftar = q("SELECT * FROM siswa WHERE id = ? AND status = 'lulus'", [$id])->fetchAll();
}
if (!$daftar) {
    flash('SKL hanya tersedia untuk siswa yang dinyatakan lulus.', 'error');
    redirect($page === 'skl' ? 'siswa' : 'hasil');
}
$s0 = setelan();
$mapel = mapel_semua();
$tampilNilai = $s0['tampil_nilai'] === '1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>SKL <?= e(count($daftar) === 1 ? $daftar[0]['nama'] : 'kelas ' . $daftar[0]['kelas']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:opsz,wght@6..96,500;6..96,600;6..96,700&family=Nunito+Sans:opsz,wght@6..12,400;6..12,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/cetak.css?v=1">
</head>
<body>
<div class="alat"><button onclick="window.print()">Cetak / simpan PDF</button><span><?= count($daftar) ?> SKL · kertas A4, skala 100%</span></div>
<?php foreach ($daftar as $s): $nilai = nilai_siswa((int) $s['id']); ?>
<section class="lembar">
  <header class="kop">
    <?php if ($s0['logo']): ?><img src="uploads/<?= e($s0['logo']) ?>" alt="" class="logo"><?php else: ?><div class="logo"><?= icon('toga', 54) ?></div><?php endif; ?>
    <div>
      <p class="instansi">PEMERINTAH PROVINSI · DINAS PENDIDIKAN</p>
      <h1><?= e(strtoupper($s0['nama_sekolah'])) ?></h1>
      <p><?= e($s0['alamat']) ?><?= $s0['telepon'] ? ' · Telp. ' . e($s0['telepon']) : '' ?></p>
      <p><?= $s0['npsn'] ? 'NPSN ' . e($s0['npsn']) : '' ?><?= $s0['email'] ? ' · ' . e($s0['email']) : '' ?><?= $s0['website'] ? ' · ' . e($s0['website']) : '' ?></p>
    </div>
  </header>
  <h2 class="judul">SURAT KETERANGAN LULUS</h2>
  <p class="nomor">Nomor: <?= e(nomor_skl((int) $s['no_urut'])) ?></p>
  <p>Yang bertanda tangan di bawah ini, Kepala <?= e($s0['nama_sekolah']) ?>, menerangkan bahwa:</p>
  <table class="data">
    <tr><td>Nama</td><td>:</td><td><b><?= e($s['nama']) ?></b></td></tr>
    <tr><td>Tempat, tanggal lahir</td><td>:</td><td><?= e($s['tempat_lahir']) ?>, <?= e(tgl($s['tgl_lahir'])) ?></td></tr>
    <tr><td>NIS / NISN</td><td>:</td><td><?= e($s['nis']) ?> / <?= e($s['nisn']) ?></td></tr>
    <tr><td>Kelas</td><td>:</td><td><?= e($s['kelas']) ?></td></tr>
    <tr><td>Nama orang tua/wali</td><td>:</td><td><?= e($s['nama_ortu']) ?></td></tr>
  </table>
  <p>berdasarkan kriteria kelulusan dan hasil rapat dewan guru, siswa tersebut dinyatakan:</p>
  <p class="lulus">L U L U S</p>
  <p>dari <?= e($s0['nama_sekolah']) ?> Tahun Ajaran <?= e($s0['tahun_ajaran']) ?><?= $tampilNilai && $nilai ? ' dengan nilai sebagai berikut:' : '.' ?></p>
  <?php if ($tampilNilai && $nilai): ?>
  <table class="nilai">
    <thead><tr><th>No</th><th>Mata Pelajaran</th><th>Nilai</th></tr></thead>
    <tbody>
    <?php $no = 0; foreach ($mapel as $m): if (!isset($nilai[$m['id']])) continue; ?>
      <tr><td><?= ++$no ?></td><td><?= e($m['nama']) ?></td><td><?= angka($nilai[$m['id']]) ?></td></tr>
    <?php endforeach; ?>
      <tr class="rata"><td></td><td>Rata-rata</td><td><?= angka(rata($nilai)) ?></td></tr>
    </tbody>
  </table>
  <?php endif; ?>
  <p>Surat keterangan ini berlaku sementara sampai ijazah diterbitkan. Demikian agar dapat dipergunakan sebagaimana mestinya.</p>
  <footer class="ttd">
    <div class="qr-kotak">
      <div class="qr" data-url="<?= e(url_dasar() . 'index.php?page=verifikasi&kode=' . $s['token']) ?>"></div>
      <small>Pindai untuk memeriksa keaslian<br>Kode <?= e($s['token']) ?></small>
    </div>
    <div class="tangan">
      <p><?= e($s0['kota']) ?>, <?= e(tgl($s0['tanggal_skl'])) ?><br>Kepala Sekolah,</p>
      <p class="nama-kepsek"><?= e($s0['kepala_sekolah']) ?></p>
      <p><?= $s0['nip_kepala'] ? 'NIP. ' . e($s0['nip_kepala']) : '' ?></p>
    </div>
  </footer>
</section>
<?php endforeach; ?>
<script src="assets/js/qrcode.js"></script>
<script>
document.querySelectorAll('.qr').forEach(function (el) {
  var q = qrcode(0, 'M'); q.addData(el.dataset.url); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
});
</script>
</body>
</html>
