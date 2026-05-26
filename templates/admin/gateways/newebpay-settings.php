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
$tabs = array_replace( YSNewebpaySettings::TABS, $tabs );

$payment_methods = [
	'credit_enabled'   => [ '信用卡一次付清', '透過 NewebPay MPG 使用信用卡付款。' ],
	'atm_enabled'      => [ 'ATM 虛擬帳號', '產生 ATM 轉帳帳號供顧客付款。' ],
	'cvs_enabled'      => [ '超商代碼', '產生超商代碼供顧客付款。' ],
	'barcode_enabled'  => [ '超商條碼', '產生條碼繳費資訊供顧客付款。' ],
	'linepay_enabled'  => [ 'LINE Pay', '透過 NewebPay MPG 使用 LINE Pay 付款。' ],
	'applepay_enabled' => [ 'Apple Pay', '透過 NewebPay MPG 使用 Apple Pay 付款。' ],
];

$logistics_descriptions = [
	'ys_ec_newebpay_ship_711_c2c'    => '7-ELEVEN C2C 超商取貨，適用一般門市寄取件流程。',
	'ys_ec_newebpay_ship_family_c2c' => '全家 C2C 超商取貨，使用藍新物流串接。',
	'ys_ec_newebpay_ship_hilife_c2c' => '萊爾富 C2C 超商取貨，使用藍新物流串接。',
	'ys_ec_newebpay_ship_ok_c2c'     => 'OK mart C2C 超商取貨，使用藍新物流串接。',
	'ys_ec_newebpay_ship_711_b2c'    => '7-ELEVEN B2C 超商取貨，適用合約型物流流程。',
];
?>

<div class="ysca-page-root">
	<?php if ( $saved ) : ?>
		<div class="ys-ec-notice ys-ec-notice-success">
			<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( '設定已儲存。', 'ys-cart-newebpay' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( '1' !== (string) ( $settings['enabled'] ?? '0' ) ) : ?>
		<div class="ys-ec-notice ys-ec-notice-warning">
			<span class="dashicons dashicons-warning"></span> <?php esc_html_e( '藍新供應商尚未啟用，金流與物流方法不會註冊到結帳流程。', 'ys-cart-newebpay' ); ?>
		</div>
	<?php endif; ?>

	<div class="ys-ec-filters ysca-tabs ysca-tabs--with-indicator" role="tablist" aria-label="<?php esc_attr_e( '藍新設定分頁', 'ys-cart-newebpay' ); ?>">
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
				<h3><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'API 基本設定', 'ys-cart-newebpay' ); ?></h3>
				<div class="inside">
					<div class="ys-ec-form-group">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong><?php esc_html_e( '啟用 NewebPay', 'ys-cart-newebpay' ); ?></strong>
						</label>
					</div>

					<div class="ys-ec-form-group">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['test_mode'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['test_mode'] ?? '1' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong><?php esc_html_e( '測試模式（Sandbox）', 'ys-cart-newebpay' ); ?></strong>
						</label>
						<p class="description"><?php esc_html_e( '正式上線前請保持測試模式，並使用藍新測試商店資料。', 'ys-cart-newebpay' ); ?></p>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong><?php esc_html_e( '商店代號（Merchant ID）', 'ys-cart-newebpay' ); ?></strong></label>
						<input class="ysca-input ysca-field--md" type="text" name="<?php echo esc_attr( $keys['merchant_id'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['merchant_id'] ?? '' ) ); ?>" autocomplete="off">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>Hash Key</strong></label>
						<input class="ysca-input ysca-field--md" type="password" name="<?php echo esc_attr( $keys['hash_key'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_key_is_set ? '已儲存，留空不變更' : '' ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>Hash IV</strong></label>
						<input class="ysca-input ysca-field--md" type="password" name="<?php echo esc_attr( $keys['hash_iv'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_iv_is_set ? '已儲存，留空不變更' : '' ); ?>">
					</div>

					<div class="ysca-inline-actions ysca-inline-actions--start">
						<button type="button" class="ysca-btn ysca-btn--secondary" id="ys-newebpay-test-connection">
							<span class="dashicons dashicons-admin-tools ysca-icon--sm"></span> <?php esc_html_e( '測試連線', 'ys-cart-newebpay' ); ?>
						</button>
						<span id="ys-newebpay-test-result" class="ysca-field__hint"></span>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'payment' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'NewebPay 金流方式', 'ys-cart-newebpay' ); ?></h3>
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
		<?php endif; ?>

		<?php if ( 'shipping' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-store"></span> <?php esc_html_e( 'NewebPay 物流方式', 'ys-cart-newebpay' ); ?></h3>
				<div class="inside">
					<p class="description"><?php esc_html_e( '運費、免運門檻與排序請到 YS CART 物流設定管理。', 'ys-cart-newebpay' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=ys-ec-shipping' ) ); ?>"><?php esc_html_e( '前往物流設定', 'ys-cart-newebpay' ); ?></a></p>

					<?php foreach ( (array) ( $settings['logistics'] ?? [] ) as $method_id => $method ) : ?>
						<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
							<label class="ysca-switch-label">
								<span class="ysca-switch">
									<input type="checkbox" name="<?php echo esc_attr( 'shipping_' . $method_id . '_enabled' ); ?>" value="1" <?php checked( '1', (string) ( $method['enabled'] ?? '0' ) ); ?>>
									<span class="ysca-switch-slider"></span>
								</span>
								<strong><?php echo esc_html( (string) ( $method['label'] ?? $method_id ) ); ?></strong>
							</label>
							<p class="description"><?php echo esc_html( $logistics_descriptions[ (string) $method_id ] ?? '使用藍新物流 API 建立超商取貨配送。' ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'installment' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( '分期設定', 'ys-cart-newebpay' ); ?></h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['inst_enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['inst_enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong><?php esc_html_e( '啟用信用卡分期', 'ys-cart-newebpay' ); ?></strong>
						</label>
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong><?php esc_html_e( '分期期數', 'ys-cart-newebpay' ); ?></strong></label>
						<input class="ysca-input ysca-field--md" type="text" name="<?php echo esc_attr( $keys['inst_flag'] ); ?>" value="<?php echo esc_attr( (string) ( $settings['inst_flag'] ?? '3,6,12' ) ); ?>" placeholder="3,6,12">
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'callbacks' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'NewebPay 回呼網址', 'ys-cart-newebpay' ); ?></h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field">
						<label><strong>NotifyURL</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $notify_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong>ReturnURL</strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $return_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong><?php esc_html_e( '選店回呼', 'ys-cart-newebpay' ); ?></strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $store_callback_url ); ?>">
					</div>

					<div class="ys-ec-form-group ysca-field">
						<label><strong><?php esc_html_e( '物流通知', 'ys-cart-newebpay' ); ?></strong></label>
						<input class="ysca-input ysca-field--lg" type="text" readonly value="<?php echo esc_attr( $shipping_notify_url ); ?>">
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( 'log' === $tab ) : ?>
			<div class="ys-ec-card">
				<h3><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( '除錯記錄', 'ys-cart-newebpay' ); ?></h3>
				<div class="inside">
					<div class="ys-ec-form-group ysca-field ysca-card--soft ysca-card--inset">
						<label class="ysca-switch-label">
							<span class="ysca-switch">
								<input type="checkbox" name="<?php echo esc_attr( $keys['debug_enabled'] ); ?>" value="1" <?php checked( '1', (string) ( $settings['debug_enabled'] ?? '0' ) ); ?>>
								<span class="ysca-switch-slider"></span>
							</span>
							<strong><?php esc_html_e( '啟用除錯記錄', 'ys-cart-newebpay' ); ?></strong>
						</label>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="ysca-inline-actions ysca-inline-actions--start">
			<button type="submit" class="ysca-btn ysca-btn--primary">
				<span class="dashicons dashicons-saved ysca-icon--sm"></span> <?php esc_html_e( '儲存設定', 'ys-cart-newebpay' ); ?>
			</button>
		</div>
	</form>
</div>
