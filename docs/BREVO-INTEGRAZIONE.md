# Integrazione Brevo

## Strategia

`FP-Discount-Gift` integra Brevo in due modi:

1. **Eventi** (`/v3/events`): tramite `FP-Marketing-Tracking-Layer` e `fp_tracking_event`.
2. **Email transazionali** (`/v3/smtp/email`): invio diretto via API Brevo (opzionale, se abilitato in impostazioni).

## Prerequisiti

- `FP-Marketing-Tracking-Layer` attivo (per eventi).
- `brevo_enabled = true` nelle impostazioni tracking.
- `brevo_api_key` configurata.
- endpoint Brevo: `https://api.brevo.com/v3/events` (eventi), `https://api.brevo.com/v3/smtp/email` (email).

## Eventi da abilitare nel tracking layer

CSV suggerito per eventi Brevo:

`discount_code_attempted,discount_code_rejected,discount_applied,discount_removed,gift_voucher_purchased,gift_voucher_redeemed,gift_card_issued,gift_card_applied,gift_card_redeemed,gift_card_removed,gift_card_expiring_soon,gift_card_expired`

## Mapping eventi (opzionale)

Esempio JSON per `fp_tracking_brevo_mapping`:

```json
{
  "discount_code_attempted": "coupon_attempt",
  "discount_code_rejected": "coupon_fail",
  "discount_applied": "coupon_applied",
  "discount_removed": "coupon_removed",
  "gift_voucher_purchased": "gift_purchased",
  "gift_voucher_redeemed": "gift_redeemed",
  "gift_card_issued": "gift_card_sent",
  "gift_card_applied": "gift_card_used",
  "gift_card_redeemed": "gift_card_redeemed",
  "gift_card_expiring_soon": "gift_card_expiring"
}
```

## Template Brevo per email gift card

Se usi Brevo per le email gift card, puoi:

1. **HTML standard** (default): il plugin invia un HTML ben strutturato come `htmlContent`.
2. **Template Brevo**: imposta in FP Discount Gift > Impostazioni l’**ID template Brevo**. Il plugin userà `templateId` + `params`.

### Parametri template

Nel template Brevo usa `{{ params.NOME }}`:

| Parametro   | Descrizione                      |
|-------------|----------------------------------|
| `CODE`      | Codice gift card                 |
| `AMOUNT`    | Importo                          |
| `CURRENCY`  | Valuta (es. EUR)                 |
| `EXPIRES_AT`| Data scadenza (vuoto se illimitata) |
| `SITE_NAME` | Nome sito                        |
| `SITE_URL`  | URL home                         |
| `CHECKOUT_URL` | URL checkout                  |
| `MESSAGE`   | Messaggio introduttivo           |

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
