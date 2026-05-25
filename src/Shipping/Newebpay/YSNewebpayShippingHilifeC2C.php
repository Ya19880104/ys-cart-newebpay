<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShippingHilifeC2C extends YSNewebpayShipping {
	protected string $id = 'ys_ec_newebpay_ship_hilife_c2c';
	protected string $title = 'NewebPay 萊爾富 C2C 店到店';

	public function get_lgs_type(): string {
		return 'C2C';
	}

	public function get_ship_type(): string {
		return '3';
	}
}
