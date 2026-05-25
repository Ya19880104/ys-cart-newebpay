<?php

namespace YangSheep\YSCartNewebpay\Logistics\Newebpay;

use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayLogisticsClient {
	private const TEST_BASE_URL = 'https://ccore.newebpay.com/API/Logistic/';
	private const PROD_BASE_URL = 'https://core.newebpay.com/API/Logistic/';

	public const API_VERSION = '1.0';

	private string $uid;
	private string $hash_key;
	private string $hash_iv;
	private bool $test_mode;
	private bool $debug_enabled;
	private int $last_http_status = 0;

	/**
	 * @param array<string,mixed> $overrides
	 */
	public function __construct( array $overrides = [] ) {
		$this->uid           = (string) ( $overrides['uid'] ?? $overrides['merchant_id'] ?? YSNewebpaySettings::get( 'merchant_id', '' ) );
		$this->hash_key      = $this->decrypt_secret( (string) ( $overrides['hash_key'] ?? YSNewebpaySettings::get( 'hash_key', '' ) ) );
		$this->hash_iv       = $this->decrypt_secret( (string) ( $overrides['hash_iv'] ?? YSNewebpaySettings::get( 'hash_iv', '' ) ) );
		$this->test_mode     = '1' === (string) ( $overrides['test_mode'] ?? YSNewebpaySettings::get( 'test_mode', '1' ) );
		$this->debug_enabled = '1' === (string) ( $overrides['debug_enabled'] ?? YSNewebpaySettings::get( 'debug_enabled', '0' ) );
	}

	private function decrypt_secret( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}

		$decrypted = YSCrypto::decrypt_from_storage( $stored );
		return '' !== $decrypted ? $decrypted : $stored;
	}

	public function is_configured(): bool {
		return '' !== $this->uid && '' !== $this->hash_key && '' !== $this->hash_iv;
	}

	public function get_base_url(): string {
		return $this->test_mode ? self::TEST_BASE_URL : self::PROD_BASE_URL;
	}

	public function get_endpoint_url( string $api ): string {
		return $this->get_base_url() . ltrim( $api, '/' );
	}

	public function get_last_http_status(): int {
		return $this->last_http_status;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{action_url:string,fields:array<string,string>}
	 */
	public function build_store_map_form( array $payload ): array {
		return $this->build_foreground_form( 'storeMap', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{action_url:string,fields:array<string,string>}
	 */
	public function build_print_label_form( array $payload ): array {
		return $this->build_foreground_form( 'printLabel', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{action_url:string,fields:array<string,string>}
	 */
	public function build_foreground_form( string $api, array $payload ): array {
		return [
			'action_url' => $this->get_endpoint_url( $api ),
			'fields'     => $this->build_envelope( $payload ),
		];
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,string>
	 */
	public function build_envelope( array $payload ): array {
		$encrypt_data = $this->encrypt_payload( $payload );

		return [
			'UID_'          => $this->uid,
			'EncryptData_'  => $encrypt_data,
			'HashData_'     => $this->generate_hash_data( $encrypt_data ),
			'Version_'      => self::API_VERSION,
			'RespondType_'  => 'JSON',
		];
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	public function encrypt_payload( array $payload ): string {
		ksort( $payload );
		$query     = http_build_query( $payload );
		$encrypted = openssl_encrypt(
			$this->add_padding( $query ),
			'aes-256-cbc',
			trim( $this->hash_key ),
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
			trim( $this->hash_iv )
		);

		return false !== $encrypted ? trim( bin2hex( $encrypted ) ) : '';
	}

	public function decrypt_payload( string $encrypt_data ): ?array {
		$binary = @hex2bin( trim( $encrypt_data ) );
		if ( false === $binary ) {
			return null;
		}

		$decrypted = openssl_decrypt(
			$binary,
			'aes-256-cbc',
			trim( $this->hash_key ),
			OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
			trim( $this->hash_iv )
		);

		if ( false === $decrypted || '' === $decrypted ) {
			return null;
		}

		$decrypted = $this->strip_padding( $decrypted );
		if ( false === $decrypted || '' === $decrypted ) {
			return null;
		}

		$json = json_decode( $decrypted, true );
		if ( is_array( $json ) ) {
			return $json;
		}

		$query = [];
		parse_str( $decrypted, $query );

		return ! empty( $query ) ? $query : null;
	}

	private function add_padding( string $value, int $block_size = 32 ): string {
		$pad = $block_size - ( strlen( $value ) % $block_size );
		return $value . str_repeat( chr( $pad ), $pad );
	}

	/**
	 * @return string|false
	 */
	private function strip_padding( string $value ) {
		$last = ord( substr( $value, -1 ) );
		if ( $last < 1 || $last > 32 ) {
			return false;
		}

		if ( substr( $value, -1 * $last ) !== str_repeat( chr( $last ), $last ) ) {
			return false;
		}

		return substr( $value, 0, strlen( $value ) - $last );
	}

	public function generate_hash_data( string $encrypt_data ): string {
		return strtoupper( hash( 'sha256', 'HashKey=' . $this->hash_key . '&' . $encrypt_data . '&HashIV=' . $this->hash_iv ) );
	}

	public function verify_hash_data( string $encrypt_data, string $hash_data ): bool {
		if ( '' === $encrypt_data || '' === $hash_data || '' === $this->hash_key || '' === $this->hash_iv ) {
			return false;
		}

		return hash_equals( $this->generate_hash_data( $encrypt_data ), strtoupper( trim( $hash_data ) ) );
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|null
	 */
	public function verify_and_decode_callback( array $params ): ?array {
		$encrypt_data = (string) ( $params['EncryptData_'] ?? $params['EncryptData'] ?? '' );
		$hash_data    = (string) ( $params['HashData_'] ?? $params['HashData'] ?? '' );

		if ( ! $this->verify_hash_data( $encrypt_data, $hash_data ) ) {
			return null;
		}

		$decoded = $this->decrypt_payload( $encrypt_data );
		if ( null === $decoded ) {
			return null;
		}

		if ( $this->debug_enabled ) {
			$decoded['_hash_data'] = $hash_data;
		}

		return $decoded;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function store_map( array $payload ): array {
		return $this->post_logistics( 'storeMap', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function create_shipment( array $payload ): array {
		return $this->post_logistics( 'createShipment', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function get_shipment_no( array $payload ): array {
		return $this->post_logistics( 'getShipmentNo', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function print_label( array $payload ): array {
		return $this->post_logistics( 'printLabel', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function query_shipment( array $payload ): array {
		return $this->post_logistics( 'queryShipment', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function modify_shipment( array $payload ): array {
		return $this->post_logistics( 'modifyShipment', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	public function trace( array $payload ): array {
		return $this->post_logistics( 'trace', $payload );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,data:array<string,mixed>|null,message:string,status:string,raw:array<string,mixed>|null}
	 */
	private function post_logistics( string $api, array $payload ): array {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => 'NewebPay logistics settings are incomplete.',
				'status'  => 'CONFIG_ERROR',
				'raw'     => null,
			];
		}

		$response = wp_remote_post(
			$this->get_endpoint_url( $api ),
			[
				'timeout'     => 30,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'        => http_build_query( $this->build_envelope( $payload ) ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->last_http_status = 0;
			YSLogger::error( 'newebpay_logistics', $api . ' HTTP error', [ 'message' => $response->get_error_message() ] );

			return [
				'success' => false,
				'data'    => null,
				'message' => $response->get_error_message(),
				'status'  => 'HTTP_ERROR',
				'raw'     => null,
			];
		}

		$this->last_http_status = (int) wp_remote_retrieve_response_code( $response );
		$raw_body               = (string) wp_remote_retrieve_body( $response );
		$raw                    = json_decode( $raw_body, true );

		if ( ! is_array( $raw ) ) {
			parse_str( $raw_body, $parsed );
			$raw = is_array( $parsed ) ? $parsed : [];
		}

		$status  = strtoupper( (string) ( $raw['Status'] ?? '' ) );
		$message = (string) ( $raw['Message'] ?? '' );
		$data    = null;

		$encrypt_data = (string) ( $raw['EncryptData_'] ?? $raw['EncryptData'] ?? '' );
		$hash_data    = (string) ( $raw['HashData_'] ?? $raw['HashData'] ?? '' );
		if ( '' !== $encrypt_data && '' !== $hash_data ) {
			$data = $this->verify_and_decode_callback(
				[
					'EncryptData_' => $encrypt_data,
					'HashData_'    => $hash_data,
				]
			);
		}

		if ( null === $data && isset( $raw['Result'] ) && is_array( $raw['Result'] ) ) {
			$data = $raw['Result'];
		}

		$success = $this->last_http_status >= 200
			&& $this->last_http_status < 300
			&& in_array( $status, [ 'SUCCESS', 'OK' ], true );

		return [
			'success' => $success,
			'data'    => $data,
			'message' => $success ? '' : ( '' !== $message ? $message : 'NewebPay logistics API request failed.' ),
			'status'  => '' !== $status ? $status : ( $success ? 'SUCCESS' : 'FAILED' ),
			'raw'     => $raw,
		];
	}
}
