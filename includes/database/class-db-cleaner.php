<?php
declare(strict_types=1);

namespace WPSpeedCore\Database;

use WPSpeedCore\Engine\Logger;

if (!defined('ABSPATH')) {
    return;
}

final class DbCleaner {
    private ?Logger $logger;

    public function __construct(?Logger $logger = null) {
        $this->logger = $logger;
    }

    public function run_cleanup(): array {
        global $wpdb;

        $stats = [
            'revisions_deleted' => 0,
            'auto_drafts_deleted' => 0,
            'transients_deleted' => 0,
            'spam_comments_deleted' => 0,
            'trash_comments_deleted' => 0,
        ];

        // 1. Revisions
        $rev_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'");
        if (!empty($rev_ids)) {
            foreach ($rev_ids as $id) {
                wp_delete_post_revision((int)$id);
            }
            $stats['revisions_deleted'] = count($rev_ids);
        }

        // 2. Auto-drafts older than 7 days
        $draft_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        if (!empty($draft_ids)) {
            foreach ($draft_ids as $id) {
                wp_delete_post((int)$id, true);
            }
            $stats['auto_drafts_deleted'] = count($draft_ids);
        }

        // 3. Expired transients
        $time = time();
        $expired_transients = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_%',
            $time
        ));
        if (!empty($expired_transients)) {
            foreach ($expired_transients as $transient) {
                $key = str_replace('_transient_timeout_', '', $transient);
                delete_transient($key);
            }
            $stats['transients_deleted'] = count($expired_transients);
        }

        // 4. Spam & Trash comments
        $spam_ids = $wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
        if (!empty($spam_ids)) {
            foreach ($spam_ids as $cid) {
                wp_delete_comment((int)$cid, true);
            }
            $stats['spam_comments_deleted'] = count($spam_ids);
        }

        $trash_ids = $wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
        if (!empty($trash_ids)) {
            foreach ($trash_ids as $cid) {
                wp_delete_comment((int)$cid, true);
            }
            $stats['trash_comments_deleted'] = count($trash_ids);
        }

        if ($this->logger) {
            $this->logger->info(sprintf(
                '1-Click DB Cleanup executed: Revisions (%d), AutoDrafts (%d), Transients (%d), Spam (%d), Trash (%d)',
                $stats['revisions_deleted'],
                $stats['auto_drafts_deleted'],
                $stats['transients_deleted'],
                $stats['spam_comments_deleted'],
                $stats['trash_comments_deleted']
            ));
        }

        return $stats;
    }
}
