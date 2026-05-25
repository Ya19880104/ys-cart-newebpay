<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShipping711C2C extends YSNewebpayShipping {
	protected string $id = 'ys_ec_newebpay_ship_711_c2c';
	protected string $title = 'NewebPay 7-ELEVEN C2C 店到店';

	public function get_lgs_type(): string {
		return 'C2C';
	}

	public function get_ship_type(): string {
		return '1';
	}

	protected function get_default_max_weight(): float {
		return 10.0;
	}
}
