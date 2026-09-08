# Integrasi PIM melalui shared folder NAS

Alur utama: **PIM simulator → file-drop NAS → PIM folder scanner → database CMS → Core API**. Tidak ada API call CMS ke PIM maupun callback PIM ke CMS dalam alur ini. PIM simulator dan shared folder boleh berjalan pada NAS yang sama.

## Yang sudah terpasang

- Simulator menulis media fisik dan metadata SKU ke direktori `.partial`, lalu rename ke `.ready` setelah lengkap.
- `php artisan pim:scan` membaca paket `.ready`, memvalidasi manifest, tipe media, path, dan checksum SHA-256.
- Impor produk dan catatan batch dilakukan dalam satu transaksi. Harga, stok, zona, material, flag, dan RFID yang sudah ada tidak diubah.
- Setiap SKU varian menjadi satu produk. Produk baru memakai default harga dan stok nol sampai CARE mengisinya.
- Scanner melewati batch yang sudah berhasil dan melindungi konten baru dari batch lama yang datang terlambat.
- Media disalin ke penyimpanan lokal CMS, sehingga aplikasi pengguna tidak memerlukan kredensial SMB. API `/api/pim-media/{hash.ext}` menyajikan gambar/video; Product API menambahkan `pim_media`.
- `/admin/pim` menampilkan ketersediaan folder, status batch, error, pemindaian manual, dan retry.
- Jadwal `pim:scan` terdaftar setiap menit. Scheduler Laravel harus berjalan agar otomatis aktif.
- Jalur API lama dinonaktifkan secara default dengan `PIM_LEGACY_HTTP_ENABLED=false`.

## Konfigurasi laptop saat ini

`.env` lokal kedua proyek menggunakan folder bersama berikut untuk pengujian:

```text
D:/LAPTOP FAIZAL/_Project/pim-simulator-02/pim-drop
```

Folder ini **belum merupakan shared folder NAS**. Satu paket demo berhasil diimpor ke dua SKU, dan scan ulang melewatinya tanpa duplikasi. Demo menggunakan PNG placeholder 1 piksel untuk menguji pengiriman file; foto/video produk asli perlu disediakan di folder sumber simulator.

CMS tetap dijalankan dengan:

```sh
php artisan serve --host=0.0.0.0 --port=8000
```

Buka `http://localhost:8000/admin/pim`.

## Mengaktifkan NAS yang sebenarnya

### 1. Dataset dan share

Di TrueNAS siapkan dataset konten, contoh `/mnt/Tank/pim-simulator/content`. Berikan UID/GID container 1000 akses tulis. Bagikan dataset ini melalui SMB, contoh nama share `pim-content`.

Nama/path tersebut adalah contoh deployment, bukan share yang telah terverifikasi. Gunakan path yang benar dari pengaturan SMB NAS. Akun Windows yang menjalankan PHP harus memiliki akses baca. Gunakan UNC langsung karena mapped drive tidak selalu tersedia pada proses service/scheduler.

### 2. Simulator di TrueNAS

Source dan YAML baru tersedia dalam `pim-file-drop-deploy.tar.gz`. Ekstrak pada folder source aplikasi di NAS dan build:

```sh
sudo docker build -t pim-api-simulator:1.1.0 .
```

Edit aplikasi yang sudah terpasang menggunakan `compose.truenas.yaml`. Sesuaikan dataset pada bind mount:

```yaml
environment:
  PIM_DELIVERY_MODE: file-drop
  PIM_DROP_DIR: /pim-drop
  PIM_DEMO_MEDIA: "true"
volumes:
  - type: bind
    source: /mnt/Tank/pim-simulator/content
    target: /pim-drop
    bind:
      create_host_path: false
```

Pertahankan volume database dan environment lain pada YAML lengkap. Token/URL callback CMS tidak dipakai untuk mode ini. Restart simulator tetap mereset seed/history in-memory sesuai perilaku lama, tetapi dataset konten tidak dihapus oleh startup.

Untuk foto/video asli, set `PIM_DEMO_MEDIA=false`, mount sumber read-only ke `/pim-source`, dan set `PIM_MEDIA_SOURCE_DIR=/pim-source`. Struktur per SKU dan kontrak paket dijelaskan dalam `pim-simulator-02/FILE-DROP.md`.

### 3. CMS

Gunakan SMB share aktual pada `.env`, contoh:

```dotenv
APP_URL=http://localhost:8000
PIM_FOLDER='//192.168.18.31/pim-content'
PIM_SCAN_ENABLED=true
PIM_LEGACY_HTTP_ENABLED=false
```

Jangan menggunakan path dataset Linux `/mnt/...` pada PHP Windows. Jika CMS kelak berada di Linux/container, mount shared folder read-only dan isi `PIM_FOLDER` dengan path mount tersebut.

```sh
php artisan migrate --force
php artisan config:clear
php artisan pim:scan
```

Untuk scan terjadwal pada development:

```sh
php artisan schedule:work
```

Perintah tersebut menjalankan seluruh jadwal aplikasi yang sudah ada, termasuk CARE. Pada server, gunakan Laravel scheduler yang menjalankan `php artisan schedule:run` setiap menit melalui pengelola service/task OS. `php artisan serve` sendiri tidak menjalankan scheduler. Jalankan satu instance scanner/backend dengan direktori storage yang sama; lock file menyelaraskan pemindaian manual dan scheduler pada instance tersebut.

## Status dan pemulihan

- `Published` pada simulator berarti file-drop selesai, bukan konfirmasi impor CMS.
- `imported` di CMS berarti transaksi impor telah selesai; pesan menampilkan jumlah SKU yang diperbarui. Batch lama dapat menghasilkan nol pembaruan.
- `.partial` diabaikan. Jika penulisan terputus, periksa paket tersebut dan publish batch baru setelah memperbaiki sumber.
- Batch gagal tidak dicoba ulang terus-menerus. Setelah memperbaiki masalah file/akses, centang **Coba ulang batch gagal** atau jalankan `php artisan pim:scan --retry-failed`.
- Jangan mengedit paket yang sudah `imported`. Perubahan konten harus memakai batch ID baru.
- Scanner mempertahankan paket NAS. Tidak ada penghapusan konten otomatis. Media beralamat hash di CMS juga dipertahankan; kebijakan retensi/cleanup belum diterapkan.
- Metadata v1 mencakup SKU, nama, deskripsi, dan media. Metadata lain seperti teknologi/spesifikasi belum dipetakan ke katalog CMS.
- Pastikan jam sumber PIM benar; urutan pembaruan menggunakan waktu UTC paket, dengan batch ID sebagai pembeda jika waktunya sama.

## Pengujian

```sh
# Di pim-simulator-02
npm test -- --silent
npm run demo:drop

# Di backend-eiger
php artisan test --filter='PimFolderImportTest|PimIntegrationTest|RfidTagWorkflowTest'
php artisan pim:scan
php artisan pim:scan
```

Scan kedua melewati paket yang sudah diimpor. Tes memakai penyimpanan/database terisolasi dan memastikan tidak ada panggilan HTTP dari scanner.

API lama dapat diaktifkan secara eksplisit dengan `PIM_LEGACY_HTTP_ENABLED=true`; alatnya berada di `/admin/pim/qa`. Opsi ini khusus QA dan bukan alur arsitektur utama. CMS saat ini masih mengikuti model akses development tanpa login yang sudah ada.
