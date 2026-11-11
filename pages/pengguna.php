<?php
require_admin();
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $un = strtolower(trim((string) ($_POST['username'] ?? '')));
    $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'operator';
    $aktif = empty($_POST['aktif']) ? 0 : 1;
    $pw = (string) ($_POST['password'] ?? '');
    if ($id === user()['id']) { $role = 'admin'; $aktif = 1; } // tidak bisa menurunkan / menonaktifkan diri sendiri
    if ($nama === '' || !preg_match('/^[a-z0-9._]{3,40}$/', $un)) $err = 'Isi nama dan username (3-40 huruf kecil, angka, titik, garis bawah).';
    elseif (q('SELECT id FROM pengguna WHERE username = ? AND id <> ?', [$un, $id])->fetch()) $err = 'Username sudah dipakai.';
    elseif ((!$id || $pw !== '') && (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw))) $err = 'Kata sandi minimal 8 karakter berisi huruf dan angka.';
    else {
        if ($id) {
            q('UPDATE pengguna SET nama = ?, username = ?, role = ?, aktif = ? WHERE id = ?', [$nama, $un, $role, $aktif, $id]);
            if ($pw !== '') q('UPDATE pengguna SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        } else {
            q('INSERT INTO pengguna (nama, username, password, role, aktif) VALUES (?, ?, ?, ?, ?)', [$nama, $un, password_hash($pw, PASSWORD_DEFAULT), $role, $aktif]);
        }
        flash('Akun ' . $un . ' disimpan.');
        redirect('pengguna');
    }
}
$rows = q('SELECT * FROM pengguna ORDER BY role, nama')->fetchAll();
$title = 'Pengguna';
$sub = 'Admin mengatur semuanya. Operator (wali kelas / TU) hanya mengelola siswa, nilai, dan mapel.';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?><span><?= e($err) ?></span></div><?php endif; ?>
<div class="grid dua lebar-kiri">
  <section class="kartu rata">
    <div class="tabel-wrap">
    <table class="tabel">
      <thead><tr><th>Nama</th><th>Peran</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr><td><b><?= e($u['nama']) ?></b><div class="kecil muted mono"><?= e($u['username']) ?></div></td><td><?= e(LABEL_ROLE[$u['role']]) ?></td>
            <td><?= $u['aktif'] ? '<span class="chip hijau">aktif</span>' : '<span class="chip abu">nonaktif</span>' ?></td>
            <td class="kanan"><a class="ibtn" href="<?= url('pengguna', ['id' => $u['id']]) ?>" aria-label="Ubah <?= e($u['nama']) ?>"><?= icon('pena', 17) ?></a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
  <?php $u = isset($_GET['id']) ? q('SELECT * FROM pengguna WHERE id = ?', [(int) $_GET['id']])->fetch() : null; ?>
  <form method="post" class="kartu">
    <?= csrf_field() ?>
    <h3><?= $u ? 'Ubah ' . e($u['nama']) : 'Tambah pengguna' ?></h3>
    <input type="hidden" name="id" value="<?= (int) ($u['id'] ?? 0) ?>">
    <label class="f"><span>Nama</span><input name="nama" value="<?= e($u['nama'] ?? '') ?>" required maxlength="100"></label>
    <label class="f"><span>Username</span><input name="username" value="<?= e($u['username'] ?? '') ?>" required maxlength="40" autocomplete="off"></label>
    <label class="f"><span>Peran</span><select name="role"><option value="operator">Operator (wali kelas / TU)</option><option value="admin" <?= ($u['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
    <label class="f"><span>Kata sandi<?= $u ? ' baru (kosongkan bila tetap)' : '' ?></span><input type="password" name="password" <?= $u ? '' : 'required' ?> minlength="8" autocomplete="new-password"></label>
    <label class="cek"><input type="checkbox" name="aktif" value="1" <?= !$u || $u['aktif'] ? 'checked' : '' ?>> Akun aktif</label>
    <button class="btn emas"><?= icon('centang') ?>Simpan</button>
    <?php if ($u): ?><a class="btn garis" href="<?= url('pengguna') ?>">Batal</a><?php endif; ?>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
