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
    'ys_ec_providers',
    'ys_ec_admin_payment_menus',
    'ys_ec_register_admin_rest_routes',
    'ys_ec_register_storefront_routes',
] as $hook) {
    assert_contains($hook, $plugin, "hook {$hook}");
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

echo "YS CART NewebPay contract checks passed.\n";
