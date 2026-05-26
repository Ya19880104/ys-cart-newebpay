<?php
/**
 * NewebPay provider manifest for YS CART.
 *
 * @package YangSheep\YSCartNewebpay
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

return [
	'id'                 => 'ys_newebpay',
	'name'               => '藍新 NewebPay',
	'description'        => '藍新 MPG 金流與物流整合，支援信用卡、分期、ATM、超商代碼、條碼、LINE Pay、Apple Pay 與超商取貨。',
	'version'            => YS_CART_NEWEBPAY_VERSION,
	'contract_version'   => 1,
	'plugin_file'        => YS_CART_NEWEBPAY_BASENAME,
	'icon'               => 'dashicons-money-alt',
	'documentation_url'  => 'https://www.newebpay.com/',
	'legacy_setting_key' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings::SETTING_KEYS['enabled'],
	'domains'            => [ 'payment', 'shipping' ],
	'capabilities'       => [
		'payment'  => [
			'methods'              => [
				[ 'id' => 'ys_ec_newebpay_credit', 'label' => '信用卡', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCreditGateway::class ],
				[ 'id' => 'ys_ec_newebpay_installment', 'label' => '信用卡分期', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayInstallmentGateway::class ],
				[ 'id' => 'ys_ec_newebpay_atm', 'label' => 'ATM 虛擬帳號', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayAtmGateway::class ],
				[ 'id' => 'ys_ec_newebpay_cvs', 'label' => '超商代碼', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCvsGateway::class ],
				[ 'id' => 'ys_ec_newebpay_barcode', 'label' => '超商條碼', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayBarcodeGateway::class ],
				[ 'id' => 'ys_ec_newebpay_linepay', 'label' => 'LINE Pay', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayLinePayGateway::class ],
				[ 'id' => 'ys_ec_newebpay_applepay', 'label' => 'Apple Pay', 'class' => \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayApplePayGateway::class ],
			],
			'supported_currencies' => [ 'TWD' ],
			'supported_countries'  => [ 'TW' ],
			'test_mode_available'  => true,
		],
		'shipping' => [
			'methods'             => [
				[
					'id'             => 'ys_ec_newebpay_ship_711_c2c',
					'label'          => '7-ELEVEN C2C 超商取貨',
					'provider_label' => 'NewebPay',
					'class'          => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShipping711C2C::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector::class,
				],
				[
					'id'             => 'ys_ec_newebpay_ship_family_c2c',
					'label'          => '全家 C2C 超商取貨',
					'provider_label' => 'NewebPay',
					'class'          => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingFamilyC2C::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector::class,
				],
				[
					'id'             => 'ys_ec_newebpay_ship_hilife_c2c',
					'label'          => '萊爾富 C2C 超商取貨',
					'provider_label' => 'NewebPay',
					'class'          => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingHilifeC2C::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector::class,
				],
				[
					'id'             => 'ys_ec_newebpay_ship_ok_c2c',
					'label'          => 'OK mart C2C 超商取貨',
					'provider_label' => 'NewebPay',
					'class'          => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingOkC2C::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector::class,
				],
				[
					'id'             => 'ys_ec_newebpay_ship_711_b2c',
					'label'          => '7-ELEVEN B2C 超商取貨',
					'provider_label' => 'NewebPay',
					'class'          => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShipping711B2C::class,
					'shipping_type'  => 'cvs',
					'store_selector' => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector::class,
				],
			],
			'supported_countries' => [ 'TW' ],
			'shipping_requester'  => \YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingRequester::class,
			'carrier_adapter'     => \YangSheep\YSCartNewebpay\Services\Shipping\Adapters\YSNewebpayAdapter::class,
		],
	],
	'admin_page'         => [
		'slug'                => 'ys-provider-newebpay',
		'title'               => '藍新 NewebPay 設定',
		'render_callback'     => [ \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings::class, 'render_page' ],
		'capability_required' => 'manage_options',
		'icon'                => 'dashicons-money-alt',
	],
	'callback_routes'    => [
		'payment_notify'  => [ 'namespace' => 'ys-ecommerce/v1', 'route' => '/newebpay/notify', 'methods' => [ 'POST' ], 'signature_scheme' => 'newebpay_aes_hash' ],
		'payment_return'  => [ 'namespace' => 'ys-ecommerce/v1', 'route' => '/newebpay/return', 'methods' => [ 'GET', 'POST' ], 'signature_scheme' => 'newebpay_aes_hash' ],
		'shipping_notify' => [ 'namespace' => 'ys-ecommerce/v1', 'route' => '/newebpay/shipping-notify', 'methods' => [ 'POST' ], 'signature_scheme' => 'newebpay_aes_hash' ],
		'store_callback'  => [ 'namespace' => 'ys-ecommerce/v1', 'route' => '/newebpay/store-callback', 'methods' => [ 'POST' ], 'signature_scheme' => 'newebpay_aes_hash' ],
	],
	'allowed_hosts'      => [
		'ccore.newebpay.com',
		'core.newebpay.com',
		'logistics-stage.newebpay.com',
		'logistics.newebpay.com',
	],
	'health_check'       => [
		'callback'  => null,
		'cache_ttl' => 3600,
	],
];
