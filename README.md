# WARUNG BAGAS - Sistem Penjualan Kebutuhan Harian

WARUNG BAGAS adalah aplikasi web penjualan kebutuhan harian untuk pelanggan di sekitar
warung. Aplikasi ini berfokus pada katalog sembako, makanan, minuman, kebutuhan rumah,
aksesoris, dan elektronik ringan, dengan proses pemesanan online yang sederhana.

## Cara menjalankan
1. Salin folder project ini ke `C:\xampp\htdocs\warung-bagas`.
2. Jalankan Apache dan MySQL dari XAMPP.
3. Buka `http://localhost/phpmyadmin`.
4. Import file `database.sql`.
5. Buka `http://localhost/warung-bagas/`.

Akun demo:
- Username: `admin`
- Password: `password` (tepat 8 karakter)

Akun pelanggan demo:
- Username: `pelanggan`
- Password: `password123`

## Peran pengguna

### Pelanggan

Pelanggan dapat mencari produk kebutuhan harian, memfilter berdasarkan kategori, mengatur
isi keranjang, melakukan checkout dengan alamat pengiriman, dan memantau status pesanan.

### Admin WARUNG BAGAS

Login dengan akun `admin` untuk melihat total seluruh pembelian, omzet, jumlah pelanggan, jumlah produk, dan daftar 10 pesanan terbaru.

Status pesanan hanya dapat diubah dari dashboard admin:
- `Menunggu Pembayaran`
- `Diproses`
- `Dikemas`
- `Dikirim`
- `Selesai`
- `Dibatalkan`

Keamanan aplikasi mencakup prepared statement, pembatasan akses berbasis role,
CSRF token pada seluruh formulir POST, cookie sesi HttpOnly/SameSite, dan validasi
input server-side.

Akun pelanggan hanya dapat melihat status, tanpa akses untuk mengubahnya.

## Fitur tambahan
- Registrasi akun baru dengan username dan email unik.
- Password registrasi minimal 8 karakter dengan konfirmasi password.
- Profil untuk menyimpan nama, nomor telepon, dan alamat default.
- Pencarian produk berdasarkan nama atau kategori.
- Katalog 40 produk warung dengan gambar, kategori, deskripsi, harga, dan stok.
- Kategori warung meliputi Sembako, Minuman, Makanan, Kebutuhan Rumah, Aksesoris, dan Elektronik.
- Katalog ditampilkan sebagai etalase terpisah per kategori dan dapat difilter melalui chip kategori.
- Keranjang dengan ubah jumlah dan validasi stok.
- Halaman keranjang terpisah di `cart.php` dengan checkout dan ringkasan pembayaran.
- Riwayat pesanan dan status pesanan.
- Nota pembelian detail yang dapat dibuka dari riwayat pesanan dan dicetak melalui browser.
- Dashboard admin modern dengan ringkasan omzet dan pesanan terbaru.
- CRUD produk admin untuk menambah, mengedit, dan menghapus katalog.
- Admin dapat menghapus transaksi secara permanen setelah konfirmasi; item transaksi juga ikut dihapus.
- Status pesanan bertahap dari pembayaran hingga selesai.

## Struktur halaman

- `login.php` dan `register.php`: pintu masuk autentikasi pengguna.
- `products.php`: katalog produk WARUNG BAGAS.
- `product.php`: detail produk dan penambahan jumlah ke keranjang.
- `cart.php` dan `checkout.php`: pengelolaan keranjang serta checkout.
- `orders.php`: riwayat pesanan pelanggan.
- `receipt.php`: nota pembelian berdasarkan nomor pesanan, dengan kontrol akses pelanggan/admin dan mode cetak.
- `nota.php`: URL khusus nota pembelian yang dapat dibuka dengan format `nota.php?id=ID_PESANAN`.
- `profile.php`: data profil dan alamat default pelanggan.
- `admin.php`: akses dashboard administrator.
- `logout.php`: keluar dan membersihkan sesi.
- `bootstrap.php`: helper autentikasi dan keamanan output.

## Skenario pengujian
- Login valid: gunakan akun demo.
- Password salah: masukkan password selain `password`.
- Password 8 karakter: diterima oleh validasi panjang minimum.
- Password 7 karakter: ditolak.
- Checkout berhasil: login, pilih produk, buka keranjang, isi semua alamat.
- Checkout keranjang kosong: buka checkout tanpa menambah produk, lalu sistem menolak.
