<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function read_file(string $relative): string {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$relative}");
    }
    return (string) file_get_contents($path);
}

function assert_contains(string $needle, string $haystack, string $label): void {
    if (false === strpos($haystack, $needle)) {
        throw new RuntimeException("Missing {$label}: {$needle}");
    }
}

$main = read_file('ys-cart-newebpay.php');
assert_contains('Plugin Name: YS CART - NewebPay', $main, 'plugin header');
assert_contains("'slug'        => 'ys-cart-newebpay'", $main, 'Hub slug');
assert_contains('YS_CART_NEWEBPAY_VERSION', $main, 'version constant');

$plugin = read_file('src/Plugin.php');
foreach ([
    'ys_ec_register_gateways',
    'ys_ec_register_shipping_methods',
    'ys_ec_providers',
    'ys_ec_admin_payment_menus',
    'ys_ec_register_admin_rest_routes',
    'ys_ec_register_storefront_routes',
    'ys_ec_shipping_requester',
    'ys_ec_shipping_carrier_adapter',
    'ys_ec_shipping_provider_labels',
    'ys_ec_external_admin_pages',
] as $hook) {
    assert_contains($hook, $plugin, "hook {$hook}");
}

foreach ([
    "admin.php?page=ys-ec-newebpay",
    '信用卡',
    '分期付款',
    'ATM 虛擬帳號',
    '超商代碼',
    '條碼繳費',
    'LINE Pay',
    'Apple Pay',
    '7-ELEVEN C2C 店到店',
    '全家 C2C 店到店',
    '萊爾富 C2C 店到店',
    'OK mart C2C 店到店',
    '7-ELEVEN B2C 大宗寄倉',
] as $providerNeedle) {
    assert_contains($providerNeedle, $plugin, "provider card {$providerNeedle}");
}

if (false !== strpos($plugin, 'CVSCOM store data passthrough')) {
    throw new RuntimeException('Provider card must not describe logistics as CVSCOM passthrough.');
}

foreach ([
    'ys_ec_newebpay_credit',
    'ys_ec_newebpay_installment',
    'ys_ec_newebpay_atm',
    'ys_ec_newebpay_cvs',
    'ys_ec_newebpay_barcode',
    'ys_ec_newebpay_linepay',
    'ys_ec_newebpay_applepay',
] as $gatewayId) {
    $found = false;
    foreach (glob($root . '/src/Gateway/Newebpay/*.php') as $file) {
        if (false !== strpos((string) file_get_contents($file), $gatewayId)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new RuntimeException("Missing gateway id: {$gatewayId}");
    }
}

$client = read_file('src/Gateway/Newebpay/YSNewebpayClient.php');
assert_contains('https://ccore.newebpay.com/MPG/mpg_gateway', $client, 'test MPG URL');
assert_contains('https://core.newebpay.com/MPG/mpg_gateway', $client, 'prod MPG URL');
assert_contains("public const MPG_VERSION = '2.3'", $client, 'MPG version');
assert_contains("'EncryptType'=> '0'", $client, 'AES-CBC encrypt type');
assert_contains('HashKey=', $client, 'TradeSha input');
assert_contains('QueryTradeInfo', $client, 'query API');
assert_contains('CreditCard/Close', $client, 'credit refund API');
assert_contains('EWallet/Refund', $client, 'ewallet refund API');

$logistics = read_file('src/Logistics/Newebpay/YSNewebpayLogisticsClient.php');
foreach ([
    'https://ccore.newebpay.com/API/Logistic/',
    'https://core.newebpay.com/API/Logistic/',
    'storeMap',
    'createShipment',
    'getShipmentNo',
    'printLabel',
    'queryShipment',
    'modifyShipment',
    'trace',
    'EncryptData_',
    'HashData_',
    'UID_',
    "Version_",
    "RespondType_",
] as $logisticsNeedle) {
    assert_contains($logisticsNeedle, $logistics, "logistics {$logisticsNeedle}");
}

foreach ([
    'src/Shipping/Newebpay/YSNewebpayShipping.php' => [ "return 'newebpay'", "return 'cvs'", 'supports_cvs_selection' ],
    'src/Shipping/Newebpay/YSNewebpayShipping711C2C.php' => [ 'ys_ec_newebpay_ship_711_c2c', "return 'C2C'", "return '1'" ],
    'src/Shipping/Newebpay/YSNewebpayShippingFamilyC2C.php' => [ 'ys_ec_newebpay_ship_family_c2c', "return 'C2C'", "return '2'" ],
    'src/Shipping/Newebpay/YSNewebpayShippingHilifeC2C.php' => [ 'ys_ec_newebpay_ship_hilife_c2c', "return 'C2C'", "return '3'" ],
    'src/Shipping/Newebpay/YSNewebpayShippingOkC2C.php' => [ 'ys_ec_newebpay_ship_ok_c2c', "return 'C2C'", "return '4'" ],
    'src/Shipping/Newebpay/YSNewebpayShipping711B2C.php' => [ 'ys_ec_newebpay_ship_711_b2c', "return 'B2C'", "return '1'" ],
] as $shippingFile => $needles) {
    $shippingSource = read_file($shippingFile);
    foreach ($needles as $needle) {
        assert_contains($needle, $shippingSource, "{$shippingFile} {$needle}");
    }
}

$storeSelector = read_file('src/Shipping/Newebpay/YSNewebpayStoreSelector.php');
assert_contains('/newebpay/store-callback', $storeSelector, 'store callback route reference');
assert_contains('ys_ec_store_selected', $storeSelector, 'store callback postMessage');
assert_contains("localStorage.setItem('ys_ec_selected_store'", $storeSelector, 'store callback localStorage fallback');
assert_contains("sessionStorage.setItem('ys_ec_selected_store'", $storeSelector, 'store callback sessionStorage fallback');
assert_contains('_transient_ys_ec_newebpay_map_', $storeSelector, 'store callback merchant order fallback lookup');
assert_contains("'provider' => 'newebpay'", $storeSelector, 'store callback provider');

$shippingBase = read_file('src/Shipping/Newebpay/YSNewebpayShipping.php');
assert_contains('get_default_max_weight', $shippingBase, 'carrier-specific max weight hook');

$shipping711 = read_file('src/Shipping/Newebpay/YSNewebpayShipping711C2C.php');
assert_contains('return 10.0', $shipping711, '7-ELEVEN C2C max weight');

$shipping711B2C = read_file('src/Shipping/Newebpay/YSNewebpayShipping711B2C.php');
assert_contains('return 10.0', $shipping711B2C, '7-ELEVEN B2C max weight');

$requester = read_file('src/Shipping/Newebpay/YSNewebpayShippingRequester.php');
foreach ([ 'create_order', 'query_status', 'get_print_url', 'modify_order', 'get_shipment_no' ] as $method) {
    assert_contains("function {$method}", $requester, "requester method {$method}");
}

$notify = read_file('src/Api/YSNewebpayShippingNotifyController.php');
assert_contains('/newebpay/shipping-notify', $notify, 'shipping notify route');
assert_contains('YSShippingHandler', $notify, 'shipping handler integration');

$adapter = read_file('src/Services/Shipping/Adapters/YSNewebpayAdapter.php');
assert_contains("return 'newebpay'", $adapter, 'adapter id');
assert_contains('YSShippingPipelineState', $adapter, 'pipeline state mapping');

$callback = read_file('src/Api/YSNewebpayCallbackController.php');
assert_contains('/newebpay/notify', $callback, 'notify route');
assert_contains('/newebpay/return', $callback, 'return route');
assert_contains('YSWebhookGuard::check_replay', $callback, 'replay guard');

$handler = read_file('src/Gateway/Newebpay/YSNewebpayWebhookHandler.php');
assert_contains('YSPaymentLifecycleService::mark_paid', $handler, 'mark paid lifecycle');
assert_contains('YSPaymentLifecycleService::mark_pending_offline', $handler, 'pending lifecycle');
assert_contains('YSPaymentLifecycleService::mark_failed', $handler, 'failed lifecycle');
assert_contains("'cvs_store_id'", $handler, 'cvs store id persistence');
assert_contains("'payment_detail' => wp_json_encode", $handler, 'payment detail persistence');
assert_contains("'shipping'", $handler, 'shipping detail persistence');

$settings = read_file('src/Gateway/Newebpay/YSNewebpaySettings.php');
assert_contains('ys_ec_newebpay_hash_key', $settings, 'hash key setting');
assert_contains('YSCrypto::encrypt_for_storage', $settings, 'secret encryption');
assert_contains("YSAdminApp::open( 'NewebPay', '金物流 / NewebPay'", $settings, 'YS CART admin shell title');
assert_contains('ys-ec-newebpay', $settings, 'primary admin slug');
assert_contains('ys_ec_newebpay_tab', $settings, 'tab-aware save field');
assert_contains("case 'api':", $settings, 'tab-specific API save');
assert_contains("case 'payment':", $settings, 'tab-specific payment save');
assert_contains("case 'shipping':", $settings, 'tab-specific shipping save');

$template = read_file('templates/admin/gateways/newebpay-settings.php');
foreach ([
    'ysca-page-root',
    'ys-ec-filters ysca-tabs ysca-tabs--with-indicator',
    'role="tablist"',
    'ysca-tab',
    'API 設定',
    '金流閘道',
    '物流閘道',
    '分期設定',
    '回呼網址',
    '交易紀錄',
    'ysca-switch-label',
    'ysca-switch-slider',
    'ysca-card--soft ysca-card--inset',
    'ysca-inline-actions ysca-inline-actions--start',
    'ysca-input',
    'API 連線設定',
    'NewebPay 金流閘道',
    'NewebPay 物流閘道',
    '7-ELEVEN C2C',
    '全家 C2C',
    '萊爾富 C2C',
    'OK mart C2C',
    '7-ELEVEN B2C',
] as $templateNeedle) {
    assert_contains($templateNeedle, $template, "template {$templateNeedle}");
}

echo "YS CART NewebPay contract checks passed.\n";
