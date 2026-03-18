# Architettura FP Discount Gift

## Obiettivo

`FP-Discount-Gift` gestisce regole sconto custom lato FP, mantenendo compatibilita WooCommerce tramite shadow coupon tecnici e sincronizzando eventi voucher da `FP-Experiences`.

## Flussi principali

1. L'utente inserisce un coupon al checkout WooCommerce.
2. `CheckoutBridge` intercetta il codice e valuta la regola con `DiscountEngine`.
3. Se valida, viene creato/aggiornato uno shadow coupon (`FPDG-{CODE}`) via `ShadowCouponManager`.
4. Lo shadow coupon viene applicato al carrello.
5. Gli eventi funzionali e tracking vengono emessi (incluso forwarding a `fp_tracking_event` tramite `TrackingBridge`).

## Moduli

### Core

- `src/Core/Plugin.php`
  - bootstrap plugin
  - init servizi
  - activation/deactivation
- `src/Core/Roles.php`
  - capability custom:
    - `manage_fp_discountgift`
    - `view_fp_discountgift`

### Admin

- `src/Admin/SettingsPage.php`
  - menu admin
  - impostazioni plugin
  - CRUD regole:
    - create/update
    - delete singolo
    - bulk enable/disable/delete
  - sicurezza:
    - nonce
    - capability check
    - sanitize input

### Domain/Application

- `src/Domain/DiscountRule.php`
  - value object regola sconto
- `src/Application/DiscountEngine.php`
  - validazione regola su carrello/utente
  - calcolo importo sconto
  - selezione best rule

### Infrastructure/DB

- `src/Infrastructure/DB/Migrations.php`
  - crea tabelle custom
  - semina opzioni default
- `src/Infrastructure/DB/DiscountRuleRepository.php`
  - accesso dati regole
  - usage tracking
  - ledger eventi voucher

### Integrations

- `src/Integrations/WooCommerce/CheckoutBridge.php`
  - hook checkout/cart coupon
  - mapping coupon utente -> shadow coupon
  - emissione eventi sconto
- `src/Integrations/WooCommerce/ShadowCouponManager.php`
  - crea/aggiorna coupon tecnici
  - metadati:
    - `_fp_discountgift_shadow`
    - `_fp_discountgift_rule_id`
- `src/Integrations/Experiences/ExperienceEventBridge.php`
  - ascolta:
    - `fp_exp_gift_purchased`
    - `fp_exp_gift_voucher_redeemed`
  - persiste in ledger locale
  - emette evento interno `fp_discountgift_voucher_synced`
- `src/Integrations/Tracking/TrackingBridge.php`
  - traduce eventi interni in `fp_tracking_event`
  - integra analytics/Brevo passando da `FP-Marketing-Tracking-Layer`

## Tabelle DB

- `wp_fp_discountgift_rules`
- `wp_fp_discountgift_rule_usages`
- `wp_fp_discountgift_voucher_events`

## Opzioni WP

- `fp_discountgift_settings`
- `fp_discountgift_db_version`

## Principi anti-conflitto

- Non alterare coupon con `_fp_exp_is_gift_coupon = yes`.
- Escludere item gift (`_fp_exp_item_type = gift`) da applicazione shadow coupon.
- Eventi voucher deduplicati verso tracking layer (solo purchased/redeemed lato `fp_tracking_event`).
