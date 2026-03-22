=== FP Discount Gift ===
Contributors: franpass87
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.1.0
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

= 1.1.0 =
* Invio gift card via email (wp_mail e Brevo transazionale).
* Duplica regola sconto.
* Statistiche uso regole (usi, importo totale, ultimo utilizzo).
* Esporta/importa regole CSV.
* Filtro e ricerca regole per codice/titolo.
* Preview sconto in modifica regola (calcolo live).
* Cuponi usa-e-getta batch (genera N codici con usage_limit=1).

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
