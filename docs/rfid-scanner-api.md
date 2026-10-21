# Pendaftaran RFID massal dari aplikasi scanner

`POST https://eiger-dev.automataserver.com/api/rfid-tags/batch`

Endpoint integrasi ini tidak membutuhkan login CMS, cookie sesi, CSRF, atau
Authorization/Bearer token. Aplikasi scanner dapat langsung mengirim JSON:

```http
Content-Type: application/json
Accept: application/json
```

```json
{
  "uids": ["E200000000000001", "E200000000000002"]
}
```

- `uids` wajib berupa array berisi 1–500 string UID heksadesimal, masing-masing maksimal 100 karakter setelah normalisasi.
- Spasi, tanda hubung, dan titik dua dibuang; huruf menjadi kapital. UID duplikat dalam request dihitung sekali.
- UID baru didaftarkan tanpa produk (`product_id: null`). UID yang sudah ada dikembalikan tanpa mengubah pemetaan produknya.
- Pemetaan produk serta aktivasi LED Ambience/Table Expedition tetap dikelola melalui CMS dan tidak dapat diatur lewat payload batch.

Respons `201` jika ada UID baru, atau `200` jika semuanya sudah terdaftar:

```json
{
  "success": true,
  "message": "2 RFID tag(s) created; 0 already existed.",
  "created_count": 2,
  "existing_count": 0,
  "created": [
    {"id": 101, "uid": "E200000000000001", "product_id": null, "product": null, "led_ambience_active": false, "table_expedition_active": false},
    {"id": 102, "uid": "E200000000000002", "product_id": null, "product": null, "led_ambience_active": false, "table_expedition_active": false}
  ],
  "existing": []
}
```

Setiap elemen `created`/`existing` merupakan objek RFID; contoh di atas menyingkat
kolom nama dan timestamp. ID pada contoh hanya ilustrasi. Request tidak valid
menghasilkan `422` beserta detail `errors`. Mengirim ulang UID yang sama tidak
menambah baris duplikat.

`POST /api/rfid-tags/resolve` tetap tersedia untuk mengecek UID tanpa menulis data.
Endpoint pengelolaan satu tag (`POST /api/rfid-tags`, `PUT/PATCH/DELETE
/api/rfid-tags/{id}`) tetap membutuhkan sesi CMS, CSRF, dan izin role terkait.
