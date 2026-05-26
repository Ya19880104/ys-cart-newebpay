<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayApplePayGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_applepay';
		$this->method_key  = 'applepay';
		$this->title       = '藍新 Apple Pay';
		$this->description = '透過藍新 MPG 使用 Apple Pay 付款。';
	}

	protected function get_mpg_options(): array {
		return [ 'APPLEPAY' => '1' ];
	}
}
