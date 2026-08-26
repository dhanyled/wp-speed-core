<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

class AdaptiveTuner {
    private EnvironmentScanner $scanner;
    private ?Logger $logger;

    public function __construct(EnvironmentScanner $scanner, ?Logger $logger = null) {
        $this->scanner = $scanner;
        $this->logger  = $logger;
    }

    public function compute(): array {
        $env = $this->scanner->get();
        $is_high_mem = ($env['php']['memory_bytes'] ?? 0) >= (256 * 1024 * 1024);

        $out = [];
        $out['general']['mode']            = 'smart';
        $out['general']['strip_emojis']    = 1;
        $out['general']['strip_embeds']    = 1;
        $out['general']['strip_dashicons'] = !empty($env['wordpress']['is_block_theme']) ? 1 : 0;
        $out['general']['block_xmlrpc']    = 1;

        if (!empty($env['ecommerce']['woocommerce'])) {
            $out['general']['disable_cart_fragments_non_shop'] = 1;
            $out['cache']['cache_authenticated']               = 0;
        }

        $out['cache']['html_cache']          = 1;
        $out['script']['delayed_execution']  = 1;
        $out['script']['execution_strategy'] = 'chunked';
        $out['script']['use_native_defer']   = 1;

        $exclusions = [
            'jquery.js',
            'jquery.min.js',
            'elementorFrontendConfig',
            'recaptcha',
            'turnstile',
            'hcaptcha',
            'wc-cart-fragments',
        ];
        $out['script']['exclusion_list'] = implode("\n", $exclusions);

        $out['style']['below_fold_skip']    = 1;
        $out['media']['native_lazy']        = 1;
        $out['media']['auto_hero_priority'] = 1;
        $out['preload']['speculation_rules'] = 1;
        $out['preload']['speculation_level'] = $is_high_mem ? 'eager' : 'moderate';

        return $out;
    }

    public function apply(): bool {
        $computed = $this->compute();
        $curr     = (array) get_option('wpsc_settings', []);

        // Preserve existing user exclusions and merge with recommended exclusions
        $existing_raw = (string) ($curr['script']['exclusion_list'] ?? '');
        if (!empty($existing_raw)) {
            $existing_arr = array_filter(array_map('trim', explode("\n", $existing_raw)));
            $computed_arr = array_filter(array_map('trim', explode("\n", (string) ($computed['script']['exclusion_list'] ?? ''))));
            $merged_arr   = array_unique(array_merge($computed_arr, $existing_arr));
            $computed['script']['exclusion_list'] = implode("\n", $merged_arr);
        }

        $merged   = array_replace_recursive($curr, $computed);
        $updated  = update_option('wpsc_settings', $merged);

        if ($this->logger) {
            $this->logger->info('1-Click Auto-Tune applied successfully.', [
                'server'            => $this->scanner->get()['server']['software'] ?? 'Unknown',
                'php'               => PHP_VERSION,
                'speculation_level' => $computed['preload']['speculation_level'] ?? 'moderate',
            ]);
            $this->logger->log_system_snapshot($this->scanner);
        }

        return $updated;
    }
}
