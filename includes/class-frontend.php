<?php
/**
 * Frontend class for the WC Hide Price & Ask Button Pro plugin.
 *
 * Handles price hiding, button replacement, modal rendering,
 * and frontend asset enqueuing.
 *
 * @package WCHPAB
 * @since   1.0.0
 */

namespace WCHPAB;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Frontend
 *
 * Hooks into WooCommerce to hide prices, replace Add to Cart buttons,
 * and render the contact modal on the frontend.
 *
 * @since 1.0.0
 */
class Frontend {

	/**
	 * Cached list of category term IDs with hidden prices.
	 *
	 * @var array
	 */
	private $hidden_categories = array();

	/**
	 * Cached list of product IDs excluded from category hiding.
	 *
	 * @var array
	 */
	private $excluded_products = array();

	/**
	 * Cached button settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Constructor. Loads options and registers hooks.
	 */
	public function __construct() {
		$this->hidden_categories = get_option( 'wchpab_hidden_categories', array() );
		$this->excluded_products = get_option( 'wchpab_excluded_products', array() );
		$this->settings          = get_option( 'wchpab_button_settings', array() );

		// Only register hooks if there are items to process.
		if ( empty( $this->hidden_categories ) ) {
			return;
		}

		// Hide product prices.
		add_filter( 'woocommerce_get_price_html', array( $this, 'hide_price' ), 999, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'hide_variation_price' ), 999, 3 );

		// Replace Add to Cart button on archive/category pages.
		$target_archive = isset( $this->settings['target_archive'] ) ? (bool) $this->settings['target_archive'] : true;
		if ( $target_archive ) {
			add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'replace_archive_button' ), 999, 2 );
		}

		// Replace Add to Cart button on single product pages.
		$target_product = isset( $this->settings['target_product'] ) ? (bool) $this->settings['target_product'] : true;
		if ( $target_product ) {
			// Hook into the actual Add to Cart generation actions for better compatibility with theme builders.
			$types = array( 'simple', 'grouped', 'variable', 'external' );
			foreach ( $types as $type ) {
				add_action( 'woocommerce_' . $type . '_add_to_cart', array( $this, 'maybe_replace_single_button' ), 1 );
			}
		}

		// Enqueue frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Render modal in footer.
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
	}

	/**
	 * Check if a product should have its price hidden.
	 *
	 * @since  1.0.0
	 * @param  int $product_id Product ID.
	 * @return bool
	 */
	private function should_hide( $product_id ) {
		// Never hide excluded products.
		if ( in_array( $product_id, $this->excluded_products, true ) ) {
			return false;
		}

		// Determine if price should be hidden based on category selection
		$should_hide_by_selection = false;

		// Check if product belongs to any hidden category.
		if ( ! empty( $this->hidden_categories ) ) {
			if ( has_term( $this->hidden_categories, 'product_cat', $product_id ) ) {
				$should_hide_by_selection = true;
			}
		}

		// Allow excluded products to remain visible.
		if ( $should_hide_by_selection && ! empty( $this->excluded_products ) ) {
			if ( in_array( $product_id, $this->excluded_products, true ) ) {
				$should_hide_by_selection = false;
			}
		}

		// If not selected for hiding, don't hide
		if ( ! $should_hide_by_selection ) {
			return false;
		}

		// Now check visibility rules
		return true;
	}

	/**
	 * Filter: Hide the price HTML for selected products.
	 *
	 * @since  1.0.0
	 * @param  string      $price_html The price HTML.
	 * @param  \WC_Product $product    The product object.
	 * @return string
	 */
	public function hide_price( $price_html, $product ) {
		if ( $this->should_hide( $product->get_id() ) ) {
			return '';
		}
		return $price_html;
	}

	/**
	 * Filter: Hide variation price in the JS variation data array.
	 *
	 * @since  1.0.1
	 * @param  array                $data       The variation data array.
	 * @param  \WC_Product_Variable $product    The parent product object.
	 * @param  \WC_Product_Variation $variation The variation object.
	 * @return array
	 */
	public function hide_variation_price( $data, $product, $variation ) {
		if ( $this->should_hide( $product->get_id() ) ) {
			$data['price_html'] = '';
		}
		return $data;
	}

	/**
	 * Get the button text for archive pages.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function get_archive_button_text() {
		$mode = isset( $this->settings['category_text_mode'] ) ? $this->settings['category_text_mode'] : 'default';
		$text = isset( $this->settings['category_text'] ) ? $this->settings['category_text'] : '';

		if ( 'custom' === $mode && ! empty( $text ) ) {
			return $text;
		}

		return esc_html__( 'Ask for product', 'wc-hide-price-ask-button' );
	}

	/**
	 * Get the button text for single product pages.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	private function get_product_button_text() {
		$mode = isset( $this->settings['product_text_mode'] ) ? $this->settings['product_text_mode'] : 'default';
		$text = isset( $this->settings['product_text'] ) ? $this->settings['product_text'] : '';

		if ( 'custom' === $mode && ! empty( $text ) ) {
			return $text;
		}

		return esc_html__( 'Ask for product', 'wc-hide-price-ask-button' );
	}



	/**
	 * Filter: Replace the Add to Cart button on archive/category pages.
	 *
	 * @since  1.0.0
	 * @param  string      $button  The default button HTML.
	 * @param  \WC_Product $product The product object.
	 * @return string
	 */
	public function replace_archive_button( $button, $product ) {
		if ( ! $this->should_hide( $product->get_id() ) ) {
			return $button;
		}

		$settings            = get_option( 'wchpab_button_settings', array() );
		$archive_action      = isset( $settings['archive_action'] ) ? $settings['archive_action'] : 'link';

		$product_name = $product->get_name();
		$product_id   = $product->get_id();

		$icon_html = '';

		$button_text = $this->get_archive_button_text();

		$custom_class_attr = '';
		$align_class = '';

		if ( 'modal' === $archive_action ) {
			// Use anchor tag for modal (default)
			return sprintf(
				'<div class="wchpab-button-wrap%s"><a href="#" class="button add_to_cart_button ajax_add_to_cart wchpab-ask-button%s" data-product-id="%d" data-product-name="%s"><span>%s</span>%s</a></div>',
				$align_class,
				$custom_class_attr,
				$product_id,
				esc_attr( $product_name ),
				esc_html( $button_text ),
				$icon_html
			);
		} else {
			// Link to the product page natively. Do NOT include wchpab-ask-button so the JS modal doesn't trigger.
			// We DO include wchpab-ask-link-button to allow CSS targeting if needed.
			$product_url = $product->get_permalink();

			// Use anchor tag (default)
			return sprintf(
				'<div class="wchpab-button-wrap%s"><a href="%s" class="button add_to_cart_button wchpab-ask-link-button%s"><span>%s</span>%s</a></div>',
				$align_class,
				esc_url( $product_url ),
				$custom_class_attr,
				esc_html( $button_text ),
				$icon_html
			);
		}
	}

	/**
	 * Action: Conditionally replace the Add to Cart button on single product pages.
	 *
	 * Hooks early into woocommerce_{type}_add_to_cart to remove
	 * the default Add to Cart and add our custom button. This avoids
	 * issues with theme builders that bypass woocommerce_single_product_summary.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function maybe_replace_single_button() {
		global $product;

		if ( ! $product || ! $this->should_hide( $product->get_id() ) ) {
			return;
		}

		$type = $product->get_type();

		// Remove default WooCommerce core handlers for this product type.
		if ( 'variable' === $type ) {
			remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
		} else {
			remove_action( 'woocommerce_' . $type . '_add_to_cart', 'woocommerce_' . $type . '_add_to_cart', 30 );
		}

		// Prevent multiple attachment of our button
		if ( 'variable' === $type ) {
			if ( ! has_action( 'woocommerce_single_variation', array( $this, 'output_single_button' ) ) ) {
				add_action( 'woocommerce_single_variation', array( $this, 'output_single_button' ), 30 );
			}
		} else {
			if ( ! has_action( 'woocommerce_' . $type . '_add_to_cart', array( $this, 'output_single_button' ) ) ) {
				add_action( 'woocommerce_' . $type . '_add_to_cart', array( $this, 'output_single_button' ), 30 );
			}
		}
	}

	/**
	 * Output the replacement button on single product pages.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function output_single_button() {
		global $product;

		$settings           = get_option( 'wchpab_button_settings', array() );
		$visibility_mode    = isset( $settings['visibility_mode'] ) ? $settings['visibility_mode'] : 'everyone';
		$product_icon_mode  = isset( $settings['product_icon_mode'] ) ? $settings['product_icon_mode'] : 'none';
		$product_icon_class = isset( $settings['product_icon_class'] ) ? $settings['product_icon_class'] : 'dashicons-info-outline';

		$product_name = $product ? $product->get_name() : '';
		$product_id   = $product ? $product->get_id() : 0;

		$icon_html = '';
		if ( 'custom' === $product_icon_mode ) {
			$icon_html = sprintf( '<i class="dashicons %s"></i>', esc_attr( $product_icon_class ) );
		}

		// If visibility mode is guests and user is not logged in, show login/register link
		if ( 'guests' === $visibility_mode && ! is_user_logged_in() ) {
			$login_url = $this->get_login_url( $product_id );
			$button_text = $this->get_login_button_text();

			// Get login button icon
			$login_icon_mode  = isset( $settings['login_icon_mode'] ) ? $settings['login_icon_mode'] : 'none';
			$login_icon_class = isset( $settings['login_icon_class'] ) ? $settings['login_icon_class'] : '';
			$login_icon_html = '';
			if ( 'custom' === $login_icon_mode && ! empty( $login_icon_class ) ) {
				$login_icon_html = sprintf( '<i class="dashicons %s"></i>', esc_attr( $login_icon_class ) );
			}

			// Get button tag, custom classes, and alignment for product page
			$product_button_tag = isset( $settings['product_button_tag'] ) ? $settings['product_button_tag'] : 'button';
			$product_custom_classes = isset( $settings['product_custom_classes'] ) ? $settings['product_custom_classes'] : '';
			$product_button_align = isset( $settings['product_button_align'] ) ? $settings['product_button_align'] : 'auto';
			$custom_class_attr = ! empty( $product_custom_classes ) ? ' ' . esc_attr( $product_custom_classes ) : '';
			$align_class = 'auto' !== $product_button_align ? ' wchpab-align-' . esc_attr( $product_button_align ) : '';

			if ( 'a' === $product_button_tag ) {
				// Use anchor tag
				printf(
					'<div class="wchpab-button-wrap%s"><a href="%s" class="single_add_to_cart_button button alt wchpab-login-button%s"><span>%s</span>%s</a></div>',
					$align_class,
					esc_url( $login_url ),
					$custom_class_attr,
					esc_html( $button_text ),
					$login_icon_html
				);
			} else {
				// Use button tag (default)
				printf(
					'<div class="wchpab-button-wrap%s"><button type="button" class="single_add_to_cart_button button alt wchpab-login-button%s" data-login-url="%s"><span>%s</span>%s</button></div>',
					$align_class,
					$custom_class_attr,
					esc_url( $login_url ),
					esc_html( $button_text ),
					$login_icon_html
				);
			}
			return;
		}

		$button_text = $this->get_product_button_text();

		// Get button tag, custom classes, and alignment
		$product_button_tag = isset( $settings['product_button_tag'] ) ? $settings['product_button_tag'] : 'button';
		$product_custom_classes = isset( $settings['product_custom_classes'] ) ? $settings['product_custom_classes'] : '';
		$product_button_align = isset( $settings['product_button_align'] ) ? $settings['product_button_align'] : 'auto';
		$custom_class_attr = ! empty( $product_custom_classes ) ? ' ' . esc_attr( $product_custom_classes ) : '';
		$align_class = 'auto' !== $product_button_align ? ' wchpab-align-' . esc_attr( $product_button_align ) : '';

		if ( 'a' === $product_button_tag ) {
			// Use anchor tag
			printf(
				'<div class="wchpab-button-wrap%s"><a href="#" class="single_add_to_cart_button button alt wchpab-ask-button%s" data-product-id="%d" data-product-name="%s"><span>%s</span>%s</a></div>',
				$align_class,
				$custom_class_attr,
				$product_id,
				esc_attr( $product_name ),
				esc_html( $button_text ),
				$icon_html
			);
		} else {
			// Use button tag (default)
			printf(
				'<div class="wchpab-button-wrap%s"><button type="button" class="single_add_to_cart_button button alt wchpab-ask-button%s" data-product-id="%d" data-product-name="%s"><span>%s</span>%s</button></div>',
				$align_class,
				$custom_class_attr,
				$product_id,
				esc_attr( $product_name ),
				esc_html( $button_text ),
				$icon_html
			);
		}
	}

	/**
	 * Enqueue frontend CSS and JS on WooCommerce pages.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		// Only load on WooCommerce pages where our plugin is active.
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'wchpab-frontend',
			WCHPAB_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			filemtime( WCHPAB_PLUGIN_DIR . 'assets/css/frontend.css' )
		);

		// Ensure Dashicons is loaded on the frontend for the info icon
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_script(
			'wchpab-frontend',
			WCHPAB_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			filemtime( WCHPAB_PLUGIN_DIR . 'assets/js/frontend.js' ),
			true
		);

		wp_localize_script( 'wchpab-frontend', 'wchpab_front', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wchpab_frontend_nonce' ),
			'i18n'     => array(
				'form_title'    => esc_html__( 'Interest in product', 'wc-hide-price-ask-button' ),
				'name_label'    => esc_html__( 'Your Name', 'wc-hide-price-ask-button' ),
				'phone_label'   => esc_html__( 'Your Telephone', 'wc-hide-price-ask-button' ),
				'email_label'   => esc_html__( 'Your Email', 'wc-hide-price-ask-button' ),
				'message_label' => esc_html__( 'Your Message', 'wc-hide-price-ask-button' ),
				'submit_btn'    => esc_html__( 'Send Message', 'wc-hide-price-ask-button' ),
				'required'      => esc_html__( 'Required', 'wc-hide-price-ask-button' ),
				'sending'       => esc_html__( 'Sending…', 'wc-hide-price-ask-button' ),
				'success'       => esc_html__( 'Thank you! Your message has been sent successfully.', 'wc-hide-price-ask-button' ),
				'error'         => esc_html__( 'An error occurred. Please try again.', 'wc-hide-price-ask-button' ),
				'fill_required' => esc_html__( 'Please fill in all required fields.', 'wc-hide-price-ask-button' ),
				'invalid_email' => esc_html__( 'Please enter a valid email address.', 'wc-hide-price-ask-button' ),
				'close'         => esc_html__( 'Close', 'wc-hide-price-ask-button' ),
			),
		) );
	}

	/**
	 * Render the contact form modal HTML in the footer.
	 *
	 * The modal is hidden by default and shown via JavaScript
	 * when the "Ask for Product" button is clicked.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_modal() {
		// Only render on WooCommerce pages.
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return;
		}
		?>
		<div id="wchpab-modal" class="wchpab-modal" role="dialog" aria-modal="true" aria-labelledby="wchpab-modal-title" style="display:none;">
			<div class="wchpab-modal-overlay"></div>
			<div class="wchpab-modal-container">
				<div class="wchpab-modal-header">
					<h2 id="wchpab-modal-title" class="wchpab-modal-title"></h2>
					<button type="button" class="wchpab-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wc-hide-price-ask-button' ); ?>">&times;</button>
				</div>
				<div class="wchpab-modal-body">
					<form id="wchpab-contact-form" novalidate>
						<?php wp_nonce_field( 'wchpab_frontend_nonce', 'wchpab_form_nonce' ); ?>
						<input type="hidden" name="wchpab_product" id="wchpab-product-name" value="" />
						<input type="hidden" name="wchpab_product_id" id="wchpab-product-id" value="" />

						<div class="wchpab-form-field">
							<label for="wchpab-name">
								<?php esc_html_e( 'Your Name', 'wc-hide-price-ask-button' ); ?>
								<span class="wchpab-required">*</span>
							</label>
							<input type="text" id="wchpab-name" name="wchpab_name" required />
						</div>

						<div class="wchpab-form-field">
							<label for="wchpab-phone">
								<?php esc_html_e( 'Your Telephone', 'wc-hide-price-ask-button' ); ?>
							</label>
							<input type="tel" id="wchpab-phone" name="wchpab_phone" />
						</div>

						<div class="wchpab-form-field">
							<label for="wchpab-email">
								<?php esc_html_e( 'Your Email', 'wc-hide-price-ask-button' ); ?>
								<span class="wchpab-required">*</span>
							</label>
							<input type="email" id="wchpab-email" name="wchpab_email" required />
						</div>

						<div class="wchpab-form-field">
							<label for="wchpab-message">
								<?php esc_html_e( 'Your Message', 'wc-hide-price-ask-button' ); ?>
								<span class="wchpab-required">*</span>
							</label>
							<textarea id="wchpab-message" name="wchpab_message" rows="4" required></textarea>
						</div>

						<div class="wchpab-form-footer">
							<button type="submit" class="button wchpab-submit-btn">
								<?php esc_html_e( 'Send Message', 'wc-hide-price-ask-button' ); ?>
							</button>
						</div>

						<div id="wchpab-form-notice" class="wchpab-form-notice" style="display:none;"></div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
