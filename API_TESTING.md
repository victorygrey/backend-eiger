# EIGER Backend — API Testing Guide

> Dokumentasi lengkap untuk mengetes seluruh endpoint API EIGER Digital Store Backend.

---

## ⚙️ Prasyarat

Pastikan service Laravel sudah berjalan:

```bash
php artisan serve
```

> **Base URL default:** `http://127.0.0.1:8000/api`
> Semua endpoint **publik** (belum ada autentikasi).

### Tools yang Bisa Dipakai

| Tool | Keterangan |
|---|---|
| **cURL** | Tersedia di terminal / PowerShell |
| **Postman** | GUI interaktif, import raw cURL |
| **Insomnia** | Ringan, mirip Postman |
| **Thunder Client** | Ekstensi VS Code |
| **REST Client** | Ekstensi VS Code (file `.http`) |
| **HTTPie** | CLI yang lebih ramah (`http`) |

### Header Wajib (khusus `POST`/`PUT`)

```
Accept: application/json
Content-Type: application/json
```

> Tanpa `Accept: application/json`, error validation akan return HTML (redirect) bukan JSON.

---

## 📑 Daftar Endpoint

| # | Method | Endpoint | Modul |
|---|---|---|---|
| 1 | `GET` | `/api/products` | Products |
| 2 | `POST` | `/api/products` | Products |
| 3 | `GET` | `/api/products/{id}` | Products |
| 4 | `PUT` | `/api/products/{id}` | Products |
| 5 | `DELETE` | `/api/products/{id}` | Products |
| 6 | `GET` | `/api/zones` | Zones |
| 7 | `POST` | `/api/zones` | Zones |
| 8 | `GET` | `/api/zones/{id}` | Zones |
| 9 | `PUT` | `/api/zones/{id}` | Zones |
| 10 | `DELETE` | `/api/zones/{id}` | Zones |
| 11 | `GET` | `/api/rfid-tags` | RFID Tags |
| 12 | `POST` | `/api/rfid-tags` | RFID Tags |
| 13 | `GET` | `/api/rfid-tags/{id}` | RFID Tags |
| 14 | `PUT` | `/api/rfid-tags/{id}` | RFID Tags |
| 15 | `DELETE` | `/api/rfid-tags/{id}` | RFID Tags |
| 16 | `POST` | `/api/rfid-tags/resolve` | Resolve RFID Tags |
| 17 | `POST` | `/api/rfid-tags/batch` | Batch-register RFID Tags |
| 18 | `GET` | `/api/print-rules` | Print Rules |
| 19 | `PUT` | `/api/print-rules/{id}` | Print Rules |
| 20 | `POST` | `/api/sync/care` | Sync CARE |
| 21 | `POST` | `/api/tablets/activate` | Tablet Display |
| 22 | `GET` | `/api/tablets/{slug}/display` | Tablet Display |
| 23 | `POST` | `/api/tablets/{slug}/heartbeat` | Tablet Display |

## Interactive Tablet Display

Konfigurasi tablet dikelola dari `/admin/tablets`. Setiap perangkat memiliki satu featured product dan recommendation terurut yang merujuk ke tabel `products` yang sama.

```bash
# Aktivasi satu kali pada perangkat
curl -X POST "http://127.0.0.1:8000/api/tablets/activate" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"slug":"lobby-01","activation_code":"LOBBY-01"}'

# Ambil konfigurasi (ganti TOKEN dari respons aktivasi)
curl "http://127.0.0.1:8000/api/tablets/lobby-01/display" \
  -H "Accept: application/json" -H "Authorization: Bearer TOKEN"

# Laporkan health perangkat
curl -X POST "http://127.0.0.1:8000/api/tablets/lobby-01/heartbeat" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"version":1,"media_status":"ready"}'
```

---

## 🟢 1. Products — `/api/products`

### 1.1 `GET /api/products`

List produk dengan pagination + filter opsional.

**Query Params (opsional):**

| Param | Tipe | Contoh | Keterangan |
|---|---|---|---|
| `search` | string | `Tas` | Cari di kolom `name` & `sku` |
| `zone_id` | int | `1` | Filter berdasarkan zone |
| `is_featured` | bool | `1` / `0` | Filter produk unggulan |
| `page` | int | `2` | Halaman pagination |

```bash
curl -X GET "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json"

curl -X GET "http://127.0.0.1:8000/api/products?search=Tas" \
  -H "Accept: application/json"

curl -X GET "http://127.0.0.1:8000/api/products?zone_id=1&is_featured=1" \
  -H "Accept: application/json"

curl -X GET "http://127.0.0.1:8000/api/products?page=2" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "data": [
    {
      "id": 1,
      "sku": "EGR-9473",
      "name": "EIGER Tas Ransel accusantium",
      "price": "549000.00",
      "stock": 15,
      "image": null,
      "material": null,
      "description": null,
      "is_featured": true,
      "is_discontinued": false,
      "zone": {
        "id": 1,
        "name": "Zona Tas & Aksesoris",
        "description": "...",
        "products_count": 12
      }
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta":  { "current_page": 1, "from": 1, "last_page": 4, "per_page": 15, "to": 15, "total": 50 }
}
```

---

### 1.2 `POST /api/products`

Buat produk baru.

**Body (JSON):**

| Field | Tipe | Wajib | Validasi |
|---|---|---|---|
| `sku` | string | ✅ | required, max 100, **unique** di tabel `products` |
| `name` | string | ✅ | required, max 255 |
| `price` | numeric | ❌ | min 0 |
| `stock` | integer | ❌ | min 0 |
| `zone_id` | integer | ❌ | harus ada di tabel `zones` |
| `image` | string | ❌ | max 500 (URL) |
| `material` | string | ❌ | max 100 |
| `description` | string | ❌ | - |
| `is_featured` | bool | ❌ | - |
| `is_discontinued` | bool | ❌ | - |

```bash
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "sku": "EGR-TEST-001",
    "name": "Tas Ransel Test",
    "price": 549000,
    "stock": 25,
    "zone_id": 1,
    "image": "https://example.com/tas.jpg",
    "material": "Ripstop Nylon",
    "description": "Tas gunung 40L untuk testing API",
    "is_featured": true,
    "is_discontinued": false
  }'
```

**Response sukses (201):**

```json
{
  "success": true,
  "message": "Product created successfully.",
  "data": {
    "id": 51,
    "sku": "EGR-TEST-001",
    "name": "Tas Ransel Test",
    "price": "549000.00",
    "stock": 25,
    "image": "https://example.com/tas.jpg",
    "material": "Ripstop Nylon",
    "description": "Tas gunung 40L untuk testing API",
    "is_featured": true,
    "is_discontinued": false,
    "zone": { "id": 1, "name": "Zona Tas & Aksesoris" }
  }
}
```

**Response error validasi (422):**

```json
{
  "message": "The sku field is required. (and 1 more error)",
  "errors": {
    "sku":  ["The sku field is required."],
    "name": ["The name field is required."]
  }
}
```

---

### 1.3 `GET /api/products/{id}`

Ambil detail 1 produk (beserta relasi `zone` & `rfidTag`).

```bash
curl -X GET "http://127.0.0.1:8000/api/products/1" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "success": true,
  "message": "Product retrieved successfully.",
  "data": {
    "id": 1,
    "sku": "EGR-9473",
    "name": "EIGER Tas Ransel accusantium",
    "price": "549000.00",
    "stock": 15,
    "is_featured": true,
    "is_discontinued": false,
    "zone": { "id": 1, "name": "Zona Tas & Aksesoris" },
        "rfid_tag": { "id": 1, "uid": "E2801170AF2B0B00" }
  }
}
```

**Response not found (404):**

```json
{ "message": "No query results for model [App\\Models\\Product] 999" }
```

---

### 1.4 `PUT /api/products/{id}`

Update produk (partial update OK, field tidak dikirim = tidak diubah).

```bash
curl -X PUT "http://127.0.0.1:8000/api/products/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "price": 599000,
    "stock": 30,
    "is_featured": false
  }'
```

**Response sukses (200):**

```json
{
  "success": true,
  "message": "Product updated successfully.",
  "data": { "id": 1, "price": "599000.00", "stock": 30, "is_featured": false, "...": "..." }
}
```

---

### 1.5 `DELETE /api/products/{id}`

Hapus produk (soft delete, sesuai model).

```bash
curl -X DELETE "http://127.0.0.1:8000/api/products/1" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "success": true,
  "message": "Product deleted successfully.",
  "data": null
}
```

---

## 🟢 2. Zones — `/api/zones`

### 2.1 `GET /api/zones`

List semua zone + jumlah produk.

```bash
curl -X GET "http://127.0.0.1:8000/api/zones" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Zona Tas & Aksesoris",
      "description": "Produk tas, ransel, dan aksesoris pelengkap",
      "products_count": 12
    },
    { "id": 2, "name": "Zona Sepatu", "description": "...", "products_count": 8 }
  ]
}
```

---

### 2.2 `POST /api/zones`

| Field | Tipe | Wajib | Validasi |
|---|---|---|---|
| `name` | string | ✅ | required, max 255, **unique** di tabel `zones` |
| `description` | string | ❌ | - |

```bash
curl -X POST "http://127.0.0.1:8000/api/zones" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Zona Testing",
    "description": "Zone yang dibuat via API test"
  }'
```

---

### 2.3 `GET /api/zones/{id}`

```bash
curl -X GET "http://127.0.0.1:8000/api/zones/1" \
  -H "Accept: application/json"
```

---

### 2.4 `PUT /api/zones/{id}`

```bash
curl -X PUT "http://127.0.0.1:8000/api/zones/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Zona Tas Updated",
    "description": "Deskripsi baru"
  }'
```

---

### 2.5 `DELETE /api/zones/{id}`

```bash
curl -X DELETE "http://127.0.0.1:8000/api/zones/1" \
  -H "Accept: application/json"
```

> ⚠️ Hati-hati: zone yang masih punya produk akan error/break relasi (cek constraint FK).

---

## 🟢 3. RFID Tags — `/api/rfid-tags`

### 3.1 `GET /api/rfid-tags`

Paginated, 15/halaman, latest first.

```bash
curl -X GET "http://127.0.0.1:8000/api/rfid-tags" \
  -H "Accept: application/json"

curl -X GET "http://127.0.0.1:8000/api/rfid-tags?page=2" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "data": [
    {
      "id": 1,
      "uid": "E2801170AF2B0B00",
      "product": {
        "id": 1,
        "sku": "EGR-9473",
        "name": "EIGER Tas Ransel accusantium"
      }
    }
  ],
  "links": { "...": "..." },
  "meta":  { "current_page": 1, "per_page": 15, "total": 10 }
}
```

---

### 3.2 `POST /api/rfid-tags`

| Field | Tipe | Wajib | Validasi |
|---|---|---|---|
| `uid` | string | ✅ | required, max 100, **unique** di tabel `rfid_tags` |
| `product_id` | integer | ❌ | nullable, jika diisi harus ada di tabel `products`, **unique** (1 produk hanya 1 tag) |

```bash
curl -X POST "http://127.0.0.1:8000/api/rfid-tags" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "uid": "E2801170AF2B0B01",
    "product_id": 1
  }'
```

---

### 3.3 `GET /api/rfid-tags/{id}`

```bash
curl -X GET "http://127.0.0.1:8000/api/rfid-tags/1" \
  -H "Accept: application/json"
```

---

### 3.4 `PUT /api/rfid-tags/{id}`

```bash
curl -X PUT "http://127.0.0.1:8000/api/rfid-tags/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "uid": "E2801170AF2B0B99",
    "product_id": 2
  }'
```

---

### 3.5 `DELETE /api/rfid-tags/{id}`

```bash
curl -X DELETE "http://127.0.0.1:8000/api/rfid-tags/1" \
  -H "Accept: application/json"
```

### 3.6 `POST /api/rfid-tags/resolve`

Memeriksa beberapa UID tanpa membuat data. Dipakai aplikasi RFID pada Read mode dan saat memeriksa tag baru.

```bash
curl -X POST "http://127.0.0.1:8000/api/rfid-tags/resolve" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"uids":["E20047103510602111380111","E200000000000002"]}'
```

Setiap elemen respons berisi `exists` dan `tag`; `tag.product` bernilai `null` jika tag sudah terdaftar tetapi belum dipetakan.

### 3.7 `POST /api/rfid-tags/batch`

Mendaftarkan UID yang belum ada sebagai tag tanpa produk. Aman dipanggil ulang: tag yang sudah ada tidak diubah dan mapping produknya tetap dipertahankan.

```bash
curl -X POST "http://127.0.0.1:8000/api/rfid-tags/batch" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"uids":["E20047103510602111380111","E200000000000002"]}'
```

Respons berisi `created_count`, `existing_count`, `created`, dan `existing`.

---

## 🟡 4. Print Rules — `/api/print-rules`

> **Read-only + update** — tidak ada `POST` / `DELETE`.
> Data di-seed via `PrintRuleSeeder`.

### 4.1 `GET /api/print-rules`

```bash
curl -X GET "http://127.0.0.1:8000/api/print-rules" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "data": [
    {
      "id": 1,
      "minimum_transaction": "250000.00",
      "require_membership": true,
      "enabled": true
    }
  ]
}
```

---

### 4.2 `PUT /api/print-rules/{id}`

| Field | Tipe | Wajib | Validasi |
|---|---|---|---|
| `minimum_transaction` | numeric | ❌ (`sometimes`) | min 0 |
| `require_membership` | bool | ❌ (`sometimes`) | - |
| `enabled` | bool | ❌ (`sometimes`) | - |

```bash
curl -X PUT "http://127.0.0.1:8000/api/print-rules/1" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "minimum_transaction": 300000,
    "require_membership": true,
    "enabled": true
  }'
```

**Response sukses (200):**

```json
{
  "success": true,
  "message": "Print rule updated successfully.",
  "data": { "id": 1, "minimum_transaction": "300000.00", "require_membership": true, "enabled": true }
}
```

---

## 🟠 5. Sync — `/api/sync/care`

### 5.1 `POST /api/sync/care`

Trigger sinkronisasi data CARE. Tidak butuh body.

```bash
curl -X POST "http://127.0.0.1:8000/api/sync/care" \
  -H "Accept: application/json"
```

**Response sukses (200):**

```json
{
  "success": true,
  "message": "CARE sync completed",
  "data": {
    "source": "api",
    "status": "success",
    "synced_at": "2026-08-04T18:30:00+07:00"
  }
}
```

**Response gagal (500):**

```json
{
  "success": false,
  "message": "CARE sync failed: <error message>",
  "data": {
    "source": "api",
    "status": "failed",
    "synced_at": "2026-08-04T18:30:00+07:00"
  }
}
```

> Hasil sync otomatis tercatat di tabel `sync_logs` dan tampil di dashboard admin.

---

## 🧪 Skenario Testing End-to-End

### ✅ Skenario 1: CRUD Lengkap Products

```bash
# 1) CREATE
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"sku":"TEST-01","name":"Produk Test","price":100000,"stock":5,"zone_id":1}'

# 2) READ list (cari yang baru dibuat)
curl -X GET "http://127.0.0.1:8000/api/products?search=TEST" \
  -H "Accept: application/json"

# 3) READ detail
curl -X GET "http://127.0.0.1:8000/api/products/<ID>" \
  -H "Accept: application/json"

# 4) UPDATE
curl -X PUT "http://127.0.0.1:8000/api/products/<ID>" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"price":150000,"stock":10}'

# 5) DELETE
curl -X DELETE "http://127.0.0.1:8000/api/products/<ID>" \
  -H "Accept: application/json"
```

### ✅ Skenario 2: Validasi Error

```bash
# SKU kosong → harus 422
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Tanpa SKU"}'

# SKU duplikat → harus 422
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"sku":"EGR-9473","name":"Duplikat"}'

# zone_id tidak ada → harus 422
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"sku":"X-1","name":"X","zone_id":9999}'

# Price negatif → harus 422
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"sku":"X-2","name":"X","price":-1000}'
```

### ✅ Skenario 3: RFID Tag Flow

```bash
# 1) Buat produk baru
curl -X POST "http://127.0.0.1:8000/api/products" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"sku":"RFID-TEST","name":"Produk RFID Test","price":99000,"stock":3,"zone_id":1}'

# 2) Attach RFID tag ke produk tersebut
curl -X POST "http://127.0.0.1:8000/api/rfid-tags" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"uid":"E20047103510602111380111","product_id":<ID>}'

# 3) Lihat produk → field rfid_tag harus muncul
curl -X GET "http://127.0.0.1:8000/api/products/<ID>" \
  -H "Accept: application/json"
```

### ✅ Skenario 4: Sync & Log

```bash
# 1) Trigger sync
curl -X POST "http://127.0.0.1:8000/api/sync/care" \
  -H "Accept: application/json"

# 2) Buka dashboard admin
# http://127.0.0.1:8000/admin/sync-logs
# Log entry terbaru harus muncul
```

---

## 🐛 Troubleshooting

| Problem | Solusi |
|---|---|
| Error HTML alih-alih JSON | Tambahkan header `Accept: application/json` |
| CORS error di browser | API ini **belum** diset CORS-friendly. Gunakan Postman/cURL |
| `404 Not Found` | Cek `php artisan route:list` untuk konfirmasi prefix `/api` |
| `419 Page Expired` | Endpoint API publik, tapi kalau pakai Sanctum perlu setup dulu |
| `405 Method Not Allowed` | Cek method yang didukung — Print Rules misalnya **tidak** punya POST/DELETE |
| `500 Internal Server Error` | Cek `storage/logs/laravel.log` untuk detail |

### 🔍 Debug Helpers

```bash
# Lihat semua route API
php artisan route:list --path=api

# Lihat log error terbaru
Get-Content storage/logs/laravel.log -Tail 50

# Clear cache kalau ada perubahan
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

---

## 📚 Referensi

- File route: [`routes/api.php`](../routes/api.php)
- Controllers: [`app/Http/Controllers/Api/`](../app/Http/Controllers/Api/)
- Form Requests: [`app/Http/Requests/`](../app/Http/Requests/)
- Resources: [`app/Http/Resources/`](../app/Http/Resources/)
- Service Sync: [`app/Services/CareSyncService.php`](../app/Services/CareSyncService.php)
