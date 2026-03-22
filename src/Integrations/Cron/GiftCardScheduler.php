<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\Cron;

use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;

use function add_action;
use function do_action;
use function get_option;
use function is_array;
use function sanitize_email;
use function wp_clear_scheduled_hook;
use function wp_next_scheduled;
use function wp_schedule_event;

/**
 * Scheduler per reminder e scadenza automatica gift card.
 */
final class GiftCardScheduler
{
    private const CRON_HOOK = 'fp_discountgift_cron_hourly';

    public function __construct(private readonly DiscountRuleRepository $repository)
    {
    }

    /**
     * Registra hook cron runtime.
     */
    public function register(): void
    {
        add_action('init', [$this, 'ensureScheduled']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    /**
     * Assicura schedulazione singola del cron.
     */
    public function ensureScheduled(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Esegue reminder "expiring soon" e expire automatico.
     */
    public function run(): void
    {
        $settings = get_option('fp_discountgift_settings', []);
        $settings = is_array($settings) ? $settings : [];

        $days = (int) ($settings['gift_card_reminder_days'] ?? 7);
        $days = $days > 0 ? $days : 7;

        $expiring_cards = $this->repository->getGiftCardsExpiringSoon($days, 200);
        foreach ($expiring_cards as $gift_card) {
            if ($this->repository->hasGiftCardExpiringSoonNotified($gift_card)) {
                continue;
            }

            $gift_card_id = (int) ($gift_card['id'] ?? 0);
            if ($gift_card_id <= 0) {
                continue;
            }

            $email = sanitize_email((string) ($gift_card['recipient_email'] ?? ''));
            $payload = [
                'gift_card_id' => $gift_card_id,
                'value' => (float) ($gift_card['current_balance'] ?? 0),
                'currency' => (string) ($gift_card['currency'] ?? 'EUR'),
                'expires_at' => (string) ($gift_card['expires_at'] ?? ''),
                'email' => $email,
                'user_data' => [
                    'em' => $email,
                ],
            ];

            do_action('fp_discountgift_gift_card_expiring_soon', (string) ($gift_card['code'] ?? ''), $payload);
            $this->repository->markGiftCardExpiringSoonNotified($gift_card_id);
        }

        if (! empty($settings['gift_card_auto_expire'])) {
            $expired_cards = $this->repository->expireOverdueGiftCards(200);
            foreach ($expired_cards as $gift_card) {
                $email = sanitize_email((string) ($gift_card['recipient_email'] ?? ''));
                do_action('fp_discountgift_gift_card_expired', (string) ($gift_card['code'] ?? ''), [
                    'gift_card_id' => (int) ($gift_card['id'] ?? 0),
                    'value' => (float) ($gift_card['current_balance'] ?? 0),
                    'currency' => (string) ($gift_card['currency'] ?? 'EUR'),
                    'expires_at' => (string) ($gift_card['expires_at'] ?? ''),
                    'email' => $email,
                    'user_data' => [
                        'em' => $email,
                    ],
                ]);
            }
        }
    }

    /**
     * Cancella hook cron su deactivation.
     */
    public static function clearScheduled(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}
