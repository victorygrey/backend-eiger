# 📖 Panduan Lengkap Integrasi & Pemanggilan API PIM Simulator & CMS EIGER

Dokumentasi ini merangkum seluruh endpoint API, format payload 1:1 sesuai dokumen resmi *"Continuous Doc PIM - Article Publish To Channel"*, serta cara pemanggilannya melalui **Postman**, **cURL**, dan antarmuka **CMS Backend EIGER**.

---

## 📑 Daftar Isi
1. [Ringkasan Alamat Server (Host & Port)](#1-ringkasan-alamat-server)
2. [Koleksi Endpoint PIM Simulator (Port 8001)](#2-koleksi-endpoint-pim-simulator-port-8001)
   - [Product Detail Payload (1:1 Dokumen)](#a-product-detail-payload-11-dokumen)
   - [Product Image Payload (Media to Channel)](#b-product-image-payload-media-to-channel)
   - [Daftar Status & Channel](#c-daftar-status--channel-katalog)
3. [Koleksi Endpoint CMS Backend EIGER (Port 8000)](#3-koleksi-endpoint-cms-backend-eiger-port-8000)
   - [Catalog Lookup (PIM + CARE Omni)](#a-catalog-lookup-admin-pim--care)
   - [Public API Kiosk AI Fit & Go (Virtual Try-On)](#b-public-api-kiosk-ai-fit--go)
   - [Public API Table Expedition (RFID Smart Table)](#c-public-api-table-expedition-rfid-smart-table)
4. [Cara Import Postman Collection (Siap Pakai)](#4-cara-import-postman-collection-siap-pakai)
5. [Contoh Lengkap JSON Payload 1:1 Artikel 910012408](#5-contoh-lengkap-json-payload-11-artikel-910012408)

---

## 1. Ringkasan Alamat Server

| Komponen | Alamat Lokal TrueNAS | Alamat Domain Publik (Ngrok) | Autentikasi |
|---|---|---|---|
| **CMS Backend EIGER** | `http://192.168.18.31:8000` | `https://cause-accompany-matchless.ngrok-free.dev` | Admin Session / Public Kiosk |
| **PIM Simulator** | `http://192.168.18.31:8001` | Sesuai tunnel / LAN internal | Publik (No Auth) |
| **CARE Simulator** | `http://192.168.18.31:8002` | Sesuai tunnel / LAN internal | Header `x-server-key` |

---

## 2. Koleksi Endpoint PIM Simulator (Port 8001)

Endpoint ini langsung dilayani oleh PIM API Simulator di TrueNAS tanpa memerlukan login.

### A. Product Detail Payload (1:1 Dokumen)
Mengambil master data spesifikasi lengkap artikel generic (9 digit).

* **Method**: `GET`
* **URL**:
  ```text
  http://192.168.18.31:8001/api/articles/910012408/product-payload
  ```
* **Headers**:
  | Key | Value |
  |---|---|
  | `Accept` | `application/json` |

* **Contoh cURL**:
  ```bash
  curl -X GET "http://192.168.18.31:8001/api/articles/910012408/product-payload" -H "Accept: application/json"
  ```

---

### B. Product Image Payload (Media to Channel)
Mengambil mapping gambar generic dan gambar khusus varian produk.

* **Method**: `GET`
* **URL**:
  ```text
  http://192.168.18.31:8001/api/articles/910012408/image-payload
  ```
* **Headers**:
  | Key | Value |
  |---|---|
  | `Accept` | `application/json` |

* **Contoh cURL**:
  ```bash
  curl -X GET "http://192.168.18.31:8001/api/articles/910012408/image-payload" -H "Accept: application/json"
  ```

---

### C. Daftar Status & Channel Katalog
* **Cek Status Simulator**: `GET http://192.168.18.31:8001/api/simulator/state`
* **Daftar Artikel Siap Publish**: `GET http://192.168.18.31:8001/api/articles/publish-list`
* **Daftar Channel Integrasi**: `GET http://192.168.18.31:8001/api/articles/channel-list`

---

## 3. Koleksi Endpoint CMS Backend EIGER (Port 8000)

### A. Catalog Lookup Admin (PIM + CARE)
Endpoint internal yang digunakan oleh tombol **"Tarik Data PIM & CARE"** di halaman Tambah/Edit Produk.

* **Method**: `GET`
* **URL Lokal**:
  ```text
  http://192.168.18.31:8000/admin/products/catalog-lookup?code=910012408
  ```
* **URL Ngrok**:
  ```text
  https://cause-accompany-matchless.ngrok-free.dev/admin/products/catalog-lookup?code=910012408
  ```
* **Headers**:
  | Key | Value |
  |---|---|
  | `Accept` | `application/json` |
  | `Cookie` | `eiger_session=...; XSRF-TOKEN=...` *(Diperlukan)* |

> 💡 **Cara Mendapatkan Cookie di Postman**:
> Lakukan login terlebih dahulu di Postman:
> * **Method**: `POST`
> * **URL**: `http://192.168.18.31:8000/login`
> * **Body (x-www-form-urlencoded)**:
>   * `email`: `superadmin@eigeradventure.com`
>   * `password`: `password`
>
> Postman akan secara otomatis menyimpan cookie session untuk request selanjutnya.

---

### B. Public API Kiosk AI Fit & Go
API publik yang dikonsumsi oleh Kiosk Virtual Try-On (tanpa login).

#### 1. Cari Produk (Search)
* **Method**: `GET`
* **URL**:
  ```text
  http://192.168.18.31:8000/api/v1/fit-and-go/search?q=910012408
  ```
* **Headers**: `Accept: application/json`

#### 2. Ambil Katalog Produk Fit & Go Berdasarkan Aktivitas / Kategori
* **Method**: `GET`
* **URL**:
  ```text
  http://192.168.18.31:8000/api/v1/fit-and-go/products?activity=mountaineering&limit=15
  ```

---

### C. Public API Table Expedition (RFID Smart Table)
API untuk membaca spesifikasi teknis dan teknologi produk ketika diletakkan di atas meja RFID.

* **Method**: `POST`
* **URL**:
  ```text
  http://192.168.18.31:8000/api/v1/table-expedition/scan
  ```
* **Headers**:
  | Key | Value |
  |---|---|
  | `Content-Type` | `application/json` |
  | `Accept` | `application/json` |
* **Body (JSON)**:
  ```json
  {
    "rfid_tag": "E2801160600002123456789A"
  }
  ```

---

## 4. Cara Import Postman Collection (Siap Pakai)

File Postman Collection v2.1 telah disiapkan:
📁 `d:\LAPTOP FAIZAL\_Project\backend-eiger\EIGER-PIM-CARE-Postman-Collection.json`

### Langkah Import:
1. Buka aplikasi **Postman**.
2. Klik tombol **Import** di pojok kiri atas.
3. Drag & drop file `EIGER-PIM-CARE-Postman-Collection.json`.
4. Seluruh request di atas (beserta environment variable) langsung muncul di sidebar Postman dan siap dijalankan dengan sekali klik!

---

## 5. Contoh Lengkap JSON Payload 1:1 Artikel 910012408

Berikut adalah struktur baku response dari `GET /api/articles/910012408/product-payload`:

```json
{
  "generic": "910012408",
  "name": "ACROSS 1.6 WINDPROOF V3",
  "mainImage": "https://storage.eigeradventure.com/assets/articles/910012408/main_image_910012408.jpg",
  "weight": 450,
  "variant": [
    {
      "sku": "910012408001",
      "name": "ACROSS 1.6 WINDPROOF V3 - BLK - M",
      "color": "BLK",
      "size": "M",
      "moq": "1",
      "ecmsku": "910012408001",
      "customAttributes": []
    },
    {
      "sku": "910012408002",
      "name": "ACROSS 1.6 WINDPROOF V3 - BLK - L",
      "color": "BLK",
      "size": "L",
      "moq": "1",
      "ecmsku": "910012408002",
      "customAttributes": []
    }
  ],
  "media": [
    {
      "attributeCode": "SIZE_CHART",
      "name": "Size Chart",
      "files": [
        {
          "value": "https://storage.eigeradventure.com/assets/articles/910012408/size_chart.jpg",
          "description": "Apparel Size Chart"
        }
      ]
    }
  ],
  "customAtributes": [
    {
      "attributeCode": "short_description",
      "value": "Jaket windproof tangguh untuk hiking dan berkendara."
    },
    {
      "attributeCode": "long_description",
      "value": "<p>Jaket ini dilengkapi ventilasi punggung dan saku fungsional.</p>"
    },
    {
      "attributeCode": "tag_product",
      "value": "Jaket EIGER, Windproof, Riding, Outdoor"
    },
    {
      "attributeCode": "gender",
      "value": "Adult Unisex"
    },
    {
      "attributeCode": "dimension",
      "value": "5X15X10"
    },
    {
      "attributeCode": "category",
      "value": "A"
    },
    {
      "attributeCode": "product_group",
      "value": "A01"
    },
    {
      "attributeCode": "activity",
      "value": "A03008"
    },
    {
      "attributeCode": "waterproof",
      "value": "Water Repellent"
    },
    {
      "attributeCode": "breathability",
      "value": "Moderate Breathable"
    }
  ],
  "technology": [
    {
      "id": "721e7845-f027-4663-bd4f-055db98516d2",
      "name": "TROPIC REPELLENT",
      "description": "Menahan air atau cipratan air intensitas ringan untuk sementara waktu.",
      "image": "https://storage.eigeradventure.com/assets/articles/technologies/tropic_repellent.jpg"
    },
    {
      "id": "96996847-b214-4112-a1b4-21e1069ec6d6",
      "name": "TROPIC WINDBLOCK",
      "description": "Mencegah terpaan angin menembus jaket dan menjaga tubuh tetap hangat.",
      "image": "https://storage.eigeradventure.com/assets/articles/technologies/tropic_windblock.jpg"
    },
    {
      "id": "71cb290e-ee84-4fe1-ba76-2f63f5454fa1",
      "name": "TROPIC VENT",
      "description": "Ventilasi pada bagian punggung untuk sirkulasi udara lebih baik.",
      "image": "https://storage.eigeradventure.com/assets/articles/technologies/tropic_vent.jpg"
    }
  ],
  "activity": [
    {
      "id": "6a67f139-4458-4505-b0b3-6789f2a7a408",
      "name": "Mountaineering",
      "description": "Hiking, trekking, climbing",
      "selected": false,
      "rating": 0,
      "desc_rating": null
    },
    {
      "id": "9d905151-54c3-4217-a068-d06efd0d0277",
      "name": "Wind Resistance",
      "description": "Tahan terpaan angin untuk berkendara harian dan outdoor",
      "selected": true,
      "rating": 4,
      "desc_rating": "4 of 5"
    },
    {
      "id": "b216962f-d656-427f-94ad-73bc289d0092",
      "name": "Water Resistance",
      "description": "Ketahanan air ringan",
      "selected": true,
      "rating": 3,
      "desc_rating": "3 of 5"
    }
  ],
  "specification": [
    {
      "code": "PRODUCT_LENGTH",
      "name": "Product Length",
      "value": "15"
    },
    {
      "code": "PRODUCT_WIDTH",
      "name": "Product Width",
      "value": "10"
    },
    {
      "code": "PRODUCT_HEIGHT",
      "name": "Product Height",
      "value": "5"
    },
    {
      "code": "PRODUCT_WEIGHT",
      "name": "Product Weight",
      "value": "450"
    }
  ]
}
```
