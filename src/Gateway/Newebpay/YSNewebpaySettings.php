<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\Admin\YSAdminApp;
use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\YSEcommerce;
use YangSheep\YSCartNewebpay\Plugin;

defined( 'ABSPATH' ) || exit;

final class YSNewebpaySettings {
	private const NONCE_ACTION = 'ys_ec_newebpay_save_settings';

	public const TABS = [
		'api'         => 'API 設定',
		'payment'     => '金流方式',
		'shipping'    => '物流方式',
		'installment' => '分期設定',
		'callbacks'   => '回呼網址',
		'log'         => '除錯記錄',
	];

	public const SETTING_KEYS = [
		'enabled'          => 'ys_ec_newebpay_enabled',
		'test_mode'        => 'ys_ec_newebpay_test_mode',
		'merchant_id'      => 'ys_ec_newebpay_merchant_id',
		'hash_key'         => 'ys_ec_newebpay_hash_key',
		'hash_iv'          => 'ys_ec_newebpay_hash_iv',
		'debug_enabled'    => 'ys_ec_newebpay_debug_enabled',
		'trade_limit'      => 'ys_ec_newebpay_trade_limit',
		'atm_expire_days'  => 'ys_ec_newebpay_atm_expire_days',
		'cvs_expire_days'  => 'ys_ec_newebpay_cvs_expire_days',
		'bar_expire_days'  => 'ys_ec_newebpay_barcode_expire_days',
		'inst_flag'        => 'ys_ec_newebpay_inst_flag',
		'credit_enabled'   => 'ys_ec_newebpay_credit_enabled',
		'inst_enabled'     => 'ys_ec_newebpay_installment_enabled',
		'atm_enabled'      => 'ys_ec_newebpay_atm_enabled',
		'cvs_enabled'      => 'ys_ec_newebpay_cvs_enabled',
		'barcode_enabled'  => 'ys_ec_newebpay_barcode_enabled',
		'linepay_enabled'  => 'ys_ec_newebpay_linepay_enabled',
		'applepay_enabled' => 'ys_ec_newebpay_applepay_enabled',
	];

	public const PAYMENT_METHOD_IDS = [
		'credit_enabled'   => 'ys_ec_newebpay_credit',
		'inst_enabled'     => 'ys_ec_newebpay_installment',
		'atm_enabled'      => 'ys_ec_newebpay_atm',
		'cvs_enabled'      => 'ys_ec_newebpay_cvs',
		'barcode_enabled'  => 'ys_ec_newebpay_barcode',
		'linepay_enabled'  => 'ys_ec_newebpay_linepay',
		'applepay_enabled' => 'ys_ec_newebpay_applepay',
	];

	public const METHOD_KEY_TO_ID = [
		'credit'   => 'ys_ec_newebpay_credit',
		'inst'     => 'ys_ec_newebpay_installment',
		'atm'      => 'ys_ec_newebpay_atm',
		'cvs'      => 'ys_ec_newebpay_cvs',
		'barcode'  => 'ys_ec_newebpay_barcode',
		'linepay'  => 'ys_ec_newebpay_linepay',
		'applepay' => 'ys_ec_newebpay_applepay',
	];

	public const LOGISTICS_METHODS = [
		'ys_ec_newebpay_ship_711_c2c'    => '7-ELEVEN C2C 超商取貨',
		'ys_ec_newebpay_ship_family_c2c' => '全家 C2C 超商取貨',
		'ys_ec_newebpay_ship_hilife_c2c' => '萊爾富 C2C 超商取貨',
		'ys_ec_newebpay_ship_ok_c2c'     => 'OK mart C2C 超商取貨',
		'ys_ec_newebpay_ship_711_b2c'    => '7-ELEVEN B2C 超商取貨',
	];

	public static function register(): void {
		add_action( 'admin_post_ys_ec_newebpay_save_settings', [ __CLASS__, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( false === strpos( $hook, 'ys-ec-newebpay' )
			&& false === strpos( $hook, 'ys-ecommerce-newebpay' )
			&& false === strpos( $hook, 'ys-provider-newebpay' )
			&& ! in_array( $page, [ 'ys-ec-newebpay', 'ys-ecommerce-newebpay', 'ys-provider-newebpay' ], true )
		) {
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
		$method_id = self::METHOD_KEY_TO_ID[ $method ] ?? '';
		if ( '' !== $method_id && class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'payment', $method_id, Plugin::manifest() );
		}

		$alias = $method . '_enabled';
		return self::is_global_enabled() && '1' === self::get( $alias, '0' );
	}

	public static function is_logistics_method_enabled( string $method_id ): bool {
		if ( ! isset( self::LOGISTICS_METHODS[ $method_id ] ) ) {
			return false;
		}

		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'shipping', $method_id, Plugin::manifest() );
		}

		return self::is_global_enabled()
			&& '1' === (string) YSEcommerce::get_instance()->get_setting( 'shipping_' . $method_id . '_enabled', '0' );
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
		$ec       = YSEcommerce::get_instance();

		$settings['enabled']         = self::is_provider_enabled() ? '1' : '0';
		$settings['hash_key_is_set'] = '' !== (string) ( $settings['hash_key'] ?? '' );
		$settings['hash_iv_is_set']  = '' !== (string) ( $settings['hash_iv'] ?? '' );
		$settings['hash_key']        = '';
		$settings['hash_iv']         = '';
		$settings['logistics']       = [];

		foreach ( self::PAYMENT_METHOD_IDS as $alias => $method_id ) {
			if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
				$settings[ $alias ] = \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'payment', $method_id, Plugin::manifest() ) ? '1' : '0';
			}
		}

		foreach ( self::LOGISTICS_METHODS as $method_id => $label ) {
			$enabled = class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' )
				? \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_method_enabled( 'shipping', $method_id, Plugin::manifest() )
				: '1' === (string) $ec->get_setting( 'shipping_' . $method_id . '_enabled', '0' );

			$settings['logistics'][ $method_id ] = [
				'label'   => $label,
				'enabled' => $enabled ? '1' : '0',
			];
		}

		return $settings;
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '權限不足。', 'ys-cart-newebpay' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION );

		$ec  = YSEcommerce::get_instance();
		$tab = self::sanitize_tab( (string) wp_unslash( $_POST['ys_ec_newebpay_tab'] ?? 'api' ) );

		switch ( $tab ) {
			case 'api':
				foreach ( [ 'enabled', 'test_mode' ] as $alias ) {
					$key = self::SETTING_KEYS[ $alias ];
					$ec->update_setting( $key, isset( $_POST[ $key ] ) ? '1' : '0' );
				}
				self::sync_provider_lifecycle( isset( $_POST[ self::SETTING_KEYS['enabled'] ] ) );

				$merchant_id = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['merchant_id'] ] ?? '' ) ) );
				$ec->update_setting( self::SETTING_KEYS['merchant_id'], mb_substr( $merchant_id, 0, 20 ) );

				$hash_key = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['hash_key'] ] ?? '' ) ) );
				if ( '' !== $hash_key ) {
					$ec->update_setting( self::SETTING_KEYS['hash_key'], YSCrypto::encrypt_for_storage( $hash_key ) );
				}

				$hash_iv = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['hash_iv'] ] ?? '' ) ) );
				if ( '' !== $hash_iv ) {
					$ec->update_setting( self::SETTING_KEYS['hash_iv'], YSCrypto::encrypt_for_storage( $hash_iv ) );
				}
				break;

			case 'payment':
				$selected = [];
				foreach ( array_keys( self::PAYMENT_METHOD_IDS ) as $alias ) {
					$key     = self::SETTING_KEYS[ $alias ];
					$enabled = isset( $_POST[ $key ] );
					$ec->update_setting( $key, $enabled ? '1' : '0' );
					if ( $enabled ) {
						$selected[] = self::PAYMENT_METHOD_IDS[ $alias ];
					}
				}
				self::sync_lifecycle_methods( 'payment', self::PAYMENT_METHOD_IDS, $selected );

				$trade_limit = absint( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['trade_limit'] ] ?? '0' ) ) );
				$ec->update_setting( self::SETTING_KEYS['trade_limit'], (string) min( $trade_limit, 9999999 ) );

				foreach ( [ 'atm_expire_days', 'cvs_expire_days', 'bar_expire_days' ] as $alias ) {
					$value = absint( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS[ $alias ] ] ?? self::default_for( $alias ) ) ) );
					if ( $value < 1 || $value > 180 ) {
						$value = (int) self::default_for( $alias );
					}
					$ec->update_setting( self::SETTING_KEYS[ $alias ], (string) $value );
				}
				break;

			case 'shipping':
				$selected = [];
				foreach ( self::LOGISTICS_METHODS as $method_id => $label ) {
					unset( $label );
					$field   = 'shipping_' . $method_id . '_enabled';
					$enabled = isset( $_POST[ $field ] );
					$ec->update_setting( $field, $enabled ? '1' : '0' );
					if ( $enabled ) {
						$selected[] = $method_id;
					}
				}
				self::sync_lifecycle_methods( 'shipping', self::LOGISTICS_METHODS, $selected );
				break;

			case 'installment':
				$key = self::SETTING_KEYS['inst_enabled'];
				$ec->update_setting( $key, isset( $_POST[ $key ] ) ? '1' : '0' );
				self::sync_lifecycle_methods(
					'payment',
					[ 'inst_enabled' => self::PAYMENT_METHOD_IDS['inst_enabled'] ],
					isset( $_POST[ $key ] ) ? [ self::PAYMENT_METHOD_IDS['inst_enabled'] ] : []
				);

				$inst_flag = sanitize_text_field( wp_unslash( (string) ( $_POST[ self::SETTING_KEYS['inst_flag'] ] ?? '3,6,12' ) ) );
				$inst_flag = preg_replace( '/[^0-9,]/', '', $inst_flag ) ?: '3,6,12';
				$ec->update_setting( self::SETTING_KEYS['inst_flag'], mb_substr( $inst_flag, 0, 64 ) );
				break;

			case 'log':
				$key = self::SETTING_KEYS['debug_enabled'];
				$ec->update_setting( $key, isset( $_POST[ $key ] ) ? '1' : '0' );
				break;
		}

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=ys-provider-newebpay' );
		wp_safe_redirect(
			add_query_arg(
				[
					'tab'   => $tab,
					'saved' => '1',
				],
				$redirect
			)
		);
		exit;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '權限不足。', 'ys-cart-newebpay' ), 403 );
		}

		$settings            = self::get_settings_for_render();
		$hash_key_is_set     = (bool) ( $settings['hash_key_is_set'] ?? false );
		$hash_iv_is_set      = (bool) ( $settings['hash_iv_is_set'] ?? false );
		$nonce_action        = self::NONCE_ACTION;
		$tab                 = self::sanitize_tab( (string) ( $_GET['tab'] ?? 'api' ) );
		$tabs                = self::TABS;
		$page_url            = admin_url( 'admin.php?page=ys-provider-newebpay' );
		$saved               = ( isset( $_GET['saved'] ) && '1' === (string) $_GET['saved'] ) || isset( $_GET['ys_ec_newebpay_saved'] );
		$notify_url          = YSNewebpayCallbackControllerProxy::notify_url();
		$return_url          = YSNewebpayCallbackControllerProxy::return_url();
		$store_callback_url  = rest_url( 'ys-ecommerce/v1/newebpay/store-callback' );
		$shipping_notify_url = rest_url( 'ys-ecommerce/v1/newebpay/shipping-notify' );

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::open( '藍新 NewebPay 設定', '金物流 / 藍新' );
		}

		$template = dirname( __DIR__, 3 ) . '/templates/admin/gateways/newebpay-settings.php';
		if ( is_readable( $template ) ) {
			include $template;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( '找不到藍新設定樣板。', 'ys-cart-newebpay' ) . '</p></div>';
		}

		if ( class_exists( YSAdminApp::class ) ) {
			YSAdminApp::close();
		}
	}

	public static function get_nonce_action(): string {
		return self::NONCE_ACTION;
	}

	private static function sanitize_tab( string $tab ): string {
		$tab = sanitize_key( $tab );
		return isset( self::TABS[ $tab ] ) ? $tab : 'api';
	}

	private static function is_provider_enabled(): bool {
		if ( class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::is_provider_enabled( 'ys_newebpay', Plugin::manifest() );
		}

		return self::is_global_enabled();
	}

	private static function sync_provider_lifecycle( bool $enabled ): void {
		if ( ! class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return;
		}

		\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::set_provider_enabled( 'ys_newebpay', $enabled, Plugin::manifest() );
	}

	/**
	 * @param array<string,string> $owned_ids_by_alias
	 * @param array<int,string>   $selected_ids
	 */
	private static function sync_lifecycle_methods( string $domain, array $owned_ids_by_alias, array $selected_ids ): void {
		if ( ! class_exists( '\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState' ) ) {
			return;
		}

		$state        = \YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::get_methods_state( $domain );
		$owned_ids    = array_values( array_unique( array_map( 'sanitize_key', array_values( $owned_ids_by_alias ) ) ) );
		$selected_ids = array_values( array_unique( array_map( 'sanitize_key', $selected_ids ) ) );
		$order        = 0;

		foreach ( $state as $row ) {
			if ( is_array( $row ) && isset( $row['order'] ) ) {
				$order = max( $order, (int) $row['order'] + 1 );
			}
		}

		foreach ( $owned_ids as $method_id ) {
			if ( '' === $method_id ) {
				continue;
			}

			if ( ! isset( $state[ $method_id ] ) || ! is_array( $state[ $method_id ] ) ) {
				$state[ $method_id ] = [ 'order' => $order++ ];
			}

			$state[ $method_id ]['enabled']     = in_array( $method_id, $selected_ids, true );
			$state[ $method_id ]['provider_id'] = 'ys_newebpay';
		}

		\YangSheep\Ecommerce\Core\Provider\YSProviderLifecycleState::update_methods_state( $domain, $state );
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
