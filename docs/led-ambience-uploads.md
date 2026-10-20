# Upload video dan audio LED Ambience

Image Docker memuat `docker/uploads.ini` untuk PHP-FPM dan CLI. Batas file PHP
500 MB, batas total POST PHP dan Nginx 610 MB: cukup untuk video 500 MB, audio
100 MB, serta metadata form. Validasi Laravel tetap membatasi jenis dan ukuran
masing-masing file. Batas waktu pembacaan upload 300 detik.

Perubahan ini harus masuk image baru; mengubah `runtime.env` saja tidak mengubah
`upload_max_filesize` atau `post_max_size` PHP. Nilai default aplikasi di
`config/led_ambience.php` sesuai batas tersebut. Jika batas dinaikkan lewat env,
batas PHP/Nginx juga harus disesuaikan sebelum deploy.

Pada domain melalui Cloudflare, ada batas ukuran **per request** sesuai paket
Cloudflare. Free/Pro membatasi 100 MB. Video dan audio sekitar 30 MB dan 3 MB
bisa dikirim bersama. Untuk request lebih besar dari batas paket, gunakan CMS
melalui jaringan lokal NAS; menaikkan batas origin tidak mengubah batas proxy.

Referensi: https://developers.cloudflare.com/support/troubleshooting/http-status-codes/4xx-client-error/error-413/

Media tersimpan dengan checksum di dataset `eiger-media/led-ambience/videos/`
atau `eiger-media/led-ambience/audio/`, dipisahkan per template. Isi file asli
dipertahankan. Nama produk, payload API wahana, dan data PIM tidak berubah.
