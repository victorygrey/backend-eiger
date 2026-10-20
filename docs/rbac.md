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
