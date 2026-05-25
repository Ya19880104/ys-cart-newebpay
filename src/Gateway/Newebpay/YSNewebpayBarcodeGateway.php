<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayBarcodeGateway extends YSNewebpayGatewayBase {
	public function __construct() {
		$this->gateway_id        = 'ys_ec_newebpay_barcode';
		$this->method_key        = 'barcode';
		$this->title             = 'NewebPay Barcode';
		$this->description       = 'Generate a barcode payment slip through NewebPay MPG.';
		$this->method_max_amount = 40000;
	}

	protected function get_mpg_options(): array {
		return [
			'BARCODE'    => '1',
			'ExpireDate' => $this->expire_date_from_setting( 'bar_expire_days' ),
		];
	}
}
