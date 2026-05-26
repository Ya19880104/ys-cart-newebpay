<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayCvsGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id        = 'ys_ec_newebpay_cvs';
		$this->method_key        = 'cvs';
		$this->title             = '藍新超商代碼';
		$this->description       = '透過藍新 MPG 產生超商代碼。';
		$this->method_max_amount = 20000;
	}

	protected function get_mpg_options(): array {
		return [
			'CVS'        => '1',
			'ExpireDate' => $this->expire_date_from_setting( 'cvs_expire_days' ),
		];
	}
}
