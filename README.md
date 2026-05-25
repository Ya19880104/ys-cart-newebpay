# YS CART - NewebPay

Standalone NewebPay provider plugin for YS CART.

## Features

- NewebPay MPG form-post checkout using AES-256-CBC (`EncryptType=0`).
- Credit card, installment, ATM virtual account, CVS code, barcode, LINE Pay, and Apple Pay gateway variants.
- NotifyURL and ReturnURL handlers using YS CART payment lifecycle service.
- NewebPay query API wrapper.
- Credit-card close/refund and e-wallet refund wrappers.
- Convenience-store logistics fields persisted in YS CART-compatible storage:
  - `cvs_store_id`
  - `cvs_store_name`
  - `cvs_store_addr`
  - `payment_detail.newebpay_store`
  - `payment_detail.shipping`
- Bundled YS Plugin Hub Client for updates from yangsheep.com.tw.

## Requirements

- WordPress with YS CART active.
- PHP 8.1 or later.
- NewebPay MerchantID, HashKey, and HashIV.

## Installation

1. Install and activate YS CART.
2. Install and activate this plugin.
3. Open YS CART payment settings and configure NewebPay.
4. Enable the payment methods you want to expose at checkout.

## Callback URLs

Set these in NewebPay if the merchant portal requires explicit URLs:

- NotifyURL: `/wp-json/ys-ecommerce/v1/newebpay/notify`
- ReturnURL: `/wp-json/ys-ecommerce/v1/newebpay/return`

The plugin also sends these URLs in each MPG request.

## Release

Build the distributable ZIP:

```bash
php bin/build-release.php
```

The ZIP is written to `artifacts/ys-cart-newebpay-{version}.zip`.

## Security Notes

- HashKey and HashIV are stored through YS CART encrypted settings.
- Secrets are never exposed to frontend JavaScript.
- NotifyURL verifies `TradeSha` before decrypting `TradeInfo`.
- Optional `CheckCode` is verified when NewebPay includes it.
- Raw callback payload is stored only when debug mode is enabled.
