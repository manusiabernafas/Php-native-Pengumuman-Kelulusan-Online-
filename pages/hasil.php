<?php
// Panitia bisa melihat tampilan siswa sebelum pengumuman dibuka (pratinjau)
$pratinjau = user() && isset($_GET['pratinjau']);
$id = $pratinjau ? (int) $_GET['pratinjau'] : (int) ($_SESSION['siswa_id'] ?? 0);
if (!$pratinjau && (status_pengumuman() !== 'buka' || !$id)) redirect('beranda');
$s = q('SELECT * FROM siswa WHERE id = ?', [$id])->fetch();
if (!$s) {
    unset($_SESSION['siswa_id']);
    redirect('beranda');
}
$lulus = $s['status'] === 'lulus';
$nilai = nilai_siswa($id);
$tampilNilai = setelan('tampil_nilai') === '1' && $nilai;
$title = 'Hasil Kelulusan';
require __DIR__ . '/../includes/pub_header.php';
$pesan = ['lulus' => $_s['pesan_lulus'], 'tidak_lulus' => $_s['pesan_tidak_lulus'], 'ditunda' => $_s['pesan_ditunda']][$s['status']];
?>
<main class="panggung hasil-hal">
  <?php if ($lulus): ?>
    <div class="konfeti" aria-hidden="true"><?php for ($i = 0; $i < 36; $i++): ?><i style="--x:<?= ($i * 37) % 100 ?>%;--d:<?= ($i * 13) % 9 / 10 ?>s;--r:<?= ($i * 47) % 360 ?>deg" class="k<?= $i % 4 ?>"></i><?php endfor; ?></div>
  <?php endif; ?>
  <?php if ($pratinjau): ?><p class="pesan info lebar"><?= icon('mata') ?>Pratinjau panitia: tampilan ini yang akan dilihat siswa. <a href="<?= url('siswa.form', ['id' => $id]) ?>">Kembali ke data siswa</a></p><?php endif; ?>

  <article class="sertifikat status-<?= e($s['status']) ?>">
    <div class="bingkai">
      <header class="sert-kepala">
        <p class="kecil">Pengumuman Kelulusan Tahun Ajaran <?= e($_s['tahun_ajaran']) ?></p>
        <h2><?= e($_s['nama_sekolah']) ?></h2>
      </header>
      <p class="sert-pembuka">Berdasarkan rapat dewan guru, siswa dengan data berikut dinyatakan</p>
      <div class="cap"><span><?= e(STATUS[$s['status']][0]) ?></span></div>
      <dl class="data-siswa">
        <div><dt>Nama</dt><dd><b><?= e($s['nama']) ?></b></dd></div>
        <div><dt>NISN</dt><dd class="mono"><?= e($s['nisn']) ?></dd></div>
        <div><dt>Tempat, tanggal lahir</dt><dd><?= e($s['tempat_lahir']) ?>, <?= e(tgl($s['tgl_lahir'])) ?></dd></div>
        <div><dt>Kelas</dt><dd><?= e($s['kelas']) ?></dd></div>
      </dl>
      <?php if ($pesan): ?><p class="sert-pesan"><?= e($pesan) ?></p><?php endif; ?>
      <?php if (!$lulus && $s['catatan']): ?><p class="pesan info"><?= icon('info') ?>Catatan: <?= e($s['catatan']) ?></p><?php endif; ?>

      <?php if ($tampilNilai): ?>
        <details class="nilai-buka" <?= $lulus ? '' : 'open' ?>>
          <summary>Daftar nilai (rata-rata <?= angka(rata($nilai)) ?>)</summary>
          <table class="tabel nilai">
            <thead><tr><th>No</th><th>Mata pelajaran</th><th class="kanan">Nilai</th></tr></thead>
            <tbody>
            <?php $no = 0; foreach (mapel_semua() as $m): if (!isset($nilai[$m['id']])) continue; ?>
              <tr><td><?= ++$no ?></td><td><?= e($m['nama']) ?></td><td class="kanan mono"><?= angka($nilai[$m['id']]) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </details>
      <?php endif; ?>

      <div class="aksi-hasil">
        <?php if ($lulus && $_s['unduh_skl'] === '1'): ?>
          <a class="btn emas" href="<?= url($pratinjau ? 'skl' : 'skl.saya', $pratinjau ? ['id' => $id] : []) ?>" target="_blank"><?= icon('unduh') ?>Unduh / cetak SKL</a>
        <?php endif; ?>
        <?php if (!$pratinjau): ?>
          <form method="post" action="<?= url('keluar.siswa') ?>"><?= csrf_field() ?><button class="btn garis"><?= icon('keluar') ?>Selesai</button></form>
        <?php endif; ?>
      </div>
    </div>
  </article>
  <p class="bantuan">Tekan <b>Selesai</b> bila memakai HP / komputer milik orang lain.</p>
</main>
<?php require __DIR__ . '/../includes/pub_footer.php'; ?>
