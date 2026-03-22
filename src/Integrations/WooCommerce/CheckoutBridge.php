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
use function is_array;
use function min;
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
        if ($rule_code !== '') {
            $rule = $this->engine->evaluateByCode($rule_code, WC()->cart, $this->resolveCustomerEmail());
            if ($rule instanceof DiscountRule) {
                $shadow_code = $this->shadow_coupon_manager->ensureShadowCoupon(
                    $rule->code,
                    $rule->id,
                    $rule->discount_type,
                    $rule->amount
                );

                if (is_string($shadow_code) && $shadow_code !== '' && ! in_array($shadow_code, WC()->cart->get_applied_coupons(), true)) {
                    WC()->cart->apply_coupon($shadow_code);
                }
            }
        }

        $gift_code = (string) WC()->session->get('fp_discountgift_active_gift_card_code', '');
        $gift_amount = (float) WC()->session->get('fp_discountgift_active_gift_card_amount', 0);
        $gift_id = (int) WC()->session->get('fp_discountgift_active_gift_card_id', 0);
        if ($gift_code === '' || $gift_amount <= 0 || $gift_id <= 0) {
            return;
        }

        $gift_shadow_code = $this->shadow_coupon_manager->ensureGiftCardShadowCoupon($gift_code, $gift_id, $gift_amount);
        if (! is_string($gift_shadow_code) || $gift_shadow_code === '') {
            return;
        }

        if (! in_array($gift_shadow_code, WC()->cart->get_applied_coupons(), true)) {
            WC()->cart->apply_coupon($gift_shadow_code);
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

        $repository = $this->getRepository();
        if ($repository) {
            $gift_card = $repository->findGiftCardByCode($normalized_code);
            if (is_array($gift_card)) {
                $gift_status = (string) ($gift_card['status'] ?? '');
                $gift_balance = (float) ($gift_card['current_balance'] ?? 0);
                $gift_id = (int) ($gift_card['id'] ?? 0);
                $gift_subtotal = (float) WC()->cart->get_subtotal();
                $gift_apply_amount = min($gift_balance, $gift_subtotal);

                if ($gift_status !== 'active' || $gift_balance <= 0 || $gift_apply_amount <= 0 || $gift_id <= 0) {
                    do_action('fp_discountgift_discount_rejected', $normalized_code, [
                        'reason' => 'gift_card_not_active_or_empty',
                        'currency' => get_woocommerce_currency(),
                        'email' => $customer_email,
                        'user_data' => [
                            'em' => $customer_email,
                        ],
                    ]);

                    wc_add_notice(__('Gift card non disponibile o senza saldo.', 'fp-discount-gift'), 'error');
                    WC()->cart->remove_coupon($coupon_code);
                    return;
                }

                WC()->session->set('fp_discountgift_active_gift_card_code', $normalized_code);
                WC()->session->set('fp_discountgift_active_gift_card_amount', $gift_apply_amount);
                WC()->session->set('fp_discountgift_active_gift_card_id', $gift_id);

                $gift_shadow_code = $this->shadow_coupon_manager->ensureGiftCardShadowCoupon(
                    $normalized_code,
                    $gift_id,
                    $gift_apply_amount
                );

                if (is_string($gift_shadow_code) && $gift_shadow_code !== '' && ! in_array($gift_shadow_code, WC()->cart->get_applied_coupons(), true)) {
                    WC()->cart->apply_coupon($gift_shadow_code);
                }

                WC()->cart->remove_coupon($coupon_code);

                do_action('fp_discountgift_gift_card_applied', $normalized_code, [
                    'gift_card_id' => $gift_id,
                    'value' => $gift_apply_amount,
                    'currency' => (string) ($gift_card['currency'] ?? get_woocommerce_currency()),
                    'email' => $customer_email,
                    'user_data' => [
                        'em' => $customer_email,
                    ],
                ]);
                wc_add_notice(__('Gift card applicata correttamente.', 'fp-discount-gift'), 'success');
                return;
            }
        }

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
        if (str_starts_with($clean, 'FPDGGC-')) {
            $gift_code = (string) WC()->session->get('fp_discountgift_active_gift_card_code', '');
            $gift_amount = (float) WC()->session->get('fp_discountgift_active_gift_card_amount', 0);
            WC()->session->__unset('fp_discountgift_active_gift_card_code');
            WC()->session->__unset('fp_discountgift_active_gift_card_amount');
            WC()->session->__unset('fp_discountgift_active_gift_card_id');

            do_action('fp_discountgift_gift_card_removed', $gift_code !== '' ? $gift_code : $clean, [
                'value' => $gift_amount,
                'currency' => get_woocommerce_currency(),
                'email' => $this->resolveCustomerEmail(),
                'user_data' => [
                    'em' => $this->resolveCustomerEmail(),
                ],
            ]);

            return;
        }

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

        $repository = $this->getRepository();
        if ($repository === null) {
            return;
        }

        if ($active_code !== '' && WC()->cart) {
            $rule = $this->engine->evaluateByCode($active_code, WC()->cart, $this->resolveCustomerEmail());
            if ($rule instanceof DiscountRule) {
                $amount = $this->engine->calculateDiscountAmount($rule, WC()->cart);
                $repository->recordUsage(
                    $rule->id,
                    $order_id,
                    (int) $order->get_user_id(),
                    (string) $order->get_billing_email(),
                    $amount
                );
            }
        }

        if (! WC()->session) {
            return;
        }

        $gift_code = (string) WC()->session->get('fp_discountgift_active_gift_card_code', '');
        $gift_amount = (float) WC()->session->get('fp_discountgift_active_gift_card_amount', 0);
        if ($gift_code === '' || $gift_amount <= 0) {
            return;
        }

        $redeemed = $repository->redeemGiftCardByCode($gift_code, $gift_amount, $order_id, 0);
        if (is_array($redeemed)) {
            do_action('fp_discountgift_gift_card_redeemed', $gift_code, [
                'gift_card_id' => (int) ($redeemed['id'] ?? 0),
                'value' => (float) ($redeemed['redeemed_amount'] ?? $gift_amount),
                'remaining_balance' => (float) ($redeemed['current_balance'] ?? 0),
                'currency' => (string) ($redeemed['currency'] ?? $order->get_currency()),
                'order_id' => $order_id,
                'email' => (string) $order->get_billing_email(),
                'user_data' => [
                    'em' => (string) $order->get_billing_email(),
                    'fn' => (string) $order->get_billing_first_name(),
                    'ln' => (string) $order->get_billing_last_name(),
                    'ph' => (string) $order->get_billing_phone(),
                ],
            ]);
        }

        WC()->session->__unset('fp_discountgift_active_gift_card_code');
        WC()->session->__unset('fp_discountgift_active_gift_card_amount');
        WC()->session->__unset('fp_discountgift_active_gift_card_id');
        WC()->session->__unset('fp_discountgift_active_code');
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
