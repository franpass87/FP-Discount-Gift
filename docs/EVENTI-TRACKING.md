# Eventi Tracking

## Panoramica

Il plugin non invia direttamente a GA4/Meta/Brevo.  
Invia eventi a `do_action('fp_tracking_event', ...)` e demanda dispatch e mapping a `FP-Marketing-Tracking-Layer`.

## Eventi interni plugin

- `fp_discountgift_discount_attempted`
- `fp_discountgift_discount_rejected`
- `fp_discountgift_discount_applied`
- `fp_discountgift_discount_removed`
- `fp_discountgift_voucher_synced` (interno, non inoltrato direttamente a tracking layer)

## Eventi inoltrati a `fp_tracking_event`

- `discount_code_attempted`
- `discount_code_rejected`
- `discount_applied`
- `discount_removed`
- `gift_voucher_purchased`
- `gift_voucher_redeemed`

## Payload consigliato

Campi comuni:

- `event_id` (univoco)
- `source` = `fp-discount-gift`
- `email` (se disponibile)
- `user_data` (`em`, `fn`, `ln`, `ph` quando disponibili)

Campi evento sconto:

- `coupon`
- `reason` (solo reject)
- `value`, `currency` (quando disponibili)

Campi evento voucher:

- `voucher_id`
- `order_id`
- `reservation_id` (redeemed)

## Note deduplicazione

- `gift_voucher_synced` resta solo evento interno.
- Verso tracking layer vengono inviati solo gli eventi voucher semantici finali (`gift_voucher_purchased`, `gift_voucher_redeemed`).
