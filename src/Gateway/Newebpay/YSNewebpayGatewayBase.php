<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\Gateways\YSGatewayInterface;
use YangSheep\Ecommerce\Models\YSOrder;
use YangSheep\Ecommerce\Utils\YSLogger;
use YangSheep\Ecommerce\Utils\YSPriceHelper;

defined( 'ABSPATH' ) || exit;

abstract class YSNewebpayGatewayBase implements YSGatewayInterface {
	protected const REFUND_HISTORY_KEY = '_ys_newebpay_refunds';

	protected string $gateway_id;
	protected string $method_key;
	protected string $title;
	protected string $description;
	protected string $icon = 'dashicons-money-alt';
	protected int $method_max_amount = 0;

	private ?YSNewebpayClient $client_instance = null;

	/**
	 * @return array<string,mixed>
	 */
	abstract protected function get_mpg_options(): array;

	public function get_id(): string {
		return $this->gateway_id;
	}

	public function get_title(): string {
		return $this->title;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function get_icon(): string {
		return $this->icon;
	}

	public function is_enabled(): bool {
		return YSNewebpaySettings::is_method_enabled( $this->method_key );
	}

	public function is_available( array $order_data ): bool {
		if ( ! $this->is_enabled() || ! $this->get_client()->is_configured() ) {
			return false;
		}

		$total = (float) ( $order_data['total'] ?? $order_data['order_total'] ?? 0 );
		if ( $total <= 0 ) {
			return true;
		}

		$min = $this->get_min_amount();
		$max = $this->get_max_amount();

		if ( $min > 0 && $total < $min ) {
			return false;
		}
		if ( $max > 0 && $total > $max ) {
			return false;
		}

		return true;
	}

	public function get_min_amount(): float {
		return 1.0;
	}

	public function get_max_amount(): float {
		$global_limit = (int) YSNewebpaySettings::get( 'trade_limit', '0' );
		if ( $global_limit > 0 && $this->method_max_amount > 0 ) {
			return (float) min( $global_limit, $this->method_max_amount );
		}
		if ( $global_limit > 0 ) {
			return (float) $global_limit;
		}

		return (float) $this->method_max_amount;
	}

	public function supports_token(): bool {
		return false;
	}

	public function process_token_charge( int $subscription_id, float $override_amount = 0.0 ): array {
		unset( $subscription_id, $override_amount );

		return [
			'success' => false,
			'message' => 'NewebPay token charging is not supported by this provider.',
		];
	}

	public function get_settings_fields(): array {
		return [];
	}

	public function process_payment( int $order_id ): array {
		$order = YSOrder::find( $order_id );
		if ( ! $order ) {
			return [
				'success' => false,
				'message' => 'Order not found.',
			];
		}

		$client = $this->get_client();
		if ( ! $client->is_configured() ) {
			return [
				'success' => false,
				'message' => 'NewebPay settings are incomplete.',
			];
		}

		$amount = (int) round( YSPriceHelper::round( (float) ( $order->total ?? 0 ) ) );
		if ( $amount <= 0 ) {
			return [
				'success' => false,
				'message' => 'Order amount is invalid.',
			];
		}

		$max_amount = $this->get_max_amount();
		if ( $max_amount > 0 && $amount > $max_amount ) {
			return [
				'success' => false,
				'message' => sprintf( 'This NewebPay method supports orders up to NT$%d.', (int) $max_amount ),
			];
		}

		if ( method_exists( YSOrder::class, 'issue_tks_token' ) ) {
			YSOrder::issue_tks_token( $order_id );
		}

		$merchant_order_no = $this->build_merchant_order_no( $order_id );
		$trade_data        = array_merge(
			[
				'MerchantOrderNo' => $merchant_order_no,
				'Amt'             => (string) $amount,
				'ItemDesc'        => $this->build_item_desc( $order ),
				'Email'           => $this->extract_email( $order ),
				'ReturnURL'       => rest_url( 'ys-ecommerce/v1/newebpay/return' ),
				'NotifyURL'       => rest_url( 'ys-ecommerce/v1/newebpay/notify' ),
				'ClientBackURL'   => $this->thankyou_url( $order ),
			],
			$this->get_mpg_options()
		);

		$form_data = $client->build_mpg_form( $trade_data );
		if ( empty( $form_data['fields']['TradeInfo'] ) || empty( $form_data['fields']['TradeSha'] ) ) {
			return [
				'success' => false,
				'message' => 'Unable to build NewebPay payment form.',
			];
		}

		$payment_detail = json_decode( (string) ( $order->payment_detail ?? '{}' ), true );
		if ( ! is_array( $payment_detail ) ) {
			$payment_detail = [];
		}

		$payment_detail['newebpay_merchant_order_no'] = $merchant_order_no;
		$payment_detail['newebpay_method']            = $this->method_key;
		$payment_detail['newebpay_test_mode']         = $client->is_test_mode() ? '1' : '0';
		$payment_detail['newebpay_form_created_at']   = current_time( 'mysql' );
		$payment_detail['mer_trade_no']               = $merchant_order_no;

		YSOrder::update(
			$order_id,
			[
				'gateway_id'       => $this->get_id(),
				'payment_method'   => $this->get_id(),
				'gateway_trade_no' => $merchant_order_no,
				'payment_detail'   => wp_json_encode( $payment_detail ),
			]
		);

		YSLogger::info(
			'newebpay',
			'MPG form created',
			[
				'order_id'          => $order_id,
				'merchant_order_no' => $merchant_order_no,
				'method'            => $this->method_key,
				'amount'            => $amount,
				'test_mode'         => $client->is_test_mode(),
			]
		);

		return [
			'success'      => true,
			'redirect_url' => $form_data['action_url'],
			'action_url'   => $form_data['action_url'],
			'form_data'    => $form_data,
			'message'      => '',
		];
	}

	public function process_refund( int $order_id, float $amount, string $reason = '', array $context = [] ): array {
		$order = YSOrder::find( $order_id );
		if ( ! $order ) {
			return [
				'success' => false,
				'message' => 'Order not found.',
			];
		}

		if ( ! in_array( $this->method_key, [ 'credit', 'inst', 'linepay', 'applepay' ], true ) ) {
			return [
				'success' => false,
				'message' => 'This NewebPay method does not support online refunds.',
			];
		}

		$refund_amount = (int) round( $amount );
		if ( $refund_amount <= 0 ) {
			return [
				'success' => false,
				'message' => 'Refund amount is invalid.',
			];
		}

		$payment_detail = json_decode( (string) ( $order->payment_detail ?? '{}' ), true );
		if ( ! is_array( $payment_detail ) ) {
			$payment_detail = [];
		}

		$merchant_order_no = (string) ( $payment_detail['newebpay_merchant_order_no'] ?? $payment_detail['mer_trade_no'] ?? '' );
		$trade_no          = (string) ( $payment_detail['trade_no'] ?? $payment_detail['gateway_trade_no'] ?? $order->gateway_trade_no ?? '' );

		if ( '' === $merchant_order_no ) {
			return [
				'success' => false,
				'message' => 'Missing NewebPay merchant order number.',
			];
		}

		$refund_request_id = (string) ( $context['refund_request_id'] ?? '' );
		$signature_input   = '' !== $refund_request_id
			? $order_id . '_request_' . $refund_request_id
			: $order_id . '_legacy_' . $refund_amount . '_' . $reason;
		$signature = md5( $signature_input );

		$history = $payment_detail[ self::REFUND_HISTORY_KEY ] ?? [];
		if ( ! is_array( $history ) ) {
			$history = [];
		}

		foreach ( $history as $entry ) {
			if ( ( $entry['signature'] ?? '' ) === $signature && ! empty( $entry['success'] ) ) {
				return [
					'success'        => true,
					'transaction_id' => (string) ( $entry['refund_order_id'] ?? $merchant_order_no ),
					'message'        => 'Refund already processed.',
				];
			}
		}

		$result = in_array( $this->method_key, [ 'credit', 'inst' ], true )
			? $this->get_client()->refund_credit_card( $merchant_order_no, $refund_amount )
			: $this->get_client()->refund_ewallet( $trade_no, $merchant_order_no, $refund_amount );

		$history[] = [
			'refund_order_id'   => substr( 'YS' . $order_id . 'R' . str_replace( '-', '', wp_generate_uuid4() ), 0, 32 ),
			'amount'            => $refund_amount,
			'reason'            => $reason,
			'refund_request_id' => $refund_request_id,
			'signature'         => $signature,
			'success'           => ! empty( $result['success'] ),
			'message'           => (string) ( $result['message'] ?? '' ),
			'requested_at'      => current_time( 'mysql' ),
		];
		$payment_detail[ self::REFUND_HISTORY_KEY ] = $history;

		YSOrder::update( $order_id, [ 'payment_detail' => wp_json_encode( $payment_detail ) ] );

		if ( ! empty( $result['success'] ) ) {
			return [
				'success'        => true,
				'transaction_id' => $merchant_order_no,
				'message'        => 'NewebPay refund request accepted.',
			];
		}

		return [
			'success' => false,
			'message' => (string) ( $result['message'] ?? 'NewebPay refund failed.' ),
		];
	}

	protected function get_client(): YSNewebpayClient {
		if ( null === $this->client_instance ) {
			$this->client_instance = new YSNewebpayClient();
		}

		return $this->client_instance;
	}

	protected function expire_date_from_setting( string $alias ): string {
		$days = max( 1, (int) YSNewebpaySettings::get( $alias, '3' ) );

		return wp_date( 'Ymd', time() + ( $days * DAY_IN_SECONDS ) );
	}

	private function build_merchant_order_no( int $order_id ): string {
		return substr( 'YS' . $order_id . 'T' . time(), 0, 30 );
	}

	private function build_item_desc( object $order ): string {
		$order_number = (string) ( $order->order_number ?? $order->id ?? '' );
		$desc         = 'YS CART Order ' . $order_number;
		$desc         = preg_replace( '/[^\pL\pN\s\-_#]/u', '', $desc ) ?: 'YS CART Order';

		return mb_substr( trim( $desc ), 0, 50 );
	}

	private function extract_email( object $order ): string {
		foreach ( [ 'billing_email', 'customer_email', 'email' ] as $field ) {
			$email = (string) ( $order->{$field} ?? '' );
			if ( is_email( $email ) ) {
				return $email;
			}
		}

		return (string) get_option( 'admin_email', '' );
	}

	private function thankyou_url( object $order ): string {
		$order_id = (int) ( $order->id ?? 0 );
		$key      = method_exists( YSOrder::class, 'generate_order_key' )
			? YSOrder::generate_order_key( $order_id, (string) ( $order->order_number ?? '' ) )
			: '';

		return add_query_arg(
			[
				'order' => $order_id,
				'key'   => $key,
			],
			home_url( '/thankyou/' )
		);
	}
}
