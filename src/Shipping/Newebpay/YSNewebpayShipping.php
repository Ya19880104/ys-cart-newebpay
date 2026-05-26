<?php

namespace YangSheep\YSCartNewebpay\Shipping\Newebpay;

use YangSheep\Ecommerce\Shipping\YSShippingInterface;
use YangSheep\YSCartNewebpay\Gateway\Newebpay\YSNewebpaySettings;

defined( 'ABSPATH' ) || exit;

abstract class YSNewebpayShipping implements YSShippingInterface {
	protected string $id = '';
	protected string $title = '';
	protected string $type = 'cvs';

	public function get_id(): string {
		return $this->id;
	}

	public function get_title(): string {
		return $this->title;
	}

	public function get_provider(): string {
		return 'newebpay';
	}

	public function get_type(): string {
		return 'cvs';
	}

	public function is_enabled(): bool {
		return YSNewebpaySettings::is_logistics_method_enabled( $this->id );
	}

	public function is_available( array $order_data ): bool {
		if ( ! $this->is_enabled() || ! YSNewebpaySettings::is_global_enabled() ) {
			return false;
		}

		if ( '' === YSNewebpaySettings::get( 'merchant_id', '' )
			|| '' === YSNewebpaySettings::get( 'hash_key', '' )
			|| '' === YSNewebpaySettings::get( 'hash_iv', '' )
		) {
			return false;
		}

		$subtotal   = $this->calculate_subtotal( $order_data['cart_items'] ?? [] );
		$max_amount = (float) $this->get_option( 'max_amount', 20000 );
		if ( $max_amount > 0 && $subtotal > $max_amount ) {
			return false;
		}

		$total_weight = $this->calculate_total_weight( $order_data['cart_items'] ?? [] );
		$max_weight   = (float) $this->get_option( 'max_weight', $this->get_default_max_weight() );
		if ( $max_weight > 0 && $total_weight > $max_weight ) {
			return false;
		}

		return true;
	}

	public function calculate_cost( array $cart_items, array $address = [] ): float {
		unset( $address );

		$subtotal       = $this->calculate_subtotal( $cart_items );
		$free_threshold = $this->get_free_threshold();
		if ( $free_threshold > 0 && $subtotal >= $free_threshold ) {
			return 0.0;
		}

		return (float) $this->get_option( 'base_fee', 60 );
	}

	public function get_free_threshold(): float {
		return (float) $this->get_option( 'free_threshold', 0 );
	}

	public function supports_cvs_selection(): bool {
		return true;
	}

	public function supports_cod(): bool {
		return true;
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function get_settings_fields(): array {
		return [
			'enabled'        => [
				'type'    => 'checkbox',
				'label'   => '啟用',
				'default' => '0',
			],
			'base_fee'       => [
				'type'    => 'number',
				'label'   => '基本運費',
				'default' => '60',
				'desc'    => '此物流方式的固定運費。',
			],
			'free_threshold' => [
				'type'    => 'number',
				'label'   => '免運門檻',
				'default' => '0',
				'desc'    => '0 表示不啟用免運。',
			],
			'max_amount'     => [
				'type'    => 'number',
				'label'   => '最高訂單金額',
				'default' => '20000',
				'desc'    => '藍新超商取貨通常有最高金額限制。',
			],
			'max_weight'     => [
				'type'    => 'number',
				'label'   => '最高重量 kg',
				'default' => (string) $this->get_default_max_weight(),
				'desc'    => '0 表示不限制重量。',
			],
		];
	}

	/**
	 * @return array<int,string>
	 */
	public function get_supported_countries(): array {
		return [ 'TW' ];
	}

	abstract public function get_lgs_type(): string;

	abstract public function get_ship_type(): string;

	protected function get_default_max_weight(): float {
		return 5.0;
	}

	public function get_trade_type(): string {
		return '3';
	}

	public function get_carrier_label(): string {
		return match ( $this->get_ship_type() ) {
			'1' => '7-ELEVEN',
			'2' => '全家',
			'3' => '萊爾富',
			'4' => 'OK mart',
			default => '超商',
		};
	}

	/**
	 * @param mixed $default
	 * @return mixed
	 */
	protected function get_option( string $key, $default = '' ) {
		global $wpdb;

		if ( ! defined( 'YS_ECOMMERCE_TABLE_PREFIX' ) || ! isset( $wpdb ) ) {
			return $default;
		}

		$table      = $wpdb->prefix . YS_ECOMMERCE_TABLE_PREFIX . 'settings';
		$option_key = 'shipping_' . $this->id . '_' . $key;
		$value      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_key = %s",
				$option_key
			)
		);

		return null !== $value ? $value : $default;
	}

	/**
	 * @param array<int|string,array<string,mixed>|object> $cart_items
	 */
	protected function calculate_subtotal( array $cart_items ): float {
		$subtotal = 0.0;
		foreach ( $cart_items as $item ) {
			$price    = is_array( $item ) ? (float) ( $item['price'] ?? 0 ) : (float) ( $item->price ?? 0 );
			$quantity = is_array( $item ) ? (int) ( $item['quantity'] ?? 1 ) : (int) ( $item->quantity ?? 1 );
			$subtotal += $price * max( 1, $quantity );
		}

		return $subtotal;
	}

	/**
	 * @param array<int|string,array<string,mixed>|object> $cart_items
	 */
	protected function calculate_total_weight( array $cart_items ): float {
		$total = 0.0;
		foreach ( $cart_items as $item ) {
			$weight   = is_array( $item ) ? (float) ( $item['weight'] ?? 0 ) : (float) ( $item->weight ?? 0 );
			$quantity = is_array( $item ) ? (int) ( $item['quantity'] ?? 1 ) : (int) ( $item->quantity ?? 1 );
			$total += $weight * max( 1, $quantity );
		}

		return $total;
	}
}
