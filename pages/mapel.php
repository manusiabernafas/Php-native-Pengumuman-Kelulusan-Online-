<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($aksi === 'hapus') {
        require_admin();
        q('DELETE FROM mapel WHERE id = ?', [$id]);
        flash('Mapel dihapus beserta nilainya.');
        redirect('mapel');
    }
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $kel = mb_substr(trim((string) ($_POST['kelompok'] ?? 'A')), 0, 10);
    $urut = max(1, min(99, (int) ($_POST['urutan'] ?? 1)));
    if ($nama === '' || mb_strlen($nama) > 100) {
        flash('Nama mapel wajib diisi (maks. 100 huruf).', 'error');
    } elseif (q('SELECT id FROM mapel WHERE nama = ? AND id <> ?', [$nama, $id])->fetch()) {
        flash('Mapel dengan nama itu sudah ada.', 'error');
    } else {
        $id ? q('UPDATE mapel SET nama = ?, kelompok = ?, urutan = ? WHERE id = ?', [$nama, $kel, $urut, $id])
            : q('INSERT INTO mapel (nama, kelompok, urutan) VALUES (?, ?, ?)', [$nama, $kel, $urut]);
        flash('Mapel ' . $nama . ' disimpan.');
    }
    redirect('mapel');
}
$rows = q('SELECT m.*, COUNT(n.siswa_id) jml, AVG(n.nilai) rata FROM mapel m LEFT JOIN nilai n ON n.mapel_id = m.id GROUP BY m.id ORDER BY m.urutan, m.id')->fetchAll();
$title = 'Mata pelajaran';
$sub = 'Urutan di sini menjadi urutan di halaman hasil, SKL, dan kolom impor CSV.';
require __DIR__ . '/../includes/header.php';
?>
<section class="kartu rata">
  <div class="tabel-wrap">
  <table class="tabel">
    <thead><tr><th style="width:80px">Urutan</th><th style="width:90px">Kelompok</th><th>Nama mapel</th><th class="kanan">Rata-rata</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $m): $fid = 'm' . $m['id']; ?>
      <tr>
        <td><input class="input" form="<?= $fid ?>" name="urutan" value="<?= (int) $m['urutan'] ?>" inputmode="numeric" aria-label="Urutan"></td>
        <td><input class="input" form="<?= $fid ?>" name="kelompok" value="<?= e($m['kelompok']) ?>" aria-label="Kelompok"></td>
        <td><input class="input" form="<?= $fid ?>" name="nama" value="<?= e($m['nama']) ?>" aria-label="Nama mapel"><small class="muted"><?= (int) $m['jml'] ?> nilai</small></td>
        <td class="kanan mono"><?= angka($m['rata']) ?></td>
        <td class="kanan nowrap">
          <form id="<?= $fid ?>" method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn kecil">Simpan</button></form>
          <?php if (is_admin()): ?><form method="post" class="inline" data-yakin="Hapus mapel <?= e($m['nama']) ?>? Semua nilainya ikut terhapus."><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="ibtn" aria-label="Hapus <?= e($m['nama']) ?>"><?= icon('sampah', 16) ?></button></form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
      <tr class="baris-baru">
        <td><input class="input" form="baru" name="urutan" value="<?= count($rows) + 1 ?>" inputmode="numeric" aria-label="Urutan"></td>
        <td><input class="input" form="baru" name="kelompok" value="A" aria-label="Kelompok"></td>
        <td><input class="input" form="baru" name="nama" placeholder="Nama mapel baru" aria-label="Nama mapel baru"></td>
        <td></td>
        <td class="kanan"><form id="baru" method="post"><?= csrf_field() ?><button class="btn emas kecil"><?= icon('plus', 15) ?>Tambah</button></form></td>
      </tr>
    </tbody>
  </table>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
