<?php

declare(strict_types=1);

namespace FP\DiscountGift\Infrastructure\DB;

use FP\DiscountGift\Domain\DiscountRule;
use wpdb;

use function array_map;
use function current_time;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function min;
use function sanitize_key;
use function sanitize_email;
use function sanitize_text_field;
use function strtotime;
use function strtoupper;
use function time;
use function wp_json_encode;
use function wp_rand;

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
     * Duplica una regola con nuovo codice univoco.
     */
    public function duplicateRule(int $id): int
    {
        $rule = $this->findById($id);
        if (! $rule instanceof DiscountRule) {
            return 0;
        }

        $payload = [
            'id' => 0,
            'code' => $this->generateUniqueCode($rule->code . '-'),
            'title' => $rule->title . ' (' . __('copia', 'fp-discount-gift') . ')',
            'discount_type' => $rule->discount_type,
            'amount' => $rule->amount,
            'individual_use' => $rule->individual_use,
            'usage_limit' => $rule->usage_limit,
            'usage_limit_per_user' => $rule->usage_limit_per_user,
            'minimum_amount' => $rule->minimum_amount,
            'maximum_amount' => $rule->maximum_amount,
            'date_expires' => $rule->date_expires,
            'allowed_emails' => $rule->allowed_emails,
            'product_ids' => $rule->product_ids,
            'exclude_product_ids' => $rule->exclude_product_ids,
            'is_enabled' => false,
            'metadata' => ['duplicated_from' => $id],
        ];

        return $this->saveRule($payload);
    }

    /**
     * Verifica se un codice regola esiste già.
     */
    public function codeExists(string $code): bool
    {
        return $this->findByCode($code) !== null;
    }

    /**
     * Genera un codice univoco con prefisso opzionale.
     */
    public function generateUniqueCode(string $prefix = 'GC'): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $prefix));
        if ($prefix === '') {
            $prefix = 'GC';
        }

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . wp_rand(100000, 999999);
            if (! $this->codeExists($code)) {
                return $code;
            }
        }

        return $prefix . wp_rand(100000, 999999) . '-' . wp_rand(100, 999);
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
     * Restituisce statistiche uso per le regole.
     *
     * @return array<int, array{usage_count: int, total_amount: float, last_used: ?string}>
     */
    public function getUsageStatsForRules(): array
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $usage_table = $wpdb->prefix . 'fp_discountgift_rule_usages';
        $rows = $wpdb->get_results(
            "SELECT rule_id, COUNT(*) as usage_count, COALESCE(SUM(amount_applied), 0) as total_amount, MAX(created_at) as last_used
             FROM {$usage_table}
             GROUP BY rule_id",
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $stats = [];
        foreach ($rows as $row) {
            $rule_id = (int) ($row['rule_id'] ?? 0);
            if ($rule_id > 0) {
                $stats[$rule_id] = [
                    'usage_count' => (int) ($row['usage_count'] ?? 0),
                    'total_amount' => (float) ($row['total_amount'] ?? 0),
                    'last_used' => ! empty($row['last_used']) ? (string) $row['last_used'] : null,
                ];
            }
        }

        return $stats;
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
     * Restituisce gift card in ordine decrescente.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getGiftCards(): array
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Restituisce gift card attive in scadenza entro N giorni.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getGiftCardsExpiringSoon(int $days, int $limit = 100): array
    {
        $days = $days > 0 ? $days : 7;
        $limit = $limit > 0 ? $limit : 100;

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $from = current_time('mysql');
        $to = gmdate('Y-m-d H:i:s', time() + (DAY_IN_SECONDS * $days));

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = %s
             AND expires_at IS NOT NULL
             AND expires_at >= %s
             AND expires_at <= %s
             ORDER BY expires_at ASC
             LIMIT %d",
            'active',
            $from,
            $to,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Verifica se una gift card ha gia ricevuto reminder "expiring soon".
     *
     * @param array<string,mixed> $gift_card
     */
    public function hasGiftCardExpiringSoonNotified(array $gift_card): bool
    {
        $metadata = $this->decodeMetadata($gift_card['metadata'] ?? '');
        return ! empty($metadata['expiring_notified_at']);
    }

    /**
     * Marca gift card come notificata per reminder scadenza.
     */
    public function markGiftCardExpiringSoonNotified(int $gift_card_id): bool
    {
        if ($gift_card_id <= 0) {
            return false;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return false;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT metadata FROM {$table} WHERE id = %d LIMIT 1", $gift_card_id),
            ARRAY_A
        );
        $metadata = is_array($row) ? $this->decodeMetadata($row['metadata'] ?? '') : [];
        $metadata['expiring_notified_at'] = current_time('mysql');

        $updated = $wpdb->update(
            $table,
            [
                'metadata' => wp_json_encode($metadata),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $gift_card_id],
            ['%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    /**
     * Scade automaticamente le gift card oltre la data di validita.
     *
     * @return array<int, array<string,mixed>>
     */
    public function expireOverdueGiftCards(int $limit = 100): array
    {
        $limit = $limit > 0 ? $limit : 100;

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return [];
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $now = current_time('mysql');
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = %s
             AND expires_at IS NOT NULL
             AND expires_at < %s
             ORDER BY expires_at ASC
             LIMIT %d",
            'active',
            $now,
            $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $expired = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'expired',
                    'updated_at' => $now,
                ],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                continue;
            }

            $this->recordGiftCardMovement(
                $id,
                'expired',
                (float) ($row['current_balance'] ?? 0),
                0,
                0,
                'Auto expire cron'
            );

            $row['status'] = 'expired';
            $expired[] = $row;
        }

        return $expired;
    }

    /**
     * Restituisce gift card per ID.
     *
     * @return array<string,mixed>|null
     */
    public function getGiftCardById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Cerca una gift card per codice.
     *
     * @return array<string,mixed>|null
     */
    public function findGiftCardByCode(string $code): ?array
    {
        $code = strtoupper(sanitize_text_field($code));
        if ($code === '') {
            return null;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE code = %s LIMIT 1", $code),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Crea una nuova gift card e registra movimento di emissione.
     *
     * @param array<string,mixed> $payload
     */
    public function createGiftCard(array $payload): int
    {
        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $amount = max(0, (float) ($payload['amount'] ?? 0));
        if ($amount <= 0) {
            return 0;
        }

        $code = strtoupper(sanitize_text_field((string) ($payload['code'] ?? '')));
        if ($code === '') {
            $code = $this->generateGiftCardCode();
        }

        $currency = strtoupper(sanitize_text_field((string) ($payload['currency'] ?? 'EUR')));
        $status = sanitize_key((string) ($payload['status'] ?? 'active'));
        if (! in_array($status, ['active', 'blocked', 'expired', 'redeemed'], true)) {
            $status = 'active';
        }

        $wpdb->insert($table, [
            'code' => $code,
            'initial_balance' => $amount,
            'current_balance' => $amount,
            'currency' => $currency !== '' ? $currency : 'EUR',
            'status' => $status,
            'recipient_email' => sanitize_email((string) ($payload['recipient_email'] ?? '')) ?: null,
            'expires_at' => ! empty($payload['expires_at']) ? sanitize_text_field((string) $payload['expires_at']) : null,
            'metadata' => wp_json_encode(is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $gift_card_id = (int) $wpdb->insert_id;
        if ($gift_card_id <= 0) {
            return 0;
        }

        $this->recordGiftCardMovement($gift_card_id, 'issued', $amount, 0, 0, (string) ($payload['note'] ?? ''));

        return $gift_card_id;
    }

    /**
     * Riscatta saldo gift card su ordine e registra movimento.
     *
     * @return array<string,mixed>|null
     */
    public function redeemGiftCardByCode(string $code, float $requested_amount, int $order_id = 0, int $reservation_id = 0): ?array
    {
        $gift_card = $this->findGiftCardByCode($code);
        if (! is_array($gift_card)) {
            return null;
        }

        $gift_card_id = (int) ($gift_card['id'] ?? 0);
        $status = (string) ($gift_card['status'] ?? '');
        $current_balance = (float) ($gift_card['current_balance'] ?? 0);
        $expires_at = (string) ($gift_card['expires_at'] ?? '');

        if ($gift_card_id <= 0 || $status !== 'active' || $current_balance <= 0) {
            return null;
        }

        if ($expires_at !== '') {
            $expires_ts = strtotime($expires_at);
            if ($expires_ts !== false && $expires_ts < current_time('timestamp')) {
                return null;
            }
        }

        $amount_to_redeem = min(max(0, $requested_amount), $current_balance);
        if ($amount_to_redeem <= 0) {
            return null;
        }

        $new_balance = $current_balance - $amount_to_redeem;
        $new_status = $new_balance <= 0 ? 'redeemed' : 'active';

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return null;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_cards';
        $updated = $wpdb->update(
            $table,
            [
                'current_balance' => $new_balance,
                'status' => $new_status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $gift_card_id],
            ['%f', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return null;
        }

        $this->recordGiftCardMovement($gift_card_id, 'redeemed', $amount_to_redeem, $order_id, $reservation_id, 'Checkout redeem');

        $gift_card['current_balance'] = $new_balance;
        $gift_card['status'] = $new_status;
        $gift_card['redeemed_amount'] = $amount_to_redeem;

        return $gift_card;
    }

    /**
     * Registra movimento saldo gift card.
     */
    public function recordGiftCardMovement(
        int $gift_card_id,
        string $movement_type,
        float $amount,
        int $order_id = 0,
        int $reservation_id = 0,
        string $note = ''
    ): void {
        if ($gift_card_id <= 0 || $amount < 0) {
            return;
        }

        global $wpdb;
        if (! isset($wpdb) || ! $wpdb instanceof wpdb) {
            return;
        }

        $table = $wpdb->prefix . 'fp_discountgift_gift_card_movements';
        $wpdb->insert($table, [
            'gift_card_id' => $gift_card_id,
            'movement_type' => sanitize_key($movement_type),
            'amount' => $amount,
            'order_id' => $order_id > 0 ? $order_id : null,
            'reservation_id' => $reservation_id > 0 ? $reservation_id : null,
            'note' => $note !== '' ? sanitize_text_field($note) : null,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Genera codice gift card pseudo-random.
     */
    private function generateGiftCardCode(): string
    {
        return 'FGC-' . strtoupper((string) wp_rand(100000, 999999)) . '-' . strtoupper((string) wp_rand(1000, 9999));
    }

    /**
     * Decodifica metadata json in array.
     *
     * @return array<string,mixed>
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || $metadata === '') {
            return [];
        }

        $decoded = json_decode($metadata, true);
        return is_array($decoded) ? $decoded : [];
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
