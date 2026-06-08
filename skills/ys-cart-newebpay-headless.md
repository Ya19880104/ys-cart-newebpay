# YS CART NewebPay Headless Skill

Use this when integrating `ys-cart-newebpay` into a YS CART headless checkout.

## Rules

- Treat NewebPay checkout as a top-level HTML form POST.
- Do not proxy MPG form submission through the backend.
- Do not render MPG inside an iframe.
- Never expose Merchant HashKey or HashIV to frontend code.
- Use the `form_data.action_url` and `form_data.fields` returned by checkout.
- For convenience-store logistics, request `/stores/newebpay/map-url` with the
  selected `shipping_id`, then submit the returned `action_url` and `fields`.
- Treat `/newebpay/store-callback` and `/newebpay/shipping-notify` as
  provider/server callback routes, not browser UI APIs.
- After NewebPay returns, trust the server-side NotifyURL/ReturnURL processing rather than frontend-only status.

## Gateway IDs

- `ys_ec_newebpay_credit`
- `ys_ec_newebpay_installment`
- `ys_ec_newebpay_atm`
- `ys_ec_newebpay_cvs`
- `ys_ec_newebpay_barcode`
- `ys_ec_newebpay_linepay`
- `ys_ec_newebpay_applepay`

## Shipping IDs

- `ys_ec_newebpay_ship_711_c2c`
- `ys_ec_newebpay_ship_family_c2c`
- `ys_ec_newebpay_ship_hilife_c2c`
- `ys_ec_newebpay_ship_ok_c2c`
- `ys_ec_newebpay_ship_711_b2c`

## Store Data

When NewebPay returns CVSCOM fields, preserve the normalized shape:

- `store_id`
- `store_name`
- `store_address`
- `recipient_name`
- `recipient_phone`
- `lgs_no`
- `lgs_type`
