<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
    return;
}

$tables = [
    $wpdb->prefix . 'fp_discountgift_rules',
    $wpdb->prefix . 'fp_discountgift_rule_usages',
    $wpdb->prefix . 'fp_discountgift_voucher_events',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

delete_option('fp_discountgift_settings');
delete_option('fp_discountgift_db_version');
