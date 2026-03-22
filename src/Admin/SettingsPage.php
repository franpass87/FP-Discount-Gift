<?php

declare(strict_types=1);

namespace FP\DiscountGift\Admin;

use FP\DiscountGift\Core\Roles;
use FP\DiscountGift\Infrastructure\DB\DiscountRuleRepository;

use function absint;
use function add_action;
use function add_query_arg;
use function add_menu_page;
use function admin_url;
use function array_filter;
use function array_map;
use function checked;
use function current_user_can;
use function do_action;
use function esc_attr__;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_html_e;
use function esc_url;
use function get_option;
use function implode;
use function is_array;
use function sanitize_email;
use function sanitize_text_field;
use function selected;
use function sprintf;
use function update_option;
use function wp_die;
use function wp_enqueue_style;
use function wp_get_current_user;
use function wp_nonce_field;
use function wp_nonce_url;
use function wp_redirect;
use function wp_unslash;
use function wp_verify_nonce;

/**
 * Pagina admin per impostazioni e regole sconto.
 */
final class SettingsPage
{
    private const MENU_SLUG = 'fp-discount-gift';
    private const SETTINGS_OPTION = 'fp_discountgift_settings';

    public function __construct(private readonly DiscountRuleRepository $repository)
    {
    }

    /**
     * Registra menu, assets e handler admin.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_fp_discountgift_save_settings', [$this, 'handleSaveSettings']);
        add_action('admin_post_fp_discountgift_save_rule', [$this, 'handleSaveRule']);
        add_action('admin_post_fp_discountgift_create_gift_card', [$this, 'handleCreateGiftCard']);
        add_action('admin_post_fp_discountgift_delete_rule', [$this, 'handleDeleteRule']);
        add_action('admin_post_fp_discountgift_bulk_rules', [$this, 'handleBulkRules']);
    }

    /**
     * Registra pagina menu plugin.
     */
    public function registerMenu(): void
    {
        add_menu_page(
            esc_html__('FP Discount Gift', 'fp-discount-gift'),
            esc_html__('FP Discount Gift', 'fp-discount-gift'),
            Roles::MANAGE,
            self::MENU_SLUG,
            [$this, 'renderPage'],
            'dashicons-tickets-alt',
            58
        );
    }

    /**
     * Carica CSS admin del plugin.
     */
    public function enqueueAssets(string $hook): void
    {
        $is_our_page = (strpos($hook, self::MENU_SLUG) !== false)
            || (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === self::MENU_SLUG);
        if (! $is_our_page) {
            return;
        }

        wp_enqueue_style(
            'fp-discount-gift-admin',
            FP_DISCOUNTGIFT_URL . 'assets/css/admin.css',
            [],
            FP_DISCOUNTGIFT_VERSION
        );
    }

    /**
     * Renderizza UI admin.
     */
    public function renderPage(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        $settings = get_option(self::SETTINGS_OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $rules = $this->repository->getAllRules();
        $gift_cards = $this->repository->getGiftCards();
        $edit_id = isset($_GET['edit_rule']) ? absint($_GET['edit_rule']) : 0;
        $edit_rule = $edit_id > 0 ? $this->repository->findById($edit_id) : null;
        $shadow_active = ! empty($settings['enable_shadow_coupons']);
        $auto_apply_active = ! empty($settings['auto_apply_best_rule']);
        ?>
        <div class="wrap fpdgift-wrap fpdgift-admin-page">
            <?php /* h1 primo nel .wrap: compat notice JS (es. jQuery('.wrap h1').after(...)). Titolo visibile = h2 nel banner. */ ?>
            <h1 class="screen-reader-text"><?php esc_html_e('FP Discount Gift', 'fp-discount-gift'); ?></h1>
            <div class="fpdgift-page-header">
                <div class="fpdgift-page-header-content">
                    <h2 class="fpdgift-page-header-title" aria-hidden="true"><span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e('FP Discount Gift', 'fp-discount-gift'); ?></h2>
                    <p><?php esc_html_e('Gestione codici sconto FP con shadow coupon WooCommerce e gift card native.', 'fp-discount-gift'); ?></p>
                </div>
                <span class="fpdgift-page-header-badge">v<?php echo esc_html(FP_DISCOUNTGIFT_VERSION); ?></span>
            </div>

            <?php
            if (isset($_GET['saved']) && '1' === $_GET['saved']) :
                echo '<div class="fpdgift-alert fpdgift-alert-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Impostazioni salvate.', 'fp-discount-gift') . '</div>';
            elseif (isset($_GET['saved_rule']) && '1' === $_GET['saved_rule']) :
                echo '<div class="fpdgift-alert fpdgift-alert-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Regola salvata.', 'fp-discount-gift') . '</div>';
            elseif (isset($_GET['gift_card_created']) && '1' === $_GET['gift_card_created']) :
                echo '<div class="fpdgift-alert fpdgift-alert-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Gift card emessa correttamente.', 'fp-discount-gift') . '</div>';
            elseif (isset($_GET['deleted_rule']) && '1' === $_GET['deleted_rule']) :
                echo '<div class="fpdgift-alert fpdgift-alert-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Regola eliminata.', 'fp-discount-gift') . '</div>';
            elseif (isset($_GET['bulk']) && '1' === $_GET['bulk']) :
                echo '<div class="fpdgift-alert fpdgift-alert-success"><span class="dashicons dashicons-yes-alt"></span> ' . esc_html__('Azione bulk eseguita.', 'fp-discount-gift') . '</div>';
            elseif (isset($_GET['bulk']) && 'empty' === $_GET['bulk']) :
                echo '<div class="fpdgift-alert fpdgift-alert-warning"><span class="dashicons dashicons-warning"></span> ' . esc_html__('Seleziona almeno una regola per l\'azione bulk.', 'fp-discount-gift') . '</div>';
            endif;
            ?>

            <div class="fpdgift-status-bar">
                <span class="fpdgift-status-pill <?php echo $shadow_active ? 'is-active' : 'is-missing'; ?>">
                    <span class="dot"></span>
                    <?php echo $shadow_active ? esc_html__('Shadow coupon attivi', 'fp-discount-gift') : esc_html__('Shadow coupon disattivi', 'fp-discount-gift'); ?>
                </span>
                <span class="fpdgift-status-pill <?php echo $auto_apply_active ? 'is-active' : ''; ?>">
                    <span class="dot"></span>
                    <?php echo $auto_apply_active ? esc_html__('Auto-applicazione attiva', 'fp-discount-gift') : esc_html__('Auto-applicazione disattiva', 'fp-discount-gift'); ?>
                </span>
                <span class="fpdgift-status-pill">
                    <span class="dot"></span>
                    <?php echo esc_html(sprintf(/* translators: %d: number of rules */ __('%d regole configurate', 'fp-discount-gift'), count($rules))); ?>
                </span>
            </div>

            <div class="fpdgift-card">
                <div class="fpdgift-card-header">
                    <div class="fpdgift-card-header-left">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <h2><?php esc_html_e('Impostazioni', 'fp-discount-gift'); ?></h2>
                    </div>
                    <span class="fpdgift-badge fpdgift-badge-neutral"><?php esc_html_e('Generale', 'fp-discount-gift'); ?></span>
                </div>
                <div class="fpdgift-card-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="fp_discountgift_save_settings">
                        <?php wp_nonce_field('fp_discountgift_save_settings', 'fp_discountgift_nonce'); ?>

                        <div class="fpdgift-toggle-row">
                            <div class="fpdgift-toggle-info">
                                <strong><?php esc_html_e('Abilita shadow coupon WooCommerce', 'fp-discount-gift'); ?></strong>
                                <span><?php esc_html_e('Sincronizza le regole FP con coupon WooCommerce per il checkout.', 'fp-discount-gift'); ?></span>
                            </div>
                            <label class="fpdgift-toggle">
                                <input type="checkbox" name="enable_shadow_coupons" value="1" <?php checked($shadow_active); ?>>
                                <span class="fpdgift-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="fpdgift-toggle-row">
                            <div class="fpdgift-toggle-info">
                                <strong><?php esc_html_e('Mantieni visibile il campo coupon WooCommerce', 'fp-discount-gift'); ?></strong>
                                <span><?php esc_html_e('Mostra il campo inserimento codice nel checkout.', 'fp-discount-gift'); ?></span>
                            </div>
                            <label class="fpdgift-toggle">
                                <input type="checkbox" name="allow_wc_coupon_field" value="1" <?php checked(! empty($settings['allow_wc_coupon_field'])); ?>>
                                <span class="fpdgift-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="fpdgift-toggle-row">
                            <div class="fpdgift-toggle-info">
                                <strong><?php esc_html_e('Auto-applica migliore regola disponibile', 'fp-discount-gift'); ?></strong>
                                <span><?php esc_html_e('Applica automaticamente lo sconto migliore senza richiedere il codice.', 'fp-discount-gift'); ?></span>
                            </div>
                            <label class="fpdgift-toggle">
                                <input type="checkbox" name="auto_apply_best_rule" value="1" <?php checked($auto_apply_active); ?>>
                                <span class="fpdgift-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="fpdgift-toggle-row">
                            <div class="fpdgift-toggle-info">
                                <strong><?php esc_html_e('Scadenza automatica gift card via cron', 'fp-discount-gift'); ?></strong>
                                <span><?php esc_html_e('Disattiva automaticamente le gift card scadute.', 'fp-discount-gift'); ?></span>
                            </div>
                            <label class="fpdgift-toggle">
                                <input type="checkbox" name="gift_card_auto_expire" value="1" <?php checked(! empty($settings['gift_card_auto_expire'])); ?>>
                                <span class="fpdgift-toggle-slider"></span>
                            </label>
                        </div>

                        <div class="fpdgift-fields-grid" style="margin-top: 20px;">
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Reminder scadenza gift card (giorni)', 'fp-discount-gift'); ?></label>
                                <input type="number" name="gift_card_reminder_days" min="1" step="1" value="<?php echo esc_attr((string) ($settings['gift_card_reminder_days'] ?? 7)); ?>">
                                <span class="fpdgift-hint"><?php esc_html_e('Giorni prima della scadenza per inviare il promemoria.', 'fp-discount-gift'); ?></span>
                            </div>
                        </div>

                        <p style="margin-top: 20px;">
                            <button type="submit" class="fpdgift-btn fpdgift-btn-primary">
                                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Salva impostazioni', 'fp-discount-gift'); ?>
                            </button>
                        </p>
                    </form>
                </div>
            </div>

            <div class="fpdgift-card">
                <div class="fpdgift-card-header">
                    <div class="fpdgift-card-header-left">
                        <span class="dashicons dashicons-tag"></span>
                        <h2><?php echo esc_html($edit_rule ? __('Modifica regola sconto', 'fp-discount-gift') : __('Nuova regola sconto', 'fp-discount-gift')); ?></h2>
                    </div>
                    <span class="fpdgift-badge fpdgift-badge-<?php echo $edit_rule ? 'warning' : 'success'; ?>"><?php echo $edit_rule ? esc_html__('Modifica', 'fp-discount-gift') : esc_html__('Nuovo', 'fp-discount-gift'); ?></span>
                </div>
                <div class="fpdgift-card-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="fp_discountgift_save_rule">
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) ($edit_rule?->id ?? 0)); ?>">
                        <?php wp_nonce_field('fp_discountgift_save_rule', 'fp_discountgift_nonce'); ?>

                        <div class="fpdgift-fields-grid">
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Codice', 'fp-discount-gift'); ?></label>
                                <input type="text" name="code" class="is-monospace" value="<?php echo esc_attr((string) ($edit_rule?->code ?? '')); ?>" placeholder="SALDI20" required>
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Titolo', 'fp-discount-gift'); ?></label>
                                <input type="text" name="title" value="<?php echo esc_attr((string) ($edit_rule?->title ?? '')); ?>" required>
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Tipo sconto', 'fp-discount-gift'); ?></label>
                                <select name="discount_type">
                                    <option value="fixed_cart" <?php selected((string) ($edit_rule?->discount_type ?? 'fixed_cart'), 'fixed_cart'); ?>><?php esc_html_e('Importo fisso carrello', 'fp-discount-gift'); ?></option>
                                    <option value="percent" <?php selected((string) ($edit_rule?->discount_type ?? 'fixed_cart'), 'percent'); ?>><?php esc_html_e('Percentuale', 'fp-discount-gift'); ?></option>
                                </select>
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Importo', 'fp-discount-gift'); ?></label>
                                <input type="number" name="amount" min="0" step="0.01" value="<?php echo esc_attr((string) ($edit_rule?->amount ?? 0)); ?>" required>
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Data scadenza (YYYY-MM-DD HH:MM:SS)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="date_expires" class="is-monospace" value="<?php echo esc_attr((string) ($edit_rule?->date_expires ?? '')); ?>" placeholder="2026-12-31 23:59:59">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Limite uso totale', 'fp-discount-gift'); ?></label>
                                <input type="number" name="usage_limit" min="0" step="1" value="<?php echo esc_attr((string) ($edit_rule?->usage_limit ?? '')); ?>" placeholder="0 = illimitato">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Limite uso per utente', 'fp-discount-gift'); ?></label>
                                <input type="number" name="usage_limit_per_user" min="0" step="1" value="<?php echo esc_attr((string) ($edit_rule?->usage_limit_per_user ?? '')); ?>">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Spesa minima', 'fp-discount-gift'); ?></label>
                                <input type="number" name="minimum_amount" min="0" step="0.01" value="<?php echo esc_attr((string) ($edit_rule?->minimum_amount ?? '')); ?>">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Spesa massima', 'fp-discount-gift'); ?></label>
                                <input type="number" name="maximum_amount" min="0" step="0.01" value="<?php echo esc_attr((string) ($edit_rule?->maximum_amount ?? '')); ?>">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Email consentite (CSV)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="allowed_emails" value="<?php echo esc_attr(implode(',', $edit_rule?->allowed_emails ?? [])); ?>" placeholder="a@b.com,c@d.com">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Product IDs consentiti (CSV)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="product_ids" class="is-monospace" value="<?php echo esc_attr(implode(',', $edit_rule?->product_ids ?? [])); ?>" placeholder="12,98">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Product IDs esclusi (CSV)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="exclude_product_ids" class="is-monospace" value="<?php echo esc_attr(implode(',', $edit_rule?->exclude_product_ids ?? [])); ?>" placeholder="45,77">
                            </div>
                            <div class="fpdgift-field" style="grid-column: 1 / -1;">
                                <div class="fpdgift-toggle-row">
                                    <div class="fpdgift-toggle-info">
                                        <strong><?php esc_html_e('Solo uso individuale', 'fp-discount-gift'); ?></strong>
                                        <span><?php esc_html_e('Non cumulabile con altri coupon.', 'fp-discount-gift'); ?></span>
                                    </div>
                                    <label class="fpdgift-toggle">
                                        <input type="checkbox" name="individual_use" value="1" <?php checked(! empty($edit_rule?->individual_use)); ?>>
                                        <span class="fpdgift-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="fpdgift-field" style="grid-column: 1 / -1;">
                                <div class="fpdgift-toggle-row">
                                    <div class="fpdgift-toggle-info">
                                        <strong><?php esc_html_e('Regola attiva', 'fp-discount-gift'); ?></strong>
                                        <span><?php esc_html_e('Disattiva per sospendere temporaneamente la regola.', 'fp-discount-gift'); ?></span>
                                    </div>
                                    <label class="fpdgift-toggle">
                                        <input type="checkbox" name="is_enabled" value="1" <?php checked($edit_rule ? ! empty($edit_rule->is_enabled) : true); ?>>
                                        <span class="fpdgift-toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="fpdgift-actions" style="margin-top: 20px;">
                            <button type="submit" class="fpdgift-btn fpdgift-btn-primary">
                                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Salva regola', 'fp-discount-gift'); ?>
                            </button>
                            <?php if ($edit_rule) : ?>
                                <a class="fpdgift-btn fpdgift-btn-secondary" href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>"><?php esc_html_e('Annulla modifica', 'fp-discount-gift'); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fpdgift-card">
                <div class="fpdgift-card-header">
                    <div class="fpdgift-card-header-left">
                        <span class="dashicons dashicons-list-view"></span>
                        <h2><?php esc_html_e('Regole esistenti', 'fp-discount-gift'); ?></h2>
                    </div>
                    <span class="fpdgift-badge fpdgift-badge-<?php echo $rules !== [] ? 'success' : 'neutral'; ?>"><?php echo esc_html((string) count($rules)); ?></span>
                </div>
                <div class="fpdgift-card-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="fp_discountgift_bulk_rules">
                        <?php wp_nonce_field('fp_discountgift_bulk_rules', 'fp_discountgift_nonce'); ?>
                        <div class="fpdgift-bulk-bar">
                            <select name="bulk_action" aria-label="<?php echo esc_attr__('Azione bulk', 'fp-discount-gift'); ?>">
                                <option value=""><?php esc_html_e('Azione bulk', 'fp-discount-gift'); ?></option>
                                <option value="enable"><?php esc_html_e('Attiva selezionate', 'fp-discount-gift'); ?></option>
                                <option value="disable"><?php esc_html_e('Disattiva selezionate', 'fp-discount-gift'); ?></option>
                                <option value="delete"><?php esc_html_e('Elimina selezionate', 'fp-discount-gift'); ?></option>
                            </select>
                            <button type="submit" class="fpdgift-btn fpdgift-btn-secondary"><?php esc_html_e('Applica', 'fp-discount-gift'); ?></button>
                        </div>
                        <table class="fpdgift-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="fpdgift-checkall" aria-label="<?php esc_attr_e('Seleziona tutte', 'fp-discount-gift'); ?>"></th>
                                    <th><?php esc_html_e('ID', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Codice', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Titolo', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Tipo', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Importo', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Stato', 'fp-discount-gift'); ?></th>
                                    <th><?php esc_html_e('Azioni', 'fp-discount-gift'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rules === []) : ?>
                                    <tr><td colspan="8" style="text-align: center; padding: 24px; color: var(--fpdms-text-muted);"><?php esc_html_e('Nessuna regola configurata.', 'fp-discount-gift'); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ($rules as $rule) : ?>
                                        <?php
                                        $edit_url = add_query_arg(
                                            ['page' => self::MENU_SLUG, 'edit_rule' => $rule->id],
                                            admin_url('admin.php')
                                        );
                                        $delete_url = wp_nonce_url(
                                            add_query_arg(
                                                ['action' => 'fp_discountgift_delete_rule', 'rule_id' => $rule->id],
                                                admin_url('admin-post.php')
                                            ),
                                            'fp_discountgift_delete_rule_' . $rule->id,
                                            'fp_discountgift_nonce'
                                        );
                                        ?>
                                        <tr class="<?php echo empty($rule->is_enabled) ? 'is-disabled' : ''; ?>">
                                            <td><input type="checkbox" name="rule_ids[]" value="<?php echo esc_attr((string) $rule->id); ?>"></td>
                                            <td><?php echo esc_html((string) $rule->id); ?></td>
                                            <td><code><?php echo esc_html($rule->code); ?></code></td>
                                            <td><?php echo esc_html($rule->title); ?></td>
                                            <td><?php echo esc_html($rule->discount_type); ?></td>
                                            <td><?php echo esc_html((string) $rule->amount); ?></td>
                                            <td><span class="fpdgift-badge fpdgift-badge-<?php echo $rule->is_enabled ? 'success' : 'neutral'; ?>"><?php echo esc_html($rule->is_enabled ? __('Attiva', 'fp-discount-gift') : __('Disattiva', 'fp-discount-gift')); ?></span></td>
                                            <td class="fpdgift-row-actions">
                                                <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Modifica', 'fp-discount-gift'); ?></a>
                                                <a href="<?php echo esc_url($delete_url); ?>" class="fpdgift-link-danger" onclick="return confirm('<?php echo esc_attr__('Confermi eliminazione regola?', 'fp-discount-gift'); ?>');">
                                                    <?php esc_html_e('Elimina', 'fp-discount-gift'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>

            <div class="fpdgift-card">
                <div class="fpdgift-card-header">
                    <div class="fpdgift-card-header-left">
                        <span class="dashicons dashicons-money-alt"></span>
                        <h2><?php esc_html_e('Gift card native', 'fp-discount-gift'); ?></h2>
                    </div>
                    <span class="fpdgift-badge fpdgift-badge-<?php echo $gift_cards !== [] ? 'success' : 'neutral'; ?>"><?php echo esc_html((string) count($gift_cards)); ?></span>
                </div>
                <div class="fpdgift-card-body">
                    <p class="description"><?php esc_html_e('Emetti gift card con saldo spendibile al checkout. Lascia vuoto il codice per generarlo automaticamente.', 'fp-discount-gift'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 28px;">
                        <input type="hidden" name="action" value="fp_discountgift_create_gift_card">
                        <?php wp_nonce_field('fp_discountgift_create_gift_card', 'fp_discountgift_nonce'); ?>

                        <div class="fpdgift-fields-grid">
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Codice (opzionale)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="gift_code" class="is-monospace" placeholder="FGC-123456-7890">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Importo iniziale', 'fp-discount-gift'); ?></label>
                                <input type="number" name="gift_amount" min="0" step="0.01" required>
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Valuta', 'fp-discount-gift'); ?></label>
                                <input type="text" name="gift_currency" value="EUR">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Email destinatario', 'fp-discount-gift'); ?></label>
                                <input type="email" name="gift_recipient_email" placeholder="cliente@esempio.it">
                            </div>
                            <div class="fpdgift-field">
                                <label><?php esc_html_e('Scadenza (YYYY-MM-DD HH:MM:SS)', 'fp-discount-gift'); ?></label>
                                <input type="text" name="gift_expires_at" class="is-monospace" placeholder="2026-12-31 23:59:59">
                            </div>
                        </div>
                        <p style="margin-top: 20px;">
                            <button type="submit" class="fpdgift-btn fpdgift-btn-success">
                                <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Emetti gift card', 'fp-discount-gift'); ?>
                            </button>
                        </p>
                    </form>

                    <h3 style="margin-bottom: 12px; font-size: 14px;"><?php esc_html_e('Gift card emesse', 'fp-discount-gift'); ?></h3>
                    <table class="fpdgift-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Codice', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Saldo iniziale', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Saldo corrente', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Valuta', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Stato', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Destinatario', 'fp-discount-gift'); ?></th>
                                <th><?php esc_html_e('Scadenza', 'fp-discount-gift'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($gift_cards === []) : ?>
                            <tr><td colspan="8" style="text-align: center; padding: 24px; color: var(--fpdms-text-muted);"><?php esc_html_e('Nessuna gift card emessa.', 'fp-discount-gift'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($gift_cards as $gift_card) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($gift_card['id'] ?? '')); ?></td>
                                    <td><code><?php echo esc_html((string) ($gift_card['code'] ?? '')); ?></code></td>
                                    <td><?php echo esc_html((string) ($gift_card['initial_balance'] ?? '0')); ?></td>
                                    <td><?php echo esc_html((string) ($gift_card['current_balance'] ?? '0')); ?></td>
                                    <td><?php echo esc_html((string) ($gift_card['currency'] ?? 'EUR')); ?></td>
                                    <td><span class="fpdgift-badge fpdgift-badge-<?php echo ('active' === ($gift_card['status'] ?? '')) ? 'success' : 'neutral'; ?>"><?php echo esc_html((string) ($gift_card['status'] ?? '')); ?></span></td>
                                    <td><?php echo esc_html((string) ($gift_card['recipient_email'] ?? '-')); ?></td>
                                    <td><?php echo esc_html((string) ($gift_card['expires_at'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const checkAll = document.querySelector('.fpdgift-checkall');
                if (!checkAll) return;
                checkAll.addEventListener('change', function () {
                    document.querySelectorAll('input[name="rule_ids[]"]').forEach((el) => {
                        el.checked = checkAll.checked;
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Salva impostazioni plugin.
     */
    public function handleSaveSettings(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        if (! $this->isValidNonce('fp_discountgift_save_settings')) {
            wp_die(esc_html__('Nonce non valido.', 'fp-discount-gift'));
        }

        $settings = [
            'enable_shadow_coupons' => ! empty($_POST['enable_shadow_coupons']),
            'allow_wc_coupon_field' => ! empty($_POST['allow_wc_coupon_field']),
            'auto_apply_best_rule' => ! empty($_POST['auto_apply_best_rule']),
            'gift_card_reminder_days' => max(1, absint($_POST['gift_card_reminder_days'] ?? 7)),
            'gift_card_auto_expire' => ! empty($_POST['gift_card_auto_expire']),
        ];

        update_option(self::SETTINGS_OPTION, $settings);
        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&saved=1'));
        exit;
    }

    /**
     * Salva una regola sconto.
     */
    public function handleSaveRule(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        if (! $this->isValidNonce('fp_discountgift_save_rule')) {
            wp_die(esc_html__('Nonce non valido.', 'fp-discount-gift'));
        }

        $payload = [
            'id' => absint($_POST['id'] ?? 0),
            'code' => sanitize_text_field(wp_unslash((string) ($_POST['code'] ?? ''))),
            'title' => sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? ''))),
            'discount_type' => sanitize_text_field(wp_unslash((string) ($_POST['discount_type'] ?? 'fixed_cart'))),
            'amount' => (float) ($_POST['amount'] ?? 0),
            'date_expires' => sanitize_text_field(wp_unslash((string) ($_POST['date_expires'] ?? ''))),
            'usage_limit' => absint($_POST['usage_limit'] ?? 0),
            'usage_limit_per_user' => absint($_POST['usage_limit_per_user'] ?? 0),
            'minimum_amount' => sanitize_text_field(wp_unslash((string) ($_POST['minimum_amount'] ?? ''))),
            'maximum_amount' => sanitize_text_field(wp_unslash((string) ($_POST['maximum_amount'] ?? ''))),
            'allowed_emails' => $this->parseEmails((string) ($_POST['allowed_emails'] ?? '')),
            'product_ids' => $this->parseIds((string) ($_POST['product_ids'] ?? '')),
            'exclude_product_ids' => $this->parseIds((string) ($_POST['exclude_product_ids'] ?? '')),
            'individual_use' => ! empty($_POST['individual_use']),
            'is_enabled' => ! empty($_POST['is_enabled']),
            'metadata' => [
                'editor' => wp_get_current_user()->user_login ?? '',
            ],
        ];

        $this->repository->saveRule($payload);
        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&saved_rule=1'));
        exit;
    }

    /**
     * Crea una nuova gift card.
     */
    public function handleCreateGiftCard(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        if (! $this->isValidNonce('fp_discountgift_create_gift_card')) {
            wp_die(esc_html__('Nonce non valido.', 'fp-discount-gift'));
        }

        $payload = [
            'code' => sanitize_text_field(wp_unslash((string) ($_POST['gift_code'] ?? ''))),
            'amount' => (float) ($_POST['gift_amount'] ?? 0),
            'currency' => sanitize_text_field(wp_unslash((string) ($_POST['gift_currency'] ?? 'EUR'))),
            'recipient_email' => sanitize_email(wp_unslash((string) ($_POST['gift_recipient_email'] ?? ''))),
            'expires_at' => sanitize_text_field(wp_unslash((string) ($_POST['gift_expires_at'] ?? ''))),
            'status' => 'active',
            'metadata' => [
                'issued_by' => wp_get_current_user()->user_login ?? '',
            ],
        ];

        $gift_card_id = $this->repository->createGiftCard($payload);
        if ($gift_card_id > 0) {
            $gift_code = (string) ($payload['code'] !== '' ? strtoupper((string) $payload['code']) : '');
            if ($gift_code === '') {
                $created = $this->repository->getGiftCards();
                $gift_code = (string) ($created[0]['code'] ?? '');
            }

            do_action('fp_discountgift_gift_card_issued', $gift_code, [
                'gift_card_id' => $gift_card_id,
                'value' => (float) $payload['amount'],
                'currency' => (string) $payload['currency'],
                'email' => (string) $payload['recipient_email'],
                'user_data' => [
                    'em' => (string) $payload['recipient_email'],
                ],
            ]);
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&gift_card_created=1'));
        exit;
    }

    /**
     * Elimina regola singola.
     */
    public function handleDeleteRule(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        $rule_id = isset($_GET['rule_id']) ? absint($_GET['rule_id']) : 0;
        $nonce = isset($_GET['fp_discountgift_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['fp_discountgift_nonce'])) : '';

        if ($rule_id <= 0 || ! wp_verify_nonce($nonce, 'fp_discountgift_delete_rule_' . $rule_id)) {
            wp_die(esc_html__('Richiesta non valida.', 'fp-discount-gift'));
        }

        $this->repository->deleteRule($rule_id);
        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&deleted_rule=1'));
        exit;
    }

    /**
     * Gestisce azioni bulk su regole.
     */
    public function handleBulkRules(): void
    {
        if (! $this->canManage()) {
            wp_die(esc_html__('Permessi insufficienti.', 'fp-discount-gift'));
        }

        if (! $this->isValidNonce('fp_discountgift_bulk_rules')) {
            wp_die(esc_html__('Nonce non valido.', 'fp-discount-gift'));
        }

        $action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash((string) $_POST['bulk_action'])) : '';
        $ids = isset($_POST['rule_ids']) && is_array($_POST['rule_ids']) ? array_map('absint', wp_unslash($_POST['rule_ids'])) : [];
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

        if ($ids === [] || $action === '') {
            wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&bulk=empty'));
            exit;
        }

        if ($action === 'enable') {
            $this->repository->bulkSetRuleEnabled($ids, true);
        } elseif ($action === 'disable') {
            $this->repository->bulkSetRuleEnabled($ids, false);
        } elseif ($action === 'delete') {
            foreach ($ids as $id) {
                $this->repository->deleteRule($id);
            }
        }

        wp_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&bulk=1'));
        exit;
    }

    /**
     * Verifica permessi gestione plugin.
     */
    private function canManage(): bool
    {
        return current_user_can('manage_options') || current_user_can(Roles::MANAGE);
    }

    /**
     * Valida nonce admin.
     */
    private function isValidNonce(string $action): bool
    {
        $nonce = isset($_POST['fp_discountgift_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['fp_discountgift_nonce'])) : '';
        return $nonce !== '' && wp_verify_nonce($nonce, $action);
    }

    /**
     * Converte CSV in lista email sanificata.
     *
     * @return array<int,string>
     */
    private function parseEmails(string $csv): array
    {
        $parts = array_map('trim', explode(',', sanitize_text_field(wp_unslash($csv))));
        $emails = array_map('sanitize_email', $parts);
        return array_values(array_filter($emails, static fn (string $email): bool => $email !== ''));
    }

    /**
     * Converte CSV in lista ID interi.
     *
     * @return array<int,int>
     */
    private function parseIds(string $csv): array
    {
        $parts = array_map('trim', explode(',', sanitize_text_field(wp_unslash($csv))));
        $ids = array_map('absint', $parts);
        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
