# ⚡ WP Speed Core `v1.3.0` - Panduan Instalasi, Penggunaan & Dokumentasi

> **Pengembang**: Dhany ([@leddhany](https://t.me/leddhany))  
> **Versi**: `1.3.0` (Production Ready)
> **Lisensi**: GPL v2 or later  

WP Speed Core adalah plugin optimasi performa dan keamanan WordPress modern all-in-one yang menggabungkan kontrol bloat script, caching HTML statis berkecepatan tinggi, Gzip Pre-Compression, CDN Rewriter, integrasi WP-CLI native, Asset Unloader Manager, INP Shield untuk Core Web Vitals, **Adaptive Smart Auto-Tuning Engine**, **Security Hardening**, serta **System & Diagnostic Logger** untuk pemantauan server real-time.

---

## 🚀 Fitur Utama

1. **Adaptive Auto-Tuning Engine**: Otomatis mendeteksi PHP, OPcache, JIT, Web Server, tema FSE/klasik, dan plugin aktif untuk menerapkan profil kecepatan terbaik dengan 1-klik.
2. **INP Shield & Script Controller**: Menunda eksekusi skrip berat dengan `scheduler.yield()` inspektur bertahap (chunked execution) agar Interaction to Next Paint (INP) tetap hijau (< 50ms).
3. **Auto-LCP Hero Preload & Zero CLS**: Otomatis mendeteksi gambar utama above-the-fold dan menginjeksi `fetchpriority="high"` & `loading="eager"`, serta menambahkan atribut `width`/`height` otomatis pada gambar lokal.
4. **W3C Speculation Rules API**: Navigasi halaman seketika (0ms TTFB) dengan prerender native di background browser.
5. **Static Disk HTML Cache + Gzip Pre-Compression**: Melayani halaman dalam format HTML statis super cepat beserta berkas `.html.gz` dari `/wp-content/cache/wp-speed-core/html/`.
6. **Cache Warmer & Never Cache URLs**: Memanaskan cache secara otomatis via background HTTP request dan menyediakan pengontrol pengecualian URL (wildcard `*`).
7. **CDN Asset Rewriter**: Mengubah URL aset statis (gambar, CSS, JS, font) ke domain CNAME CDN kustom secara otomatis.
8. **Asset Unloader Manager**: Antarmuka futuristik untuk mematikan CSS/JS yang tidak terpakai secara global atau per spesifik URL match.
9. **WP-CLI Native Integration**: Perintah terminal resmi `wp wpsc` (`purge`, `autotune`, `db-clean`, `status`).
10. **Security Hardening**: Proteksi log file dengan header `<?php die(); ?>`, pemblokiran Author Enumeration (`/?author=N`), penutupan REST API Users Endpoint untuk publik, dan injeksi Security HTTP Headers (`X-Frame-Options`, `X-Content-Type-Options`, dll).
11. **Smart Tag Auditor**: Mendeteksi dan memperingatkan tag analitik/tracking (GA4, GTM, Meta Pixel, Clarity) yang terpasang ganda tanpa false-positive.
12. **Plugin Overlap Arbiter**: Mendeteksi fitur yang bertabrakan dengan plugin lain (LiteSpeed, WP Rocket, Autoptimize, Smush) dan memberi rekomendasi pengaturan terbaik.
13. **Database Housekeeper & Defragmenter**: Pembersihan harian untuk revisi pos, draf otomatis, pos di kotak sampah, komentar spam, transient kadaluarsa, dan de-fragmentasi tabel (`OPTIMIZE TABLE`).
14. **System & Diagnostic Logger**: Menyimpan log diagnosa sistem ber-timezone lokal WordPress, status OPcache, operasi cache hit/miss/purge, dan riwayat Auto-Tune di dashboard admin.

---

## 📜 Changelog / Riwayat Perubahan

### `v1.3.0` (2026-08-25) - Multi-Rule Asset Unloader & Architecture Upgrade
- 📦 **Multi-Rule List Asset Unloader Architecture**:
  - Peningkatan struktur data `wpsc_disabled_assets` ke format Multi-Rule List (indexed array of rule objects). Pengguna kini bebas membuat banyak aturan terpisah (tipe JS, CSS, atau Keduanya dengan target URL match yang berbeda) untuk nama handle yang sama.
  - Penambahan opsi tipe penonaktifan `Keduanya (CSS & JS)` pada `AssetGatekeeper` dan antarmuka `AssetManagerPanel`.
  - Dukungan penayangan sampel handle bawaan WordPress (`jquery-migrate`, `wp-block-library`, `contact-form-7`, `elementor-frontend`, `woocommerce`) yang siap dikonfigurasi.
- 🎨 **Asset Unloader UI Glassmorphism & Layout Fix**:
  - Penataan antarmuka futuristik dengan perbaikan layout padding `#wpcontent` dan persembunyian `#wpfooter` untuk mencegah konten tertimpa di bagian bawah.

### `v1.2.0` (2026-08-25) - High Performance, Security & UI Enhancement
- 🛡️ **Security Hardening**:
  - Penambahan fitur pemblokiran Author Enumeration (`/?author=N`).
  - Penutupan endpoint REST API Users (`/wp/v2/users`) untuk pengunjung non-login.
  - Injeksi otomatis Security Response Headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection`, `Referrer-Policy`).
  - Pengamanan file log menggunakan format `.log.php` dengan header `<?php die(); ?>`.
- 🎨 **Asset Unloader UI Redesign**:
  - Redesain penuh antarmuka Asset Unloader Manager dengan tema Futuristic Dark Glassmorphism HUD.
  - Perbaikan layout padding `#wpcontent` dan persembunyian `#wpfooter` untuk mencegah konten tertimpa di bagian bawah.
- 🕒 **WordPress Local Timezone Logging**:
  - Logger kini mencatat timestamp menggunakan timezone lokal WordPress (`wp_date`).
- 🛠️ **Autoloader Acronym Regex Fix**:
  - Perbaikan regex autoloader agar class dengan akronim kapital berturut-turut (seperti `CLICommand`) ter-load otomatis dengan tepat.
- 🔍 **TagAuditor GA4 Regex Refinement**:
  - Mengetatkan kriteria deteksi GA4 tag untuk mencegah false positive pada kelas CSS/ID HTML biasa (seperti `g-bottom`).

### `v1.1.0` (2026-08-24) - Feature Expansion Release
- 💻 **WP-CLI Integration**:
  - Penambahan perintah terminal `wp wpsc` (`purge`, `autotune`, `db-clean`, `status`).
- 🗄️ **Static Gzip Pre-Compression & Cache Warmer**:
  - Pembuatan otomatis file `.html.gz` dan metode pemanasan cache `warm_cache()`.
- 🌐 **CDN Asset Rewriter**:
  - Penambahan module penggantian URL aset statis ke CNAME CDN kustom dengan penanganan query parameter & hash fragment.
- 🎯 **Auto Image Dimensions & CLS Safeguard**:
  - Ekstraksi otomatis dimensi `width` dan `height` pada gambar lokal.
- 🧹 **Database Table Defragmenter**:
  - Penambahan routine `OPTIMIZE TABLE` dan tombol pembersihan database manual.

### `v1.0.0` (2026-08-24) - Initial Production Release
- Peluncuran perdana WP Speed Core v1.0.0 dengan Adaptive Auto-Tuning Engine, INP Shield, Auto-LCP Priority, Speculation Rules API, Static Disk HTML Cache, Tag Auditor, Overlap Arbiter, dan System Logger.

---

## 📦 Cara Instalasi ke WordPress

### Metode 1: Upload via Dashboard WordPress (Paling Mudah)
1. Gunakan file `wp-speed-core.zip` yang telah dikemas.
2. Buka dashboard WordPress Anda &rarr; Masuk ke menu **Plugins** &rarr; **Add New (Tambah Baru)**.
3. Klik tombol **Upload Plugin (Unggah Plugin)** di bagian atas.
4. Pilih file `wp-speed-core.zip` dan klik **Install Now (Pasang Sekarang)**.
5. Setelah selesai, klik **Activate Plugin (Aktifkan Plugin)**.

### Metode 2: Menggunakan WP-CLI (Untuk Server/Developer)
Jalankan perintah berikut di terminal server WordPress Anda:
```bash
wp plugin install /path/to/wp-speed-core.zip --activate
```

---

## 📋 Persyaratan Sistem
- **WordPress**: Versi 6.2 atau yang lebih baru (Mendukung penuh WordPress 6.5+, 7.0, & v7.x).
- **PHP**: Versi 8.0, 8.1, 8.2, 8.3, atau 8.4+.
- **Web Server**: Nginx, Apache, LiteSpeed, OpenLiteSpeed, atau IIS.
- **Kontak Developer**: Telegram [@leddhany](https://t.me/leddhany)
