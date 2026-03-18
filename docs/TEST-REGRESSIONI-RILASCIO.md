# Test, Regressioni e Rilascio

## Test rapidi locali

Da cartella plugin:

```bash
composer dump-autoload
composer run test:smoke
```

Lint PHP:

```bash
php -l src/Core/Plugin.php
```

## Test funzionali minimi

### Sconto

1. Crea regola sconto da admin.
2. Inserisci coupon in checkout WooCommerce.
3. Verifica:
   - evento attempted
   - eventuale rejected o applied
   - shadow coupon applicato (`FPDG-*`).

### Rimozione

1. Rimuovi coupon shadow.
2. Verifica evento `discount_removed`.

### Voucher FP-Experiences

1. Simula acquisto voucher experiences.
2. Verifica ledger in tabella `wp_fp_discountgift_voucher_events`.
3. Verifica evento `gift_voucher_purchased`.
4. Simula redeem voucher e verifica `gift_voucher_redeemed`.

## Checklist regressione (sintesi)

- Nessun fatal all'attivazione plugin.
- Admin page accessibile con capability corretta.
- Nessuna interferenza con coupon gift experiences.
- Eventi tracking senza doppioni non voluti.
- Payload con `email/user_data` quando disponibili.

## Rilascio (workflow consigliato)

1. Aggiorna versione:
   - header plugin
   - costante plugin
   - badge README
   - `Stable tag` in `readme.txt`
2. Aggiorna `CHANGELOG.md`.
3. Commit con messaggio Conventional Commits.
4. Push su `main`.
5. Crea tag release:
   - `git tag -a vX.Y.Z -m "Release vX.Y.Z"`
   - `git push origin vX.Y.Z`
