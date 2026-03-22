## [1.2.3] - 2026-03-23
### Changed
- Brevo centralizzato: API key e stato abilitato ora letti da `fp_tracking_get_brevo_settings()` (FP-Tracking) con fallback a fp_tracking_settings.

## [1.2.2] - 2026-03-22
### Added
- Prodotto gift card: opzione "Importo scelto dal cliente" — il cliente inserisce l'importo che diventa prezzo e saldo.
- Importo minimo e massimo configurabili per prodotto (e varianti).
- Supporto prodotti variabili: campo importo si mostra quando si seleziona una variante con importo personalizzato.

## [1.2.1] - 2026-03-22
### Changed
- Template email gift card HTML ristrutturato: layout tabellare, header gradiente, card codice, CTA evidente.
- Supporto template Brevo: campo "ID template Brevo" in impostazioni; se configurato usa templateId + params.
- Parametri template Brevo: CODE, AMOUNT, CURRENCY, EXPIRES_AT, SITE_NAME, SITE_URL, CHECKOUT_URL, MESSAGE.
- Documentazione Brevo: eventi gift_card_*, guida template e parametri.

## [1.2.0] - 2026-03-22
### Added
- Prodotto gift card WooCommerce: marca prodotti (anche varianti) come gift card tramite checkbox in scheda prodotto.
- All'acquisto di un prodotto gift card, emissione automatica di gift card e invio email al destinatario.
- Campi checkout (email e nome destinatario) quando il carrello contiene prodotti gift card.
- Integrazione `GiftCardProductIntegration`: meta prodotto, campi checkout, handler ordine completato.

## [1.1.0] - 2026-03-22
### Added
- Invio gift card via email: wp_mail e Brevo Transactional (se FP-Marketing-Tracking-Layer configurato).
- Duplica regola sconto con generazione codice univoco.
- Statistiche uso regole: colonne Usi, Ultimo uso in tabella.
- Esporta regole in CSV e importa da CSV.
- Filtro e ricerca regole per codice o titolo.
- Preview sconto in modifica regola (AJAX con sottototale di test).
- Cuponi usa-e-getta batch: genera N regole con usage_limit=1.

### Changed
- Impostazioni: toggle invio email gift card e uso Brevo.
- Migrazioni: default gift_card_send_email, gift_card_email_via_brevo.
- DiscountEngine: metodo calculateDiscountForSubtotal() per preview.
- Admin: form ricerca, link Esporta CSV, form Importa CSV, box batch single-use, box preview.

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
