<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayLinePayGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_linepay';
		$this->method_key  = 'linepay';
		$this->title       = '藍新 LINE Pay';
		$this->description = '透過藍新 MPG 使用 LINE Pay 付款。';
	}

	protected function get_mpg_options(): array {
		return [ 'LINEPAY' => '1' ];
	}
}
