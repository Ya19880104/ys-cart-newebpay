<?php
/**
 * NewebPay headless docs and SDK must match the store-map REST contract.
 */

declare(strict_types=1);

$root   = dirname(__DIR__, 2);
$plugin = (string) file_get_contents($root . '/src/Plugin.php');
$sdk    = (string) file_get_contents($root . '/sdk/ys-cart-newebpay-headless.js');
$docs   = (string) file_get_contents($root . '/docs/headless.md');
$skill  = (string) file_get_contents($root . '/skills/ys-cart-newebpay-headless.md');
$readme = (string) file_get_contents($root . '/README.md');

$fail = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$check(
    'REST route exposes NewebPay store map endpoint',
    str_contains($plugin, "'/stores/newebpay/map-url'")
        && str_contains($plugin, 'newebpay_map_url')
);

$check(
    'REST handler reads canonical shipping_id while retaining alias compatibility',
    str_contains($plugin, "\$params['shipping_id']")
        && str_contains($plugin, "\$params['shipping_method']")
);

$check(
    'SDK publishes store-map route and helper',
    str_contains($sdk, 'storeMapUrl')
        && str_contains($sdk, 'requestNewebPayStoreMapForm')
        && str_contains($sdk, 'shipping_id: shippingId')
);

$check(
    'Headless docs publish shipping_id payload',
    str_contains($docs, '"shipping_id": "ys_ec_newebpay_ship_711_c2c"')
        && str_contains($docs, '/wp-json/ys-ecommerce-headless/v1/stores/newebpay/map-url')
);

$check(
    'Skill documents shipping_id and callback route boundary',
    str_contains($skill, 'selected `shipping_id`')
        && str_contains($skill, 'provider/server callback routes')
);

$check(
    'README documents route and provider-facing callback boundary',
    str_contains($readme, '"shipping_id": "ys_ec_newebpay_ship_711_c2c"')
        && str_contains($readme, 'provider-facing callback routes')
);

echo "v111_headless_store_map_contract FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
