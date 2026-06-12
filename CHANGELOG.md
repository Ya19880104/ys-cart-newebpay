# Changelog

## [1.0.11] - 2026-06-12

### Security
- Webhook and reconcile now pass the gateway-reported `Amt` (already protected
  by the TradeSha/CheckCode signature) into the payment detail DTO so YS CART
  core verifies `paid_amount` against the order total before marking the order
  paid. Previously the amount guard was a no-op for NewebPay because no
  `paid_amount` was supplied.
