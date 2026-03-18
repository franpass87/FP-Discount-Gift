<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use FP\DiscountGift\Application\DiscountEngine;
use FP\DiscountGift\Domain\DiscountRule;
use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;

/**
 * Repository fake per test del motore.
 */
final class FakeRepository extends DiscountRuleRepository
{
    /**
     * @param array<int, DiscountRule> $rules
     */
    public function __construct(private readonly array $rules)
    {
    }

    public function getActiveRules(): array
    {
        return $this->rules;
    }

    public function findByCode(string $code): ?DiscountRule
    {
        foreach ($this->rules as $rule) {
            if (strtoupper($rule->code) === strtoupper($code)) {
                return $rule;
            }
        }

        return null;
    }

    public function countUsage(int $rule_id): int
    {
        return 0;
    }

    public function countUsageByEmail(int $rule_id, string $email): int
    {
        return 0;
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('ASSERT FAIL: ' . $message);
    }
}

$rulePercent = new DiscountRule(
    id: 1,
    code: 'SAVE10',
    title: '10%',
    discount_type: 'percent',
    amount: 10.0,
    individual_use: false,
    usage_limit: null,
    usage_limit_per_user: null,
    minimum_amount: 10.0,
    maximum_amount: null,
    date_expires: null,
    allowed_emails: [],
    product_ids: [],
    exclude_product_ids: [],
    product_category_ids: [],
    exclude_category_ids: [],
    allowed_roles: [],
    metadata: [],
    is_enabled: true
);

$ruleFixed = new DiscountRule(
    id: 2,
    code: 'FIX5',
    title: '5 EUR',
    discount_type: 'fixed_cart',
    amount: 5.0,
    individual_use: false,
    usage_limit: null,
    usage_limit_per_user: null,
    minimum_amount: null,
    maximum_amount: null,
    date_expires: null,
    allowed_emails: [],
    product_ids: [],
    exclude_product_ids: [],
    product_category_ids: [],
    exclude_category_ids: [],
    allowed_roles: [],
    metadata: [],
    is_enabled: true
);

$repo = new FakeRepository([$rulePercent, $ruleFixed]);
$engine = new DiscountEngine($repo);
$cart = new WC_Cart(100.0, [['product_id' => 12]]);

$found = $engine->evaluateByCode('SAVE10', $cart, 'demo@example.com');
assertTrue($found instanceof DiscountRule, 'evaluateByCode deve trovare SAVE10');

$discount = $engine->calculateDiscountAmount($rulePercent, $cart);
assertTrue($discount === 10.0, 'Percentuale 10% su 100 deve essere 10');

$bestRule = $engine->findBestRule($cart, 'demo@example.com');
assertTrue($bestRule instanceof DiscountRule, 'findBestRule deve restituire una regola');
assertTrue($bestRule->code === 'SAVE10', 'Regola migliore attesa SAVE10');

echo "DiscountEngine smoke tests: OK\n";
