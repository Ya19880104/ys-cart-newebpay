<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayCreditGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_credit';
		$this->method_key  = 'credit';
		$this->title       = 'NewebPay Credit Card';
		$this->description = 'Pay by credit card through NewebPay MPG.';
	}

	protected function get_mpg_options(): array {
		return [ 'CREDIT' => '1' ];
	}
}
