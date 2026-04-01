<?php
/**
 * Plugin Name:       WC Hide Price & Ask Button
 * Plugin URI:        https://sygraphe.com/wc-hide-price-ask-button
 * Description:       Hide WooCommerce product prices and replace the Add to Cart button with a customizable "Ask for Product" button that opens a contact modal.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Sygraphe
 * Author URI:        https://github.com/sygraphe
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wc-hide-price-ask-button
 * Domain Path:       /languages
 * WC requires at least: 5.0
 * WC tested up to:   9.0
 *
 * @package WCHPAB
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'WCHPAB_VERSION', '1.0.0' );
define( 'WCHPAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCHPAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCHPAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active before initializing.
 */
function wchpab_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wchpab_woocommerce_missing_notice' );
		return;
	}
	wchpab_init();
}
add_action( 'plugins_loaded', 'wchpab_check_woocommerce' );

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function wchpab_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name. */
				esc_html__( '%s requires WooCommerce to be installed and active.', 'wc-hide-price-ask-button' ),
				'<strong>WC Hide Price &amp; Ask Button</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Load plugin text domain for translations.
 *
 * @return void
 */
function wchpab_load_textdomain() {
	load_plugin_textdomain(
		'wc-hide-price-ask-button',
		false,
		dirname( WCHPAB_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'wchpab_load_textdomain' );

/**
 * Initialize plugin components.
 *
 * @return void
 */
function wchpab_init() {
	require_once WCHPAB_PLUGIN_DIR . 'includes/class-ajax.php';
	require_once WCHPAB_PLUGIN_DIR . 'includes/class-admin.php';
	require_once WCHPAB_PLUGIN_DIR . 'includes/class-frontend.php';

	new WCHPAB\Ajax();
	new WCHPAB\Admin();
	new WCHPAB\Frontend();
}

/**
 * Plugin activation hook.
 * Sets default options on first activation.
 *
 * @return void
 */
function wchpab_activate() {
	if ( false === get_option( 'wchpab_hidden_categories' ) ) {
		update_option( 'wchpab_hidden_categories', array() );
	}
	if ( false === get_option( 'wchpab_excluded_products' ) ) {
		update_option( 'wchpab_excluded_products', array() );
	}
	if ( false === get_option( 'wchpab_button_settings' ) ) {
		update_option( 'wchpab_button_settings', array(
			'product_text_mode'  => 'default',
			'product_text'       => '',
			'category_text_mode' => 'default',
			'category_text'      => '',
			'target_product'     => true,
			'target_archive'     => true,
			'archive_action'     => 'link',
		) );
	}
}
register_activation_hook( __FILE__, 'wchpab_activate' );

/**
 * Get a plugin option with default fallback.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function wchpab_get_option( $key, $default = false ) {
	return get_option( $key, $default );
}
