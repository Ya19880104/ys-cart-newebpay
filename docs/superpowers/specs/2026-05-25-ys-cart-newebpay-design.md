# YS CART NewebPay Design

Date: 2026-05-25

## Scope

Build `ys-cart-newebpay` as a standalone YS CART provider plugin, following the PayUni/PayNow external-provider pattern. The first public release covers NewebPay MPG payments and preserves convenience-store logistics fields returned by NewebPay callbacks.

## Provider Model

- Do not modify YS CART core.
- Register gateways through `ys_ec_register_gateways`.
- Register provider card through `ys_ec_providers`.
- Register settings under the existing YS CART payment admin menu.
- Register server-to-server routes through external plugin hooks.
- Bundle `vendor/yangsheep/ys-plugin-hub-client` so YS Hub can discover updates.

## Payment Methods

- `ys_ec_newebpay_credit`
- `ys_ec_newebpay_installment`
- `ys_ec_newebpay_atm`
- `ys_ec_newebpay_cvs`
- `ys_ec_newebpay_barcode`
- `ys_ec_newebpay_linepay`
- `ys_ec_newebpay_applepay`

Each gateway submits an MPG form with `MerchantID`, encrypted `TradeInfo`, `TradeSha`, and `Version=2.3`.

## Callback Lifecycle

- Verify `TradeSha` before decrypting payload.
- Decrypt `TradeInfo` with AES-256-CBC and decode JSON or query-string payload.
- Use `YSPaymentLifecycleService::mark_paid()`, `mark_pending_offline()`, or `mark_failed()`.
- Keep idempotency in `payment_detail` with provider status and gateway trade number.
- Persist logistics/store fields in `payment_detail.newebpay_store` and `payment_detail.shipping`.

## Logistics Storage

YS CART already stores checkout store selection in order table columns:

- `cvs_store_id`
- `cvs_store_name`
- `cvs_store_addr`

NewebPay callback fields such as `StoreCode`, `StoreName`, `StoreAddr`, `CVSCOMName`, `CVSCOMPhone`, `LgsNo`, and `LgsType` are kept in `payment_detail` for provider audit and later logistics reconciliation. The first release does not add new order columns.

## Release

- GitHub public repo: `Ya19880104/ys-cart-newebpay`
- Hub slug: `ys-cart-newebpay`
- Platform: `ys-cart`
- Category: `payment`
- Version: `1.0.0`
