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

    public function trim_revisions(): int {
        global $wpdb;
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'revision',
            'inherit'
        );
        return (int) $wpdb->query($sql);
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
        $sql  = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
            $wpdb->esc_like('_transient_timeout_') . '%',
            $time
        );
        return (int) $wpdb->query($sql);
    }
}
