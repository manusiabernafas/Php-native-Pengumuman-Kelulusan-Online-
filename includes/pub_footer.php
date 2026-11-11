<footer class="pub-kaki">
  <div><b><?= e($_s['nama_sekolah']) ?></b><?= $_s['npsn'] ? ' · NPSN ' . e($_s['npsn']) : '' ?><br><?= e($_s['alamat']) ?></div>
  <div><?= $_s['telepon'] ? e($_s['telepon']) . '<br>' : '' ?><?= e($_s['email']) ?></div>
</footer>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>
