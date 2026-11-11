<?php
$status = status_pengumuman();
$kunci = 'cek:' . ip();
$error = '';
$lama = ['nisn' => '', 'tgl_lahir' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = ['nisn' => preg_replace('/\D/', '', (string) ($_POST['nisn'] ?? '')), 'tgl_lahir' => (string) ($_POST['tgl_lahir'] ?? '')];
    if (($_POST['website'] ?? '') !== '') {
        // Honeypot: kolom tersembunyi hanya diisi bot
        $error = 'Data tidak cocok. Periksa kembali NISN dan tanggal lahir.';
    } elseif ($status !== 'buka') {
        $error = $status === 'belum' ? 'Pengumuman belum dibuka. Tunggu hitung mundur selesai.' : 'Masa pengumuman online sudah ditutup.';
    } elseif (coba_terkunci($kunci, 5, 900)) {
        q("INSERT INTO log_akses (waktu, ip, hasil) VALUES (NOW(), ?, 'terkunci')", [ip()]);
        $error = 'Terlalu banyak percobaan yang salah. Tunggu 15 menit lalu coba lagi.';
    } elseif (!preg_match('/^\d{4,20}$/', $lama['nisn']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lama['tgl_lahir'])) {
        $error = 'Isi NISN (angka saja) dan tanggal lahir dengan lengkap.';
    } else {
        $s = q('SELECT id FROM siswa WHERE nisn = ? AND tgl_lahir = ?', [$lama['nisn'], $lama['tgl_lahir']])->fetch();
        if ($s) {
            coba_reset($kunci);
            q("INSERT INTO log_akses (waktu, siswa_id, ip, hasil) VALUES (NOW(), ?, ?, 'cocok')", [$s['id'], ip()]);
            q('UPDATE siswa SET dilihat_at = COALESCE(dilihat_at, NOW()), dilihat_kali = dilihat_kali + 1 WHERE id = ?', [$s['id']]);
            session_regenerate_id(true);
            $_SESSION['siswa_id'] = (int) $s['id'];
            redirect('hasil');
        }
        coba_gagal($kunci, 900);
        q("INSERT INTO log_akses (waktu, ip, hasil) VALUES (NOW(), ?, 'gagal')", [ip()]);
        // Pesan sengaja sama untuk NISN salah maupun tanggal lahir salah
        $error = 'Data tidak cocok. Periksa kembali NISN dan tanggal lahir.';
    }
}

$buka = strtotime($_s_buka = setelan('waktu_buka'));
$title = 'Pengumuman Kelulusan';
require __DIR__ . '/../includes/pub_header.php';
?>
<main class="panggung">
  <div class="konfeti-latar" aria-hidden="true"></div>
  <section class="pub-hero">
    <p class="pita">Tahun Ajaran <?= e($_s['tahun_ajaran']) ?></p>
    <h1>Pengumuman<br>Kelulusan</h1>
    <p class="hero-sub">Kelas XII <?= e($_s['nama_sekolah']) ?> · <?= e(hari_id($_s_buka)) ?>, <?= e(tgl_jam($_s_buka)) ?></p>

    <?php if ($status === 'belum'): ?>
      <div class="hitung" data-hitung="<?= $buka * 1000 ?>" data-server="<?= round(microtime(true) * 1000) ?>" aria-live="polite">
        <div><b data-d>--</b><span>Hari</span></div>
        <div><b data-j>--</b><span>Jam</span></div>
        <div><b data-m>--</b><span>Menit</span></div>
        <div><b data-s>--</b><span>Detik</span></div>
      </div>
      <p class="hitung-ket">Halaman akan terbuka otomatis saat waktunya tiba. Tidak perlu menekan tombol muat ulang berkali-kali.</p>
    <?php endif; ?>
  </section>

  <section class="kartu-cek <?= $status !== 'buka' ? 'redup' : '' ?>" id="cek">
    <div class="bingkai">
      <h2>Cek Hasil Kelulusan</h2>
      <?php if ($status === 'tutup'): ?>
        <p class="pesan info"><?= icon('info') ?>Masa pengumuman online sudah ditutup. Silakan menghubungi sekolah untuk informasi kelulusan.</p>
      <?php else: ?>
        <p class="kecil muted">Masukkan NISN dan tanggal lahir sesuai data di sekolah.</p>
        <?php if ($error): ?><p class="pesan error" role="alert"><?= icon('awas') ?><?= e($error) ?></p><?php endif; ?>
        <?php if (!empty($_SESSION['siswa_id']) && $status === 'buka'): ?>
          <p class="pesan info"><?= icon('info') ?><span>Kamu sudah membuka hasil di perangkat ini. <a href="<?= url('hasil') ?>">Lihat lagi</a></span></p>
        <?php endif; ?>
        <form method="post" action="<?= url('beranda') ?>#cek" class="form-cek" autocomplete="off">
          <?= csrf_field() ?>
          <label class="f"><span>NISN</span><input name="nisn" value="<?= e($lama['nisn']) ?>" inputmode="numeric" pattern="\d{4,20}" maxlength="20" required placeholder="10 digit, mis. 0070012345" <?= $status !== 'buka' ? 'disabled' : '' ?>></label>
          <label class="f"><span>Tanggal lahir</span><input type="date" name="tgl_lahir" value="<?= e($lama['tgl_lahir']) ?>" required <?= $status !== 'buka' ? 'disabled' : '' ?>></label>
          <label class="madu" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
          <button class="btn emas besar" <?= $status !== 'buka' ? 'disabled' : '' ?>><?= icon($status === 'buka' ? 'cari' : 'kunci') ?><?= $status === 'buka' ? 'Lihat hasil' : 'Belum dibuka' ?></button>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <section class="langkah">
    <div><span>1</span><h3>Siapkan NISN</h3><p>Lihat di kartu pelajar, rapor, atau cek di laman NISN Kemendikdasmen.</p></div>
    <div><span>2</span><h3>Isi tanggal lahir</h3><p>Sesuai akta kelahiran / data Dapodik sekolah.</p></div>
    <div><span>3</span><h3>Unduh SKL</h3><p>Siswa yang lulus bisa mencetak Surat Keterangan Lulus ber-QR untuk daftar kuliah atau kerja.</p></div>
  </section>
  <?php if ($_s['kontak_wa']): ?>
    <p class="bantuan">Data tidak cocok atau ada kendala? <a href="https://wa.me/<?= e(preg_replace('/^0/', '62', preg_replace('/\D/', '', $_s['kontak_wa']))) ?>" target="_blank" rel="noopener">Hubungi panitia lewat WhatsApp</a></p>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../includes/pub_footer.php'; ?>
