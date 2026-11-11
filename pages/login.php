<?php
if (user()) redirect('dashboard');
$err = null;
$username = strtolower(trim((string) ($_POST['username'] ?? '')));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kunci = 'login:' . ip();
    if (coba_terkunci($kunci, 5, 600)) {
        $err = 'Terlalu banyak percobaan masuk. Coba lagi 10 menit lagi.';
    } else {
        $u = q('SELECT * FROM pengguna WHERE username = ?', [$username])->fetch();
        if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password'])) {
            coba_gagal($kunci, 600);
            $err = 'Username atau kata sandi salah.';
        } elseif (!$u['aktif']) {
            $err = 'Akun ini dinonaktifkan.';
        } else {
            coba_reset($kunci);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
            redirect('dashboard');
        }
    }
}
$f = flash();
$title = 'Masuk panitia';
require __DIR__ . '/../includes/pub_header.php';
?>
<main class="panggung">
  <div class="konfeti-latar" aria-hidden="true"></div>
  <section class="kartu-cek masuk">
    <div class="bingkai">
      <h2>Masuk Panitia</h2>
      <p class="kecil muted">Khusus admin, wali kelas, dan Tata Usaha.</p>
      <?php if ($f): ?><p class="pesan <?= e($f['type']) ?>"><?= icon($f['type'] === 'error' ? 'awas' : 'centang') ?><?= e($f['msg']) ?></p><?php endif; ?>
      <?php if ($err): ?><p class="pesan error" role="alert"><?= icon('awas') ?><?= e($err) ?></p><?php endif; ?>
      <form method="post" class="form-cek">
        <?= csrf_field() ?>
        <label class="f"><span>Username</span><input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
        <label class="f"><span>Kata sandi</span><input name="password" type="password" autocomplete="current-password" required></label>
        <button class="btn emas besar"><?= icon('kunci') ?>Masuk</button>
      </form>
      <p class="kecil tengah"><a href="<?= url('beranda') ?>">Kembali ke halaman pengumuman</a></p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/../includes/pub_footer.php'; ?>
