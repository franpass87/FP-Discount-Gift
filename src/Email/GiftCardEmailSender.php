<?php

declare(strict_types=1);

namespace FP\DiscountGift\Email;

use function apply_filters;
use function get_bloginfo;
use function home_url;
use function is_array;
use function is_wp_error;
use function preg_match;
use function sanitize_email;
use function sprintf;
use function wp_mail;
use function wp_remote_post;
use function wp_remote_retrieve_response_code;
use function wp_json_encode;

/**
 * Invia email gift card al destinatario.
 * Supporta wp_mail, Brevo Transactional API (htmlContent o templateId) e eventi fp_tracking_event per Brevo.
 */
final class GiftCardEmailSender
{
    private const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /**
     * Parametri disponibili per template Brevo (usa {{ params.NOME }} nel template).
     */
    public const BREVO_TEMPLATE_PARAMS = [
        'CODE',
        'AMOUNT',
        'CURRENCY',
        'EXPIRES_AT',
        'SITE_NAME',
        'SITE_URL',
        'CHECKOUT_URL',
        'MESSAGE',
    ];

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

        $body = $this->buildGiftCardCardHtml($giftCard);
        $body = apply_filters('fp_discountgift_gift_card_email_body', $body, $giftCard);

        $custom_sender = apply_filters('fp_discountgift_send_gift_card_email', null, $to, $subject, $body, $giftCard);
        if ($custom_sender === true) {
            return true;
        }
        if ($custom_sender === false) {
            return false;
        }

        $body = $this->finalizeGiftCardHtmlForTransport($body);

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
     * Restituisce parametri per template Brevo.
     *
     * @return array<string, string>
     */
    public function getBrevoTemplateParams(array $giftCard): array
    {
        $code = (string) ($giftCard['code'] ?? '');
        $amount = (string) ($giftCard['initial_balance'] ?? $giftCard['current_balance'] ?? '0');
        $currency = (string) ($giftCard['currency'] ?? 'EUR');
        $expires = (string) ($giftCard['expires_at'] ?? '');
        $siteName = get_bloginfo('name');
        $siteUrl = home_url();
        $checkoutUrl = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : $siteUrl;
        $message = sprintf(
            /* translators: 1: site name */
            __('%s ti ha inviato una gift card spendibile nel nostro negozio.', 'fp-discount-gift'),
            $siteName
        );

        $params = [
            'CODE' => $code,
            'AMOUNT' => $amount,
            'CURRENCY' => $currency,
            'EXPIRES_AT' => $expires,
            'SITE_NAME' => $siteName,
            'SITE_URL' => $siteUrl,
            'CHECKOUT_URL' => $checkoutUrl,
            'MESSAGE' => $message,
        ];

        return apply_filters('fp_discountgift_brevo_template_params', $params, $giftCard);
    }

    /**
     * Blocco HTML della gift card (senza documento esterno), da avvolgere con FP Mail SMTP o fallback locale.
     *
     * @param array<string, mixed> $giftCard
     */
    private function buildGiftCardCardHtml(array $giftCard): string
    {
        $code = (string) ($giftCard['code'] ?? '');
        $amount = (string) ($giftCard['initial_balance'] ?? $giftCard['current_balance'] ?? '0');
        $currency = (string) ($giftCard['currency'] ?? 'EUR');
        $expires = (string) ($giftCard['expires_at'] ?? '');
        $siteName = get_bloginfo('name');
        $siteUrl = home_url();
        $checkoutUrl = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : $siteUrl;

        $expiresRow = $expires !== ''
            ? sprintf(
                '<tr><td style="padding:8px 0;color:#64748b;font-size:14px;">%s</td><td style="padding:8px 0;text-align:right;font-weight:600;">%s</td></tr>',
                esc_html__('Valida fino al', 'fp-discount-gift'),
                esc_html($expires)
            )
            : '';

        $ctaLabel = esc_html__('Vai al checkout', 'fp-discount-gift');
        $ctaUrl = esc_url($checkoutUrl);

        return '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#fff;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,.05);overflow:hidden;">' .
            '<tr><td style="padding:32px 24px;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);text-align:center;">' .
            '<h1 style="margin:0;color:#fff;font-size:24px;font-weight:600;">' . esc_html__('Hai ricevuto una gift card!', 'fp-discount-gift') . '</h1>' .
            '<p style="margin:8px 0 0;color:rgba(255,255,255,.9);font-size:14px;">' . esc_html($siteName) . '</p>' .
            '</td></tr>' .
            '<tr><td style="padding:32px 24px;">' .
            '<p style="margin:0 0 20px;color:#475569;font-size:15px;line-height:1.6;">' . sprintf(
                /* translators: 1: site name */
                esc_html__('%s ti ha inviato una gift card spendibile nel nostro negozio.', 'fp-discount-gift'),
                esc_html($siteName)
            ) . '</p>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="16" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:20px 0;">' .
            '<tr><td style="text-align:center;padding:16px;">' .
            '<p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.05em;">' . esc_html__('Codice gift card', 'fp-discount-gift') . '</p>' .
            '<p style="margin:0;font-size:22px;font-weight:700;font-family:monospace;letter-spacing:.15em;color:#1e293b;">' . esc_html($code) . '</p>' .
            '</td></tr></table>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;">' .
            '<tr><td style="padding:8px 0;color:#64748b;font-size:14px;">' . esc_html__('Importo', 'fp-discount-gift') . '</td><td style="padding:8px 0;text-align:right;font-weight:600;font-size:16px;">' . esc_html($amount . ' ' . $currency) . '</td></tr>' .
            $expiresRow .
            '</table>' .
            '<p style="margin:24px 0;color:#64748b;font-size:14px;line-height:1.6;">' . esc_html__('Inserisci il codice nel campo coupon durante il checkout per utilizzare il saldo.', 'fp-discount-gift') . '</p>' .
            '<p style="margin:0;text-align:center;">' .
            '<a href="' . $ctaUrl . '" style="display:inline-block;background:#6366f1;color:#fff!important;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;">' . $ctaLabel . '</a>' .
            '</p></td></tr>' .
            '<tr><td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">' .
            '<p style="margin:0;color:#94a3b8;font-size:12px;">' . esc_html($siteName) . ' — ' . esc_url($siteUrl) . '</p>' .
            '</td></tr></table>';
    }

    /**
     * Avvolge il frammento con FP Mail SMTP; se il filtro ha restituito un documento HTML completo, non lo ri-avvolge.
     * Senza FP Mail, mantiene il layout precedente (sfondo grigio + card centrata).
     */
    private function finalizeGiftCardHtmlForTransport(string $html): string
    {
        if (preg_match('/<\s*!DOCTYPE/i', $html) === 1 || preg_match('/<\s*html[\s>]/i', $html) === 1) {
            return $html;
        }

        if (function_exists('fp_fpmail_brand_html')) {
            return fp_fpmail_brand_html($html);
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,sans-serif;">' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:24px 16px;">' .
            '<tr><td align="center">' .
            $html .
            '</td></tr></table></body></html>';
    }

    private function sendViaWpMail(string $to, string $subject, string $body): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return (bool) wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Invia via Brevo Transactional API.
     * Se è configurato un templateId, usa il template con params; altrimenti htmlContent.
     * Con FP Marketing Tracking Layer attivo il payload riceve i tag sito tramite {@see fp_tracking_brevo_merge_transactional_tags()}.
     */
    private function sendViaBrevo(string $to, string $subject, string $body, array $giftCard): bool
    {
        $settings = get_option('fp_discountgift_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $templateId = (int) ($settings['gift_card_brevo_template_id'] ?? 0);
        $templateId = (int) apply_filters('fp_discountgift_brevo_template_id', $templateId);

        $apiKey = $this->getBrevoApiKey();
        if ($apiKey === '') {
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
        ];

        if ($templateId > 0) {
            $payload['templateId'] = $templateId;
            $payload['params'] = $this->getBrevoTemplateParams($giftCard);
        } else {
            $payload['htmlContent'] = $body;
        }

        if (function_exists('fp_tracking_brevo_merge_transactional_tags')) {
            $payload = fp_tracking_brevo_merge_transactional_tags($payload);
        }

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

        return $this->getBrevoApiKey() !== '';
    }

    /**
     * Restituisce API key Brevo da FP-Tracking (centralizzato) o da fp_tracking_settings (fallback).
     */
    private function getBrevoApiKey(): string
    {
        if (function_exists('fp_tracking_get_brevo_settings')) {
            $central = fp_tracking_get_brevo_settings();
            if (! empty($central['enabled']) && ! empty($central['api_key'])) {
                return (string) $central['api_key'];
            }
        }

        $tracking = get_option('fp_tracking_settings', []);
        $apiKey = is_array($tracking) ? ($tracking['brevo_api_key'] ?? '') : '';

        return ! empty($tracking['brevo_enabled']) ? (string) $apiKey : '';
    }
}
