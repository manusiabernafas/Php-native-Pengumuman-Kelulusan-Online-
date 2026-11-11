<?php
$hasil = (string) ($_GET['hasil'] ?? '');
$where = in_array($hasil, ['cocok', 'gagal', 'terkunci'], true) ? 'WHERE l.hasil = ?' : '';
$p = $where ? [$hasil] : [];
$total = (int) q("SELECT COUNT(*) FROM log_akses l $where", $p)->fetchColumn();
$hal = max(1, (int) ($_GET['hal'] ?? 1)); $per = 100;
$rows = q("SELECT l.*, s.nama, s.kelas, s.nisn FROM log_akses l LEFT JOIN siswa s ON s.id = l.siswa_id $where ORDER BY l.id DESC LIMIT $per OFFSET " . (($hal - 1) * $per), $p)->fetchAll();
// IP dengan percobaan gagal terbanyak (kemungkinan menebak NISN)
$curiga = q("SELECT ip, COUNT(*) n, MAX(waktu) akhir FROM log_akses WHERE hasil <> 'cocok' AND waktu >= NOW() - INTERVAL 1 DAY GROUP BY ip HAVING n >= 3 ORDER BY n DESC LIMIT 5")->fetchAll();
$title = 'Log akses';
$sub = $total . ' catatan. Setiap percobaan cek kelulusan tercatat beserta alamat IP.';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($curiga): ?>
  <div class="kartu"><h3>IP dengan banyak percobaan gagal (24 jam)</h3>
    <p class="kecil muted">Setelah 5 kali salah dalam 15 menit, IP otomatis diblokir sementara. Satu sekolah sering berbagi IP Wi-Fi yang sama, jadi wajar bila ada beberapa.</p>
    <table class="tabel"><tbody><?php foreach ($curiga as $c): ?><tr><td class="mono"><?= e($c['ip']) ?></td><td><?= (int) $c['n'] ?> kali</td><td class="kecil muted">terakhir <?= date('H.i', strtotime($c['akhir'])) ?></td></tr><?php endforeach; ?></tbody></table>
  </div>
<?php endif; ?>
<form class="filter kartu" method="get" data-auto>
  <input type="hidden" name="page" value="log">
  <label class="f"><span>Hasil</span><select name="hasil"><option value="">Semua</option><option value="cocok" <?= $hasil === 'cocok' ? 'selected' : '' ?>>Berhasil</option><option value="gagal" <?= $hasil === 'gagal' ? 'selected' : '' ?>>Tidak cocok</option><option value="terkunci" <?= $hasil === 'terkunci' ? 'selected' : '' ?>>Diblokir</option></select></label>
</form>
<section class="kartu rata">
  <div class="tabel-wrap">
  <table class="tabel">
    <thead><tr><th>Waktu</th><th>Siswa</th><th>IP</th><th>Hasil</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $l): ?>
      <tr><td class="nowrap"><?= e(tgl($l['waktu'], false)) ?> <span class="mono"><?= date('H.i.s', strtotime($l['waktu'])) ?></span></td>
          <td><?= $l['nama'] ? '<a href="' . url('siswa.form', ['id' => $l['siswa_id']]) . '">' . e($l['nama']) . '</a> <small class="muted">' . e($l['kelas']) . '</small>' : '<span class="muted">-</span>' ?></td>
          <td class="mono kecil"><?= e($l['ip']) ?></td>
          <td><?= ['cocok' => '<span class="chip hijau">berhasil</span>', 'gagal' => '<span class="chip kuning">tidak cocok</span>', 'terkunci' => '<span class="chip merah">diblokir</span>'][$l['hasil']] ?></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="kosong">Belum ada catatan.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php $hals = (int) ceil($total / $per); if ($hals > 1): ?>
  <nav class="halaman"><?php for ($i = 1; $i <= min($hals, 30); $i++): ?><a class="<?= $i === $hal ? 'aktif' : '' ?>" href="<?= url('log', ['hal' => $i, 'hasil' => $hasil]) ?>"><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
