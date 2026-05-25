<?php

namespace YangSheep\YSCartNewebpay\Services\Shipping\Adapters;

use YangSheep\Ecommerce\Enums\YSShippingPipelineState;
use YangSheep\Ecommerce\Services\Shipping\YSCarrierAdapter;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayAdapter extends YSCarrierAdapter {
	private const CARRIER_TO_PIPELINE = [
		'0_1' => YSShippingPipelineState::ORDER_PLACED,
		'0_2' => YSShippingPipelineState::FAILED,
		'0_3' => YSShippingPipelineState::FAILED,
		'1'   => YSShippingPipelineState::PREPARING,
		'2'   => YSShippingPipelineState::PREPARING,
		'3'   => YSShippingPipelineState::PREPARING,
		'4'   => YSShippingPipelineState::IN_TRANSIT,
		'5'   => YSShippingPipelineState::ARRIVED_AT_STORE,
		'6'   => YSShippingPipelineState::DELIVERED,
		'-6'  => YSShippingPipelineState::RETURNED,
		'7'   => YSShippingPipelineState::RETURNED,
		'8'   => YSShippingPipelineState::RETURNED,
		'9'   => YSShippingPipelineState::RETURNED,
		'1101' => YSShippingPipelineState::FAILED,
		'1103' => YSShippingPipelineState::FAILED,
		'1104' => YSShippingPipelineState::FAILED,
		'1105' => YSShippingPipelineState::FAILED,
		'label_created' => YSShippingPipelineState::PREPARING,
		'in_transit'    => YSShippingPipelineState::IN_TRANSIT,
		'arrived'       => YSShippingPipelineState::ARRIVED_AT_STORE,
		'picked_up'     => YSShippingPipelineState::DELIVERED,
		'returned'      => YSShippingPipelineState::RETURNED,
	];

	public function get_id(): string {
		return 'newebpay';
	}

	public function map_to_pipeline_state( string $carrier_status ): ?string {
		return self::CARRIER_TO_PIPELINE[ $carrier_status ] ?? null;
	}

	public function supports_webhook(): bool {
		return true;
	}

	public function supports_query_api(): bool {
		return true;
	}
}
