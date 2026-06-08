<?php

namespace YangSheep\YSCartNewebpay\Api;

use YangSheep\Ecommerce\Handlers\YSShippingHandler;
use YangSheep\Ecommerce\Models\YSOrder;
use YangSheep\Ecommerce\Security\YSInboundPermission;
use YangSheep\Ecommerce\Security\YSRateLimiter;
use YangSheep\Ecommerce\Security\YSWebhookGuard;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\YSCartNewebpay\Logistics\Newebpay\YSNewebpayLogisticsClient;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShippingNotifyController {
	public const REST_NAMESPACE = 'ys-ecommerce/v1';
	private const MAX_BODY_BYTES = 65536;

	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/newebpay/shipping-notify',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_notify' ],
				'permission_callback' => [ __CLASS__, 'notify_permission' ],
			]
		);
	}

	public static function notify_permission( \WP_REST_Request $request ) {
		if ( ! class_exists( YSInboundPermission::class ) ) {
			return true;
		}

		$callback = YSInboundPermission::build( 'newebpay_shipping_notify', [
			'body_max_bytes' => self::MAX_BODY_BYTES,
			'rate_limit'     => [ 300, 60 ],
			'allowed_types'  => [ 'application/x-www-form-urlencoded' ],
		] );
		return $callback( $request );
	}

	public static function handle_notify( \WP_REST_Request $request ): \WP_REST_Response {
		if ( class_exists( YSRateLimiter::class ) && ! YSRateLimiter::check( 'newebpay_shipping_notify', 300, 60 ) ) {
			return new \WP_REST_Response( 'Too many requests.', 429 );
		}

		$body = (string) $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return new \WP_REST_Response( 'Body too large.', 413 );
		}

		$client = new YSNewebpayLogisticsClient();
		$data   = $client->verify_and_decode_callback( $request->get_params() );
		if ( null === $data ) {
			YSLogger::warning( 'newebpay_shipping', 'Shipping notify verification failed.' );
			return new \WP_REST_Response( 'Invalid callback.', 400 );
		}

		$signature = (string) ( $request->get_param( 'HashData_' ) ?? $request->get_param( 'EncryptData_' ) ?? '' );
		if ( class_exists( YSWebhookGuard::class ) && ! YSWebhookGuard::check_replay( 'newebpay_shipping_notify', $signature, 600 ) ) {
			return new \WP_REST_Response( 'OK', 200 );
		}

		self::process_shipping_update( $data );

		return new \WP_REST_Response( 'OK', 200 );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function process_shipping_update( array $data ): void {
		$merchant_order_no = sanitize_text_field( (string) ( $data['MerchantOrderNo'] ?? '' ) );
		if ( '' === $merchant_order_no ) {
			return;
		}

		$order = self::update_shipping_label( $merchant_order_no, $data );
		if ( ! $order ) {
			$order = self::find_order_by_logistics_no( $merchant_order_no );
		}

		if ( ! $order ) {
			YSLogger::warning( 'newebpay_shipping', 'Shipping notify order not found.', [ 'merchant_order_no' => $merchant_order_no ] );
			return;
		}

		$status = sanitize_text_field( (string) ( $data['RetId'] ?? '' ) );
		if ( class_exists( \YangSheep\Ecommerce\Services\Shipping\YSShippingPipelineService::class ) ) {
			\YangSheep\Ecommerce\Services\Shipping\YSShippingPipelineService::advance_from_carrier_status(
				(int) $order->id,
				$status,
				'webhook_newebpay'
			);
		} elseif ( method_exists( YSOrder::class, 'update' ) ) {
			YSOrder::update(
				(int) $order->id,
				[
					'shipping_status' => self::legacy_status( $status ),
					'updated_at'      => current_time( 'mysql' ),
				]
			);
		}
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private static function update_shipping_label( string $merchant_order_no, array $data ): ?object {
		if ( ! class_exists( YSShippingHandler::class ) ) {
			return null;
		}

		global $wpdb;
		$label_table = YSShippingHandler::table();
		$label       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$label_table} WHERE provider_trade_no = %s AND provider = %s ORDER BY id DESC LIMIT 1",
				$merchant_order_no,
				'newebpay'
			)
		);

		if ( ! $label ) {
			return null;
		}

		$status = sanitize_text_field( (string) ( $data['RetId'] ?? '' ) );
		$detail = [
			'ret_id'       => $status,
			'ret_string'   => sanitize_text_field( (string) ( $data['RetString'] ?? '' ) ),
			'event_time'   => sanitize_text_field( (string) ( $data['EventTime'] ?? '' ) ),
			'lgs_no'       => sanitize_text_field( (string) ( $data['LgsNo'] ?? '' ) ),
			'trade_type'   => sanitize_text_field( (string) ( $data['TradeType'] ?? '' ) ),
			'lgs_type'     => sanitize_text_field( (string) ( $data['LgsType'] ?? '' ) ),
			'ship_type'    => sanitize_text_field( (string) ( $data['ShipType'] ?? '' ) ),
		];

		$wpdb->update(
			$label_table,
			[
				'tracking_number'    => '' !== $detail['lgs_no'] ? $detail['lgs_no'] : $label->tracking_number,
				'status'             => '' !== $status ? $status : $label->status,
				'status_detail'      => wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ),
				'status_code'        => $status,
				'status_updated_at'  => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			],
			[ 'id' => (int) $label->id ]
		);

		return YSOrder::find( (int) $label->order_id );
	}

	private static function find_order_by_logistics_no( string $merchant_order_no ): ?object {
		if ( preg_match( '/^YSL(\d+)T\d+$/', $merchant_order_no, $matches ) ) {
			$order = YSOrder::find( (int) $matches[1] );
			if ( $order ) {
				return $order;
			}
		}

		global $wpdb;
		$table = YSOrder::table();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gateway_trade_no = %s OR tracking_number = %s ORDER BY id DESC LIMIT 1",
				$merchant_order_no,
				$merchant_order_no
			)
		) ?: null;
	}

	private static function legacy_status( string $status ): string {
		return match ( $status ) {
			'4' => 'in_transit',
			'5' => 'arrived',
			'6' => 'delivered',
			'-6', '7', '8', '9' => 'returned',
			'0_2', '0_3', '1101', '1103', '1104', '1105' => 'cancelled',
			default => 'processing',
		};
	}
}
