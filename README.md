# Website Pengumuman Kelulusan Online (PHP Native + MySQL)

Halaman pengumuman kelulusan untuk SMA/SMK/MA/SMP: hitung mundur menuju waktu pengumuman, siswa mengecek hasil dengan NISN dan tanggal lahir, lalu siswa yang lulus bisa mengunduh Surat Keterangan Lulus (SKL) ber-QR. Panitia mengelola data siswa, nilai, impor CSV, dan memantau siapa saja yang sudah membuka pengumuman.

## Isi folder
```
index.php              router semua halaman
config/database.php    koneksi MySQL
includes/              bootstrap (fungsi bantu, keamanan), header & footer
pages/                 halaman siswa (beranda, hasil, skl, verifikasi) dan panitia
assets/                CSS, JS hitung mundur, qrcode.js (MIT, Kazuhiko Arase)
database/kelulusan.sql skema + data contoh 216 siswa
uploads/               logo sekolah (skrip diblokir lewat .htaccess)
```

## Instalasi (XAMPP / Laragon)
1. Salin folder `pengumuman-kelulusan` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin` → **Import** → pilih `database/kelulusan.sql`.
   Waktu pengumuman contoh diatur ke kemarin pukul 16.00, jadi cek hasil bisa langsung dicoba.
4. Sesuaikan `config/database.php` bila user/password MySQL berbeda.
5. Buka `http://localhost/pengumuman-kelulusan`.

Akun panitia (segera ganti sandinya di menu **Profil**):

| Peran | Username | Sandi | Akses |
|---|---|---|---|
| Admin | `admin` | `admin123` | semua menu, pengaturan, pengguna |
| Operator (wali kelas / TU) | `operator` | `operator123` | siswa, nilai, mapel, log |

Contoh siswa untuk mencoba: buka menu **Siswa & nilai**, lihat NISN dan tanggal lahir salah satu siswa, lalu masukkan di halaman depan.

## Persiapan sebelum hari H
1. **Pengaturan**: isi identitas sekolah, logo, kepala sekolah + NIP, format nomor SKL, dan **waktu pengumuman dibuka**.
2. **Mapel**: sesuaikan daftar mata pelajaran (urutan = urutan di SKL dan kolom CSV).
3. **Siswa & nilai → Impor**: unduh templat CSV, isi dari Dapodik / leger nilai, lalu unggah. NISN yang sudah ada diperbarui.
4. Tandai siswa **tidak lulus** / **ditunda** beserta catatannya (bisa massal dengan centang).
5. Pakai **Pratinjau siswa** untuk melihat tampilan hasil sebelum pengumuman dibuka.
6. Setelah uji coba, tekan **Kosongkan log uji coba** dan bila perlu **Susun ulang nomor SKL**.

## Cara kerja singkat
- Sebelum waktu buka, halaman depan menampilkan hitung mundur yang disamakan dengan jam server. Saat waktunya tiba, halaman memuat ulang sendiri dengan jeda acak 0-8 detik agar server tidak dibanjiri.
- Cek hasil hanya diterima server setelah waktu buka (dan sebelum waktu tutup bila diisi).
- Salah isi 5 kali dalam 15 menit memblokir IP tersebut sementara. Penghitungnya disimpan di database, jadi tidak bisa diakali dengan menghapus cookie.
- Pesan salah selalu sama ("Data tidak cocok"), sehingga orang lain tidak bisa menebak NISN mana yang terdaftar.
- SKL hanya bisa dibuka siswa yang bersangkutan setelah lolos cek. QR di SKL mengarah ke halaman verifikasi berisi kode acak 16 karakter, bukan NISN.

## Hosting
Unggah semua file ke hosting, impor SQL lewat phpMyAdmin hosting, sesuaikan `config/database.php`. Bila memakai Nginx, blokir akses ke folder `config`, `includes`, `pages`, `database` (di Apache sudah diatur `.htaccess`). Gunakan HTTPS agar data siswa aman.

Tips hari H: pengumuman biasanya diakses ratusan siswa dalam menit pertama. Hosting bersama (shared) umumnya cukup karena halaman ringan, tetapi hindari mengumumkan bersamaan dengan aplikasi lain di server yang sama.

## Keamanan
PDO prepared statement, CSRF di semua form, password_hash, batas percobaan login & cek hasil per IP (database), honeypot, escape output, header keamanan, unggahan logo diperiksa sebagai gambar asli dengan nama acak, folder internal dan `uploads` tidak bisa menjalankan skrip, ekspor CSV aman dari formula injection.
