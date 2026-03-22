<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\Tracking;

use function add_action;
use function do_action;
use function is_array;

/**
 * Bridge eventi tracking verso fp_tracking_event.
 */
final class TrackingBridge
{
    /**
     * Registra hook di tracking plugin.
     */
    public function register(): void
    {
        add_action('fp_discountgift_discount_attempted', [$this, 'onDiscountAttempted'], 10, 2);
        add_action('fp_discountgift_discount_rejected', [$this, 'onDiscountRejected'], 10, 2);
        add_action('fp_discountgift_discount_applied', [$this, 'onDiscountApplied'], 10, 2);
        add_action('fp_discountgift_discount_removed', [$this, 'onDiscountRemoved'], 10, 2);
        add_action('fp_discountgift_gift_card_applied', [$this, 'onGiftCardApplied'], 10, 2);
        add_action('fp_discountgift_gift_card_redeemed', [$this, 'onGiftCardRedeemed'], 10, 2);
        add_action('fp_discountgift_gift_card_removed', [$this, 'onGiftCardRemoved'], 10, 2);
        add_action('fp_discountgift_gift_card_issued', [$this, 'onGiftCardIssued'], 10, 2);
        add_action('fp_discountgift_gift_card_expiring_soon', [$this, 'onGiftCardExpiringSoon'], 10, 2);
        add_action('fp_discountgift_gift_card_expired', [$this, 'onGiftCardExpired'], 10, 2);
        add_action('fp_discountgift_voucher_synced', [$this, 'onVoucherSynced'], 10, 6);
    }

    /**
     * Inoltra evento discount applied al tracking bus comune FP.
     *
     * @param array<string,mixed> $context
     */
    public function onDiscountApplied(string $rule_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'discount_applied', [
            'coupon' => $rule_code,
            'event_id' => $this->eventId('discount_applied'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra tentativo codice sconto.
     *
     * @param array<string,mixed> $context
     */
    public function onDiscountAttempted(string $coupon_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'discount_code_attempted', [
            'coupon' => $coupon_code,
            'event_id' => $this->eventId('discount_code_attempted'),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra rifiuto codice sconto.
     *
     * @param array<string,mixed> $context
     */
    public function onDiscountRejected(string $coupon_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'discount_code_rejected', [
            'coupon' => $coupon_code,
            'event_id' => $this->eventId('discount_code_rejected'),
            'reason' => (string) ($context['reason'] ?? 'unknown'),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra rimozione sconto.
     *
     * @param array<string,mixed> $context
     */
    public function onDiscountRemoved(string $coupon_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'discount_removed', [
            'coupon' => $coupon_code,
            'event_id' => $this->eventId('discount_removed'),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra applicazione gift card.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardApplied(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_applied', [
            'gift_card_code' => $gift_card_code,
            'gift_card_id' => (int) ($context['gift_card_id'] ?? 0),
            'event_id' => $this->eventId('gift_card_applied'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra riscatto saldo gift card.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardRedeemed(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_redeemed', [
            'gift_card_code' => $gift_card_code,
            'gift_card_id' => (int) ($context['gift_card_id'] ?? 0),
            'order_id' => (int) ($context['order_id'] ?? 0),
            'event_id' => $this->eventId('gift_card_redeemed'),
            'value' => (float) ($context['value'] ?? 0),
            'remaining_balance' => (float) ($context['remaining_balance'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra rimozione gift card dal carrello.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardRemoved(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_removed', [
            'gift_card_code' => $gift_card_code,
            'event_id' => $this->eventId('gift_card_removed'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra emissione gift card da admin.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardIssued(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_issued', [
            'gift_card_code' => $gift_card_code,
            'gift_card_id' => (int) ($context['gift_card_id'] ?? 0),
            'event_id' => $this->eventId('gift_card_issued'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra reminder gift card in scadenza.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardExpiringSoon(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_expiring_soon', [
            'gift_card_code' => $gift_card_code,
            'gift_card_id' => (int) ($context['gift_card_id'] ?? 0),
            'event_id' => $this->eventId('gift_card_expiring_soon'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'expires_at' => (string) ($context['expires_at'] ?? ''),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra evento gift card scaduta.
     *
     * @param array<string,mixed> $context
     */
    public function onGiftCardExpired(string $gift_card_code, array $context = []): void
    {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        do_action('fp_tracking_event', 'gift_card_expired', [
            'gift_card_code' => $gift_card_code,
            'gift_card_id' => (int) ($context['gift_card_id'] ?? 0),
            'event_id' => $this->eventId('gift_card_expired'),
            'value' => (float) ($context['value'] ?? 0),
            'currency' => (string) ($context['currency'] ?? 'EUR'),
            'expires_at' => (string) ($context['expires_at'] ?? ''),
            'email' => (string) ($context['email'] ?? ''),
            'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
            'source' => 'fp-discount-gift',
            'meta' => is_array($context) ? $context : [],
        ]);
    }

    /**
     * Inoltra al tracking layer i sync eventi voucher da FP-Experiences.
     *
     * @param array<string,mixed> $context
     */
    public function onVoucherSynced(
        string $status,
        int $voucher_id,
        int $order_id = 0,
        int $reservation_id = 0,
        array $context = []
    ): void {
        if (! defined('FP_TRACKING_VERSION')) {
            return;
        }

        if ($status === 'purchased') {
            do_action('fp_tracking_event', 'gift_voucher_purchased', [
                'voucher_id' => $voucher_id,
                'order_id' => $order_id,
                'event_id' => $this->eventId('gift_voucher_purchased'),
                'email' => (string) ($context['email'] ?? ''),
                'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
                'source' => 'fp-discount-gift',
                'meta' => is_array($context) ? $context : [],
            ]);
        }

        if ($status === 'redeemed') {
            do_action('fp_tracking_event', 'gift_voucher_redeemed', [
                'voucher_id' => $voucher_id,
                'order_id' => $order_id,
                'reservation_id' => $reservation_id,
                'event_id' => $this->eventId('gift_voucher_redeemed'),
                'email' => (string) ($context['email'] ?? ''),
                'user_data' => is_array($context['user_data'] ?? null) ? $context['user_data'] : [],
                'source' => 'fp-discount-gift',
                'meta' => is_array($context) ? $context : [],
            ]);
        }
    }

    /**
     * Genera event_id univoco per deduplicazione.
     */
    private function eventId(string $event_name): string
    {
        return 'fpdg_' . $event_name . '_' . uniqid('', true);
    }
}
