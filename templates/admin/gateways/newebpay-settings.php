<?php
/**
 * @var array<string,mixed> $settings
 * @var bool $hash_key_is_set
 * @var bool $hash_iv_is_set
 * @var string $nonce_action
 * @var string $notify_url
 * @var string $return_url
 */

defined( 'ABSPATH' ) || exit;

use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

$keys = YSNewebpaySettings::SETTING_KEYS;
?>

<div class="ysca-card">
	<div class="ysca-card__body">
		<?php if ( isset( $_GET['ys_ec_newebpay_saved'] ) ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'NewebPay settings saved.', 'ys-cart-newebpay' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ysca-form">
			<input type="hidden" name="action" value="ys_ec_newebpay_save_settings">
			<?php wp_nonce_field( $nonce_action ); ?>

			<h2><?php esc_html_e( 'Merchant', 'ys-cart-newebpay' ); ?></h2>
			<div class="ysca-form-grid">
				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Enable NewebPay', 'ys-cart-newebpay' ); ?></span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['enabled'] ); ?>" value="1" <?php checked( '1', (string) $settings['enabled'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Sandbox mode', 'ys-cart-newebpay' ); ?></span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['test_mode'] ); ?>" value="1" <?php checked( '1', (string) $settings['test_mode'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Debug raw callback', 'ys-cart-newebpay' ); ?></span>
					<input type="checkbox" name="<?php echo esc_attr( $keys['debug_enabled'] ); ?>" value="1" <?php checked( '1', (string) $settings['debug_enabled'] ); ?>>
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Merchant ID', 'ys-cart-newebpay' ); ?></span>
					<input class="regular-text" type="text" name="<?php echo esc_attr( $keys['merchant_id'] ); ?>" value="<?php echo esc_attr( (string) $settings['merchant_id'] ); ?>" autocomplete="off">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Hash Key', 'ys-cart-newebpay' ); ?></span>
					<input class="regular-text" type="password" name="<?php echo esc_attr( $keys['hash_key'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_key_is_set ? __( 'Saved. Leave blank to keep current value.', 'ys-cart-newebpay' ) : '' ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Hash IV', 'ys-cart-newebpay' ); ?></span>
					<input class="regular-text" type="password" name="<?php echo esc_attr( $keys['hash_iv'] ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $hash_iv_is_set ? __( 'Saved. Leave blank to keep current value.', 'ys-cart-newebpay' ) : '' ); ?>">
				</label>
			</div>

			<h2><?php esc_html_e( 'Payment Methods', 'ys-cart-newebpay' ); ?></h2>
			<div class="ysca-form-grid">
				<?php
				$method_labels = [
					'credit_enabled'  => __( 'Credit card', 'ys-cart-newebpay' ),
					'inst_enabled'    => __( 'Installment', 'ys-cart-newebpay' ),
					'atm_enabled'     => __( 'ATM virtual account', 'ys-cart-newebpay' ),
					'cvs_enabled'     => __( 'CVS code', 'ys-cart-newebpay' ),
					'barcode_enabled' => __( 'Barcode', 'ys-cart-newebpay' ),
					'linepay_enabled' => __( 'LINE Pay', 'ys-cart-newebpay' ),
					'applepay_enabled'=> __( 'Apple Pay', 'ys-cart-newebpay' ),
				];
				foreach ( $method_labels as $alias => $label ) :
					?>
					<label class="ysca-field">
						<span class="ysca-field__label"><?php echo esc_html( $label ); ?></span>
						<input type="checkbox" name="<?php echo esc_attr( $keys[ $alias ] ); ?>" value="1" <?php checked( '1', (string) $settings[ $alias ] ); ?>>
					</label>
				<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'Limits and Expiry', 'ys-cart-newebpay' ); ?></h2>
			<div class="ysca-form-grid">
				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Global transaction limit (0 = provider default)', 'ys-cart-newebpay' ); ?></span>
					<input class="small-text" type="number" min="0" name="<?php echo esc_attr( $keys['trade_limit'] ); ?>" value="<?php echo esc_attr( (string) $settings['trade_limit'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Installment periods', 'ys-cart-newebpay' ); ?></span>
					<input class="regular-text" type="text" name="<?php echo esc_attr( $keys['inst_flag'] ); ?>" value="<?php echo esc_attr( (string) $settings['inst_flag'] ); ?>" placeholder="3,6,12">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'ATM expiry days', 'ys-cart-newebpay' ); ?></span>
					<input class="small-text" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['atm_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['atm_expire_days'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'CVS expiry days', 'ys-cart-newebpay' ); ?></span>
					<input class="small-text" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['cvs_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['cvs_expire_days'] ); ?>">
				</label>

				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'Barcode expiry days', 'ys-cart-newebpay' ); ?></span>
					<input class="small-text" type="number" min="1" max="180" name="<?php echo esc_attr( $keys['bar_expire_days'] ); ?>" value="<?php echo esc_attr( (string) $settings['bar_expire_days'] ); ?>">
				</label>
			</div>

			<h2><?php esc_html_e( 'Callback URLs', 'ys-cart-newebpay' ); ?></h2>
			<div class="ysca-form-grid">
				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'NotifyURL', 'ys-cart-newebpay' ); ?></span>
					<input class="large-text" type="text" readonly value="<?php echo esc_attr( $notify_url ); ?>">
				</label>
				<label class="ysca-field">
					<span class="ysca-field__label"><?php esc_html_e( 'ReturnURL', 'ys-cart-newebpay' ); ?></span>
					<input class="large-text" type="text" readonly value="<?php echo esc_attr( $return_url ); ?>">
				</label>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save NewebPay settings', 'ys-cart-newebpay' ); ?></button>
				<button type="button" class="button" id="ys-newebpay-test-connection"><?php esc_html_e( 'Check configuration', 'ys-cart-newebpay' ); ?></button>
				<span id="ys-newebpay-test-result" style="margin-left:8px;"></span>
			</p>
		</form>
	</div>
</div>
