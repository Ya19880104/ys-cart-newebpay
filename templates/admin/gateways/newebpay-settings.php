<?php
/**
 * @var array<string,mixed> $settings
 * @var bool $hash_key_is_set
 * @var bool $hash_iv_is_set
 * @var string $nonce_action
 * @var string $tab
 * @var array<string,string> $tabs
 * @var string $page_url
 * @var bool $saved
 * @var string $notify_url
 * @var string $return_url
 * @var string $store_callback_url
 * @var string $shipping_notify_url
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

$keys = YSNewebpaySettings::SETTING_KEYS;
$tabs = array_replace(
	[
		'api'         => 'API 設定',
		'payment'     => '金流閘道',
		'shipping'    => '物流閘道',
		'installment' => '分期設定',
		'callbacks'   => '回呼網址',
		'log'         => '交易紀錄',
	],
	$tabs
);

$payment_methods = [
	'credit_enabled'  => [ '信用卡一次付清', 'NewebPay MPG 信用卡付款。' ],
	'atm_enabled'     => [ 'ATM 虛擬帳號', '產生虛擬帳號供消費者轉帳。' ],
	'cvs_enabled'     => [ '超商代碼繳費', '產生超商繳費代碼。' ],
	'barcode_enabled' => [ '條碼繳費', '產生三段式條碼供超商繳費。' ],
	'linepay_enabled' => [ 'LINE Pay', '透過 NewebPay MPG 啟用 LINE Pay。' ],
	'applepay_enabled' => [ 'Apple Pay', '透過 NewebPay MPG 啟用 Apple Pay。' ],
];

$logistics_descriptions = [
	'ys_ec_newebpay_ship_711_c2c'    => '藍新官方 C2C 店到店物流；可搭配取貨付款或取貨不付款。',
	'ys_ec_newebpay_ship_family_c2c' => '全家 C2C 店到店物流；使用藍新物流建單與門市資料。',
	'ys_ec_newebpay_ship_hilife_c2c' => '萊爾富 C2C 店到店物流；使用藍新物流建單與門市資料。',
	'ys_ec_newebpay_ship_ok_c2c'     => 'OK mart C2C 店到店物流；使用藍新物流建單與門市資料。',
	'ys_ec_newebpay_ship_711_b2c'    => '7-ELEVEN B2C 大宗寄倉取貨，需商家完成藍新物流服務開通。',
];
?>

<div class="ysca-page-root">
	<?php if ( $saved ) : ?>
		<div class="ys-ec-notice ys-ec-notice-success">
			<span class="dashicons dashicons-yes-alt"></span> 設定已儲存。
		</div>
	<?php endif; ?>

	<?php if ( '1' !== (string) ( $settings['enabled'] ?? '0' ) ) : ?>
		<div class="ys-ec-notice ys-ec-notice-warning">
			<span class="dashicons dashicons-warning"></span> NewebPay 尚未啟用；完成 API 設定後請啟用總開關，並到金物流供應商列表確認狀態。
		</div>
	<?php endif; ?>

	<div class="ys-ec-filters ysca-tabs ysca-tabs--with-indicator" role="tablist" aria-label="NewebPay 設定分頁">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<?php $is_active = $tab === $tab_key; ?>
			<a
				href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, $page_url ) ); ?>"
				class="ys-ec-filter-btn ysca-tab <?php echo $is_active ? 'active ysca-tab--active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
				<?php echo $is_active ? 'aria-current="page"' : ''; ?>
			>
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-form">
		<input type="hidden" name="action" value="ys_ec_newebpay_save_settings">
		<input type="hidden" name="ys_ec_newebpay_tab" value="<?php echo esc_attr( $tab ); ?>">
		<?php wp_nonce_field( $nonce_action ); ?>

		<?php if ( 'api' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-admin-network"></span> API 連線設定</h3>
				<div class="inside">
					<div class="ys-ec-form-group">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong>啟用 NewebPay</strong>
						</label>
					</div>

					<div class="ys-ec-form-group">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['test_mode'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['test_mode'] ?? '1' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong>測試模式（Sandbox）</strong>
						</label>
						<p class="description">啟用後使用藍新測試環境，不會產生實際扣款。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>商店代號（Merchant ID）</strong></label>
						<input class="ysca-input ysca-field--md" type="text" name="<?php echo esc_attr( $keys['merchant_id'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['merchant_id'] ?? '' ) ); ?>" autocomplete="off">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>Hash Key</strong></label>
						<input class="ysca-input ysca-field--md" type="password" name="<?php echo esc_attr( $keys['hash_key'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_key_is_set ? '已設定（留空不更改）' : '' ); ?>">
						<p class="description">32 字元加密金鑰。留空表示不更改。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>Hash IV</strong></label>
						<input class="ysca-input ysca-field--md" type="password" name="<?php echo esc_attr( $keys['hash_iv'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_iv_is_set ? '已設定（留空不更改）' : '' ); ?>">
						<p class="description">16 字元初始化向量。留空表示不更改。</p>
					</div>

					<div class="ysca-inline-actions ysca-inline-actions--start">
						<button type="button" class="ysca-btn ysca-btn--secondary" id="ys-newebpay-test-connection">
							<span class="dashicons dashicons-admin-tools ysca-icon--sm"></span> 檢查設定
						</button>
						<span id="ys-newebpay-test-result" class="ysca-field__hint"></span>
					</div>
				</div>
			</div>

			<div class="ys-ec-card ysca-card ysca-mt-md">
				<h3><span class="dashicons dashicons-shield"></span> 幕後交易設定提醒</h3>
				<div class="inside">
					<div class="ysca-card--soft ysca-card--inset">
						<p><strong>使用 ATM/CVS/條碼、LINE Pay、Apple Pay 與物流通知前，請在藍新商店後台完成回呼網址設定。</strong></p>
						<p class="description">回呼網址可在「回呼網址」分頁複製；物流門市回傳與貨態通知請同時設定。</p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'payment' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-money-alt"></span> NewebPay 金流閘道</h3>
				<div class="inside">
					<?php foreach ( $payment_methods as $alias => [ $label, $description ] ) : ?>
						<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
							<label class="ysca-switch-label">
								<span class="ysca-switch">
									<input type="checkbox" name="<?php echo esc_attr( $keys[ $alias ] ); ?>" value="1" <?php checked( '1', (string) ( $settings[ $alias ] ?? '0' ) ); ?>>
									<span class="ysca-switch-slider"></span>
								</span>
								<strong><?php echo esc_html( $label ); ?></strong>
							</label>
							<p class="description"><?php echo esc_html( $description ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ys-ec-card ysca-card ysca-mt-md">
				<h3><span class="dashicons dashicons-clock"></span> 付款期限與交易限制</h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field">
						<label><strong>交易金額上限</strong></label>
						<input class="ysca-input ysca-field--sm" type="number" min="0" name="<?php echo esc_attr( $keys['trade_limit'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['trade_limit'] ?? '0' ) ); ?>">
						<p class="description">0 表示依藍新付款方式預設限制。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>ATM 付款期限</strong></label>
						<input class="ysca-input ysca-field--sm" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['atm_expire_days'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['atm_expire_days'] ?? '3' ) ); ?>">
						<p class="description">單位：天。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>超商代碼付款期限</strong></label>
						<input class="ysca-input ysca-field--sm" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['cvs_expire_days'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['cvs_expire_days'] ?? '3' ) ); ?>">
						<p class="description">單位：天。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>條碼付款期限</strong></label>
						<input class="ysca-input ysca-field--sm" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['bar_expire_days'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['bar_expire_days'] ?? '3' ) ); ?>">
						<p class="description">單位：天。</p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'shipping' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-store"></span> NewebPay 物流閘道</h3>
				<div class="inside">
					<p class="description">使用藍新官方物流 API：C2C 支援四大超商，B2C 支援 7-ELEVEN 大宗寄倉。開啟後仍需到 <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">物流設置</a> 設定運費與啟用規則。</p>

					<?php foreach ( (array) ( $settings['logistics'] ?? [] ) as $method_id => $method ) : ?>
						<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
							<label class="ysca-switch-label">
								<span class="ysca-switch">
									<input type="checkbox" name="<?php echo esc_attr( 'shipping_' . $method_id . '_enabled' ); ?>" value="1" <?php checked( '1', (string) ( $method['enabled'] ?? '0' ) ); ?>>
									<span class="ysca-switch-slider"></span>
								</span>
								<strong><?php echo esc_html( (string) ( $method['label'] ?? $method_id ) ); ?></strong>
							</label>
							<p class="description"><?php echo esc_html( $logistics_descriptions[ (string) $method_id ] ?? '使用藍新官方物流 API 建立與查詢物流單。' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ys-ec-card ysca-card ysca-mt-md">
				<h3><span class="dashicons dashicons-list-view"></span> 官方物流 API 範圍</h3>
				<div class="inside">
					<div class="ysca-provider-card__badges">
						<span class="ysca-badge ysca-badge--success">7-ELEVEN C2C</span>
						<span class="ysca-badge ysca-badge--success">全家 C2C</span>
						<span class="ysca-badge ysca-badge--success">萊爾富 C2C</span>
						<span class="ysca-badge ysca-badge--success">OK mart C2C</span>
						<span class="ysca-badge ysca-badge--success">7-ELEVEN B2C</span>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'installment' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-chart-bar"></span> 分期付款設定</h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['inst_enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['inst_enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong>啟用分期付款</strong>
						</label>
						<p class="description">啟用 NewebPay 信用卡分期付款。</p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>分期期數</strong></label>
						<input class="ysca-input ysca-field--md" type="text" name="<?php echo esc_attr( $keys['inst_flag'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['inst_flag'] ?? '3,6,12' ) ); ?>" placeholder="3,6,12">
						<p class="description">以半形逗號分隔，例如 3,6,12。</p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'callbacks' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-admin-links"></span> NewebPay 回呼網址</h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field">
						<label><strong>付款 NotifyURL</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $notify_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>付款 ReturnURL</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $return_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>門市回傳網址</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $store_callback_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>物流貨態通知網址</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $shipping_notify_url ); ?>">
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'log' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-clipboard"></span> 交易紀錄</h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['debug_enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['debug_enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong>記錄原始回呼</strong>
						</label>
						<p class="description">除錯時記錄 NewebPay 付款與物流回呼原始資料；正式站建議完成驗收後關閉。</p>
					</div>

					<p class="description">日誌位置：<code>wp-content/uploads/ys-ec-logs/newebpay-{date}.log</code></p>
				</div>
			</div>
		<?php endif; ?>

		<div class="ysca-inline-actions ysca-inline-actions--start">
			<button type="submit" class="ysca-btn ysca-btn--primary">
				<span class="dashicons dashicons-saved ysca-icon--sm"></span> 儲存設定
			</button>
		</div>
	</form>
</div>
