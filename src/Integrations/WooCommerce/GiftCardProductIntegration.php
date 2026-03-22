<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\WooCommerce;

use FP\DiscountGift\Email\GiftCardEmailSender;
use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;
use WC_Order;
use WC_Product;
use WC_Order_Item_Product;

use function add_action;
use function add_filter;
use function get_option;
use function is_array;
use function is_email;
use function sanitize_email;
use function sanitize_text_field;
use function update_post_meta;
use function wc_add_notice;
use function wc_get_order;
use function wp_unslash;

/**
 * Integrazione prodotto WooCommerce tipo gift card.
 * Quando un prodotto marcato come gift card viene acquistato, emette automaticamente
 * una gift card e invia l'email al destinatario.
 */
final class GiftCardProductIntegration
{
    public const PRODUCT_META = '_fp_discountgift_is_gift_card_product';
    public const ORDER_META_RECIPIENT_EMAIL = '_fp_discountgift_gift_recipient_email';
    public const ORDER_META_RECIPIENT_NAME = '_fp_discountgift_gift_recipient_name';

    public function __construct(
        private readonly DiscountRuleRepository $repository
    ) {
    }

    /**
     * Registra hook WooCommerce.
     */
    public function register(): void
    {
        if (! class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_product_options_general_product_data', [$this, 'addProductOption']);
        add_action('woocommerce_process_product_meta', [$this, 'saveProductMeta']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'addVariationOption'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'saveVariationMeta'], 10, 2);

        add_filter('woocommerce_checkout_fields', [$this, 'addCheckoutFields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCheckoutMeta']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);

        add_action('woocommerce_order_status_completed', [$this, 'onOrderCompleted'], 10, 2);
    }

    /**
     * Aggiunge checkbox in scheda Generale prodotto.
     */
    public function addProductOption(): void
    {
        global $post;

        if (! $post instanceof \WP_Post) {
            return;
        }

        $product = wc_get_product($post->ID);
        if (! $product instanceof WC_Product) {
            return;
        }

        $is_gift = get_post_meta($post->ID, self::PRODUCT_META, true) === 'yes';

        woocommerce_wp_checkbox([
            'id' => self::PRODUCT_META,
            'value' => $is_gift ? 'yes' : 'no',
            'label' => __('Prodotto gift card', 'fp-discount-gift'),
            'description' => __('Se attivo, all\'acquisto verrà emessa una gift card e inviata via email al destinatario indicato al checkout.', 'fp-discount-gift'),
            'cbvalue' => 'yes',
        ]);
    }

    /**
     * Salva meta prodotto.
     */
    public function saveProductMeta(int $post_id): void
    {
        $value = isset($_POST[self::PRODUCT_META]) && $_POST[self::PRODUCT_META] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, self::PRODUCT_META, $value);
    }

    /**
     * Aggiunge opzione per varianti prodotto variabile.
     */
    public function addVariationOption(int $loop, array $variation_data, \WP_Post $variation): void
    {
        $is_gift = get_post_meta($variation->ID, self::PRODUCT_META, true) === 'yes';
        $var_key = 'variable_fp_discountgift_gift';

        echo '<div class="form-row form-row-full">';
        woocommerce_wp_checkbox([
            'id' => $var_key . $loop,
            'name' => $var_key . '[' . $loop . ']',
            'value' => $is_gift ? 'yes' : 'no',
            'label' => __('Prodotto gift card', 'fp-discount-gift'),
            'cbvalue' => 'yes',
        ]);
        echo '</div>';
    }

    /**
     * Salva meta variante.
     */
    public function saveVariationMeta(int $variation_id, int $loop): void
    {
        $var_key = 'variable_fp_discountgift_gift';
        $posted = isset($_POST[$var_key]) && is_array($_POST[$var_key])
            ? wp_unslash($_POST[$var_key])
            : [];
        $val = $posted[$loop] ?? '';
        $value = ($val === 'yes' || $val === '1') ? 'yes' : 'no';
        update_post_meta($variation_id, self::PRODUCT_META, $value);
    }

    /**
     * Aggiunge campi checkout se il carrello contiene prodotti gift card.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function addCheckoutFields(array $fields): array
    {
        if (! $this->cartHasGiftCardProducts()) {
            return $fields;
        }

        if (! isset($fields['order']) || ! is_array($fields['order'])) {
            $fields['order'] = [];
        }

        $fields['order'][self::ORDER_META_RECIPIENT_EMAIL] = [
            'type' => 'email',
            'label' => __('Email destinatario gift card', 'fp-discount-gift'),
            'placeholder' => 'destinatario@esempio.it',
            'required' => true,
            'priority' => 35,
            'class' => ['form-row-wide'],
        ];

        $fields['order'][self::ORDER_META_RECIPIENT_NAME] = [
            'type' => 'text',
            'label' => __('Nome destinatario gift card (opzionale)', 'fp-discount-gift'),
            'placeholder' => '',
            'required' => false,
            'priority' => 36,
            'class' => ['form-row-wide'],
        ];

        return $fields;
    }

    /**
     * Salva meta ordine da checkout.
     */
    public function saveCheckoutMeta(int $order_id): void
    {
        if (! $this->cartHasGiftCardProducts()) {
            return;
        }

        $email = isset($_POST[self::ORDER_META_RECIPIENT_EMAIL])
            ? sanitize_email(wp_unslash((string) $_POST[self::ORDER_META_RECIPIENT_EMAIL]))
            : '';
        $name = isset($_POST[self::ORDER_META_RECIPIENT_NAME])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::ORDER_META_RECIPIENT_NAME]))
            : '';

        if ($email !== '') {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order) {
                $order->update_meta_data(self::ORDER_META_RECIPIENT_EMAIL, $email);
                $order->update_meta_data(self::ORDER_META_RECIPIENT_NAME, $name);
                $order->save();
            }
        }
    }

    /**
     * Valida campi checkout quando presenti prodotti gift card.
     */
    public function validateCheckout(): void
    {
        if (! $this->cartHasGiftCardProducts()) {
            return;
        }

        $email = isset($_POST[self::ORDER_META_RECIPIENT_EMAIL])
            ? sanitize_email(wp_unslash((string) $_POST[self::ORDER_META_RECIPIENT_EMAIL]))
            : '';

        if ($email === '' || ! is_email($email)) {
            wc_add_notice(
                __('Inserisci l\'email del destinatario per la gift card.', 'fp-discount-gift'),
                'error'
            );
        }
    }

    /**
     * All'ordine completato: crea gift card per ogni riga gift card e invia email.
     */
    public function onOrderCompleted(int $order_id, ?WC_Order $order = null): void
    {
        $order = $order ?? wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return;
        }

        if ($order->get_meta('_fp_discountgift_product_cards_issued') === 'yes') {
            return;
        }

        $recipient_email = $order->get_meta(self::ORDER_META_RECIPIENT_EMAIL);
        if ($recipient_email === '' || ! is_email($recipient_email)) {
            return;
        }

        $settings = get_option('fp_discountgift_settings', []);
        $settings = is_array($settings) ? $settings : [];
        $send_email = ! empty($settings['gift_card_send_email']);

        $currency = $order->get_currency();
        $created_codes = [];
        $processed_any = false;

        foreach ($order->get_items() as $item) {
            if (! $item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            if (! $product instanceof WC_Product) {
                continue;
            }

            if (! $this->isGiftCardProduct($product)) {
                continue;
            }

            $qty = (int) $item->get_quantity();
            $line_total = (float) $item->get_total();
            $unit_amount = $qty > 0 ? $line_total / $qty : $line_total;

            for ($i = 0; $i < $qty; $i++) {
                $amount = $unit_amount;
                $payload = [
                    'amount' => $amount,
                    'currency' => $currency !== '' ? $currency : 'EUR',
                    'recipient_email' => $recipient_email,
                    'metadata' => [
                        'order_id' => $order_id,
                        'order_item_id' => $item->get_id(),
                        'source' => 'woocommerce_product',
                        'product_id' => $product->get_id(),
                        'product_name' => $product->get_name(),
                    ],
                    'note' => sprintf(
                        'Ordine #%d — %s (qty %d)',
                        $order_id,
                        $product->get_name(),
                        $qty
                    ),
                ];

                $gift_card_id = $this->repository->createGiftCard($payload);
                if ($gift_card_id <= 0) {
                    continue;
                }

                $created = $this->repository->getGiftCardById($gift_card_id);
                if (! is_array($created)) {
                    continue;
                }

                $gift_code = (string) ($created['code'] ?? '');
                $created_codes[] = $gift_code;

                do_action('fp_discountgift_gift_card_issued', $gift_code, [
                    'gift_card_id' => $gift_card_id,
                    'value' => $amount,
                    'currency' => $payload['currency'],
                    'email' => $recipient_email,
                    'order_id' => $order_id,
                    'source' => 'woocommerce_product',
                ]);

                if ($send_email && $gift_code !== '') {
                    $sender = new GiftCardEmailSender();
                    $sender->send($recipient_email, $created);
                }
            }
        }

        if ($processed_any) {
            if ($created_codes !== []) {
                $order->update_meta_data('_fp_discountgift_issued_codes', $created_codes);
            }
            $order->update_meta_data('_fp_discountgift_product_cards_issued', 'yes');
            $order->save();
        }
    }

    /**
     * Verifica se il prodotto (o la variante) è una gift card.
     */
    public function isGiftCardProduct(WC_Product $product): bool
    {
        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();

        if ($parent_id > 0) {
            $variation_is = get_post_meta($product_id, self::PRODUCT_META, true);
            if ($variation_is === 'yes') {
                return true;
            }
            $product_id = $parent_id;
        }

        return get_post_meta($product_id, self::PRODUCT_META, true) === 'yes';
    }

    /**
     * Verifica se il carrello contiene prodotti gift card.
     */
    private function cartHasGiftCardProducts(): bool
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if ($product instanceof WC_Product && $this->isGiftCardProduct($product)) {
                return true;
            }
        }

        return false;
    }
}
