# Main App — E-commerce React UI

Aplikasi e-commerce berbasis React + Vite yang menghubungkan UI dengan beberapa microservice.

## Fitur UI
- Menampilkan daftar produk beserta rating.
- Melihat detail produk dan ulasan.
- Menambahkan produk ke keranjang.
- Mengubah jumlah dan menghapus item keranjang.
- Memilih User ID untuk membedakan keranjang dan ulasan.

## Microservice
| Service | Port | Fungsi |
|---|---:|---|
| Product Service | 3000 | Data produk |
| Cart Service | 3001 | Keranjang belanja |
| Review Service | 3002 | Rating dan ulasan |
| Order Service | 3003 | Layanan order pada stack Docker |

Database berjalan melalui Docker Compose.

## Menjalankan aplikasi
1. Jalankan Docker Desktop.
2. Buka terminal pada folder utama repository.
3. Jalankan seluruh container:
   ```bash
   docker compose up -d --build
   docker compose ps
   ```
4. Buka terminal kedua, masuk ke folder UI:
   ```bash
   cd ecommerce-ui
   npm install
   npm run dev
   ```
5. Buka alamat yang ditampilkan Vite, biasanya `http://localhost:5173`.

## Build production
Dari folder `ecommerce-ui`:
```bash
npm run lint
npm run build
npm run preview -- --host 0.0.0.0 --port 4173
```
Kemudian buka `http://localhost:4173`.

## Alur demo
1. Tampilkan daftar produk dan rating.
2. Buka salah satu detail produk.
3. Tunjukkan deskripsi, rating, dan ulasan.
4. Tambahkan produk ke keranjang.
5. Buka halaman keranjang.
6. Ubah jumlah produk, lalu tunjukkan fitur hapus item.
7. Jelaskan bahwa UI berkomunikasi dengan Product, Cart, dan Review Service melalui API.

## Pemeriksaan
- `docker compose ps` untuk melihat status container.
- `http://localhost:3000/products` untuk memeriksa Product Service.
- `http://localhost:3001/carts/1` untuk memeriksa Cart Service.
- `http://localhost:3002/health` untuk memeriksa Review Service.
- `http://localhost:3003/health` untuk memeriksa Order Service.
