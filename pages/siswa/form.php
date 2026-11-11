<?php
$id = (int) ($_GET['id'] ?? 0);
$s = $id ? q('SELECT * FROM siswa WHERE id = ?', [$id])->fetch() : null;
if ($id && !$s) redirect('siswa');
$d = $s ?: ['nisn' => '', 'nis' => '', 'nama' => '', 'jk' => 'L', 'kelas' => '', 'tempat_lahir' => '', 'tgl_lahir' => '', 'nama_ortu' => '', 'status' => 'lulus', 'catatan' => ''];
$nilai = $id ? nilai_siswa($id) : [];
$mapel = mapel_semua();
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nisn', 'nis', 'nama', 'jk', 'kelas', 'tempat_lahir', 'tgl_lahir', 'nama_ortu', 'status', 'catatan'] as $k) $d[$k] = trim((string) ($_POST[$k] ?? ''));
    $d['nisn'] = preg_replace('/\s/', '', $d['nisn']);
    if (!preg_match('/^\d{4,20}$/', $d['nisn'])) $err[] = 'NISN harus berupa angka (4-20 digit).';
    elseif (q('SELECT id FROM siswa WHERE nisn = ? AND id <> ?', [$d['nisn'], $id])->fetch()) $err[] = 'NISN sudah dipakai siswa lain.';
    if ($d['nama'] === '' || mb_strlen($d['nama']) > 120) $err[] = 'Nama wajib diisi (maks. 120 huruf).';
    if ($d['kelas'] === '' || mb_strlen($d['kelas']) > 30) $err[] = 'Kelas wajib diisi.';
    $t = DateTime::createFromFormat('!Y-m-d', $d['tgl_lahir']);
    if (!$t || $t->format('Y-m-d') !== $d['tgl_lahir'] || $t > new DateTime('-10 years')) $err[] = 'Tanggal lahir tidak valid.';
    if (!in_array($d['jk'], ['L', 'P'], true)) $d['jk'] = 'L';
    if (!isset(STATUS[$d['status']])) $err[] = 'Pilih status kelulusan.';
    $nilaiBaru = [];
    foreach ($mapel as $m) {
        $v = str_replace(',', '.', trim((string) ($_POST['nilai'][$m['id']] ?? '')));
        if ($v === '') continue;
        if (!is_numeric($v) || $v < 0 || $v > 100) { $err[] = 'Nilai ' . $m['nama'] . ' harus 0-100.'; continue; }
        $nilaiBaru[$m['id']] = round((float) $v, 2);
    }
    $nilai = $nilaiBaru + $nilai;
    if (!$err) {
        db()->beginTransaction();
        $kolom = [$d['nisn'], $d['nis'], $d['nama'], $d['jk'], $d['kelas'], $d['tempat_lahir'], $d['tgl_lahir'], $d['nama_ortu'], $d['status'], mb_substr($d['catatan'], 0, 255)];
        if ($id) {
            q('UPDATE siswa SET nisn=?, nis=?, nama=?, jk=?, kelas=?, tempat_lahir=?, tgl_lahir=?, nama_ortu=?, status=?, catatan=? WHERE id = ?', [...$kolom, $id]);
            if (!empty($_POST['token_baru'])) q('UPDATE siswa SET token = ? WHERE id = ?', [token_baru(), $id]);
        } else {
            q('INSERT INTO siswa (nisn, nis, nama, jk, kelas, tempat_lahir, tgl_lahir, nama_ortu, status, catatan, token) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [...$kolom, token_baru()]);
            $id = (int) db()->lastInsertId();
        }
        beri_nomor($id);
        q('DELETE FROM nilai WHERE siswa_id = ?', [$id]);
        foreach ($nilaiBaru as $m => $v) q('INSERT INTO nilai (siswa_id, mapel_id, nilai) VALUES (?, ?, ?)', [$id, $m, $v]);
        db()->commit();
        flash('Data ' . $d['nama'] . ' disimpan.');
        redirect('siswa.form', ['id' => $id]);
    }
}

$title = $s ? $s['nama'] : 'Tambah siswa';
$kembali = [url('siswa'), 'Daftar siswa'];
if ($s) {
    $sub = 'NISN ' . e($s['nisn']) . ' · ' . chip($s['status']) . ($s['no_urut'] ? ' · SKL ' . e(nomor_skl((int) $s['no_urut'])) : '');
    $actions = '<a class="btn" href="' . url('hasil', ['pratinjau' => $s['id']]) . '" target="_blank">' . icon('mata') . 'Pratinjau siswa</a>'
             . ($s['status'] === 'lulus' ? '<a class="btn" href="' . url('skl', ['id' => $s['id']]) . '" target="_blank">' . icon('cetak') . 'Cetak SKL</a>' : '');
}
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= implode('<br>', array_map('e', $err)) ?></span></div><?php endif; ?>
<form method="post" class="grid dua lebar-kiri">
  <?= csrf_field() ?>
  <section class="kartu">
    <h3>Data siswa</h3>
    <div class="baris-f">
      <label class="f"><span>NISN</span><input name="nisn" value="<?= e($d['nisn']) ?>" inputmode="numeric" required maxlength="20"></label>
      <label class="f"><span>NIS</span><input name="nis" value="<?= e($d['nis']) ?>" maxlength="20"></label>
    </div>
    <label class="f"><span>Nama lengkap</span><input name="nama" value="<?= e($d['nama']) ?>" required maxlength="120"></label>
    <div class="baris-f">
      <label class="f"><span>Jenis kelamin</span><select name="jk"><option value="L">Laki-laki</option><option value="P" <?= $d['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option></select></label>
      <label class="f"><span>Kelas</span><input name="kelas" value="<?= e($d['kelas']) ?>" required maxlength="30" placeholder="XII-1" list="daftar-kelas"></label>
    </div>
    <datalist id="daftar-kelas"><?php foreach (q('SELECT DISTINCT kelas FROM siswa ORDER BY kelas')->fetchAll(PDO::FETCH_COLUMN) as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
    <div class="baris-f">
      <label class="f"><span>Tempat lahir</span><input name="tempat_lahir" value="<?= e($d['tempat_lahir']) ?>" maxlength="60"></label>
      <label class="f"><span>Tanggal lahir</span><input type="date" name="tgl_lahir" value="<?= e($d['tgl_lahir']) ?>" required><small>Dipakai siswa untuk membuka pengumuman.</small></label>
    </div>
    <label class="f"><span>Nama orang tua / wali</span><input name="nama_ortu" value="<?= e($d['nama_ortu']) ?>" maxlength="120"></label>
    <h3 class="mt">Keputusan</h3>
    <div class="pilih-status">
      <?php foreach (STATUS as $k => [$l, $w]): ?>
        <label class="opsi <?= $w ?>"><input type="radio" name="status" value="<?= $k ?>" <?= $d['status'] === $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label>
      <?php endforeach; ?>
    </div>
    <label class="f"><span>Catatan untuk siswa</span><input name="catatan" value="<?= e($d['catatan']) ?>" maxlength="255" placeholder="mis. alasan ditunda: belum menyerahkan pas foto"><small>Tampil di halaman hasil bila status tidak lulus / ditunda.</small></label>
    <?php if ($s): ?><label class="cek"><input type="checkbox" name="token_baru" value="1"> Buat kode QR baru (SKL lama yang sudah tercetak menjadi tidak sah)</label><?php endif; ?>
  </section>
  <section class="kartu">
    <div class="kartu-kepala"><h3>Nilai</h3><span class="kecil muted">rata-rata <?= angka(rata($nilai)) ?></span></div>
    <?php if (!$mapel): ?><p class="muted">Belum ada mata pelajaran. <a href="<?= url('mapel') ?>">Tambah mapel</a></p><?php endif; ?>
    <div class="daftar-nilai">
      <?php foreach ($mapel as $m): ?>
        <label><span><?= e($m['nama']) ?></span><input name="nilai[<?= (int) $m['id'] ?>]" value="<?= isset($nilai[$m['id']]) ? e(rtrim(rtrim(number_format((float) $nilai[$m['id']], 2, '.', ''), '0'), '.')) : '' ?>" inputmode="decimal" aria-label="Nilai <?= e($m['nama']) ?>"></label>
      <?php endforeach; ?>
    </div>
  </section>
  <div class="aksi-bawah"><button class="btn emas besar"><?= icon('centang') ?>Simpan</button></div>
</form>
<?php if ($s): ?>
<form method="post" action="<?= url('siswa.aksi') ?>" class="hapus-form" data-yakin="Hapus <?= e($s['nama']) ?> beserta nilainya?">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
  <button class="tautan merah"><?= icon('sampah', 16) ?>Hapus siswa ini</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
