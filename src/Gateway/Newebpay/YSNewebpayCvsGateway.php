<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayCvsGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id        = 'ys_ec_newebpay_cvs';
		$this->method_key        = 'cvs';
		$this->title             = 'NewebPay CVS Code';
		$this->description       = 'Generate a convenience-store payment code through NewebPay MPG.';
		$this->method_max_amount = 20000;
	}

	protected function get_mpg_options(): array {
		return [
			'CVS'        => '1',
			'ExpireDate' => $this->expire_date_from_setting( 'cvs_expire_days' ),
		];
	}
}
