<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShipping711B2C extends YSNewebpayShipping {
	protected string $id = 'ys_ec_newebpay_ship_711_b2c';
	protected string $title = 'NewebPay 7-ELEVEN B2C 大宗寄倉';

	public function get_lgs_type(): string {
		return 'B2C';
	}

	public function get_ship_type(): string {
		return '1';
	}

	protected function get_default_max_weight(): float {
		return 10.0;
	}
}
