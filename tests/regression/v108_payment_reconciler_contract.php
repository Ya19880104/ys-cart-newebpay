<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pass = 0;
$fail = 0;

function v108_read(string $relative): string {
    global $root;
    $path = $root . '/' . ltrim($relative, '/\\');
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$relative}\n");
        exit(1);
    }
    return (string) file_get_contents($path);
}

function v108_check(string $label, bool $ok): void {
    global $pass, $fail;
    if ($ok) {
        echo "[PASS] {$label}\n";
        $pass++;
        return;
    }
    echo "[FAIL] {$label}\n";
    $fail++;
}

$main = v108_read('ys-cart-newebpay.php');
$plugin = v108_read('src/Plugin.php');
$gateway = v108_read('src/Gateway/Newebpay/YSNewebpayGatewayBase.php');
$client = v108_read('src/Gateway/Newebpay/YSNewebpayClient.php');
$reconciler = v108_read('src/Gateway/Newebpay/YSNewebpayPaymentReconciler.php');

echo "## NewebPay payment reconciliation contract\n";

v108_check(
    'Provider registers a payment reconciler only through the YS CART hook',
    str_contains($plugin, "add_action( 'ys_ec_register_payment_reconcilers'")
        && str_contains($plugin, 'public function register_payment_reconcilers')
        && str_contains($plugin, '$registry->register( new YSNewebpayPaymentReconciler( $client ) );')
);

v108_check(
    'Reconciler registration is gated by enabled payment methods and configured client',
    str_contains($plugin, 'has_enabled_payment_methods()')
        && str_contains($plugin, '$client->is_configured()')
        && str_contains($plugin, "interface_exists( '\\YangSheep\\Ecommerce\\Services\\Payment\\YSPaymentReconcilerInterface' )")
);

v108_check(
    'Gateway records a provider marker for future reconciliation matching',
    str_contains($gateway, "\$payment_detail['payment_provider']           = 'newebpay';")
);

v108_check(
    'Client already exposes NewebPay QueryTradeInfo API',
    str_contains($client, 'public function query_trade')
        && str_contains($client, 'generate_query_check_value')
        && str_contains($client, 'API/QueryTradeInfo')
);

v108_check(
    'Reconciler maps NewebPay query states to normalized YS CART actions',
    str_contains($reconciler, 'implements YSPaymentReconcilerInterface')
        && str_contains($reconciler, 'YSPaymentReconcileResult::paid')
        && str_contains($reconciler, 'YSPaymentReconcileResult::offline_pending')
        && str_contains($reconciler, 'YSPaymentReconcileResult::failed')
        && str_contains($reconciler, 'YSPaymentReconcileResult::hold')
);

v108_check(
    'Reconciler recognizes only NewebPay-owned orders',
    str_contains($reconciler, "payment_provider'] ?? ''")
        && str_contains($reconciler, 'newebpay_merchant_order_no')
        && str_contains($reconciler, "str_starts_with( \$gateway_id, 'ys_ec_newebpay_' )")
);

preg_match('/Version:\s*([0-9.]+)/', $main, $version_match);
preg_match("/YS_CART_NEWEBPAY_VERSION', '([0-9.]+)'/", $main, $constant_match);
v108_check(
    'Plugin version is bumped for payment reconciliation',
    version_compare((string) ($version_match[1] ?? ''), '1.0.8', '>=')
        && version_compare((string) ($constant_match[1] ?? ''), '1.0.8', '>=')
);

echo "REGRESSION v108_payment_reconciler_contract PASS={$pass} FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);
