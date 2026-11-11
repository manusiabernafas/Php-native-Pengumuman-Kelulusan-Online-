<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_name('kelulusan');
session_start();
date_default_timezone_set('Asia/Jakarta');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec("SET time_zone = '+07:00'"); // NOW() di MySQL ikut jam WIB
        } catch (PDOException $e) {
            die('<h3>Koneksi database gagal.</h3><p>Cek <b>config/database.php</b> dan pastikan database <b>'
                . htmlspecialchars($c['database']) . '</b> sudah di-import dari folder <b>database/</b>.</p>');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

const BULAN_ID = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function tgl($date, bool $panjang = true): string
{
    if (!$date) return '-';
    $t = strtotime($date);
    $b = BULAN_ID[(int) date('n', $t)];
    return date('j', $t) . ' ' . ($panjang ? $b : substr($b, 0, 3)) . ' ' . date('Y', $t);
}

function url(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        die('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** Baca CSV unggahan (pemisah ; atau , dideteksi otomatis, BOM dibuang). */
function baca_csv(string $path, int $maksBaris = 2000): array
{
    $isi = file_get_contents($path, false, null, 0, 2_000_000);
    if ($isi === false) return [];
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
    $baris1 = strtok($isi, "\n");
    $pisah = substr_count((string) $baris1, ';') >= substr_count((string) $baris1, ',') ? ';' : ',';
    $out = [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $isi);
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $pisah, '"', '')) !== false && count($out) < $maksBaris) {
        if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) continue;
        $out[] = array_map(fn($x) => trim((string) $x), $r);
    }
    fclose($fh);
    return $out;
}

function kirim_csv(string $nama, array $baris): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nama) . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($baris as $r) {
        // Sel diawali = + - @ diberi kutip satu agar tidak dijalankan Excel sebagai rumus
        fputcsv($o, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $r), ';', '"', '');
    }
    fclose($o);
    exit;
}

// ---------------------------------------------------------------- akun & peran

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        flash('Silakan masuk terlebih dahulu.', 'error');
        redirect('login');
    }
    // Baca ulang dari database: perubahan peran / penonaktifan langsung berlaku
    $u = q('SELECT id, nama, username, role, aktif FROM pengguna WHERE id = ?', [user()['id']])->fetch();
    if (!$u || !$u['aktif']) {
        $_SESSION = [];
        flash('Akun Anda tidak aktif. Hubungi admin sekolah.', 'error');
        redirect('login');
    }
    $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
}

function is_admin(): bool
{
    return (user()['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('Menu itu hanya untuk admin.', 'error');
        redirect('dashboard');
    }
}

const LABEL_ROLE = ['admin' => 'Admin', 'operator' => 'Operator (wali kelas / TU)'];

/**
 * Batas percobaan berbasis IP yang disimpan di database (bukan sesi),
 * sehingga tidak bisa diakali dengan menghapus cookie.
 */
function coba_terkunci(string $kunci, int $maks, int $detik): bool
{
    $r = q('SELECT n, mulai FROM batas_coba WHERE kunci = ?', [$kunci])->fetch();
    if (!$r) return false;
    if (strtotime($r['mulai']) < time() - $detik) {
        q('DELETE FROM batas_coba WHERE kunci = ?', [$kunci]);
        return false;
    }
    return (int) $r['n'] >= $maks;
}

function coba_gagal(string $kunci, int $detik): void
{
    q('INSERT INTO batas_coba (kunci, n, mulai) VALUES (?, 1, NOW())
       ON DUPLICATE KEY UPDATE n = IF(mulai < NOW() - INTERVAL ? SECOND, 1, n + 1),
                               mulai = IF(mulai < NOW() - INTERVAL ? SECOND, NOW(), mulai)', [$kunci, $detik, $detik]);
}

function coba_reset(string $kunci): void
{
    q('DELETE FROM batas_coba WHERE kunci = ?', [$kunci]);
}

function ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// ---------------------------------------------------------------- pengaturan & data

const PENGATURAN_AWAL = [
    'nama_sekolah' => 'SMA Negeri 1 Contoh', 'npsn' => '', 'alamat' => '', 'kota' => '', 'telepon' => '', 'email' => '', 'website' => '',
    'kepala_sekolah' => '', 'nip_kepala' => '', 'tahun_ajaran' => '2025/2026', 'jenjang' => 'SMA',
    'waktu_buka' => '2026-05-05 16:00:00', 'waktu_tutup' => '', 'tanggal_skl' => '2026-05-05',
    'format_nomor' => '421.3/{no}/SMA/{tahun}', 'tampil_nilai' => '1', 'unduh_skl' => '1',
    'pesan_lulus' => '', 'pesan_tidak_lulus' => '', 'pesan_ditunda' => '', 'logo' => '', 'kontak_wa' => '',
];

function setelan(?string $kunci = null)
{
    static $s = null;
    if ($s === null) {
        $s = PENGATURAN_AWAL;
        foreach (q('SELECT kunci, nilai FROM pengaturan')->fetchAll() as $r) $s[$r['kunci']] = (string) $r['nilai'];
    }
    return $kunci === null ? $s : ($s[$kunci] ?? '');
}

function simpan_setelan(array $data): void
{
    $st = db()->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
    foreach ($data as $k => $v) {
        if (array_key_exists($k, PENGATURAN_AWAL)) $st->execute([$k, (string) $v]);
    }
}

/** Status pengumuman: 'belum' (hitung mundur), 'buka', atau 'tutup'. */
function status_pengumuman(?int $sekarang = null): string
{
    $now = $sekarang ?? time();
    $buka = strtotime(setelan('waktu_buka'));
    $tutup = setelan('waktu_tutup') !== '' ? strtotime(setelan('waktu_tutup')) : null;
    if ($buka && $now < $buka) return 'belum';
    if ($tutup && $now >= $tutup) return 'tutup';
    return 'buka';
}

const STATUS = [
    'lulus' => ['LULUS', 'hijau'],
    'tidak_lulus' => ['TIDAK LULUS', 'merah'],
    'ditunda' => ['DITUNDA', 'kuning'],
];

function chip(string $status): string
{
    [$l, $w] = STATUS[$status] ?? [$status, 'abu'];
    return '<span class="chip ' . $w . '">' . e($l) . '</span>';
}

function tgl_jam($dt): string
{
    if (!$dt) return '-';
    return tgl($dt) . ' pukul ' . date('H.i', strtotime($dt)) . ' WIB';
}

/** NISN disamarkan untuk halaman publik: 0012345678 -> 00******78 */
function samar(string $nisn): string
{
    $n = strlen($nisn);
    return $n <= 4 ? str_repeat('*', $n) : substr($nisn, 0, 2) . str_repeat('*', $n - 4) . substr($nisn, -2);
}

/** Nomor SKL dari format pengaturan, mis. 421.3/{no}/SMA/{tahun} -> 421.3/007/SMA/2026 */
function nomor_skl(int $urut): string
{
    $th = date('Y', strtotime(setelan('tanggal_skl') ?: 'now'));
    return strtr(setelan('format_nomor'), ['{no}' => sprintf('%03d', $urut), '{tahun}' => $th]);
}

/** Token verifikasi acak untuk QR di SKL (tidak bisa ditebak dari NISN). */
function token_baru(): string
{
    return substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 16);
}

function url_dasar(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

function mapel_semua(): array
{
    static $m = null;
    return $m ??= q('SELECT * FROM mapel ORDER BY urutan, id')->fetchAll();
}

function nilai_siswa(int $id): array
{
    return q('SELECT n.mapel_id, n.nilai FROM nilai n WHERE n.siswa_id = ?', [$id])->fetchAll(PDO::FETCH_KEY_PAIR);
}

function rata(array $nilai): ?float
{
    $n = array_filter($nilai, fn($v) => $v !== null && $v !== '');
    return $n ? round(array_sum($n) / count($n), 2) : null;
}

function angka($v, int $des = 2): string
{
    return $v === null || $v === '' ? '-' : number_format((float) $v, $des, ',', '.');
}

/** Siswa lulus yang belum punya nomor SKL diberi nomor berikutnya; status lain nomornya dilepas. */
function beri_nomor(int $id): void
{
    q("UPDATE siswa SET no_urut = NULL WHERE id = ? AND status <> 'lulus'", [$id]);
    // Dipanggil di dalam transaksi: FOR UPDATE mencegah dua admin mendapat nomor yang sama
    $n = (int) q('SELECT COALESCE(MAX(no_urut), 0) + 1 FROM siswa FOR UPDATE')->fetchColumn();
    q("UPDATE siswa SET no_urut = ? WHERE id = ? AND status = 'lulus' AND no_urut IS NULL", [$n, $id]);
}

function hari_id($date): string
{
    return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int) date('w', strtotime($date))];
}


// ---------------------------------------------------------------- ikon

function icon(string $name, int $size = 18): string
{
    // Ikon garis gaya Lucide, disematkan agar tetap jalan tanpa internet
    static $p = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'pena' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'grid' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
        'buku' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
        'kelas' => '<path d="M2 20h20M5 20V9l7-5 7 5v11"/><path d="M10 20v-5h4v5"/>',
        'cetak' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'unduh' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'unggah' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'setel' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M5 12h14M12 5v14"/>',
        'centang' => '<path d="M20 6 9 17l-5-5"/>',
        'silang' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'cari' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'kunci' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'buka' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'kiri' => '<path d="m12 19-7-7 7-7M19 12H5"/>',
        'kanan' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'grafik' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'kalender' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'jam' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'kirim' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'bintang' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'awas' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sampah' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'mata' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'buku2' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'pindai' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 8v8M11 8v8M14 8v8M17 8v8"/>',
        'pinjam' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'kembali2' => '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
        'kartu' => '<rect width="20" height="14" x="2" y="5" rx="2"/><circle cx="8" cy="12" r="2"/><path d="M14 10h4M14 14h4"/>',
        'koin' => '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.5-1-1.5-1.5-2.5-1.5-1.4 0-2.5.9-2.5 2s1.1 1.7 2.5 2 2.5.9 2.5 2-1.1 2-2.5 2c-1 0-2-.5-2.5-1.5M12 6.5v1.5M12 16v1.5"/>',
        'pintu' => '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.56v16.88a.5.5 0 0 1-.6.49l-6-1.2a.5.5 0 0 1-.4-.49V5.76a.5.5 0 0 1 .4-.49l6-1.2a.5.5 0 0 1 .6.49Z"/>',
        'label' => '<path d="M12.6 2.6a2 2 0 0 0-1.4-.6H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'toga' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/><path d="M22 10v6"/>',
        'perisai' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'salin' => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['info']) . '</svg>';
}
