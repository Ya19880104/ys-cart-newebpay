<?php

namespace YangSheep\YSCartNewebpay\Gateway\Newebpay;

use YangSheep\Ecommerce\DTOs\YSPaymentDetailDTO;
use YangSheep\Ecommerce\Models\YSOrder;
use YangSheep\Ecommerce\Services\Payment\YSPaymentLifecycleService;
use YangSheep\Ecommerce\Utils\YSLogger;

defined( 'ABSPATH' ) || exit;

final class YSNewebpayWebhookHandler {
	private const META_TRADE_NO    = '_ys_newebpay_trade_no';
	private const META_LAST_STATUS = '_ys_newebpay_last_status';
	private const META_LAST_ACTION = '_ys_newebpay_last_action';

	/**
	 * @return array{success:bool,action:string,mapped:string,message:string}
	 */
	public static function handle( int $order_id, array $payload, string $source = 'webhook_newebpay_notify' ): array {
		$order = YSOrder::find( $order_id );
		if ( ! $order ) {
			return [
				'success' => false,
				'action'  => 'missing_order',
				'mapped'  => 'unknown',
				'message' => 'Order not found.',
			];
		}

		$status            = strtoupper( (string) ( $payload['Status'] ?? '' ) );
		$merchant_order_no = YSNewebpayClient::extract_result_value( $payload, 'MerchantOrderNo' );
		$trade_no          = YSNewebpayClient::extract_result_value( $payload, 'TradeNo' );
		$payment_type      = strtoupper( YSNewebpayClient::extract_result_value( $payload, 'PaymentType' ) );
		$pay_time          = YSNewebpayClient::extract_result_value( $payload, 'PayTime' );
		$trade_status      = YSNewebpayClient::extract_result_value( $payload, 'TradeStatus', $status );
		$action_key        = implode( '|', [ $status, $trade_status, $trade_no, $pay_time ] );

		$existing_detail = json_decode( (string) ( $order->payment_detail ?? '{}' ), true );
		if ( ! is_array( $existing_detail ) ) {
			$existing_detail = [];
		}

		if (
			(string) ( $existing_detail[ self::META_TRADE_NO ] ?? '' ) === $trade_no
			&& (string) ( $existing_detail[ self::META_LAST_ACTION ] ?? '' ) === $action_key
		) {
			return [
				'success' => true,
				'action'  => 'idempotent_noop',
				'mapped'  => 'noop',
				'message' => 'Already processed.',
			];
		}

		$gateway_id = (string) ( $order->gateway_id ?? '' );
		$detail    = self::build_detail_dto( $payload, $gateway_id );
		$mapped    = self::map_status( $status, $payment_type, $pay_time, $trade_status );

		$store_detail = self::extract_store_detail( $payload );

		// R14（CODEX 跨 repo 金融一致性）：payment_detail 改 **CAS mutator 寫入**——
		// 舊 whole-JSON 盲寫以進場 stale $order 做 RMW，會在 core 退款 CAS 成功後
		// 反向整包覆蓋 ledger（succeeded 倒退）。附帶欄位（gateway_trade_no／CVS）
		// 同語句 SET；寫入失敗必須消費（不得繼續 transition）。
		$also_fields = [];
		if ( '' !== $trade_no ) {
			$also_fields['gateway_trade_no'] = $trade_no;
		}
		if ( ! empty( $store_detail['store_id'] ) ) {
			$also_fields['cvs_store_id'] = (string) $store_detail['store_id'];
		}
		if ( ! empty( $store_detail['store_name'] ) ) {
			$also_fields['cvs_store_name'] = (string) $store_detail['store_name'];
		}
		if ( ! empty( $store_detail['store_address'] ) ) {
			$also_fields['cvs_store_addr'] = (string) $store_detail['store_address'];
		}
		if ( ! empty( $store_detail['lgs_no'] ) ) {
			$also_fields['tracking_number'] = (string) $store_detail['lgs_no'];
		}
		$detail_written = self::cas_update_payment_detail(
			$order_id,
			static function ( array $fresh_detail ) use ( $trade_no, $status, $action_key, $merchant_order_no, $payment_type, $payload, $store_detail ): array {
				$fresh_detail[ self::META_TRADE_NO ]    = $trade_no;
				$fresh_detail[ self::META_LAST_STATUS ] = $status;
				$fresh_detail[ self::META_LAST_ACTION ] = $action_key;
				$fresh_detail['newebpay_merchant_order_no'] = $merchant_order_no ?: (string) ( $fresh_detail['newebpay_merchant_order_no'] ?? '' );
				$fresh_detail['newebpay_payment_type']      = $payment_type;
				$fresh_detail['newebpay_callback_at']       = current_time( 'mysql' );
				$fresh_detail['newebpay_status_message']    = (string) ( $payload['Message'] ?? '' );
				if ( ! empty( $store_detail ) ) {
					$fresh_detail['newebpay_store'] = $store_detail;
					$fresh_detail['shipping']       = array_merge(
						is_array( $fresh_detail['shipping'] ?? null ) ? $fresh_detail['shipping'] : [],
						self::store_detail_to_shipping( $store_detail )
					);
				}
				return $fresh_detail;
			},
			$also_fields
		);
		if ( ! $detail_written ) {
			YSLogger::error( 'newebpay', 'CRITICAL: webhook payment_detail 寫入失敗（中止，不進行狀態轉換）', [
				'order_id' => $order_id,
				'trade_no' => $trade_no,
			] );
			return [
				'success' => false,
				'action'  => 'persist_failed',
				'mapped'  => $mapped,
				'message' => 'payment_detail persist failed.',
			];
		}

		$result = [
			'success' => true,
			'action'  => 'no_transition',
			'mapped'  => $mapped,
			'message' => 'Processed.',
		];

		if ( 'paid' === $mapped ) {
			// v2.x 安全：若 payload 取不到 Amt，paid_amount 缺席 → 核心金額守衛 fail-open
			// （不核對金額）。明確記 warning 供營運稽核（仍依藍新成功通知推進，留下軌跡）。
			if ( null === self::extract_paid_amount( $payload ) ) {
				YSLogger::warning(
					'newebpay',
					'付款成功但 payload 缺 Amt，金額守衛無法核對',
					[
						'order_id'    => $order_id,
						'trade_no'    => $trade_no,
						'order_total' => (float) ( $order->total ?? 0 ),
					]
				);
			}
			$transition = YSPaymentLifecycleService::mark_paid( $order_id, $detail, $source );
			$result['action']  = ! empty( $transition['success'] ) ? 'mark_paid' : 'mark_paid_rejected';
			$result['message'] = (string) ( $transition['message'] ?? '' );
		} elseif ( 'pending_offline' === $mapped ) {
			$transition = YSPaymentLifecycleService::mark_pending_offline( $order_id, $detail, $source . '_pending' );
			$result['action']  = ! empty( $transition['success'] ) ? 'mark_pending_offline' : 'mark_pending_offline_rejected';
			$result['message'] = (string) ( $transition['message'] ?? '' );
		} elseif ( 'failed' === $mapped ) {
			$transition = YSPaymentLifecycleService::mark_failed(
				$order_id,
				$detail->add_note( 'NewebPay payment failed or was cancelled.', 'warning' ),
				$source,
				'failed'
			);
			$result['action']  = ! empty( $transition['success'] ) ? 'mark_failed' : 'mark_failed_rejected';
			$result['message'] = (string) ( $transition['message'] ?? '' );
		}

		do_action( 'ys_newebpay_payment_status_changed', $order_id, $payload, $result['action'], $mapped );

		YSLogger::info(
			'newebpay',
			'Callback processed',
			[
				'order_id'          => $order_id,
				'merchant_order_no' => $merchant_order_no,
				'trade_no'          => $trade_no,
				'status'            => $status,
				'mapped'            => $mapped,
				'action'            => $result['action'],
			]
		);

		return $result;
	}

	private static function map_status( string $status, string $payment_type, string $pay_time, string $trade_status ): string {
		if ( 'SUCCESS' !== $status ) {
			return 'failed';
		}

		$is_offline = in_array( $payment_type, [ 'VACC', 'CVS', 'BARCODE' ], true );
		$is_paid    = '' !== $pay_time || in_array( (string) $trade_status, [ '1', 'SUCCESS', 'PAID' ], true );

		if ( $is_offline && ! $is_paid ) {
			return 'pending_offline';
		}

		return 'paid';
	}

	private static function build_detail_dto( array $payload, string $gateway_id ): YSPaymentDetailDTO {
		$payment_type = strtoupper( YSNewebpayClient::extract_result_value( $payload, 'PaymentType' ) );
		$pay_no       = self::extract_pay_no( $payload );
		$trade_no     = YSNewebpayClient::extract_result_value( $payload, 'TradeNo' );
		$installment  = (int) YSNewebpayClient::extract_result_value( $payload, 'Inst', '0' );

		$detail = [
			'payment_type'       => self::canonical_payment_type( $payment_type, $gateway_id ),
			'trade_status'       => (string) ( $payload['Status'] ?? '' ),
			'trade_no'           => $trade_no,
			'mer_trade_no'       => YSNewebpayClient::extract_result_value( $payload, 'MerchantOrderNo' ),
			'gateway_trade_no'   => $trade_no,
			'card_4no'           => YSNewebpayClient::extract_result_value( $payload, 'Card4No' ),
			'card_6no'           => YSNewebpayClient::extract_result_value( $payload, 'Card6No' ),
			'auth_code'          => YSNewebpayClient::extract_result_value( $payload, 'Auth' ),
			'pay_no'             => $pay_no,
			'bank_type'          => YSNewebpayClient::extract_result_value( $payload, 'BankCode' ),
			'expire_date'        => trim( YSNewebpayClient::extract_result_value( $payload, 'ExpireDate' ) . ' ' . YSNewebpayClient::extract_result_value( $payload, 'ExpireTime' ) ),
			'installment_period' => $installment,
			'response_code'      => YSNewebpayClient::extract_result_value( $payload, 'RespondCode', (string) ( $payload['Status'] ?? '' ) ),
			'response_message'   => (string) ( $payload['Message'] ?? '' ),
		];

		// v2.x 安全修正：補上 paid_amount（藍新回傳的實付金額 Amt）。
		// 核心 YSPaymentLifecycleService 進 processing 時會用 paid_amount_matches_order
		// 守衛比對「實付金額 vs order->total」，但該守衛在 paid_amount <= 0 時 fail-open。
		// 先前本 handler 沒帶 Amt → 守衛變 no-op，webhook 收到付款成功即原價入帳、不核對金額。
		// 藍新 Amt 為整數元 TWD（與 order->total 同單位，無需換算），且已用於 CheckCode 驗章。
		$paid_amount = self::extract_paid_amount( $payload );
		if ( null !== $paid_amount ) {
			$detail['paid_amount'] = $paid_amount;
		}

		if ( '1' === YSNewebpaySettings::get( 'debug_enabled', '0' ) ) {
			$detail['raw_callback'] = $payload;
		}

		return YSPaymentDetailDTO::from_legacy_array( $detail, $gateway_id );
	}

	/**
	 * 從藍新 decrypt 後的 payload 取出實付金額（Amt）。
	 *
	 * 藍新 callback 解密後金額欄位為 `Amt`（位於 Result 物件或 top-level），
	 * 透過 YSNewebpayClient::extract_result_value 取得；單位為整數元 TWD，
	 * 與 order->total 同單位（無需換算）。
	 *
	 * 防禦性：取不到或非數值時回 null（而非 0），避免讓核心守衛 fail-open
	 * （paid_amount <= 0 → return true，等同不核對金額）。
	 *
	 * @param array<string,mixed> $payload
	 * @return float|null
	 */
	private static function extract_paid_amount( array $payload ): ?float {
		$raw = YSNewebpayClient::extract_result_value( $payload, 'Amt' );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return null;
		}

		$amount = (float) $raw;
		return $amount > 0 ? $amount : null;
	}

	private static function canonical_payment_type( string $payment_type, string $gateway_id ): string {
		return match ( $payment_type ) {
			'CREDIT', 'WEBATM' => str_contains( $gateway_id, 'installment' ) ? 'credit_inst' : 'credit_card',
			'VACC'            => 'atm',
			'CVS', 'BARCODE'  => 'cvs_code',
			'LINEPAY'         => 'line_pay',
			'APPLEPAY'        => 'apple_pay',
			default           => '',
		};
	}

	private static function extract_pay_no( array $payload ): string {
		foreach ( [ 'CodeNo', 'PaymentNo', 'PayerAccount5Code', 'Barcode_1', 'Barcode_2', 'Barcode_3' ] as $key ) {
			$value = YSNewebpayClient::extract_result_value( $payload, $key );
			if ( '' !== $value ) {
				if ( 'Barcode_1' === $key ) {
					return implode(
						'|',
						array_filter(
							[
								YSNewebpayClient::extract_result_value( $payload, 'Barcode_1' ),
								YSNewebpayClient::extract_result_value( $payload, 'Barcode_2' ),
								YSNewebpayClient::extract_result_value( $payload, 'Barcode_3' ),
							],
							static fn( string $item ): bool => '' !== $item
						)
					);
				}
				return $value;
			}
		}

		return '';
	}

	/**
	 * @return array<string,string>
	 */
	private static function extract_store_detail( array $payload ): array {
		$store_id = YSNewebpayClient::extract_result_value( $payload, 'StoreCode' )
			?: YSNewebpayClient::extract_result_value( $payload, 'StoreID' );
		$store_name = YSNewebpayClient::extract_result_value( $payload, 'StoreName' )
			?: YSNewebpayClient::extract_result_value( $payload, 'CVSStoreName' );
		$store_addr = YSNewebpayClient::extract_result_value( $payload, 'StoreAddr' )
			?: YSNewebpayClient::extract_result_value( $payload, 'StoreAddress' );

		$detail = [
			'provider'        => 'newebpay',
			'store_id'        => $store_id,
			'store_name'      => $store_name,
			'store_type'      => YSNewebpayClient::extract_result_value( $payload, 'StoreType' ),
			'store_address'   => $store_addr,
			'recipient_name'  => YSNewebpayClient::extract_result_value( $payload, 'CVSCOMName' ),
			'recipient_phone' => YSNewebpayClient::extract_result_value( $payload, 'CVSCOMPhone' ),
			'lgs_no'          => YSNewebpayClient::extract_result_value( $payload, 'LgsNo' ),
			'lgs_type'        => YSNewebpayClient::extract_result_value( $payload, 'LgsType' ),
			'trade_type'      => YSNewebpayClient::extract_result_value( $payload, 'TradeType' ),
			'updated_at'      => current_time( 'mysql' ),
		];

		return array_filter( $detail, static fn( string $value ): bool => '' !== $value );
	}

	/**
	 * @param array<string,string> $store_detail
	 * @return array<string,string>
	 */
	private static function store_detail_to_shipping( array $store_detail ): array {
		return array_filter(
			[
				'provider'        => 'newebpay',
				'store_id'        => $store_detail['store_id'] ?? '',
				'store_name'      => $store_detail['store_name'] ?? '',
				'store_address'   => $store_detail['store_address'] ?? '',
				'store_type'      => $store_detail['store_type'] ?? '',
				'recipient_name'  => $store_detail['recipient_name'] ?? '',
				'recipient_phone' => $store_detail['recipient_phone'] ?? '',
				'lgs_no'          => $store_detail['lgs_no'] ?? '',
				'lgs_type'        => $store_detail['lgs_type'] ?? '',
				'tracking_number' => $store_detail['lgs_no'] ?? '',
				'updated_at'      => current_time( 'mysql' ),
			],
			static fn( string $value ): bool => '' !== $value
		);
	}

	/**
	 * payment_detail 的 CAS mutator 寫入（R14：跨 repo writer ownership 統一）
	 *
	 * fresh read（穿透模型層快取）→ mutator 重算 → 真 CAS（WHERE payment_detail=
	 * 舊 raw、NULL 用 IS NULL）→ 落敗重讀重放（bounded retry）。同值＝合法冪等
	 * no-op（同值 UPDATE 回 0 不得誤判競爭）；SQL 失敗（false）不重試。
	 * 附帶欄位（gateway_trade_no／CVS）同語句 SET；欄名為程式內常數非外部輸入。
	 */
	private static function cas_update_payment_detail( int $order_id, callable $mutator, array $also_fields = [] ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'ys_ec_orders';
		for ( $i = 0; $i < 3; $i++ ) {
			if ( method_exists( YSOrder::class, 'forget' ) ) {
				YSOrder::forget( $order_id );
			}
			$fresh = YSOrder::find( $order_id );
			if ( ! $fresh ) {
				return false;
			}
			$old_raw = $fresh->payment_detail;
			$detail  = json_decode( (string) ( $old_raw ?? '{}' ), true );
			if ( ! is_array( $detail ) ) {
				$detail = [];
			}
			$mutated = $mutator( $detail );
			if ( null === $mutated ) {
				return true;
			}
			$new_raw    = wp_json_encode( $mutated );
			$alsos_same = true;
			foreach ( $also_fields as $column => $value ) {
				if ( (string) ( $fresh->{$column} ?? '' ) !== (string) $value ) {
					$alsos_same = false;
					break;
				}
			}
			if ( $new_raw === $old_raw && $alsos_same ) {
				return true; // 目標值已達＝冪等 no-op
			}
			$sets   = [ 'payment_detail = %s' ];
			$values = [ $new_raw ];
			foreach ( $also_fields as $column => $value ) {
				$sets[]   = "`{$column}` = %s";
				$values[] = (string) $value;
			}
			$values[] = $order_id;
			if ( null === $old_raw ) {
				$sql = "UPDATE {$table} SET " . implode( ', ', $sets ) . ' WHERE id = %d AND payment_detail IS NULL';
			} else {
				$sql      = "UPDATE {$table} SET " . implode( ', ', $sets ) . ' WHERE id = %d AND payment_detail = %s';
				$values[] = (string) $old_raw;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$updated = $wpdb->query( $wpdb->prepare( $sql, ...$values ) );
			if ( false === $updated ) {
				return false; // SQL 失敗（≠CAS 落敗）
			}
			if ( 1 === (int) $updated ) {
				if ( method_exists( YSOrder::class, 'forget' ) ) {
					YSOrder::forget( $order_id );
				}
				return true;
			}
			// 0 rows＝CAS 落敗（期間被改寫）→ 重讀重放
		}
		return false;
	}
}
