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

1. SuperAdmin mengisi Nama, Email, Role, dan Jabatan pada popup Buat User.
2. Backend membuat password acak yang tidak ditampilkan. Status user menunggu aktivasi.
3. Admin mendapat tautan sekali pakai untuk atur password. Tautan berlaku 24 jam;
   token disimpan dalam bentuk hash di record user. Tautan baru membatalkan tautan lama.
4. User membuka tautan dan membuat password minimal 8 karakter dengan konfirmasi.
5. Token dibersihkan setelah berhasil, lalu user dapat masuk menggunakan email.

Tidak ada pengiriman email otomatis dalam implementasi ini. Tautan ditampilkan
sesudah pembuatan atau penerbitan ulang dan dapat disalin oleh SuperAdmin.
Tautan yang kedaluwarsa, sudah digunakan, atau milik akun/role nonaktif ditolak.

## API dan export

Endpoint runtime wahana, inbound PIM, katalog read-only, dan alur scanner RFID
tetap memakai kontraknya masing-masing. RBAC tidak mengubah token pairing perangkat.

API pengelolaan berikut sekarang memerlukan sesi login CMS, CSRF, dan izin sesuai
modul; kredensial Bearer PIM/perangkat bukan pengganti sesi user CMS:

- POST/PUT/PATCH/DELETE `/api/zones` dan `/api/zones/{zone}`.
- POST/PUT/PATCH/DELETE `/api/rfid-tags` dan `/api/rfid-tags/{rfid_tag}`.
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

Local: jalankan migrasi hanya dengan DB_DATABASE menunjuk ke file preview.
Jangan jalankan migrasi preview pada path SMB/NAS. Branch belum dipush ke main.
