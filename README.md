# ⚡ WP Speed Core `v1.6.6` - Panduan Instalasi & Penggunaan

> **Pengembang**: Dhany ([@leddhany](https://t.me/leddhany))  
> **Versi**: `1.6.6` (Production Ready)  
> **Lisensi**: GPL v2 or later  

WP Speed Core adalah plugin akselerasi performa WordPress modern all-in-one AI-Native yang menggabungkan caching HTML statis berkecepatan tinggi dengan kompresi GZIP, **Zero Rate-Limit GitHub Updater**, **Model Context Protocol (MCP) AI Server (10 Tools)**, **Cloudflare Edge Cache API Sync**, **1-Click Settings Importer (WP Rocket & Perfmatters)**, **Granular WooCommerce Cache Invalidation**, **Safe HTML Minification**, **Auto Image Dimensions (Zero CLS)**, **Resource Hints (Preconnect/DNS-Prefetch)**, **Interactive Performance Checklist**, **Smart Contextual Asset Unloader**, INP Shield untuk Core Web Vitals, **Instant Debug / Bypass Mode (`?nowpsc=1`)**, **Adaptive Smart Auto-Tuning Engine**, Google PageSpeed Insights Lab integration, serta **System & Diagnostic Logger** untuk pemantauan server dan audit performa real-time.

---

## 🚀 Fitur Utama

1. **Zero Rate-Limit GitHub Updater**: Arsitektur pembaruan otomatis andal dari GitHub tanpa terblokir kuota IP limit (60 req/jam). Menggunakan metadata CDN `version.json`, dual hook transient WordPress (`site_transient_update_plugins` & `pre_set_site_transient_update_plugins`), serta tombol 1-Click Update langsung di `wp-admin/plugins.php`.
2. **Safe Disk HTML Minification**: Pemadatan HTML pada cache statis dengan pembersihan komentar dan spasi berlebih secara aman tanpa merusak blok `<pre>`, `<code>`, `<textarea>`, conditional IE, maupun tag noscript.
3. **Auto Image Dimensions (Zero CLS)**: Otomatis mendeteksi dan menginjeksi atribut `width` dan `height` yang hilang pada tag `<img>` melalui metadata media library atau pemeriksaan file lokal untuk melenyapkan Cumulative Layout Shift (CLS).
4. **W3C Speculation Rules & Resource Hints Engine**: Navigasi instan (0ms TTFB) dengan prerender W3C di latar browser dan percepatan koneksi origin eksternal via `<link rel="preconnect">` & `<link rel="dns-prefetch">`.
5. **AI Model Context Protocol (MCP) Server (10 Tools)**: Integrasi protokol AI standar terbuka (`/wp-json/wpsc/v1/mcp`) untuk menghubungkan Claude Desktop, Cursor IDE, Antigravity, dan ChatGPT secara langsung guna diagnosis otomatis, eksekusi optimasi, Cloudflare edge purge, dan migrasi konfigurasi.
6. **Cloudflare CDN Edge Cache API Sync**: Sinkronisasi pembersihan cache otomatis langsung ke edge network Cloudflare via Cloudflare API v4 setiap kali cache WordPress dibersihkan (Purge All maupun Single URL).
7. **1-Click Settings Importer (Migration Hub)**: Deteksi otomatis dan migrasi 1-klik untuk mengimpor konfigurasi dari WP Rocket, Perfmatters, dan LiteSpeed Cache tanpa setup manual yang rumit.
8. **Granular WooCommerce & FSE Cache Invalidation**: Membersihkan cache produk, kategori, dan shop archive otomatis saat harga atau stok berganti tanpa menghapus cache seluruh website; auto-purge saat template FSE dan menu navigasi diperbarui.
9. **Nonce-Aware Form Lifetime Protection**: Mendeteksi input nonce pada halaman formulir dinamis dan membatasi masa aktif cache agar pengunjung formulir tidak mengalami error *invalid nonce*.
10. **XML Sitemap Cache Preloader**: Memanaskan cache secara otomatis dengan mengekstrak URL dari XML sitemap WordPress Core (`wp-sitemap.xml`) atau sitemap SEO.
11. **Interactive Performance Checklist & Scorecard**: Audit kepatuhan kecepatan real-time (16+ kriteria kepatuhan Core Web Vitals) dengan dial gauge skor kesehatan persentase.
12. **Adaptive Auto-Tuning Engine**: Otomatis mendeteksi PHP, OPcache, JIT, Web Server, tema FSE/klasik, dan plugin aktif untuk menerapkan profil kecepatan terbaik dengan 1-klik.
13. **INP Shield & Script Controller**: Menunda eksekusi skrip berat dengan `scheduler.yield()` inspektur bertahap (chunked execution) agar Interaction to Next Paint (INP) tetap hijau (< 50ms).
14. **Smart Contextual Asset Unloader**: Mematikan file CSS & JavaScript yang tidak terpakai secara kontekstual (*Global*, *Homepage*, *Single Posts*, *Pages*, *WooCommerce*, atau *Custom Regex*) dengan dukungan pengecualian URL/ID (*Exceptions*) tanpa merusak cache HTML statis.
15. **Instant Debug / Bypass Mode (`?nowpsc=1`)**: Parameter URL instan untuk mem-bypass seluruh optimasi & cache statis untuk keperluan testing performa A/B dan debugging tanpa menonaktifkan plugin.
16. **Frontend Admin Bar Quick HUD & Diagnostic Headers**: Bar menu terintegrasi di topbar WordPress untuk memantau status cache, beralih ke mode bypass 1-klik, membersihkan cache halaman aktif seketika, dan header diagnostik `X-WPSC-Cache: HIT/MISS/BYPASS`.
17. **Auto-LCP Hero Preload & Video Throttling**: Otomatis mendeteksi gambar utama above-the-fold (`fetchpriority="high"`) dan mengatur video self-hosted ke `preload="none"` guna menghemat bandwidth mobile.
18. **Static Disk HTML Cache with GZIP**: Melayani halaman dalam format HTML statis super cepat dan terkompresi GZIP dari `/wp-content/cache/wp-speed-core/html/`.
19. **Selective Inline Bloat Suppressor**: Menghapus duotone SVG filters, classic theme styles, dan inline global styles Gutenberg yang tidak terpakai.
20. **Smart Tag Auditor**: Mendeteksi dan memperingatkan tag analitik/tracking (GA4, GTM, Meta Pixel, Clarity) yang terpasang ganda.
21. **Plugin Overlap Arbiter**: Mendeteksi fitur yang bertabrakan dengan plugin lain (LiteSpeed, WP Rocket, Autoptimize, Smush) dan memberi rekomendasi pengaturan terbaik.
22. **Database Housekeeper**: Pembersihan terjadwal untuk revisi pos, draf otomatis, pos di kotak sampah, komentar spam, dan transient kadaluarsa.
23. **System & Diagnostic Logger**: Menyimpan log diagnosa sistem, server software, PHP, OPcache, aktivitas pembersihan cache, dan riwayat Auto-Tune langsung di dashboard admin.

---

## 📊 Komparasi vs Kompetitor

| Fitur / Arsitektur | WP Speed Core `v1.6.6` | FlyingPress | WP Rocket | Perfmatters | xSpeed Cache | NitroPack |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Static Disk HTML Cache** | ✅ Yes (Zero DB Query) | ✅ Yes (Disk Cache) | ✅ Yes | ❌ (Butuh Caching Eksternal) | ✅ Yes | ✅ (Cloud Service) |
| **GZIP Pre-compression** | ✅ Yes (`.html.gz`) | ✅ Yes | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| **INP Shield (`scheduler.yield`)** | ✅ Yes (Sub-50ms) | ⚠️ (Delay Standar) | ⚠️ (Delay Standar) | ⚠️ (Delay Standar) | ⚠️ (Delay Standar) | ⚠️ (Proprietary JS) |
| **CrUX & Ad Query Normalizer** | ✅ Yes (`gad_source`, `gclid`, dsb.) | ✅ Yes | ⚠️ (Dasar) | ❌ No | ❌ No | ⚠️ (Cloud Proxy) |
| **Cloudflare Edge Cache Sync** | ✅ Yes (API v4) | ⚠️ (Addon) | ✅ Yes | ❌ No | ✅ Yes | ⚠️ (Cloud Native) |
| **1-Click Settings Importer** | ✅ Yes (WPR/PM/LSC) | ❌ No | ❌ No | ❌ No | ✅ Yes | ❌ No |
| **Granular WooCommerce Purge**| ✅ Yes (Price/Stock) | ✅ Yes | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| **W3C Speculation Rules** | ✅ Yes (Native W3C) | ⚠️ (Link Preload JS) | ⚠️ (Instant Page JS) | ⚠️ (Instant Page JS) | ❌ No | ❌ No |
| **Contextual Asset Unloader** | ✅ Yes (with Exceptions) | ⚠️ (Unused CSS) | ⚠️ (Unused CSS only) | ✅ Yes | ❌ No | ⚠️ (Blackbox) |
| **Model Context Protocol (MCP)**| ✅ Yes (10 AI Tools) | ❌ No | ✅ Yes (v3.23+) | ❌ No | ❌ No | ❌ No |
| **1-Click Adaptive Auto-Tune** | ✅ Yes (Heuristic Scanner) | ⚠️ (Preset Config) | ❌ (Setup Manual) | ❌ (Setup Manual) | ❌ (Setup Manual) | ⚠️ (Preset Cloud) |
| **Duplicate Tag Auditor** | ✅ Yes (GA4/GTM/Pixel) | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No |
| **Instant Bypass URL Parameter**| ✅ `?nowpsc=1` | ✅ `?flying_press_bypass` | ✅ `?nowprocket` | ✅ `?nowp` | ✅ `?noshifty` | ⚠️ (Cookie Bypass) |

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
