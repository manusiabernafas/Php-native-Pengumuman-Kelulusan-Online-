<?php
$f = ['kelas' => (string) ($_GET['kelas'] ?? ''), 'status' => (string) ($_GET['status'] ?? ''), 'cari' => trim((string) ($_GET['cari'] ?? '')), 'lihat' => (string) ($_GET['lihat'] ?? '')];
$where = []; $p = [];
if ($f['kelas'] !== '') { $where[] = 'kelas = ?'; $p[] = $f['kelas']; }
if (isset(STATUS[$f['status']])) { $where[] = 'status = ?'; $p[] = $f['status']; }
if ($f['cari'] !== '') { $where[] = '(nama LIKE ? OR nisn LIKE ? OR nis LIKE ?)'; array_push($p, "%{$f['cari']}%", "%{$f['cari']}%", "%{$f['cari']}%"); }
if ($f['lihat'] === 'belum') $where[] = 'dilihat_at IS NULL';
if ($f['lihat'] === 'sudah') $where[] = 'dilihat_at IS NOT NULL';
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$total = (int) q("SELECT COUNT(*) FROM siswa $w", $p)->fetchColumn();
$hal = max(1, (int) ($_GET['hal'] ?? 1)); $per = 50;
$rows = q("SELECT * FROM siswa $w ORDER BY kelas, nama LIMIT $per OFFSET " . (($hal - 1) * $per), $p)->fetchAll();
$daftarKelas = q('SELECT DISTINCT kelas FROM siswa ORDER BY kelas')->fetchAll(PDO::FETCH_COLUMN);

$title = 'Siswa & nilai';
$sub = $total . ' siswa' . ($where ? ' sesuai filter' : '');
$actions = '<a class="btn" href="' . url('siswa.aksi', ['aksi' => 'ekspor'] + array_filter($f)) . '">' . icon('unduh') . 'Ekspor CSV</a>'
         . '<a class="btn" href="' . url('siswa.impor') . '">' . icon('unggah') . 'Impor</a>'
         . '<a class="btn emas" href="' . url('siswa.form') . '">' . icon('plus') . 'Tambah siswa</a>';
require __DIR__ . '/../../includes/header.php';
?>
<form class="filter kartu" method="get" data-auto>
  <input type="hidden" name="page" value="siswa">
  <label class="f"><span>Cari</span><input name="cari" value="<?= e($f['cari']) ?>" placeholder="Nama, NISN, atau NIS"></label>
  <label class="f"><span>Kelas</span><select name="kelas"><option value="">Semua kelas</option><?php foreach ($daftarKelas as $k): ?><option <?= $f['kelas'] === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Status</span><select name="status"><option value="">Semua status</option><?php foreach (STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e(ucfirst(strtolower($l))) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Membuka</span><select name="lihat"><option value="">Semua</option><option value="sudah" <?= $f['lihat'] === 'sudah' ? 'selected' : '' ?>>Sudah membuka</option><option value="belum" <?= $f['lihat'] === 'belum' ? 'selected' : '' ?>>Belum membuka</option></select></label>
  <button class="btn"><?= icon('cari') ?>Tampilkan</button>
</form>

<?php if ($f['kelas'] !== ''): ?>
  <p class="pesan info"><?= icon('cetak') ?><span>Cetak semua SKL siswa lulus kelas <?= e($f['kelas']) ?> sekaligus: <a href="<?= url('skl', ['kelas' => $f['kelas']]) ?>" target="_blank">buka lembar cetak</a></span></p>
<?php endif; ?>

<form method="post" action="<?= url('siswa.aksi') ?>" class="kartu rata" data-yakin="Ubah status siswa yang dicentang?">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="status_massal">
  <div class="tabel-wrap">
  <table class="tabel">
    <thead><tr><th style="width:34px"><input type="checkbox" data-semua aria-label="Pilih semua"></th><th>Siswa</th><th>Kelas</th><th>Lahir</th><th>Status</th><th>Membuka</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><input type="checkbox" name="id[]" value="<?= (int) $r['id'] ?>" aria-label="Pilih <?= e($r['nama']) ?>"></td>
        <td><a href="<?= url('siswa.form', ['id' => $r['id']]) ?>"><b><?= e($r['nama']) ?></b></a><div class="kecil muted mono">NISN <?= e($r['nisn']) ?></div></td>
        <td><?= e($r['kelas']) ?></td>
        <td class="nowrap"><?= e(tgl($r['tgl_lahir'], false)) ?></td>
        <td><?= chip($r['status']) ?><?= $r['catatan'] ? '<div class="kecil muted">' . e($r['catatan']) . '</div>' : '' ?></td>
        <td class="kecil"><?= $r['dilihat_at'] ? e(tgl($r['dilihat_at'], false)) . ' ' . date('H.i', strtotime($r['dilihat_at'])) . ' <span class="muted">(' . (int) $r['dilihat_kali'] . 'x)</span>' : '<span class="muted">belum</span>' ?></td>
        <td class="kanan nowrap">
          <?php if ($r['status'] === 'lulus'): ?><a class="ibtn" href="<?= url('skl', ['id' => $r['id']]) ?>" target="_blank" title="Cetak SKL" aria-label="Cetak SKL <?= e($r['nama']) ?>"><?= icon('cetak', 17) ?></a><?php endif; ?>
          <a class="ibtn" href="<?= url('siswa.form', ['id' => $r['id']]) ?>" title="Ubah" aria-label="Ubah <?= e($r['nama']) ?>"><?= icon('pena', 17) ?></a>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="kosong">Tidak ada siswa. <a href="<?= url('siswa.impor') ?>">Impor dari CSV</a></td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <div class="massal">
    <span class="kecil muted">Yang dicentang jadi:</span>
    <select name="status" aria-label="Status baru"><?php foreach (STATUS as $k => [$l]): ?><option value="<?= $k ?>"><?= e(ucfirst(strtolower($l))) ?></option><?php endforeach; ?></select>
    <button class="btn kecil">Terapkan</button>
  </div>
</form>
<?php $hals = (int) ceil($total / $per); if ($hals > 1): ?>
  <nav class="halaman"><?php for ($i = 1; $i <= $hals; $i++): ?><a class="<?= $i === $hal ? 'aktif' : '' ?>" href="<?= url('siswa', ['hal' => $i] + array_filter($f)) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
