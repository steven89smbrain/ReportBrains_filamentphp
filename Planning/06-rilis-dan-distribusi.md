# 06 — Rilis & Distribusi (Anystack)

Keputusan: jual lewat **Anystack Exclusive** ($0/bulan, potongan 15% per penjualan), package di
**repository GitHub terpisah**, rilis pertama **v1.0.0**.

Kode sudah siap. Langkah di bawah melibatkan akun, pembayaran, dan repository baru — **harus Anda
kerjakan sendiri**; saya tidak bisa membuat akun atau mengelola pembayaran.

## Fakta Anystack yang sudah dicek

- Repository yang dihubungkan **wajib punya `composer.json` di root** — itu sebabnya package perlu
  repo sendiri.
- Rilis diambil dari **GitHub release / tag bersemver** (mis. `1.0.0`), bisa otomatis dengan
  *Auto Publish*.
- Pembeli memasang lewat `https://{product-identifier}.composer.sh`, login HTTP basic:
  **username = email pembeli, password = license key** (username `unlock` bila lisensi tanpa email).

Sumber: <https://anystack.sh/docs/guides/private-php-packages>,
<https://anystack.sh/docs/integrations/php>.

## Checklist rilis v1.0.0

### 1. Repository package

- [ ] Buat repository GitHub **privat**, saran nama: `filament-report-designer`.
- [ ] Pisahkan folder package beserta riwayat commit-nya, lalu push ke repo baru:

```bash
git push git@github.com:steven89smbrain/filament-report-designer.git \
  "$(git subtree split --prefix=packages/filament-report-designer)":refs/heads/main
```

`git subtree split` mencetak commit yang berisi folder package saja (dengan riwayatnya), dan commit
itu langsung di-push sebagai `main` repo package. Tidak ada branch lokal yang dibuat, jadi perintah
yang sama bisa diulang di setiap rilis.

### 2. Produk di Anystack

- [ ] Buat akun Anystack, pilih paket **Exclusive**.
- [ ] Buat produk: tipe **PHP**, integrasi distribusi **Composer**, hubungkan repo GitHub di atas,
      aktifkan **Auto Publish**.
- [ ] Catat **product identifier**. README package dan `Documentation/01-installation.md` memakai
      `report-designer.composer.sh` — **ganti di kedua tempat bila identifier Anda berbeda.**
- [ ] Atur harga, halaman produk, dan email pembelian.

### 3. Rilis

- [ ] Di repo package, buat GitHub release dengan tag **`v1.0.0`** (isi catatan dari `CHANGELOG.md`).
- [ ] Pastikan Anystack mengimpor rilisnya.

### 4. Uji sebagai pembeli

- [ ] Buat lisensi uji di Anystack.
- [ ] Di aplikasi Laravel + Filament yang **baru**, ikuti persis README package: tambah repository,
      simpan kredensial, `composer require`, migrate, daftarkan plugin.
- [ ] Buka Report Templates, buat report, ekspor PDF.

## Rilis berikutnya

1. Kembangkan dan uji di repo ini seperti biasa.
2. Perbarui `packages/filament-report-designer/CHANGELOG.md`.
3. Jalankan lagi perintah push di langkah 1. `git subtree split` menghasilkan riwayat yang konsisten,
   jadi push berikutnya hanya menambah commit baru.
4. Buat GitHub release dengan tag versi baru. Perubahan yang merusak = versi mayor baru.

## Yang sudah diverifikasi sebelum rilis

- Package dipasang di **aplikasi Laravel + Filament yang benar-benar baru** (disalin, bukan symlink),
  lalu plugin terdaftar, migrasi jalan, dan `report:render` menghasilkan Markdown, CSV, XLSX, dan PDF.
