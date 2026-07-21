<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\Utils\YSCrypto;
use YangSheep\Ecommerce\Utils\YSLogger;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayClient {
	private const MPG_TEST_URL           = 'https://ccore.newebpay.com/MPG/mpg_gateway';
	private const MPG_PROD_URL           = 'https://core.newebpay.com/MPG/mpg_gateway';
	private const QUERY_TEST_URL         = 'https://ccore.newebpay.com/API/QueryTradeInfo';
	private const QUERY_PROD_URL         = 'https://core.newebpay.com/API/QueryTradeInfo';
	private const CREDIT_CLOSE_TEST_URL  = 'https://ccore.newebpay.com/API/CreditCard/Close';
	private const CREDIT_CLOSE_PROD_URL  = 'https://core.newebpay.com/API/CreditCard/Close';
	private const EWALLET_REFUND_TEST_URL = 'https://ccore.newebpay.com/API/EWallet/Refund';
	private const EWALLET_REFUND_PROD_URL = 'https://core.newebpay.com/API/EWallet/Refund';

	public const MPG_VERSION = '2.3';

	private string $merchant_id;
	private string $hash_key;
	private string $hash_iv;
	private bool $test_mode;
	private bool $debug_enabled;
	private int $last_http_status = 0;

	public function __construct( array $overrides = [] ) {
		$this->merchant_id   = (string) ( $overrides['merchant_id'] ?? YSNewebpaySettings::get( 'merchant_id', '' ) );
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
		return '' !== $this->merchant_id && '' !== $this->hash_key && '' !== $this->hash_iv;
	}

	/**
	 * @return array<int,string>
	 */
	public function get_missing_settings(): array {
		$missing = [];
		if ( '' === $this->merchant_id ) {
			$missing[] = 'merchant_id';
		}
		if ( '' === $this->hash_key ) {
			$missing[] = 'hash_key';
		}
		if ( '' === $this->hash_iv ) {
			$missing[] = 'hash_iv';
		}

		return $missing;
	}

	public function is_test_mode(): bool {
		return $this->test_mode;
	}

	public function get_last_http_status(): int {
		return $this->last_http_status;
	}

	public function get_mpg_url(): string {
		return $this->test_mode ? self::MPG_TEST_URL : self::MPG_PROD_URL;
	}

	public function get_query_url(): string {
		return $this->test_mode ? self::QUERY_TEST_URL : self::QUERY_PROD_URL;
	}

	/**
	 * @param array<string,mixed> $trade_data
	 * @return array{action_url:string,fields:array<string,string>}
	 */
	public function build_mpg_form( array $trade_data ): array {
		$trade_data['MerchantID']  = $this->merchant_id;
		$trade_data['RespondType'] = 'JSON';
		$trade_data['Version']     = self::MPG_VERSION;
		$trade_data['TimeStamp']   = (string) time();

		$trade_info = $this->encrypt_trade_info( $trade_data );
		$trade_sha  = $this->generate_trade_sha( $trade_info );

		return [
			'action_url' => $this->get_mpg_url(),
			'fields'     => [
				'MerchantID' => $this->merchant_id,
				'TradeInfo'  => $trade_info,
				'TradeSha'   => $trade_sha,
				'Version'    => self::MPG_VERSION,
				'EncryptType'=> '0',
			],
		];
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function encrypt_trade_info( array $data ): string {
		$payload   = http_build_query( $data );
		$encrypted = openssl_encrypt(
			$payload,
			'AES-256-CBC',
			trim( $this->hash_key ),
			OPENSSL_RAW_DATA,
			trim( $this->hash_iv )
		);

		return false !== $encrypted ? bin2hex( $encrypted ) : '';
	}

	public function decrypt_trade_info( string $trade_info ): ?string {
		$binary = @hex2bin( trim( $trade_info ) );
		if ( false === $binary ) {
			return null;
		}

		$decrypted = openssl_decrypt(
			$binary,
			'AES-256-CBC',
			trim( $this->hash_key ),
			OPENSSL_RAW_DATA,
			trim( $this->hash_iv )
		);

		return false !== $decrypted ? $decrypted : null;
	}

	public function generate_trade_sha( string $trade_info ): string {
		return strtoupper( hash( 'sha256', 'HashKey=' . $this->hash_key . '&' . $trade_info . '&HashIV=' . $this->hash_iv ) );
	}

	public function verify_trade_sha( string $trade_info, string $trade_sha ): bool {
		if ( '' === $trade_info || '' === $trade_sha || '' === $this->hash_key || '' === $this->hash_iv ) {
			return false;
		}

		return hash_equals( $this->generate_trade_sha( $trade_info ), strtoupper( trim( $trade_sha ) ) );
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>|null
	 */
	public function verify_and_decode_callback( array $params ): ?array {
		$trade_info = (string) ( $params['TradeInfo'] ?? '' );
		$trade_sha  = (string) ( $params['TradeSha'] ?? '' );

		if ( ! $this->verify_trade_sha( $trade_info, $trade_sha ) ) {
			return null;
		}

		$decrypted = $this->decrypt_trade_info( $trade_info );
		if ( null === $decrypted ) {
			return null;
		}

		$decoded = self::decode_payload( $decrypted );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$decoded['_raw_decrypted'] = $this->debug_enabled ? $decrypted : '';
		$decoded['_trade_sha']     = $trade_sha;

		if ( ! $this->verify_optional_check_code( $decoded ) ) {
			YSLogger::warning( 'newebpay', 'CheckCode verification failed', [
				'merchant_order_no' => self::extract_result_value( $decoded, 'MerchantOrderNo' ),
			] );
			return null;
		}

		return $decoded;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function decode_payload( string $payload ): ?array {
		$payload = trim( $payload );
		if ( '' === $payload ) {
			return null;
		}

		$json = json_decode( $payload, true );
		if ( is_array( $json ) ) {
			if ( isset( $json['Result'] ) && is_string( $json['Result'] ) ) {
				$result_json = json_decode( $json['Result'], true );
				if ( is_array( $result_json ) ) {
					$json['Result'] = $result_json;
				}
			}
			return $json;
		}

		$query = [];
		parse_str( $payload, $query );
		return is_array( $query ) && ! empty( $query ) ? $query : null;
	}

	public static function extract_result_value( array $payload, string $key, string $fallback = '' ): string {
		if ( isset( $payload['Result'] ) && is_array( $payload['Result'] ) && array_key_exists( $key, $payload['Result'] ) ) {
			return (string) $payload['Result'][ $key ];
		}
		if ( array_key_exists( $key, $payload ) ) {
			return (string) $payload[ $key ];
		}

		return $fallback;
	}

	public function generate_query_check_value( string $merchant_order_no, int $amount ): string {
		$input = 'IV=' . $this->hash_iv
			. '&Amt=' . $amount
			. '&MerchantID=' . $this->merchant_id
			. '&MerchantOrderNo=' . $merchant_order_no
			. '&Key=' . $this->hash_key;

		return strtoupper( hash( 'sha256', $input ) );
	}

	/**
	 * @return array{success:bool,data:array<string,mixed>|null,message:string}
	 */
	public function query_trade( string $merchant_order_no, int $amount ): array {
		$params = [
			'MerchantID'      => $this->merchant_id,
			'Version'         => '1.3',
			'RespondType'     => 'JSON',
			'CheckValue'      => $this->generate_query_check_value( $merchant_order_no, $amount ),
			'TimeStamp'       => (string) time(),
			'MerchantOrderNo' => $merchant_order_no,
			'Amt'             => (string) $amount,
		];

		return $this->post_form( $this->get_query_url(), $params, 'query_trade' );
	}

	/**
	 * @return array{success:bool,data:array<string,mixed>|null,message:string}
	 */
	public function refund_credit_card( string $merchant_order_no, int $amount ): array {
		$data = [
			'RespondType'     => 'JSON',
			'Version'         => '1.1',
			'Amt'             => (string) $amount,
			'MerchantOrderNo' => $merchant_order_no,
			'TimeStamp'       => (string) time(),
			'IndexType'       => '1',
			'CloseType'       => '2',
		];

		return $this->post_encrypted_api(
			$this->test_mode ? self::CREDIT_CLOSE_TEST_URL : self::CREDIT_CLOSE_PROD_URL,
			$data,
			'refund_credit_card'
		);
	}

	/**
	 * @return array{success:bool,data:array<string,mixed>|null,message:string}
	 */
	public function refund_ewallet( string $trade_no, string $merchant_order_no, int $amount ): array {
		$data = [
			'RespondType'     => 'JSON',
			'Version'         => '1.0',
			'TimeStamp'       => (string) time(),
			'TradeNo'         => $trade_no,
			'MerchantOrderNo' => $merchant_order_no,
			'Amt'             => (string) $amount,
		];

		return $this->post_encrypted_api(
			$this->test_mode ? self::EWALLET_REFUND_TEST_URL : self::EWALLET_REFUND_PROD_URL,
			$data,
			'refund_ewallet'
		);
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array{success:bool,data:array<string,mixed>|null,message:string}
	 */
	private function post_encrypted_api( string $url, array $data, string $context ): array {
		$post_data = $this->encrypt_trade_info( $data );
		if ( '' === $post_data ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => 'Unable to encrypt NewebPay request.',
			];
		}

		return $this->post_form(
			$url,
			[
				'MerchantID_' => $this->merchant_id,
				'PostData_'   => $post_data,
			],
			$context
		);
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array{success:bool,data:array<string,mixed>|null,message:string}
	 */
	private function post_form( string $url, array $params, string $context ): array {
		if ( ! $this->is_configured() ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => 'NewebPay settings are incomplete.',
			];
		}

		$response = wp_remote_post(
			$url,
			[
				'timeout'     => 30,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => [ 'Content-Type' => 'application/x-www-form-urlencoded' ],
				'body'        => http_build_query( $params ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->last_http_status = 0;
			YSLogger::error( 'newebpay', $context . ' HTTP error', [ 'message' => $response->get_error_message() ] );
			// R7-F1：連線層失敗（timeout 等）＝結果不明——退款 caller 據此凍結而非重送。
			return [
				'success'       => false,
				'indeterminate' => true,
				'data'          => null,
				'message'       => $response->get_error_message(),
			];
		}

		$this->last_http_status = (int) wp_remote_retrieve_response_code( $response );
		$raw                    = (string) wp_remote_retrieve_body( $response );
		$data                   = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			parse_str( $raw, $parsed );
			$data = is_array( $parsed ) ? $parsed : [];
		}

		$http_ok = ( $this->last_http_status >= 200 && $this->last_http_status < 300 );
		$status  = strtoupper( (string) ( $data['Status'] ?? '' ) );
		if ( ! $http_ok ) {
			// R7-F1：HTTP 非 2xx＝伺服器層不確定（server 可能已收並處理）→ indeterminate。
			return [
				'success'       => false,
				'indeterminate' => true,
				'data'          => $data,
				'message'       => (string) ( $data['Message'] ?? 'NewebPay API request failed.' ),
			];
		}
		if ( 'SUCCESS' !== $status ) {
			// R7-F1：HTTP 2xx 但業務碼非 SUCCESS＝provider 明確拒絕（terminal，可重試）。
			return [
				'success'       => false,
				'indeterminate' => false,
				'data'          => $data,
				'message'       => (string) ( $data['Message'] ?? 'NewebPay API request failed.' ),
			];
		}

		return [
			'success' => true,
			'data'    => $data,
			'message' => '',
		];
	}

	private function verify_optional_check_code( array $payload ): bool {
		$check_code = self::extract_result_value( $payload, 'CheckCode' );
		if ( '' === $check_code ) {
			return true;
		}

		$fields = [
			'Amt'             => self::extract_result_value( $payload, 'Amt' ),
			'MerchantID'      => self::extract_result_value( $payload, 'MerchantID' ),
			'MerchantOrderNo' => self::extract_result_value( $payload, 'MerchantOrderNo' ),
			'TradeNo'         => self::extract_result_value( $payload, 'TradeNo' ),
		];
		ksort( $fields );

		$input = 'HashIV=' . $this->hash_iv . '&' . http_build_query( $fields ) . '&HashKey=' . $this->hash_key;
		$hash  = strtoupper( hash( 'sha256', $input ) );

		return hash_equals( $hash, strtoupper( $check_code ) );
	}
}
