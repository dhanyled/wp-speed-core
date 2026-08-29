# ⚡ WP Speed Core `v1.5.0` - Panduan Instalasi & Penggunaan

> **Pengembang**: Dhany ([@leddhany](https://t.me/leddhany))  
> **Versi**: `1.5.0` (Production Ready)  
> **Lisensi**: GPL v2 or later  

WP Speed Core adalah plugin akselerasi performa WordPress modern all-in-one AI-Native yang menggabungkan caching HTML statis berkecepatan tinggi, **Model Context Protocol (MCP) AI Server**, **Interactive Performance Checklist**, **Smart Contextual Asset Unloader**, INP Shield untuk Core Web Vitals, **Instant Debug / Bypass Mode (`?nowpsc=1`)**, **Adaptive Smart Auto-Tuning Engine**, Google PageSpeed Insights Lab integration, serta **System & Diagnostic Logger** untuk pemantauan server dan audit performa real-time.

---

## 🚀 Fitur Utama

1. **AI Model Context Protocol (MCP) Server**: Integrasi protokol AI standar terbuka (`/wp-json/wpsc/v1/mcp`) untuk menghubungkan Claude Desktop, Cursor IDE, Antigravity, dan ChatGPT secara langsung guna diagnosis otomatis dan eksekusi optimasi.
2. **Interactive Performance Checklist & Scorecard**: Audit kepatuhan kecepatan real-time (14+ kriteria kepatuhan Core Web Vitals) dengan dial gauge skor kesehatan persentase.
3. **Adaptive Auto-Tuning Engine**: Otomatis mendeteksi PHP, OPcache, JIT, Web Server, tema FSE/klasik, dan plugin aktif untuk menerapkan profil kecepatan terbaik dengan 1-klik.
4. **INP Shield & Script Controller**: Menunda eksekusi skrip berat dengan `scheduler.yield()` inspektur bertahap (chunked execution) agar Interaction to Next Paint (INP) tetap hijau (< 50ms).
5. **Smart Contextual Asset Unloader**: Mematikan file CSS & JavaScript yang tidak terpakai secara kontekstual (*Global*, *Homepage*, *Single Posts*, *Pages*, *WooCommerce*, atau *Custom Regex*) dengan dukungan pengecualian URL/ID (*Exceptions*) tanpa merusak cache HTML statis.
6. **Instant Debug / Bypass Mode (`?nowpsc=1`)**: Parameter URL instan untuk mem-bypass seluruh optimasi & cache statis untuk keperluan testing performa A/B dan debugging tanpa menonaktifkan plugin.
7. **Frontend Admin Bar Quick HUD**: Bar menu terintegrasi di topbar WordPress untuk memantau status cache, beralih ke mode bypass 1-klik, dan membersihkan cache halaman aktif seketika.
8. **Auto-LCP Hero Preload & Zero CLS**: Otomatis mendeteksi gambar utama above-the-fold dan menginjeksi `fetchpriority="high"` & `loading="eager"`.
9. **W3C Speculation Rules API**: Navigasi halaman seketika (0ms TTFB) dengan prerender native di background browser.
10. **Static Disk HTML Cache**: Melayani halaman dalam format HTML statis super cepat dari `/wp-content/cache/wp-speed-core/html/`.
11. **Selective Inline Bloat Suppressor**: Menghapus duotone SVG filters, classic theme styles, dan inline global styles Gutenberg yang tidak terpakai.
12. **Smart Tag Auditor**: Mendeteksi dan memperingatkan tag analitik/tracking (GA4, GTM, Meta Pixel, Clarity) yang terpasang ganda.
13. **Plugin Overlap Arbiter**: Mendeteksi fitur yang bertabrakan dengan plugin lain (LiteSpeed, WP Rocket, Autoptimize, Smush) dan memberi rekomendasi pengaturan terbaik.
14. **Database Housekeeper**: Pembersihan terjadwal untuk revisi pos, draf otomatis, pos di kotak sampah, komentar spam, dan transient kadaluarsa.
15. **System & Diagnostic Logger**: Menyimpan log diagnosa sistem, server software, PHP, OPcache, aktivitas pembersihan cache, dan riwayat Auto-Tune langsung di dashboard admin.

---

## 📊 Komparasi vs Kompetitor

| Fitur / Arsitektur | WP Speed Core `v1.5.0` | WP Rocket | Perfmatters | WP Shifty | NitroPack |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Static Disk HTML Cache** | ✅ Yes (Zero DB Query) | ✅ Yes | ❌ (Butuh Caching Eksternal) | ❌ (Butuh Caching Eksternal) | ✅ (Cloud Service) |
| **INP Shield (`scheduler.yield`)** | ✅ Yes (Sub-50ms) | ⚠️ (Delay Standar) | ⚠️ (Delay Standar) | ⚠️ (Delay Standar) | ⚠️ (Proprietary JS) |
| **W3C Speculation Rules** | ✅ Yes (Native W3C) | ⚠️ (Instant Page JS) | ⚠️ (Instant Page JS) | ❌ No | ❌ No |
| **Contextual Asset Unloader** | ✅ Yes (with Exceptions) | ⚠️ (Unused CSS only) | ✅ Yes | ✅ Yes | ⚠️ (Blackbox) |
| **Model Context Protocol (MCP)**| ✅ Yes (Built-in Server) | ✅ Yes (v3.23+) | ❌ No | ❌ No | ❌ No |
| **1-Click Adaptive Auto-Tune** | ✅ Yes (Heuristic Scanner) | ❌ (Setup Manual) | ❌ (Setup Manual) | ❌ (Setup Manual) | ⚠️ (Preset Cloud) |
| **Duplicate Tag Auditor** | ✅ Yes (GA4/GTM/Pixel) | ❌ No | ❌ No | ❌ No | ❌ No |
| **Instant Bypass URL Parameter**| ✅ `?nowpsc=1` | ✅ `?nowprocket` | ✅ `?nowp` | ✅ `?noshifty` | ⚠️ (Cookie Bypass) |

---

## 🔑 Panduan Google PageSpeed Insights API Key (Gratis)

1. Kunjungi [Google PageSpeed Insights API Quickstart](https://developers.google.com/speed/docs/insights/v5/get-started).
2. Klik tombol **Get a Key** &rarr; Pilih atau buat project di Google Cloud Console.
3. Salin **API Key** ke tab **Settings** di dashboard WP Speed Core.
4. Nikmati kuota **25.000 audit per hari** secara gratis tanpa batasan IP shared hosting.

---

## 📦 Cara Instalasi ke WordPress

### Metode 1: Upload via Dashboard WordPress (Paling Mudah)
1. Unduh atau gunakan file `wp-speed-core.zip`.
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
   - Buka menu **Settings &rarr; WP Speed Core**.
   - Klik tombol **1-Click Auto-Tune**.
   - Sistem akan memindai konfigurasi server Anda dan langsung menerapkan preset optimal yang aman tanpa merusak website.

2. **Periksa Performance Checklist**:
   - Masuk ke tab **Performance Checklist** di dashboard WP Speed Core.
   - Evaluasi skor kepatuhan Core Web Vitals dan ikuti rekomendasi optimasi 1-klik.

3. **Koneksikan AI Assistant via MCP**:
   - Masuk ke tab **AI & MCP Protocol**.
   - Klik **Generate Secret Token** untuk mendapatkan API key Anda.
   - Salin cuplikan konfigurasi `claude_desktop_config.json` ke Claude Desktop atau Cursor IDE Anda untuk mengelola kecepatan web lewat instruksi bahasa alami.

4. **Mengelola Asset Unloader (Matikan CSS / JS yang Tidak Terpakai)**:
   - Buka menu **Settings &rarr; WPSC Asset Unloader**.
   - Tentukan target skenario (*Global*, *Homepage*, *Single Posts*, *WooCommerce Only*, atau *Non-Shop*) dan isi kolom *Pengecualian* jika dibutuhkan.

5. **Menguji Kecepatan Sebelum vs Sesudah (Bypass Mode)**:
   - Kunjungi URL mana pun di website Anda dan tambahkan parameter `?nowpsc=1` (misal `https://yoursite.com/?nowpsc=1`).
   - Atau gunakan menu dropdown **WP Speed Core &rarr; Test Tanpa Optimasi** di Admin Bar atas.

---

## 📋 Persyaratan Sistem
- **WordPress**: Versi 6.2 atau yang lebih baru (Mendukung penuh WordPress 6.5+ & v7.x dengan `WP_HTML_Tag_Processor`).
- **PHP**: Versi 8.0, 8.1, 8.2, 8.3, atau 8.4+.
- **Web Server**: Nginx, Apache, LiteSpeed, OpenLiteSpeed, atau IIS.
- **Changelog**: Lihat berkas [CHANGELOG.md](file:///D:/Dhany/Plugin_flyingpress_perfmatter/CHANGELOG.md) untuk riwayat rilis lengkap.
- **Kontak Developer**: Telegram [@leddhany](https://t.me/leddhany)
