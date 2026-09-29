# Aplikasi Pemesanan Toko

PHP + MySQL (PDO), dibuat dari ERD: Pengguna, Toko, Kategori, Produk, Pesanan, dan tabel relasi `detail_pesanan` (relasi "Memesan").

## Menjalankan (XAMPP)
1. Start Apache dan MySQL di XAMPP.
2. Salin folder `toko_app` ke `C:\xampp\htdocs\`.
3. Buka `http://localhost/phpmyadmin` -> tab **Import** -> pilih `database.sql` -> Go.
4. Cek `config.php` (default: user `root`, password kosong).
5. Buka `http://localhost/toko_app/`.

## Akun awal (segera ganti)
- admin / admin123
- kasir / kasir123

## Halaman
| File | Akses | Fungsi |
|---|---|---|
| index.php | publik | Lihat produk per kategori, keranjang, buat pesanan |
| login.php / logout.php | staf | Masuk / keluar |
| pesanan.php | admin, kasir | Proses pesanan: baru -> diproses -> selesai / batal (stok dikembalikan saat batal) |
| produk.php | admin, kasir | CRUD produk + unggah gambar |
| kategori.php | admin | CRUD kategori |
| toko.php | admin | Alamat, kontak, jam buka/tutup, status buka |
| pengguna.php | admin | CRUD pengguna dan hak akses |

## Relasi ERD -> tabel
- Pengguna (Many) memiliki Toko (1): `pengguna.id_toko`
- Pengguna (1) memproses Pesanan (Many): `pesanan.id_pengguna`
- Toko (1) menerima Pesanan (Many): `pesanan.id_toko`
- Toko (1) menjual Produk (Many): `produk.id_toko`
- Kategori (1) mengelompokkan Produk (Many): `produk.id_kategori`
- Pesanan (Many) memesan Produk (Many): `detail_pesanan` (id_detail, jumlah, subtotal)

## Keamanan
Prepared statement, token CSRF, password di-hash, session HttpOnly, batas percobaan login, validasi tipe gambar, harga dan stok selalu dihitung dari database saat checkout (dengan penguncian baris).

## Tampilan & branding
- Ganti nama brand di `config.php` pada baris `const APP_NAME = 'PesanToko';`.
- Warna, font, dan ukuran diatur lewat variabel di bagian atas `assets/app.css` (`--brand`, `--sun`, dll.), jadi satu tempat untuk menyesuaikan tema pelanggan.
- Font Plus Jakarta Sans dimuat dari Google Fonts. Jika tanpa internet, tampilan otomatis memakai font sistem.
- Halaman pelanggan: pencarian, filter kategori, keranjang tanpa reload, tombol WhatsApp ke toko, dan konfirmasi pesanan lewat WhatsApp.
- Halaman staf: sidebar, papan pesanan (baru / diproses), dan muat ulang otomatis tiap 60 detik.
- Responsif untuk ponsel: keranjang menjadi panel geser dari bawah.
