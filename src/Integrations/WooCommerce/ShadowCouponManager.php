<?php

declare(strict_types=1);

namespace FP\DiscountGift\Integrations\WooCommerce;

use Exception;
use WC_Coupon;

use function sanitize_text_field;
use function strtoupper;
use function wc_get_coupon_id_by_code;

/**
 * Gestisce i coupon tecnici (shadow) usati dal plugin.
 */
final class ShadowCouponManager
{
    public const SHADOW_META = '_fp_discountgift_shadow';
    public const RULE_META = '_fp_discountgift_rule_id';
    public const GIFT_CARD_META = '_fp_discountgift_gift_card_id';

    /**
     * Restituisce codice shadow normalizzato per una regola.
     */
    public function getShadowCode(string $rule_code): string
    {
        return 'FPDG-' . strtoupper(sanitize_text_field($rule_code));
    }

    /**
     * Crea o aggiorna shadow coupon per la regola passata.
     */
    public function ensureShadowCoupon(string $rule_code, int $rule_id, string $discount_type, float $amount): ?string
    {
        if (! class_exists('WC_Coupon')) {
            return null;
        }

        $shadow_code = $this->getShadowCode($rule_code);
        $coupon_id = (int) wc_get_coupon_id_by_code($shadow_code);
        $coupon = $coupon_id > 0 ? new WC_Coupon($coupon_id) : new WC_Coupon();

        $coupon->set_code($shadow_code);
        $coupon->set_discount_type($discount_type);
        $coupon->set_amount($amount);
        $coupon->set_individual_use(false);
        $coupon->set_usage_limit(0);
        $coupon->set_usage_limit_per_user(0);
        $coupon->set_description('FP Discount Gift shadow coupon');
        $coupon->update_meta_data(self::SHADOW_META, 'yes');
        $coupon->update_meta_data(self::RULE_META, $rule_id);

        try {
            $coupon->save();
        } catch (Exception) {
            return null;
        }

        return $shadow_code;
    }

    /**
     * Crea/aggiorna shadow coupon tecnico per gift card.
     */
    public function ensureGiftCardShadowCoupon(string $gift_card_code, int $gift_card_id, float $amount): ?string
    {
        if ($gift_card_id <= 0 || $amount <= 0) {
            return null;
        }

        $shadow_code = 'FPDGGC-' . strtoupper(sanitize_text_field($gift_card_code));
        if (! class_exists('WC_Coupon')) {
            return null;
        }

        $coupon_id = (int) wc_get_coupon_id_by_code($shadow_code);
        $coupon = $coupon_id > 0 ? new WC_Coupon($coupon_id) : new WC_Coupon();

        $coupon->set_code($shadow_code);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($amount);
        $coupon->set_individual_use(false);
        $coupon->set_usage_limit(0);
        $coupon->set_usage_limit_per_user(0);
        $coupon->set_description('FP Discount Gift gift card shadow coupon');
        $coupon->update_meta_data(self::SHADOW_META, 'yes');
        $coupon->update_meta_data(self::GIFT_CARD_META, $gift_card_id);

        try {
            $coupon->save();
        } catch (Exception) {
            return null;
        }

        return $shadow_code;
    }

    /**
     * Verifica se un coupon è shadow coupon FP Discount Gift.
     */
    public function isShadowCoupon(WC_Coupon $coupon): bool
    {
        return $coupon->get_meta(self::SHADOW_META) === 'yes';
    }

    /**
     * Verifica se coupon appartiene ai gift coupon di FP-Experiences.
     */
    public function isExperiencesGiftCoupon(WC_Coupon $coupon): bool
    {
        return $coupon->get_meta('_fp_exp_is_gift_coupon') === 'yes';
    }

    /**
     * Restituisce ID gift card associato allo shadow coupon, se presente.
     */
    public function getGiftCardId(WC_Coupon $coupon): int
    {
        return (int) $coupon->get_meta(self::GIFT_CARD_META);
    }
}
