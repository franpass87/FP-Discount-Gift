## [1.0.10] - 2026-04-03

### Fixed

- **GiftCardProductIntegration.php**: rimosso di nuovo il frammento duplicato dopo la chiusura della classe (parse error `Unmatched '}'` alla riga ~709) che causava **HTTP 500** su WordPress con il plugin attivo.

## [1.0.9] - 2026-03-25

### Fixed

- **GiftCardProductIntegration.php**: eliminato il blocco duplicato e le righe orfane dopo la `}` di chiusura della classe (file fino a 734 righe) che causava `PHP Parse error: Unmatched '}'` e HTTP 500 su tutto il sito.

## [1.0.8] - 2026-03-25

### Fixed

- **GiftCardProductIntegration.php**: rimosso codice duplicato/frammentato dopo la chiusura della classe (parse error `Unmatched '}'` riga 709) che impediva il caricamento di WordPress con il plugin attivo.

## [1.0.7] - 2026-03-24

### Changed

- Email gift card via Brevo: payload `POST /v3/smtp/email` arricchito con `fp_tracking_brevo_merge_transactional_tags()` quando FP Marketing Tracking Layer è attivo (tag sito per log/sync transactional).

## [1.0.6] - 2026-03-24
### Changed
- Email gift card: corpo costruito come **frammento HTML** (card); con **FP Mail SMTP** attivo viene applicato `fp_fpmail_brand_html()` prima di `wp_mail` e di Brevo `htmlContent`. Senza FP Mail, mantenuto wrapper locale (sfondo + card). Il filtro `fp_discountgift_gift_card_email_body` riceve il frammento card; se restituisce un documento completo (`<!DOCTYPE` / `<html>`), non viene ri-avvolto.

## [1.0.5] - 2026-03-23
### Changed
- Menu position 56.1 per ordine alfabetico FP.
## [1.0.4] - 2026-03-22
### Added
- Campo "Scadenza (giorni dalla emissione)" sui prodotti WooCommerce gift card: consente di impostare la validità (es. 365 = 1 anno) per le gift card emesse all'acquisto. Supportato su prodotti semplici e variabili.

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
