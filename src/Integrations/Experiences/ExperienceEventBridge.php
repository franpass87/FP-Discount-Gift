<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\Experiences;

use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;
use WC_Order;

use function add_action;
use function do_action;
use function sanitize_email;
use function sanitize_text_field;
use function wc_get_order;

/**
 * Sincronizza eventi voucher da FP-Experiences verso il ledger locale.
 */
final class ExperienceEventBridge
{
    /**
     * Registra listener verso eventi FP-Experiences.
     */
    public function register(): void
    {
        add_action('fp_exp_gift_purchased', [$this, 'handleVoucherPurchased'], 10, 2);
        add_action('fp_exp_gift_voucher_redeemed', [$this, 'handleVoucherRedeemed'], 10, 3);
    }

    /**
     * Registra evento acquisto voucher.
     */
    public function handleVoucherPurchased(int $voucher_id, int $order_id): void
    {
        $contact = $this->buildContactPayload($order_id);
        $payload = [
            'source' => 'fp-experiences',
            'status' => 'purchased',
        ] + $contact;

        $this->repository()->recordVoucherEvent(
            'fp_exp_gift_purchased',
            $voucher_id,
            $order_id,
            0,
            $payload
        );

        do_action('fp_discountgift_voucher_synced', 'purchased', $voucher_id, $order_id, 0, $payload);
    }

    /**
     * Registra evento riscatto voucher.
     */
    public function handleVoucherRedeemed(int $voucher_id, int $order_id, int $reservation_id): void
    {
        $contact = $this->buildContactPayload($order_id);
        $payload = [
            'source' => 'fp-experiences',
            'status' => 'redeemed',
        ] + $contact;

        $this->repository()->recordVoucherEvent(
            'fp_exp_gift_voucher_redeemed',
            $voucher_id,
            $order_id,
            $reservation_id,
            $payload
        );

        do_action('fp_discountgift_voucher_synced', 'redeemed', $voucher_id, $order_id, $reservation_id, $payload);
    }

    /**
     * Restituisce repository eventi.
     */
    private function repository(): DiscountRuleRepository
    {
        return new DiscountRuleRepository();
    }

    /**
     * Costruisce payload contatto per tracking layer/Brevo.
     *
     * @return array<string,mixed>
     */
    private function buildContactPayload(int $order_id): array
    {
        if ($order_id <= 0 || ! function_exists('wc_get_order')) {
            return [];
        }

        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return [];
        }

        $email = sanitize_email((string) $order->get_billing_email());
        $first_name = sanitize_text_field((string) $order->get_billing_first_name());
        $last_name = sanitize_text_field((string) $order->get_billing_last_name());
        $phone = sanitize_text_field((string) $order->get_billing_phone());

        $user_data = [];
        if ($email !== '') {
            $user_data['em'] = $email;
        }
        if ($first_name !== '') {
            $user_data['fn'] = $first_name;
        }
        if ($last_name !== '') {
            $user_data['ln'] = $last_name;
        }
        if ($phone !== '') {
            $user_data['ph'] = $phone;
        }

        $payload = [];
        if ($email !== '') {
            $payload['email'] = $email;
        }
        if ($user_data !== []) {
            $payload['user_data'] = $user_data;
        }

        return $payload;
    }
}
