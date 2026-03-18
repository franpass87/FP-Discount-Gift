<?php

declare(strict_types=1);

namespace FP\DiscountGift\Domain;

use function array_filter;
use function array_map;
use function array_values;
use function floatval;
use function in_array;
use function is_string;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Value object della regola sconto.
 */
final class DiscountRule
{
    /**
     * @param array<int, string> $allowed_emails
     * @param array<int, int> $product_ids
     * @param array<int, int> $exclude_product_ids
     * @param array<int, int> $product_category_ids
     * @param array<int, int> $exclude_category_ids
     * @param array<int, string> $allowed_roles
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $title,
        public readonly string $discount_type,
        public readonly float $amount,
        public readonly bool $individual_use,
        public readonly ?int $usage_limit,
        public readonly ?int $usage_limit_per_user,
        public readonly ?float $minimum_amount,
        public readonly ?float $maximum_amount,
        public readonly ?string $date_expires,
        public readonly array $allowed_emails,
        public readonly array $product_ids,
        public readonly array $exclude_product_ids,
        public readonly array $product_category_ids,
        public readonly array $exclude_category_ids,
        public readonly array $allowed_roles,
        public readonly array $metadata,
        public readonly bool $is_enabled
    ) {
    }

    /**
     * Crea una regola dai dati DB.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            code: strtoupper((string) ($row['code'] ?? '')),
            title: (string) ($row['title'] ?? ''),
            discount_type: self::normalizeType((string) ($row['discount_type'] ?? 'fixed_cart')),
            amount: floatval($row['amount'] ?? 0),
            individual_use: (bool) ($row['individual_use'] ?? false),
            usage_limit: isset($row['usage_limit']) ? (int) $row['usage_limit'] : null,
            usage_limit_per_user: isset($row['usage_limit_per_user']) ? (int) $row['usage_limit_per_user'] : null,
            minimum_amount: isset($row['minimum_amount']) ? floatval($row['minimum_amount']) : null,
            maximum_amount: isset($row['maximum_amount']) ? floatval($row['maximum_amount']) : null,
            date_expires: ! empty($row['date_expires']) ? (string) $row['date_expires'] : null,
            allowed_emails: self::stringList($row['allowed_emails'] ?? []),
            product_ids: self::intList($row['product_ids'] ?? []),
            exclude_product_ids: self::intList($row['exclude_product_ids'] ?? []),
            product_category_ids: self::intList($row['product_category_ids'] ?? []),
            exclude_category_ids: self::intList($row['exclude_category_ids'] ?? []),
            allowed_roles: self::stringList($row['allowed_roles'] ?? []),
            metadata: self::map($row['metadata'] ?? []),
            is_enabled: (bool) ($row['is_enabled'] ?? true)
        );
    }

    /**
     * Verifica se la regola è attiva.
     */
    public function isActive(): bool
    {
        return $this->is_enabled && $this->amount > 0.0;
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private static function intList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $raw), static fn (int $id): bool => $id > 0));
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private static function stringList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $raw
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function map(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Normalizza il tipo sconto supportato.
     */
    private static function normalizeType(string $value): string
    {
        $normalized = strtolower(trim($value));
        $allowed = ['fixed_cart', 'percent'];

        return in_array($normalized, $allowed, true) ? $normalized : 'fixed_cart';
    }
}
