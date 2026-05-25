<?php

declare( strict_types = 1 );

namespace YangSheep\Ecommerce\Enums {
	class YSShippingPipelineState {
		public const ORDER_PLACED     = 'order_placed';
		public const PREPARING        = 'preparing';
		public const IN_TRANSIT       = 'in_transit';
		public const ARRIVED_AT_STORE = 'arrived_at_store';
		public const DELIVERED        = 'delivered';
		public const RETURNED         = 'returned';
		public const FAILED           = 'failed';
	}
}

namespace YangSheep\Ecommerce\Services\Shipping {
	abstract class YSCarrierAdapter {
		abstract public function get_id(): string;
		abstract public function map_to_pipeline_state( string $carrier_status ): ?string;
		abstract public function supports_webhook(): bool;
		abstract public function supports_query_api(): bool;
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	require dirname( __DIR__, 2 ) . '/src/Services/Shipping/Adapters/YSNewebpayAdapter.php';

	use YangSheep\Ecommerce\Enums\YSShippingPipelineState;
	use YangSheep\YSCartNewebpay\Services\Shipping\Adapters\YSNewebpayAdapter;

	function v101_assert_same( string $expected, ?string $actual, string $message ): void {
		if ( $expected !== $actual ) {
			fwrite( STDERR, "[FAIL] {$message}: expected {$expected}, got " . ( $actual ?? 'null' ) . "\n" );
			exit( 1 );
		}

		echo "[PASS] {$message}\n";
	}

	$adapter = new YSNewebpayAdapter();

	$expected = [
		'0_1' => YSShippingPipelineState::ORDER_PLACED,
		'0_2' => YSShippingPipelineState::FAILED,
		'0_3' => YSShippingPipelineState::FAILED,
		'1'   => YSShippingPipelineState::PREPARING,
		'2'   => YSShippingPipelineState::IN_TRANSIT,
		'3'   => YSShippingPipelineState::IN_TRANSIT,
		'4'   => YSShippingPipelineState::IN_TRANSIT,
		'5'   => YSShippingPipelineState::ARRIVED_AT_STORE,
		'6'   => YSShippingPipelineState::DELIVERED,
		'11'  => YSShippingPipelineState::FAILED,
		'-1'  => YSShippingPipelineState::RETURNED,
		'-2'  => YSShippingPipelineState::RETURNED,
		'-3'  => YSShippingPipelineState::RETURNED,
		'-4'  => YSShippingPipelineState::RETURNED,
		'-5'  => YSShippingPipelineState::RETURNED,
		'-6'  => YSShippingPipelineState::RETURNED,
		'-7'  => YSShippingPipelineState::RETURNED,
		'-9'  => YSShippingPipelineState::RETURNED,
		'-10' => YSShippingPipelineState::RETURNED,
		'-11' => YSShippingPipelineState::RETURNED,
		'10'  => YSShippingPipelineState::RETURNED,
		'12'  => YSShippingPipelineState::RETURNED,
		'13'  => YSShippingPipelineState::RETURNED,
		'14'  => YSShippingPipelineState::RETURNED,
		'15'  => YSShippingPipelineState::RETURNED,
		'16'  => YSShippingPipelineState::RETURNED,
	];

	foreach ( $expected as $carrier_status => $pipeline_state ) {
		v101_assert_same(
			$pipeline_state,
			$adapter->map_to_pipeline_state( (string) $carrier_status ),
			"NewebPay RetId {$carrier_status} maps to {$pipeline_state}"
		);
	}

	v101_assert_same( 'newebpay', $adapter->get_id(), 'adapter id' );

	echo "REGRESSION v101_newebpay_logistics_pipeline PASS\n";
}
