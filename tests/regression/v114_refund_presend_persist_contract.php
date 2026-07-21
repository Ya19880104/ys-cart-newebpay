<?php
/**
 * Contract regression: 退款 pre-send durable attempt（CODEX 終審 R6-F3）。
 *
 * 保證 YSNewebpayGatewayBase::process_refund：
 *   (1) 送出之前先落盤 submitting entry，且寫入失敗（YSOrder::update 回 false）
 *       → 中止、不呼叫 client（「未送出」語意）
 *   (2) pre-send 持久化檢查出現在 client refund 呼叫**之前**（順序契約）
 *   (3) 同 signature 的 submitting entry → 凍結（不重送）
 *   (4) 送出後 fresh read 再寫結果（不得用 stale payment_detail 洗掉 core ledger）
 *   (5) provider 成功但結果落盤失敗 → 仍回 success（不得被 core 誤標 aborted），
 *       message 帶警示
 *
 * Run: php tests/regression/v114_refund_presend_persist_contract.php
 */

declare( strict_types = 1 );

$base = dirname( __DIR__, 2 );
$src  = file_get_contents( $base . '/src/Gateway/Newebpay/YSNewebpayGatewayBase.php' );

if ( false === $src ) {
	echo "FATAL: cannot read YSNewebpayGatewayBase.php\n";
	exit( 1 );
}

// CRLF 衛生：契約用固定字串斷言，先正規化換行（既有教訓：CRLF 會讓跨行斷言假陰性）
$src = str_replace( "\r\n", "\n", $src );

$pass = 0;
$fail = 0;
$assert = static function ( bool $ok, string $label ) use ( &$pass, &$fail ): void {
	if ( $ok ) {
		++$pass;
		echo "  PASS  {$label}\n";
		return;
	}
	++$fail;
	echo "  FAIL  {$label}\n";
};

// 只取 process_refund 方法本體（避免其他方法誤中）
$m_start = strpos( $src, 'public function process_refund(' );
$assert( false !== $m_start, '(0) process_refund 方法存在' );
$method = false !== $m_start ? substr( $src, $m_start ) : '';
$m_end  = strpos( $method, "\n\tprotected function" );
if ( false !== $m_end ) {
	$method = substr( $method, 0, $m_end );
}

// (1) pre-send submitting entry＋寫入失敗中止（未送出）
$assert(
	str_contains( $method, "'status'            => 'submitting'" ),
	'(1a) pre-send entry 帶 status=submitting'
);
$assert(
	1 === preg_match( '/if\s*\(\s*!\s*YSOrder::update\(/', $method )
	|| substr_count( $method, 'if ( ! YSOrder::update(' ) >= 1,
	'(1b) pre-send 持久化結果被檢查（if ( ! YSOrder::update）'
);
$assert(
	str_contains( $method, '未送出藍新退款請求' ),
	'(1c) 寫入失敗訊息明示「未送出」（金流未動、可安全重試）'
);

// (2) 順序契約：pre-send 檢查 < client 呼叫 < fresh read 結果落盤
$pos_presend_check = strpos( $method, '未送出藍新退款請求' );
$pos_client_call   = strpos( $method, 'refund_credit_card(' );
$pos_fresh_read    = strpos( $method, '$fresh         = YSOrder::find(' );
if ( false === $pos_fresh_read ) {
	$pos_fresh_read = strpos( $method, '$fresh = YSOrder::find(' );
}
$assert(
	false !== $pos_presend_check && false !== $pos_client_call && $pos_presend_check < $pos_client_call,
	'(2a) pre-send 持久化檢查在 client refund 呼叫之前'
);
$assert(
	false !== $pos_fresh_read && false !== $pos_client_call && $pos_client_call < $pos_fresh_read,
	'(2b) 結果落盤的 fresh read 在 client 呼叫之後'
);

// (3) submitting 凍結分支
$assert(
	str_contains( $method, "'submitting' === (string) ( \$entry['status']" )
	&& str_contains( $method, '為避免重複退款已凍結' ),
	'(3) 同 signature submitting entry → 凍結不重送'
);

// (4) fresh read：送出後結果不得用 stale $payment_detail 直接寫回
$after_call = false !== $pos_client_call ? substr( $method, $pos_client_call ) : '';
$assert(
	str_contains( $after_call, 'YSOrder::find(' )
	&& str_contains( $after_call, '$fresh_detail' ),
	'(4) 送出後以 fresh read 更新結果（防洗掉 core finalization ledger）'
);

// (5) provider 成功＋落盤失敗 → 回 success＋警示（不得讓 core 誤標 aborted）
$assert(
	str_contains( $method, '藍新已受理退款，但本地退款紀錄更新失敗' )
	&& str_contains( $method, "'success'        => true," ),
	'(5) 藍新已受理＋紀錄寫失敗 → success=true＋警示訊息'
);

// ── R7-F1：typed outcome（indeterminate vs rejected_terminal）──
$client_src = str_replace( "\r\n", "\n", (string) file_get_contents( $base . '/src/Gateway/Newebpay/YSNewebpayClient.php' ) );
$assert(
	substr_count( $client_src, "'indeterminate' => true" ) >= 2
	&& str_contains( $client_src, "'indeterminate' => false" ),
	'(6) client：連線/非 2xx→indeterminate=true、HTTP 2xx 業務拒絕→indeterminate=false'
);
$assert(
	str_contains( $method, "( \$is_indeterminate ? 'submitting' : 'failed' )" ),
	'(7) 失敗落盤 status：indeterminate→submitting（凍結、防同 UUID 重送）、terminal→failed'
);
$assert(
	str_contains( $method, "'outcome' => 'indeterminate'" )
	&& str_contains( $method, "'outcome' => 'rejected_terminal'" ),
	'(8) gateway 失敗回 typed outcome（indeterminate 凍結／rejected_terminal 可重試）'
);

echo "\nnewebpay refund pre-send persist contract: {$pass} PASS / {$fail} FAIL\n";
exit( $fail > 0 ? 1 : 0 );
