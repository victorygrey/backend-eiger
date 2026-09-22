# CMS EIGER di TrueNAS

Target: `http://192.168.18.31:8000`, aplikasi TrueNAS `eiger-backend`, image awal `eiger-backend:20260911-1`.

## Penyimpanan

- Dataset: `/mnt/Tank/eiger-backend`.
- Source release awal: `/mnt/Tank/eiger-backend/releases/20260911-1`.
- Database: `/mnt/Tank/eiger-backend/data/database.sqlite`, dipasang ke `/data/database.sqlite`.
- Storage Laravel: `/mnt/Tank/eiger-backend/storage`.
- Media permanen PIM: `/mnt/Tank/nas-shared/eiger-media`, dipasang ke `/var/www/html/storage/app/eiger-media`.
- Struktur media dibuat otomatis: `products/photos/{nama--sku}`, `products/videos/{nama--sku}`, dan `led-ambience/videos/{nama--sku}`.
- Konfigurasi: `/mnt/Tank/eiger-backend/config/runtime.env`, permission 0600, hanya root. APP_KEY dipertahankan dari CMS lokal.
- Backup SQLite otomatis sebelum migrasi: `/mnt/Tank/eiger-backend/data/backups`.

Folder migrasi tidak dipasang sebagai volume. Migrasi mengikuti versi image; data tetap berada di dataset. Tidak ada seed/reset data saat startup. Konfigurasi database menggunakan DB_DATABASE, bukan DB_FILE.

Path media diatur langsung oleh `compose.truenas.yaml`. Setelah volume baru pertama kali dipasang, jalankan perapihan idempotent berikut dari container aplikasi:

```bash
php artisan media:organize --download-remote --remove-legacy
```

Perintah tersebut menyalin media datar lama ke folder produk, memperbarui referensi pada produk, varian, teknologi, dan tabel media, lalu menghapus file lama hanya bila tidak ada referensi database yang tersisa. Media remote yang sudah kedaluwarsa dilaporkan dan dibiarkan tetap tercatat agar dapat dikirim ulang oleh PIM.

## Update dari GitHub

Image CMS dibangun oleh GitHub Actions dari branch `main` dan Watchtower memperbarui aplikasi otomatis. Jangan commit `.env.docker`, database, media, atau runtime secrets. Repository: `https://github.com/victorygrey/backend-eiger.git`.

Siapkan checkout repository di direktori source terpisah di NAS. Gunakan mekanisme autentikasi GitHub yang sudah diizinkan jika repository privat. Jalankan `sudo bash deploy.sh` pada checkout tersebut. Script meminta working tree bersih, melakukan pull fast-forward, build image bertag commit, lalu memperbarui aplikasi yang sudah ada melalui API TrueNAS. Script mempertahankan konfigurasi live, termasuk env_file dan mount. Ia tidak menjalankan `docker compose up` terpisah dari Apps.

Periksa status job Apps dan `/up` setelah update. Untuk rollback kode, pilih image sebelumnya dengan konfigurasi dan volume yang sama. Rollback image tidak otomatis membatalkan migrasi; pulihkan backup database hanya bila diperlukan setelah menilai data baru yang masuk.

## Layanan

Nginx dan PHP-FPM melayani CMS. Queue worker aktif. Scheduler tersedia tetapi awalnya nonaktif (`RUN_SCHEDULER=false`), karena endpoint CARE belum dipisahkan dari PIM pada template. Aktifkan setelah alamat CARE benar dan jadwal yang akan berjalan sudah diperiksa. Scanner file-drop PIM nonaktif karena integrasi aktif memakai HTTP.

PIM tetap pada port 8001. Setelah CMS sehat, tujuan publish PIM diarahkan ke `http://192.168.18.31:8000/api/integrations/pim`. Pemindahan ini tidak memublikasikan ulang katalog.

Admin CMS masih mengikuti model akses internal aplikasi yang ada; deployment ini hanya pada alamat LAN, tanpa konfigurasi domain atau akses Internet.
