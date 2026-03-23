# FP Discount Gift

Plugin WordPress per la gestione di codici sconto FP con integrazione WooCommerce in modalita shadow coupon e sincronizzazione eventi voucher da FP-Experiences.

![Version](https://img.shields.io/badge/version-1.2.5-blue)

## Funzionalita MVP (Fase 1)

- Regole sconto custom con campi principali compatibili con coupon WooCommerce.
- Applicazione in checkout tramite shadow coupon tecnico (`FPDG-{CODICE}`).
- Salvataggio usage regole su ordine.
- Pagina admin dedicata per impostazioni e CRUD regole (crea, modifica, elimina, bulk enable/disable/delete).
- Bridge eventi voucher da FP-Experiences (`fp_exp_gift_purchased`, `fp_exp_gift_voucher_redeemed`).
- Gift card native con saldo residuo e movimenti (`issued`, `redeemed`).
- Prodotto gift card WooCommerce: emissione automatica all'acquisto; opzione "Importo scelto dal cliente" con min/max.

## Integrazione WooCommerce

- Guard runtime: il bridge checkout viene caricato solo se WooCommerce e attivo.
- Protezione conflitti: i coupon con meta `_fp_exp_is_gift_coupon = yes` non vengono alterati.
- Compatibilita flow experiences: esclusione su item gift (`_fp_exp_item_type = gift`) per shadow coupon.

## Eventi emessi

- `fp_discountgift_discount_applied` (interno plugin)
- `fp_discountgift_discount_attempted` (interno plugin)
- `fp_discountgift_discount_rejected` (interno plugin)
- `fp_discountgift_discount_removed` (interno plugin)
- `fp_discountgift_voucher_synced` (interno plugin, emesso al sync da FP-Experiences)
- `fp_tracking_event` con evento `discount_applied` (se FP-Marketing-Tracking-Layer e attivo)
- `fp_tracking_event` con eventi `discount_code_attempted`, `discount_code_rejected`, `discount_removed`
- `fp_tracking_event` con eventi `gift_voucher_purchased` e `gift_voucher_redeemed`
- `fp_tracking_event` con eventi `gift_card_issued`, `gift_card_applied`, `gift_card_redeemed`, `gift_card_removed`

## Integrazione Brevo (via FP Tracking Layer)

L'invio a Brevo non viene fatto direttamente da questo plugin: passa da `FP-Marketing-Tracking-Layer`.

Per abilitarlo:
- attiva in FP Tracking le opzioni Brevo Server-Side (`brevo_enabled`, `brevo_api_key`);
- abilita gli eventi `discount_applied,discount_code_attempted,discount_code_rejected,discount_removed,gift_voucher_purchased,gift_voucher_redeemed` nella lista eventi Brevo;
- opzionale: mappa i nomi evento nel mapping Brevo JSON (es. `discount_applied -> coupon_applied`, `gift_voucher_synced -> gift_sync`).

I payload eventi includono `email` e `user_data` (quando disponibili), cosi Brevo riceve identificatori contatto validi.

## Documentazione completa

- [Architettura plugin](docs/ARCHITETTURA.md)
- [Guida eventi tracking](docs/EVENTI-TRACKING.md)
- [Setup Brevo via FP Tracking Layer](docs/BREVO-INTEGRAZIONE.md)
- [Guida test, regressioni e rilascio](docs/TEST-REGRESSIONI-RILASCIO.md)

## Struttura

```
FP-Discount-Gift/
├── fp-discount-gift.php
├── composer.json
├── src/
│   ├── Core/Plugin.php
│   ├── Admin/SettingsPage.php
│   ├── Application/DiscountEngine.php
│   ├── Domain/DiscountRule.php
│   ├── Infrastructure/DB/
│   └── Integrations/
└── assets/css/admin.css
```

## Requisiti

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (solo per funzioni checkout/sconto)

## Suggerimenti per sviluppi futuri

- **Template messaggio gift card**: personalizzazione avanzata email (Brevo/SMTP)
- **Regole per categoria prodotto**: restrizioni per taxonomy oltre a product IDs
- **Condizioni data/ora**: sconti attivi solo in fasce orarie (es. happy hour)
- **Checkout Blocks**: supporto campi gift card nel checkout a blocchi WooCommerce

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
