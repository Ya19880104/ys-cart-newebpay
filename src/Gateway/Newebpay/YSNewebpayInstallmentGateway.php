<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayInstallmentGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id  = 'ys_ec_newebpay_installment';
		$this->method_key  = 'inst';
		$this->title       = 'NewebPay Installment';
		$this->description = 'Pay by credit-card installment through NewebPay MPG.';
	}

	protected function get_mpg_options(): array {
		return [
			'CREDIT'   => '1',
			'InstFlag' => YSNewebpaySettings::get( 'inst_flag', '3,6,12' ),
		];
	}
}
