# Headless Notes

YS CART checkout receives NewebPay as normal payment gateway IDs:

- `ys_ec_newebpay_credit`
- `ys_ec_newebpay_installment`
- `ys_ec_newebpay_atm`
- `ys_ec_newebpay_cvs`
- `ys_ec_newebpay_barcode`
- `ys_ec_newebpay_linepay`
- `ys_ec_newebpay_applepay`

After checkout succeeds, the API response includes `form_data`:

```json
{
  "form_data": {
    "action_url": "https://ccore.newebpay.com/MPG/mpg_gateway",
    "fields": {
      "MerchantID": "...",
      "TradeInfo": "...",
      "TradeSha": "...",
      "Version": "2.3",
      "EncryptType": "0"
    }
  }
}
```

Submit those fields as a regular HTML `POST` form in the top-level browser window. NewebPay MPG should not be loaded through an iframe or proxy.

## Convenience-store store selection

For NewebPay logistics methods, request a store-map form from YS CART before
sending the customer to NewebPay store selection:

```text
POST /wp-json/ys-ecommerce-headless/v1/stores/newebpay/map-url
```

Expected payload:

```json
{
  "shipping_id": "ys_ec_newebpay_ship_711_c2c"
}
```

The response contains `action_url` and hidden `fields`. Submit the returned form
in the top-level browser window or a popup; do not call the store callback route
from browser UI.

Convenience-store logistics data returned by NewebPay callbacks is normalized to:

```json
{
  "store_id": "store code",
  "store_name": "store name",
  "store_address": "store address",
  "recipient_name": "recipient",
  "recipient_phone": "phone",
  "lgs_no": "logistics number",
  "lgs_type": "B2C or C2C"
}
```

YS CART stores compatible fields in the order table and keeps provider-specific audit data in `payment_detail`.
