# YS CART NewebPay Headless Skill

Use this when integrating `ys-cart-newebpay` into a YS CART headless checkout.

## Rules

- Treat NewebPay checkout as a top-level HTML form POST.
- Do not proxy MPG form submission through the backend.
- Do not render MPG inside an iframe.
- Never expose Merchant HashKey or HashIV to frontend code.
- Use the `form_data.action_url` and `form_data.fields` returned by checkout.
- After NewebPay returns, trust the server-side NotifyURL/ReturnURL processing rather than frontend-only status.

## Gateway IDs

- `ys_ec_newebpay_credit`
- `ys_ec_newebpay_installment`
- `ys_ec_newebpay_atm`
- `ys_ec_newebpay_cvs`
- `ys_ec_newebpay_barcode`
- `ys_ec_newebpay_linepay`
- `ys_ec_newebpay_applepay`

## Store Data

When NewebPay returns CVSCOM fields, preserve the normalized shape:

- `store_id`
- `store_name`
- `store_address`
- `recipient_name`
- `recipient_phone`
- `lgs_no`
- `lgs_type`
