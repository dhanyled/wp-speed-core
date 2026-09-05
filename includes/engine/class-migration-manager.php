<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class MigrationManager {
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $this->logger = $logger;
    }

    /**
     * Detects existing settings from external performance plugins.
     *
     * @return array<string, array{name: string, count: int, available: bool}>
     */
    public function get_available_migrations(): array {
        $results = [];

        // 1. WP Rocket
        $wpr = get_option('wp_rocket_settings');
        if (is_array($wpr) && !empty($wpr)) {
            $results['wp_rocket'] = [
                'name'      => 'WP Rocket',
                'count'     => count($wpr),
                'available' => true,
            ];
        }

        // 2. Perfmatters
        $pm = get_option('perfmatters_options');
        if (is_array($pm) && !empty($pm)) {
            $results['perfmatters'] = [
                'name'      => 'Perfmatters',
                'count'     => count($pm),
                'available' => true,
            ];
        }

        // 3. LiteSpeed Cache
        $lsc = get_option('litespeed.conf') ?: get_option('litespeed-cache-conf');
        if (is_array($lsc) && !empty($lsc)) {
            $results['litespeed'] = [
                'name'      => 'LiteSpeed Cache',
                'count'     => count($lsc),
                'available' => true,
            ];
        }

        return $results;
    }

    /**
     * Import settings from the selected external plugin into WP Speed Core.
     *
     * @param string $source Key: 'wp_rocket' | 'perfmatters' | 'litespeed'
     * @return array{success: bool, message: string, imported_count: int}
     */
    public function import_settings(string $source): array {
        $settings = (array) get_option('wpsc_settings', []);
        $imported = 0;

        switch ($source) {
            case 'wp_rocket':
                $wpr = get_option('wp_rocket_settings');
                if (!is_array($wpr) || empty($wpr)) {
                    return ['success' => false, 'message' => __('Pengaturan WP Rocket tidak ditemukan.', 'wp-speed-core'), 'imported_count' => 0];
                }

                // Caching
                $settings['cache']['html_cache'] = 1;
                if (!empty($wpr['cache_mobile'])) {
                    $settings['cache']['cache_mobile'] = 1;
                    $imported++;
                }
                if (!empty($wpr['cache_reject_uri']) && is_array($wpr['cache_reject_uri'])) {
                    $existing = trim($settings['cache']['cache_exclusions'] ?? '');
                    $new_ex = implode("\n", array_filter($wpr['cache_reject_uri']));
                    $settings['cache']['cache_exclusions'] = $existing ? $existing . "\n" . $new_ex : $new_ex;
                    $imported++;
                }

                // Scripts
                if (!empty($wpr['defer_all_js'])) {
                    $settings['script']['use_native_defer'] = 1;
                    $imported++;
                }
                if (!empty($wpr['delay_js'])) {
                    $settings['script']['delayed_execution'] = 1;
                    $imported++;
                }

                // Media
                if (!empty($wpr['lazyload'])) {
                    $settings['media']['lazy_load'] = 1;
                    $imported++;
                }
                if (!empty($wpr['lazyload_iframes']) || !empty($wpr['lazyload_youtube'])) {
                    $settings['media']['lazy_iframes'] = 1;
                    $imported++;
                }

                // CDN
                if (!empty($wpr['cdn']) && !empty($wpr['cdn_cnames']) && is_array($wpr['cdn_cnames'])) {
                    $settings['cdn']['enable_cdn'] = 1;
                    $settings['cdn']['cdn_url']    = esc_url_raw('https://' . ltrim($wpr['cdn_cnames'][0], '/'));
                    $imported++;
                }
                break;

            case 'perfmatters':
                $pm = get_option('perfmatters_options');
                if (!is_array($pm) || empty($pm)) {
                    return ['success' => false, 'message' => __('Pengaturan Perfmatters tidak ditemukan.', 'wp-speed-core'), 'imported_count' => 0];
                }

                // Scripts & Performance
                if (!empty($pm['defer_js'])) {
                    $settings['script']['use_native_defer'] = 1;
                    $imported++;
                }
                if (!empty($pm['delay_js'])) {
                    $settings['script']['delayed_execution'] = 1;
                    $imported++;
                }

                // Media
                if (!empty($pm['lazy_loading'])) {
                    $settings['media']['lazy_load'] = 1;
                    $imported++;
                }
                if (!empty($pm['lazy_loading_iframes'])) {
                    $settings['media']['lazy_iframes'] = 1;
                    $imported++;
                }

                // Bloat
                if (!empty($pm['disable_emojis'])) {
                    $settings['bloat']['remove_emojis'] = 1;
                    $imported++;
                }
                if (!empty($pm['disable_duotone'])) {
                    $settings['bloat']['remove_duotone'] = 1;
                    $imported++;
                }

                // Speculation Rules
                if (!empty($pm['instant_page']) || !empty($pm['speculation_rules'])) {
                    $settings['preload']['speculation_rules'] = 1;
                    $imported++;
                }

                // CDN
                if (!empty($pm['cdn_url'])) {
                    $settings['cdn']['enable_cdn'] = 1;
                    $settings['cdn']['cdn_url']    = esc_url_raw($pm['cdn_url']);
                    $imported++;
                }
                break;

            case 'litespeed':
                $lsc = get_option('litespeed.conf') ?: get_option('litespeed-cache-conf');
                if (!is_array($lsc) || empty($lsc)) {
                    return ['success' => false, 'message' => __('Pengaturan LiteSpeed Cache tidak ditemukan.', 'wp-speed-core'), 'imported_count' => 0];
                }

                if (!empty($lsc['cache-mobile'])) {
                    $settings['cache']['cache_mobile'] = 1;
                    $imported++;
                }
                if (!empty($lsc['media-lazy'])) {
                    $settings['media']['lazy_load'] = 1;
                    $imported++;
                }
                if (!empty($lsc['media-lazy_iframe'])) {
                    $settings['media']['lazy_iframes'] = 1;
                    $imported++;
                }
                break;

            default:
                return ['success' => false, 'message' => __('Sumber migrasi tidak valid.', 'wp-speed-core'), 'imported_count' => 0];
        }

        update_option('wpsc_settings', $settings);

        if ($this->logger) {
            $this->logger->info(sprintf('Migrasi 1-klik berhasil dijalankan dari %s.', $source), [
                'imported_keys' => $imported,
            ]);
        }

        return [
            'success'        => true,
            'message'        => sprintf(__('Berhasil mengimpor %d konfigurasi dari %s.', 'wp-speed-core'), $imported, ucfirst(str_replace('_', ' ', $source))),
            'imported_count' => $imported,
        ];
    }
}
