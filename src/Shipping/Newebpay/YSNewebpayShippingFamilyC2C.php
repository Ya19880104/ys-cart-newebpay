<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShippingFamilyC2C extends YSNewebpayShipping {
	protected string $id = 'ys_ec_newebpay_ship_family_c2c';
	protected string $title = 'NewebPay 全家 C2C 超商取貨';

	public function get_lgs_type(): string { return 'C2C'; }
	public function get_ship_type(): string { return '2'; }
}
