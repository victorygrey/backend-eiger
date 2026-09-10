# Database Documentation — EIGER Digital Store Backend

Skema database (relasional, MySQL-compatible) untuk backend EIGER Digital Store.
Termasuk konfigurasi display untuk setiap interactive tablet.

## ER Diagram

```mermaid
erDiagram
    ZONES ||--o{ PRODUCTS : "memiliki"
    PRODUCTS ||--o{ RFID_TAGS : "ditautkan ke"
    PRODUCTS ||--o{ TABLETS : "featured pada"
    TABLETS ||--o{ TABLET_RECOMMENDATIONS : "memiliki"
    TABLETS ||--o{ TABLET_CONFIG_VERSIONS : "merekam"
    PRODUCTS ||--o{ TABLET_RECOMMENDATIONS : "direkomendasikan"
    USERS ||--o{ SESSIONS : "sesi login"
    PRODUCTS {
        bigint id PK
        string sku UK
        string name
        decimal(15,2) price
        integer stock
        bigint zone_id FK
        text image
        string material
        text description
        boolean is_featured
        boolean is_discontinued
        json pim_media
        string pim_version
        json pim_payload
        json pim_image_payload
        timestamp created_at
        timestamp updated_at
    }
    ZONES {
        bigint id PK
        string name
        text description
        timestamp created_at
        timestamp updated_at
    }
    RFID_TAGS {
        bigint id PK
        string uid UK
        bigint product_id FK
        timestamp created_at
        timestamp updated_at
    }
    PRINT_RULES {
        bigint id PK
        decimal(15,2) minimum_transaction
        boolean require_membership
        boolean enabled
        timestamp created_at
        timestamp updated_at
    }
    SYNC_LOGS {
        bigint id PK
        string source
        string status
        text message
        timestamp synced_at
        timestamp created_at
        timestamp updated_at
    }
    PIM_IMPORTS {
        bigint id PK
        string batch_id UK
        string(64) checksum
        string status
        text message
        unsignedInteger attempts
        timestamp created_at
        timestamp updated_at
    }
    USERS {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
    }
    SESSIONS {
        string id PK
        bigint user_id FK
        string(45) ip_address
        text user_agent
        longText payload
        integer last_activity
    }
    TABLETS {
        bigint id PK
        string slug UK
        string name
        string location
        bigint featured_product_id FK
        string activation_code_hash
        string device_token_hash
        bigint config_version
        timestamp last_seen_at
        string media_status
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }
    TABLET_RECOMMENDATIONS {
        bigint id PK
        bigint tablet_id FK
        bigint product_id FK
        integer sort_order
        timestamp created_at
        timestamp updated_at
    }
    TABLET_CONFIG_VERSIONS {
        bigint id PK
        bigint tablet_id FK
        bigint version_number
        bigint featured_product_id FK
        json recommendation_product_ids
        timestamp created_at
        timestamp updated_at
    }
```

## Ringkasan Tabel

### Tabel Bisnis

| Tabel | Deskripsi | Kolom Utama |
|-------|-----------|-------------|
| `zones` | Area/section fisik di EIGER Digital Store | `id`, `name`, `description` |
| `products` | Entitas inti katalog produk | `id`, `sku` (unik), `name`, `price`, `stock`, `zone_id`, `image`, `material`, `description`, `is_featured`, `is_discontinued`, `pim_media`, `pim_version`, `pim_payload`, `pim_image_payload` |
| `rfid_tags` | Menautkan UID hardware RFID ke produk | `id`, `uid` (unik), `product_id` |
| `print_rules` | Aturan/kondisi fitur Print Photo | `id`, `minimum_transaction`, `require_membership`, `enabled` |
| `sync_logs` | Riwayat sinkronisasi dari sistem eksternal (CARE, PIM, Loyalty/EAC) | `id`, `source`, `status`, `message`, `synced_at` |
| `pim_imports` | Log batch import dari PIM | `id`, `batch_id` (unik), `checksum`, `status`, `message`, `attempts` |
| `tablets` | Konfigurasi dan status setiap interactive tablet | `slug`, `featured_product_id`, `config_version`, `last_seen_at`, `is_active` |
| `tablet_recommendations` | Daftar rekomendasi berurutan per tablet | `tablet_id`, `product_id`, `sort_order` |
| `tablet_config_versions` | Snapshot konfigurasi untuk audit dan rollback | `tablet_id`, `version_number`, `featured_product_id`, `recommendation_product_ids` |

### Relasi

| Relasi | Keterangan |
|--------|------------|
| `zones` 1 — N `products` | Satu zone memiliki banyak produk. `products.zone_id` nullable, `nullOnDelete` (produk tetap ada jika zone dihapus). |
| `products` 1 — N `rfid_tags` | Satu produk memiliki banyak tag RFID. `rfid_tags.product_id` nullable, `nullOnDelete` (tag tetap ada jika produk dihapus). |
| `products` 1 — N `tablets` | Satu produk dapat menjadi featured item pada beberapa tablet. |
| `tablets` N — N `products` | Setiap tablet mempunyai rekomendasi berurutan melalui `tablet_recommendations`. |
| `tablets` 1 — N `tablet_config_versions` | Setiap publikasi/rollback menyimpan snapshot konfigurasi yang immutable. |
| `users` 1 — N `sessions` | Satu user memiliki banyak sesi login. |

### Tabel Bawaan Laravel

| Tabel | Kegunaan |
|-------|----------|
| `cache`, `cache_locks` | Cache & locking cache |
| `jobs`, `job_batches`, `failed_jobs` | Antrian job |
| `password_reset_tokens` | Reset password |

## Catatan Migrasi Penting

- `rfid_tags.product_id` awalnya `NOT NULL` + `cascadeOnDelete`, kemudian diubah menjadi **nullable** + `nullOnDelete` (migrasi `2026_09_08_000001`) agar tag bisa berdiri tanpa produk.
- `products` diperkaya kolom PIM: `pim_media`, `pim_version` (migrasi `2026_09_08_190000`) dan `pim_payload`, `pim_image_payload` (migrasi `2026_09_09_120000`).
- Tidak ada relasi FK langsung ke `print_rules` dan `sync_logs` — keduanya tabel konfigurasi/log yang berdiri sendiri.

## Urutan Eksekusi Migrasi

1. `0001_01_01_000000_create_users_table` (users, password_reset_tokens, sessions)
2. `0001_01_01_000001_create_cache_table` (cache, cache_locks)
3. `0001_01_01_000002_create_jobs_table` (jobs, job_batches, failed_jobs)
4. `2026_08_04_181856_create_zones_table`
5. `2026_08_04_181857_create_products_table`
6. `2026_08_04_181857_create_rfid_tags_table`
7. `2026_08_04_181857_create_print_rules_table`
8. `2026_08_04_181858_create_sync_logs_table`
9. `2026_09_08_000001_make_product_id_nullable_on_rfid_tags_table`
10. `2026_09_08_190000_add_pim_folder_imports` (pim_imports + kolom PIM produk)
11. `2026_09_09_120000_add_pim_payload_to_products`
12. `2026_09_10_000000_create_tablets_table` (tablets + tablet_recommendations)
