<?php

declare(strict_types=1);

namespace FP\DiscountGift\Email;

use function apply_filters;
use function get_bloginfo;
use function home_url;
use function sanitize_email;
use function sprintf;
use function wp_mail;
use function wp_remote_post;

/**
 * Invia email gift card al destinatario.
 * Supporta wp_mail e opzionale Brevo Transactional API (se configurato in FP-Marketing-Tracking-Layer).
 */
final class GiftCardEmailSender
{
    private const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Invia email con codice e dettagli gift card.
     *
     * @param string $to Email destinatario
     * @param array<string, mixed> $giftCard Dati gift card (code, initial_balance, currency, expires_at)
     * @return bool True se inviata con successo
     */
    public function send(string $to, array $giftCard): bool
    {
        $to = sanitize_email($to);
        if ($to === '') {
            return false;
        }

        $subject = apply_filters(
            'fp_discountgift_gift_card_email_subject',
            sprintf(
                /* translators: 1: site name */
                __('Hai ricevuto una gift card da %s', 'fp-discount-gift'),
                get_bloginfo('name')
            )
        );

        $body = $this->buildBody($giftCard);
        $body = apply_filters('fp_discountgift_gift_card_email_body', $body, $giftCard);

        $custom_sender = apply_filters('fp_discountgift_send_gift_card_email', null, $to, $subject, $body, $giftCard);
        if ($custom_sender === true) {
            return true;
        }
        if ($custom_sender === false) {
            return false;
        }

        $use_brevo = $this->shouldUseBrevo();
        if ($use_brevo) {
            $sent = $this->sendViaBrevo($to, $subject, $body, $giftCard);
            if ($sent) {
                return true;
            }
        }

        return $this->sendViaWpMail($to, $subject, $body);
    }

    /**
     * Costruisce corpo email in HTML.
     *
     * @param array<string, mixed> $giftCard
     */
    private function buildBody(array $giftCard): string
    {
        $code = (string) ($giftCard['code'] ?? '');
        $amount = (string) ($giftCard['initial_balance'] ?? $giftCard['current_balance'] ?? '0');
        $currency = (string) ($giftCard['currency'] ?? 'EUR');
        $expires = (string) ($giftCard['expires_at'] ?? '');
        $siteName = get_bloginfo('name');
        $siteUrl = home_url();
        $checkoutUrl = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : $siteUrl;

        $expiresHtml = $expires !== ''
            ? sprintf(
                '<p>%s: <strong>%s</strong></p>',
                esc_html__('Valida fino al', 'fp-discount-gift'),
                esc_html($expires)
            )
            : '';

        return '<div style="font-family:sans-serif;max-width:500px;">' .
            '<h2>' . esc_html__('Hai ricevuto una gift card!', 'fp-discount-gift') . '</h2>' .
            '<p>' . sprintf(
                /* translators: 1: site name */
                esc_html__('%s ti ha inviato una gift card spendibile nel nostro negozio.', 'fp-discount-gift'),
                esc_html($siteName)
            ) . '</p>' .
            '<p><strong>' . esc_html__('Codice gift card', 'fp-discount-gift') . ':</strong><br>' .
            '<code style="font-size:18px;background:#f0f0f0;padding:8px 12px;display:inline-block;margin:8px 0;">' . esc_html($code) . '</code></p>' .
            '<p><strong>' . esc_html__('Importo', 'fp-discount-gift') . ':</strong> ' . esc_html($amount . ' ' . $currency) . '</p>' .
            $expiresHtml .
            '<p>' . esc_html__('Inserisci il codice nel carrello durante il checkout per utilizzare il saldo.', 'fp-discount-gift') . '</p>' .
            '<p><a href="' . esc_url($checkoutUrl) . '" style="display:inline-block;background:#667eea;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;">' .
            esc_html__('Vai al checkout', 'fp-discount-gift') . '</a></p>' .
            '<p style="color:#666;font-size:12px;">' . esc_html($siteName) . ' — ' . esc_url($siteUrl) . '</p>' .
            '</div>';
    }

    private function sendViaWpMail(string $to, string $subject, string $body): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return (bool) wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Invia via Brevo Transactional API se disponibile.
     */
    private function sendViaBrevo(string $to, string $subject, string $body, array $giftCard): bool
    {
        $settings = get_option('fp_tracking_settings', []);
        $apiKey = is_array($settings) ? ($settings['brevo_api_key'] ?? '') : '';
        if ($apiKey === '' || ! is_string($apiKey)) {
            return false;
        }

        $fromEmail = apply_filters('wp_mail_from', get_option('admin_email'));
        $fromName = apply_filters('wp_mail_from_name', get_bloginfo('name'));

        $payload = [
            'sender' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'to' => [
                ['email' => $to],
            ],
            'subject' => $subject,
            'htmlContent' => $body,
        ];

        $response = wp_remote_post(
            self::BREVO_ENDPOINT,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'api-key' => $apiKey,
                ],
                'body' => wp_json_encode($payload),
                'timeout' => 15,
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    private function shouldUseBrevo(): bool
    {
        $settings = get_option('fp_discountgift_settings', []);
        if (! is_array($settings) || empty($settings['gift_card_email_via_brevo'])) {
            return false;
        }

        $tracking = get_option('fp_tracking_settings', []);
        $apiKey = is_array($tracking) ? ($tracking['brevo_api_key'] ?? '') : '';

        return $apiKey !== '' && ! empty($tracking['brevo_enabled']);
    }
}
