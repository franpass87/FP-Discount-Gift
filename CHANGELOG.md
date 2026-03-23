## [1.0.3] - 2026-03-22
### Fixed
- Corretto controllo `discount_type` in `DiscountEngine::isRuleApplicable()`: `wc_get_coupon_types()` restituisce array associativo, usare `array_keys()` per validare i tipi. Le regole con `percent` o altri tipi venivano sempre scartate.

## [1.0.2] - 2026-03-22
### Changed
- Interfaccia admin rinnovata con design system FP: card con header, toggle switch, badge, alert, tabelle thead viola.
- Status bar con pill per stato shadow coupon, auto-applicazione e conteggio regole.
- Notice di successo/errore per salvataggio, eliminazione, gift card emesse, azioni bulk.
- Form regole e gift card con griglia campi `fpdgift-fields-grid` e bottoni design system.
- Correzioni nullsafe per form nuova regola (`$edit_rule?->`).

### Added
- Sezione "Suggerimenti per sviluppi futuri" nel README (esporta CSV, duplica regola, statistiche, invio email gift card, ecc.).

## [1.0.1] - 2026-03-17
### Added
- Documentazione estesa in `docs/` con architettura, eventi tracking, setup Brevo e checklist test/rilascio.
- Nuovi eventi tracking utili: `discount_code_attempted`, `discount_code_rejected`, `discount_removed`, `gift_voucher_purchased`, `gift_voucher_redeemed`.

### Changed
- Pulizia eventi per evitare doppioni verso tracking layer/Brevo: mantenuto `fp_discountgift_voucher_synced` come evento interno e inviati solo eventi voucher specifici al bus `fp_tracking_event`.
- Menu admin allineato alla capability plugin `manage_fp_discountgift`.

## [1.0.0] - 2026-03-17
### Added
- Scaffold iniziale del plugin FP Discount Gift con bootstrap, autoload PSR-4 e uninstall.
- Migrazioni database per regole sconto, usage e ledger eventi voucher.
- Motore regole sconto con supporto campi principali equivalenti ai coupon WooCommerce.
- Integrazione WooCommerce con shadow coupon e gestione applicazione checkout.
- Pagina admin con impostazioni e CRUD base delle regole sconto.
- Bridge eventi FP-Experiences per sincronizzazione acquisto/riscatto voucher.
- Bridge tracking verso `fp_tracking_event` per evento `discount_applied`.
