<?php

declare(strict_types=1);

$root   = dirname(__DIR__, 2);
$plugin = (string) file_get_contents($root . '/src/Plugin.php');
$pass   = 0;
$fail   = 0;

function v103_check(string $label, bool $ok): void {
    global $pass, $fail;
    if ($ok) {
        echo "[PASS] {$label}\n";
        $pass++;
        return;
    }

    echo "[FAIL] {$label}\n";
    $fail++;
}

v103_check(
    'NewebPay declares canonical payment and shipping method id lists for route gates',
    str_contains($plugin, 'private const REGISTERED_GATEWAY_IDS')
        && str_contains($plugin, "'ys_ec_newebpay_credit'")
        && str_contains($plugin, "'ys_ec_newebpay_installment'")
        && str_contains($plugin, 'private const REGISTERED_SHIPPING_IDS')
        && str_contains($plugin, "'ys_ec_newebpay_ship_711_c2c'")
);

v103_check(
    'Payment callback route requires at least one enabled NewebPay payment method',
    str_contains($plugin, 'private function has_enabled_payment_methods(): bool')
        && str_contains($plugin, 'self::REGISTERED_GATEWAY_IDS as $method_id')
        && str_contains($plugin, "is_method_enabled( 'payment', \$method_id )")
        && str_contains($plugin, 'if ( $this->has_enabled_payment_methods() )')
);

v103_check(
    'Store selector and shipping notify surfaces require at least one enabled NewebPay shipping method',
    str_contains($plugin, 'private function has_enabled_shipping_methods(): bool')
        && str_contains($plugin, 'self::REGISTERED_SHIPPING_IDS as $method_id')
        && str_contains($plugin, "is_method_enabled( 'shipping', \$method_id )")
        && str_contains($plugin, 'if ( ! $this->has_enabled_shipping_methods() )')
);

v103_check(
    'Frontend store-selector bridge and carrier integrations are also method gated',
    str_contains($plugin, 'enqueue_store_selector_bridge')
        && str_contains($plugin, 'register_shipping_requester')
        && str_contains($plugin, 'register_shipping_provider_label')
        && substr_count($plugin, 'has_enabled_shipping_methods()') >= 5
);

echo "REGRESSION v103_route_surface_lifecycle_contract PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
