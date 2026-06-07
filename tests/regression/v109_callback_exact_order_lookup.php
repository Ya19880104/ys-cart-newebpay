<?php

declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$pass = 0;
$fail = 0;

function v109_read( string $relative ): string {
	global $root;
	$path = $root . '/' . ltrim( $relative, '/\\' );
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Missing required file: {$relative}\n" );
		exit( 1 );
	}

	return (string) file_get_contents( $path );
}

function v109_check( string $label, bool $ok ): void {
	global $pass, $fail;
	if ( $ok ) {
		echo "[PASS] {$label}\n";
		++$pass;
		return;
	}

	echo "[FAIL] {$label}\n";
	++$fail;
}

$controller = v109_read( 'src/Api/YSNewebpayCallbackController.php' );

echo "## NewebPay callback exact order lookup contract\n";

v109_check(
	'MerchantOrderNo decoded from signed payload still drives notify and return lookup',
	substr_count( $controller, "extract_result_value( \$decoded, 'MerchantOrderNo' )" ) >= 2
		&& substr_count( $controller, 'find_order_by_merchant_order_no( $merchant_order_no )' ) >= 2
);

v109_check(
	'Pattern-derived order id must also match stored NewebPay merchant order number',
	str_contains( $controller, 'if ( $order && self::order_has_merchant_order_no( $order, $merchant_order_no ) )' )
		&& str_contains( $controller, 'private static function order_has_merchant_order_no' )
		&& str_contains( $controller, "hash_equals( (string) ( \$detail['newebpay_merchant_order_no'] ?? '' ), \$merchant_order_no )" )
		&& str_contains( $controller, "hash_equals( (string) ( \$detail['mer_trade_no'] ?? '' ), \$merchant_order_no )" )
);

v109_check(
	'Fallback lookup is exact JSON extraction, not order-number or payment_detail LIKE matching',
	str_contains( $controller, "JSON_UNQUOTE(JSON_EXTRACT(payment_detail, '$.newebpay_merchant_order_no')) = %s" )
		&& str_contains( $controller, "JSON_UNQUOTE(JSON_EXTRACT(payment_detail, '$.mer_trade_no')) = %s" )
		&& ! str_contains( $controller, 'find_by_number( $merchant_order_no )' )
		&& ! str_contains( $controller, 'payment_detail LIKE' )
		&& ! str_contains( $controller, '$wpdb->esc_like( $merchant_order_no )' )
);

echo "REGRESSION v109_callback_exact_order_lookup PASS={$pass} FAIL={$fail}\n";
exit( $fail > 0 ? 1 : 0 );
