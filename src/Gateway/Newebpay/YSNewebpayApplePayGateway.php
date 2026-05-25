<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayApplePayGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_applepay';
		$this->method_key  = 'applepay';
		$this->title       = 'NewebPay Apple Pay';
		$this->description = 'Pay with Apple Pay through NewebPay MPG.';
	}

	protected function get_mpg_options(): array {
		return [ 'APPLEPAY' => '1' ];
	}
}
