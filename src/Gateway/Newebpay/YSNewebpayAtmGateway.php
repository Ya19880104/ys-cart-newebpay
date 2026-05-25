<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayAtmGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id        = 'ys_ec_newebpay_atm';
		$this->method_key        = 'atm';
		$this->title             = 'NewebPay ATM';
		$this->description       = 'Generate a virtual ATM account through NewebPay MPG.';
		$this->method_max_amount = 50000;
	}

	protected function get_mpg_options(): array {
		return [
			'VACC'       => '1',
			'ExpireDate' => $this->expire_date_from_setting( 'atm_expire_days' ),
		];
	}
}
