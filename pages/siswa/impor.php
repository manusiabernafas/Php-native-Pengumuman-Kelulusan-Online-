<?php
$mapel = mapel_semua();
$KOLOM = ['nisn', 'nis', 'nama', 'jk', 'kelas', 'tempat_lahir', 'tgl_lahir', 'nama_ortu', 'status', 'catatan'];

if (($_GET['unduh'] ?? '') === 'templat') {
    $contoh = ['0071234567', '23001', 'Contoh Nama Siswa', 'L', 'XII-1', 'Purwosari', '2008-05-17', 'Nama Ayah/Ibu', 'lulus', ''];
    kirim_csv('templat-impor-kelulusan.csv', [array_merge($KOLOM, array_column($mapel, 'nama')), array_merge($contoh, array_fill(0, count($mapel), '85'))]);
}

/** Terima 2008-05-17, 17/05/2008, 17-05-2008, atau 17.05.2008 */
function tanggal_csv(string $v): ?string
{
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) [$y, $b, $d] = [$m[1], $m[2], $m[3]];
    elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m)) [$d, $b, $y] = [$m[1], $m[2], $m[3]];
    else return null;
    return checkdate((int) $b, (int) $d, (int) $y) ? sprintf('%04d-%02d-%02d', $y, $b, $d) : null;
}

$hasil = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_FILES['berkas'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2_000_000) {
        flash('Pilih berkas CSV (maks. 2 MB).', 'error');
        redirect('siswa.impor');
    }
    $baris = baca_csv($f['tmp_name'], 3000);
    $kepala = array_map(fn($h) => strtolower(trim(preg_replace('/\s+/', ' ', $h))), array_shift($baris) ?? []);
    $pos = array_flip($kepala);
    if (!isset($pos['nisn'], $pos['nama'], $pos['kelas'], $pos['tgl_lahir'])) {
        flash('Kolom wajib tidak ditemukan. Pakai templat: baris pertama berisi nisn, nama, kelas, tgl_lahir, dst.', 'error');
        redirect('siswa.impor');
    }
    $kolomNilai = [];
    foreach ($mapel as $m) if (isset($pos[strtolower($m['nama'])])) $kolomNilai[$m['id']] = $pos[strtolower($m['nama'])];
    $abaikan = array_values(array_diff($kepala, $KOLOM, array_map(fn($m) => strtolower($m['nama']), $mapel), ['']));
    $hasil = ['baru' => 0, 'ubah' => 0, 'salah' => [], 'abaikan' => $abaikan];
    $ambil = fn($r, $k) => isset($pos[$k]) ? trim((string) ($r[$pos[$k]] ?? '')) : '';
    db()->beginTransaction();
    foreach ($baris as $i => $r) {
        $no = $i + 2;
        $nisn = preg_replace('/\D/', '', $ambil($r, 'nisn'));
        // NISN selalu 10 digit; Excel sering membuang angka 0 di depan
        if (strlen($nisn) >= 7 && strlen($nisn) < 10) $nisn = str_pad($nisn, 10, '0', STR_PAD_LEFT);
        $nama = $ambil($r, 'nama');
        $tgl = tanggal_csv($ambil($r, 'tgl_lahir'));
        $st = strtolower(str_replace(' ', '_', $ambil($r, 'status')));
        $st = ['l' => 'lulus', 'tl' => 'tidak_lulus', 'tunda' => 'ditunda'][$st] ?? $st;
        if (strlen($nisn) < 4 || $nama === '' || $ambil($r, 'kelas') === '' || !$tgl) {
            $hasil['salah'][] = "Baris $no: NISN, nama, kelas, dan tanggal lahir wajib benar.";
            continue;
        }
        if ($st !== '' && !isset(STATUS[$st])) {
            $hasil['salah'][] = "Baris $no: status \"" . $ambil($r, 'status') . '" tidak dikenal (pakai lulus / tidak lulus / ditunda).';
            continue;
        }
        $ada = q('SELECT id, status FROM siswa WHERE nisn = ?', [$nisn])->fetch();
        $jk = strtoupper(substr($ambil($r, 'jk'), 0, 1)) === 'P' ? 'P' : 'L';
        $v = [$ambil($r, 'nis'), mb_substr($nama, 0, 120), $jk, mb_substr($ambil($r, 'kelas'), 0, 30), mb_substr($ambil($r, 'tempat_lahir'), 0, 60), $tgl,
              mb_substr($ambil($r, 'nama_ortu'), 0, 120), $st ?: ($ada['status'] ?? 'lulus'), mb_substr($ambil($r, 'catatan'), 0, 255)];
        if ($ada) {
            q('UPDATE siswa SET nis=?, nama=?, jk=?, kelas=?, tempat_lahir=?, tgl_lahir=?, nama_ortu=?, status=?, catatan=? WHERE id = ?', [...$v, $ada['id']]);
            $id = (int) $ada['id'];
            $hasil['ubah']++;
        } else {
            q('INSERT INTO siswa (nis, nama, jk, kelas, tempat_lahir, tgl_lahir, nama_ortu, status, catatan, nisn, token) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [...$v, $nisn, token_baru()]);
            $id = (int) db()->lastInsertId();
            $hasil['baru']++;
        }
        beri_nomor($id);
        foreach ($kolomNilai as $mid => $p) {
            $n = str_replace(',', '.', trim((string) ($r[$p] ?? '')));
            if ($n === '') continue;
            if (!is_numeric($n) || $n < 0 || $n > 100) { $hasil['salah'][] = "Baris $no: nilai \"$n\" diabaikan (harus 0-100)."; continue; }
            q('INSERT INTO nilai (siswa_id, mapel_id, nilai) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)', [$id, $mid, round((float) $n, 2)]);
        }
    }
    db()->commit();
}

$title = 'Impor siswa & nilai';
$kembali = [url('siswa'), 'Daftar siswa'];
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($hasil): ?>
  <div class="pesan <?= $hasil['salah'] ? 'info' : 'ok' ?>"><?= icon('centang') ?><span><b><?= $hasil['baru'] ?></b> siswa baru, <b><?= $hasil['ubah'] ?></b> diperbarui<?= $hasil['salah'] ? ', ' . count($hasil['salah']) . ' baris bermasalah' : '' ?>.
    <?= $hasil['abaikan'] ? '<br>Kolom tidak dikenal diabaikan: ' . e(implode(', ', $hasil['abaikan'])) : '' ?></span></div>
  <?php if ($hasil['salah']): ?><div class="kartu"><h3>Baris yang dilewati</h3><ul class="daftar-salah"><?php foreach (array_slice($hasil['salah'], 0, 30) as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php endif; ?>
<div class="grid dua">
  <form method="post" enctype="multipart/form-data" class="kartu">
    <?= csrf_field() ?>
    <h3>Unggah berkas CSV</h3>
    <p class="muted kecil">NISN yang sudah ada akan diperbarui, NISN baru ditambahkan. Data siswa lain tidak berubah.</p>
    <label class="f"><span>Berkas .csv</span><input type="file" name="berkas" accept=".csv,text/csv" required></label>
    <button class="btn emas"><?= icon('unggah') ?>Impor sekarang</button>
  </form>
  <section class="kartu">
    <h3>Cara menyiapkan</h3>
    <ol class="langkah-kecil">
      <li><a href="<?= url('siswa.impor', ['unduh' => 'templat']) ?>">Unduh templat CSV</a> lalu buka di Excel / Google Sheets.</li>
      <li>Salin data dari Dapodik atau leger nilai. Kolom wajib: <code>nisn</code>, <code>nama</code>, <code>kelas</code>, <code>tgl_lahir</code>.</li>
      <li>Tanggal boleh <code>2008-05-17</code> atau <code>17/05/2008</code>. Status: <code>lulus</code>, <code>tidak lulus</code>, atau <code>ditunda</code>.</li>
      <li>Kolom nilai memakai nama mapel persis seperti di menu <a href="<?= url('mapel') ?>">Mapel</a> (<?= count($mapel) ?> mapel).</li>
      <li>Simpan sebagai <b>CSV</b> (pemisah titik koma atau koma, keduanya bisa).</li>
    </ol>
    <p class="kecil muted">Tips: NISN di Excel sering kehilangan angka 0 di depan. Format kolomnya sebagai <b>Teks</b> sebelum menempel data.</p>
  </section>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
