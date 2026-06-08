<?php

namespace YangSheep\YSCartNewebpay;

use YangSheep\Ecommerce\Api\Storefront\YSRequestParser;
use YangSheep\Ecommerce\Api\Storefront\YSRestAuth;
use YangSheep\Ecommerce\Api\Storefront\YSRestResponder;
use YangSheep\Ecommerce\Gateways\YSGatewayRegistry;
use YangSheep\Ecommerce\Security\YSInboundPermission;
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
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayPaymentReconciler;
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

	private const REGISTERED_GATEWAY_IDS = [
		'ys_ec_newebpay_credit',
		'ys_ec_newebpay_installment',
		'ys_ec_newebpay_atm',
		'ys_ec_newebpay_cvs',
		'ys_ec_newebpay_barcode',
		'ys_ec_newebpay_linepay',
		'ys_ec_newebpay_applepay',
	];

	private const REGISTERED_SHIPPING_IDS = [
		'ys_ec_newebpay_ship_711_c2c',
		'ys_ec_newebpay_ship_family_c2c',
		'ys_ec_newebpay_ship_hilife_c2c',
		'ys_ec_newebpay_ship_ok_c2c',
		'ys_ec_newebpay_ship_711_b2c',
	];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		YSNewebpaySettings::register();

		add_filter( 'ys_ec_provider_manifests', [ $this, 'register_manifest' ], 10, 1 );
		add_action( 'ys_ec_register_gateways', [ $this, 'register_gateways' ] );
		add_action( 'ys_ec_register_shipping_methods', [ $this, 'register_shipping_methods' ] );
		add_action( 'ys_ec_register_admin_rest_routes', [ $this, 'register_admin_routes' ] );
		add_action( 'ys_ec_register_storefront_routes', [ $this, 'register_storefront_routes' ] );
		add_action( 'ys_ec_register_payment_reconcilers', [ $this, 'register_payment_reconcilers' ] );
		add_action( 'rest_api_init', [ $this, 'register_public_routes' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_store_selector_bridge' ] );
		add_filter( 'ys_ec_shipping_requester', [ $this, 'register_shipping_requester' ], 10, 2 );
		add_filter( 'ys_ec_shipping_carrier_adapter', [ $this, 'register_carrier_adapter' ], 10, 2 );
		add_filter( 'ys_ec_shipping_provider_labels', [ $this, 'register_shipping_provider_label' ] );
	}

	/**
	 * @param array<int,array<string,mixed>> $manifests
	 * @return array<int,array<string,mixed>>
	 */
	public function register_manifest( array $manifests ): array {
		$manifests[] = self::manifest();

		return $manifests;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function manifest(): array {
		static $manifest = null;

		if ( null === $manifest ) {
			$manifest = require YS_CART_NEWEBPAY_DIR . 'manifest.php';
		}

		return $manifest;
	}

	public function register_gateways(): void {
		if ( ! class_exists( YSGatewayRegistry::class ) || ! $this->is_payment_enabled() ) {
			return;
		}

		$gateways = [
			'ys_ec_newebpay_credit'      => YSNewebpayCreditGateway::class,
			'ys_ec_newebpay_installment' => YSNewebpayInstallmentGateway::class,
			'ys_ec_newebpay_atm'         => YSNewebpayAtmGateway::class,
			'ys_ec_newebpay_cvs'         => YSNewebpayCvsGateway::class,
			'ys_ec_newebpay_barcode'     => YSNewebpayBarcodeGateway::class,
			'ys_ec_newebpay_linepay'     => YSNewebpayLinePayGateway::class,
			'ys_ec_newebpay_applepay'    => YSNewebpayApplePayGateway::class,
		];

		foreach ( $gateways as $method_id => $gateway_class ) {
			if ( $this->is_method_enabled( 'payment', $method_id ) ) {
				YSGatewayRegistry::register( new $gateway_class() );
			}
		}
	}

	public function register_payment_reconcilers( $registry ): void {
		if ( ! $this->has_enabled_payment_methods()
			|| ! is_object( $registry )
			|| ! method_exists( $registry, 'register' )
			|| ! interface_exists( '\YangSheep\Ecommerce\Services\Payment\YSPaymentReconcilerInterface' ) ) {
			return;
		}

		$client = new \YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayClient();
		if ( ! $client->is_configured() ) {
			return;
		}

		$registry->register( new YSNewebpayPaymentReconciler( $client ) );
	}

	public function register_shipping_methods(): void {
		if ( ! class_exists( YSShippingRegistry::class ) || ! $this->is_shipping_enabled() ) {
			return;
		}

		$methods = [
			'ys_ec_newebpay_ship_711_c2c'    => YSNewebpayShipping711C2C::class,
			'ys_ec_newebpay_ship_family_c2c' => YSNewebpayShippingFamilyC2C::class,
			'ys_ec_newebpay_ship_hilife_c2c' => YSNewebpayShippingHilifeC2C::class,
			'ys_ec_newebpay_ship_ok_c2c'     => YSNewebpayShippingOkC2C::class,
			'ys_ec_newebpay_ship_711_b2c'    => YSNewebpayShipping711B2C::class,
		];

		foreach ( $methods as $method_id => $method_class ) {
			if ( $this->is_method_enabled( 'shipping', $method_id ) ) {
				YSShippingRegistry::register( new $method_class() );
			}
		}
	}

	public function register_admin_routes( $registrar = null ): void {
		unset( $registrar );

		if ( ! $this->is_provider_enabled() ) {
			return;
		}

		YSNewebpayTestConnectionController::register_routes();
	}

	public function register_storefront_routes( string $namespace = '' ): void {
		if ( $this->has_enabled_payment_methods() ) {
			YSNewebpayCallbackController::register_routes();
		}

		if ( ! $this->has_enabled_shipping_methods() ) {
			return;
		}

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
		if ( ! $this->has_enabled_shipping_methods() ) {
			return;
		}

		register_rest_route(
			'ys-ecommerce/v1',
			'/newebpay/store-callback',
			[
				'methods'             => 'POST',
				'callback'            => [ YSNewebpayStoreSelector::class, 'handle_store_callback' ],
				'permission_callback' => [ self::class, 'store_callback_permission' ],
			]
		);

		YSNewebpayShippingNotifyController::register_routes();
	}

	public static function store_callback_permission( \WP_REST_Request $request ) {
		if ( ! class_exists( YSInboundPermission::class ) ) {
			return true;
		}

		$callback = YSInboundPermission::build( 'newebpay_store_callback', [
			'body_max_bytes' => 65536,
			'rate_limit'     => [ 300, 60 ],
			'allowed_types'  => [ 'application/x-www-form-urlencoded' ],
			'verify_ip'      => false,
		] );
		return $callback( $request );
	}

	public function newebpay_map_url( \WP_REST_Request $request ): \WP_REST_Response {
		$params      = class_exists( YSRequestParser::class ) ? YSRequestParser::params( $request ) : $request->get_params();
		$shipping_id = sanitize_text_field( (string) ( $params['shipping_id'] ?? $params['shipping_method'] ?? '' ) );

		if ( ! $this->is_shipping_enabled() ) {
			return YSRestResponder::error( 'provider_disabled', '藍新物流尚未啟用。' );
		}

		if ( '' === $shipping_id ) {
			return YSRestResponder::error( 'missing_shipping_id', '缺少物流方式 ID。' );
		}

		if ( ! $this->is_method_enabled( 'shipping', $shipping_id ) ) {
			return YSRestResponder::error( 'shipping_method_disabled', '藍新物流方式尚未啟用。' );
		}

		$result = YSNewebpayStoreSelector::build_map_form_data( $shipping_id );
		if ( $result ) {
			return YSRestResponder::success( 'map_url_ready', '', $result );
		}

		return YSRestResponder::error( 'map_url_failed', '藍新物流 API 設定尚未完成。' );
	}

	public function enqueue_store_selector_bridge(): void {
		if ( ! $this->has_enabled_shipping_methods() ) {
			return;
		}

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

		if ( ! $this->has_enabled_shipping_methods() ) {
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

		if ( ! $this->has_enabled_shipping_methods() ) {
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
		if ( ! $this->has_enabled_shipping_methods() ) {
			return $labels;
		}

		$labels['newebpay'] = 'NewebPay';

		return $labels;
	}

	private function is_provider_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_provider_enabled( 'ys_newebpay', self::manifest() );
		}

		return YSNewebpaySettings::is_global_enabled();
	}

	private function is_payment_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_capability_enabled( 'ys_newebpay', 'payment', self::manifest() );
		}

		return $this->is_provider_enabled();
	}

	private function is_shipping_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_capability_enabled( 'ys_newebpay', 'shipping', self::manifest() );
		}

		return $this->is_provider_enabled();
	}

	private function has_enabled_payment_methods(): bool {
		if ( ! $this->is_payment_enabled() ) {
			return false;
		}

		foreach ( self::REGISTERED_GATEWAY_IDS as $method_id ) {
			if ( $this->is_method_enabled( 'payment', $method_id ) ) {
				return true;
			}
		}

		return false;
	}

	private function has_enabled_shipping_methods(): bool {
		if ( ! $this->is_shipping_enabled() ) {
			return false;
		}

		foreach ( self::REGISTERED_SHIPPING_IDS as $method_id ) {
			if ( $this->is_method_enabled( 'shipping', $method_id ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_method_enabled( string $domain, string $method_id ): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( $domain, $method_id, self::manifest() );
		}

		if ( 'payment' === $domain ) {
			$legacy_map = array_flip( YSNewebpaySettings::METHOD_KEY_TO_ID );
			if ( ! isset( $legacy_map[ $method_id ] ) ) {
				return false;
			}

			return YSNewebpaySettings::is_method_enabled( $legacy_map[ $method_id ] );
		}

		if ( 'shipping' === $domain ) {
			return YSNewebpaySettings::is_logistics_method_enabled( $method_id );
		}

		return false;
	}
}
