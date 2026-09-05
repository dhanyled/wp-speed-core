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
        $this->cache_ttl        = 12 * HOUR_IN_SECONDS; // 12 hours cache
        $this->logger           = $logger;

        $this->register_hooks();
    }

    private function register_hooks(): void {
        // Inject update data into WordPress plugin update transients
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);

        // Supply data for the "View version details" modal popup
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

        // Normalize destination directory after upgrader extracts zip
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);

        // Custom update message row in wp-admin/plugins.php
        add_action('in_plugin_update_message-' . $this->plugin_basename, [$this, 'update_message'], 10, 2);

        // Allow manual trigger to force check for updates
        add_action('admin_init', [$this, 'handle_force_check']);
    }

    /**
     * Fetch latest release info from GitHub API or cache.
     */
    public function get_remote_release(bool $force = false): ?array {
        if (!$force) {
            $cached = get_transient($this->transient_key);
            if (is_array($cached) && !empty($cached['version'])) {
                return $cached;
            }
        }

        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . (get_bloginfo('version') ?: '6.x') . '; WP-Speed-Core/' . $this->current_version,
        ];

        // 1. Try Releases API
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

                // Look for attached .zip asset (e.g., wp-speed-core.zip)
                if (!empty($data['assets']) && is_array($data['assets'])) {
                    foreach ($data['assets'] as $asset) {
                        if (isset($asset['name']) && str_ends_with(strtolower((string) $asset['name']), '.zip')) {
                            $download_url = (string) ($asset['browser_download_url'] ?? '');
                            break;
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
            $item->tested         = $release['tested'] ?? '6.7';
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
            $no_update->tested       = '6.7';
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

        $info = new \stdClass();
        $info->name          = 'WP Speed Core';
        $info->slug          = 'wp-speed-core';
        $info->version       = $version;
        $info->author        = '<a href="https://t.me/leddhany" target="_blank">Dhany (@leddhany)</a>';
        $info->homepage      = 'https://github.com/' . $this->repo;
        $info->requires      = $release['requires'] ?? '6.2';
        $info->tested        = $release['tested'] ?? '6.7';
        $info->requires_php  = $release['requires_php'] ?? '8.0';
        $info->downloaded    = 0;
        $info->last_updated  = !empty($release['published_at']) ? substr((string) $release['published_at'], 0, 10) : date('Y-m-d');
        $info->download_link = $release['download_url'] ?? '';

        $changelog_content = !empty($release['changelog'])
            ? esc_html($release['changelog'])
            : 'Pembaruan rutin stabilitas, performa, dan fitur terkini.';

        $info->sections = [
            'description' => '<p>All-in-one WordPress performance engine with AI Model Context Protocol (MCP), Adaptive Auto-Tuning, Tracking Conflict Inspector, Overlap Arbiter, Disk HTML Cache, INP-safe script delay, auto-LCP priority, Speculation Rules prerender, Contextual Asset Unloader, and DB housekeeping.</p>',
            'changelog'   => '<pre style="white-space:pre-wrap; font-family:inherit;">' . $changelog_content . '</pre>',
        ];

        return $info;
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

        if (isset($result['destination']) && $result['destination'] !== $proper_destination) {
            if ($wp_filesystem && $wp_filesystem->exists($result['destination'])) {
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

        return $result;
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

        return [
            'has_update'      => $has_update,
            'current_version' => $this->current_version,
            'latest_version'  => $latest_ver,
            'release_url'     => $release['url'] ?? "https://github.com/{$this->repo}/releases",
            'check_url'       => $check_url,
            'update_url'      => $update_url,
            'changelog'       => $release['changelog'] ?? '',
            'published_at'    => $release['published_at'] ?? '',
        ];
    }
}
