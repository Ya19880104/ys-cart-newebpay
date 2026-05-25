<?php
/**
 * @var array<string,mixed> $settings
 * @var bool $hash_key_is_set
 * @var bool $hash_iv_is_set
 * @var string $nonce_action
 * @var string $notify_url
 * @var string $return_url
 * @var string $store_callback_url
 * @var string $shipping_notify_url
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

$keys = YSNewebpaySettings::SETTING_KEYS;
?>

<?php if ( isset( $_GET['ys_ec_newebpay_saved'] ) ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'NewebPay 設定已儲存。', 'ys-cart-newebpay' ); ?></p></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-form ysca-stack">
	<input type="hidden" name="action" value="ys_ec_newebpay_save_settings">
	<?php wp_nonce_field( $nonce_action ); ?>

	<div class="ysca-card ysca-surface ys-ec-card">
		<div class="ysca-card__body">
			<div class="ysca-card__title-row">
				<div>
					<h2 class="ysca-card__title">API 設定</h2>
					<p class="description">藍新 MPG 與物流 API 共用商店代號、Hash Key、Hash IV。</p>
				</div>
			</div>

			<div class="ysca-form-grid">
				<label class="ysca-field">
					<span class="ysca-field__label">啟用 NewebPay</span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['enabled'] ); ?>" value="1" <?php checked( '1', (string) $settings['enabled'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">測試模式</span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['test_mode'] ); ?>" value="1" <?php checked( '1', (string) $settings['test_mode'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">記錄原始回呼</span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['debug_enabled'] ); ?>" value="1" <?php checked( '1', (string) $settings['debug_enabled'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">商店代號</span>
					<input class="ysca-input" type="text" name="<?php echo esc_attr( $keys['merchant_id'] ); ?>" value="<?php echo esc_attr( (string) $settings['merchant_id'] ); ?>" autocomplete="off">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">Hash Key</span>
					<input class="ysca-input" type="password" name="<?php echo esc_attr( $keys['hash_key'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_key_is_set ? '已儲存，留空可保留原值' : '' ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">Hash IV</span>
					<input class="ysca-input" type="password" name="<?php echo esc_attr( $keys['hash_iv'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_iv_is_set ? '已儲存，留空可保留原值' : '' ); ?>">
				</label>
			</div>
		</div>
	</div>

	<div class="ysca-card ysca-surface ys-ec-card">
		<div class="ysca-card__body">
			<h2 class="ysca-card__title">金流設定</h2>
			<div class="ysca-form-grid">
				<?php
				$method_labels = [
					'credit_enabled'  => '信用卡',
					'inst_enabled'    => '分期付款',
					'atm_enabled'     => 'ATM 虛擬帳號',
					'cvs_enabled'     => '超商代碼',
					'barcode_enabled' => '條碼繳費',
					'linepay_enabled' => 'LINE Pay',
					'applepay_enabled'=> 'Apple Pay',
				];
				foreach ( $method_labels as $alias => $label ) :
					?>
					<label class="ysca-field">
						<span class="ysca-field__label"><?php echo esc_html( $label ); ?></span>
						<input type="checkbox" name="<?php echo esc_attr( $keys[ $alias ] ); ?>" value="1" <?php checked( '1', (string) $settings[ $alias ] ); ?>>
					</label>
				<?php endforeach; ?>

				<label class="ysca-field">
					<span class="ysca-field__label">交易金額上限</span>
					<input class="ysca-input" type="number" min="0" name="<?php echo esc_attr( $keys['trade_limit'] ); ?>" value="<?php echo esc_attr( (string) $settings['trade_limit'] ); ?>">
					<span class="ysca-field__hint">0 表示依藍新付款方式預設限制。</span>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">分期期數</span>
					<input class="ysca-input" type="text" name="<?php echo esc_attr( $keys['inst_flag'] ); ?>" value="<?php echo esc_attr( (string) $settings['inst_flag'] ); ?>" placeholder="3,6,12">
				</label>
			</div>
		</div>
	</div>

	<div class="ysca-card ysca-surface ys-ec-card">
		<div class="ysca-card__body">
			<div class="ysca-card__title-row">
				<div>
					<h2 class="ysca-card__title">物流設定</h2>
					<p class="description">使用藍新官方物流 API：C2C 支援四大超商，B2C 支援 7-ELEVEN 大宗寄倉。</p>
				</div>
				<a class="ysca-btn ysca-btn--sm ysca-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>">物流設置</a>
			</div>

			<div class="ysca-form-grid">
				<?php foreach ( (array) ( $settings['logistics'] ?? [] ) as $method_id => $method ) : ?>
					<label class="ysca-field">
						<span class="ysca-field__label"><?php echo esc_html( (string) ( $method['label'] ?? $method_id ) ); ?></span>
						<input type="checkbox" name="<?php echo esc_attr( 'shipping_' . $method_id . '_enabled' ); ?>" value="1" <?php checked( '1', (string) ( $method['enabled'] ?? '0' ) ); ?>>
					</label>
				<?php endforeach; ?>

				<div class="ysca-field">
					<span class="ysca-field__label">物流 API 範圍</span>
					<div class="ysca-provider-card__badges">
						<span class="ysca-badge ysca-badge--success">7-ELEVEN C2C</span>
						<span class="ysca-badge ysca-badge--success">全家 C2C</span>
						<span class="ysca-badge ysca-badge--success">萊爾富 C2C</span>
						<span class="ysca-badge ysca-badge--success">OK mart C2C</span>
						<span class="ysca-badge ysca-badge--success">7-ELEVEN B2C</span>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="ysca-card ysca-surface ys-ec-card">
		<div class="ysca-card__body">
			<h2 class="ysca-card__title">期限與回呼</h2>
			<div class="ysca-form-grid">
				<label class="ysca-field">
					<span class="ysca-field__label">ATM 付款期限</span>
					<input class="ysca-input" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['atm_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['atm_expire_days'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">超商代碼付款期限</span>
					<input class="ysca-input" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['cvs_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['cvs_expire_days'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">條碼付款期限</span>
					<input class="ysca-input" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['bar_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['bar_expire_days'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">付款 NotifyURL</span>
					<input class="ysca-input" type="text" readonly value="<?php echo esc_attr( $notify_url ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">付款 ReturnURL</span>
					<input class="ysca-input" type="text" readonly value="<?php echo esc_attr( $return_url ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">門市回傳網址</span>
					<input class="ysca-input" type="text" readonly value="<?php echo esc_attr( $store_callback_url ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label">物流貨態通知網址</span>
					<input class="ysca-input" type="text" readonly value="<?php echo esc_attr( $shipping_notify_url ); ?>">
				</label>
			</div>
		</div>
	</div>

	<div class="ysca-actions-bar">
		<button type="submit" class="ysca-btn ysca-btn--primary">儲存 NewebPay 設定</button>
		<button type="button" class="ysca-btn" id="ys-newebpay-test-connection">檢查設定</button>
		<span id="ys-newebpay-test-result" class="ysca-field__hint"></span>
	</div>
</form>
