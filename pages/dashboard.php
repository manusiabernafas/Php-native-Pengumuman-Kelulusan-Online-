<?php
$jml = q("SELECT COUNT(*) total, SUM(status='lulus') lulus, SUM(status='tidak_lulus') tidak, SUM(status='ditunda') tunda,
          SUM(dilihat_at IS NOT NULL) lihat FROM siswa")->fetch();
$gagal = q("SELECT SUM(hasil='gagal') g, SUM(hasil='terkunci') k FROM log_akses WHERE waktu >= NOW() - INTERVAL 1 DAY")->fetch();
$buka = setelan('waktu_buka');
// Akses per jam sejak pengumuman dibuka (12 jam pertama)
$perJam = q("SELECT FLOOR(TIMESTAMPDIFF(MINUTE, ?, waktu) / 60) j, COUNT(*) n FROM log_akses
             WHERE hasil = 'cocok' AND waktu >= ? AND waktu < ? + INTERVAL 12 HOUR GROUP BY j", [$buka, $buka, $buka])->fetchAll(PDO::FETCH_KEY_PAIR);
$maks = max([1] + $perJam);
$kelas = q("SELECT kelas, COUNT(*) n, SUM(dilihat_at IS NOT NULL) l, SUM(status='lulus') lulus FROM siswa GROUP BY kelas ORDER BY kelas")->fetchAll();
$log = q("SELECT l.*, s.nama, s.kelas FROM log_akses l LEFT JOIN siswa s ON s.id = l.siswa_id ORDER BY l.id DESC LIMIT 8")->fetchAll();
$pct = $jml['total'] ? round($jml['lihat'] / $jml['total'] * 100) : 0;

$title = 'Ringkasan';
$sub = 'Pengumuman dibuka ' . e(hari_id($buka)) . ', ' . e(tgl_jam($buka));
$actions = '<a class="btn" href="' . url('siswa.impor') . '">' . icon('unggah') . 'Impor siswa</a>'
         . (is_admin() ? '<a class="btn emas" href="' . url('pengaturan') . '">' . icon('jam') . 'Atur waktu</a>' : '');
require __DIR__ . '/../includes/header.php';
?>
<div class="statistik">
  <div class="stat"><span>Peserta</span><b><?= (int) $jml['total'] ?></b><small>siswa kelas XII</small></div>
  <div class="stat hijau"><span>Lulus</span><b><?= (int) $jml['lulus'] ?></b><small><?= $jml['total'] ? angka($jml['lulus'] / $jml['total'] * 100, 1) : 0 ?>% dari peserta</small></div>
  <div class="stat merah"><span>Tidak lulus / ditunda</span><b><?= (int) $jml['tidak'] ?> / <?= (int) $jml['tunda'] ?></b><small><a href="<?= url('siswa', ['status' => 'tidak_lulus']) ?>">lihat daftar</a></small></div>
  <div class="stat"><span>Sudah membuka</span><b><?= $pct ?>%</b><small><?= (int) $jml['lihat'] ?> dari <?= (int) $jml['total'] ?> siswa</small></div>
</div>

<div class="grid dua">
  <section class="kartu">
    <div class="kartu-kepala"><h3>Akses 12 jam pertama</h3><span class="kecil muted"><?= (int) $gagal['g'] ?> salah isi · <?= (int) $gagal['k'] ?> diblokir (24 jam)</span></div>
    <div class="batang" role="img" aria-label="Grafik jumlah siswa membuka pengumuman per jam">
      <?php for ($j = 0; $j < 12; $j++): $n = (int) ($perJam[$j] ?? 0); ?>
        <div><b style="height:<?= max(2, round($n / $maks * 100)) ?>%"><em><?= $n ?: '' ?></em></b><span><?= date('H', strtotime($buka) + $j * 3600) ?></span></div>
      <?php endfor; ?>
    </div>
  </section>
  <section class="kartu">
    <div class="kartu-kepala"><h3>Per kelas</h3><a class="kecil" href="<?= url('siswa') ?>">Semua siswa</a></div>
    <table class="tabel">
      <thead><tr><th>Kelas</th><th class="kanan">Lulus</th><th>Sudah membuka</th></tr></thead>
      <tbody>
      <?php foreach ($kelas as $k): $p = $k['n'] ? round($k['l'] / $k['n'] * 100) : 0; ?>
        <tr>
          <td><a href="<?= url('siswa', ['kelas' => $k['kelas']]) ?>"><b><?= e($k['kelas']) ?></b></a></td>
          <td class="kanan"><?= (int) $k['lulus'] ?>/<?= (int) $k['n'] ?></td>
          <td><div class="progres"><i style="width:<?= $p ?>%"></i></div><small class="muted"><?= (int) $k['l'] ?> siswa · <?= $p ?>%</small></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<section class="kartu">
  <div class="kartu-kepala"><h3>Akses terbaru</h3><a class="kecil" href="<?= url('log') ?>">Semua log</a></div>
  <table class="tabel">
    <thead><tr><th>Waktu</th><th>Siswa</th><th>IP</th><th>Hasil</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l): ?>
      <tr><td class="nowrap"><?= e(tgl($l['waktu'], false)) ?> <?= date('H.i', strtotime($l['waktu'])) ?></td><td><?= $l['nama'] ? e($l['nama']) . ' <small class="muted">' . e($l['kelas']) . '</small>' : '<span class="muted">-</span>' ?></td><td class="mono kecil"><?= e($l['ip']) ?></td>
          <td><?= ['cocok' => '<span class="chip hijau">berhasil</span>', 'gagal' => '<span class="chip kuning">tidak cocok</span>', 'terkunci' => '<span class="chip merah">diblokir</span>'][$l['hasil']] ?></td></tr>
    <?php endforeach; if (!$log): ?><tr><td colspan="4" class="kosong">Belum ada siswa yang membuka pengumuman.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
