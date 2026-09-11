# 05 — Keputusan yang Masih Terbuka

Perlu dijawab sebelum atau saat milestone terkait. Diurutkan berdasar seberapa mahal kalau salah pilih
di belakang hari.

---

### K1 — Plugin untuk publik, atau untuk pemakaian internal? · blokir M0

Menentukan hampir semua hal lain: apakah perlu package terpisah, seberapa ketat backward compatibility,
perlu dokumentasi publik atau tidak.

- **Publik (Packagist/open source)** → wajib package terpisah, semantic versioning, README, dukungan
  multi-versi Filament. Lebih lambat, jangkauan lebih luas.
- **Internal** → boleh langsung di `app/`, jauh lebih cepat, bebas berubah sewaktu-waktu.

Rencana di dokumen ini mengasumsikan **publik**. Kalau ternyata internal, M0 dan M6 bisa dipangkas banyak.

---

### K2 — Siapa yang memakai editornya? · blokir M4

- **Developer** → boleh menampilkan nama field mentah, ekspresi, dan JSON. Editor bisa lebih sederhana.
- **Pengguna bisnis (staf keuangan/operasional)** → wajib label ramah, tanpa istilah teknis, banyak
  validasi dan pengaman. Biaya UI naik signifikan.

Jawaban ini menentukan berapa banyak usaha yang pantas dicurahkan ke editor v2.

---

### K3 — Paket PDF mana? · blokir M5

| Pilihan | Kelebihan | Kekurangan |
|---|---|---|
| `spatie/laravel-pdf` (Chrome headless) | Dukungan CSS modern paling baik, hasil paling mirip HTML | Butuh Node + Chromium di server |
| `dompdf` / `barryvdh/laravel-dompdf` | PHP murni, tanpa dependensi luar | Dukungan CSS terbatas, layout kompleks sering meleset |
| `typst` | Kualitas cetak sangat baik, cepat | Perlu binary eksternal, harus membuat renderer sendiri |

**Wajib diuji dulu** dengan `composer require … --dry-run` di PHP 8.5 sebelum diputuskan — stack ini
terlalu baru untuk berasumsi.

---

### K4 — Multi-tenant sejak awal? · blokir M2

Kalau ya, `scope()` wajib dan harus ada test isolasi tenant sejak M2. Menambahkan isolasi tenant
belakangan pada sistem yang sudah punya template tersimpan adalah pekerjaan yang menyakitkan dan rawan
bocor.

---

### K5 — Bahasa antarmuka: Indonesia, Inggris, atau keduanya? · blokir M4

Kalau plugin ditujukan publik, Inggris jadi bahasa dasar dengan berkas terjemahan Indonesia. Kalau
internal, langsung Indonesia saja. Format angka/tanggal lokal tetap diperlukan pada kedua kasus.

---

### K6 — Sudah dicek ada plugin serupa? · sebaiknya sebelum M0

Sebelum membangun, telusuri katalog plugin Filament dan Packagist untuk report builder. Kalau sudah ada
yang mendekati, dua pilihan biasanya lebih hemat daripada membangun dari nol: berkontribusi ke sana,
atau membangun di atasnya. Belum saya periksa — perlu akses pencarian web.

---

### K7 — Sumber data selain Eloquent? · pengaruhi desain M2

Perlukah mendukung query builder mentah, view database, stored procedure, atau API eksternal? Kalau
kelak perlu, `DataSource` sebaiknya dirancang sebagai antarmuka (interface) sejak awal, bukan kelas
yang terikat Eloquent — perubahan ini murah sekarang, mahal nanti.

---

### K8 — Siapa yang boleh mengakses panel admin? · blokir sebelum deploy

Ditemukan saat M0. Filament menolak akses panel dengan 403 di environment non-`local` kecuali model
`User` mengimplementasikan `FilamentUser`. Kontraknya sudah dipasang di `app/Models/User.php`, tapi
`canAccessPanel()` untuk sementara mengembalikan `true` — **artinya setiap user terdaftar bisa masuk
panel admin.**

Aman untuk pengembangan, tidak aman untuk produksi. Perlu diputuskan sebelum deploy pertama:

- kolom `is_admin` sederhana, atau
- sistem peran/izin (mis. spatie/laravel-permission), atau
- pembatasan berdasar domain email untuk pemakaian internal

Keputusan ini juga memengaruhi K4 (multi-tenant) dan otorisasi per template di M1.
