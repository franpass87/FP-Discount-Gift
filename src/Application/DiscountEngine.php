<?php

declare(strict_types=1);

namespace FP\DiscountGift\Application;

use FP\DiscountGift\Domain\DiscountRule;
use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;
use WC_Cart;

use function array_filter;
use function array_intersect;
use function array_map;
use function current_datetime;
use function floatval;
use function in_array;
use function is_email;
use function is_string;
use function sanitize_email;
use function wc_get_coupon_types;

/**
 * Motore di valutazione regole sconto.
 */
final class DiscountEngine
{
    public function __construct(private readonly DiscountRuleRepository $repository)
    {
    }

    /**
     * Cerca una regola applicabile per codice digitato dall'utente.
     */
    public function evaluateByCode(string $code, ?WC_Cart $cart, string $customer_email = ''): ?DiscountRule
    {
        if ($cart === null) {
            return null;
        }

        $rule = $this->repository->findByCode($code);
        if (! $rule instanceof DiscountRule || ! $rule->isActive()) {
            return null;
        }

        return $this->isRuleApplicable($rule, $cart, $customer_email) ? $rule : null;
    }

    /**
     * Restituisce la miglior regola applicabile al carrello.
     */
    public function findBestRule(?WC_Cart $cart, string $customer_email = ''): ?DiscountRule
    {
        if ($cart === null) {
            return null;
        }

        $active_rules = $this->repository->getActiveRules();
        $eligible = array_filter($active_rules, fn (DiscountRule $rule): bool => $this->isRuleApplicable($rule, $cart, $customer_email));

        if ($eligible === []) {
            return null;
        }

        usort($eligible, function (DiscountRule $a, DiscountRule $b) use ($cart): int {
            $a_amount = $this->calculateDiscountAmount($a, $cart);
            $b_amount = $this->calculateDiscountAmount($b, $cart);
            return $b_amount <=> $a_amount;
        });

        return $eligible[0] ?? null;
    }

    /**
     * Calcola importo sconto per una regola.
     */
    public function calculateDiscountAmount(DiscountRule $rule, WC_Cart $cart): float
    {
        $subtotal = (float) $cart->get_subtotal();
        if ($rule->discount_type === 'percent') {
            return round(max(0, ($subtotal * $rule->amount) / 100), 2);
        }

        return round(max(0, min($subtotal, $rule->amount)), 2);
    }

    /**
     * Verifica se regola è applicabile al carrello corrente.
     */
    private function isRuleApplicable(DiscountRule $rule, WC_Cart $cart, string $customer_email): bool
    {
        if (! $rule->isActive()) {
            return false;
        }

        if (! in_array($rule->discount_type, wc_get_coupon_types(), true)) {
            return false;
        }

        if (! $this->checkDates($rule)) {
            return false;
        }

        $subtotal = (float) $cart->get_subtotal();
        if ($rule->minimum_amount !== null && $subtotal < $rule->minimum_amount) {
            return false;
        }

        if ($rule->maximum_amount !== null && $subtotal > $rule->maximum_amount) {
            return false;
        }

        if ($rule->usage_limit !== null && $this->repository->countUsage($rule->id) >= $rule->usage_limit) {
            return false;
        }

        if (! $this->checkPerUserLimit($rule, $customer_email)) {
            return false;
        }

        if (! $this->checkEmailRestriction($rule, $customer_email)) {
            return false;
        }

        if (! $this->checkProductRestrictions($rule, $cart)) {
            return false;
        }

        return $this->checkRoleRestriction($rule);
    }

    /**
     * Verifica vincoli data scadenza.
     */
    private function checkDates(DiscountRule $rule): bool
    {
        if (! is_string($rule->date_expires) || $rule->date_expires === '') {
            return true;
        }

        $expires_ts = strtotime($rule->date_expires);
        if (! is_int($expires_ts)) {
            return true;
        }

        return $expires_ts >= current_datetime()->getTimestamp();
    }

    /**
     * Verifica limite uso per utente/email.
     */
    private function checkPerUserLimit(DiscountRule $rule, string $customer_email): bool
    {
        if ($rule->usage_limit_per_user === null) {
            return true;
        }

        $email = sanitize_email($customer_email);
        if ($email === '') {
            return true;
        }

        return $this->repository->countUsageByEmail($rule->id, $email) < $rule->usage_limit_per_user;
    }

    /**
     * Verifica restrizione email consentite.
     */
    private function checkEmailRestriction(DiscountRule $rule, string $customer_email): bool
    {
        if ($rule->allowed_emails === []) {
            return true;
        }

        $email = sanitize_email($customer_email);
        if (! is_email($email)) {
            return false;
        }

        $allowed = array_map(static fn (string $allowed_email): string => sanitize_email($allowed_email), $rule->allowed_emails);
        return in_array($email, $allowed, true);
    }

    /**
     * Verifica restrizioni prodotti/categorie.
     */
    private function checkProductRestrictions(DiscountRule $rule, WC_Cart $cart): bool
    {
        $product_ids = [];
        $category_ids = [];

        foreach ($cart->get_cart() as $item) {
            $product_id = absint($item['product_id'] ?? 0);
            if ($product_id > 0) {
                $product_ids[] = $product_id;
            }

            $terms = get_the_terms($product_id, 'product_cat');
            if (is_array($terms)) {
                foreach ($terms as $term) {
                    $category_ids[] = (int) ($term->term_id ?? 0);
                }
            }
        }

        if ($rule->product_ids !== [] && array_intersect($product_ids, $rule->product_ids) === []) {
            return false;
        }

        if ($rule->exclude_product_ids !== [] && array_intersect($product_ids, $rule->exclude_product_ids) !== []) {
            return false;
        }

        if ($rule->product_category_ids !== [] && array_intersect($category_ids, $rule->product_category_ids) === []) {
            return false;
        }

        return ! ($rule->exclude_category_ids !== [] && array_intersect($category_ids, $rule->exclude_category_ids) !== []);
    }

    /**
     * Verifica eventuale restrizione ruolo utente.
     */
    private function checkRoleRestriction(DiscountRule $rule): bool
    {
        if ($rule->allowed_roles === []) {
            return true;
        }

        $user = wp_get_current_user();
        $roles = is_array($user->roles ?? null) ? $user->roles : [];

        return array_intersect($roles, $rule->allowed_roles) !== [];
    }
}
