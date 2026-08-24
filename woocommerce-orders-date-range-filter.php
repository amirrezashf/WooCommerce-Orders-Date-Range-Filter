<?php
/**
 * Plugin Name:       WooCommerce Orders Date Range Filter
 * Plugin URI:        https://github.com/amirrezashf/WooCommerce-Orders-Date-Range-Filter
 * Description:       Add custom and preset date-range filters with summary statistics to the WooCommerce admin orders list, with HPOS and legacy storage support.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Amirreza Shayesteh Far
 * Author URI:        https://github.com/amirrezashf
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wc-orders-date-range-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WC_Orders_Date_Range_Filter {

	const VERSION      = '1.0.0';
	const CACHE_PREFIX = 'wcodrf_stats_v1_';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
			return;
		}

		add_action( 'manage_posts_extra_tablenav', array( $this, 'render_legacy_controls' ), 20, 1 );
		add_action( 'woocommerce_order_list_table_extra_tablenav', array( $this, 'render_hpos_controls' ), 20, 1 );

		add_filter(
			'woocommerce_order_list_table_prepare_items_query_args',
			array( $this, 'filter_hpos_orders' ),
			20
		);

		add_action( 'pre_get_posts', array( $this, 'filter_legacy_orders' ), 20 );
	}

	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WooCommerce Orders Date Range Filter requires WooCommerce to be installed and active.', 'wc-orders-date-range-filter' );
		echo '</p></div>';
	}

	public function render_legacy_controls( $which ) {
		if ( 'top' !== $which || ! $this->can_manage_orders() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-shop_order' !== $screen->id ) {
			return;
		}

		$this->render_controls();
	}

	public function render_hpos_controls( $which ) {
		if ( 'top' !== $which || ! $this->can_manage_orders() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'woocommerce_page_wc-orders' !== $screen->id ) {
			return;
		}

		$this->render_controls();
	}

	private function can_manage_orders() {
		return is_admin() && current_user_can( 'manage_woocommerce' );
	}

	private function get_presets() {
		return array(
			'7d'  => '۷ روز اخیر',
			'14d' => '۱۴ روز اخیر',
			'1m'  => '۱ ماه اخیر',
			'2m'  => '۲ ماه اخیر',
			'3m'  => '۳ ماه اخیر',
			'4m'  => '۴ ماه اخیر',
			'5m'  => '۵ ماه اخیر',
			'6m'  => '۶ ماه اخیر',
			'1y'  => '۱ سال اخیر',
		);
	}

	private function render_controls() {
		$from  = isset( $_GET['wcodrf_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wcodrf_from'] ) ) : '';
		$to    = isset( $_GET['wcodrf_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wcodrf_to'] ) ) : '';
		$range = isset( $_GET['wcodrf_range'] ) ? sanitize_key( wp_unslash( $_GET['wcodrf_range'] ) ) : '';

		list( $resolved_from, $resolved_to ) = $this->get_requested_range();

		$clear_url = remove_query_arg(
			array(
				'wcodrf_from',
				'wcodrf_to',
				'wcodrf_range',
				'paged',
			)
		);
		?>
		<div class="wcodrf-root">
			<div class="wcodrf-controls">
				<label for="wcodrf_range"><?php esc_html_e( 'بازه آماده', 'wc-orders-date-range-filter' ); ?></label>

				<select id="wcodrf_range" name="wcodrf_range">
					<option value=""><?php esc_html_e( 'انتخاب کنید…', 'wc-orders-date-range-filter' ); ?></option>
					<?php foreach ( $this->get_presets() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $range, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="wcodrf_from"><?php esc_html_e( 'از', 'wc-orders-date-range-filter' ); ?></label>
				<input type="date" id="wcodrf_from" name="wcodrf_from" value="<?php echo esc_attr( $from ); ?>">

				<label for="wcodrf_to"><?php esc_html_e( 'تا', 'wc-orders-date-range-filter' ); ?></label>
				<input type="date" id="wcodrf_to" name="wcodrf_to" value="<?php echo esc_attr( $to ); ?>">

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'اعمال', 'wc-orders-date-range-filter' ); ?>
				</button>

				<a href="<?php echo esc_url( $clear_url ); ?>" class="button">
					<?php esc_html_e( 'پاکسازی', 'wc-orders-date-range-filter' ); ?>
				</a>
			</div>

			<?php
			if ( '' !== $resolved_from && '' !== $resolved_to ) {
				$this->render_stats_box( $resolved_from, $resolved_to );
			}
			?>
		</div>

		<style>
			.wcodrf-root{display:block;width:100%;clear:both;margin:12px 0 0}
			.wcodrf-controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;box-sizing:border-box;margin:0 0 10px;padding:10px 14px;border:1px solid #dcdcde;border-radius:7px;background:#fff}
			.wcodrf-controls label{font-weight:600}
			.wcodrf-controls select{min-width:170px}
			.wcodrf-controls input[type="date"]{min-width:155px}
			.wcodrf-stats{display:block;width:100%;box-sizing:border-box;margin:0 0 12px;padding:16px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
			.wcodrf-stats__head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}
			.wcodrf-stats__title{font-size:14px;font-weight:700}
			.wcodrf-stats__range{padding:4px 9px;border-radius:999px;background:#f0f0f1;color:#50575e;font-size:12px}
			.wcodrf-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:14px}
			.wcodrf-metric{padding:10px 12px;border:1px solid #e2e4e7;border-radius:6px;background:#f9f9f9}
			.wcodrf-metric__label{margin-bottom:4px;color:#646970;font-size:11px}
			.wcodrf-metric__value{color:#1d2327;font-size:17px;font-weight:700;line-height:1.7}
			.wcodrf-subtitle{margin:0 0 8px;font-size:13px;font-weight:700}
			.wcodrf-statuses{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
			.wcodrf-status{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid #dcdcde;border-radius:999px;background:#fff}
			.wcodrf-products{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:8px}
			.wcodrf-product{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid #e2e4e7;border-radius:6px;background:#fff}
			.wcodrf-product__main{display:flex;align-items:flex-start;gap:9px;min-width:0}
			.wcodrf-product__rank{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:#f0f0f1;font-size:12px;font-weight:700}
			.wcodrf-product__name{line-height:1.7;word-break:break-word}
		</style>

		<script>
		(function(){
			const select = document.getElementById('wcodrf_range');
			const fromInput = document.getElementById('wcodrf_from');
			const toInput = document.getElementById('wcodrf_to');

			if (!select || !fromInput || !toInput) return;

			function pad(number) {
				return number < 10 ? '0' + number : String(number);
			}

			function formatDate(date) {
				return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
			}

			function subtractMonthsClamped(date, months) {
				const originalDay = date.getDate();
				const target = new Date(date.getFullYear(), date.getMonth(), 1);
				target.setMonth(target.getMonth() - months);

				const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
				target.setDate(Math.min(originalDay, lastDay));

				return target;
			}

			select.addEventListener('change', function(){
				const value = this.value;
				if (!value) return;

				const now = new Date();
				const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
				let start = new Date(today.getTime());

				if (value === '7d') {
					start.setDate(start.getDate() - 6);
				} else if (value === '14d') {
					start.setDate(start.getDate() - 13);
				} else if (/^[1-6]m$/.test(value)) {
					start = subtractMonthsClamped(today, parseInt(value, 10));
					start.setDate(start.getDate() + 1);
				} else if (value === '1y') {
					start = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
					start.setDate(start.getDate() + 1);
				} else {
					return;
				}

				fromInput.value = formatDate(start);
				toInput.value = formatDate(today);
			});
		})();
		</script>
		<?php
	}

	public function filter_hpos_orders( $query_args ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $query_args;
		}

		list( $from, $to ) = $this->get_requested_range();

		if ( '' === $from || '' === $to ) {
			return $query_args;
		}

		$range = $this->get_datetime_range( $from, $to );

		if ( empty( $range['from'] ) || empty( $range['to'] ) ) {
			return $query_args;
		}

		if ( empty( $query_args['date_query'] ) || ! is_array( $query_args['date_query'] ) ) {
			$query_args['date_query'] = array();
		}

		$query_args['date_query'][] = array(
			'after'     => $range['from'],
			'before'    => $range['to'],
			'inclusive' => true,
		);

		return $query_args;
	}

	public function filter_legacy_orders( $query ) {
		if (
			! is_admin()
			|| ! $query instanceof WP_Query
			|| ! $query->is_main_query()
			|| ! current_user_can( 'manage_woocommerce' )
		) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-shop_order' !== $screen->id ) {
			return;
		}

		list( $from, $to ) = $this->get_requested_range();

		if ( '' === $from || '' === $to ) {
			return;
		}

		$range = $this->get_datetime_range( $from, $to );

		if ( empty( $range['from'] ) || empty( $range['to'] ) ) {
			return;
		}

		$query->set(
			'date_query',
			array(
				array(
					'column'    => 'post_date',
					'after'     => $range['from'],
					'before'    => $range['to'],
					'inclusive' => true,
				),
			)
		);
	}

	private function get_requested_range() {
		$from  = isset( $_GET['wcodrf_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wcodrf_from'] ) ) : '';
		$to    = isset( $_GET['wcodrf_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wcodrf_to'] ) ) : '';
		$range = isset( $_GET['wcodrf_range'] ) ? sanitize_key( wp_unslash( $_GET['wcodrf_range'] ) ) : '';

		if ( $range && ( '' === $from || '' === $to ) ) {
			$resolved = $this->resolve_preset( $range );

			if ( empty( $resolved ) ) {
				return array( '', '' );
			}

			$from = $resolved['from'];
			$to   = $resolved['to'];
		}

		if ( '' !== $from && '' === $to ) {
			$to = $from;
		} elseif ( '' === $from && '' !== $to ) {
			$from = $to;
		}

		if ( ! $this->is_valid_date( $from ) || ! $this->is_valid_date( $to ) ) {
			return array( '', '' );
		}

		if ( $from > $to ) {
			$temp = $from;
			$from = $to;
			$to   = $temp;
		}

		return array( $from, $to );
	}

	private function resolve_preset( $range ) {
		if ( ! isset( $this->get_presets()[ $range ] ) ) {
			return array();
		}

		$tz    = wp_timezone();
		$today = new DateTimeImmutable( current_datetime()->format( 'Y-m-d' ) . ' 00:00:00', $tz );
		$start = $today;

		if ( '7d' === $range ) {
			$start = $today->sub( new DateInterval( 'P6D' ) );
		} elseif ( '14d' === $range ) {
			$start = $today->sub( new DateInterval( 'P13D' ) );
		} elseif ( preg_match( '/^([1-6])m$/', $range, $matches ) ) {
			$start = $this->subtract_months_clamped( $today, (int) $matches[1] )->add( new DateInterval( 'P1D' ) );
		} elseif ( '1y' === $range ) {
			$start = $this->subtract_year_clamped( $today )->add( new DateInterval( 'P1D' ) );
		}

		return array(
			'from' => $start->format( 'Y-m-d' ),
			'to'   => $today->format( 'Y-m-d' ),
		);
	}

	private function subtract_months_clamped( DateTimeImmutable $date, $months ) {
		$months       = max( 1, absint( $months ) );
		$original_day = (int) $date->format( 'j' );

		$target = $date
			->modify( 'first day of this month' )
			->sub( new DateInterval( 'P' . $months . 'M' ) );

		$last_day = (int) $target->format( 't' );
		$day      = min( $original_day, $last_day );

		return $target->setDate(
			(int) $target->format( 'Y' ),
			(int) $target->format( 'n' ),
			$day
		);
	}

	private function subtract_year_clamped( DateTimeImmutable $date ) {
		$year  = (int) $date->format( 'Y' ) - 1;
		$month = (int) $date->format( 'n' );
		$day   = (int) $date->format( 'j' );

		$probe = new DateTimeImmutable(
			sprintf( '%04d-%02d-01', $year, $month ),
			$date->getTimezone()
		);

		$last_day = (int) $probe->format( 't' );

		return $date->setDate( $year, $month, min( $day, $last_day ) );
	}

	private function is_valid_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );

		return $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value;
	}

	private function get_datetime_range( $from, $to ) {
		if ( ! $this->is_valid_date( $from ) || ! $this->is_valid_date( $to ) ) {
			return array(
				'from' => '',
				'to'   => '',
			);
		}

		$tz = wp_timezone();

		try {
			$from_datetime = new DateTimeImmutable( $from . ' 00:00:00', $tz );
			$to_datetime   = new DateTimeImmutable( $to . ' 23:59:59', $tz );
		} catch ( Exception $exception ) {
			return array(
				'from' => '',
				'to'   => '',
			);
		}

		return array(
			'from' => $from_datetime->format( 'Y-m-d H:i:s' ),
			'to'   => $to_datetime->format( 'Y-m-d H:i:s' ),
		);
	}

	private function render_stats_box( $from, $to ) {
		$stats = $this->get_stats_for_range( $from, $to );

		if ( empty( $stats ) || ! is_array( $stats ) ) {
			return;
		}

		$metrics = array(
			'تعداد سفارش'                  => number_format_i18n( (int) $stats['order_count'] ),
			'مشتریان یکتا'                 => number_format_i18n( (int) $stats['unique_customers'] ),
			'تعداد کل محصولات خریداری‌شده' => number_format_i18n( (int) $stats['total_items_qty'] ),
			'تنوع محصولات'                 => number_format_i18n( (int) $stats['unique_products'] ),
		);
		?>
		<div class="wcodrf-stats">
			<div class="wcodrf-stats__head">
				<div class="wcodrf-stats__title"><?php esc_html_e( 'آمار بازه انتخاب‌شده', 'wc-orders-date-range-filter' ); ?></div>
				<div class="wcodrf-stats__range">
					<?php echo esc_html( sprintf( 'از %1$s تا %2$s', $from, $to ) ); ?>
				</div>
			</div>

			<div class="wcodrf-metrics">
				<?php foreach ( $metrics as $label => $value ) : ?>
					<div class="wcodrf-metric">
						<div class="wcodrf-metric__label"><?php echo esc_html( $label ); ?></div>
						<div class="wcodrf-metric__value"><?php echo esc_html( $value ); ?></div>
					</div>
				<?php endforeach; ?>

				<div class="wcodrf-metric">
					<div class="wcodrf-metric__label"><?php esc_html_e( 'مبلغ کل سفارش‌ها', 'wc-orders-date-range-filter' ); ?></div>
					<div class="wcodrf-metric__value"><?php echo wp_kses_post( wc_price( (float) $stats['total_order_value'] ) ); ?></div>
				</div>

				<div class="wcodrf-metric">
					<div class="wcodrf-metric__label"><?php esc_html_e( 'میانگین مبلغ هر سفارش', 'wc-orders-date-range-filter' ); ?></div>
					<div class="wcodrf-metric__value"><?php echo wp_kses_post( wc_price( (float) $stats['average_order_total'] ) ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $stats['status_counts'] ) ) : ?>
				<?php arsort( $stats['status_counts'] ); ?>
				<div class="wcodrf-subtitle"><?php esc_html_e( 'وضعیت سفارش‌ها', 'wc-orders-date-range-filter' ); ?></div>
				<div class="wcodrf-statuses">
					<?php foreach ( $stats['status_counts'] as $status_key => $count ) : ?>
						<span class="wcodrf-status">
							<span><?php echo esc_html( $this->get_order_status_label( $status_key ) ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $stats['top_products'] ) ) : ?>
				<div class="wcodrf-subtitle"><?php esc_html_e( '۵ محصول پرفروش این بازه', 'wc-orders-date-range-filter' ); ?></div>
				<div class="wcodrf-products">
					<?php
					$rank = 1;
					foreach ( $stats['top_products'] as $product_row ) :
						?>
						<div class="wcodrf-product">
							<div class="wcodrf-product__main">
								<span class="wcodrf-product__rank"><?php echo esc_html( (string) $rank ); ?></span>
								<span class="wcodrf-product__name"><?php echo esc_html( $product_row['name'] ); ?></span>
							</div>
							<strong><?php echo esc_html( number_format_i18n( (int) $product_row['qty'] ) ); ?></strong>
						</div>
						<?php
						$rank++;
					endforeach;
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_stats_for_range( $from, $to ) {
		$cache_key = self::CACHE_PREFIX . md5( $from . '|' . $to . '|' . get_woocommerce_currency() );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$range = $this->get_datetime_range( $from, $to );

		if ( empty( $range['from'] ) || empty( $range['to'] ) ) {
			return array();
		}

		$stats = array(
			'order_count'         => 0,
			'total_items_qty'     => 0,
			'unique_products'     => 0,
			'unique_customers'    => 0,
			'total_order_value'   => 0.0,
			'average_order_total' => 0.0,
			'status_counts'       => array(),
			'top_products'        => array(),
		);

		$unique_products  = array();
		$unique_customers = array();
		$top_products     = array();

		$page     = 1;
		$per_page = 200;
		$statuses = array_keys( wc_get_order_statuses() );

		do {
			$results = wc_get_orders(
				array(
					'type'         => 'shop_order',
					'limit'        => $per_page,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'date_created' => $range['from'] . '...' . $range['to'],
					'status'       => $statuses,
				)
			);

			if (
				! is_object( $results )
				|| empty( $results->orders )
				|| ! is_array( $results->orders )
			) {
				break;
			}

			foreach ( $results->orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}

				$order_id = $order->get_id();

				$stats['order_count']++;

				$status = $order->get_status();

				if ( ! isset( $stats['status_counts'][ $status ] ) ) {
					$stats['status_counts'][ $status ] = 0;
				}

				$stats['status_counts'][ $status ]++;
				$stats['total_order_value'] += (float) $order->get_total();

				$customer_id = (int) $order->get_customer_id();

				if ( $customer_id > 0 ) {
					$unique_customers[ 'user_' . $customer_id ] = true;
				} else {
					$email = strtolower( trim( (string) $order->get_billing_email() ) );
					$phone = trim( (string) $order->get_billing_phone() );

					if ( '' !== $email ) {
						$unique_customers[ 'email_' . md5( $email ) ] = true;
					} elseif ( '' !== $phone ) {
						$unique_customers[ 'phone_' . md5( $phone ) ] = true;
					} else {
						$unique_customers[ 'guest_order_' . $order_id ] = true;
					}
				}

				foreach ( $order->get_items( 'line_item' ) as $item ) {
					if ( ! $item instanceof WC_Order_Item_Product ) {
						continue;
					}

					$quantity = max( 0, (int) $item->get_quantity() );
					$stats['total_items_qty'] += $quantity;

					$product_id = absint( $item->get_product_id() );
					$name       = trim( (string) $item->get_name() );

					if ( $product_id > 0 ) {
						$unique_products[ $product_id ] = true;

						if ( ! isset( $top_products[ $product_id ] ) ) {
							$product = wc_get_product( $product_id );

							$top_products[ $product_id ] = array(
								'id'   => $product_id,
								'name' => $product ? $product->get_name() : ( $name ? $name : 'محصول بدون نام' ),
								'qty'  => 0,
							);
						}

						$top_products[ $product_id ]['qty'] += $quantity;
					} else {
						$fallback_key = 'fallback_' . md5( $name );

						if ( ! isset( $top_products[ $fallback_key ] ) ) {
							$top_products[ $fallback_key ] = array(
								'id'   => 0,
								'name' => $name ? $name : 'محصول بدون نام',
								'qty'  => 0,
							);
						}

						$top_products[ $fallback_key ]['qty'] += $quantity;
					}
				}
			}

			$max_pages = isset( $results->max_num_pages ) ? absint( $results->max_num_pages ) : 0;
			$page++;

		} while ( $page <= $max_pages );

		$stats['unique_products']  = count( $unique_products );
		$stats['unique_customers'] = count( $unique_customers );

		if ( $stats['order_count'] > 0 ) {
			$stats['average_order_total'] = $stats['total_order_value'] / $stats['order_count'];
		}

		if ( ! empty( $top_products ) ) {
			uasort(
				$top_products,
				static function ( $left, $right ) {
					$left_qty  = isset( $left['qty'] ) ? (int) $left['qty'] : 0;
					$right_qty = isset( $right['qty'] ) ? (int) $right['qty'] : 0;

					if ( $left_qty === $right_qty ) {
						return 0;
					}

					return $left_qty > $right_qty ? -1 : 1;
				}
			);

			$stats['top_products'] = array_slice( array_values( $top_products ), 0, 5 );
		}

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	private function get_order_status_label( $status_key ) {
		$status_key = sanitize_key( (string) $status_key );
		$statuses   = wc_get_order_statuses();
		$wc_key     = 0 === strpos( $status_key, 'wc-' ) ? $status_key : 'wc-' . $status_key;

		return isset( $statuses[ $wc_key ] )
			? $statuses[ $wc_key ]
			: ( $status_key ? $status_key : 'نامشخص' );
	}
}

WC_Orders_Date_Range_Filter::instance();
