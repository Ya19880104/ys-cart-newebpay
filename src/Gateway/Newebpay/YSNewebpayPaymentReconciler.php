<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\DTOs\YSPaymentDetailDTO;
use YangSheep\Ecommerce\Services\Payment\YSPaymentReconcileResult;
use YangSheep\Ecommerce\Services\Payment\YSPaymentReconcilerInterface;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayPaymentReconciler implements YSPaymentReconcilerInterface {
	private YSNewebpayClient $client;

	public function __construct( ?YSNewebpayClient $client = null ) {
		$this->client = $client ?: new YSNewebpayClient();
	}

	public function supports( object $order ): bool {
		$detail = $this->payment_detail( $order );
		$gateway_id = (string) ( $order->gateway_id ?? $order->payment_method ?? '' );

		return 'newebpay' === (string) ( $detail['payment_provider'] ?? '' )
			|| '' !== (string) ( $detail['newebpay_merchant_order_no'] ?? '' )
			|| str_starts_with( $gateway_id, 'ys_ec_newebpay_' );
	}

	public function reconcile( object $order ): YSPaymentReconcileResult {
		$detail = $this->payment_detail( $order );
		$merchant_order_no = (string) ( $detail['newebpay_merchant_order_no'] ?? $detail['mer_trade_no'] ?? '' );
		if ( '' === $merchant_order_no ) {
			return YSPaymentReconcileResult::unsupported( 'NewebPay merchant order number is missing.' );
		}

		$amount = max( 1, (int) round( (float) ( $order->total ?? 0 ) ) );
		$result = $this->client->query_trade( $merchant_order_no, $amount );
		$data   = is_array( $result['data'] ?? null ) ? $result['data'] : [];
		if ( empty( $result['success'] ) ) {
			return YSPaymentReconcileResult::error( (string) ( $result['message'] ?? 'NewebPay query failed.' ), null, $data );
		}

		$payment_detail = $this->detail_from_query( $data, (string) ( $order->gateway_id ?? '' ) );
		$status         = strtoupper( (string) ( $data['Status'] ?? '' ) );
		$trade_status   = YSNewebpayClient::extract_result_value( $data, 'TradeStatus', $status );

		if ( 'SUCCESS' === $status && in_array( (string) $trade_status, [ '1', 'SUCCESS', 'PAID' ], true ) ) {
			return YSPaymentReconcileResult::paid( $payment_detail, 'NewebPay query confirmed payment.', $data );
		}

		if ( 'SUCCESS' === $status && '0' === (string) $trade_status && $this->is_offline_payment( $order, $data ) ) {
			return YSPaymentReconcileResult::offline_pending( $payment_detail, 'NewebPay query confirmed offline payment is still pending.', $data );
		}

		if ( 'SUCCESS' === $status && in_array( (string) $trade_status, [ '0', '9' ], true ) ) {
			return YSPaymentReconcileResult::hold( 'NewebPay query found the trade but payment is not complete yet.', $payment_detail, $data );
		}

		if ( 'SUCCESS' === $status && in_array( (string) $trade_status, [ '2', '3' ], true ) ) {
			$target = '3' === (string) $trade_status ? 'cancelled' : 'failed';
			return YSPaymentReconcileResult::failed( $payment_detail, $target, 'NewebPay query reported a failed or cancelled trade.', $data );
		}

		return YSPaymentReconcileResult::hold( 'NewebPay query returned an unknown trade state.', $payment_detail, $data );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function payment_detail( object $order ): array {
		$detail = json_decode( (string) ( $order->payment_detail ?? '{}' ), true );
		return is_array( $detail ) ? $detail : [];
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function detail_from_query( array $payload, string $gateway_id ): YSPaymentDetailDTO {
		$payment_type = strtoupper( YSNewebpayClient::extract_result_value( $payload, 'PaymentType' ) );
		$trade_no     = YSNewebpayClient::extract_result_value( $payload, 'TradeNo' );
		$installment  = (int) YSNewebpayClient::extract_result_value( $payload, 'Inst', '0' );

		$detail = [
			'payment_type'       => $this->canonical_payment_type( $payment_type, $gateway_id ),
			'trade_status'       => YSNewebpayClient::extract_result_value( $payload, 'TradeStatus', (string) ( $payload['Status'] ?? '' ) ),
			'trade_no'           => $trade_no,
			'mer_trade_no'       => YSNewebpayClient::extract_result_value( $payload, 'MerchantOrderNo' ),
			'gateway_trade_no'   => $trade_no,
			'card_4no'           => YSNewebpayClient::extract_result_value( $payload, 'Card4No' ),
			'card_6no'           => YSNewebpayClient::extract_result_value( $payload, 'Card6No' ),
			'auth_code'          => YSNewebpayClient::extract_result_value( $payload, 'Auth' ),
			'pay_no'             => $this->extract_pay_no( $payload ),
			'bank_type'          => YSNewebpayClient::extract_result_value( $payload, 'BankCode' ),
			'expire_date'        => trim( YSNewebpayClient::extract_result_value( $payload, 'ExpireDate' ) . ' ' . YSNewebpayClient::extract_result_value( $payload, 'ExpireTime' ) ),
			'installment_period' => $installment,
			'response_code'      => YSNewebpayClient::extract_result_value( $payload, 'RespondCode', (string) ( $payload['Status'] ?? '' ) ),
			'response_message'   => (string) ( $payload['Message'] ?? '' ),
		];

		// v2.x 安全修正：補上 paid_amount（藍新 query 回傳的 Amt），讓 reconcile → mark_paid
		// 也受核心金額守衛保護（守衛在 paid_amount <= 0 時 fail-open，缺金額等於不核對）。
		// Amt 為整數元 TWD，與 order->total 同單位（無需換算）。取不到回 null（不回 0）。
		$raw_amt = YSNewebpayClient::extract_result_value( $payload, 'Amt' );
		if ( '' !== $raw_amt && is_numeric( $raw_amt ) && (float) $raw_amt > 0 ) {
			$detail['paid_amount'] = (float) $raw_amt;
		}

		return YSPaymentDetailDTO::from_legacy_array( $detail, $gateway_id );
	}

	private function canonical_payment_type( string $payment_type, string $gateway_id ): string {
		return match ( $payment_type ) {
			'CREDIT', 'WEBATM' => str_contains( $gateway_id, 'installment' ) ? 'credit_inst' : 'credit_card',
			'VACC'            => 'atm',
			'CVS', 'BARCODE'  => 'cvs_code',
			'LINEPAY'         => 'line_pay',
			'APPLEPAY'        => 'apple_pay',
			default           => '',
		};
	}

	/**
	 * @param array<string,mixed> $payload
	 */
	private function extract_pay_no( array $payload ): string {
		foreach ( [ 'CodeNo', 'PaymentNo', 'PayerAccount5Code', 'Barcode_1', 'Barcode_2', 'Barcode_3' ] as $key ) {
			$value = YSNewebpayClient::extract_result_value( $payload, $key );
			if ( '' === $value ) {
				continue;
			}

			if ( 'Barcode_1' === $key ) {
				return implode(
					'|',
					array_filter(
						[
							YSNewebpayClient::extract_result_value( $payload, 'Barcode_1' ),
							YSNewebpayClient::extract_result_value( $payload, 'Barcode_2' ),
							YSNewebpayClient::extract_result_value( $payload, 'Barcode_3' ),
						],
						static fn ( string $item ): bool => '' !== $item
					)
				);
			}

			return $value;
		}

		return '';
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function is_offline_payment( object $order, array $data ): bool {
		$gateway_id = (string) ( $order->gateway_id ?? $order->payment_method ?? '' );
		$payment_type = strtoupper( YSNewebpayClient::extract_result_value( $data, 'PaymentType' ) );

		return str_contains( $gateway_id, 'atm' )
			|| str_contains( $gateway_id, 'cvs' )
			|| str_contains( $gateway_id, 'barcode' )
			|| in_array( $payment_type, [ 'VACC', 'CVS', 'BARCODE' ], true );
	}
}
