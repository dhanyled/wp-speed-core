# 📜 Changelog - WP Speed Core

Semua perubahan, penambahan fitur, dan perbaikan pada plugin **WP Speed Core** dicatat secara komprehensif di dalam dokumen ini.

## [1.6.6] - 2026-10-12

### 🚀 Zero Rate-Limit GitHub Updater & Competitor Parity Engine
- **Zero Rate-Limit GitHub Updater Architecture**:
  - Mengatasi kendala IP rate limit GitHub API (60 req/jam) pada shared hosting WordPress yang menyebabkan notifikasi pembaruan di `wp-admin/plugins.php` gagal muncul.
  - Mengimplementasikan endpoint primer CDN-backed `version.json` melalui `raw.githubusercontent.com` tanpa batasan kuota API.
  - Mendaftarkan filter ganda `site_transient_update_plugins` (read) dan `pre_set_site_transient_update_plugins` (write/cron update trigger) agar pembaruan latar belakang WordPress dan tombol 1-Click Update berjalan mulus.
  - Menurunkan default TTL cache transient rilis dari 12 jam menjadi 3 jam serta mendukung bypass instan melalui parameter `force-check=1` dan tombol manual "Check Update" di Dashboard.
- **Safe Disk HTML Minification (Disk Cache Save)**:
  - Menambahkan metode pemadatan HTML cerdas `minify_html()` pada `HtmlCacheEngine` sebelum berkas statis `.html` dan `.html.gz` ditulis ke disk cache.
  - Menghapus komentar HTML non-esensial dan menormalkan whitespace berlebih antar-tag tanpa merusak blok terlindungi (`<pre>`, `<code>`, `<textarea>`), conditional IE (`[if ...]`), maupun blok noscript.
  - Dilengkapi kontrol toggle pengaturan di Dashboard Tab 5 (`wpsc_html_minify`).
- **Auto Missing Image Dimensions (Zero Cumulative Layout Shift / CLS)**:
  - Menambahkan inspeksi dimensi gambar pada `MediaController`: otomatis mendeteksi tag `<img>` yang tidak memiliki atribut `width` dan `height`.
  - Menggunakan fast-path pencarian metadata attachment WordPress via class `wp-image-{id}` serta fallback resolusi berkas fisik lokal untuk menginjeksi dimensi asli gambar dan meniadakan pergeseran tata letak (CLS).
- **Resource Hints Engine (Preconnect & DNS-Prefetch)**:
  - Menambahkan metode `inject_resource_hints()` pada `SpeculationEngine` yang diinjeksi pada hook `wp_head` prioritas awal.
  - Mengizinkan administrator mendaftarkan domain/origin aset pihak ketiga (seperti Google Fonts, CDNs, pelacak analitik) untuk mempercepat handshake TCP dan TLS DNS sebelum permintaan aset dieksekusi.
- **Self-Hosted Video Lazy / Preload Throttling**:
  - Memperbarui `MediaFacadeOptimizer` untuk mendeteksi tag `<video>` self-hosted dan otomatis menetapkan atribut `preload="none"`, mencegah browser mengunduh puluhan megabyte video secara prematur di perangkat mobile.
- **Diagnostic Cache Headers (`X-WPSC-Cache`)**:
  - Menambahkan header respons HTTP diagnostik `X-WPSC-Cache: HIT` (dilayani dari cache statis), `X-WPSC-Cache: MISS` (halaman baru yang disimpan ke cache), dan `X-WPSC-Cache: BYPASS` (melewati cache karena login, bypass mode, atau query dinamis) untuk mempermudah audit via DevTools dan curl.

---

## [1.6.5] - 2026-10-12

### 🛡️ Engine Buffer Inversion Fix, Universal Bypass & Architecture Hardening
- **Output Buffer Inversion Fix (Critical)**:
  - Mengubah prioritas hook `template_redirect` pada `HtmlCacheEngine::capture()` dari `9999` menjadi `0`. Dengan pembalikan ini, buffer cache HTML menjadi buffer paling luar (*outermost*), menjamin seluruh transformasi inner buffer (`ScriptController`, `MediaController`, `CdnRewriter`, `FontOptimizer`, `MediaFacadeOptimizer`) selesai diproses ke dalam HTML sebelum berkas statis `.html` dan `.html.gz` ditulis ke disk.
- **Enforcement Universal Bypass Mode (`?nowpsc=1`)**:
  - Menyematkan validasi `Kernel::is_bypassed()` pada seluruh sub-modul pengoptimal (`ScriptController`, `MediaController`, `StyleController`, `FontController`, `FontOptimizer`, `SpeculationEngine`, `CdnRewriter`, `MediaFacadeOptimizer`). Mode bypass kini secara konsisten menonaktifkan seluruh kompresi dan manipulasi DOM untuk keperluan debugging dan verifikasi visual murni.
- **WordPress 5.9+ Duotone SVG Bloat Stripping**:
  - Menambahkan fitur `strip_duotone_svg` pada `BloatSuppressor`, `Bootstrap::DEFAULTS`, `AdaptiveTuner`, dan `MigrationManager` untuk menghapus tumpukan inline SVG filter duotone (`wp_global_styles_render_svg_filters`) dari `wp_body_open` / header.
- **Database Housekeeper Stats & MCP Return Alignment**:
  - Mengubah metode `DatabaseHousekeeper::maintain(): array` agar mengembalikan data statistik (revisions, drafts, trash, spam, transients, tables) yang akurat ke caller, memperbaiki kembalian `null` pada MCP Server tool `wpsc_optimize_db`.
- **Query Parameter Cache Purge & Warmer Feed Sanitization**:
  - Memperluas `HtmlCacheEngine::purge_url()` agar menghapus berkas cache berbasis hash query-string selain path statis.
  - Menghapus URL RSS feed dari `warm_cache()` karena feed memiliki header `no-cache` dan tidak disimpan di disk HTML cache.
- **Single URL Purge Delegation**:
  - Mengarahkan aksi pembersihan single URL di `Dashboard::actions()` dan `McpServer::tool_purge_cache()` langsung ke `HtmlCacheEngine::purge_url()`, memastikan pembersihan mobile cache file dan pemanggilan Cloudflare Edge CDN purge API dieksekusi secara terkoordinasi.
- **Asset Manager Rule Targeting & Persistence**:
  - Memperbaiki `AssetManagerPanel` dan UI form JavaScript agar aturan unloader mendukung target kontekstual (`everywhere`, `frontpage`, `posts`, `pages`, `shop_only`, `non_shop`, `url_match`) serta pengecualian ID/URL.
- **Granular Core Engine Settings UI (Dashboard Tab 5)**:
  - Menyediakan form kontrol lengkap di Tab Settings untuk mengonfigurasi HTML Cache & TTL, JS Delay Execution & Defer, W3C Speculation Rules & Eagerness, Media Hero Priority & Video Facade, serta Bloat Suppressor & Heartbeat lengkap dengan sistem notifikasi alert interaktif.
- **Strict GitHub Updater Asset Matching**:
  - Memperketat regex asset matcher pada `GitHubUpdater` untuk memprioritaskan arsip distribusi resmi `wp-speed-core.zip` dan mengabaikan arsip tester/parsial.

---

## [1.6.4] - 2026-09-05

### 🛡️ Kernel Guardrail & Fault-Isolated Boot Architecture (Hotfix)
- **Granular Kernel Guardrail (`register_safe`)**:
  - Mengimplementasikan mekanisme *Fault-Isolation* berbasis `try-catch (\Throwable)` pada setiap modul internal kernel (`Kernel::register_safe()`).
  - Jika salah satu modul gagal inisialisasi karena dependensi lingkungan, database, atau galat berkas, modul terkait dinonaktifkan secara aman dan dicatat ke log diagnostik tanpa mematikan modul lain dan tanpa memicu *White Screen of Death* (WSOD).
  - Menampilkan notifikasi visual admin yang informatif (`admin_notices`) bagi Administrator tanpa mengganggu tampilan halaman WordPress.
- **Top-Level Lifecycle Guardrail**:
  - Membungkus pemanggilan `Kernel::launch()`, `Bootstrap::activate()`, dan `Bootstrap::deactivate()` dengan blok perlindungan `try-catch (\Throwable)` di level root plugin `wp-speed-core.php`.
- **Critical Typo Fix**:
  - Memperbaiki konstanta pengecekan direct-access pada `includes/optimization/class-database-housekeeper.php` dari typo `ABSP@Lr` kembali menjadi `ABSPATH`, mengatasi masalah penghentian paksa (`exit;`) seketika saat aktivasi modul database.

---

## [1.6.3] - 2026-09-05

### 🛡️ Boot Hardening & Release Packaging Resilience (Hotfix)
- **Defensive Kernel Booting (White Screen Prevention)**:
  - Mengisolasi inisialisasi `GitHubUpdater` di `Kernel::boot_engine()` menggunakan proteksi `class_exists(Engine\GitHubUpdater::class)`.
  - Mencegah *Fatal Critical Error* pada seluruh situs jika proses ekstraksi pembaruan zip oleh WordPress terputus atau mengalami keterlambatan penulisan file ke disk server.
- **Strict Whitelist ZIP Distribution Standard**:
  - Menghentikan pembuatan arsip rilis zip liar dan menerapkan whitelist folder inti (`assets`, `includes`, `tests`, `wp-content`, `wp-speed-core.php`, metadata).
  - Menjamin struktur direktori `includes/engine/` selalu utuh dan konsisten saat diekstrak oleh WordPress Upgrader.
- **Workflow Rules Update**:
  - Menambahkan bab standar rilis *Release Packaging & GitHub Updater Resilience Protocol* di `WORKFLOW_RULES.md`.

---

## [1.6.2] - 2026-09-05

### 🐞 Perbaikan Bug & Stabilitas Kode (Maintenance Release)
- **Unit Test Suite Fix**:
  - Menghapus karakter UTF-8 BOM pada 7 file test (`CLICommandTest`, `DbCleanerTest`, `LoggerTest`, `PerformanceChecklistTest`, `TagAuditorTest`, `BloatSuppressorTest`, `FontControllerTest`) untuk memulihkan eksekusi pengujian otomatis PHPUnit.
- **Database Housekeeper Optimization**:
  - Memperbaiki `trim_transients()` untuk menghapus kunci `_transient_timeout_*` sekaligus kunci data `_transient_*`, mencegah penumpukan data yatim (*orphaned transients*) di tabel `wp_options`.
  - Memperbaiki `trim_revisions()` agar menggunakan `wp_delete_post_revision()` untuk membersihkan meta revisi (*postmeta*) secara tuntas.
- **GitHub Updater Hook Registration**:
  - Mengeliminasi pendaftaran filter ganda pada `check_update()` di `GitHubUpdater` untuk mengoptimalkan performa pemrosesan transient update plugins.
- **HTML Cache Engine & Security Improvements**:
  - Memperketat validasi host domain pada `purge_url()` agar penghapusan cache terisolasi ke domain situs aktif (`home_url()`).
  - Mengaktifkan verifikasi sertifikat SSL bawaan (`sslverify = true`) pada fitur pemanasan cache (`warm_cache()`) dengan kustomisasi filter `wpsc_warm_cache_sslverify`.

---

## [1.6.1] - 2026-09-05

### 🚀 Fitur Baru: Native GitHub Plugin Updater & Release Sync (`GitHubUpdater`)
- **Pembaruan Manual Langsung dari Menu WordPress**:
  - Mengintegrasikan WordPress hook `pre_set_site_transient_update_plugins` dan `plugins_api`.
  - Di halaman `wp-admin > Plugins`, WordPress akan secara otomatis memunculkan notifikasi resmi jika terdapat rilis baru di repositori GitHub (`dhanyled/wp-speed-core`).
  - Administrator cukup mengklik tautan **"Update now" (Perbarui sekarang)** untuk mengunduh, mengekstrak, dan memperbarui plugin secara langsung tanpa FTP atau re-upload manual.
  - Tautan **"View version details"** menampilkan pop-up modal WordPress native lengkap dengan deskripsi dan changelog dari GitHub Releases.
- **Dukungan Pembaruan Otomatis (Auto-Updates)**:
  - Sepenuhnya kompatibel dengan fitur native *Enable auto-updates* WordPress 5.5+.
  - WordPress Background Cron (`wp_version_check`) dapat memperbarui plugin secara otomatis saat rilis baru diluncurkan.
- **Transient Caching & Rate-Limit Shield (12 Jam)**:
  - Menyimpan informasi rilis dari GitHub API di WordPress Transient selama 12 jam untuk mencegah pembatasan kuota (*rate limiting*).
  - Menyediakan tombol manual **"↻ Cek Update"** di header Dashboard WP Speed Core untuk pengecekan instan kapan saja.
- **Post-Install Directory Sanitization (`upgrader_post_install`)**:
  - Menjamin folder ekstraksi plugin selalu bernama `wp-speed-core/` sehingga tidak terjadi duplikasi folder atau plugin dinonaktifkan pasca pembaruan.
- **Status Versi di Telemetry HUD**:
  - Header Dashboard menampilkan badge versi terkini (`v1.6.1`), badge status *"Versi Terbaru"*, serta banner alert interaktif jika versi baru tersedia.
- **PHPUnit Test Coverage**:
  - Menambahkan unit test suite `GitHubUpdaterTest` di `tests/Unit/Engine/GitHubUpdaterTest.php`.

---

## [1.6.0] - 2026-09-05

### 🚀 Fitur Baru & Peningkatan Performa (Major Release)
- **Cloudflare CDN Edge Cache API Sync (`CloudflarePurger`)**:
  - Mengintegrasikan Cloudflare API v4 ke dalam engine pembersihan cache.
  - Setiap pembersihan cache (baik Purge All maupun Single Page) otomatis mengirim sinyal pembersihan ke edge CDN Cloudflare secara aman dan non-blocking.
  - Ditambahkan form konfigurasi API Token, Zone ID, dan tombol verifikasi koneksi (*Test Connection*) di tab Settings Dashboard.
- **1-Click Settings Importer & Migration Hub (`MigrationManager`)**:
  - Otomatis mendeteksi keberadaan konfigurasi dari plugin performa sebelumnya (**WP Rocket**, **Perfmatters**, dan **LiteSpeed Cache**).
  - Memungkinkan import 1-klik untuk seluruh aturan caching, JavaScript delay & defer, media lazy loading, bloat cleaning, dan CDN URLs ke WP Speed Core.
- **Granular WooCommerce & FSE Cache Invalidation (`HtmlCacheEngine`)**:
  - Pembersihan cache selektif pada produk, variasi, kategori, tag, dan arsip toko saat harga atau stok berganti tanpa menghapus cache seluruh website.
  - Mencegah serangan *cache-wipe* dari ulasan pengunjung anonim (ulasan hanya membersihkan halaman produk setelah disetujui).
  - Invalidasi cache otomatis saat template Full Site Editing (FSE), template parts, global styles, dan menu navigasi diperbarui.
- **Nonce & Dynamic Form Lifetime Awareness (`HtmlCacheEngine`)**:
  - Mendeteksi formulir yang membawa WordPress nonces atau token form (`_wpnonce`, `woocommerce-login-nonce`, `wpcf7-nonce`).
  - Membatasi masa simpan cache halaman formulir maksimal 10 jam untuk mencegah error *invalid nonce* / *link expired* pada pengunjung anonim.
- **XML Sitemap Cache Preloader (`HtmlCacheEngine`)**:
  - Meningkatkan algoritma pemanasan cache (*Cache Warmer*) untuk mengekstrak dan mem-preload URL dari XML sitemap standar (`wp-sitemap.xml`, `sitemap_index.xml`, `sitemap.xml`).
- **Ekspansi AI Model Context Protocol (10 Tools) (`McpServer`)**:
  - Menambahkan 2 tool MCP baru: `wpsc_sync_cloudflare` (pembersihan edge CDN Cloudflare via AI) dan `wpsc_migrate_settings` (deteksi dan migrasi konfigurasi via AI).
- **Interactive Performance Checklist Update (`PerformanceChecklist`)**:
  - Menambahkan kriteria deteksi *Persistent Object Cache (Redis/Memcached)* dan *Cloudflare Edge Cache API Sync*.
- **PHPUnit Test Expansion**:
  - Menambahkan test suite baru `CloudflarePurgerTest` dan `MigrationManagerTest`.

---

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
