# RBAC dan pembuatan user CMS

Perubahan dikembangkan di `codex/rbac-role-registration`. Preview menggunakan
SQLite `storage/app/rbac-preview.sqlite`; database NAS tidak dipakai untuk pengujian lokal.

## Role

- Role memiliki slug permanen, nama, status, dan daftar izin `modul.tindakan`.
- Matriks memakai modul yang benar-benar tersedia pada CMS. Pilihan tindakan yang
  tidak tersedia ditandai dengan `-`.
- Edit, hapus, dan export menyertakan izin lihat. Izin diperiksa pada server di
  setiap request, termasuk akses URL langsung dan request yang dimodifikasi.
- Sidebar serta kontrol perubahan menyesuaikan izin role. Pencabutan izin dan
  penonaktifan role berlaku pada request berikutnya.
- User dan Role serta pembuatan token inbound PIM tetap khusus SuperAdmin.
- Role SuperAdmin dilindungi; Store Admin mempertahankan akses operasional lama.
- Nama role contoh dari referensi dibuat tanpa izin. SuperAdmin perlu menentukan
  cakupan aksesnya sebelum role dipakai untuk pekerjaan operasional.
- Role sistem tidak dapat dihapus. Role lain hanya dapat dihapus jika tidak dipakai user.

## Pembuatan user

1. SuperAdmin mengisi Nama, Email, Role, Jabatan, Password, dan Konfirmasi Password.
2. Password wajib 8–72 karakter, konfirmasi harus cocok, dan backend menyimpan hash.
3. User dengan akun dan role aktif dapat langsung login menggunakan password tersebut.
4. Saat mengubah user, SuperAdmin dapat mengisi password baru beserta konfirmasinya.
   Kosongkan kedua kolom untuk mempertahankan password lama.

Tidak ada tautan setup password atau pengiriman email dalam alur ini. Email tetap
menjadi identitas login. Endpoint setup publik dan penerbitan undangan telah dihapus.
User pending dari preview lama tetap diblokir sampai SuperAdmin menetapkan password
melalui Ubah User. Penggantian password menghapus token undangan lama dan merotasi
remember token. Password tidak ditampilkan ulang atau diisi kembali setelah validasi gagal.
Kolom undangan pada migrasi lama dipertahankan untuk kompatibilitas database preview.

## API dan export

Endpoint runtime wahana, inbound PIM, katalog read-only, dan alur scanner RFID
tetap memakai kontraknya masing-masing. RBAC tidak mengubah token pairing perangkat.

API pengelolaan berikut sekarang memerlukan sesi login CMS, CSRF, dan izin sesuai
modul; kredensial Bearer PIM/perangkat bukan pengganti sesi user CMS:

- POST/PUT/PATCH/DELETE `/api/zones` dan `/api/zones/{zone}`.
- POST/PUT/PATCH/DELETE `/api/rfid-tags` dan `/api/rfid-tags/{rfid_tag}`.
- POST `/api/rfid-tags/batch`: sesi CMS, CSRF, dan `rfid-tags.edit` (pendaftaran massal
  menulis database sehingga tidak boleh dipanggil anonim). Endpoint resolve tetap read-only.
- POST `/api/sync/care`: `integrations.edit`.
- PUT `/api/print-rules/{printRule}`: SuperAdmin.

Export CSV tersedia pada `/admin/{module}/export/csv` untuk products, rfid-tags,
print-history, dan sync-logs. Export memerlukan izin export tersendiri, tidak
menyertakan kredensial, dan mengamankan nilai yang dapat menjadi formula spreadsheet.

## Migrasi dan deployment

Migrasi membuat role, menghubungkan nilai role lama melalui slug, dan menambah
jabatan serta data undangan. Password akun lama tidak diubah. UserSeeder memakai
firstOrCreate supaya startup container tidak menimpa role, status, atau password
akun yang telah dikelola admin.

## Pengamanan login dan sesi

- Login dibatasi lima kegagalan per akun dalam 60 detik, termasuk percobaan lewat
  alias nama/prefix email yang berbeda. Ada batas tambahan 30 kegagalan per IP.
  Respons pembatasan adalah 429 dengan header Retry-After.
- Email lengkap dicocokkan persis tanpa membedakan huruf besar/kecil. Alias nama
  atau prefix email lama tetap didukung jika hanya menunjuk satu user; wildcard
  SQL tidak diperluas. Validasi email profil juga menolak duplikasi beda kapitalisasi.
- Hash password diikat ke sesi saat login. Middleware auth.session memeriksa
  perubahan hash pada halaman admin dan API pengelolaan, sehingga sesi dengan
  password lama ditolak setelah reset. Perubahan password profil merotasi remember token.
- Password baru pada validasi profil tidak ikut disimpan sebagai old input;
  ketentuan minimal 8 karakter dan batas bcrypt 72 byte berlaku konsisten.
- Petunjuk akun demo dan pembuatan akun default hanya berjalan di local/testing.
  Seeder produksi tidak membuat akun dengan password bawaan; akun yang sudah ada
  tetap dipertahankan. Password demo yang pernah dibuat pada server harus diganti
  oleh pengelola sebelum rilis. Fresh install produksi memerlukan provisioning
  SuperAdmin secara terpisah melalui prosedur operasional.

Pengujian audit ada di `tests/Feature/Admin/RbacSecurityAuditTest.php`, mencakup
akses tulis RFID anonim/read-only, bypass alias login, rate limiting, sesi setelah
reset, kebocoran old input, duplikasi email, input tidak valid, serta CSRF aktif.
Audit ini dilakukan pada source dan database pengujian lokal. Konfigurasi NAS,
Cloudflare, kredensial akun staging, serta seluruh permukaan API runtime tidak
termasuk verifikasi deployment ini.

## Dependensi keamanan

Audit `composer audit --locked` pada baseline Laravel 11.55.0 menemukan advisori
pada Laravel, CommonMark, Flysystem, Symfony HttpFoundation, dan Symfony YAML.
Branch lokal memperbarui framework ke Laravel 12.70.0 beserta patch dependensi
terkait dan PHPUnit 11. Composer menargetkan PHP 8.2.0 melalui config.platform,
sesuai versi dasar container, agar resolver lokal PHP 8.4 tidak memilih paket
yang memerlukan versi PHP di atas runtime deployment.

Rujukan upgrade: https://laravel.com/framework/docs/12.x/upgrade
Advisori validasi email: https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq

Perubahan dependency hanya disiapkan pada branch lokal ini. Tidak ada migrasi
tambahan atau perubahan password pada database NAS yang dilakukan oleh audit.

Local: jalankan migrasi hanya dengan DB_DATABASE menunjuk ke file preview.
Jangan jalankan migrasi preview pada path SMB/NAS. Branch belum dipush ke main.
