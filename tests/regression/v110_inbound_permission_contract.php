<?php
/**
 * NewebPay inbound callback routes must use route-level YS CART inbound guards.
 */

declare(strict_types=1);

$root     = dirname(__DIR__, 2);
$main     = (string) file_get_contents($root . '/ys-cart-newebpay.php');
$payment  = (string) file_get_contents($root . '/src/Api/YSNewebpayCallbackController.php');
$shipping = (string) file_get_contents($root . '/src/Api/YSNewebpayShippingNotifyController.php');
$plugin   = (string) file_get_contents($root . '/src/Plugin.php');
$manifest = (string) file_get_contents($root . '/manifest.php');

$fail = 0;
$check = static function (string $label, bool $ok) use (&$fail): void {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (! $ok) {
        $fail++;
    }
};

preg_match('/Version:\s*([0-9.]+)/', $main, $version_match);
preg_match("/YS_CART_NEWEBPAY_VERSION', '([0-9.]+)'/", $main, $constant_match);

$check('plugin version header/constant match and >= 1.0.10', '' !== ($version_match[1] ?? '') && ($version_match[1] ?? '') === ($constant_match[1] ?? '') && version_compare($version_match[1] ?? '0', '1.0.10', '>='));
$check('payment callbacks import YSInboundPermission', str_contains($payment, 'use YangSheep\\Ecommerce\\Security\\YSInboundPermission;'));
$check('shipping callback imports YSInboundPermission', str_contains($shipping, 'use YangSheep\\Ecommerce\\Security\\YSInboundPermission;'));
$check('store callback imports YSInboundPermission', str_contains($plugin, 'use YangSheep\\Ecommerce\\Security\\YSInboundPermission;'));

foreach ([
    'notify_permission',
    'return_permission',
] as $method) {
    $check("payment controller exposes {$method}", str_contains($payment, $method));
}

$check('shipping controller exposes notify_permission', str_contains($shipping, 'notify_permission'));
$check('plugin exposes store_callback_permission', str_contains($plugin, 'store_callback_permission'));
$check('runtime callbacks no longer use __return_true', ! str_contains($payment . $shipping . $plugin, "'permission_callback' => '__return_true'"));
$check('manifest declares callback permission callbacks', substr_count($manifest, "'permission_callback' =>") >= 4);

echo "v110_inbound_permission_contract FAIL={$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
