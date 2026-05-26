<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;
use YangSheep\YSCartNewebpay\Logistics\Newebpay\YSNewebpayLogisticsClient;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayStoreSelector {
	private const METHOD_MAP = [
		'ys_ec_newebpay_ship_711_c2c'    => [ 'C2C', '1' ],
		'ys_ec_newebpay_ship_family_c2c' => [ 'C2C', '2' ],
		'ys_ec_newebpay_ship_hilife_c2c' => [ 'C2C', '3' ],
		'ys_ec_newebpay_ship_ok_c2c'     => [ 'C2C', '4' ],
		'ys_ec_newebpay_ship_711_b2c'    => [ 'B2C', '1' ],
	];

	/**
	 * @return array{action_url:string,fields:array<string,string>,temp_id:string}|false
	 */
	public static function build_map_form_data( string $shipping_id ) {
		$shipping_id = sanitize_key( $shipping_id );
		if ( '' === $shipping_id || ! isset( self::METHOD_MAP[ $shipping_id ] ) ) {
			return false;
		}

		if ( ! YSNewebpaySettings::is_logistics_method_enabled( $shipping_id ) ) {
			return false;
		}

		[ $lgs_type, $ship_type ] = self::METHOD_MAP[ $shipping_id ];
		$temp_id                 = wp_generate_uuid4();
		$merchant_order_no       = substr( 'YSMAP' . str_replace( '-', '', $temp_id ), 0, 30 );

		set_transient(
			'ys_ec_newebpay_map_' . $temp_id,
			[
				'shipping_id'       => $shipping_id,
				'merchant_order_no' => $merchant_order_no,
				'lgs_type'          => $lgs_type,
				'ship_type'         => $ship_type,
				'user_id'           => get_current_user_id(),
				'created_at'        => current_time( 'timestamp' ),
			],
			30 * MINUTE_IN_SECONDS
		);

		$client = new YSNewebpayLogisticsClient();
		if ( ! $client->is_configured() ) {
			return false;
		}

		$form = $client->build_store_map_form(
			[
				'MerchantOrderNo' => $merchant_order_no,
				'LgsType'         => $lgs_type,
				'ShipType'        => $ship_type,
				'ReturnURL'       => rest_url( 'ys-ecommerce/v1/newebpay/store-callback' ),
				'TimeStamp'       => (string) time(),
				'ExtraData'       => $temp_id,
			]
		);

		$form['temp_id'] = $temp_id;

		return $form;
	}

	public static function handle_store_callback( \WP_REST_Request $request ): void {
		$params = $request->get_params();
		$client = new YSNewebpayLogisticsClient();
		$data   = $client->verify_and_decode_callback( $params );

		if ( null === $data ) {
			YSLogger::error( 'newebpay_store', 'Store callback verification failed.' );
			wp_die( 'Invalid store callback.', 'NewebPay Store Callback', [ 'response' => 400 ] );
		}

		$temp_id           = sanitize_text_field( (string) ( $data['ExtraData'] ?? '' ) );
		$merchant_order_no = sanitize_text_field( (string) ( $data['MerchantOrderNo'] ?? '' ) );

		$map_data = '' !== $temp_id ? get_transient( 'ys_ec_newebpay_map_' . $temp_id ) : false;
		if ( ! is_array( $map_data ) && '' !== $merchant_order_no ) {
			$map_data = self::find_map_data_by_order_no( $merchant_order_no );
		}

		if ( ! is_array( $map_data ) ) {
			YSLogger::error( 'newebpay_store', 'Store callback transient not found.', [ 'temp_id' => $temp_id ] );
			wp_die( 'Store selection expired.', 'NewebPay Store Callback', [ 'response' => 400 ] );
		}

		$shipping_id = sanitize_key( (string) ( $map_data['shipping_id'] ?? '' ) );
		if ( '' === $shipping_id || ! YSNewebpaySettings::is_logistics_method_enabled( $shipping_id ) ) {
			YSLogger::warning( 'newebpay_store', 'Store callback rejected because shipping method is disabled.', [ 'shipping_id' => $shipping_id ] );
			wp_die( 'Shipping method disabled.', 'NewebPay Store Callback', [ 'response' => 403 ] );
		}

		$store_info = [
			'provider' => 'newebpay',
			'store_id'      => sanitize_text_field( (string) ( $data['StoreID'] ?? '' ) ),
			'store_name'    => sanitize_text_field( (string) ( $data['StoreName'] ?? '' ) ),
			'store_address' => sanitize_text_field( (string) ( $data['StoreAddr'] ?? '' ) ),
			'store_phone'   => sanitize_text_field( (string) ( $data['StoreTel'] ?? '' ) ),
			'lgs_type'      => sanitize_text_field( (string) ( $data['LgsType'] ?? $map_data['lgs_type'] ?? '' ) ),
			'ship_type'     => sanitize_text_field( (string) ( $data['ShipType'] ?? $map_data['ship_type'] ?? '' ) ),
			'shipping_id'   => $shipping_id,
			'selected_at'   => current_time( 'mysql' ),
		];

		if ( '' !== $temp_id ) {
			set_transient( 'ys_ec_newebpay_store_' . $temp_id, $store_info, 30 * MINUTE_IN_SECONDS );
			delete_transient( 'ys_ec_newebpay_map_' . $temp_id );
		}

		self::render_callback_page( $store_info );
	}

	/**
	 * @return array<string,mixed>|false
	 */
	private static function find_map_data_by_order_no( string $merchant_order_no ) {
		global $wpdb;

		$merchant_order_no = sanitize_text_field( $merchant_order_no );
		if ( '' === $merchant_order_no || ! isset( $wpdb ) ) {
			return false;
		}

		$like = $wpdb->esc_like( '_transient_ys_ec_newebpay_map_' ) . '%';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100",
				$like
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$map_data = maybe_unserialize( $row['option_value'] ?? '' );
			if ( is_array( $map_data ) && $merchant_order_no === (string) ( $map_data['merchant_order_no'] ?? '' ) ) {
				return $map_data;
			}
		}

		return false;
	}

	/**
	 * @param array<string,string> $store_info
	 */
	private static function render_callback_page( array $store_info ): void {
		$json_data = wp_json_encode( $store_info, JSON_UNESCAPED_UNICODE );
		?>
		<!doctype html>
		<html>
		<head><meta charset="UTF-8"><title>NewebPay store selected</title></head>
		<body>
		<script>
		(function() {
			var storeData = <?php echo $json_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			storeData._timestamp = Date.now();
			var serialized = JSON.stringify(storeData);
			try {
				localStorage.setItem('ys_ec_selected_store', serialized);
			} catch (e) {
				try { sessionStorage.setItem('ys_ec_selected_store', serialized); } catch (e2) {}
			}
			if (window.opener) {
				window.opener.postMessage({
					type: 'ys_ec_store_selected',
					action: 'ys_ec_store_selected',
					provider: 'newebpay',
					data: storeData,
					store_id: storeData.store_id,
					store_name: storeData.store_name,
					store_address: storeData.store_address
				}, '<?php echo esc_url( home_url() ); ?>');
				window.close();
			}
		})();
		</script>
		</body>
		</html>
		<?php
		exit;
	}
}
