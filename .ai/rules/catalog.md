---
paths:
    - "app/Modules/Core/Catalog/**"
---

# Catalog

## Money as major-unit decimals

Persist and pass monetary amounts as major-unit decimal strings (`decimal:18` casts). Do not introduce Brick Money objects or minor-unit integers.

## Quote as of a moment is the latest observed at or before that moment

`Currency::quoteAsOf($at)` is the latest quote with `observed_at <= $at`. Quotes after that instant are ignored. `currentQuote` is the same relation constrained to `now()`. Transaction store defaults a blank quote from `quoteAsOf(occurred_at)`, not from `currentQuote`.

## Scheduled quotes come from QuoteProviders

Currencies with quote_source=scheduled are filled by catalog:sync-scheduled-quotes. Register each source as a QuoteProvider on AggregateQuoteProvider (BCU TXT is the first). Keep provider URLs on the implementation class, not in config/services.php. Persist rates as 1 USD = X major-unit decimals; BCU ** rows are inverted. The BCU HTTP client skips TLS verification because *.bcu.gub.uy uses an Abitab intermediate that is not in public CA stores. A 200 body of "Servicio no disponible" is a BCU outage, not a parse error.
