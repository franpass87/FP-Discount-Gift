<?php

declare(strict_types=1);

namespace FP\DiscountGift\Core;

use FP\DiscountGift\Admin\SettingsPage;
use FP\DiscountGift\Application\DiscountEngine;
use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;
use FP\DiscountGift\Infrastructure\DB\Migrations;
use FP\DiscountGift\Integrations\Experiences\ExperienceEventBridge;
use FP\DiscountGift\Integrations\Tracking\TrackingBridge;
use FP\DiscountGift\Integrations\WooCommerce\CheckoutBridge;
use FP\DiscountGift\Integrations\WooCommerce\ShadowCouponManager;

use function add_action;
use function current_user_can;
use function esc_html__;
use function printf;
use function version_compare;

/**
 * Bootstrap principale del plugin FP Discount Gift.
 */
final class Plugin
{
    private static ?self $instance = null;

    private Migrations $migrations;

    private DiscountRuleRepository $repository;

    private DiscountEngine $engine;

    private ShadowCouponManager $shadow_coupon_manager;

    private CheckoutBridge $checkout_bridge;

    private ExperienceEventBridge $experience_event_bridge;

    private TrackingBridge $tracking_bridge;

    private SettingsPage $settings_page;

    /**
     * Restituisce l'istanza singleton del plugin.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->migrations = new Migrations();
        $this->repository = new DiscountRuleRepository();
        $this->engine = new DiscountEngine($this->repository);
        $this->shadow_coupon_manager = new ShadowCouponManager();
        $this->checkout_bridge = new CheckoutBridge($this->engine, $this->shadow_coupon_manager);
        $this->experience_event_bridge = new ExperienceEventBridge();
        $this->tracking_bridge = new TrackingBridge();
        $this->settings_page = new SettingsPage($this->repository);
    }

    /**
     * Inizializza servizi e hook principali.
     */
    public function init(): void
    {
        if (! $this->checkRequirements()) {
            return;
        }

        $this->migrations->run();
        $this->settings_page->register();
        $this->checkout_bridge->register();
        $this->experience_event_bridge->register();
        $this->tracking_bridge->register();
    }

    /**
     * Esegue attività di attivazione.
     */
    public function activate(): void
    {
        if (! $this->checkRequirements()) {
            return;
        }

        Roles::addCapabilities();
        $this->migrations->run();
    }

    /**
     * Esegue attività di disattivazione.
     */
    public function deactivate(): void
    {
        Roles::removeCapabilities();
    }

    /**
     * Verifica requisiti minimi runtime.
     */
    private function checkRequirements(): bool
    {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            add_action('admin_notices', static function (): void {
                if (! current_user_can('activate_plugins')) {
                    return;
                }

                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html__('FP Discount Gift richiede PHP 8.0 o superiore.', 'fp-discount-gift')
                );
            });

            return false;
        }

        return true;
    }
}
