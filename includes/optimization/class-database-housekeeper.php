<?php
declare(strict_types=1);

namespace WPSpeedCore\Optimization;

use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseHousekeeper {
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $this->logger = $logger;
        add_action('wpsc_maintenance', [$this, 'maintain']);
    }

    public function maintain(): void {
        $revs  = $this->trim_revisions();
        $drf   = $this->trim_drafts();
        $trsh  = $this->trim_trash();
        $spm   = $this->trim_spam();
        $trans = $this->trim_transients();

        if ($this->logger) {
            $this->logger->info('Scheduled DB Housekeeping completed.', [
                'revisions_pruned' => $revs,
                'drafts_cleared'   => $drf,
                'trash_emptied'    => $trsh,
                'spam_removed'     => $spm,
                'expired_trans'    => $trans,
            ]);
        }
    }

    public function optimize_tables(): int {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        $count  = 0;

        if ($tables) {
            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE `{$table}`");
                $count++;
            }
        }

        if ($this->logger) {
            $this->logger->info('Database tables optimization completed.', ['tables_optimized' => $count]);
        }

        return $count;
    }

    public function trim_revisions(): int {
        global $wpdb;
        $rev_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s LIMIT 500",
            'revision',
            'inherit'
        ));

        if (empty($rev_ids)) {
            return 0;
        }

        $count = 0;
        foreach ($rev_ids as $id) {
            if (function_exists('wp_delete_post_revision')) {
                wp_delete_post_revision((int) $id);
            } else {
                wp_delete_post((int) $id, true);
            }
            $count++;
        }
        return $count;
    }

    public function trim_drafts(): int {
        global $wpdb;
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_status = %s",
            'auto-draft'
        );
        return (int) $wpdb->query($sql);
    }

    public function trim_trash(): int {
        global $wpdb;
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_status = %s",
            'trash'
        );
        return (int) $wpdb->query($sql);
    }

    public function trim_spam(): int {
        global $wpdb;
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->comments} WHERE comment_approved IN (%s, %s)",
            'spam',
            'trash'
        );
        return (int) $wpdb->query($sql);
    }

    public function trim_transients(): int {
        global $wpdb;
        $time = time();
        $expired = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
            $wpdb->esc_like('_transient_timeout_') . '%',
            $time
        ));

        if (empty($expired)) {
            return 0;
        }

        $count = 0;
        foreach ($expired as $transient) {
            $key = str_replace('_transient_timeout_', '', $transient);
            if (function_exists('delete_transient')) {
                delete_transient($key);
            } else {
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)", '_transient_timeout_' . $key, '_transient_' . $key));
            }
            $count++;
        }

        return $count;
    }
}
