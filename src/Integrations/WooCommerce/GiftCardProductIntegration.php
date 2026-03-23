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
    public const PRODUCT_META_CUSTOM_AMOUNT = '_fp_discountgift_gift_custom_amount';
    public const PRODUCT_META_MIN_AMOUNT = '_fp_discountgift_gift_min_amount';
    public const PRODUCT_META_MAX_AMOUNT = '_fp_discountgift_gift_max_amount';
    public const CART_ITEM_AMOUNT = 'fp_discountgift_amount';
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

        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderAmountField']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCart'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 4);
        add_filter('woocommerce_add_cart_item', [$this, 'setCartItemPrice'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'applyCartItemPrice'], 15);
        add_filter('woocommerce_get_item_data', [$this, 'displayCartItemData'], 10, 2);

        add_filter('woocommerce_checkout_fields', [$this, 'addCheckoutFields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCheckoutMeta']);
        add_action('woocommerce_checkout_process', [$this, 'validateCheckout']);

        add_action('woocommerce_order_status_completed', [$this, 'onOrderCompleted'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveOrderItemAmount'], 10, 4);
        add_filter('woocommerce_available_variation', [$this, 'addVariationCustomAmountData'], 10, 3);
    }

    /**
     * Verifica se il prodotto richiede importo personalizzato.
     */
    public function hasCustomAmount(WC_Product $product): bool
    {
        $id = $product->get_id();
        $parent = $product->get_parent_id();
        if ($parent > 0) {
            return get_post_meta($id, self::PRODUCT_META_CUSTOM_AMOUNT, true) === 'yes';
        }
        return get_post_meta($id, self::PRODUCT_META_CUSTOM_AMOUNT, true) === 'yes';
    }

    /**
     * Restituisce importo minimo (0 se non impostato).
     */
    public function getMinAmount(WC_Product $product): float
    {
        $id = $product->get_id();
        $v = get_post_meta($id, self::PRODUCT_META_MIN_AMOUNT, true);
        if ($v !== '') {
            return (float) $v;
        }
        $parent = $product->get_parent_id();
        if ($parent > 0) {
            $v = get_post_meta($parent, self::PRODUCT_META_MIN_AMOUNT, true);
            return $v !== '' ? (float) $v : 0;
        }
        return 0;
    }

    /**
     * Restituisce importo massimo (0 se non impostato).
     */
    public function getMaxAmount(WC_Product $product): float
    {
        $id = $product->get_id();
        $v = get_post_meta($id, self::PRODUCT_META_MAX_AMOUNT, true);
        if ($v !== '') {
            return (float) $v;
        }
        $parent = $product->get_parent_id();
        if ($parent > 0) {
            $v = get_post_meta($parent, self::PRODUCT_META_MAX_AMOUNT, true);
            return $v !== '' ? (float) $v : 0;
        }
        return 0;
    }

    /**
     * Salva importo come meta ordine per display.
     */
    public function saveOrderItemAmount(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order): void
    {
        $amount = (float) ($values[self::CART_ITEM_AMOUNT] ?? 0);
        if ($amount > 0) {
            $item->add_meta_data('_' . self::CART_ITEM_AMOUNT, $amount, true);
        }
    }

    /**
     * Aggiunge dati custom amount alla variazione per JS.
     *
     * @param array $data
     * @param WC_Product $product
     * @param \WC_Product_Variation $variation
     * @return array
     */
    public function addVariationCustomAmountData(array $data, WC_Product $product, \WC_Product_Variation $variation): array
    {
        if (! $this->isGiftCardProduct($variation)) {
            $data['fp_discountgift_custom_amount'] = false;
            return $data;
        }
        $data['fp_discountgift_custom_amount'] = $this->hasCustomAmount($variation);
        $data['fp_discountgift_min'] = $this->getMinAmount($variation);
        $data['fp_discountgift_max'] = $this->getMaxAmount($variation);
        return $data;
    }

    /**
     * Script inline per show/hide campo importo su prodotti variabili.
     */
    private function getVariationAmountScript(): string
    {
        return "
jQuery(function($) {
    var form = $('.variations_form');
    if (!form.length) return;
    var wrapper = $('#fp_discountgift_amount_wrapper');
    var input = $('#fp_discountgift_amount');
    if (!wrapper.length || !input.length) return;
    form.on('found_variation', function(e, variation) {
        if (variation && variation.fp_discountgift_custom_amount) {
            wrapper.show();
            input.prop('required', true).attr('min', variation.fp_discountgift_min || '').attr('max', variation.fp_discountgift_max || '').val('');
        } else {
            wrapper.hide();
            input.prop('required', false).val('');
        }
    });
    form.on('reset_data hide_variation', function() {
        wrapper.hide();
        input.prop('required', false).val('');
    });
});
";
    }

    /**
     * Aggiunge checkbox e opzioni importo in scheda Generale prodotto.
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
        $custom_amount = get_post_meta($post->ID, self::PRODUCT_META_CUSTOM_AMOUNT, true) === 'yes';
        $min = get_post_meta($post->ID, self::PRODUCT_META_MIN_AMOUNT, true) ?: '';
        $max = get_post_meta($post->ID, self::PRODUCT_META_MAX_AMOUNT, true) ?: '';

        woocommerce_wp_checkbox([
            'id' => self::PRODUCT_META,
            'value' => $is_gift ? 'yes' : 'no',
            'label' => __('Prodotto gift card', 'fp-discount-gift'),
            'description' => __('Se attivo, all\'acquisto verrà emessa una gift card e inviata via email al destinatario indicato al checkout.', 'fp-discount-gift'),
            'cbvalue' => 'yes',
        ]);

        echo '<div class="options_group fp_discountgift_amount_options">';
        woocommerce_wp_checkbox([
            'id' => self::PRODUCT_META_CUSTOM_AMOUNT,
            'value' => $custom_amount ? 'yes' : 'no',
            'label' => __('Importo scelto dal cliente', 'fp-discount-gift'),
            'description' => __('Il cliente inserisce l\'importo desiderato; quel valore diventa il prezzo e il saldo gift card.', 'fp-discount-gift'),
            'cbvalue' => 'yes',
        ]);
        woocommerce_wp_text_input([
            'id' => self::PRODUCT_META_MIN_AMOUNT,
            'value' => $min,
            'label' => __('Importo minimo (€)', 'fp-discount-gift'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'placeholder' => __('Nessun minimo', 'fp-discount-gift'),
        ]);
        woocommerce_wp_text_input([
            'id' => self::PRODUCT_META_MAX_AMOUNT,
            'value' => $max,
            'label' => __('Importo massimo (€)', 'fp-discount-gift'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'placeholder' => __('Nessun massimo', 'fp-discount-gift'),
        ]);
        echo '</div>';
    }

    /**
     * Salva meta prodotto.
     */
    public function saveProductMeta(int $post_id): void
    {
        $value = isset($_POST[self::PRODUCT_META]) && sanitize_text_field(wp_unslash((string) $_POST[self::PRODUCT_META])) === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, self::PRODUCT_META, $value);

        $custom = isset($_POST[self::PRODUCT_META_CUSTOM_AMOUNT]) && sanitize_text_field(wp_unslash((string) $_POST[self::PRODUCT_META_CUSTOM_AMOUNT])) === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, self::PRODUCT_META_CUSTOM_AMOUNT, $custom);

        $min = isset($_POST[self::PRODUCT_META_MIN_AMOUNT]) ? (float) wp_unslash($_POST[self::PRODUCT_META_MIN_AMOUNT]) : 0;
        $max = isset($_POST[self::PRODUCT_META_MAX_AMOUNT]) ? (float) wp_unslash($_POST[self::PRODUCT_META_MAX_AMOUNT]) : 0;
        update_post_meta($post_id, self::PRODUCT_META_MIN_AMOUNT, $min > 0 ? $min : '');
        update_post_meta($post_id, self::PRODUCT_META_MAX_AMOUNT, $max > 0 ? $max : '');
    }

    /**
     * Aggiunge opzione per varianti prodotto variabile.
     */
    public function addVariationOption(int $loop, array $variation_data, \WP_Post $variation): void
    {
        $is_gift = get_post_meta($variation->ID, self::PRODUCT_META, true) === 'yes';
        $custom_amount = get_post_meta($variation->ID, self::PRODUCT_META_CUSTOM_AMOUNT, true) === 'yes';
        $var_key = 'variable_fp_discountgift_gift';
        $var_custom = 'variable_fp_discountgift_gift_custom';

        echo '<div class="form-row form-row-full">';
        woocommerce_wp_checkbox([
            'id' => $var_key . $loop,
            'name' => $var_key . '[' . $loop . ']',
            'value' => $is_gift ? 'yes' : 'no',
            'label' => __('Prodotto gift card', 'fp-discount-gift'),
            'cbvalue' => 'yes',
        ]);
        woocommerce_wp_checkbox([
            'id' => $var_custom . $loop,
            'name' => $var_custom . '[' . $loop . ']',
            'value' => $custom_amount ? 'yes' : 'no',
            'label' => __('Importo scelto dal cliente', 'fp-discount-gift'),
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
        $var_custom = 'variable_fp_discountgift_gift_custom';
        $posted = isset($_POST[$var_key]) && is_array($_POST[$var_key]) ? array_map('sanitize_text_field', wp_unslash($_POST[$var_key])) : [];
        $posted_custom = isset($_POST[$var_custom]) && is_array($_POST[$var_custom]) ? array_map('sanitize_text_field', wp_unslash($_POST[$var_custom])) : [];
        $val = $posted[$loop] ?? '';
        $val_custom = $posted_custom[$loop] ?? '';
        update_post_meta($variation_id, self::PRODUCT_META, ($val === 'yes' || $val === '1') ? 'yes' : 'no');
        update_post_meta($variation_id, self::PRODUCT_META_CUSTOM_AMOUNT, ($val_custom === 'yes' || $val_custom === '1') ? 'yes' : 'no');
    }

    /**
     * Renderizza campo importo su pagina prodotto (solo se gift card con importo personalizzato).
     */
    public function renderAmountField(): void
    {
        global $product;

        if (! $product instanceof WC_Product || ! $this->isGiftCardProduct($product)) {
            return;
        }

        if (! $this->hasCustomAmount($product)) {
            return;
        }

        $min = $this->getMinAmount($product);
        $max = $this->getMaxAmount($product);
        $step = 0.01;
        $min_attr = $min > 0 ? ' min="' . esc_attr((string) $min) . '"' : '';
        $max_attr = $max > 0 ? ' max="' . esc_attr((string) $max) . '"' : '';
        $placeholder = $min > 0 && $max > 0
            ? sprintf(__('da %s a %s €', 'fp-discount-gift'), number_format($min, 2, ',', ''), number_format($max, 2, ',', ''))
            : ($min > 0 ? sprintf(__('min %s €', 'fp-discount-gift'), number_format($min, 2, ',', '')) : __('Importo (€)', 'fp-discount-gift'));

        $is_variable = $product->is_type('variable');
        $wrapper_style = $is_variable ? 'margin-bottom:1em;display:none;' : 'margin-bottom:1em;';
        ?>
        <div class="fp-discountgift-amount-field" id="fp_discountgift_amount_wrapper" style="<?php echo esc_attr($wrapper_style); ?>"
             data-min="<?php echo esc_attr((string) $min); ?>" data-max="<?php echo esc_attr((string) $max); ?>">
            <label for="fp_discountgift_amount"><?php esc_html_e('Importo gift card (€)', 'fp-discount-gift'); ?></label>
            <input type="number" name="<?php echo esc_attr(self::CART_ITEM_AMOUNT); ?>" id="fp_discountgift_amount"
                   step="<?php echo esc_attr((string) $step); ?>"<?php echo $min_attr . $max_attr; ?>
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   <?php echo $is_variable ? '' : ' required'; ?>
                   style="width:120px;padding:8px 12px;margin-left:8px;">
        </div>
        <?php
        if ($is_variable) {
            wp_add_inline_script('wc-add-to-cart-variation', $this->getVariationAmountScript());
        }
    }

    /**
     * Valida importo al momento dell'aggiunta al carrello.
     *
     * @param bool $passed
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     * @param array $variations
     * @return bool
     */
    public function validateAddToCart(bool $passed, int $product_id, int $quantity, int $variation_id, array $variations = []): bool
    {
        $product = $variation_id > 0 ? wc_get_product($variation_id) : wc_get_product($product_id);
        if (! $product instanceof WC_Product || ! $this->isGiftCardProduct($product) || ! $this->hasCustomAmount($product)) {
            return $passed;
        }

        $amount = isset($_POST[self::CART_ITEM_AMOUNT]) ? (float) wp_unslash($_POST[self::CART_ITEM_AMOUNT]) : 0;
        $min = $this->getMinAmount($product);
        $max = $this->getMaxAmount($product);

        if ($amount <= 0) {
            wc_add_notice(__('Inserisci un importo valido per la gift card.', 'fp-discount-gift'), 'error');
            return false;
        }
        if ($min > 0 && $amount < $min) {
            wc_add_notice(sprintf(__('L\'importo minimo è %s €.', 'fp-discount-gift'), number_format($min, 2, ',', '')), 'error');
            return false;
        }
        if ($max > 0 && $amount > $max) {
            wc_add_notice(sprintf(__('L\'importo massimo è %s €.', 'fp-discount-gift'), number_format($max, 2, ',', '')), 'error');
            return false;
        }

        return $passed;
    }

    /**
     * Salva importo nei dati carrello.
     *
     * @param array $cart_item_data
     * @param int $product_id
     * @param int $variation_id
     * @param int $quantity
     * @return array
     */
    public function addCartItemData(array $cart_item_data, int $product_id, int $variation_id, int $quantity): array
    {
        $product = $variation_id > 0 ? wc_get_product($variation_id) : wc_get_product($product_id);
        if (! $product instanceof WC_Product || ! $this->isGiftCardProduct($product) || ! $this->hasCustomAmount($product)) {
            return $cart_item_data;
        }

        $amount = isset($_POST[self::CART_ITEM_AMOUNT]) ? (float) wp_unslash($_POST[self::CART_ITEM_AMOUNT]) : 0;
        if ($amount > 0) {
            $cart_item_data[self::CART_ITEM_AMOUNT] = $amount;
        }

        return $cart_item_data;
    }

    /**
     * Imposta prezzo carrello in base all'importo scelto.
     *
     * @param array $cart_item
     * @param string $cart_item_key
     * @return array
     */
    public function setCartItemPrice(array $cart_item, string $cart_item_key): array
    {
        $amount = (float) ($cart_item[self::CART_ITEM_AMOUNT] ?? 0);
        if ($amount <= 0) {
            return $cart_item;
        }

        $product = $cart_item['data'] ?? null;
        if (! $product instanceof WC_Product) {
            return $cart_item;
        }

        $product->set_price($amount);
        $product->set_regular_price($amount);
        $product->set_sale_price('');

        return $cart_item;
    }

    /**
     * Applica prezzo custom su ricalcolo totali carrello (sessione).
     */
    public function applyCartItemPrice(\WC_Cart $cart): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $amount = (float) ($cart_item[self::CART_ITEM_AMOUNT] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $product = $cart_item['data'] ?? null;
            if (! $product instanceof WC_Product) {
                continue;
            }

            $product->set_price($amount);
            $product->set_regular_price($amount);
            $product->set_sale_price('');
        }
    }

    /**
     * Mostra importo nei dati articolo carrello/checkout.
     *
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public function displayCartItemData(array $item_data, array $cart_item): array
    {
        $amount = (float) ($cart_item[self::CART_ITEM_AMOUNT] ?? 0);
        if ($amount <= 0) {
            return $item_data;
        }

        $item_data[] = [
            'key' => __('Importo gift card', 'fp-discount-gift'),
            'value' => wc_price($amount),
        ];

        return $item_data;
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

                $processed_any = true;

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
                    'user_data' => [
                        'em' => $recipient_email,
                    ],
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
