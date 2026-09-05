# ⚡ WP Speed Core - Antigravity Agent Workflow & Engineering Rules

> **Referensi**: Mengadopsi prinsip-prinsip rigorous engineering dari [mattpocock/skills](https://github.com/mattpocock/skills) yang diselaraskan secara spesifik dengan arsitektur **Antigravity CLI (AGY)** dan **Antigravity Skills System**.

---

## 1. 🎯 Relevansi Matt Pocock Skills terhadap Antigravity

Repo `mattpocock/skills` berfokus pada **Agent Grounding**, **Anti-Vibe-Coding (Anti-Halusinasi)**, **Test-Driven Discipline**, dan **Interactive Alignment (Grill-Me)**. Dalam ekosistem **Antigravity**, prinsip-prinsip ini diwujudkan melalui:

| Konsep Matt Pocock | Integrasi di Antigravity | Implementasi di WP Speed Core |
| :--- | :--- | :--- |
| **`grill-me`** | Mengajukan pertanyaan klarifikasi sebelum menulis kode arsitektur besar (`ask_question` / slash command `/grill-me`). | Digunakan saat menentukan batas agresivitas JS delay, level Speculation Rules, dan kebijakan purge cache. |
| **`anti-vibe-coding`** | Menolak asumsi tidak berdasar, wajib memvalidasi API resmi, dan memverifikasi integritas file pasca-edit. | Wajib menggunakan `WP_HTML_Tag_Processor` (WP 6.2+) alih-alih regex DOM parsing rapuh. |
| **`phased-refactoring`** | Memecah perubahan besar menjadi modul-modul independen yang dapat diuji satu per satu. | Pembagian arsitektur ke dalam Service Container singleton (`Kernel`) dan 9 modul terisolasi. |
| **`tdd-discipline`** | Validasi sintaks, tipe data strict (`declare(strict_types=1)`), dan verifikasi nol error. | Audit UTF-8, sanitasi SQL, sanitasi HTML escaping, proteksi CSRF nonce, dan logging diagnosa. |

---

## 2. 🛡️ Security & Hardening Protocols

1. **CSRF & Access Control**:
   - Seluruh mutasi state admin (Auto-Tune, Purge Cache, Clear Logs) wajib divalidasi dengan `check_admin_referer()` dan `current_user_can('manage_options')`.
   - Method `render()` wajib memblokir unauthorized user dengan `wp_die(esc_html__('Akses ditolak.', 'wp-speed-core'))`.

2. **Cross-Site Scripting (XSS) Prevention**:
   - Semua output variabel dinamis wajib di-escape menggunakan `esc_html()`, `esc_attr()`, `esc_textarea()`, atau `wp_json_encode(..., JSON_UNESCAPED_SLASHES)`.

3. **SQL Injection Prevention**:
   - Seluruh query ke `$wpdb` wajib dibungkus oleh `$wpdb->prepare()`.
   - Menggunakan wildcard escaping `$wpdb->esc_like()` untuk pembersihan transient.

4. **Directory & Log File Hardening**:
   - Direktori cache dan log (`/wp-content/cache/wp-speed-core/logs/`) dilindungi oleh `.htaccess` (`Deny from all`) dan `index.php` kosong untuk mencegah akses langsung via URL browser.
   - Rotasi otomatis berkas log (maksimal 1 MB) untuk mencegah disk bloat.

5. **Regex & DoS Hardening**:
   - Menghindari catastrophic backtracking pada pattern regex tag auditor.
   - Menggunakan limit ukuran buffer (minimal 200 karakter) sebelum memproses payload HTML.

---

## 3. ⚡ Core Web Vitals & INP Shield Standard

1. **INP Yielding Standard**:
   - Skrip yang dieksekusi secara deferred tidak boleh memblokir main thread lebih dari 50ms per frame.
   - Wajib menggunakan `scheduler.yield()` native dengan fallback `setTimeout(resolve, 0)`.
   - Menjaga sequential dependency order skrip eksternal dengan Promise event handler.

2. **LCP Hero Auto-Prioritization**:
   - Gambar pertama dalam viewport above-the-fold otomatis mendapatkan atribut `fetchpriority="high"` dan `loading="eager"`.

---

## 4. 🏛️ Module Architecture & Boot Order

```mermaid
graph TD
    A["wp-speed-core.php"] -->|plugins_loaded| B["Kernel::launch"]
    B --> C["Logger Engine"]
    B --> D["EnvironmentScanner"]
    D --> E["AdaptiveTuner"]
    D --> F["OverlapArbiter"]
    D --> G["TagAuditor"]
    B --> H["Optimization Modules"]
    H --> H1["BloatSuppressor"]
    H --> H2["ScriptController"]
    H --> H3["StyleController"]
    H --> H4["MediaController"]
    H --> H5["FontController"]
    H --> H6["SpeculationEngine"]
    H --> H7["AssetGatekeeper"]
    H --> H8["DatabaseHousekeeper"]
    B --> I["HtmlCacheEngine"]
    B --> J["Admin Dashboard & Logs Viewer"]
```

---

## 5. 📦 Release Packaging & GitHub Updater Resilience Protocol

Untuk mencegah terulangnya **Critical Error** saat user melakukan pembaruan plugin (update) dari dashboard WordPress, setiap rilis wajib mematuhi aturan berikut:

1. **Defensive Autoloading & Boot Isolation**:
   - Seluruh inisialisasi modul engine opsional (seperti `GitHubUpdater`) di `Kernel::boot_engine()` wajib dibungkus dengan pengecekan `class_exists()`.
   - Jika file updater atau komponen eksternal corrupt/hilang selama proses ekstraksi ZIP oleh WordPress, plugin **tidak boleh memicu Fatal Error** dan website tetap berjalan normal (fail-safe fallback).

2. **Clean ZIP Distribution Standard**:
   - Dilarang membuat arsip `wp-speed-core.zip` menggunakan wildcard liar (`*`) yang dapat menyertakan folder `.git/`, `.phpunit.result.cache`, atau cache log internal.
   - Packaging rilis hanya boleh menyertakan file inti plugin:
     ```powershell
     Compress-Archive -Path 'assets','includes','tests','wp-content','wp-speed-core.php','composer.json','composer.lock','phpunit.xml','README.md','CHANGELOG.md','WORKFLOW_RULES.md' -DestinationPath 'wp-speed-core.zip' -Force
     ```
   - Verifikasi integritas direktori root arsip ZIP sebelum git push atau tagging rilis. File `class-github-updater.php` dan file class kernel lainnya wajib berada di path yang tepat (`includes/engine/class-github-updater.php`).

3. **Sync Tag & Release Asset**:
   - Setiap pembuatan tag rilis Git (`vX.X.X`), file rilis `wp-speed-core.zip` yang ter-compile bersih wajib ikut di-commit ke branch `main`.
   - Fallback download URL updater menargetkan branch `main/wp-speed-core.zip` untuk menjamin file yang diunduh selalu memiliki struktur direktori valid.

