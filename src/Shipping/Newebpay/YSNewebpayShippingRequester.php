<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\Ecommerce\Models\YSOrder;
use YangSheep\YSCartNewebpay\Logistics\Newebpay\YSNewebpayLogisticsClient;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayShippingRequester {
	private YSNewebpayShipping $shipping;
	private YSNewebpayLogisticsClient $client;

	public function __construct( YSNewebpayShipping $shipping, ?YSNewebpayLogisticsClient $client = null ) {
		$this->shipping = $shipping;
		$this->client   = $client ?: new YSNewebpayLogisticsClient();
	}

	/**
	 * @param array<string,mixed> $order_data
	 * @return array<string,mixed>
	 */
	public function create_order( array $order_data ): array {
		$merchant_order_no = $this->merchant_order_no( (int) ( $order_data['order_id'] ?? 0 ) );
		$is_cod            = $this->is_cod_order( $order_data );
		$params            = [
			'MerchantOrderNo' => $merchant_order_no,
			'TradeType'       => $is_cod ? '1' : $this->shipping->get_trade_type(),
			'UserName'        => mb_substr( sanitize_text_field( (string) ( $order_data['receiver_name'] ?? '' ) ), 0, 20 ),
			'UserTel'         => preg_replace( '/[^0-9]/', '', (string) ( $order_data['receiver_phone'] ?? '' ) ),
			'UserEmail'       => $this->resolve_email( $order_data ),
			'StoreID'         => sanitize_text_field( (string) ( $order_data['receiver_store_id'] ?? '' ) ),
			'Amt'             => (string) max( 0, absint( $order_data['product_amount'] ?? 0 ) ),
			'NotifyURL'       => rest_url( 'ys-ecommerce/v1/newebpay/shipping-notify' ),
			'ItemDesc'        => mb_substr( sanitize_text_field( (string) ( $order_data['product_name'] ?? 'YS CART Order' ) ), 0, 50 ),
			'LgsType'         => $this->shipping->get_lgs_type(),
			'ShipType'        => $this->shipping->get_ship_type(),
			'TimeStamp'       => (string) time(),
		];

		if ( '' === $params['StoreID'] ) {
			return [
				'success' => false,
				'message' => 'NewebPay shipment requires selected CVS store.',
			];
		}

		$response = $this->client->create_shipment( $params );
		if ( empty( $response['success'] ) ) {
			return [
				'success' => false,
				'message' => (string) ( $response['message'] ?? 'NewebPay shipment create failed.' ),
				'raw'     => $response,
			];
		}

		$data       = is_array( $response['data'] ?? null ) ? $response['data'] : [];
		$trade_no   = sanitize_text_field( (string) ( $data['TradeNo'] ?? $data['LgsNo'] ?? $merchant_order_no ) );
		$tracking   = sanitize_text_field( (string) ( $data['LgsNo'] ?? $data['StorePrintNo'] ?? $trade_no ) );
		$status     = sanitize_text_field( (string) ( $data['Status'] ?? $response['status'] ?? 'created' ) );

		YSLogger::info(
			'newebpay_shipping',
			'Shipment created',
			[
				'merchant_order_no' => $merchant_order_no,
				'trade_no'          => $trade_no,
				'lgs_type'          => $this->shipping->get_lgs_type(),
				'ship_type'         => $this->shipping->get_ship_type(),
			]
		);

		return [
			'success'           => true,
			'label_id'          => $merchant_order_no,
			'tracking_no'       => $tracking,
			'status'            => '' !== $status ? $status : 'created',
			'provider_trade_no' => $trade_no,
			'raw'               => $data,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function query_status( string $label_id ): array {
		$response = $this->client->query_shipment(
			[
				'MerchantOrderNo' => sanitize_text_field( $label_id ),
				'TimeStamp'       => (string) time(),
			]
		);

		if ( empty( $response['success'] ) ) {
			return [
				'success' => false,
				'message' => (string) ( $response['message'] ?? 'NewebPay shipment query failed.' ),
				'raw'     => $response,
			];
		}

		$data   = is_array( $response['data'] ?? null ) ? $response['data'] : [];
		$status = sanitize_text_field( (string) ( $data['RetId'] ?? $data['Status'] ?? $response['status'] ?? '' ) );

		return [
			'success'     => true,
			'status'      => $status,
			'status_text' => sanitize_text_field( (string) ( $data['RetString'] ?? $response['message'] ?? '' ) ),
			'tracking_no' => sanitize_text_field( (string) ( $data['LgsNo'] ?? $data['StorePrintNo'] ?? '' ) ),
			'update_time' => sanitize_text_field( (string) ( $data['EventTime'] ?? current_time( 'mysql' ) ) ),
			'data'        => $data,
			'raw'         => $data,
		];
	}

	public function cancel_order( string $label_id ): bool {
		unset( $label_id );
		return false;
	}

	public function get_print_url( string|array $label_ids ): string {
		$ids = is_array( $label_ids ) ? $label_ids : [ $label_ids ];
		$ids = array_values(
			array_filter(
				array_map(
					static fn( $id ): string => sanitize_text_field( (string) $id ),
					$ids
				)
			)
		);

		if ( empty( $ids ) ) {
			return '';
		}

		$form = $this->client->build_print_label_form(
			[
				'LgsType'         => $this->shipping->get_lgs_type(),
				'ShipType'        => $this->shipping->get_ship_type(),
				'MerchantOrderNo' => wp_json_encode( $ids ),
				'TimeStamp'       => (string) time(),
			]
		);

		$print_key = 'ys_ec_print_' . wp_generate_password( 12, false );
		set_transient(
			$print_key,
			[
				'api_url' => $form['action_url'],
				'fields'  => $form['fields'],
			],
			300
		);

		return $print_key;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function modify_order( string $label_id, array $data ): array {
		$response = $this->client->modify_shipment(
			[
				'MerchantOrderNo' => sanitize_text_field( $label_id ),
				'LgsType'         => $this->shipping->get_lgs_type(),
				'ShipType'        => $this->shipping->get_ship_type(),
				'UserName'        => mb_substr( sanitize_text_field( (string) ( $data['receiver_name'] ?? '' ) ), 0, 20 ),
				'UserTel'         => preg_replace( '/[^0-9]/', '', (string) ( $data['receiver_phone'] ?? '' ) ),
				'UserEmail'       => sanitize_email( (string) ( $data['receiver_email'] ?? '' ) ),
				'StoreID'         => sanitize_text_field( (string) ( $data['receiver_store_id'] ?? '' ) ),
				'TimeStamp'       => (string) time(),
			]
		);

		return [
			'success' => ! empty( $response['success'] ),
			'message' => (string) ( $response['message'] ?? '' ),
			'data'    => $response['data'] ?? null,
			'raw'     => $response,
		];
	}

	/**
	 * @param array<int,string> $merchant_order_nos
	 * @return array<string,mixed>
	 */
	public function get_shipment_no( array $merchant_order_nos ): array {
		$merchant_order_nos = array_slice(
			array_values(
				array_filter(
					array_map(
						static fn( $id ): string => sanitize_text_field( (string) $id ),
						$merchant_order_nos
					)
				)
			),
			0,
			10
		);

		$response = $this->client->get_shipment_no(
			[
				'MerchantOrderNo' => wp_json_encode( $merchant_order_nos ),
				'TimeStamp'       => (string) time(),
			]
		);

		return [
			'success' => ! empty( $response['success'] ),
			'message' => (string) ( $response['message'] ?? '' ),
			'data'    => $response['data'] ?? null,
			'raw'     => $response,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public function trace( string $label_id ): array {
		$response = $this->client->trace(
			[
				'MerchantOrderNo' => sanitize_text_field( $label_id ),
				'TimeStamp'       => (string) time(),
			]
		);

		return [
			'success' => ! empty( $response['success'] ),
			'message' => (string) ( $response['message'] ?? '' ),
			'data'    => $response['data'] ?? null,
			'raw'     => $response,
		];
	}

	/**
	 * @param array<string,mixed> $order_data
	 */
	private function resolve_email( array $order_data ): string {
		$email = sanitize_email( (string) ( $order_data['receiver_email'] ?? $order_data['email'] ?? '' ) );
		if ( is_email( $email ) ) {
			return $email;
		}

		return sanitize_email( (string) get_option( 'admin_email', '' ) );
	}

	private function merchant_order_no( int $order_id ): string {
		return substr( 'YSL' . $order_id . 'T' . time(), 0, 30 );
	}

	/**
	 * @param array<string,mixed> $order_data
	 */
	private function is_cod_order( array $order_data ): bool {
		if ( ! empty( $order_data['is_cod'] ) ) {
			return true;
		}

		$order_id = (int) ( $order_data['order_id'] ?? 0 );
		if ( $order_id <= 0 || ! class_exists( YSOrder::class ) ) {
			return false;
		}

		$order = YSOrder::find( $order_id );
		if ( ! $order ) {
			return false;
		}

		return in_array( (string) ( $order->payment_method ?? $order->gateway_id ?? '' ), [ 'ys_ec_cod', 'cod' ], true );
	}
}
