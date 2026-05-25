# YS CART NewebPay Architecture Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `ys-cart-newebpay` as a YS CART external payment and logistics provider that follows the PAYUNI/PAYNOW architecture and covers NewebPay official logistics API group #4.

**Architecture:** Keep YS CART core as the host and implement NewebPay entirely in the provider plugin through existing hooks: gateways, provider card, admin menu, storefront routes, shipping methods, requester, carrier adapter, labels, and external admin page registration. Logistics uses NewebPay `/API/Logistic/*` encrypted envelope with YS CART order/shipping-label storage instead of custom one-off persistence.

**Tech Stack:** WordPress plugin PHP 8.1, YS CART gateway/shipping interfaces, WP REST routes, YSAdminApp/YSCA admin UI, NewebPay MPG and Logistics APIs.

---

### Task 1: Contract Tests

**Files:**
- Modify: `tests/regression/v100_newebpay_contract.php`

- [ ] Add assertions for YS CART provider hooks:
  `ys_ec_register_shipping_methods`, `ys_ec_shipping_requester`, `ys_ec_shipping_carrier_adapter`, `ys_ec_shipping_provider_labels`, `ys_ec_register_storefront_routes`, and `ys_ec_external_admin_pages`.
- [ ] Add assertions for provider card content:
  `admin.php?page=ys-ec-newebpay`, Traditional Chinese method names, C2C/B2C logistics badges, and no `CVSCOM store data passthrough`.
- [ ] Add assertions for official NewebPay logistics endpoints:
  `storeMap`, `createShipment`, `getShipmentNo`, `printLabel`, `queryShipment`, `modifyShipment`, `trace`, `EncryptData_`, `HashData_`, `UID_`, and `Version_`.
- [ ] Add assertions for shipping classes:
  `YSNewebpayShipping711C2C`, `YSNewebpayShippingFamilyC2C`, `YSNewebpayShippingHilifeC2C`, `YSNewebpayShippingOkC2C`, `YSNewebpayShipping711B2C`, and their `LgsType`/`ShipType` values.
- [ ] Run: `php tests/regression/v100_newebpay_contract.php`
  Expected before implementation: FAIL on missing shipping/logistics contract.

### Task 2: Plugin Registration And Admin Entry

**Files:**
- Modify: `src/Plugin.php`
- Modify: `src/Gateway/Newebpay/YSNewebpaySettings.php`

- [ ] Register NewebPay shipping methods via `ys_ec_register_shipping_methods`.
- [ ] Register storefront REST route `/stores/newebpay/map-url`.
- [ ] Register legacy public REST routes `/newebpay/store-callback` and `/newebpay/shipping-notify`.
- [ ] Register shipping requester, carrier adapter, provider label, and external admin pages.
- [ ] Change provider `admin_url` to relative `admin.php?page=ys-ec-newebpay`.
- [ ] Register primary admin slug `ys-ec-newebpay` and hidden legacy slug `ys-ecommerce-newebpay` to avoid old links breaking.
- [ ] Update settings enqueue/page checks to accept both slugs.

### Task 3: NewebPay Logistics Client

**Files:**
- Create: `src/Logistics/Newebpay/YSNewebpayLogisticsClient.php`

- [ ] Implement test/prod base URLs:
  `https://ccore.newebpay.com/API/Logistic/` and `https://core.newebpay.com/API/Logistic/`.
- [ ] Implement encrypted envelope:
  top-level `UID_`, `EncryptData_`, `HashData_`, `Version_ = 1.0`, `RespondType_ = JSON`.
- [ ] Implement AES-256-CBC hex encryption/decryption and SHA256 hash verification using NewebPay logistics hash format.
- [ ] Implement request builders for `storeMap`, `createShipment`, `getShipmentNo`, `printLabel`, `queryShipment`, `modifyShipment`, and `trace`.
- [ ] Return normalized arrays with `success`, `data`, `message`, `status`, and `raw`.

### Task 4: Shipping Methods

**Files:**
- Create: `src/Shipping/Newebpay/YSNewebpayShipping.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShipping711C2C.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShippingFamilyC2C.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShippingHilifeC2C.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShippingOkC2C.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShipping711B2C.php`

- [ ] Implement `YSShippingInterface` with provider `newebpay`, type `cvs`, Taiwan-only support, and `supports_cvs_selection() = true`.
- [ ] Set carrier codes:
  7-ELEVEN C2C `LgsType=C2C`, `ShipType=1`;
  FamilyMart C2C `LgsType=C2C`, `ShipType=2`;
  HiLife C2C `LgsType=C2C`, `ShipType=3`;
  OK mart C2C `LgsType=C2C`, `ShipType=4`;
  7-ELEVEN B2C `LgsType=B2C`, `ShipType=1`.
- [ ] Use YS CART shipping method settings keys `shipping_{method_id}_{field}` for enable, fee, free threshold, amount, and weight limits.
- [ ] Use NewebPay global settings for merchant ID, hash key, hash IV, and sandbox mode.

### Task 5: Store Map And Shipment Requester

**Files:**
- Create: `src/Shipping/Newebpay/YSNewebpayStoreSelector.php`
- Create: `src/Shipping/Newebpay/YSNewebpayShippingRequester.php`

- [ ] Build map form data from `/stores/newebpay/map-url` with `MerchantOrderNo`, `LgsType`, `ShipType`, `ReturnURL`, `TimeStamp`, and `ExtraData`.
- [ ] Persist selection state in transients and render a callback page that posts `type=ys_ec_store_selected`, `provider=newebpay`, and normalized store data back to the opener.
- [ ] Implement `create_order()` using `createShipment` with YS CART order data, selected store ID, amount, item description, notify URL, `LgsType`, `ShipType`, and `TradeType`.
- [ ] Implement `query_status()` with `queryShipment` and `trace`.
- [ ] Implement `get_print_url()` as a form-payload token/HTML flow compatible with YS CART's print URL filter.
- [ ] Implement `modify_order()` and `get_shipment_no()` helpers even if not yet exposed by core UI, because they are part of official #4.

### Task 6: Shipping Notify And Adapter

**Files:**
- Create: `src\Api\YSNewebpayShippingNotifyController.php`
- Create: `src\Services\Shipping\Adapters\YSNewebpayAdapter.php`

- [ ] Register `POST /wp-json/ys-ecommerce/v1/newebpay/shipping-notify`.
- [ ] Verify/decrypt `EncryptData_` and `HashData_`, normalize `MerchantOrderNo`, `LgsNo`, `RetId`, `RetString`, and `EventTime`.
- [ ] Update YS CART shipping label/order state through existing YS CART shipping pipeline when available.
- [ ] Map NewebPay statuses such as `0_1`, `1`, `4`, `5`, `6`, return/cancel/error codes to unified pipeline states.

### Task 7: PAYUNI-Style Admin UI

**Files:**
- Modify: `src/Gateway/Newebpay/YSNewebpaySettings.php`
- Replace: `templates/admin/gateways/newebpay-settings.php`

- [ ] Use `YSAdminApp::open('NewebPay', '金物流 / NewebPay')`.
- [ ] Use YSCA card/form classes (`ysca-card`, `ysca-card__body`, `ysca-form-grid`, `ysca-field`, `ysca-input`, `ysca-select`, `ysca-actions-bar`).
- [ ] Split settings into tabs/sections matching PAYUNI style: API, payment, logistics, endpoints/log.
- [ ] Add logistics toggles for C2C 7-ELEVEN, FamilyMart, HiLife, OK mart, and B2C 7-ELEVEN.
- [ ] Show callback URLs for payment notify/return, store callback, and shipping notify.
- [ ] Keep secret fields blank after save with "saved, leave blank to keep" hint.

### Task 8: Verification, Release, Dev Deployment

**Files:**
- Modify: `ys-cart-newebpay.php`
- Use: `bin/build-release.php`

- [ ] Run PHP syntax checks across plugin runtime files excluding `vendor`.
- [ ] Run regression tests.
- [ ] Run `git diff --check`.
- [ ] Bump plugin version to `1.0.1`.
- [ ] Build release zip and confirm it excludes development-only files.
- [ ] Deploy to `dev-newecommerce` and activate/update the plugin.
- [ ] Verify provider card setting URL no longer double-wraps.
- [ ] Verify the settings page renders in YSAdminApp/YSCA style.
- [ ] Verify YS Hub release metadata points to the new version.
