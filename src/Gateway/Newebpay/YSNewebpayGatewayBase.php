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
			'message' => '藍新目前不支援訂閱扣款。',
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
				'message' => '找不到訂單。',
			];
		}

		$client = $this->get_client();
		if ( ! $client->is_configured() ) {
			return [
				'success' => false,
				'message' => '藍新金流設定尚未完成。',
			];
		}

		$amount = (int) round( YSPriceHelper::round( (float) ( $order->total ?? 0 ) ) );
		if ( $amount <= 0 ) {
			return [
				'success' => false,
				'message' => '訂單金額無效。',
			];
		}

		$max_amount = $this->get_max_amount();
		if ( $max_amount > 0 && $amount > $max_amount ) {
			return [
				'success' => false,
				'message' => sprintf( '此藍新付款方式最高支援 NT$%d。', (int) $max_amount ),
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
				'message' => '無法建立藍新付款表單。',
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
		$payment_detail['payment_provider']           = 'newebpay';

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

	/**
	 * 自動金流退款能力宣告（core v2.56.4 退款能力協定）——**方法級**：
	 * 白名單必須與 process_refund() 的支援清單一致（credit／inst／linepay／applepay
	 * 具真實退款 API；ATM／CVS／barcode 在 process_refund 內明確拒絕＝人工退款，
	 * 宣告 false 讓後台顯示「訂單退款≠金流退款」警示）。
	 */
	public function supports_gateway_refund(): bool {
		return in_array( $this->method_key, [ 'credit', 'inst', 'linepay', 'applepay' ], true );
	}

	public function process_refund( int $order_id, float $amount, string $reason = '', array $context = [] ): array {
		// R7-F1：pre-send 業務拒絕（金流未動）→ outcome=rejected_terminal（可安全重試）。
		//         字面值＝與 core YSRefundHandler::REFUND_OUTCOME_* 契約一致。
		$order = YSOrder::find( $order_id );
		if ( ! $order ) {
			return [
				'success' => false,
				'outcome' => 'rejected_terminal',
				'message' => '找不到訂單。',
			];
		}

		if ( ! in_array( $this->method_key, [ 'credit', 'inst', 'linepay', 'applepay' ], true ) ) {
			return [
				'success' => false,
				'outcome' => 'rejected_terminal',
				'message' => '此藍新付款方式不支援線上退款。',
			];
		}

		$refund_amount = (int) round( $amount );
		if ( $refund_amount <= 0 ) {
			return [
				'success' => false,
				'outcome' => 'rejected_terminal',
				'message' => '退款金額無效。',
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
				'outcome' => 'rejected_terminal',
				'message' => '缺少藍新商店訂單編號。',
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
			if ( ( $entry['signature'] ?? '' ) !== $signature ) {
				continue;
			}
			if ( ! empty( $entry['success'] ) ) {
				return [
					'success'        => true,
					'transaction_id' => (string) ( $entry['refund_order_id'] ?? $merchant_order_no ),
					'message'        => '退款已處理。',
				];
			}
			// v2.56.4（CODEX 終審 R6-F3）：同 signature 先前已送出但結果未落盤
			//（submitting）→ 凍結：可能已在藍新端退款，重送＝重複退款。
			//（舊資料無 status 欄位＝明確失敗過的 attempt，不在此攔、可重試。）
			if ( 'submitting' === (string) ( $entry['status'] ?? '' ) ) {
				// 先前已送出未落盤＝結果不明 → indeterminate（core 亦凍結）。
				return [
					'success' => false,
					'outcome' => 'indeterminate',
					'message' => '此退款請求先前已送出但結果未落盤，為避免重複退款已凍結；請先於藍新後台核對實際狀態，再人工核定處理。',
				];
			}
		}

		// v2.56.4（CODEX 終審 R6-F3）：pre-send durable attempt——送出**之前**先落盤
		// submitting entry；寫入失敗＝冪等防線不存在，不得送出（金流未動、可安全重試）。
		$refund_order_id = substr( 'YS' . $order_id . 'R' . str_replace( '-', '', wp_generate_uuid4() ), 0, 32 );
		$history[]       = [
			'refund_order_id'   => $refund_order_id,
			'amount'            => $refund_amount,
			'reason'            => $reason,
			'refund_request_id' => $refund_request_id,
			'signature'         => $signature,
			'success'           => false,
			'status'            => 'submitting',
			'message'           => '',
			'requested_at'      => current_time( 'mysql' ),
		];
		$payment_detail[ self::REFUND_HISTORY_KEY ] = $history;

		if ( ! YSOrder::update( $order_id, [ 'payment_detail' => wp_json_encode( $payment_detail ) ] ) ) {
			YSLogger::error( 'newebpay', '退款 attempt 持久化失敗，已中止（未送出退款請求）', [
				'order_id'          => $order_id,
				'refund_request_id' => $refund_request_id,
			] );
			return [
				'success' => false,
				'outcome' => 'rejected_terminal',
				'message' => '退款請求無法持久化（冪等防線寫入失敗），已中止；未送出藍新退款請求，請重試。',
			];
		}

		$result = in_array( $this->method_key, [ 'credit', 'inst' ], true )
			? $this->get_client()->refund_credit_card( $merchant_order_no, $refund_amount )
			: $this->get_client()->refund_ewallet( $trade_no, $merchant_order_no, $refund_amount );

		// R7-F1：client 的 indeterminate 旗標（timeout／非 2xx）決定失敗落盤的 status——
		// 不明＝維持 'submitting'（同 signature 重試被凍結、不重送）；明確拒絕＝'failed'
		// （可重試）。舊版把 timeout 一律記 'failed' → 同 UUID 重試新 refund_order_id 再送
		// ＝重複退款（CODEX 終審 R7-F1 修正）。
		$is_indeterminate = ! empty( $result['indeterminate'] );

		// 送出後把 provider 結果落盤（fresh read——core handler 於本呼叫前後也會寫
		// payment_detail，用 stale array 寫回會洗掉 core 的 finalization ledger）。
		$outcome_saved = false;
		$fresh         = YSOrder::find( $order_id );
		if ( $fresh ) {
			$fresh_detail = json_decode( (string) ( $fresh->payment_detail ?? '{}' ), true );
			if ( is_array( $fresh_detail ) ) {
				$fresh_history = $fresh_detail[ self::REFUND_HISTORY_KEY ] ?? [];
				if ( is_array( $fresh_history ) ) {
					foreach ( $fresh_history as $i => $saved_entry ) {
						if ( ( $saved_entry['refund_order_id'] ?? '' ) === $refund_order_id ) {
							$fresh_history[ $i ]['success'] = ! empty( $result['success'] );
							$fresh_history[ $i ]['status']  = ! empty( $result['success'] )
								? 'done'
								: ( $is_indeterminate ? 'submitting' : 'failed' );
							$fresh_history[ $i ]['message'] = (string) ( $result['message'] ?? '' );

							$fresh_detail[ self::REFUND_HISTORY_KEY ] = $fresh_history;

							$outcome_saved = (bool) YSOrder::update( $order_id, [
								'payment_detail' => wp_json_encode( $fresh_detail ),
							] );
							break;
						}
					}
				}
			}
		}

		if ( ! empty( $result['success'] ) ) {
			// 結果寫失敗但藍新已受理：不得回 false（會被 core 當「金流未動」標
			// aborted）；回 success＋警示訊息，由 core 透傳到後台（R6-F6）。
			$note = $outcome_saved ? '' : '⚠ 藍新已受理退款，但本地退款紀錄更新失敗，請於藍新後台核對。';
			if ( '' !== $note ) {
				YSLogger::error( 'newebpay', 'CRITICAL: 退款結果落盤失敗（藍新已受理）', [
					'order_id'        => $order_id,
					'refund_order_id' => $refund_order_id,
				] );
			}
			return [
				'success'        => true,
				'transaction_id' => $merchant_order_no,
				'message'        => trim( '藍新退款請求已送出。' . ( '' === $note ? '' : ' ' . $note ) ),
			];
		}

		// R7-F1：結果不明（timeout／非 2xx）→ outcome=indeterminate，core 維持凍結、
		// 禁重送；entry 已落 'submitting'（或落盤失敗仍停 submitting）＝同 id 重試被凍結。
		// 字面值＝與 core YSRefundHandler::REFUND_OUTCOME_* 契約一致。
		if ( $is_indeterminate ) {
			YSLogger::error( 'newebpay', 'CRITICAL: 退款結果未明（傳輸中斷/非 2xx）— 本單凍結待人工核定', [
				'order_id'        => $order_id,
				'refund_order_id' => $refund_order_id,
			] );
			return [
				'success' => false,
				'outcome' => 'indeterminate',
				'message' => '藍新退款結果未明（傳輸中斷或伺服器未回覆），為避免重複退款已凍結；請於藍新後台核對後人工核定。',
			];
		}

		// provider 明確拒絕（HTTP 2xx 但業務碼失敗）＝金流未動、可安全重試。
		if ( ! $outcome_saved ) {
			// 拒絕結果寫失敗 → entry 停留 submitting（同 id 之後會被凍結）——保守方向，
			// 寧可要求人工核對，不冒重送風險。
			YSLogger::error( 'newebpay', 'CRITICAL: 退款拒絕結果落盤失敗（entry 停留 submitting，同請求將凍結）', [
				'order_id'        => $order_id,
				'refund_order_id' => $refund_order_id,
			] );
		}

		return [
			'success' => false,
			'outcome' => 'rejected_terminal',
			'message' => (string) ( $result['message'] ?? '藍新退款失敗。' ),
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
