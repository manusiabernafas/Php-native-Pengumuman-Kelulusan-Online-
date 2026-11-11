<?php
$kode = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['kode'] ?? ''));
$s = strlen($kode) === 16 ? q("SELECT * FROM siswa WHERE token = ? AND status = 'lulus'", [$kode])->fetch() : null;
$title = 'Verifikasi SKL';
require __DIR__ . '/../includes/pub_header.php';
?>
<main class="panggung">
  <article class="sertifikat verif <?= $s ? 'sah' : 'tidak' ?>">
    <div class="bingkai">
      <p class="kecil">Verifikasi Surat Keterangan Lulus</p>
      <?php if ($s): ?>
        <div class="verif-ikon"><?= icon('perisai', 40) ?></div>
        <h2>SKL terdaftar dan sah</h2>
        <p class="muted">Data di bawah sesuai dengan arsip <?= e($_s['nama_sekolah']) ?>. Cocokkan dengan dokumen yang Anda terima.</p>
        <dl class="data-siswa">
          <div><dt>Nomor surat</dt><dd class="mono"><?= e(nomor_skl((int) $s['no_urut'])) ?></dd></div>
          <div><dt>Nama</dt><dd><b><?= e($s['nama']) ?></b></dd></div>
          <div><dt>NISN</dt><dd class="mono"><?= e(samar($s['nisn'])) ?></dd></div>
          <div><dt>Tempat, tanggal lahir</dt><dd><?= e($s['tempat_lahir']) ?>, <?= e(tgl($s['tgl_lahir'])) ?></dd></div>
          <div><dt>Status</dt><dd><?= chip('lulus') ?> Tahun Ajaran <?= e($_s['tahun_ajaran']) ?></dd></div>
        </dl>
      <?php else: ?>
        <div class="verif-ikon"><?= icon('awas', 40) ?></div>
        <h2>SKL tidak ditemukan</h2>
        <p class="muted">Kode verifikasi tidak terdaftar atau SKL sudah dibatalkan. Hubungi <?= e($_s['nama_sekolah']) ?><?= $_s['telepon'] ? ' di ' . e($_s['telepon']) : '' ?> untuk memastikan keaslian dokumen.</p>
      <?php endif; ?>
    </div>
  </article>
</main>
<?php require __DIR__ . '/../includes/pub_footer.php'; ?>
