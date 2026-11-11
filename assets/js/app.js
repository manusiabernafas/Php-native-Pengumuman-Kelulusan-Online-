// Hitung mundur pengumuman. Jam perangkat siswa sering tidak tepat, jadi selisihnya
// dengan jam server dihitung sekali lalu dipakai terus.
(function () {
    var el = document.querySelector('[data-hitung]');
    if (!el) return;
    var buka = +el.dataset.hitung, selisih = +el.dataset.server - Date.now(), selesai = false;
    function dua(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
        var sisa = Math.max(0, buka - (Date.now() + selisih)), d = Math.floor(sisa / 1000);
        el.querySelector('[data-d]').textContent = dua(Math.floor(d / 86400));
        el.querySelector('[data-j]').textContent = dua(Math.floor(d % 86400 / 3600));
        el.querySelector('[data-m]').textContent = dua(Math.floor(d % 3600 / 60));
        el.querySelector('[data-s]').textContent = dua(d % 60);
        if (sisa <= 0 && !selesai) {
            selesai = true;
            // Ratusan siswa menunggu di detik yang sama: muat ulang dengan jeda acak 0-8 detik
            // supaya server tidak dibanjiri permintaan serentak.
            el.insertAdjacentHTML('afterend', '<p class="hitung-ket">Pengumuman dibuka. Halaman sedang dimuat ulang...</p>');
            setTimeout(function () { location.reload(); }, Math.random() * 8000);
        }
    }
    tick();
    setInterval(tick, 1000);
    // Setelah HP terkunci / tab tertidur, samakan lagi dengan jam server
    document.addEventListener('visibilitychange', function () {
        if (document.hidden || selesai) return;
        fetch('index.php?page=waktu', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
            selisih = j.server - Date.now(); buka = j.buka;
        }).catch(function () {});
    });
})();
// Konfirmasi aksi penting
document.querySelectorAll('form[data-yakin]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.yakin)) e.preventDefault(); });
});
// Filter berubah = langsung tampilkan
document.querySelectorAll('form[data-auto] select').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
});
// Centang semua
document.querySelectorAll('[data-semua]').forEach(function (c) {
    c.addEventListener('change', function () {
        c.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(function (x) { x.checked = c.checked; });
    });
});
// Cegah klik dobel pada tombol simpan (kecuali bila pengiriman dibatalkan konfirmasi)
document.querySelectorAll('form[method=post]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
        setTimeout(function () {
            if (ev.defaultPrevented) return;
            f.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
        }, 0);
    });
});
