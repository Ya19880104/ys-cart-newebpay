<?php

namespace YangSheep\YSCartNewebpay\Api;

use YangSheep\Ecommerce\Models\YSOrder;
use YangSheep\Ecommerce\Security\YSRateLimiter;
use YangSheep\Ecommerce\Security\YSWebhookGuard;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayClient;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpayWebhookHandler;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayCallbackController {
	public const REST_NAMESPACE = 'ys-ecommerce/v1';
	private const MAX_BODY_BYTES = 65536;

	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/newebpay/notify',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_notify' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/newebpay/return',
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ __CLASS__, 'handle_return' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function handle_notify( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! YSWebhookGuard::verify_ip( 'newebpay_notify' ) ) {
			return self::json_error( 'IP not allowed.', 403 );
		}

		$body = (string) $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return self::json_error( 'Body too large.', 413 );
		}

		if ( class_exists( YSRateLimiter::class ) && ! YSRateLimiter::check( 'newebpay_notify', 300, 60 ) ) {
			return self::json_error( 'Rate limit exceeded.', 429 );
		}

		$client = new YSNewebpayClient();
		if ( ! $client->is_configured() ) {
			return self::json_error( 'NewebPay is not configured.', 503 );
		}

		$params  = $request->get_params();
		$decoded = $client->verify_and_decode_callback( $params );
		if ( null === $decoded ) {
			YSLogger::warning( 'newebpay', 'Notify verification failed' );
			return self::json_error( 'Invalid callback.', 400 );
		}

		$signature = (string) ( $params['TradeSha'] ?? $params['TradeInfo'] ?? '' );
		if ( ! YSWebhookGuard::check_replay( 'newebpay_notify', $signature, 600 ) ) {
			return new \WP_REST_Response( 'OK', 200 );
		}

		$merchant_order_no = YSNewebpayClient::extract_result_value( $decoded, 'MerchantOrderNo' );
		$order             = self::find_order_by_merchant_order_no( $merchant_order_no );
		if ( ! $order ) {
			YSLogger::warning( 'newebpay', 'Notify order not found', [ 'merchant_order_no' => $merchant_order_no ] );
			return new \WP_REST_Response( 'OK', 200 );
		}

		YSNewebpayWebhookHandler::handle( (int) $order->id, $decoded, 'webhook_newebpay_notify' );

		return new \WP_REST_Response( 'OK', 200 );
	}

	public static function handle_return( \WP_REST_Request $request ): void {
		$params = $request->get_params();
		$order  = null;

		$client = new YSNewebpayClient();
		if ( $client->is_configured() && ! empty( $params['TradeInfo'] ) && ! empty( $params['TradeSha'] ) ) {
			$decoded = $client->verify_and_decode_callback( $params );
			if ( is_array( $decoded ) ) {
				$merchant_order_no = YSNewebpayClient::extract_result_value( $decoded, 'MerchantOrderNo' );
				$order             = self::find_order_by_merchant_order_no( $merchant_order_no );
				$signature         = (string) ( $params['TradeSha'] ?? $params['TradeInfo'] ?? '' );

				if ( $order && YSWebhookGuard::check_replay( 'newebpay_return', $signature, 600 ) ) {
					YSNewebpayWebhookHandler::handle( (int) $order->id, $decoded, 'webhook_newebpay_return' );
				}
			}
		}

		if ( $order ) {
			YSOrder::issue_tks_token( (int) $order->id );
			$redirect = add_query_arg(
				[
					'order' => (int) $order->id,
					'key'   => YSOrder::generate_order_key( (int) $order->id, (string) ( $order->order_number ?? '' ) ),
				],
				home_url( '/thankyou/' )
			);
		} else {
			$redirect = home_url( '/cart/' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function find_order_by_merchant_order_no( string $merchant_order_no ): ?object {
		$merchant_order_no = trim( $merchant_order_no );
		if ( '' === $merchant_order_no ) {
			return null;
		}

		if ( preg_match( '/^YS(\d+)T\d+$/', $merchant_order_no, $matches ) ) {
			$order = YSOrder::find( (int) $matches[1] );
			if ( $order && self::order_has_merchant_order_no( $order, $merchant_order_no ) ) {
				return $order;
			}
		}

		global $wpdb;
		$table = YSOrder::table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE JSON_UNQUOTE(JSON_EXTRACT(payment_detail, '$.newebpay_merchant_order_no')) = %s
				    OR JSON_UNQUOTE(JSON_EXTRACT(payment_detail, '$.mer_trade_no')) = %s
				 ORDER BY id DESC LIMIT 1",
				$merchant_order_no,
				$merchant_order_no
			)
		);

		return $row ?: null;
	}

	private static function order_has_merchant_order_no( object $order, string $merchant_order_no ): bool {
		$detail = json_decode( (string) ( $order->payment_detail ?? '{}' ), true );
		if ( ! is_array( $detail ) ) {
			return false;
		}

		return hash_equals( (string) ( $detail['newebpay_merchant_order_no'] ?? '' ), $merchant_order_no )
			|| hash_equals( (string) ( $detail['mer_trade_no'] ?? '' ), $merchant_order_no );
	}

	private static function json_error( string $message, int $status ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'Status'  => 'FAILED',
				'Message' => $message,
			],
			$status
		);
	}
}
