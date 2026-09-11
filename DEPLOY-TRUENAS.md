# CMS EIGER di TrueNAS

Target: `http://192.168.18.31:8000`, aplikasi TrueNAS `eiger-backend`, image awal `eiger-backend:20260911-1`.

## Penyimpanan

- Dataset: `/mnt/Tank/eiger-backend`.
- Source release awal: `/mnt/Tank/eiger-backend/releases/20260911-1`.
- Database: `/mnt/Tank/eiger-backend/data/database.sqlite`, dipasang ke `/data/database.sqlite`.
- Media dan storage Laravel: `/mnt/Tank/eiger-backend/storage`.
- Konfigurasi: `/mnt/Tank/eiger-backend/config/runtime.env`, permission 0600, hanya root. APP_KEY dipertahankan dari CMS lokal.
- Backup SQLite otomatis sebelum migrasi: `/mnt/Tank/eiger-backend/data/backups`.

Folder migrasi tidak dipasang sebagai volume. Migrasi mengikuti versi image; data tetap berada di dataset. Tidak ada seed/reset data saat startup. Konfigurasi database menggunakan DB_DATABASE, bukan DB_FILE.

## Update dari GitHub

Deployment pertama memakai snapshot source lokal karena file Docker baru belum dikomit ke GitHub. Commit dan push file deployment yang sudah diperbaiki terlebih dahulu, tanpa `.env.docker`, database, media, atau runtime secrets. Repository: `https://github.com/victorygrey/backend-eiger.git`.

Siapkan checkout repository di direktori source terpisah di NAS. Gunakan mekanisme autentikasi GitHub yang sudah diizinkan jika repository privat. Jalankan `sudo bash deploy.sh` pada checkout tersebut. Script meminta working tree bersih, melakukan pull fast-forward, build image bertag commit, lalu memperbarui aplikasi yang sudah ada melalui API TrueNAS. Script mempertahankan konfigurasi live, termasuk env_file dan mount. Ia tidak menjalankan `docker compose up` terpisah dari Apps.

Periksa status job Apps dan `/up` setelah update. Untuk rollback kode, pilih image sebelumnya dengan konfigurasi dan volume yang sama. Rollback image tidak otomatis membatalkan migrasi; pulihkan backup database hanya bila diperlukan setelah menilai data baru yang masuk.

## Layanan

Nginx dan PHP-FPM melayani CMS. Queue worker aktif. Scheduler tersedia tetapi awalnya nonaktif (`RUN_SCHEDULER=false`), karena endpoint CARE belum dipisahkan dari PIM pada template. Aktifkan setelah alamat CARE benar dan jadwal yang akan berjalan sudah diperiksa. Scanner file-drop PIM nonaktif karena integrasi aktif memakai HTTP.

PIM tetap pada port 8001. Setelah CMS sehat, tujuan publish PIM diarahkan ke `http://192.168.18.31:8000/api/integrations/pim`. Pemindahan ini tidak memublikasikan ulang katalog.

Admin CMS masih mengikuti model akses internal aplikasi yang ada; deployment ini hanya pada alamat LAN, tanpa konfigurasi domain atau akses Internet.
