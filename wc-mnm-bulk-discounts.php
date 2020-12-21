<?php
/**
* Plugin Name: WooCommerce Mix and Match - Bulk Discounts BAK
* Plugin URI: https://github.com/kathyisawesome/wc-mnm-bulk-discounts
* Description: Bulk quantity discounts for WooCommerce Mix and Match Products.
* Version: 1.0.0-beta-1
* Author: Kathy Darling
* Author URI: https://kathyisawesome.com/
*
* Text Domain: wc-mnm-bulk-discounts
* Domain Path: /languages/
*
* Requires at least: 4.4
* Tested up to: 5.5
* Requires PHP: 5.6

* WC requires at least: 3.1
* WC tested up to: 4.4
*
* Copyright: © 2020 Backcourt Development.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_MNM_Bulk_Discounts {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public static $version = '1.0.0-beta-1';

	/**
	 * Min required PB version.
	 *
	 * @var string
	 */
	public static $req_mnm_version = '1.4.0';

	/**
	 * PB URL.
	 *
	 * @var string
	 */
	private static $mnm_url = 'https://woocommerce.com/products/woocommerce-mix-and-match-products/';

	/**
	 * Discount data array for access via filter callbacks -- internal use only.
	 *
	 * @var array
	 */
	public static $discount_data_array = array();

	/**
	 * Total min Qty for access via filter callbacks -- internal use only.
	 *
	 * @var array
	 */
	public static $total_min_quantity = 0;

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename(__FILE__) );
	}

	/**
	 * Plugin path.
	 *
	 * @return string
	 */
	public static function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Fire in the hole!
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ) );
	}

	/**
	 * Hooks.
	 */
	public static function load_plugin() {

		// Check dependencies.
		if ( ! function_exists( 'WC_Mix_and_Match' ) || version_compare( WC_Mix_and_Match()->version, self::$req_mnm_version ) < 0 ) {
			add_action( 'admin_notices', array( __CLASS__, 'version_notice' ) );
			return false;
		}

		/*
		 * Admin.
		 */
		// Admin styles and scripts.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
		
		// Display quantity discount option.
		add_action( 'woocommerce_mnm_product_options', array( __CLASS__, 'additional_container_option' ), 32, 2 );
		add_action( 'woocommerce_mnm_product_options', array( __CLASS__, 'discount_pricing_options' ), 35, 2 );

		// Save discount data.
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_meta' ) );

		/*
		 * Cart.
		 */

		// Apply discount to bundled cart items.
		add_filter( 'woocommerce_mnm_cart_item', array( __CLASS__, 'child_cart_item_discount' ), -10, 2 );

		// Apply discount to container cart items.
		add_filter( 'woocommerce_mnm_container_cart_item', array( __CLASS__, 'container_cart_item_discount' ), 10, 2 );

		/*
		 * Products / Catalog.
		 */

		// Modify the catalog price to include discounts for the default min quantities.
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'get_discounted_price_html' ), 10000, 2 );

		// Add a suffix to bundled product prices.
		add_action( 'woocommerce_bundled_product_price_filters_added', array( __CLASS__, 'add_container_discount_price_html_suffix' ), 10, 1 );
		add_action( 'woocommerce_bundled_product_price_filters_removed', array( __CLASS__, 'remove_container_discount_price_html_suffix' ), 10, 1 );

		// Bootstrapping.
		add_action( 'woocommerce_mix-and-match_add_to_cart', array( __CLASS__, 'frontend_script' ) );

		// Add parameters to bundle price data.
		add_filter( 'woocommerce_bundle_price_data', array( __CLASS__, 'add_discount_data' ), 10, 2 );

		// Parameters passed to the script.
		add_filter( 'woocommerce_bundle_front_end_params', array( __CLASS__, 'add_front_end_params' ), 10, 1 );

		// Localization.
		add_action( 'init', array( __CLASS__, 'localize_plugin' ) );
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public static function localize_plugin() {
		load_plugin_textdomain( 'wc-mnm-bulk-discounts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Application layer.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Calculates a discounted price based on quantity and a discount data array.
	 *
	 * @param  integer  $total_quantity
	 * @param  array    $discount_data_array
	 * @return mixed
	 */
	public static function get_discount( $total_quantity, $discount_data_array ) {

		$discount = 0.0;

		foreach ( $discount_data_array as $lines ) {

			if ( isset( $lines[ 'quantity_min' ], $lines[ 'quantity_max' ], $lines[ 'discount' ] ) ) {

				$quantity_min = $lines[ 'quantity_min' ];
				$quantity_max = $lines[ 'quantity_max' ];

				if ( $total_quantity >= $quantity_min && $total_quantity <= $quantity_max ) {
					$discount = $lines[ 'discount' ];
					break;
				}
			}
		}

		return $discount;
	}

	/**
	 * Calculates a discounted price based on quantity and a discount data array.
	 *
	 * @param  mixed  $price
	 * @param  float  $discount
	 * @return mixed
	 */
	public static function get_discounted_price( $price, $discount ) {
		return $discount ? (double) ( ( 100 - $discount ) * $price ) / 100 : $price;
	}

	/**
	 * Decodes a discount data array to a human-readable format.
	 *
	 * @param  array   $discount_data_array
	 * @return string  $discount_data_string
	 */
	private static function decode( $discount_data_array ) {

		$discount_data_string = '';

		if ( ! empty( $discount_data_array ) ) {
			foreach ( $discount_data_array as $i => $discount_line) {

				if ( $discount_line[ 'quantity_min' ] === $discount_line[ 'quantity_max'] ) {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} elseif ( is_infinite( $discount_line[ 'quantity_max' ] ) ) {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . '+' . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				} else {
					$discount_data_string = $discount_data_string . $discount_line[ 'quantity_min' ] . ' ' . '-' . ' ' . $discount_line[ 'quantity_max' ] . ' ' . '|' . ' ' . $discount_line[ 'discount' ]. "\n";
				}
			}
		}

		return $discount_data_string;
	}

	/**
	 * Encodes $input_data string to array by separating quantity_min, quantity_max and discount.
	 *
	 * @param  string  $input_data
	 * @return array   $parsed_discount_data
	 */
	private static function encode( $input_data ) {

		$parsed_discount_data = array();
		$input_data           = wc_sanitize_textarea( $input_data );

		if ( ! empty( $input_data ) ) {

			$input_data = array_filter( array_map( 'trim', explode( "\n", $input_data ) ) );

			// Explode based on "|".
			foreach ( $input_data as $discount_line ) {

				$line_error_notice_added        = false;
				$discount_line_seperator_pieces = array_map( 'trim', explode( "|", $discount_line ) );

				// Validate that only one "|" exist in each line.
				if ( 2 !== sizeof( $discount_line_seperator_pieces ) ) {

					if ( ! $line_error_notice_added ) {
						WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'wc-mnm-bulk-discounts' ), $discount_line ), 'error' );
						$line_error_notice_added = true;
						continue;
					}
				}

				$discount_line_dash_pieces = array_map( 'trim', explode( "-", $discount_line_seperator_pieces[ 0 ] ) );

				// Validate that only at most one "-" exist in each line.
				if ( sizeof( $discount_line_dash_pieces ) > 2 ) {

					if ( ! $line_error_notice_added ) {

						WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
						$line_error_notice_added = true;
						continue;
					}

				} else {

					if ( 2 === sizeof( $discount_line_dash_pieces ) ) {

						$quantity_min = $discount_line_dash_pieces[0];
						$quantity_max = $discount_line_dash_pieces[1];

					} else {

						$quantity_min = $discount_line_dash_pieces[0];
						$quantity_max = $quantity_min;

						if ( '+' === substr( $quantity_min, -1 ) ) {
							$quantity_min = rtrim( $quantity_min, '+ ' );
							$quantity_max = INF;
						}
					}

					if ( is_numeric( $quantity_min ) && is_numeric( $quantity_max )  ) {

						if ( ! empty( $parsed_discount_data ) ) {

							// Check for overlap.
							foreach ( $parsed_discount_data as $lines ) {

								if ( $lines[ 'quantity_min' ] <= $quantity_min && $lines[ 'quantity_max' ] >= $quantity_min ) {

									if ( ! $line_error_notice_added ) {

										WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
										$line_error_notice_added = true;
										continue 2;
									}

								} elseif ( $lines[ 'quantity_min' ] <= $quantity_max && $lines[ 'quantity_max' ] >= $quantity_max ) {

									if ( ! $line_error_notice_added ) {

										WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
										$line_error_notice_added = true;
										continue 2;
									}

								} elseif ( $lines[ 'quantity_min' ] >= $quantity_min && $lines[ 'quantity_max' ] <= $quantity_max ) {

									if ( ! $line_error_notice_added ) {
										WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Overlapping data.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
										$line_error_notice_added = true;
										continue 2;
									}
								}
							}
						}

						if ( 0 > $discount_line_seperator_pieces[1] || 100 < $discount_line_seperator_pieces[1] ) {

							if ( ! $line_error_notice_added ) {

								WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid discount.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
								$line_error_notice_added = true;
								continue;
							}
						}

						if ( is_infinite( $quantity_max ) ) {

							$parsed_discount_data[] = array(
								'quantity_min' => intval( $quantity_min ),
								'quantity_max' => INF,
								'discount'     => floatval( $discount_line_seperator_pieces[1])
							);

						} else {

							$parsed_discount_data[] = array(
								'quantity_min' => intval( $quantity_min ),
								'quantity_max' => intval( $quantity_max ),
								'discount'     => floatval( $discount_line_seperator_pieces[1])
							);
						}


					// Non numeric data entered.
					} else {

						if ( ! $line_error_notice_added ) {

							WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Line <strong> %s </strong> not saved. Invalid format.', 'wc-mnm-bulk-discounts' ), $discount_line ) );
							$line_error_notice_added = true;
							continue;
						}
					}
				}
			}
		}

		return $parsed_discount_data;
	}

	/**
	 * Whether to apply discounts to the base price.
	 *
	 * @param  array    $container
	 * @return boolean
	 */
	public static function apply_discount_to_base_price( $container ) {
		/**
		 * 'wc_mnm_bulk_discount_apply_to_base_price' filter.
		 *
		 * @param  bool               $apply
		 * @param  WC_Product_Mix_and_Match  $container
		 */
		return apply_filters( 'wc_mnm_bulk_discount_apply_to_base_price', false, $container );
	}

	/*
	|--------------------------------------------------------------------------
	| Admin and Metaboxes.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Front-end script.
	 *
	 * @param  array  $dependencies
	 */
	public static function admin_scripts() {

		// Get admin screen id.
		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		/*
		 * Enqueue styles and scripts.
		 */
		if ( 'product' === $screen_id ) {

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			$script_asset_path = self::plugin_url() . '/assets/build/index.asset.php';

			$script_info       = file_exists( $script_asset_path )
            ? include $script_asset_path
            : [ 'dependencies' => [], 'version' => self::$version ];

			wp_enqueue_script(
				'wc-mnm-bulk-discount-metabox',
				self::plugin_url() . '/assets/build/index.js',
				$script_info['dependencies'],
				$script_info['version'],
				true
			);

			wp_enqueue_style(
				'wc-mnm-bulk-discount-admin-styles',
				self::plugin_url() . '/assets/build/style.css',
				false, $script_info['version']
			);

		}	

	}

	/**
	 * PB version check notice.
	 */
	public static function version_notice() {
		echo '<div class="error"><p>' . sprintf( __( '<strong>Mix and Match Products &ndash; Bulk Discounts</strong> requires <a href="%1$s" target="_blank">WooCommerce Mix and Match Products</a> version <strong>%2$s</strong> or higher.', 'wc-mnm-bulk-discounts' ), self::$mnm_url, self::$req_mnm_version ) . '</p></div>';
	}


	/**
	 * Adds the writepanel options.
	 *
	 * @param int $post_id
	 * @param  WC_Product_Mix_and_Match  $mnm_product_object
	 */
	public static function additional_container_option( $post_id, $mnm_product_object ) {

		woocommerce_wp_radio( 
			array(
				'id'            => 'wc_mnm_discount_mode',
				'class'         => 'select short mnm_discount_mode',
				'wrapper_class' => 'show_if_per_item_pricing',
				'label'         => __( 'Discount mode', 'wc-mnm-bulk-discounts' ),
				'value'	        => $mnm_product_object->get_meta( '_wc_mnm_discount_mode' ) === 'bulk' ? 'bulk' : '',
				'options'       => array( 
					''     => __( 'Flat discount', 'wc-mnm-bulk-discounts' ),
					'bulk' => __( 'Scaling discount', 'wc-mnm-bulk-discounts' )
				)
			)
		);

	}

	/**
	 * Add bundle quantity discount option.
	 *
	 * @param  int $post_id
	 * @param  WC_Mix_and_Match  $mnm_product_object
	 * @return void
	 */
	public static function discount_pricing_options( $post_id, $mnm_product_object ) { 

		$discount_data_array  = $mnm_product_object->get_meta( '_wc_mnm_bulk_discount_data', true );

		if ( ! is_array( $discount_data_array ) ) {
			$discount_data_array = array(
				array(
					'min'    => '',
					'max'    => '',
					'amount' => '',
				)
			);
		}

		$json = json_encode( $discount_data_array );

		?>

		<fieldset class="form-field _mnm_per_product_discount_field hide_if_static_pricing show_if_bulk_discount_mode" >

			<label><?php _e( 'Bulk Discounts', 'wc-mnm-bulk-discounts' ); ?></label>

			<table id="wc_mnm_bulk_discount_data" data-discountdata="<?php echo esc_attr( $json );?> ">
				<thead>
					<th><?php esc_html_e( 'Minimum Quantity', 'wc-mnm-bulk-discounts' );?></th>
					<th><?php esc_html_e( 'Maximum Quantity', 'wc-mnm-bulk-discounts' );?></th>
					<th><?php esc_html_e( 'Discount (%)', 'wc-mnm-bulk-discounts' );?></th>
					<th><?php echo "&nbsp"; ?></th>
				</thead>
				<tbody>
				</tbody>

			</table>

		</fieldset>


		<script type="text/html" id="tmpl-wc-mnm-discount-rule">
			
			<td><input type="number" name="wc_mnm_bulk_discount_data['min']" value="{{{ data.min }}}" /></td>
			<td><input type="number" name="wc_mnm_bulk_discount_data['max']" value="{{{ data.max }}}" /></td>
			<td><input type="text" name="wc_mnm_bulk_discount_data['amount']" value="{{{ data.amount }}}" class="wc_input_decimal" /></td>
			<td width="48">
				<button type="button" class="mnm-add-discount-rule dashicons-plus-alt dashicons-before"></button>
				<button type="button" class="mnm-delete-discount-rule dashicons-dismiss dashicons-before delete"></button>
			</td>

		</script>


		<?php

	}

	/**
	 * Save meta.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function save_meta( $product ) {

		$mode = false;
		
		if ( ! empty( $_POST[ 'wc_mnm_discount_mode' ] ) ) {
			$mode = 'bulk' === wp_unslash( $_POST[ 'wc_mnm_discount_mode' ] ) ? 'bulk' : false;
		}

		if ( $mode  ) { 
			$product->add_meta_data( '_wc_mnm_discount_mode', $mode, true );
		} else {
			$product->delete_meta_data( '_wc_mnm_discount_mode' );
		}

		if ( ! empty( $_POST[ '_wc_mnm_bulk_discount_data' ] ) ) {
			$input_data           = $_POST[ '_wc_mnm_bulk_discount_data' ];
		//	$parsed_discount_data = self::encode( $input_data );
		//	$product->add_meta_data( '_wc_mnm_bulk_discount_data', $parsed_discount_data, true );
		} else {
			$product->delete_meta_data( '_wc_mnm_bulk_discount_data' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Cart.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Applies discount on bundled cart items based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  array  $container
	 * @return array  $cart_item
	 */
	public static function child_cart_item_discount( $cart_item, $container ) {

		if (  wc_mnm_maybe_is_child_cart_item( $cart_item ) ) {

			$container = wc_mnm_get_cart_item_container( $cart_item );

			$discount_data_array = $container[ 'data' ]->get_meta( '_wc_mnm_bulk_discount_data', true );
			$price               = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$containerd_items_data = $container[ 'stamp' ];
				$total_quantity     = 0;

				foreach ( $containerd_items_data as $containerd_item_data ) {
					if ( isset( $containerd_item_data[ 'quantity' ] ) ) {
						$total_quantity += $containerd_item_data[ 'quantity' ];
					}
				}

				if ( $discount = self::get_discount( $total_quantity, $discount_data_array ) ) {

					$discounted_price = self::get_discounted_price( $price, $discount );
					$cart_item[ 'data' ]->set_price( $discounted_price );
				
				}
			}
		}

		return $cart_item;
	}

	/**
	 * Applies discount on bundle container cart item based on overall cart quantity.
	 *
	 * @param  array  $cart_item
	 * @param  array  $container
	 * @return array  $cart_item
	 */
	public static function container_cart_item_discount( $cart_item, $container ) {

		if ( wc_mnm_is_container_cart_item( $cart_item ) && self::apply_discount_to_base_price( $container ) ) {

			$discount_data_array = $cart_item[ 'data' ]->get_meta( '_wc_mnm_bulk_discount_data', true );
			$price               = $cart_item[ 'data' ]->get_price();

			if ( $price && ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$containerd_items_data = $cart_item[ 'stamp' ];
				$total_quantity     = 0;

				foreach ( $containerd_items_data as $containerd_item_data ) {
					if ( isset( $containerd_item_data[ 'quantity' ] ) ) {
						$total_quantity += $containerd_item_data[ 'quantity' ];
					}
				}

				if ( $discount = self::get_discount( $total_quantity, $discount_data_array ) ) {

					$cart_item[ 'data' ]->set_price( self::get_discounted_price( $price, $discount ) );
					
				}
			}
		}

		return $cart_item;
	}

	/*
	|--------------------------------------------------------------------------
	| Products / Catalog.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns discounted price based on default min quantities.
	 *
	 * @param  string  $price
	 * @param  object  $product
	 * @return string  $price
	 */
	public static function get_discounted_price_html( $price, $product ) {

		// If product is bundle then get discount_data_array.
		if ( $product->is_type( 'bundle' ) ) {

			$discount_data_array = $product->get_meta( '_wc_mnm_bulk_discount_data', true );

			// If there exists a discount then get all min_quantities of the bundled items.
			if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

				$total_min_quantity = 0;
				$discount_applies   = false;
				$containerd_items      = $product->get_bundled_items();

				// Calculate minimum possible bundled items quantity.
				foreach ( $containerd_items as $containerd_item ) {

					$total_min_quantity += $containerd_item->get_quantity( 'min', array(
						'context'        => 'price',
						'check_optional' => true
					) );
				}

				// Check if the sum of min_quantity exists in a disount line.
				foreach ( $discount_data_array as $line ) {
					if ( isset( $line[ 'quantity_min' ] ) && $total_min_quantity >= $line[ 'quantity_min' ] && $line[ 'discount' ] > 0 ) {
						$discount_applies = true;
					}
				}

				if ( $discount_applies ) {

					self::$discount_data_array = $discount_data_array;
					self::$total_min_quantity  = $total_min_quantity;

					self::add_filters();

					// Remove to prevent infinite loop.
					remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'get_discounted_price_html' ), 10000, 2 );

					$price = $product->get_price_html();

					// Add again.
					add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'get_discounted_price_html' ), 10000, 2 );

					self::remove_filters();

					self::$discount_data_array = array();
					self::$total_min_quantity  = 0;
				}
			}
		}

		return $price;
	}

	/**
	 * Add filters to modify products when contained in Bundles.
	 *
	 * @return void
	 */
	public static function add_filters() {
		add_filter( 'woocommerce_bundle_prices_hash', array( __CLASS__, 'filter_bundle_prices_hash' ), 10, 2 );
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
	}

	/**
	 * Remove filters - @see 'add_filters'.
	 *
	 * @return void
	 */
	public static function remove_filters() {
		remove_filter( 'woocommerce_bundle_prices_hash', array( __CLASS__, 'filter_bundle_prices_hash' ), 10, 2 );
		remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
		remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 16, 2 );
	}


	/**
	 * Modify bundle prices hash to go around the runtime cache.
	 *
	 * @param   array              $hash
	 * @param   WC_Product_Mix_and_Match  $container
	 * @return array
	 */
	public static function filter_bundle_prices_hash( $hash, $container ) {
		$hash[ 'discount_data' ] = self::decode( $container->get_meta( '_wc_mnm_bulk_discount_data', true ) );
		return $hash;
	}

	/**
	 * Compoutes and returns discounted price.
	 *
	 * @param  mixed   $price
	 * @param  object  $product
	 * @return mixed
	 */
	public static function filter_price( $price, $product ) {

		if ( '' === $price ) {
			return $price;
		}

		$calculate_discount = true;

		// Check if discount is applied to base price as well.
		if ( $product->is_type( 'bundle' ) && false === self::apply_discount_to_base_price( $product ) ) {
			$calculate_discount = false;
		}

		if ( $calculate_discount ) {

			$total_quantity = self::$total_min_quantity;

			if ( $discount = self::get_discount( $total_quantity, self::$discount_data_array ) ) {
				$price = self::get_discounted_price( $price, $discount );
			}
		}

		return $price;
	}

	/**
	 * Calls filter to add a suffix to price_html if some discount applies.
	 *
	 * @param  array   $containerd_item
	 * @return void
	 */
	public static function add_container_discount_price_html_suffix( $containerd_item ) {

		$container              = $containerd_item->get_bundle();
		$discount_data_array = $container->get_meta( '_wc_mnm_bulk_discount_data', true );

		if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {
			self::$discount_data_array = $discount_data_array;
		}

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'add_discount_price_html_suffix' ), 100, 2 );
	}

	/**
	 * Adds suffix to price_html.
	 *
	 * @param  string  $price_html
	 * @param  object  $product
	 * @return string  $price_html
	 */
	public static function add_discount_price_html_suffix( $price_html, $product ) {

		if ( ! empty( self::$discount_data_array ) && is_array( self::$discount_data_array ) ) {
			$price_html = sprintf( __( '%s <small>(before discount)</small>', 'wc-mnm-bulk-discounts' ), $price_html );
		}

		return $price_html;
	}

	/**
	 * Removes filter.
	 *
	 * @param  array  $containerd_item
	 * @return void
	 */
	public static function remove_container_discount_price_html_suffix( $containerd_item ) {
		self::$discount_data_array = array();
		remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'add_discount_price_html_suffix' ), 100, 2 );
	}

	/**
	 * Front-end script.
	 *
	 * @param  array  $dependencies
	 */
	public static function frontend_script() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'wc-mnm-bulk-discount-add-to-cart', self::plugin_url() . '/assets/js/frontend/wc-mnm-bulk-discount-add-to-cart' . $suffix . '.js', array( 'wc-add-to-cart-mnm' ), self::$version );

		wp_enqueue_style( 'wc-mnm-bulk-discount-styles', self::plugin_url() . '/assets/css/frontend/wc-mnm-bulk-discount-styles' . $suffix . '.css', false, self::$version, 'all' );

	}

	/**
	 * Update settings to add parameters.
	 *
	 * @param  array  $price_data
	 * @param  array  $container
	 * @return array
	 */
	public static function add_discount_data( $price_data, $container ) {

		$discount_data_array = $container->get_meta( '_wc_mnm_bulk_discount_data', true );

		if ( ! empty( $discount_data_array ) && is_array( $discount_data_array ) ) {

			// INF cannot be JSON-encoded :)
			foreach ( $discount_data_array as $line_key => $line ) {
				if ( isset( $line[ 'quantity_max' ] ) && is_infinite( $line[ 'quantity_max' ] ) ) {
					$discount_data_array[ $line_key ][ 'quantity_max' ] = '';
				}
			}

			$price_data[ 'bulk_discount_data' ] = array(
				'discount_array' => $discount_data_array,
				'discount_base'  => self::apply_discount_to_base_price( $container ) ? 'yes' : 'no'
			);
		}

		return $price_data;
	}

	/**
	 * Update front-end parameters to add 'After discount'.
	 *
	 * @param  array  $parameter_array
	 * @return array
	 */
	public static function add_front_end_params( $parameter_array ) {

		$parameter_array[ 'i18n_bulk_discount_subtotal' ] = __( 'Subtotal: ', 'wc-mnm-bulk-discounts' );
		$parameter_array[ 'i18n_bulk_discount' ]          = __( 'Discount: ', 'wc-mnm-bulk-discounts' );
		$parameter_array[ 'i18n_bulk_discount_value' ]    = sprintf( __( '%s%%', 'wc-mnm-bulk-discounts' ), '%v' );
		$parameter_array[ 'i18n_bulk_discount_format' ]   = sprintf( _x( '%1$s%2$s', '"Discount" string followed by value', 'wc-mnm-bulk-discounts' ), '%s', '%v' );

		return $parameter_array;
	}
}

WC_MNM_Bulk_Discounts::init();
