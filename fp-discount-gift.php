<?php
/**
 * Plugin Name: FP Discount Gift
 * Plugin URI: https://github.com/franpass87/FP-Discount-Gift
 * Description: Gestione codici sconto FP con integrazione WooCommerce e sincronizzazione eventi voucher da FP-Experiences.
 * Version: 1.2.4
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * License: Proprietary
 * Text Domain: fp-discount-gift
 * Domain Path: /languages
 * GitHub Plugin URI: franpass87/FP-Discount-Gift
 * Primary Branch: main
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('FP_DISCOUNTGIFT_VERSION', '1.2.4');
define('FP_DISCOUNTGIFT_FILE', __FILE__);
define('FP_DISCOUNTGIFT_DIR', plugin_dir_path(__FILE__));
define('FP_DISCOUNTGIFT_URL', plugin_dir_url(__FILE__));
define('FP_DISCOUNTGIFT_BASENAME', plugin_basename(__FILE__));

if (file_exists(FP_DISCOUNTGIFT_DIR . 'vendor/autoload.php')) {
    require_once FP_DISCOUNTGIFT_DIR . 'vendor/autoload.php';
}

register_activation_hook(FP_DISCOUNTGIFT_FILE, static function (): void {
    if (class_exists(\FP\DiscountGift\Core\Plugin::class)) {
        \FP\DiscountGift\Core\Plugin::instance()->activate();
    }
});

register_deactivation_hook(FP_DISCOUNTGIFT_FILE, static function (): void {
    if (class_exists(\FP\DiscountGift\Core\Plugin::class)) {
        \FP\DiscountGift\Core\Plugin::instance()->deactivate();
    }
});

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('fp-discount-gift', false, dirname(FP_DISCOUNTGIFT_BASENAME) . '/languages');

    if (class_exists(\FP\DiscountGift\Core\Plugin::class)) {
        \FP\DiscountGift\Core\Plugin::instance()->init();
    }
});
