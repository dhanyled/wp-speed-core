# ⚡ WP Speed Core `v1.4.1` - Panduan Instalasi, Penggunaan & Dokumentasi

> **Pengembang**: Dhany ([@leddhany](https://t.me/leddhany))  
> **Versi**: `1.4.1` (Production Ready)
> **Lisensi**: GPL v2 or later  

WP Speed Core adalah plugin optimasi performa WordPress modern all-in-one yang menggabungkan kontrol bloat script, caching HTML statis berkecepatan tinggi, INP Shield untuk Core Web Vitals, **Adaptive Smart Auto-Tuning Engine**, serta **System & Diagnostic Logger** untuk pemantauan server dan audit performa real-time.

---

## 📜 Changelog / Riwayat Perubahan

### `v1.4.1` (2026-08-26) - PageSpeed API Key Support, Dual Strategy & Stability Hardening
- 🔑 **Google PageSpeed API Key Field & 429 Quota Resolution**:
  - Menambahkan kolom input konfigurasi API Key Google Cloud Console gratis di Dashboard Admin untuk membebaskan audit dari batas kuota IP hosting bersama (shared hosting pool limit 429), memberikan kuota pribadi 25.000 audit/hari.
- 📱 **Dual Strategy Switcher (Mobile & Desktop)**:
  - Navigasi tab audit instan antara Mobile dan Desktop di widget Google PageSpeed Insights Dashboard.
- 🎬 **Iframe Facade IntersectionObserver Hardening**:
  - Memperbaiki pemulihan atribut `src` dinamis pada `iframe-facade.js` saat mendekati viewport browser (`rootMargin: 200px`).
- 🧹 **Safe Database Cleaner Batch Limit**:
  - Menambahkan limitasi batching `LIMIT 500` pada penghapusan revisi pos usang di `DbCleaner` guna mencegah *MySQL table lock* pada database raksasa.
- 🛡️ **Autoloader Compound Name & Namespace Hardening**:
  - Menambahkan fallback *compound name* di autoloader PHP dan mengimpor `use WP_CLI;` di namespace root.

### `v1.4.0` (2026-08-25) - Google PageSpeed Dashboard, Facade Optimizer & 1-Click DB Cleaner
- ⚡ **Google PageSpeed Insights Dashboard Integration**:
  - Menambahkan integrasi Google PageSpeed Insights REST API v5 dengan pencatatan transient cache 12 jam, visualisasi dual gauge SVG ring, dan pelaporan metrik Core Web Vitals (LCP, FCP, CLS, INP/TBT) langsung di WP-Admin.
- 🎬 **Smart Media & Iframe Facade Optimizer**:
  - Mengganti iframe YouTube, Vimeo, dan Google Maps yang berat dengan facade placeholder ultra-ringan menggunakan `WP_HTML_Tag_Processor` native WordPress dan `IntersectionObserver` API JS (<1.5 KB).
- 🔤 **Smart Font & Resource Preconnect Optimizer**:
  - Menginjeksi tag `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>` secara otomatis dan memastikan penambahan `display=swap` pada URL Google Fonts.
- 🧹 **1-Click Database & Transient Cleaner UI**:
  - Membawa kapabilitas pembersihan database usang (post revision, expired transient, auto-draft, spam comment) dari CLI langsung ke tombol interaktif di UI Dashboard WP-Admin.


### `v1.3.1` (2026-08-25) - UI/UX Refinement & Local Disk Cache Clarity
- 🎨 **UI/UX Cache Purge Clarity**:
  - Memperjelas label tombol dan notifikasi sukses pembersihan cache pada Dashboard Admin dari sekadar "Purge Cache" menjadi `🗑️ Purge Local Disk Cache` untuk membedakan secara tegas pembersihan cache HTML statis lokal di server hosting dengan pembersihan CDN global pada Admin Bar atas.
- 🕒 **WordPress Local Timezone Logging**:
  - Logger mencatat timestamp menggunakan timezone lokal WordPress (`wp_date`).
- 🛠️ **Asset Unloader Multi-Rule Enhancement**:
  - Penyempurnaan antarmuka Multi-Rule List Asset Unloader dengan tombol interaktif `+ Tambah Baris Aturan Baru` via JavaScript real-time.

### `v1.3.0` (2026-08-25) - Multi-Rule Asset Unloader & Architecture Upgrade
- 📦 **Multi-Rule List Asset Unloader Architecture**:
  - Peningkatan struktur data `wpsc_disabled_assets` ke format Multi-Rule List (indexed array of rule objects). Pengguna kini bebas membuat banyak aturan terpisah (tipe JS, CSS, atau Keduanya dengan target URL match yang berbeda) untuk nama handle yang sama.

### `v1.2.0` (2026-08-25) - High Performance, Security & UI Enhancement
- 🛡️ **Security Hardening**:
  - Penambahan fitur pemblokiran Author Enumeration (`/?author=N`).
  - Penutupan endpoint REST API Users (`/wp/v2/users`) untuk pengunjung non-login.
  - Injeksi otomatis Security Response Headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection`, `Referrer-Policy`).
- 🌐 **CDN Rewriter & Cache Warmer**:
  - Penambahan modul CDN Rewriter, Gzip static pre-compression `.html.gz`, dan background Cache Warmer.

---

## 🚀 Fitur Utama

1. **Adaptive Auto-Tuning Engine**: Otomatis mendeteksi PHP, OPcache, JIT, Web Server, tema FSE/klasik, dan plugin aktif untuk menerapkan profil kecepatan terbaik dengan 1-klik.
2. **INP Shield & Script Controller**: Menunda eksekusi skrip berat dengan `scheduler.yield()` inspektur bertahap (chunked execution) agar Interaction to Next Paint (INP) tetap hijau (< 50ms).
3. **Auto-LCP Hero Preload & Zero CLS**: Otomatis mendeteksi gambar utama above-the-fold dan menginjeksi `fetchpriority="high"` & `loading="eager"`.
4. **W3C Speculation Rules API**: Navigasi halaman seketika (0ms TTFB) dengan prerender native di background browser.
5. **Static Disk HTML Cache**: Melayani halaman dalam format HTML statis super kecepatan dari `/wp-content/cache/wp-speed-core/html/`.
6. **Smart Tag Auditor**: Mendeteksi dan memperingatkan tag analitik/tracking (GA4, GTM, Meta Pixel, Clarity) yang terpasang ganda.
7. **Plugin Overlap Arbiter**: Mendeteksi fitur yang bertabrakan dengan plugin lain (LiteSpeed, WP Rocket, Autoptimize, Smush) dan memberi rekomendasi pengaturan terbaik.
8. **Database Housekeeper**: Pembersihan terjadwal untuk revisi pos, draf otomatis, pos di kotak sampah, komentar spam, dan transient kadaluarsa.
9. **System & Diagnostic Logger**: Menyimpan log diagnosa sistem, server software, PHP, OPcache, aktivitas pembersihan cache, dan riwayat Auto-Tune langsung di dashboard admin.

---

## 📦 Cara Instalasi ke WordPress

### Metode 1: Upload via Dashboard WordPress (Paling Mudah)
1. Gunakan file `wp-speed-core.zip` yang telah dikemas.
2. Buka dashboard WordPress Anda &rarr; Masuk ke menu **Plugins** &rarr; **Add New (Tambah Baru)**.
3. Klik tombol **Upload Plugin (Unggah Plugin)** di bagian atas.
4. Pilih file `wp-speed-core.zip` dan klik **Install Now (Pasang Sekarang)**.
5. Setelah selesai, klik **Activate Plugin (Aktifkan Plugin)**.

### Metode 2: Upload Manual via FTP / File Manager cPanel
1. Hubungkan ke hosting Anda via FTP atau cPanel File Manager.
2. Navigasikan ke direktori: `/wp-content/plugins/`.
3. Upload dan ekstrak folder plugin ke lokasi tersebut sehingga jalurnya menjadi:
   `/wp-content/plugins/wp-speed-core/`
4. Buka dashboard WordPress &rarr; Masuk ke menu **Plugins** &rarr; cari **WP Speed Core** &rarr; klik **Activate**.

### Metode 3: Menggunakan WP-CLI (Untuk Server/Developer)
Jalankan perintah berikut di terminal server WordPress Anda:
```bash
wp plugin install /path/to/wp-speed-core.zip --activate
```

---

## 🛠️ Cara Penggunaan

1. **Jalankan 1-Click Auto-Tune**:
   - Setelah plugin aktif, buka menu **Settings &rarr; WP Speed Core** di sidebar WordPress.
   - Klik tombol biru **🚀 1-Click Auto-Tune**.
   - Sistem akan memindai konfigurasi server Anda dan langsung menerapkan preset optimal yang aman tanpa merusak website.

2. **Memeriksa Status Tag & Konflik**:
   - Di halaman dashboard WP Speed Core, periksa kotak **Environment & Server Stack**.
   - Jika ada tag ganda (misal GA4 terpasang di dua tempat), kotak kuning **Tag Tracking Duplikat** akan menampilkan lokasinya.
   - Kotak biru **Smart Arbiter** akan memberi saran jika ada plugin lain yang memiliki fitur tumpang tindih.

3. **Melihat Log Diagnosa & Server**:
   - Scroll ke bagian **System & Diagnostic Logs** di dashboard.
   - Anda dapat melihat status server, versi WordPress, PHP, status OPcache, operasi cache hit/miss/purge, dan riwayat auto-tune.
   - Tersedia tombol **Kosongkan Log** jika ingin mereset log diagnosa.

4. **Membersihkan Cache**:
   - Untuk membersihkan seluruh HTML cache statis, klik tombol **🗑️ Bersihkan Cache** di kanan atas dashboard WP Speed Core.

---

## 📋 Persyaratan Sistem
- **WordPress**: Versi 6.2 atau yang lebih baru (Mendukung penuh WordPress 6.5+ & v7.x dengan `WP_HTML_Tag_Processor`).
- **PHP**: Versi 8.0, 8.1, 8.2, 8.3, atau 8.4+.
- **Web Server**: Nginx, Apache, LiteSpeed, OpenLiteSpeed, atau IIS.
- **Kontak Developer**: Telegram [@leddhany](https://t.me/leddhany)
