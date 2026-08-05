# EIGER Backend — Laravel 11 REST API & Admin Console

## Overview

Membangun backend utama **EIGER Digital Store** menggunakan Laravel 11 sebagai REST API dengan SQLite sebagai database untuk tahap development/testing. Project ini akan menjadi pusat data untuk Virtual Fitting Try-On, Product Catalog, Table Product Knowledge, Immersive Ambience Digital, dan Print Photo.

---

## User Review Required

> [!IMPORTANT]
> Directory target adalah `d:\LAPTOP FAIZAL\_Project\backend-eiger` yang saat ini **kosong**. Laravel 11 akan di-install langsung di direktori ini menggunakan `composer create-project`.

> [!WARNING]
> Pengerjaan dilakukan **secara bertahap sesuai urutan yang diminta** — tidak semua file dibuat sekaligus. Setiap tahap akan dijelaskan sebelum dieksekusi.

> [!NOTE]
> Tidak ada autentikasi (Login/Sanctum) pada tahap ini. Semua endpoint accessible secara bebas untuk keperluan development.

---

## Open Questions

Tidak ada open question yang memblokir implementasi. Semua requirement sudah lengkap.

---

## Proposed Changes

### TAHAP 1 — Laravel Project Setup

#### [NEW] Laravel 11 Project (via Composer)
- Install Laravel 11 di `d:\LAPTOP FAIZAL\_Project\backend-eiger`
- Konfigurasi `.env` untuk SQLite
- Verifikasi struktur Laravel 11 default

---

### TAHAP 2 — Database Migration

#### [NEW] migrations/
| File | Table |
|---|---|
| `create_zones_table.php` | zones |
| `create_products_table.php` | products |
| `create_rfid_tags_table.php` | rfid_tags |
| `create_print_rules_table.php` | print_rules |
| `create_sync_logs_table.php` | sync_logs |

**Schema detail:**
- `zones`: id, name, description, timestamps
- `products`: id, sku(unique), name, price, stock, zone_id(FK), image, material, description, is_featured, is_discontinued, timestamps
- `rfid_tags`: id, uid, product_id(FK), timestamps
- `print_rules`: id, minimum_transaction, require_membership, enabled, timestamps
- `sync_logs`: id, source, status, message, synced_at, timestamps

---

### TAHAP 3 — Eloquent Models & Relationships

#### [NEW] Models
| Model | Relationships |
|---|---|
| `Zone.php` | `hasMany(Product::class)` |
| `Product.php` | `belongsTo(Zone::class)`, `hasOne(RfidTag::class)` |
| `RfidTag.php` | `belongsTo(Product::class)` |
| `PrintRule.php` | — |
| `SyncLog.php` | — |

---

### TAHAP 4 — Factories

#### [NEW] Factories
- `ZoneFactory.php` — 8 zona realistis
- `ProductFactory.php` — 50 produk dengan Faker
- `RfidTagFactory.php` — 20 tag UID
- `PrintRuleFactory.php` — 1 data rule
- `SyncLogFactory.php` — 5 dummy log

---

### TAHAP 5 — Seeders

#### [NEW] Seeders
- `ZoneSeeder.php`
- `ProductSeeder.php`
- `RfidTagSeeder.php`
- `PrintRuleSeeder.php`
- `SyncLogSeeder.php`
- `DatabaseSeeder.php` (orchestrator)

---

### TAHAP 6 — Run Migration & Seeder

```bash
php artisan migrate --seed
```

---

### TAHAP 7 — Form Request Validation

#### [NEW] app/Http/Requests/
| Request | Digunakan untuk |
|---|---|
| `StoreProductRequest.php` | POST /api/products |
| `UpdateProductRequest.php` | PUT /api/products/{id} |
| `StoreZoneRequest.php` | POST /api/zones |
| `UpdateZoneRequest.php` | PUT /api/zones/{id} |
| `StoreRfidTagRequest.php` | POST /api/rfid-tags |
| `UpdateRfidTagRequest.php` | PUT /api/rfid-tags/{id} |
| `UpdatePrintRuleRequest.php` | PUT /api/print-rules/{id} |

Validasi Product yang diimplementasikan:
- `sku`: required, unique (ignore self on update)
- `name`: required, string
- `price`: numeric, min:0
- `stock`: integer, min:0

---

### TAHAP 8 — API Resources

#### [NEW] app/Http/Resources/
| Resource | Deskripsi |
|---|---|
| `ProductResource.php` | Representasi Product |
| `ProductCollection.php` | Paginated list |
| `ZoneResource.php` | Representasi Zone |
| `RfidTagResource.php` | Representasi RFID Tag |
| `PrintRuleResource.php` | Representasi Print Rule |
| `SyncLogResource.php` | Representasi Sync Log |

Format response standar:
```json
{
    "success": true,
    "message": "Success",
    "data": {}
}
```

---

### TAHAP 9 — API Controllers

#### [NEW] app/Http/Controllers/Api/
| Controller | Endpoints |
|---|---|
| `ProductController.php` | GET, GET/{id}, POST, PUT/{id}, DELETE/{id} |
| `ZoneController.php` | GET, GET/{id}, POST, PUT/{id}, DELETE/{id} |
| `RfidTagController.php` | GET, GET/{id}, POST, PUT/{id}, DELETE/{id} |
| `PrintRuleController.php` | GET, PUT/{id} |
| `SyncController.php` | POST /api/sync/care |

Menggunakan Route Model Binding untuk semua endpoint by ID.

---

### TAHAP 10 — API Routes

#### [MODIFY] routes/api.php
```
/api/products         — ProductController (resource)
/api/zones            — ZoneController (resource)
/api/rfid-tags        — RfidTagController (resource)
/api/print-rules      — PrintRuleController (index, update)
/api/sync/care        — SyncController@care
```

---

### TAHAP 11 — Blade Admin Layout

#### [NEW] resources/views/layouts/
- `admin.blade.php` — Master layout dengan Bootstrap 5 CDN
- Sidebar navigasi: Dashboard, Products, Zones, RFID Tags, Print Rules, Sync Logs
- Topbar dengan nama project "EIGER Admin Console"

---

### TAHAP 12 — Dashboard

#### [NEW] resources/views/admin/dashboard.blade.php
Menampilkan:
- Total Products, Zones, RFID Tags, Print Rules (stat cards)
- Backend Status (always "Running")
- Last Synchronization (dari sync_logs terbaru)
- Recent Sync Logs (tabel 5 record terakhir)

#### [NEW] app/Http/Controllers/Admin/DashboardController.php

---

### TAHAP 13 — CRUD Products (Admin)

#### [NEW] app/Http/Controllers/Admin/ProductController.php
#### [NEW] resources/views/admin/products/ (index, create, edit)
- Search by name/SKU
- Pagination 10 per page
- Form create/edit dengan semua field

---

### TAHAP 14 — CRUD Zones (Admin)

#### [NEW] app/Http/Controllers/Admin/ZoneController.php
#### [NEW] resources/views/admin/zones/ (index, create, edit)

---

### TAHAP 15 — CRUD RFID Tags (Admin)

#### [NEW] app/Http/Controllers/Admin/RfidTagController.php
#### [NEW] resources/views/admin/rfid-tags/ (index, create, edit)

---

### TAHAP 16 — Edit Print Rules (Admin)

#### [NEW] app/Http/Controllers/Admin/PrintRuleController.php
#### [NEW] resources/views/admin/print-rules/ (index, edit)

---

### TAHAP 17 — Sync Logs (Admin, Read-Only)

#### [NEW] app/Http/Controllers/Admin/SyncLogController.php
#### [NEW] resources/views/admin/sync-logs/index.blade.php

---

### TAHAP 18 — CareSyncService

#### [NEW] app/Services/CareSyncService.php
- Method `sync()` yang mensimulasikan sinkronisasi
- Menulis record ke tabel `sync_logs`

---

### TAHAP 19 — Artisan Command care:sync

#### [NEW] app/Console/Commands/CareSyncCommand.php
```bash
php artisan care:sync
```
- Memanggil `CareSyncService::sync()`
- Mencetak output ke console
- Menambahkan log ke `sync_logs`

---

### TAHAP 20 — Testing

- Menjalankan semua endpoint API via curl/artisan tinker untuk verifikasi
- Cek CRUD admin via browser
- Verifikasi format response JSON sesuai spesifikasi

---

## Directory Structure (Final)

```
backend-eiger/
├── app/
│   ├── Console/Commands/
│   │   └── CareSyncCommand.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── ZoneController.php
│   │   │   │   ├── RfidTagController.php
│   │   │   │   ├── PrintRuleController.php
│   │   │   │   └── SyncController.php
│   │   │   └── Admin/
│   │   │       ├── DashboardController.php
│   │   │       ├── ProductController.php
│   │   │       ├── ZoneController.php
│   │   │       ├── RfidTagController.php
│   │   │       ├── PrintRuleController.php
│   │   │       └── SyncLogController.php
│   │   ├── Requests/
│   │   │   ├── StoreProductRequest.php
│   │   │   ├── UpdateProductRequest.php
│   │   │   ├── StoreZoneRequest.php
│   │   │   ├── UpdateZoneRequest.php
│   │   │   ├── StoreRfidTagRequest.php
│   │   │   ├── UpdateRfidTagRequest.php
│   │   │   └── UpdatePrintRuleRequest.php
│   │   └── Resources/
│   │       ├── ProductResource.php
│   │       ├── ZoneResource.php
│   │       ├── RfidTagResource.php
│   │       ├── PrintRuleResource.php
│   │       └── SyncLogResource.php
│   ├── Models/
│   │   ├── Zone.php
│   │   ├── Product.php
│   │   ├── RfidTag.php
│   │   ├── PrintRule.php
│   │   └── SyncLog.php
│   └── Services/
│       └── CareSyncService.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── resources/views/
│   ├── layouts/
│   │   └── admin.blade.php
│   └── admin/
│       ├── dashboard.blade.php
│       ├── products/
│       ├── zones/
│       ├── rfid-tags/
│       ├── print-rules/
│       └── sync-logs/
└── routes/
    ├── api.php
    └── web.php
```

---

## Verification Plan

### Automated Tests
- `php artisan migrate:status` — verifikasi semua migration berjalan
- `php artisan db:seed --class=DatabaseSeeder` — verifikasi seeder
- `php artisan route:list` — verifikasi semua route terdaftar
- `php artisan care:sync` — verifikasi command berjalan

### Manual Verification
- Akses `http://localhost:8000/admin` untuk melihat Admin Console
- Test endpoint API menggunakan curl atau browser untuk setiap resource
- Verifikasi format response JSON sesuai spesifikasi

---

## Notes

- **PHP Version**: 8.2+ diperlukan untuk Laravel 11
- **SQLite**: File database akan dibuat otomatis di `database/database.sqlite`
- **Bootstrap 5**: Digunakan via CDN, tidak perlu npm/node
- **No Authentication**: Semua endpoint dan halaman admin accessible tanpa login
- **Future-Ready**: Struktur folder dirancang untuk mudah menambahkan Queue, Scheduler, Auth, dll
