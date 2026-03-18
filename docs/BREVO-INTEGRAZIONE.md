# Integrazione Brevo

## Strategia

`FP-Discount-Gift` non parla direttamente con Brevo API.  
Usa `FP-Marketing-Tracking-Layer`, che gestisce il canale server-side Brevo (`/v3/events`).

## Prerequisiti

- `FP-Marketing-Tracking-Layer` attivo.
- `brevo_enabled = true` nelle impostazioni tracking.
- `brevo_api_key` configurata.
- endpoint Brevo corretto (`https://api.brevo.com/v3/events`).

## Eventi da abilitare nel tracking layer

CSV suggerito:

`discount_code_attempted,discount_code_rejected,discount_applied,discount_removed,gift_voucher_purchased,gift_voucher_redeemed`

## Mapping eventi (opzionale)

Esempio JSON per `fp_tracking_brevo_mapping`:

```json
{
  "discount_code_attempted": "coupon_attempt",
  "discount_code_rejected": "coupon_fail",
  "discount_applied": "coupon_applied",
  "discount_removed": "coupon_removed",
  "gift_voucher_purchased": "gift_purchased",
  "gift_voucher_redeemed": "gift_redeemed"
}
```

## Perche alcuni eventi Brevo vengono scartati

Il mapper Brevo richiede almeno un identificatore contatto (`email_id`, `ext_id`, `phone_id`).  
Se evento senza identificatori, il tracking layer puo saltare il dispatch.

## Cosa invia questo plugin per aiutare Brevo

- `email`
- `user_data.em`
- per eventi voucher, quando disponibile anche:
  - `user_data.fn`
  - `user_data.ln`
  - `user_data.ph`

## Verifica operativa

1. Applica un coupon in checkout.
2. Verifica in Event Inspector del tracking layer la presenza di `discount_applied`.
3. Verifica nella dashboard Brevo l'arrivo evento mappato.
4. Ripeti per `gift_voucher_purchased` e `gift_voucher_redeemed`.
