# Pemilihan produk AI Fit & Go

- Kedua mode menyimpan seluruh produk yang dicentang per lantai, tanpa batas 30 produk.
- Produk tetap harus aktif untuk AI Fit & Go dan berstatus Config Done (foto dan deskripsi setiap warna lengkap).
- Mode **Terbaru** menggunakan pilihan admin untuk lantai tersebut. Produk dari katalog global atau lantai lain tidak otomatis ikut ditampilkan.
- Semua pilihan tetap dipakai. Daftar di CMS dan kategori API kiosk diurutkan terbaru ke terlama berdasarkan `products.created_at`, lalu ID terbesar jika waktunya sama. Mengedit konfigurasi tidak membuat produk lama menjadi produk terbaru.
- Rekomendasi aktivitas pada mode Terbaru merupakan irisan rekomendasi aktivitas dengan pilihan lantai, dengan urutan terbaru yang sama.
- `GET /api/v1/fit-and-go/products` dan `/search` tanpa parameter `limit` mengembalikan seluruh hasil pilihan. Parameter `limit` tetap opsional untuk klien yang menginginkan hasil lebih sedikit (1–50).
- Bar Simpan Config Product tetap terlihat saat halaman di-scroll. Menyimpan dengan semua checkbox kosong mengosongkan pilihan lantai tersebut.

Tidak membutuhkan migrasi database. Pilihan per lantai yang sudah tersimpan tetap digunakan.
