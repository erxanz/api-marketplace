# REST API Marketplace

REST API untuk marketplace (users, kategori, produk, keranjang, pesanan) — **PHP 8 + PDO + MySQL/MariaDB**, tanpa framework.

## Setup

1. Import database (sudah ada isinya):

```bash
mysql -u badio -p marketplace_db < db_marketplace.sql
```

2. Sesuaikan kredensial DB di `config/config.php` jika perlu.

3. Jalankan server:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

## Test di Postman

Collection siap import: [`postman_collection.json`](postman_collection.json)

### Langkah

1. **Jalankan server** dulu:

   ```bash
   php -S 127.0.0.1:8000 -t public public/index.php
   ```

2. **Import collection** ke Postman:
   - Postman → **Import** → **Files** → pilih `postman_collection.json` → **Open**
   - Collection **API Marketplace** muncul di sidebar, dengan variabel `base_url` = `http://127.0.0.1:8000`

3. **Login dulu** (wajib untuk endpoint 🔒):
   - Buka folder **1. Auth** → jalankan **Login (jalankan dulu)**
   - Script otomatis menyimpan token ke variabel `{{token}}` (cek: icon *Environment quick look* 👁 di kanan atas)
   - Bisa ganti login: **Login Admin** / **Login Penjual** (token ikut terganti)

4. **Jalankan endpoint lain** — header `Authorization: Bearer {{token}}` sudah terisi otomatis di request yang bertanda 🔒.

### Urutan test flow yang disarankan

| No | Folder | Request | Yang dicek |
|----|--------|---------|------------|
| 1 | 0. Health | Health Check | Server hidup → `200` |
| 2 | 1. Auth | Login | Dapat `token` |
| 3 | 1. Auth | Me | Profil sesuai login |
| 4 | 2. Kategori | List Kategori | 4 kategori |
| 5 | 3. Produk | List / Cari / Detail | Filter `q`, `id_kategori` |
| 6 | 4. Keranjang | Tambah → Lihat → Ubah → Hapus | Item & `total` benar |
| 7 | 5. Pesanan | Checkout | Pesanan baru, stok produk berkurang |
| 8 | 5. Pesanan | List + Detail | Muncul pesanan tadi |
| 9 | 5. Pesanan | Update Status | `menunggu` → `diproses` |
| 10 | 6. Users | List Users | Harus login **admin** dulu |

### Test negatif (folder 7)

| Request | Ekspektasi |
|---------|------------|
| Login password salah | `401` |
| Tanpa / token palsu | `401` |
| Register data tidak valid | `422` + `errors` per field |
| Endpoint salah | `404` |
| Pembeli buka `/api/users` | `403` |
| Hategori 1 dihapus (punya produk) | `409` |
| Checkout keranjang kosong | `409` |

### Troubleshooting

| Gejala | Solusi |
|--------|--------|
| `401 Unauthorized` | Jalankan **Login** dulu; token kedaluwarsa (24 jam) → login ulang |
| `ECONNREFUSED` | Server belum jalan / salah port |
| Response `Forbidden` | Role tidak cocok (contoh: butuh admin) → login ulang sebagai admin |
| Mau lihat token sekarang | Klik **Environment quick look** 👁 → `collection → token` |

## Autentikasi

Semua endpoint bertanda 🔒 butuh header:

```
Authorization: Bearer <token>
```

Token didapat dari `POST /api/auth/login` atau `POST /api/auth/register` (berlaku 24 jam).

### Akun bawaan

| Role    | Email                      | Password    |
|---------|----------------------------|-------------|
| admin   | admin@marketplace.test     | admin123    |
| penjual | budi@marketplace.test      | penjual123  |
| penjual | andi@marketplace.test      | penjual123  |
| pembeli | siti@marketplace.test      | pembeli123  |

---

## Endpoint

### Auth

| Method | Path                  | Auth | Deskripsi |
|--------|-----------------------|------|-----------|
| POST   | `/api/auth/register`  | –    | Daftar (role: `pembeli`/`penjual`) |
| POST   | `/api/auth/login`     | –    | Login |
| GET    | `/api/auth/me`        | 🔒   | Profil user login |

### Users

| Method | Path               | Auth | Deskripsi |
|--------|--------------------|------|-----------|
| GET    | `/api/users`       | 🔒 admin | Daftar users |
| GET    | `/api/users/{id}`  | 🔒   | Detail user |
| PUT    | `/api/users/{id}`  | 🔒   | Update profile (admin bisa ubah role/status) |
| DELETE | `/api/users/{id}`  | 🔒 admin | Hapus/nonaktifkan user |

### Kategori

| Method | Path                    | Auth | Deskripsi |
|--------|-------------------------|------|-----------|
| GET    | `/api/kategori`         | –    | Daftar kategori + jumlah produk aktif |
| GET    | `/api/kategori/{id}`    | –    | Detail |
| POST   | `/api/kategori`         | 🔒   | Tambah |
| PUT    | `/api/kategori/{id}`    | 🔒   | Ubah |
| DELETE | `/api/kategori/{id}`    | 🔒   | Hapus (ditolak jika masih dipakai produk) |

### Produk

| Method | Path                      | Auth | Deskripsi |
|--------|---------------------------|------|-----------|
| GET    | `/api/produk`             | –    | List produk aktif. Query: `q`, `id_kategori`, `id_penjual`, `page`, `per_page` |
| GET    | `/api/produk/{id}`        | –    | Detail produk |
| GET    | `/api/penjual/{id}/produk`| –    | Semua produk milik penjual |
| POST   | `/api/produk`             | 🔒 penjual | Tambah produk (otomatis milik login) |
| PUT    | `/api/produk/{id}`        | 🔒 owner/admin | Ubah produk |
| DELETE | `/api/produk/{id}`        | 🔒 owner/admin | Hapus / nonaktifkan jika sudah dipakai transaksi |

### Keranjang 🔒 (pembeli)

| Method | Path                         | Deskripsi |
|--------|------------------------------|-----------|
| GET    | `/api/keranjang`             | Lihat keranjang aktif + total |
| POST   | `/api/keranjang`             | Tambah item `{id_produk, jumlah}` (gabung jika sudah ada) |
| PUT    | `/api/keranjang/{id_produk}` | Ubah jumlah `{jumlah}` |
| DELETE | `/api/keranjang/{id_produk}` | Hapus 1 item |
| DELETE | `/api/keranjang`             | Kosongkan keranjang |

### Pesanan 🔒

| Method | Path                        | Auth | Deskripsi |
|--------|-----------------------------|------|-----------|
| POST   | `/api/checkout`             | pembeli | Checkout dari keranjang → buat pesanan + kurangi stok. Body: `{alamat_pengiriman?, metode_pembayaran}` (`transfer`/`ewallet`/`cod`) |
| GET    | `/api/pesanan`              | 🔒 | List pesanan (pembeli: miliknya; penjual: yang memuat produknya; admin: semua). Query: `status` |
| GET    | `/api/pesanan/{id}`         | 🔒 | Detail + items |
| PUT    | `/api/pesanan/{id}/status`  | 🔒 | Ubah status (`menunggu`/`diproses`/`dikirim`/`selesai`/`dibatalkan`). Batalkan = stok kembali |

### Lain

| Method | Path           | Deskripsi |
|--------|----------------|-----------|
| GET    | `/api/health`  | Cek server |

---

## Contoh

```bash
# Login
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"siti@marketplace.test","password":"pembeli123"}'

# List produk
curl 'http://127.0.0.1:8000/api/produk?q=laptop&id_kategori=1&page=1'

# Tambah keranjang
curl -X POST http://127.0.0.1:8000/api/keranjang \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"id_produk":1,"jumlah":2}'

# Checkout
curl -X POST http://127.0.0.1:8000/api/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"alamat_pengiriman":"Jl. Merdeka No. 1","metode_pembayaran":"ewallet"}'
```

## Respons

Sukses:

```json
{ "success": true, "message": "...", "data": ... }
```

Gagal:

```json
{ "success": false, "message": "...", "errors": { ... } }
```

## Struktur

```
├── db_marketplace.sql
├── config/config.php          # kredensial DB + secret token
├── public/index.php           # entry point + routing
└── src/
    ├── Auth.php               # token HMAC-SHA256
    ├── Database.php           # koneksi PDO
    ├── Request.php / Response.php / Router.php
    └── Controllers/
        ├── AuthController.php
        ├── UserController.php
        ├── KategoriController.php
        ├── ProdukController.php
        ├── KeranjangController.php
        └── PesananController.php
```
