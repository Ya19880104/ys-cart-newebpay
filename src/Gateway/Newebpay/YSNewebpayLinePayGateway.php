<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayLinePayGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_linepay';
		$this->method_key  = 'linepay';
		$this->title       = 'NewebPay LINE Pay';
		$this->description = 'Pay with LINE Pay through NewebPay MPG.';
	}

	protected function get_mpg_options(): array {
		return [ 'LINEPAY' => '1' ];
	}
}
