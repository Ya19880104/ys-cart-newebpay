<?php

declare(strict_types=1);

$root   = dirname(__DIR__, 2);
$plugin = (string) file_get_contents($root . '/src/Plugin.php');
$settings = (string) file_get_contents($root . '/src/Gateway/Newebpay/YSNewebpaySettings.php');
$store = (string) file_get_contents($root . '/src/Shipping/Newebpay/YSNewebpayStoreSelector.php');
$main = (string) file_get_contents($root . '/ys-cart-newebpay.php');
$manifest = (string) file_get_contents($root . '/manifest.php');

function v102_assert_contains(string $needle, string $haystack, string $label): void {
    if (false === strpos($haystack, $needle)) {
        fwrite(STDERR, "[FAIL] Missing {$label}: {$needle}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

function v102_assert_true(bool $condition, string $label): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

function v102_method_body(string $source, string $method): string {
    $offset = strpos($source, "function {$method}");
    if (false === $offset) {
        fwrite(STDERR, "[FAIL] Missing method {$method}\n");
        exit(1);
    }

    $brace = strpos($source, '{', $offset);
    if (false === $brace) {
        fwrite(STDERR, "[FAIL] Missing method body {$method}\n");
        exit(1);
    }

    $depth = 0;
    $len   = strlen($source);
    for ($i = $brace; $i < $len; $i++) {
        if ('{' === $source[$i]) {
            $depth++;
        } elseif ('}' === $source[$i]) {
            $depth--;
            if (0 === $depth) {
                return substr($source, $brace, $i - $brace + 1);
            }
        }
    }

    fwrite(STDERR, "[FAIL] Unterminated method body {$method}\n");
    exit(1);
}

$gateway_body  = v102_method_body($plugin, 'register_gateways');
$shipping_body = v102_method_body($plugin, 'register_shipping_methods');
$map_body      = v102_method_body($plugin, 'newebpay_map_url');

v102_assert_contains("add_filter( 'ys_ec_provider_manifests'", $plugin, 'provider registers manifest');
v102_assert_contains("'id'                 => 'ys_newebpay'", $manifest, 'manifest declares provider id');
v102_assert_contains("'slug'                => 'ys-provider-newebpay'", $manifest, 'manifest declares lifecycle settings page');
if (false !== strpos($plugin, 'ys_ec_providers') || false !== strpos($plugin, 'ys_ec_admin_payment_menus')) {
    fwrite(STDERR, "[FAIL] provider must not use legacy provider/menu hooks\n");
    exit(1);
}
echo "[PASS] provider no longer uses legacy provider/menu hooks\n";
v102_assert_contains('is_payment_enabled()', $gateway_body, 'gateway registration checks payment capability');
v102_assert_contains('is_shipping_enabled()', $shipping_body, 'shipping registration checks shipping capability');
v102_assert_contains("is_capability_enabled( 'ys_newebpay', 'payment'", $plugin, 'payment capability uses lifecycle state');
v102_assert_contains("is_capability_enabled( 'ys_newebpay', 'shipping'", $plugin, 'shipping capability uses lifecycle state');
v102_assert_contains('function is_logistics_method_enabled', $settings, 'settings expose per-logistics registration gate');
v102_assert_contains("is_method_enabled( 'shipping', \$method_id", $shipping_body, 'shipping registration checks each lifecycle method switch');
v102_assert_contains("is_method_enabled( 'payment', \$method_id", $gateway_body, 'gateway registration checks each lifecycle method switch');
v102_assert_contains('is_shipping_enabled()', $map_body, 'store map route checks shipping capability');
v102_assert_contains('provider_disabled', $map_body, 'store map route returns provider-disabled error');
v102_assert_contains("is_method_enabled( 'shipping', \$shipping_id", $map_body, 'store map route checks selected lifecycle method state');

$shipping_methods = [
    'ys_ec_newebpay_ship_711_c2c' => 'YSNewebpayShipping711C2C',
    'ys_ec_newebpay_ship_family_c2c' => 'YSNewebpayShippingFamilyC2C',
    'ys_ec_newebpay_ship_hilife_c2c' => 'YSNewebpayShippingHilifeC2C',
    'ys_ec_newebpay_ship_ok_c2c' => 'YSNewebpayShippingOkC2C',
    'ys_ec_newebpay_ship_711_b2c' => 'YSNewebpayShipping711B2C',
];

foreach ($shipping_methods as $method_id => $class_name) {
    if (false === strpos($shipping_body, "'{$method_id}'") || false === strpos($shipping_body, "{$class_name}::class")) {
        fwrite(STDERR, "[FAIL] {$class_name} must be mapped to {$method_id}\n");
        exit(1);
    }

    echo "[PASS] {$class_name} mapped to {$method_id}\n";
}

v102_assert_contains('YSShippingRegistry::register( new $method_class() )', $shipping_body, 'shipping registry registers only the gated mapped class');

v102_assert_contains('use YangSheep\\YSCartNewebpay\\Gateway\\Newebpay\\YSNewebpaySettings;', $store, 'store selector imports settings');
v102_assert_contains('YSNewebpaySettings::is_logistics_method_enabled( $shipping_id )', $store, 'store selector checks selected logistics method switch');
preg_match('/Version:\s*([0-9.]+)/', $main, $version_match);
preg_match("/YS_CART_NEWEBPAY_VERSION', '([0-9.]+)'/", $main, $constant_match);
v102_assert_true(version_compare((string) ($version_match[1] ?? ''), '1.0.6', '>=') && version_compare((string) ($constant_match[1] ?? ''), '1.0.6', '>='), 'plugin version is at least 1.0.6');

echo "REGRESSION v102_newebpay_registration_scope PASS\n";
