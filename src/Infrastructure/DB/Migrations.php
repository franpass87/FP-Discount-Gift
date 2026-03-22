<?php

declare(strict_types=1);

namespace FP\DiscountGift\Infrastructure\DB;

use wpdb;

use function add_option;
use function dbDelta;
use function get_option;
use function is_string;
use function maybe_serialize;
use function update_option;
use function wp_json_encode;

/**
 * Migrazioni database per FP Discount Gift.
 */
final class Migrations
{
    private const DB_VERSION = '1.1.0';
    private const DB_VERSION_OPTION = 'fp_discountgift_db_version';
    private const SETTINGS_OPTION = 'fp_discountgift_settings';

    /**
     * Esegue le migrazioni se la versione DB è cambiata.
     */
    public function run(): void
    {
        $stored_version = (string) get_option(self::DB_VERSION_OPTION, '');

        if ($stored_version === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $rules_table = $wpdb->prefix . 'fp_discountgift_rules';
        $usage_table = $wpdb->prefix . 'fp_discountgift_rule_usages';
        $events_table = $wpdb->prefix . 'fp_discountgift_voucher_events';
        $gift_cards_table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $gift_movements_table = $wpdb->prefix . 'fp_discountgift_gift_card_movements';

        $rules_sql = "CREATE TABLE {$rules_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(100) NOT NULL,
            title VARCHAR(190) NOT NULL,
            discount_type VARCHAR(20) NOT NULL DEFAULT 'fixed_cart',
            amount DECIMAL(18,4) NOT NULL DEFAULT 0,
            individual_use TINYINT(1) NOT NULL DEFAULT 0,
            usage_limit BIGINT UNSIGNED NULL,
            usage_limit_per_user BIGINT UNSIGNED NULL,
            minimum_amount DECIMAL(18,4) NULL,
            maximum_amount DECIMAL(18,4) NULL,
            date_expires DATETIME NULL,
            allowed_emails LONGTEXT NULL,
            product_ids LONGTEXT NULL,
            exclude_product_ids LONGTEXT NULL,
            product_category_ids LONGTEXT NULL,
            exclude_category_ids LONGTEXT NULL,
            allowed_roles LONGTEXT NULL,
            metadata LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY is_enabled (is_enabled),
            KEY date_expires (date_expires)
        ) {$charset_collate};";

        $usage_sql = "CREATE TABLE {$usage_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NULL,
            amount_applied DECIMAL(18,4) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_id (rule_id),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $events_sql = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(80) NOT NULL,
            voucher_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            reservation_id BIGINT UNSIGNED NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_name (event_name),
            KEY voucher_id (voucher_id)
        ) {$charset_collate};";

        $gift_cards_sql = "CREATE TABLE {$gift_cards_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(100) NOT NULL,
            initial_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
            current_balance DECIMAL(18,4) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            recipient_email VARCHAR(190) NULL,
            expires_at DATETIME NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        $gift_movements_sql = "CREATE TABLE {$gift_movements_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            gift_card_id BIGINT UNSIGNED NOT NULL,
            movement_type VARCHAR(30) NOT NULL,
            amount DECIMAL(18,4) NOT NULL DEFAULT 0,
            order_id BIGINT UNSIGNED NULL,
            reservation_id BIGINT UNSIGNED NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY gift_card_id (gift_card_id),
            KEY movement_type (movement_type)
        ) {$charset_collate};";

        dbDelta($rules_sql);
        dbDelta($usage_sql);
        dbDelta($events_sql);
        dbDelta($gift_cards_sql);
        dbDelta($gift_movements_sql);

        $this->ensureDefaultSettings();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Inizializza opzioni default plugin.
     */
    private function ensureDefaultSettings(): void
    {
        $default = [
            'enable_shadow_coupons' => true,
            'allow_wc_coupon_field' => false,
            'auto_apply_best_rule' => false,
            'gift_card_reminder_days' => 7,
            'gift_card_auto_expire' => true,
            'gift_card_send_email' => true,
            'gift_card_email_via_brevo' => false,
        ];

        if (get_option(self::SETTINGS_OPTION, null) === null) {
            add_option(self::SETTINGS_OPTION, $default);
            return;
        }

        $saved = get_option(self::SETTINGS_OPTION, []);
        if (! is_array($saved)) {
            $serialized = is_string($saved) ? $saved : wp_json_encode($saved);
            update_option(self::SETTINGS_OPTION, maybe_serialize(['legacy_raw' => $serialized]) ?: $default);
            return;
        }

        update_option(self::SETTINGS_OPTION, $saved + $default);
    }
}
