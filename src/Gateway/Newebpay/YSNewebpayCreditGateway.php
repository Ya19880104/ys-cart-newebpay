<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayCreditGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_credit';
		$this->method_key  = 'credit';
		$this->title       = '藍新信用卡';
		$this->description = '透過藍新 MPG 使用信用卡付款。';
	}

	protected function get_mpg_options(): array {
		return [ 'CREDIT' => '1' ];
	}
}
