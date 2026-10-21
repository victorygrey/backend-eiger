# Impor ATOM dari daftar produk pilihan

`atom:import-selection` menambahkan artikel yang belum ada ke skema produk PIM CMS. Perintah ini tidak menghapus produk dan selalu melewati SKU yang sudah ada, baik berasal dari PIM maupun ATOM. Tidak ada perubahan kontrak API wahana atau migrasi database.

1. Buat backup konsisten database SQLite dengan SQLite backup API atau `.backup`, lalu periksa `PRAGMA integrity_check` dan salin backup ke PC.
2. Simpan daftar sementara hasil pencocokan spreadsheet, URL situs, slug, dan SKU yang sudah diverifikasi. Nama yang mirip saja tidak cukup. Jangan mengganti SKU spreadsheet dengan artikel lain tanpa pemeriksaan.
3. Ambil detail, `variants`, dan `variant-images` dari ATOM. `resources` opsional pada setiap artikel dapat menyimpan ketiga respons yang sudah dinormalisasi menjadi `detail`, `variants`, dan `variant_images`.
4. Cocokkan warna ke SKU melalui hasil varian per warna di situs resmi. Jangan menyimpulkan warna berdasarkan urutan array varian. `variants[].color.name` yang eksplisit diprioritaskan oleh mapper.
5. Simpan manifest JSON di folder impor NAS. Jangan commit database, manifest operasional, foto, credential, atau salinan spreadsheet ke Git.

```sh
php artisan atom:import-selection /path/selection-manifest.json --dry-run
php artisan atom:import-selection /path/selection-manifest.json
```

Manifest berisi `products` dengan `sku` 9 digit, `slug`, `name`, `gender`, `gender_source`, `product_type`, dan `activity`. `protected_skus` dapat berisi SKU yang selalu dilewati. Gunakan `--limit=1` untuk percobaan dan `--skip-media` hanya bila sengaja menyimpan URL remote.

Perintah menolak duplikasi SKU, SKU detail yang berbeda, dan SKU varian arsip yang tidak sesuai artikel. Manifest dicatat ulang setelah setiap artikel; proses dapat dilanjutkan dengan perintah yang sama. Status `imported`, `skipped_existing`, dan `protected` dilewati pada eksekusi berikutnya; `failed` dicoba lagi. Jalankan hanya satu proses untuk manifest yang sama.

Foto diunduh melalui importer media CMS yang sudah ada, disimpan sebagai JPG pada folder produk di NAS, dan tetap memakai endpoint media CMS. Media yang tidak tersedia mengikuti penanganan fallback importer yang ada.

## Regresi lokal melalui HTTP

Mock lokal memakai bentuk respons dari panduan ATOM dan fixture pengujian; ini bukan spesifikasi lengkap milik provider. Mock pemilik tidak ditarik karena tidak ada workspace ATOM terhubung. Simulasi tidak membutuhkan login/publikasi Postman.

```powershell
postman simulation run postman/simulations/atom-import.sim.yaml --port 4907 --no-history --output ndjson
# Di terminal lain:
$env:ATOM_SIMULATION_URL='http://127.0.0.1:4907/atom/api/v1/app'
php artisan test --compact --filter=test_real_http_client_imports_from_optional_local_simulation
Remove-Item Env:ATOM_SIMULATION_URL
```

Periksa log simulasi: harus ada tiga GET detail, variants, dan variant-images. Tes ini menjalankan client HTTP Laravel asli dan menulis ke database pengujian in-memory; tidak menyentuh staging.
