<?php
/**
 * Plugin Name: YS CART - NewebPay
 * Plugin URI: https://github.com/Ya19880104/ys-cart-newebpay
 * Description: Adds NewebPay MPG payment and official logistics methods to YS CART as an external provider plugin.
 * Version: 1.0.9
 * Author: YangSheep
 * Author URI: https://yangsheep.com.tw
 * Requires PHP: 8.1
 * Requires Plugins: ys-cart
 * Text Domain: ys-cart-newebpay
 */

defined( 'ABSPATH' ) || exit;

define( 'YS_CART_NEWEBPAY_VERSION', '1.0.9' );
define( 'YS_CART_NEWEBPAY_FILE', __FILE__ );
define( 'YS_CART_NEWEBPAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'YS_CART_NEWEBPAY_URL', plugin_dir_url( __FILE__ ) );
define( 'YS_CART_NEWEBPAY_BASENAME', plugin_basename( __FILE__ ) );

if ( is_readable( YS_CART_NEWEBPAY_DIR . 'vendor/autoload.php' ) ) {
	require_once YS_CART_NEWEBPAY_DIR . 'vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'YangSheep\\YSCartNewebpay\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = YS_CART_NEWEBPAY_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \YangSheep\Ecommerce\Gateways\YSGatewayRegistry::class ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>YS CART - NewebPay requires YS CART to be active.</p></div>';
					}
				}
			);
			return;
		}

		if ( class_exists( '\YangSheep\PluginHubClient\YSPluginHubClient' ) ) {
			\YangSheep\PluginHubClient\YSPluginHubClient::register(
				[
					'slug'        => 'ys-cart-newebpay',
					'version'     => YS_CART_NEWEBPAY_VERSION,
					'plugin_file' => __FILE__,
					'name'        => 'YS CART - NewebPay',
				]
			);
		}

		\YangSheep\YSCartNewebpay\Plugin::instance()->init();
	},
	30
);
