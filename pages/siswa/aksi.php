<?php
$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? '');

if ($aksi === 'ekspor') {
    $mapel = mapel_semua();
    $rows = q('SELECT * FROM siswa ORDER BY kelas, nama')->fetchAll();
    $nilai = [];
    foreach (q('SELECT siswa_id, mapel_id, nilai FROM nilai')->fetchAll() as $n) $nilai[$n['siswa_id']][$n['mapel_id']] = $n['nilai'];
    $out = [array_merge(['nisn', 'nis', 'nama', 'jk', 'kelas', 'tempat_lahir', 'tgl_lahir', 'nama_ortu', 'status', 'catatan', 'nomor_skl', 'dibuka'], array_column($mapel, 'nama'), ['rata_rata'])];
    foreach ($rows as $r) {
        $nv = $nilai[$r['id']] ?? [];
        $out[] = array_merge([$r['nisn'], $r['nis'], $r['nama'], $r['jk'], $r['kelas'], $r['tempat_lahir'], $r['tgl_lahir'], $r['nama_ortu'], $r['status'], $r['catatan'],
            $r['no_urut'] ? nomor_skl((int) $r['no_urut']) : '', $r['dilihat_at'] ?? ''], array_map(fn($m) => $nv[$m['id']] ?? '', $mapel), [rata($nv) ?? '']);
    }
    kirim_csv('kelulusan-' . date('Ymd-Hi') . '.csv', $out);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('siswa');

if ($aksi === 'status_massal') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['id'] ?? [])))));
    $st = (string) ($_POST['status'] ?? '');
    if (!$ids || !isset(STATUS[$st])) {
        flash('Centang minimal satu siswa dan pilih statusnya.', 'error');
        redirect('siswa');
    }
    db()->beginTransaction();
    $in = implode(',', array_fill(0, count($ids), '?'));
    q("UPDATE siswa SET status = ? WHERE id IN ($in)", [$st, ...$ids]);
    foreach ($ids as $i) beri_nomor($i);
    db()->commit();
    flash(count($ids) . ' siswa diubah menjadi ' . strtolower(STATUS[$st][0]) . '.');
    redirect('siswa');
}

if ($aksi === 'hapus') {
    require_admin();
    $s = q('SELECT nama FROM siswa WHERE id = ?', [(int) ($_POST['id'] ?? 0)])->fetch();
    if ($s) {
        q('DELETE FROM siswa WHERE id = ?', [(int) $_POST['id']]);
        flash($s['nama'] . ' dihapus.');
    }
    redirect('siswa');
}

if ($aksi === 'nomori') {
    // Susun ulang nomor SKL 1..n berurutan kelas lalu nama
    require_admin();
    db()->beginTransaction();
    q('UPDATE siswa SET no_urut = NULL');
    q('SET @n := 0');
    q("UPDATE siswa SET no_urut = (@n := @n + 1) WHERE status = 'lulus' ORDER BY kelas, nama");
    db()->commit();
    flash('Nomor SKL disusun ulang berurutan per kelas dan nama.');
    redirect('pengaturan');
}

redirect('siswa');
