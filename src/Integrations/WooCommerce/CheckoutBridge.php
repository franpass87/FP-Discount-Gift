<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\WooCommerce;

use FP\DiscountGift\Application\DiscountEngine;
use FP\DiscountGift\Domain\DiscountRule;
use WC_Coupon;
use WC_Order;

use function add_action;
use function add_filter;
use function do_action;
use function sanitize_text_field;
use function strtoupper;
use function wc_add_notice;

/**
 * Bridge checkout WooCommerce per applicazione regole sconto FP.
 */
final class CheckoutBridge
{
    public function __construct(
        private readonly DiscountEngine $engine,
        private readonly ShadowCouponManager $shadow_coupon_manager
    ) {
    }

    /**
     * Registra hook WooCommerce se disponibile.
     */
    public function register(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_before_calculate_totals', [$this, 'maybeApplyRuleFromSession'], 20);
        add_action('woocommerce_applied_coupon', [$this, 'handleUserCoupon'], 10, 1);
        add_action('woocommerce_removed_coupon', [$this, 'handleRemovedCoupon'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'recordRuleUsage'], 10, 3);

        add_filter('woocommerce_coupon_is_valid', [$this, 'validateCouponCompatibility'], 20, 3);
    }

    /**
     * Applica regola in sessione se presente.
     */
    public function maybeApplyRuleFromSession(): void
    {
        if (! WC()->cart || ! WC()->session) {
            return;
        }

        $rule_code = (string) WC()->session->get('fp_discountgift_active_code', '');
        if ($rule_code === '') {
            return;
        }

        $rule = $this->engine->evaluateByCode($rule_code, WC()->cart, $this->resolveCustomerEmail());
        if (! $rule instanceof DiscountRule) {
            return;
        }

        $shadow_code = $this->shadow_coupon_manager->ensureShadowCoupon(
            $rule->code,
            $rule->id,
            $rule->discount_type,
            $rule->amount
        );

        if (! is_string($shadow_code) || $shadow_code === '') {
            return;
        }

        if (! in_array($shadow_code, WC()->cart->get_applied_coupons(), true)) {
            WC()->cart->apply_coupon($shadow_code);
        }
    }

    /**
     * Gestisce coupon manuale inserito dall'utente.
     */
    public function handleUserCoupon(string $coupon_code): void
    {
        if (! WC()->cart || ! WC()->session) {
            return;
        }

        $customer_email = $this->resolveCustomerEmail();
        $normalized_code = strtoupper(sanitize_text_field($coupon_code));

        $coupon = new WC_Coupon($coupon_code);
        if ($coupon->get_id() > 0 && $this->shadow_coupon_manager->isShadowCoupon($coupon)) {
            return;
        }

        if ($coupon->get_id() > 0 && $this->shadow_coupon_manager->isExperiencesGiftCoupon($coupon)) {
            return;
        }

        do_action('fp_discountgift_discount_attempted', $normalized_code, [
            'currency' => get_woocommerce_currency(),
            'email' => $customer_email,
            'user_data' => [
                'em' => $customer_email,
            ],
        ]);

        $rule = $this->engine->evaluateByCode($coupon_code, WC()->cart, $customer_email);
        if (! $rule instanceof DiscountRule) {
            do_action('fp_discountgift_discount_rejected', $normalized_code, [
                'reason' => 'rule_not_applicable',
                'currency' => get_woocommerce_currency(),
                'email' => $customer_email,
                'user_data' => [
                    'em' => $customer_email,
                ],
            ]);
            wc_add_notice(__('Codice sconto non valido per le regole FP.', 'fp-discount-gift'), 'error');
            WC()->cart->remove_coupon($coupon_code);
            return;
        }

        WC()->session->set('fp_discountgift_active_code', strtoupper($rule->code));
        $shadow_code = $this->shadow_coupon_manager->ensureShadowCoupon(
            $rule->code,
            $rule->id,
            $rule->discount_type,
            $rule->amount
        );

        if (is_string($shadow_code) && $shadow_code !== '' && ! in_array($shadow_code, WC()->cart->get_applied_coupons(), true)) {
            WC()->cart->apply_coupon($shadow_code);
        }

        WC()->cart->remove_coupon($coupon_code);
        do_action('fp_discountgift_discount_applied', $rule->code, [
            'value' => $this->engine->calculateDiscountAmount($rule, WC()->cart),
            'currency' => get_woocommerce_currency(),
            'email' => $customer_email,
            'user_data' => [
                'em' => $customer_email,
            ],
        ]);
        wc_add_notice(__('Codice sconto FP applicato.', 'fp-discount-gift'), 'success');
    }

    /**
     * Pulisce sessione alla rimozione coupon.
     */
    public function handleRemovedCoupon(string $coupon_code): void
    {
        if (! WC()->session) {
            return;
        }

        $clean = strtoupper(sanitize_text_field($coupon_code));
        if (str_starts_with($clean, 'FPDG-')) {
            WC()->session->__unset('fp_discountgift_active_code');
            do_action('fp_discountgift_discount_removed', $clean, [
                'currency' => get_woocommerce_currency(),
                'email' => $this->resolveCustomerEmail(),
                'user_data' => [
                    'em' => $this->resolveCustomerEmail(),
                ],
            ]);
        }
    }

    /**
     * Blocca conflitti tra coupon plugin e gift coupon Experiences.
     */
    public function validateCouponCompatibility(bool $valid, WC_Coupon $coupon, mixed $discount_obj = null): bool
    {
        if (! $valid) {
            return false;
        }

        if ($this->shadow_coupon_manager->isExperiencesGiftCoupon($coupon)) {
            return $valid;
        }

        if (! $this->shadow_coupon_manager->isShadowCoupon($coupon)) {
            return $valid;
        }

        if (! WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            $item_type = (string) ($item['_fp_exp_item_type'] ?? '');
            if ($item_type === 'gift') {
                return false;
            }
        }

        return true;
    }

    /**
     * Registra usage della regola su ordine completato.
     *
     * @param array<string,mixed> $posted_data
     */
    public function recordRuleUsage(int $order_id, array $posted_data, WC_Order $order): void
    {
        $active_code = '';
        if (WC()->session) {
            $active_code = (string) WC()->session->get('fp_discountgift_active_code', '');
        }

        if ($active_code === '') {
            return;
        }

        if (! WC()->cart) {
            return;
        }

        $rule = $this->engine->evaluateByCode($active_code, WC()->cart, $this->resolveCustomerEmail());
        if (! $rule instanceof DiscountRule) {
            return;
        }

        $repository = $this->getRepository();
        if ($repository === null) {
            return;
        }

        $amount = $this->engine->calculateDiscountAmount($rule, WC()->cart);
        $repository->recordUsage(
            $rule->id,
            $order_id,
            (int) $order->get_user_id(),
            (string) $order->get_billing_email(),
            $amount
        );
    }

    /**
     * Recupera email cliente corrente.
     */
    private function resolveCustomerEmail(): string
    {
        if (! WC()->customer) {
            return '';
        }

        return sanitize_text_field((string) WC()->customer->get_billing_email());
    }

    /**
     * Carica repository solo quando serve.
     */
    private function getRepository(): ?\FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository
    {
        if (! class_exists(\FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository::class)) {
            return null;
        }

        return new \FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository();
    }
}
