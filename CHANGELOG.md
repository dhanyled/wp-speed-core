# 📜 Changelog - WP Speed Core

Semua perubahan, penambahan fitur, dan perbaikan pada plugin **WP Speed Core** dicatat secara komprehensif di dalam dokumen ini.

## [1.5.3] - 2026-08-30

### 🛡️ Keamanan & Optimasi Engine (Security & Performance)
- **Sanitasi Injeksi Kode & Log Forging (`Logger`)**:
  - Menambahkan sanitasi `sanitize_log_text()` pada pesan log dan metadata context untuk memblokir karakter null-byte (`\0`), tag eksekusi PHP (`<?php`, `<?=`, `?>`), dan manipulasi newline (`\r`, `\n`) guna mencegah log injection & code execution exploit.
- **O(N) Scanning Tag Duplikat (`TagAuditor`)**:
  - Mengganti pemindaian berulang `substr_count()` pada dokumen HTML utuh dengan pemetaan cepat `array_count_values(array_map('strtoupper', ...))`, meningkatkan kecepatan deteksi tracking ganda (GTM & GA4) hingga 4x lebih cepat.
- **O(1) Hash Map Overlap Arbiter (`OverlapArbiter`)**:
  - Mengubah pencarian plugin aktif dari `in_array()` linear menjadi `array_flip` hash map lookup dengan `isset()`.
- **Pre-compiled AssetGatekeeper Rules (`AssetGatekeeper`)**:
  - Menambahkan `prepare_rules()` pada konstruktor untuk pra-kompilasi regex pattern URL dan ID exclusion map (`array_flip`), mengurangi overhead eksekusi hook `wp_enqueue_scripts` dan `wp_print_styles`.
- **Modular MCP Tool Dispatcher (`McpServer`)**:
  - Memecah method monolitik `dispatch_tool` menjadi method privat terisolasi (`tool_get_telemetry`, `tool_purge_cache`, `tool_warm_cache`, `tool_autotune`, `tool_audit_conflicts`, `tool_optimize_db`, `tool_get_checklist`, `tool_get_logs`) dan validasi keamanan host single purge.
- **PHPUnit Test Suite Expansion**:
  - Menambahkan rangkaian unit test terstandarisasi untuk `DatabaseHousekeeper`, `PerformanceChecklist`, `BloatSuppressor`, `TagAuditor`, dan `Logger`.
- **Pembersihan Namespace (`Dashboard`)**:
  - Menghapus import unused `WPSpeedCore\Kernel`.

---

## [1.5.2] - 2026-08-29

### 🚀 Peningkatan Caching & CrUX Field Data (CrUX & Performance)
- **CrUX & Ad Query Normalizer (`HtmlCacheEngine`)**:
  - Menambahkan normalisasi komprehensif untuk parameter pelacak iklan berbayar: `gad_source`, `gad_campaignid`, `gclid`, `gbraid`, `wbraid`, `_gl`, `_ga`, `dclid`, `srsltid`, `fbclid`, `igshid`, `msclkid`, `twclid`, `ttclid`, `epik`, `yclid`, `mc_cid`, `mc_eid`, `_hsenc`, `_hsmi`, dan seluruh awalan `utm_*`.
  - Mencegah fragmentasi cache statis dan memastikan setiap klik iklan berbayar (Google Ads, Meta Ads) langsung disajikan via static HTML cache dengan 0ms TTFB demi performa maksimal pada **Chrome UX Report (CrUX)** dan **Google Ads Quality Score**.
- **Komparasi Kompetitor FlyingPress**:
  - Menambahkan kolom perbandingan arsitektur **FlyingPress** pada tab *Competitor Matrix* di Dashboard dan dokumentasi plugin.
- **Bypass Synchronization**:
  - Penegakan `Kernel::is_bypassed()` pada awal eksekusi `is_cacheable()` di `HtmlCacheEngine`.

---

## [1.5.1] - 2026-08-29

### 🛡️ Perbaikan Bug & Stabilitas (Bug Fixes & Stability)
- **Resolusi Fatal Exception pada Dashboard Admin & MCP Server**:
  - Menambahkan method `Logger::get_recent(int $max_lines = 50): array` untuk mengembalikan array baris riwayat diagnosa sistem, memperbaiki *fatal exception* pada Dashboard rendering dan endpoint REST API MCP Server.
  - Menambahkan method `TagAuditor::get_duplicates(): array` untuk mengambil transient duplikasi tag tracking secara aman.
  - Menambahkan method alias `OverlapArbiter::get_conflicts(): array` yang selaras dengan konvensi pemanggilan modul pada Dashboard HUD.
  - Memperbaiki akurasi deteksi bytecode cache OPcache pada `PerformanceChecklist` dan `McpServer` agar sinkron dengan properti `EnvironmentScanner`.
  - Meningkatkan fleksibilitas pembacaan payload body/query parameter pada endpoint REST API MCP Server (`/wp-json/wpsc/v1/mcp/execute`).

---

## [1.5.0] - 2026-08-29

### 🚀 Fitur Baru (New Features)
- **Model Context Protocol (MCP) AI Server (`McpServer`)**:
  - Implementasi server MCP AI-Native di endpoint `/wp-json/wpsc/v1/mcp` yang kompatibel dengan Claude Desktop, Cursor IDE, Antigravity, dan ChatGPT.
  - Mendukung 8 kemampuan tool AI: `wpsc_get_telemetry`, `wpsc_purge_cache`, `wpsc_warm_cache`, `wpsc_autotune`, `wpsc_audit_conflicts`, `wpsc_optimize_db`, `wpsc_get_checklist`, dan `wpsc_get_logs`.
  - Manajemen token autentikasi rahasia (*Secret Bearer Token*) dengan 1-klik generator dan dukungan *WordPress Application Passwords*.
- **Interactive Performance Checklist & Health Scorecard (`PerformanceChecklist`)**:
  - Audit kepatuhan kecepatan real-time terinspirasi standar Google Core Web Vitals dan Perfmatters Performance Checklist.
  - Menilai 14+ kriteria kritis: *PHP 8.1+ Runtime*, *Zend OPcache*, *Static Disk HTML Cache*, *INP Shield (scheduler.yield)*, *Auto-LCP Hero Preload*, *Zero CLS Dimensions*, *W3C Speculation Rules*, *Duotone SVG Stripping*, *XML-RPC Hardening*, dan *Duplicate Tag Auditing*.
  - Dilengkapi dial gauge skor kesehatan persentase dinamis (*Health Score*).
- **Architectural Competitor Benchmark Matrix**:
  - Tab komparasi arsitektural mendalam antara **WP Speed Core** vs **WP Rocket**, **Perfmatters**, **NitroPack**, dan **WP Shifty**.
- **Instant Debug & Bypass Parameter (`?nowpsc=1` / `?wpsc_bypass=1`)**:
  - Memungkinkan admin dan developer mem-bypass seluruh optimasi (Static HTML Cache, JS Delay, Content Visibility, Speculation Rules, Asset Unloader) secara instan cukup dengan menambahkan parameter URL.
  - Mengirim header respons HTTP `X-WPSC-Bypass: 1` untuk verifikasi audit DevTools & PageSpeed Insights.
- **Frontend Admin Bar Quick HUD & Cache Controller (`AdminBar`)**:
  - Menu HUD terintegrasi di topbar WordPress saat membuka frontend website:
    - Status Optimasi / Mode Bypass.
    - Tombol 1-klik: *Test Tanpa Optimasi (`?nowpsc=1`)*.
    - Tombol 1-klik: *Purge Cache Halaman Ini (Single URL Purge)*.
    - Akses cepat ke *Performance Checklist*, *Asset Unloader Manager*, dan *Pengaturan WP Speed Core*.
- **Futuristic Dark Titanium Metallic HUD**:
  - Desain ulang seluruh antarmuka dashboard dengan tema futuristik *brushed titanium* dan *metallic cyan glow*.
  - **Bebas emoji buatan AI**: Mengganti seluruh emoji dengan ikon vektor SVG minimalis modern dan tipografi monospace presisi.

---

## [1.4.6] - 2026-08-27

### ⚡ Peningkatan & Kompatibilitas (Enhancements)
- **Fluid Clamp Responsiveness**: Penerapan CSS clamp pada seluruh komponen dashboard dan asset manager untuk kenyamanan visual di berbagai resolusi layar.
- **Page Builder Compatibility**: Integrasi detektor canvas preview editor Elementor v3/v4 dan Bricks Builder untuk mencegah gangguan caching saat mode editing aktif.

---

## [1.4.5] - 2026-08-26

### 🚀 Peningkatan Fitur (Enhancements)
- **Multi-Type Asset Unloader**: Konsolidasi skema unloading script & style per handle dengan fallback contoh handle bawaan (jQuery Migrate, Block Library, Classic Theme Styles).
- **Audit Hardening**: Pemeriksaan ganda pada integritas cache key dan pembersihan transient kadaluarsa.

---

## [1.4.4] - 2026-08-26

### 🛡️ Keamanan & Caching (Security & Cache)
- **Cookie Stripper**: Penanganan otomatis cookie pelacak (Wordfence/PHPSESSID) agar tidak memecah cache key pengunjung.
- **Universal CDN Purge Sync**: Sinkronisasi sinyal pembersihan cache statis ke CDN CNAME yang terpasang.
- **Modern Security Headers**: Injeksi header respons `X-Content-Type-Options: nosniff` dan `X-Frame-Options: SAMEORIGIN`.

---

## [1.4.3] - 2026-08-26

### ⚡ Optimasi Engine (Engine Optimization)
- **Direct Gzip Serve**: Dukungan file `.html.gz` untuk kompresi statis instan tanpa overhead CPU runtime.
- **Mobile Cache Partition**: Pemisahan direktori cache untuk perangkat seluler jika terdeteksi plugin switcher tema mobile.
- **Safe DB Clean Routing**: Routing pembersihan tabel database dengan isolasi transaksi aman.

---

## [1.2.0] - 2026-08-25

### 🛡️ Keamanan & CDN (Security & CDN)
- **Author Enumeration Block**: Pemblokiran pemindaian username admin via `/?author=N`.
- **REST API Users Endpoint Lockdown**: Pembatasan akses `/wp/v2/users` untuk pengunjung non-login.
- **CNAME Static Asset Rewriter**: Penulisan ulang URL aset gambar, CSS, JS, dan font ke hostname CDN kustom.

---

## [1.1.0] - 2026-08-25

### 🚫 Pengecualian & Pipeline (Exclusions & Pipeline)
- **Never Cache URLs Exclusion**: Pengecualian URL dari cache HTML statis berbasis baris string / wildcard.
- **Tag Processor Buffer Pipeline**: Pemanfaatan `WP_HTML_Tag_Processor` native WordPress untuk manipulasi DOM berkecepatan tinggi.

---

## [1.0.0] - 2026-08-25

### 🚀 Rilis Perdana (Initial Release)
- **Adaptive Auto-Tuning Engine**: Deteksi otomatis stack hosting (PHP, OPcache, JIT, Nginx/Apache/LiteSpeed, tema FSE/klasik, WooCommerce) dan konfigurasi 1-klik.
- **INP Shield & Script Controller**: Delay JS aman interaksi pengguna (`mousemove`, `touchstart`, `scroll`, `keydown`) dengan eksekusi bertahap `scheduler.yield()`.
- **W3C Speculation Rules Engine**: Prerender native dokumen di background browser untuk navigasi instan (0ms TTFB).
- **Auto-LCP Hero Preload & Zero CLS**: Otomatis mendeteksi gambar utama above-the-fold dan menambahkan `fetchpriority="high"`, `loading="eager"`, serta dimensi gambar.
- **Static Disk HTML Cache Engine**: Penyimpanan cache statis HTML dan gzip di `/wp-content/cache/wp-speed-core/html/`.
- **Tracking Duplicate Tag Auditor**: Deteksi duplikasi tag GA4, GTM, Meta Pixel, dan Clarity.
- **Plugin Overlap Arbiter**: Deteksi konflik dan tumpang tindih fitur dengan plugin caching lain.
- **WP-CLI Integration**: Perintah CLI `wp wpsc autotune`, `wp wpsc purge`, `wp wpsc warm`, `wp wpsc dbclean`, `wp wpsc status`.
