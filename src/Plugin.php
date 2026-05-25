<?php

namespace YangSheep\YSCartNewebpay;

use YangSheep\Ecommerce\Gateways\YSGatewayRegistry;
use YangSheep\Ecommerce\Api\Storefront\YSRequestParser;
use YangSheep\Ecommerce\Api\Storefront\YSRestAuth;
use YangSheep\Ecommerce\Api\Storefront\YSRestResponder;
use YangSheep\Ecommerce\Shipping\YSShippingRegistry;
use YangSheep\YSCartNewebpay\Api\Admin\YSNewebpayTestConnectionController;
use YangSheep\YSCartNewebpay\Api\YSNewebpayCallbackController;
use YangSheep\YSCartNewebpay\Api\YSNewebpayShippingNotifyController;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayApplePayGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayAtmGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayBarcodeGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCreditGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayCvsGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayInstallmentGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayLinePayGateway;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;
use YangSheep\YSCartNewebpay\Services\Shipping\Adapters\YSNewebpayAdapter;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShipping;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShipping711B2C;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShipping711C2C;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingFamilyC2C;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingHilifeC2C;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingOkC2C;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayShippingRequester;
use YangSheep\YSCartNewebpay\Shipping\Newebpay\YSNewebpayStoreSelector;

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
		add_action( 'ys_ec_register_shipping_methods', [ $this, 'register_shipping_methods' ] );
		add_filter( 'ys_ec_providers', [ $this, 'register_provider' ] );
		add_action( 'ys_ec_admin_payment_menus', [ $this, 'register_admin_menu' ], 10, 2 );
		add_action( 'ys_ec_register_admin_rest_routes', [ $this, 'register_admin_routes' ] );
		add_action( 'ys_ec_register_storefront_routes', [ $this, 'register_storefront_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_public_routes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_store_selector_bridge' ] );
		add_filter( 'ys_ec_shipping_requester', [ $this, 'register_shipping_requester' ], 10, 2 );
		add_filter( 'ys_ec_shipping_carrier_adapter', [ $this, 'register_carrier_adapter' ], 10, 2 );
		add_filter( 'ys_ec_shipping_provider_labels', [ $this, 'register_shipping_provider_label' ] );
		add_filter( 'ys_ec_external_admin_pages', [ $this, 'register_external_admin_page' ] );
		add_filter( 'ys_ec_admin_nav_groups', [ $this, 'register_admin_nav_group' ] );
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

	public function register_shipping_methods(): void {
		if ( ! class_exists( YSShippingRegistry::class ) ) {
			return;
		}

		YSShippingRegistry::register( new YSNewebpayShipping711C2C() );
		YSShippingRegistry::register( new YSNewebpayShippingFamilyC2C() );
		YSShippingRegistry::register( new YSNewebpayShippingHilifeC2C() );
		YSShippingRegistry::register( new YSNewebpayShippingOkC2C() );
		YSShippingRegistry::register( new YSNewebpayShipping711B2C() );
	}

	/**
	 * @param array<string,array<string,mixed>> $providers
	 * @return array<string,array<string,mixed>>
	 */
	public function register_provider( array $providers ): array {
		$providers['newebpay'] = [
			'name'        => 'NewebPay',
			'icon'        => 'dashicons-money-alt',
			'description' => '藍新 MPG 金流與官方物流 API，支援信用卡、ATM、超商代碼、條碼、LINE Pay、Apple Pay 與超商取貨。',
			'payment'     => [ '信用卡', '分期付款', 'ATM 虛擬帳號', '超商代碼', '條碼繳費', 'LINE Pay', 'Apple Pay' ],
			'shipping'    => [ '7-ELEVEN C2C 店到店', '全家 C2C 店到店', '萊爾富 C2C 店到店', 'OK mart C2C 店到店', '7-ELEVEN B2C 大宗寄倉' ],
			'setting_key' => YSNewebpaySettings::SETTING_KEYS['enabled'],
			'admin_url'   => 'admin.php?page=ys-ec-newebpay',
		];

		return $providers;
	}

	public function register_admin_menu( string $parent_slug, string $capability ): void {
		add_submenu_page(
			$parent_slug,
			'NewebPay 設定',
			'NewebPay',
			$capability,
			'ys-ec-newebpay',
			[ YSNewebpaySettings::class, 'render_page' ]
		);

		add_submenu_page(
			'ys-ec-newebpay-hidden',
			'NewebPay 設定',
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
		YSNewebpayCallbackController::register_routes();

		register_rest_route(
			$namespace,
			'/stores/newebpay/map-url',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'newebpay_map_url' ],
				'permission_callback' => [ YSRestAuth::class, 'permission_customer_or_guest_write' ],
			]
		);
	}

	public function register_public_routes(): void {
		register_rest_route(
			'ys-ecommerce/v1',
			'/newebpay/store-callback',
			[
				'methods'             => 'POST',
				'callback'            => [ YSNewebpayStoreSelector::class, 'handle_store_callback' ],
				'permission_callback' => '__return_true',
			]
		);

		YSNewebpayShippingNotifyController::register_routes();
	}

	public function newebpay_map_url( \WP_REST_Request $request ): \WP_REST_Response {
		$params      = class_exists( YSRequestParser::class ) ? YSRequestParser::params( $request ) : $request->get_params();
		$shipping_id = sanitize_text_field( (string) ( $params['shipping_id'] ?? $params['shipping_method'] ?? '' ) );

		if ( '' === $shipping_id ) {
			return YSRestResponder::error( 'missing_shipping_id', 'Missing shipping method ID.' );
		}

		$result = YSNewebpayStoreSelector::build_map_form_data( $shipping_id );
		if ( $result ) {
			return YSRestResponder::success( 'map_url_ready', '', $result );
		}

		return YSRestResponder::error( 'map_url_failed', 'NewebPay logistics API settings are incomplete.' );
	}

	public function enqueue_store_selector_bridge(): void {
		wp_enqueue_script(
			'ys-ec-newebpay-store-selector',
			YS_CART_NEWEBPAY_URL . 'assets/js/newebpay-store-selector.js',
			[],
			YS_CART_NEWEBPAY_VERSION,
			true
		);
	}

	public function register_shipping_requester( $requester, $method ) {
		if ( null !== $requester ) {
			return $requester;
		}

		if ( $method instanceof YSNewebpayShipping ) {
			return new YSNewebpayShippingRequester( $method );
		}

		return $requester;
	}

	public function register_carrier_adapter( $adapter, string $provider_key ) {
		if ( null !== $adapter ) {
			return $adapter;
		}

		if ( 'newebpay' === $provider_key ) {
			return new YSNewebpayAdapter();
		}

		return $adapter;
	}

	/**
	 * @param array<string,string> $labels
	 * @return array<string,string>
	 */
	public function register_shipping_provider_label( array $labels ): array {
		$labels['newebpay'] = 'NewebPay';

		return $labels;
	}

	/**
	 * @param array<int,string> $pages
	 * @return array<int,string>
	 */
	public function register_external_admin_page( array $pages ): array {
		$pages[] = 'ys-ec-newebpay';
		$pages[] = 'ys-ecommerce-newebpay';

		return array_values( array_unique( $pages ) );
	}

	/**
	 * @param array<string,array{label:string,icon:string,slugs:array<int,string>}> $groups
	 * @return array<string,array{label:string,icon:string,slugs:array<int,string>}>
	 */
	public function register_admin_nav_group( array $groups ): array {
		if ( ! isset( $groups['providers'] ) || ! is_array( $groups['providers']['slugs'] ?? null ) ) {
			return $groups;
		}

		$groups['providers']['slugs'] = array_values(
			array_unique(
				array_merge(
					$groups['providers']['slugs'],
					[ 'ys-ec-newebpay', 'ys-ecommerce-newebpay' ]
				)
			)
		);

		return $groups;
	}
}
