<?php
require_admin();
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'reset_log') {
        q('DELETE FROM log_akses');
        q('DELETE FROM batas_coba');
        q('UPDATE siswa SET dilihat_at = NULL, dilihat_kali = 0');
        flash('Log akses dan penanda "sudah membuka" dikosongkan. Siap untuk hari pengumuman.');
        redirect('pengaturan');
    }
    $d = [];
    foreach (['nama_sekolah', 'npsn', 'alamat', 'kota', 'telepon', 'email', 'website', 'kontak_wa', 'kepala_sekolah', 'nip_kepala', 'tahun_ajaran',
              'format_nomor', 'pesan_lulus', 'pesan_tidak_lulus', 'pesan_ditunda', 'tanggal_skl'] as $k) {
        $d[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $d['tampil_nilai'] = empty($_POST['tampil_nilai']) ? '0' : '1';
    $d['unduh_skl'] = empty($_POST['unduh_skl']) ? '0' : '1';
    $buka = strtotime((string) ($_POST['waktu_buka'] ?? ''));
    $tutup = trim((string) ($_POST['waktu_tutup'] ?? '')) === '' ? null : strtotime((string) $_POST['waktu_tutup']);
    if ($d['nama_sekolah'] === '') $err[] = 'Nama sekolah wajib diisi.';
    if (!$buka) $err[] = 'Isi tanggal dan jam pengumuman dibuka.';
    if ($tutup === false || ($tutup && $buka && $tutup <= $buka)) $err[] = 'Waktu tutup harus setelah waktu dibuka (atau kosongkan).';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['tanggal_skl'])) $err[] = 'Tanggal SKL tidak valid.';
    if (!str_contains($d['format_nomor'], '{no}')) $err[] = 'Format nomor SKL wajib memuat {no}.';
    if (mb_strlen($d['pesan_lulus'] . $d['pesan_tidak_lulus'] . $d['pesan_ditunda']) > 3000) $err[] = 'Pesan terlalu panjang.';
    $f = $_FILES['logo'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        $info = @getimagesize($f['tmp_name']);
        $ext = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if (!$ext || $f['size'] > 1_000_000) $err[] = 'Logo harus gambar PNG/JPG/WEBP maks. 1 MB.';
        else {
            $nama = 'logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
            move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/' . $nama);
            $lama = setelan('logo');
            if ($lama && preg_match('/^logo-[a-f0-9]+\.(png|jpg|webp)$/', $lama)) @unlink(__DIR__ . '/../uploads/' . $lama);
            $d['logo'] = $nama;
        }
    } elseif (!empty($_POST['hapus_logo'])) {
        $d['logo'] = '';
    }
    if (!$err) {
        $d['waktu_buka'] = date('Y-m-d H:i:s', $buka);
        $d['waktu_tutup'] = $tutup ? date('Y-m-d H:i:s', $tutup) : '';
        $d = array_map(fn($v) => mb_substr($v, 0, 1000), $d);
        simpan_setelan($d);
        flash('Pengaturan disimpan.');
        redirect('pengaturan');
    }
}
$s = array_merge(setelan(), $_SERVER['REQUEST_METHOD'] === 'POST' ? array_intersect_key($_POST, PENGATURAN_AWAL) : []);
$dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
$title = 'Pengaturan';
$sub = 'Jadwal pengumuman, identitas sekolah, dan isi SKL.';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= implode('<br>', array_map('e', $err)) ?></span></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="grid dua lebar-kiri">
  <?= csrf_field() ?>
  <div class="tumpuk">
    <section class="kartu sorot">
      <h3>Jadwal pengumuman</h3>
      <div class="baris-f">
        <label class="f"><span>Dibuka</span><input type="datetime-local" name="waktu_buka" value="<?= e($dt($s['waktu_buka'])) ?>" required><small>Sebelum waktu ini siswa melihat hitung mundur.</small></label>
        <label class="f"><span>Ditutup (opsional)</span><input type="datetime-local" name="waktu_tutup" value="<?= e($dt($s['waktu_tutup'])) ?>"><small>Kosongkan agar tetap bisa dibuka.</small></label>
      </div>
      <div class="baris-f">
        <label class="f"><span>Tahun ajaran</span><input name="tahun_ajaran" value="<?= e($s['tahun_ajaran']) ?>" maxlength="20"></label>
        <label class="f"><span>Tanggal SKL</span><input type="date" name="tanggal_skl" value="<?= e($s['tanggal_skl']) ?>"></label>
      </div>
      <label class="cek"><input type="checkbox" name="tampil_nilai" value="1" <?= $s['tampil_nilai'] === '1' ? 'checked' : '' ?>> Tampilkan daftar nilai di halaman hasil dan SKL</label>
      <label class="cek"><input type="checkbox" name="unduh_skl" value="1" <?= $s['unduh_skl'] === '1' ? 'checked' : '' ?>> Siswa lulus boleh mengunduh / mencetak SKL sendiri</label>
    </section>
    <section class="kartu">
      <h3>Pesan di halaman hasil</h3>
      <label class="f"><span>Untuk siswa lulus</span><textarea name="pesan_lulus" rows="3"><?= e($s['pesan_lulus']) ?></textarea></label>
      <label class="f"><span>Untuk siswa tidak lulus</span><textarea name="pesan_tidak_lulus" rows="3"><?= e($s['pesan_tidak_lulus']) ?></textarea></label>
      <label class="f"><span>Untuk siswa ditunda</span><textarea name="pesan_ditunda" rows="3"><?= e($s['pesan_ditunda']) ?></textarea></label>
    </section>
  </div>
  <div class="tumpuk">
    <section class="kartu">
      <h3>Identitas sekolah</h3>
      <label class="f"><span>Nama sekolah</span><input name="nama_sekolah" value="<?= e($s['nama_sekolah']) ?>" required maxlength="120"></label>
      <div class="baris-f">
        <label class="f"><span>NPSN</span><input name="npsn" value="<?= e($s['npsn']) ?>" maxlength="20"></label>
        <label class="f"><span>Kota (untuk TTD)</span><input name="kota" value="<?= e($s['kota']) ?>" maxlength="60"></label>
      </div>
      <label class="f"><span>Alamat</span><input name="alamat" value="<?= e($s['alamat']) ?>" maxlength="200"></label>
      <div class="baris-f">
        <label class="f"><span>Telepon</span><input name="telepon" value="<?= e($s['telepon']) ?>" maxlength="40"></label>
        <label class="f"><span>WhatsApp panitia</span><input name="kontak_wa" value="<?= e($s['kontak_wa']) ?>" maxlength="20" inputmode="tel"></label>
      </div>
      <div class="baris-f">
        <label class="f"><span>Email</span><input name="email" value="<?= e($s['email']) ?>" maxlength="100"></label>
        <label class="f"><span>Website</span><input name="website" value="<?= e($s['website']) ?>" maxlength="100"></label>
      </div>
      <label class="f"><span>Logo sekolah</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG/JPG/WEBP maks. 1 MB, tampil di halaman siswa dan kop SKL.</small></label>
      <?php if ($s['logo']): ?><label class="cek"><img src="uploads/<?= e($s['logo']) ?>" alt="" width="32" height="32"> <input type="checkbox" name="hapus_logo" value="1"> Hapus logo</label><?php endif; ?>
    </section>
    <section class="kartu">
      <h3>Surat Keterangan Lulus</h3>
      <label class="f"><span>Kepala sekolah</span><input name="kepala_sekolah" value="<?= e($s['kepala_sekolah']) ?>" maxlength="120"></label>
      <label class="f"><span>NIP kepala sekolah</span><input name="nip_kepala" value="<?= e($s['nip_kepala']) ?>" maxlength="30"></label>
      <label class="f"><span>Format nomor</span><input name="format_nomor" value="<?= e($s['format_nomor']) ?>" maxlength="80"><small><code>{no}</code> nomor urut 3 digit, <code>{tahun}</code> tahun SKL. Contoh: <?= e(nomor_skl(7)) ?></small></label>
    </section>
  </div>
  <div class="aksi-bawah"><button class="btn emas besar"><?= icon('centang') ?>Simpan pengaturan</button></div>
</form>
<section class="kartu bahaya">
  <h3>Persiapan hari H</h3>
  <p class="kecil muted">Setelah uji coba bersama wali kelas, kosongkan log agar statistik "sudah membuka" mulai dari nol. Nomor SKL juga bisa disusun ulang berurutan per kelas.</p>
  <div class="baris-tombol">
    <form method="post" action="<?= url('siswa.aksi') ?>" data-yakin="Susun ulang semua nomor SKL? SKL yang sudah dicetak akan memakai nomor lama."><?= csrf_field() ?><input type="hidden" name="aksi" value="nomori"><button class="btn">Susun ulang nomor SKL</button></form>
    <form method="post" data-yakin="Kosongkan semua log akses dan penanda siswa sudah membuka?"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset_log"><button class="btn merah">Kosongkan log uji coba</button></form>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
