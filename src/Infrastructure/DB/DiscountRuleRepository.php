<?php

declare(strict_types=1);

namespace FP\DiscountGift\Infrastructure\DB;

use FP\DiscountGift\Domain\DiscountRule;
use wpdb;

use function array_map;
use function current_time;
use function in_array;
use function is_array;
use function json_encode;
use function sanitize_email;
use function sanitize_text_field;
use function strtoupper;
use function wp_json_encode;

/**
 * Repository regole sconto e ledger eventi voucher.
 */
class DiscountRuleRepository
{
    /**
     * Restituisce tutte le regole.
     *
     * @return array<int, DiscountRule>
     */
    public function getAllRules(): array
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): DiscountRule => DiscountRule::fromArray($row), $rows);
    }

    /**
     * Restituisce tutte le regole attive.
     *
     * @return array<int, DiscountRule>
     */
    public function getActiveRules(): array
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE is_enabled = 1 ORDER BY id DESC", ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): DiscountRule => DiscountRule::fromArray($row), $rows);
    }

    /**
     * Trova una regola per codice.
     */
    public function findByCode(string $code): ?DiscountRule
    {
        $code = strtoupper(sanitize_text_field($code));
        if ($code === '') {
            return null;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE code = %s LIMIT 1", $code), ARRAY_A);

        return is_array($row) ? DiscountRule::fromArray($row) : null;
    }

    /**
     * Trova una regola per ID.
     */
    public function findById(int $id): ?DiscountRule
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);

        return is_array($row) ? DiscountRule::fromArray($row) : null;
    }

    /**
     * Crea o aggiorna una regola.
     *
     * @param array<string, mixed> $payload
     */
    public function saveRule(array $payload): int
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $id = isset($payload['id']) ? absint($payload['id']) : 0;
        $now = current_time('mysql');

        $data = [
            'code' => strtoupper(sanitize_text_field((string) ($payload['code'] ?? ''))),
            'title' => sanitize_text_field((string) ($payload['title'] ?? '')),
            'discount_type' => sanitize_text_field((string) ($payload['discount_type'] ?? 'fixed_cart')),
            'amount' => (float) ($payload['amount'] ?? 0),
            'individual_use' => empty($payload['individual_use']) ? 0 : 1,
            'usage_limit' => ! empty($payload['usage_limit']) ? absint($payload['usage_limit']) : null,
            'usage_limit_per_user' => ! empty($payload['usage_limit_per_user']) ? absint($payload['usage_limit_per_user']) : null,
            'minimum_amount' => $payload['minimum_amount'] !== '' ? (float) ($payload['minimum_amount'] ?? 0) : null,
            'maximum_amount' => $payload['maximum_amount'] !== '' ? (float) ($payload['maximum_amount'] ?? 0) : null,
            'date_expires' => ! empty($payload['date_expires']) ? sanitize_text_field((string) $payload['date_expires']) : null,
            'allowed_emails' => wp_json_encode($this->sanitizeEmails($payload['allowed_emails'] ?? [])),
            'product_ids' => wp_json_encode($this->sanitizeIds($payload['product_ids'] ?? [])),
            'exclude_product_ids' => wp_json_encode($this->sanitizeIds($payload['exclude_product_ids'] ?? [])),
            'product_category_ids' => wp_json_encode($this->sanitizeIds($payload['product_category_ids'] ?? [])),
            'exclude_category_ids' => wp_json_encode($this->sanitizeIds($payload['exclude_category_ids'] ?? [])),
            'allowed_roles' => wp_json_encode($this->sanitizeStringList($payload['allowed_roles'] ?? [])),
            'metadata' => wp_json_encode(is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []),
            'is_enabled' => empty($payload['is_enabled']) ? 0 : 1,
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
            return $id;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
        return (int) $wpdb->insert_id;
    }

    /**
     * Elimina una regola per ID.
     */
    public function deleteRule(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return false;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

        return $deleted !== false;
    }

    /**
     * Aggiorna stato attivo/disattivo di una regola.
     */
    public function setRuleEnabled(int $id, bool $enabled): bool
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return false;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rules';
        $updated = $wpdb->update(
            $table,
            [
                'is_enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Aggiorna in bulk lo stato delle regole.
     *
     * @param array<int,int> $ids
     */
    public function bulkSetRuleEnabled(array $ids, bool $enabled): int
    {
        $count = 0;

        foreach ($ids as $id) {
            if ($this->setRuleEnabled(absint($id), $enabled)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Registra un uso regola su ordine.
     */
    public function recordUsage(int $rule_id, int $order_id, int $user_id, string $email, float $amount): void
    {
        if ($rule_id <= 0 || $order_id <= 0) {
            return;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rule_usages';
        $wpdb->insert($table, [
            'rule_id' => $rule_id,
            'order_id' => $order_id,
            'user_id' => $user_id > 0 ? $user_id : null,
            'email' => $email !== '' ? sanitize_email($email) : null,
            'amount_applied' => $amount,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Restituisce conteggio usi regola.
     */
    public function countUsage(int $rule_id): int
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb || $rule_id <= 0) {
            return 0;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rule_usages';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE rule_id = %d", $rule_id));
    }

    /**
     * Restituisce conteggio usi regola per email.
     */
    public function countUsageByEmail(int $rule_id, string $email): int
    {
        $email = sanitize_email($email);
        if ($email === '' || $rule_id <= 0) {
            return 0;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->prefix . 'fp_discountgift_rule_usages';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE rule_id = %d AND email = %s",
            $rule_id,
            $email
        ));
    }

    /**
     * Registra evento voucher da FP-Experiences.
     *
     * @param array<string, mixed> $payload
     */
    public function recordVoucherEvent(string $event_name, int $voucher_id, int $order_id = 0, int $reservation_id = 0, array $payload = []): void
    {
        if ($voucher_id <= 0) {
            return;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'fp_discountgift_voucher_events';
        $wpdb->insert($table, [
            'event_name' => sanitize_text_field($event_name),
            'voucher_id' => $voucher_id,
            'order_id' => $order_id > 0 ? $order_id : null,
            'reservation_id' => $reservation_id > 0 ? $reservation_id : null,
            'payload' => json_encode($payload),
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function sanitizeIds(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [];
        $ids = array_map('absint', $items);
        return array_values(array_filter($ids, static fn (int $value): bool => $value > 0));
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function sanitizeEmails(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [];
        $emails = array_map(static fn (mixed $item): string => sanitize_email((string) $item), $items);
        return array_values(array_filter($emails, static fn (string $email): bool => $email !== ''));
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function sanitizeStringList(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [];
        $allowed_roles = ['administrator', 'editor', 'author', 'contributor', 'customer', 'subscriber'];
        $list = array_map(static fn (mixed $item): string => sanitize_text_field((string) $item), $items);
        $list = array_values(array_filter($list, static fn (string $value): bool => $value !== '' && in_array($value, $allowed_roles, true)));
        return $list;
    }
}
