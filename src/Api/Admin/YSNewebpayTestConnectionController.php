<?php

namespace YangSheep\YSCartNewebpay\Api\Admin;

use YangSheep\Ecommerce\Api\Storefront\YSRouteRegistrar as StorefrontRouteRegistrar;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayClient;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayTestConnectionController {
	public const NAMESPACE = StorefrontRouteRegistrar::NAMESPACE;

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/newebpay/test-connection',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'test_connection' ],
				'permission_callback' => [ __CLASS__, 'permission_admin' ],
			]
		);
	}

	public static function permission_admin( \WP_REST_Request $request ) {
		if ( class_exists( \YangSheep\Ecommerce\Api\Admin\YSAdminRestAuth::class ) ) {
			return \YangSheep\Ecommerce\Api\Admin\YSAdminRestAuth::permission_admin( $request );
		}

		return current_user_can( 'manage_options' );
	}

	public static function test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		unset( $request );

		$client = new YSNewebpayClient();
		if ( ! $client->is_configured() ) {
			return new \WP_REST_Response(
				[
					'ok'      => false,
					'success' => false,
					'error'   => 'incomplete_config',
					'missing' => $client->get_missing_settings(),
					'message' => 'NewebPay settings are incomplete.',
				],
				400
			);
		}

		return new \WP_REST_Response(
			[
				'ok'          => true,
				'success'     => true,
				'test_mode'   => $client->is_test_mode(),
				'mpg_url'     => $client->get_mpg_url(),
				'query_url'   => $client->get_query_url(),
				'message'     => 'NewebPay settings are present. Use a real order callback to verify merchant credentials.',
			],
			200
		);
	}
}
