=== FP Discount Gift ===
Contributors: franpass87
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: Proprietary
Tags: woocommerce, coupon, discount, gift card, fp

Plugin per la gestione codici sconto FP con compatibilita WooCommerce e sincronizzazione eventi voucher da FP-Experiences.

== Description ==

FP Discount Gift introduce un motore regole sconto FP che si integra con WooCommerce tramite shadow coupon tecnici.

Caratteristiche principali:
- regole sconto custom (fisso/percentuale, limiti, validita, restrizioni),
- applicazione checkout compatibile WooCommerce,
- audit eventi voucher da FP-Experiences,
- tracking evento `discount_applied` via `fp_tracking_event`.

== Installation ==

1. Carica la cartella plugin in `wp-content/plugins/FP-Discount-Gift`.
2. Assicurati che sia presente `vendor/`.
3. Attiva il plugin da Bacheca > Plugin.
4. Configura regole da menu `FP Discount Gift`.

== Changelog ==

= 1.0.0 =
* Prima release MVP con regole sconto, bridge WooCommerce e sync eventi voucher Experiences.
