<?php

declare(strict_types=1);

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (! function_exists('wc_get_coupon_types')) {
    function wc_get_coupon_types(): array
    {
        return ['fixed_cart', 'percent'];
    }
}

if (! function_exists('current_datetime')) {
    function current_datetime(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

if (! function_exists('get_the_terms')) {
    function get_the_terms(int $post_id, string $taxonomy): array
    {
        return [];
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        return (object) ['roles' => ['customer']];
    }
}

if (! class_exists('WC_Cart')) {
    class WC_Cart
    {
        public function __construct(private readonly float $subtotal, private readonly array $cart = [])
        {
        }

        public function get_subtotal(): float
        {
            return $this->subtotal;
        }

        public function get_cart(): array
        {
            return $this->cart;
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
