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

$main     = read_file('ys-cart-newebpay.php');
$plugin   = read_file('src/Plugin.php');
$manifest = read_file('manifest.php');
$settings = read_file('src/Gateway/Newebpay/YSNewebpaySettings.php');
$template = read_file('templates/admin/gateways/newebpay-settings.php');

assert_contains('Plugin Name: YS CART - NewebPay', $main, 'plugin header');
assert_contains("'slug'        => 'ys-cart-newebpay'", $main, 'Hub slug');
assert_contains('YS_CART_NEWEBPAY_VERSION', $main, 'version constant');

foreach ([
    'ys_ec_provider_manifests',
    'ys_ec_register_gateways',
    'ys_ec_register_shipping_methods',
    'ys_ec_register_admin_rest_routes',
    'ys_ec_register_storefront_routes',
    'ys_ec_shipping_requester',
    'ys_ec_shipping_carrier_adapter',
    'ys_ec_shipping_provider_labels',
] as $hook) {
    assert_contains($hook, $plugin, "hook {$hook}");
}

if (false !== strpos($plugin, 'ys_ec_providers') || false !== strpos($plugin, 'ys_ec_admin_payment_menus')) {
    throw new RuntimeException('NewebPay must use manifest-first provider registration, not legacy provider/menu hooks.');
}

assert_contains("'id'                 => 'ys_newebpay'", $manifest, 'manifest provider id');
assert_contains("'name'               => '藍新 NewebPay'", $manifest, 'manifest name');
assert_contains("'slug'                => 'ys-provider-newebpay'", $manifest, 'lifecycle settings slug');
assert_contains("'label' => '信用卡'", $manifest, 'payment label');
assert_contains("'label'          => '7-ELEVEN C2C 超商取貨'", $manifest, 'shipping label');

foreach ([
    'ys_ec_newebpay_credit',
    'ys_ec_newebpay_installment',
    'ys_ec_newebpay_atm',
    'ys_ec_newebpay_cvs',
    'ys_ec_newebpay_barcode',
    'ys_ec_newebpay_linepay',
    'ys_ec_newebpay_applepay',
] as $gatewayId) {
    assert_contains($gatewayId, $manifest, "manifest gateway {$gatewayId}");
}

foreach ([
    'ys_ec_newebpay_ship_711_c2c',
    'ys_ec_newebpay_ship_family_c2c',
    'ys_ec_newebpay_ship_hilife_c2c',
    'ys_ec_newebpay_ship_ok_c2c',
    'ys_ec_newebpay_ship_711_b2c',
] as $shippingId) {
    assert_contains($shippingId, $manifest, "manifest shipping {$shippingId}");
}

assert_contains('sync_lifecycle_methods', $settings, 'settings sync lifecycle methods');
assert_contains("YSAdminApp::open( '藍新 NewebPay 設定'", $settings, 'YS CART admin shell title');
assert_contains('ys-provider-newebpay', $settings, 'primary admin slug');
assert_contains('ys_ec_newebpay_tab', $settings, 'tab-aware save field');
assert_contains("case 'api':", $settings, 'tab-specific API save');
assert_contains("case 'payment':", $settings, 'tab-specific payment save');
assert_contains("case 'shipping':", $settings, 'tab-specific shipping save');

foreach ([
    'ysca-page-root',
    'ys-ec-filters ysca-tabs ysca-tabs--with-indicator',
    'role="tablist"',
    '金流方式',
    '物流方式',
    '回呼網址',
    '啟用 NewebPay',
    'NewebPay 金流方式',
    'NewebPay 物流方式',
    '儲存設定',
] as $templateNeedle) {
    assert_contains($templateNeedle, $template, "template {$templateNeedle}");
}

echo "YS CART NewebPay contract checks passed.\n";
