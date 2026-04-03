=== FP Discount Gift ===
Contributors: franpass87
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.10
License: Proprietary
Tags: woocommerce, coupon, discount, gift card, fp

Plugin per la gestione codici sconto FP con compatibilita WooCommerce e sincronizzazione eventi voucher da FP-Experiences.

== Description ==

FP Discount Gift introduce un motore regole sconto FP che si integra con WooCommerce tramite shadow coupon tecnici.

Caratteristiche principali:
- regole sconto custom (fisso/percentuale, limiti, validita, restrizioni),
- applicazione checkout compatibile WooCommerce tramite shadow coupon,
- CRUD completo regole (crea, modifica, elimina, bulk action),
- audit eventi voucher da FP-Experiences,
- tracking eventi utili via `fp_tracking_event` (attempted, rejected, applied, removed, purchased, redeemed),
- integrazione Brevo server-side tramite FP-Marketing-Tracking-Layer.

== Installation ==

1. Carica la cartella plugin in `wp-content/plugins/FP-Discount-Gift`.
2. Assicurati che sia presente `vendor/`.
3. Attiva il plugin da Bacheca > Plugin.
4. Configura regole da menu `FP Discount Gift`.

== Changelog ==

= 1.0.10 =
* Fix critico: `GiftCardProductIntegration.php` — eliminato frammento duplicato dopo la classe (parse error riga ~709, HTTP 500).

= 1.0.9 =
* Fix critico: `GiftCardProductIntegration.php` — rimosso duplicato/orfano dopo la chiusura classe (parse error, sito in HTTP 500).

= 1.0.7 =
* Brevo gift card: merge tag sito via FP Tracking (`fp_tracking_brevo_merge_transactional_tags`) prima dell'API transactional.

= 1.0.6 =
* Email gift card: integrazione layout FP Mail SMTP (`fp_fpmail_brand_html`); frammento card + fallback se plugin assente.

= 1.0.5 =
* Menu position 56.1 per ordine alfabetico FP.

= 1.0.4 =
* Campo scadenza (giorni dalla emissione) sui prodotti WC gift card: valida 1 anno, 6 mesi, ecc.

= 1.0.3 =
* Corretto controllo discount_type in DiscountEngine: le regole percentuali e altri tipi venivano scartate.

= 1.0.2 =
* Interfaccia admin rinnovata con design system FP (card, toggle, badge, tabelle).
* Status bar con pill per stato funzionalità.
* Notice di successo per salvataggio, eliminazione, azioni bulk.
* Suggerimenti sviluppi futuri nel README.

= 1.0.1 =
* Documentazione completa aggiunta (architettura, tracking, Brevo, test e rilascio).
* Eventi tracking estesi e deduplicati per flussi analytics/Brevo.
* Aggiornamento capability menu admin su `manage_fp_discountgift`.

= 1.0.0 =
* Prima release MVP con regole sconto, bridge WooCommerce e sync eventi voucher Experiences.
