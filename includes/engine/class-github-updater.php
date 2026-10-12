<?php
declare(strict_types=1);

namespace WPSpeedCore\Engine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GitHubUpdater
 *
 * Provides native WordPress integration for manual and automatic updates
 * directly from GitHub Releases and tags.
 */
class GitHubUpdater {

    private string $repo;
    private string $plugin_basename;
    private string $current_version;
    private string $transient_key;
    private int $cache_ttl;
    private ?Logger $logger;

    public function __construct(
        string $repo = 'dhanyled/wp-speed-core',
        ?string $plugin_basename = null,
        ?string $current_version = null,
        ?Logger $logger = null
    ) {
        $this->repo             = $repo;
        $this->plugin_basename  = $plugin_basename ?? (defined('WPSC_BASENAME') ? WPSC_BASENAME : 'wp-speed-core/wp-speed-core.php');
        $this->current_version  = $current_version ?? (defined('WPSC_VERSION') ? WPSC_VERSION : '1.0.0');
        $this->transient_key    = 'wpsc_github_release_data';
        $this->cache_ttl        = 3 * HOUR_IN_SECONDS; // 3 hours cache
        $this->logger           = $logger;

        $this->register_hooks();
    }

    private function register_hooks(): void {
        // Inject update data into WordPress plugin update transients (read & write)
        add_filter('site_transient_update_plugins', [$this, 'check_update']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);

        // Supply data for the "View version details" modal popup
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

        // Action links under plugin name (Settings link)
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'plugin_action_links']);

        // Row meta links beside version/author (View details modal & Changelog link)
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);

        // Ensure thickbox assets are enqueued on plugins page
        add_action('admin_enqueue_scripts', [$this, 'ensure_thickbox']);

        // Normalize destination directory after upgrader extracts zip
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);

        // Custom update message row in wp-admin/plugins.php
        add_action('in_plugin_update_message-' . $this->plugin_basename, [$this, 'update_message'], 10, 2);

        // Allow manual trigger to force check for updates
        add_action('admin_init', [$this, 'handle_force_check']);

        // Auto-update filter for WordPress core background cron
        add_filter('auto_update_plugin', [$this, 'should_auto_update'], 10, 2);

        // Allow 1-click toggle auto-update from WP Speed Core Dashboard
        add_action('admin_init', [$this, 'handle_toggle_auto_update']);
    }

    /**
     * Fetch latest release info from GitHub API or cache.
     */
    public function get_remote_release(bool $force = false): ?array {
        if (!empty($_GET['force-check'])) {
            $force = true;
        }

        if (!$force) {
            $cached = get_transient($this->transient_key);
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
        }

        // 1. Primary: Fast, rate-limit-free version.json from raw.githubusercontent.com
        $raw_json_url = "https://raw.githubusercontent.com/{$this->repo}/main/version.json";
        $raw_resp     = wp_remote_get($raw_json_url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (!is_wp_error($raw_resp) && wp_remote_retrieve_response_code($raw_resp) === 200) {
            $json_data = json_decode(wp_remote_retrieve_body($raw_resp), true);
            if (is_array($json_data) && !empty($json_data['version'])) {
                $release = [
                    'version'      => (string) $json_data['version'],
                    'tag_name'     => 'v' . ltrim((string) $json_data['version'], 'v'),
                    'download_url' => (string) ($json_data['download_url'] ?? "https://github.com/{$this->repo}/raw/main/wp-speed-core.zip"),
                    'changelog'    => (string) ($json_data['changelog'] ?? "Pembaruan v{$json_data['version']} WP Speed Core dari GitHub."),
                    'published_at' => (string) ($json_data['published_at'] ?? current_time('mysql')),
                    'url'          => (string) ($json_data['url'] ?? "https://github.com/{$this->repo}/releases"),
                    'requires'     => (string) ($json_data['requires'] ?? '6.2'),
                    'tested'       => (string) ($json_data['tested'] ?? '6.7'),
                    'requires_php' => (string) ($json_data['requires_php'] ?? '8.0'),
                ];

                set_transient($this->transient_key, $release, $this->cache_ttl);
                return $release;
            }
        }

        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . (get_bloginfo('version') ?: '6.x') . '; WP-Speed-Core/' . $this->current_version,
        ];

        // 2. Secondary fallback: Releases API
        $release_url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $response    = wp_remote_get($release_url, [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (is_array($data) && !empty($data['tag_name'])) {
                $version = ltrim((string) $data['tag_name'], 'v');
                $download_url = '';

                // Look for attached .zip asset (strictly prioritize official wp-speed-core.zip)
                if (!empty($data['assets']) && is_array($data['assets'])) {
                    foreach ($data['assets'] as $asset) {
                        $asset_name = strtolower((string) ($asset['name'] ?? ''));
                        if ($asset_name === 'wp-speed-core.zip') {
                            $download_url = (string) ($asset['browser_download_url'] ?? '');
                            break;
                        }
                    }

                    if (empty($download_url)) {
                        foreach ($data['assets'] as $asset) {
                            $asset_name = strtolower((string) ($asset['name'] ?? ''));
                            if (str_ends_with($asset_name, '.zip') && strpos($asset_name, 'tester') === false) {
                                $download_url = (string) ($asset['browser_download_url'] ?? '');
                                break;
                            }
                        }
                    }
                }

                if (empty($download_url)) {
                    $download_url = !empty($data['zipball_url'])
                        ? (string) $data['zipball_url']
                        : "https://github.com/{$this->repo}/archive/refs/tags/{$data['tag_name']}.zip";
                }

                $release = [
                    'version'      => $version,
                    'tag_name'     => (string) $data['tag_name'],
                    'download_url' => $download_url,
                    'changelog'    => (string) ($data['body'] ?? ''),
                    'published_at' => (string) ($data['published_at'] ?? ''),
                    'url'          => (string) ($data['html_url'] ?? "https://github.com/{$this->repo}/releases"),
                    'requires'     => '6.2',
                    'tested'       => '6.7',
                    'requires_php' => '8.0',
                ];

                set_transient($this->transient_key, $release, $this->cache_ttl);
                return $release;
            }
        }

        // 2. Fallback: Query tags endpoint if no GitHub Release object is found
        $tags_url  = "https://api.github.com/repos/{$this->repo}/tags";
        $tags_resp = wp_remote_get($tags_url, [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (!is_wp_error($tags_resp) && wp_remote_retrieve_response_code($tags_resp) === 200) {
            $tags = json_decode(wp_remote_retrieve_body($tags_resp), true);
            if (is_array($tags) && !empty($tags[0]['name'])) {
                $tag_name = (string) $tags[0]['name'];
                $version  = ltrim($tag_name, 'v');

                $release = [
                    'version'      => $version,
                    'tag_name'     => $tag_name,
                    'download_url' => "https://github.com/{$this->repo}/raw/main/wp-speed-core.zip",
                    'changelog'    => "Pembaruan versi {$version} dari GitHub repository ({$tag_name}).",
                    'published_at' => current_time('mysql'),
                    'url'          => "https://github.com/{$this->repo}/releases",
                    'requires'     => '6.2',
                    'tested'       => '6.7',
                    'requires_php' => '8.0',
                ];

                set_transient($this->transient_key, $release, $this->cache_ttl);
                return $release;
            }
        }

        return null;
    }

    /**
     * Check if a newer version is available and update the WordPress update transient.
     *
     * @param object|null $transient
     * @return object|null
     */
    public function check_update($transient) {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }

        $release = $this->get_remote_release();
        if (!$release || empty($release['version'])) {
            return $transient;
        }

        $wp_ver = (string) (get_bloginfo('version') ?: '6.8');
        $tested = (string) ($release['tested'] ?? '6.8');
        if (version_compare($wp_ver, $tested, '>')) {
            $tested = $wp_ver;
        }

        if (version_compare($release['version'], $this->current_version, '>')) {
            $item = new \stdClass();
            $item->id             = 'github.com/' . $this->repo;
            $item->slug           = 'wp-speed-core';
            $item->plugin         = $this->plugin_basename;
            $item->new_version    = $release['version'];
            $item->url            = $release['url'];
            $item->package        = $release['download_url'];
            $item->icons          = [
                'default' => defined('WPSC_URL') ? WPSC_URL . 'assets/img/icon.png' : '',
            ];
            $item->banners        = [];
            $item->banners_rtl    = [];
            $item->requires       = $release['requires'] ?? '6.2';
            $item->tested         = $tested;
            $item->requires_php   = $release['requires_php'] ?? '8.0';
            $item->compatibility  = new \stdClass();

            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = [];
            }
            $transient->response[$this->plugin_basename] = $item;

            if (isset($transient->no_update[$this->plugin_basename])) {
                unset($transient->no_update[$this->plugin_basename]);
            }
        } else {
            $no_update = new \stdClass();
            $no_update->id           = 'github.com/' . $this->repo;
            $no_update->slug         = 'wp-speed-core';
            $no_update->plugin       = $this->plugin_basename;
            $no_update->new_version  = $this->current_version;
            $no_update->url          = $release['url'] ?? "https://github.com/{$this->repo}";
            $no_update->package      = '';
            $no_update->requires     = '6.2';
            $no_update->tested       = $tested;
            $no_update->requires_php = '8.0';

            if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[$this->plugin_basename] = $no_update;

            if (isset($transient->response[$this->plugin_basename])) {
                unset($transient->response[$this->plugin_basename]);
            }
        }

        return $transient;
    }

    /**
     * Provide details for the WordPress "View version details" modal popup.
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return object|false
     */
    public function plugin_info($result, string $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        $slug_match = isset($args->slug) && $args->slug === 'wp-speed-core';
        $file_match = isset($args->plugin) && $args->plugin === $this->plugin_basename;

        if (!$slug_match && !$file_match) {
            return $result;
        }

        $release = $this->get_remote_release();
        $version = $release['version'] ?? $this->current_version;

        $wp_ver = (string) (get_bloginfo('version') ?: '6.8');
        $tested = (string) ($release['tested'] ?? '6.8');
        if (version_compare($wp_ver, $tested, '>')) {
            $tested = $wp_ver;
        }

        $info = new \stdClass();
        $info->name          = 'WP Speed Core';
        $info->slug          = 'wp-speed-core';
        $info->version       = $version;
        $info->author        = '<a href="https://t.me/leddhany" target="_blank">Dhany (@leddhany)</a>';
        $info->homepage      = 'https://github.com/' . $this->repo;
        $info->requires      = $release['requires'] ?? '6.2';
        $info->tested        = $tested;
        $info->requires_php  = $release['requires_php'] ?? '8.0';
        $info->downloaded    = 0;
        $info->last_updated  = !empty($release['published_at']) ? substr((string) $release['published_at'], 0, 10) : date('Y-m-d');
        $info->download_link = $release['download_url'] ?? '';

        $changelog_content = !empty($release['changelog'])
            ? esc_html($release['changelog'])
            : 'Pembaruan rutin stabilitas, performa, dan fitur terkini.';

        $info->sections = [
            'description' => '<p>All-in-one WordPress performance engine with AI Model Context Protocol (MCP), Adaptive Auto-Tuning, Tracking Conflict Inspector, Overlap Arbiter, Disk HTML Cache, INP-safe script delay, auto-LCP priority, Speculation Rules prerender, Contextual Asset Unloader, and DB housekeeping.</p>',
            'changelog'   => $this->get_formatted_changelog($release['changelog'] ?? null),
        ];

        return $info;
    }

    /**
     * Parse CHANGELOG.md into semantic, chronological HTML for WordPress modal.
     */
    private function get_formatted_changelog(?string $fallback = null): string {
        $markdown = '';
        $local_path = defined('WPSC_PATH') ? WPSC_PATH . 'CHANGELOG.md' : dirname(__DIR__, 2) . '/CHANGELOG.md';
        if (file_exists($local_path) && is_readable($local_path)) {
            $markdown = (string) file_get_contents($local_path);
        }

        if (empty($markdown)) {
            $raw_url = "https://raw.githubusercontent.com/{$this->repo}/main/CHANGELOG.md";
            $resp    = wp_remote_get($raw_url, ['timeout' => 5]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $markdown = (string) wp_remote_retrieve_body($resp);
            }
        }

        if (empty($markdown)) {
            return !empty($fallback) ? '<div style="font-family:sans-serif; line-height:1.5;">' . nl2br(esc_html($fallback)) . '</div>' : '<p>Pembaruan berkala performa dan stabilitas.</p>';
        }

        $lines   = explode("\n", $markdown);
        $html    = '';
        $in_list = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed === '---' || strpos($trimmed, '# 📜 Changelog') === 0 || strpos($trimmed, 'Semua perubahan') === 0) {
                if ($in_list && $trimmed === '') {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                continue;
            }

            // Version headers: ## [1.6.6] - 2026-10-12
            if (preg_match('/^##\s+\[?([0-9\.]+)\]?(?:\s+-\s+(.+))?/', $trimmed, $m)) {
                if ($in_list) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $version_str = 'Versi ' . esc_html($m[1]);
                $date_str    = !empty($m[2]) ? ' <span style="font-size:12px; font-weight:normal; color:#64748b;">(' . esc_html($m[2]) . ')</span>' : '';
                $html .= '<h4 style="margin: 22px 0 8px 0; padding-bottom: 5px; border-bottom: 1px solid #cbd5e1; color: #0284c7; font-size: 15px;">' . $version_str . $date_str . "</h4>\n";
                continue;
            }

            // Category headers: ### 🚀 ...
            if (preg_match('/^###\s+(.+)/', $trimmed, $m)) {
                if ($in_list) {
                    $html .= "</ul>\n";
                    $in_list = false;
                }
                $html .= '<p style="margin: 10px 0 4px 0; font-weight: 600; color: #1e293b;">' . esc_html($m[1]) . "</p>\n";
                continue;
            }

            // Top-level bullet: - **Title**: Description or - Text
            if (preg_match('/^-\s+(.+)/', $trimmed, $m)) {
                if (!$in_list) {
                    $html .= "<ul style=\"margin: 4px 0 14px 20px; list-style-type: disc;\">\n";
                    $in_list = true;
                }
                $item_text = $m[1];
                $item_text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $item_text);
                $item_text = preg_replace('/`(.+?)`/', '<code>$1</code>', $item_text);
                $html .= '<li style="margin-bottom: 4px; line-height: 1.5;">' . wp_kses_post($item_text) . "</li>\n";
                continue;
            }

            // Indented sub-bullet: "  - Text"
            if (preg_match('/^\s{2,}-\s+(.+)/', $line, $m)) {
                $sub_text = $m[1];
                $sub_text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $sub_text);
                $sub_text = preg_replace('/`(.+?)`/', '<code>$1</code>', $sub_text);
                $html .= '<li style="margin-left: 20px; margin-bottom: 3px; list-style-type: circle; color: #475569;">' . wp_kses_post($sub_text) . "</li>\n";
                continue;
            }
        }

        if ($in_list) {
            $html .= "</ul>\n";
        }

        return $html;
    }

    /**
     * Post-install upgrader filter to ensure the folder is properly named 'wp-speed-core'.
     *
     * @param bool|array $response
     * @param array $hook_extra
     * @param array $result
     * @return array|bool
     */
    public function post_install($response, array $hook_extra, array $result) {
        global $wp_filesystem;

        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $response;
        }

        $proper_destination = WP_PLUGIN_DIR . '/wp-speed-core';

        // Deteksi & ratakan (flatten) jika terjadi folder bersarang wp-speed-core/wp-speed-core
        $nested_subfolder = $proper_destination . '/wp-speed-core';
        if ($wp_filesystem && $wp_filesystem->is_dir($nested_subfolder)) {
            if (function_exists('copy_dir')) {
                copy_dir($nested_subfolder, $proper_destination);
            }
            $wp_filesystem->delete($nested_subfolder, true);
        }

        if (isset($result['destination']) && $result['destination'] !== $proper_destination) {
            if ($wp_filesystem && $wp_filesystem->exists($result['destination'])) {
                if ($wp_filesystem->exists($proper_destination)) {
                    $wp_filesystem->delete($proper_destination, true);
                }
                $wp_filesystem->move($result['destination'], $proper_destination, true);
                $result['destination']      = $proper_destination;
                $result['destination_name'] = 'wp-speed-core';
            }
        }

        if (function_exists('is_plugin_active') && is_plugin_active($this->plugin_basename)) {
            activate_plugin($this->plugin_basename);
        }

        if ($this->logger) {
            $this->logger->info('GitHub Updater: Plugin berhasil diperbarui ke folder ' . $proper_destination);
        }

        return $response;
    }

    /**
     * Custom update message in wp-admin/plugins.php below plugin row.
     *
     * @param array $plugin_data
     * @param object $response
     */
    public function update_message(array $plugin_data, $response): void {
        echo '<br><span style="display:inline-block; margin-top:5px; color:#0284c7; font-size:12px;">'
            . '🚀 <strong>GitHub Official Release:</strong> Pembaruan diunduh langsung dari repositori resmi (<code>' . esc_html($this->repo) . '</code>). '
            . 'Pengaturan dan cache Anda tidak akan hilang.</span>';
    }

    /**
     * Handle manual force check request from dashboard.
     */
    public function handle_force_check(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['wpsc_check_update']) && check_admin_referer('wpsc_check_update_nonce')) {
            delete_transient($this->transient_key);
            delete_site_transient('update_plugins');

            $release = $this->get_remote_release(true);

            $tab = sanitize_key($_GET['tab'] ?? 'overview');
            $has_new = ($release && version_compare($release['version'], $this->current_version, '>')) ? '1' : '0';

            wp_safe_redirect(add_query_arg([
                'page'           => 'wp-speed-core',
                'tab'            => $tab,
                'update_checked' => '1',
                'has_new'        => $has_new,
            ], admin_url('options-general.php')));
            exit;
        }
    }

    /**
     * Ensure WordPress auto-updates recognize WP Speed Core when enabled.
     *
     * @param bool|null $update
     * @param object $item
     * @return bool|null
     */
    public function should_auto_update($update, $item) {
        if (!empty($item->plugin) && $item->plugin === $this->plugin_basename) {
            $auto_plugins = (array) get_site_option('auto_update_plugins', []);
            if (in_array($this->plugin_basename, $auto_plugins, true)) {
                return true;
            }
        }
        return $update;
    }

    /**
     * Handle 1-click toggle auto-update action.
     */
    public function handle_toggle_auto_update(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['wpsc_toggle_autoupdate']) && check_admin_referer('wpsc_toggle_autoupdate_nonce')) {
            $auto_plugins = (array) get_site_option('auto_update_plugins', []);
            $state        = sanitize_key($_GET['state'] ?? 'enable');

            if ($state === 'enable') {
                $auto_plugins[] = $this->plugin_basename;
                $auto_plugins   = array_unique($auto_plugins);
            } else {
                $auto_plugins = array_diff($auto_plugins, [$this->plugin_basename]);
            }

            update_site_option('auto_update_plugins', array_values($auto_plugins));

            $tab = sanitize_key($_GET['tab'] ?? 'overview');
            wp_safe_redirect(add_query_arg([
                'page'               => 'wp-speed-core',
                'tab'                => $tab,
                'autoupdate_toggled' => $state,
            ], admin_url('options-general.php')));
            exit;
        }
    }

    /**
     * Get update status info for the Admin Dashboard.
     *
     * @return array
     */
    public function get_update_status(): array {
        $release    = $this->get_remote_release();
        $latest_ver = $release['version'] ?? $this->current_version;
        $has_update = version_compare($latest_ver, $this->current_version, '>');

        $check_url = wp_nonce_url(
            admin_url('options-general.php?page=wp-speed-core&wpsc_check_update=1'),
            'wpsc_check_update_nonce'
        );

        $update_url = wp_nonce_url(
            self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($this->plugin_basename)),
            'upgrade-plugin_' . $this->plugin_basename
        );

        $auto_plugins          = (array) get_site_option('auto_update_plugins', []);
        $is_autoupdate_enabled = in_array($this->plugin_basename, $auto_plugins, true);
        $toggle_autoupdate_url = wp_nonce_url(
            admin_url('options-general.php?page=wp-speed-core&wpsc_toggle_autoupdate=1&state=' . ($is_autoupdate_enabled ? 'disable' : 'enable')),
            'wpsc_toggle_autoupdate_nonce'
        );

        return [
            'has_update'            => $has_update,
            'current_version'       => $this->current_version,
            'latest_version'        => $latest_ver,
            'release_url'           => $release['url'] ?? "https://github.com/{$this->repo}/releases",
            'check_url'             => $check_url,
            'update_url'            => $update_url,
            'changelog'             => $release['changelog'] ?? '',
            'published_at'          => $release['published_at'] ?? '',
            'is_autoupdate_enabled' => $is_autoupdate_enabled,
            'toggle_autoupdate_url' => $toggle_autoupdate_url,
        ];
    }

    /**
     * Enqueue Thickbox assets on the plugins list screen to support the details modal.
     */
    public function ensure_thickbox(string $hook_suffix): void {
        if ($hook_suffix === 'plugins.php') {
            add_thickbox();
            wp_enqueue_script('plugin-install');
        }
    }

    /**
     * Add "Settings" shortcut link in plugin actions (beside Deactivate).
     *
     * @param array $actions
     * @return array
     */
    public function plugin_action_links(array $actions): array {
        $settings_url  = admin_url('options-general.php?page=wp-speed-core');
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url($settings_url),
            esc_html__('Settings', 'wp-speed-core')
        );

        array_unshift($actions, $settings_link);
        return $actions;
    }

    /**
     * Add "View details" (modal popup) and "Changelog" to plugin row meta in wp-admin/plugins.php.
     *
     * @param array  $meta Existing meta items (Version X | By Author | Visit plugin site).
     * @param string $file The plugin basename being evaluated.
     * @return array
     */
    public function plugin_row_meta(array $meta, string $file): array {
        if ($file !== $this->plugin_basename) {
            return $meta;
        }

        // ThickBox modal URL for WordPress plugin information popup
        $details_url = self_admin_url(
            'plugin-install.php?tab=plugin-information&plugin=wp-speed-core&TB_iframe=true&width=772&height=550'
        );

        $meta[] = sprintf(
            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
            esc_url($details_url),
            esc_attr__('Lihat detail WP Speed Core', 'wp-speed-core'),
            esc_attr('WP Speed Core'),
            esc_html__('View details', 'wp-speed-core')
        );

        // Direct link to online changelog
        $meta[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://github.com/' . $this->repo . '/blob/main/CHANGELOG.md'),
            esc_html__('Changelog', 'wp-speed-core')
        );

        return $meta;
    }
}
