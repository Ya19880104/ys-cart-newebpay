<?php

namespace YangSheep\YSCartNewebpay;

use YangSheep\Ecommerce\Gateways\YSGatewayRegistry;
use YangSheep\YSCartNewebpay\Api\Admin\YSNewebpayTestConnectionController;
use YangSheep\YSCartNewebpay\Api\YSNewebpayCallbackController;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayApplePayGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayAtmGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayBarcodeGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCreditGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCvsGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayInstallmentGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayLinePayGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		YSNewebpaySettings::register();

		add_action( 'ys_ec_register_gateways', [ $this, 'register_gateways' ] );
		add_filter( 'ys_ec_providers', [ $this, 'register_provider' ] );
		add_action( 'ys_ec_admin_payment_menus', [ $this, 'register_admin_menu' ], 10, 2 );
		add_action( 'ys_ec_register_admin_rest_routes', [ $this, 'register_admin_routes' ] );
		add_action( 'ys_ec_register_storefront_routes', [ $this, 'register_storefront_routes' ] );
		add_filter( 'ys_ec_external_admin_pages', [ $this, 'register_external_admin_page' ] );
	}

	public function register_gateways(): void {
		if ( ! class_exists( YSGatewayRegistry::class ) ) {
			return;
		}

		YSGatewayRegistry::register( new YSNewebpayCreditGateway() );
		YSGatewayRegistry::register( new YSNewebpayInstallmentGateway() );
		YSGatewayRegistry::register( new YSNewebpayAtmGateway() );
		YSGatewayRegistry::register( new YSNewebpayCvsGateway() );
		YSGatewayRegistry::register( new YSNewebpayBarcodeGateway() );
		YSGatewayRegistry::register( new YSNewebpayLinePayGateway() );
		YSGatewayRegistry::register( new YSNewebpayApplePayGateway() );
	}

	/**
	 * @param array<string,array<string,mixed>> $providers
	 * @return array<string,array<string,mixed>>
	 */
	public function register_provider( array $providers ): array {
		$providers['newebpay'] = [
			'name'        => 'NewebPay',
			'icon'        => 'dashicons-money-alt',
			'description' => 'NewebPay MPG credit card, ATM, CVS code, barcode, LINE Pay, and Apple Pay.',
			'payment'     => [ 'Credit card', 'Installment', 'ATM', 'CVS code', 'Barcode', 'LINE Pay', 'Apple Pay' ],
			'shipping'    => [ 'CVSCOM store data passthrough' ],
			'setting_key' => YSNewebpaySettings::SETTING_KEYS['enabled'],
			'admin_url'   => admin_url( 'admin.php?page=ys-ecommerce-newebpay' ),
		];

		return $providers;
	}

	public function register_admin_menu( string $parent_slug, string $capability ): void {
		add_submenu_page(
			$parent_slug,
			'NewebPay Settings',
			'NewebPay',
			$capability,
			'ys-ecommerce-newebpay',
			[ YSNewebpaySettings::class, 'render_page' ]
		);
	}

	public function register_admin_routes( $registrar = null ): void {
		unset( $registrar );

		YSNewebpayTestConnectionController::register_routes();
	}

	public function register_storefront_routes( string $namespace = '' ): void {
		unset( $namespace );

		YSNewebpayCallbackController::register_routes();
	}

	/**
	 * @param array<int,string> $pages
	 * @return array<int,string>
	 */
	public function register_external_admin_page( array $pages ): array {
		$pages[] = 'ys-ecommerce-newebpay';

		return array_values( array_unique( $pages ) );
	}
}
