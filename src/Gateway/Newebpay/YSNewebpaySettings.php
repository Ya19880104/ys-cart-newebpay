<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\Admin\YSAdminApp;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\YSEcommerce;

defined( 'ABSPATH' ) || exit;

final class YSNewebpaySettings {
	private const NONCE_ACTION = 'ys_ec_newebpay_save_settings';

	public const SETTING_KEYS = [
		'enabled'         => 'ys_ec_newebpay_enabled',
		'test_mode'       => 'ys_ec_newebpay_test_mode',
		'merchant_id'     => 'ys_ec_newebpay_merchant_id',
		'hash_key'        => 'ys_ec_newebpay_hash_key',
		'hash_iv'         => 'ys_ec_newebpay_hash_iv',
		'debug_enabled'   => 'ys_ec_newebpay_debug_enabled',
		'trade_limit'     => 'ys_ec_newebpay_trade_limit',
		'atm_expire_days' => 'ys_ec_newebpay_atm_expire_days',
		'cvs_expire_days' => 'ys_ec_newebpay_cvs_expire_days',
		'bar_expire_days' => 'ys_ec_newebpay_barcode_expire_days',
		'inst_flag'       => 'ys_ec_newebpay_inst_flag',
		'credit_enabled'  => 'ys_ec_newebpay_credit_enabled',
		'inst_enabled'    => 'ys_ec_newebpay_installment_enabled',
		'atm_enabled'     => 'ys_ec_newebpay_atm_enabled',
		'cvs_enabled'     => 'ys_ec_newebpay_cvs_enabled',
		'barcode_enabled' => 'ys_ec_newebpay_barcode_enabled',
		'linepay_enabled' => 'ys_ec_newebpay_linepay_enabled',
		'applepay_enabled'=> 'ys_ec_newebpay_applepay_enabled',
	];

	public static function register(): void {
		add_action( 'admin_post_ys_ec_newebpay_save_settings', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( false === strpos( $hook, 'ys-ecommerce-newebpay' ) && 'ys-ecommerce-newebpay' !== $page ) {
			return;
		}

		if ( defined( 'YS_ECOMMERCE_URL' ) && defined( 'YS_ECOMMERCE_VERSION' ) ) {
			wp_enqueue_script(
				'ys-ec-password-toggle',
				YS_ECOMMERCE_URL . 'assets/js/admin/ys-ec-password-toggle.js',
				[],
				YS_ECOMMERCE_VERSION,
				true
			);
		}

		wp_enqueue_script(
			'ys-ec-newebpay-test-connection',
			YS_CART_NEWEBPAY_URL . 'assets/js/admin/newebpay-test-connection.js',
			[],
			YS_CART_NEWEBPAY_VERSION,
			true
		);

		wp_localize_script(
			'ys-ec-newebpay-test-connection',
			'ysNewebpayTestConnection',
			[
				'endpoint' => esc_url_raw( rest_url( 'ys-ecommerce-headless/v1/admin/newebpay/test-connection' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	public static function default_for( string $alias ): string {
		return match ( $alias ) {
			'test_mode'       => '1',
			'enabled',
			'debug_enabled',
			'credit_enabled',
			'inst_enabled',
			'atm_enabled',
			'cvs_enabled',
			'barcode_enabled',
			'linepay_enabled',
			'applepay_enabled',
			'trade_limit'     => '0',
			'atm_expire_days' => '3',
			'cvs_expire_days' => '3',
			'bar_expire_days' => '3',
			'inst_flag'       => '3,6,12',
			default           => '',
		};
	}

	public static function get( string $alias, string $fallback = '' ): string {
		$key = self::SETTING_KEYS[ $alias ] ?? '';
		if ( '' === $key ) {
			return $fallback;
		}

		return (string) YSEcommerce::get_instance()->get_setting(
			$key,
			'' !== $fallback ? $fallback : self::default_for( $alias )
		);
	}

	public static function is_global_enabled(): bool {
		return '1' === self::get( 'enabled', '0' );
	}

	public static function is_method_enabled( string $method ): bool {
		$alias = $method . '_enabled';
		return self::is_global_enabled() && '1' === self::get( $alias, '0' );
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_all(): array {
		$out = [];
		$ec  = YSEcommerce::get_instance();

		foreach ( self::SETTING_KEYS as $alias => $key ) {
			$out[ $alias ] = (string) $ec->get_setting( $key, self::default_for( $alias ) );
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get_settings_for_render(): array {
		$settings = self::get_all();

		$settings['hash_key_is_set'] = '' !== (string) ( $settings['hash_key'] ?? '' );
		$settings['hash_iv_is_set']  = '' !== (string) ( $settings['hash_iv'] ?? '' );
		$settings['hash_key']        = '';
		$settings['hash_iv']         = '';

		return $settings;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-newebpay' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$ec = YSEcommerce::get_instance();

		$checkboxes = [
			'enabled',
			'test_mode',
			'debug_enabled',
			'credit_enabled',
			'inst_enabled',
			'atm_enabled',
			'cvs_enabled',
			'barcode_enabled',
			'linepay_enabled',
			'applepay_enabled',
		];

		foreach ( $checkboxes as $alias ) {
			$key = self::SETTING_KEYS[ $alias ];
			$ec->update_setting( $key, isset( $_POST[ $key ] ) ? '1' : '0' );
		}

		$merchant_id = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['merchant_id'] ] ?? '' ) ) );
		$ec->update_setting( self::SETTING_KEYS['merchant_id'], mb_substr( $merchant_id, 0, 20 ) );

		$trade_limit = absint( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['trade_limit'] ] ?? '0' ) ) );
		$ec->update_setting( self::SETTING_KEYS['trade_limit'], (string) min( $trade_limit, 9999999 ) );

		foreach ( [ 'atm_expire_days', 'cvs_expire_days', 'bar_expire_days' ] as $alias ) {
			$value = absint( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS[ $alias ] ] ?? self::default_for( $alias ) ) ) );
			if ( $value < 1 || $value > 180 ) {
				$value = (int) self::default_for( $alias );
			}
			$ec->update_setting( self::SETTING_KEYS[ $alias ], (string) $value );
		}

		$inst_flag = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['inst_flag'] ] ?? '3,6,12' ) ) );
		$inst_flag = preg_replace( '/[^0-9,]/', '', $inst_flag ) ?: '3,6,12';
		$ec->update_setting( self::SETTING_KEYS['inst_flag'], mb_substr( $inst_flag, 0, 64 ) );

		$hash_key = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['hash_key'] ] ?? '' ) ) );
		if ( '' !== $hash_key ) {
			$ec->update_setting( self::SETTING_KEYS['hash_key'], YSCrypto::encrypt_for_storage( $hash_key ) );
		}

		$hash_iv = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['hash_iv'] ] ?? '' ) ) );
		if ( '' !== $hash_iv ) {
			$ec->update_setting( self::SETTING_KEYS['hash_iv'], YSCrypto::encrypt_for_storage( $hash_iv ) );
		}

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=ys-ecommerce-newebpay' );
		wp_safe_redirect( add_query_arg( 'ys_ec_newebpay_saved', '1', $redirect ) );
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ys-cart-newebpay' ), 403 );
		}

		$settings        = self::get_settings_for_render();
		$hash_key_is_set = (bool) ( $settings['hash_key_is_set'] ?? false );
		$hash_iv_is_set  = (bool) ( $settings['hash_iv_is_set'] ?? false );
		$nonce_action    = self::NONCE_ACTION;
		$notify_url      = YSNewebpayCallbackControllerProxy::notify_url();
		$return_url      = YSNewebpayCallbackControllerProxy::return_url();

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::open( 'NewebPay', 'Payment / NewebPay' );
		}

		$template = dirname( __DIR__, 3 ) . '/templates/admin/gateways/newebpay-settings.php';
		if ( is_readable( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>NewebPay settings template missing.</p></div>';
		}

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::close();
		}
	}

	public static function get_nonce_action(): string {
		return self::NONCE_ACTION;
	}
}

final class YSNewebpayCallbackControllerProxy {
	public static function notify_url(): string {
		return rest_url( 'ys-ecommerce/v1/newebpay/notify' );
	}

	public static function return_url(): string {
		return rest_url( 'ys-ecommerce/v1/newebpay/return' );
	}
}
