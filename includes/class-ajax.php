<?php
/**
 * AJAX handler class for the WC Hide Price & Ask Button Pro plugin.
 *
 * Handles product search, category search, AJAX save operations,
 * and contact form submission via WordPress AJAX endpoints.
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
 * Class Ajax
 *
 * Registers and handles all AJAX actions for the plugin.
 *
 * @since 1.0.0
 */
class Ajax {

	/**
	 * Constructor. Registers AJAX hooks.
	 */
	public function __construct() {
		// Admin AJAX — search products.
		add_action( 'wp_ajax_wchpab_search_products', array( $this, 'search_products' ) );

		// Admin AJAX — search categories.
		add_action( 'wp_ajax_wchpab_search_categories', array( $this, 'search_categories' ) );

		// Admin AJAX — save categories.
		add_action( 'wp_ajax_wchpab_save_categories', array( $this, 'save_categories' ) );

		// Admin AJAX — save settings.
		add_action( 'wp_ajax_wchpab_save_settings', array( $this, 'save_settings' ) );

		// Admin AJAX — save excluded products.
		add_action( 'wp_ajax_wchpab_save_excluded', array( $this, 'save_excluded' ) );

		// Frontend AJAX — submit contact form (logged-in and guest users).
		add_action( 'wp_ajax_wchpab_submit_form', array( $this, 'submit_form' ) );
		add_action( 'wp_ajax_nopriv_wchpab_submit_form', array( $this, 'submit_form' ) );
	}

	/**
	 * AJAX handler: Search WooCommerce products by name, ID, or SKU.
	 *
	 * Expects $_GET['term'] as the search query.
	 * Returns a JSON array of { id, text } objects.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function search_products() {
		check_ajax_referer( 'wchpab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'wc-hide-price-ask-button' ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json( array() );
		}

		$product_ids = array();

		// 1. Search by ID (if numeric).
		if ( is_numeric( $term ) ) {
			$id_product = wc_get_product( absint( $term ) );
			if ( $id_product ) {
				$product_ids[] = $id_product->get_id();
			}
		}

		// 2. Search by SKU.
		$sku_id = wc_get_product_id_by_sku( $term );
		if ( $sku_id ) {
			$product_ids[] = $sku_id;
		}

		// 3. Search by name (title).
		$name_query = new \WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $term,
			'fields'         => 'ids',
		) );
		$product_ids = array_merge( $product_ids, $name_query->posts );

		// 4. Also search by SKU with LIKE match for partial SKU matches.
		global $wpdb;
		$like_term   = '%' . $wpdb->esc_like( $term ) . '%';
		$sku_results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s LIMIT 20",
				$like_term
			)
		);
		if ( $sku_results ) {
			$product_ids = array_merge( $product_ids, array_map( 'absint', $sku_results ) );
		}

		// Deduplicate.
		$product_ids = array_unique( $product_ids );

		$results = array();
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$sku  = $product->get_sku();
				$text = $sku
					? sprintf( '#%d — %s (SKU: %s)', $product_id, $product->get_name(), $sku )
					: sprintf( '#%d — %s', $product_id, $product->get_name() );
				$results[] = array(
					'id'   => $product_id,
					'text' => $text,
				);
			}
		}

		wp_send_json( $results );
	}

	/**
	 * AJAX handler: Search WooCommerce product categories by name or ID.
	 *
	 * Expects $_GET['term'] as the search query.
	 * Returns a JSON array of { id, text } objects.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function search_categories() {
		check_ajax_referer( 'wchpab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'wc-hide-price-ask-button' ) );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json( array() );
		}

		$tax_args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 20,
		);

		if ( is_numeric( $term ) ) {
			$tax_args['include'] = array( absint( $term ) );
		} else {
			$tax_args['search'] = $term;
		}

		$terms   = get_terms( $tax_args );
		$results = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $cat ) {
				$results[] = array(
					'id'   => $cat->term_id,
					'text' => sprintf( '#%d — %s', $cat->term_id, $cat->name ),
				);
			}
		}

		wp_send_json( $results );
	}


	/**
	 * AJAX handler: Save hidden categories list.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function save_categories() {
		check_ajax_referer( 'wchpab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'wc-hide-price-ask-button' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( count( $ids ) > 10 ) {
			$ids = array_slice( $ids, 0, 10 );
		}
		update_option( 'wchpab_hidden_categories', $ids );

		wp_send_json_success( esc_html__( 'Changes saved successfully.', 'wc-hide-price-ask-button' ) );
	}

	/**
	 * AJAX handler: Save button settings.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function save_settings() {
		check_ajax_referer( 'wchpab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'wc-hide-price-ask-button' ) );
		}

		$settings = array(
			'target_product'         => ! empty( $_POST['target_product'] ),
			'target_archive'         => ! empty( $_POST['target_archive'] ),
			'product_text_mode'   => isset( $_POST['product_text_mode'] ) && 'custom' === $_POST['product_text_mode'] ? 'custom' : 'default',
			'product_text'        => isset( $_POST['product_text'] ) ? sanitize_text_field( wp_unslash( $_POST['product_text'] ) ) : '',
			'category_text_mode'  => isset( $_POST['category_text_mode'] ) && 'custom' === $_POST['category_text_mode'] ? 'custom' : 'default',
			'category_text'       => isset( $_POST['category_text'] ) ? sanitize_text_field( wp_unslash( $_POST['category_text'] ) ) : '',
			'archive_action'      => isset( $_POST['archive_action'] ) && 'modal' === $_POST['archive_action'] ? 'modal' : 'link',
		);

		$settings['product_text']  = mb_substr( $settings['product_text'], 0, 30 );
		$settings['category_text'] = mb_substr( $settings['category_text'], 0, 30 );

		update_option( 'wchpab_button_settings', $settings );

		wp_send_json_success( esc_html__( 'Changes saved successfully.', 'wc-hide-price-ask-button' ) );
	}

	/**
	 * AJAX handler: Save excluded products list.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function save_excluded() {
		check_ajax_referer( 'wchpab_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'wc-hide-price-ask-button' ) );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( count( $ids ) > 20 ) {
			$ids = array_slice( $ids, 0, 20 );
		}

		update_option( 'wchpab_excluded_products', $ids );

		wp_send_json_success( esc_html__( 'Changes saved successfully.', 'wc-hide-price-ask-button' ) );
	}


	/**
	 * AJAX handler: Process the contact form submission.
	 *
	 * Validates fields, sanitizes input, and sends an email via wp_mail().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function submit_form() {
		check_ajax_referer( 'wchpab_frontend_nonce', 'nonce' );

		$name    = isset( $_POST['wchpab_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wchpab_name'] ) ) : '';
		$phone   = isset( $_POST['wchpab_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wchpab_phone'] ) ) : '';
		$email   = isset( $_POST['wchpab_email'] ) ? sanitize_email( wp_unslash( $_POST['wchpab_email'] ) ) : '';
		$message = isset( $_POST['wchpab_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wchpab_message'] ) ) : '';
		$product_id   = isset( $_POST['wchpab_product_id'] ) ? absint( $_POST['wchpab_product_id'] ) : 0;
		$product_text = isset( $_POST['wchpab_product'] ) ? sanitize_text_field( wp_unslash( $_POST['wchpab_product'] ) ) : '';

		if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
			wp_send_json_error(
				esc_html__( 'Please fill in all required fields.', 'wc-hide-price-ask-button' )
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				esc_html__( 'Please enter a valid email address.', 'wc-hide-price-ask-button' )
			);
		}

		$to = get_option( 'admin_email' );

		// Fetch the WooCommerce Product to get SKU and Link
		$wc_product   = $product_id ? wc_get_product( $product_id ) : false;
		$product_name = $wc_product ? $wc_product->get_name() : $product_text;
		$product_sku  = $wc_product ? $wc_product->get_sku() : '';
		$product_link = $wc_product ? $wc_product->get_permalink() : '';

		/* translators: %s: Product name. */
		$subject = sprintf( esc_html__( 'Interest for the product: %s', 'wc-hide-price-ask-button' ), $product_name );

		$body  = sprintf( "%s: %s\n", esc_html__( 'Name', 'wc-hide-price-ask-button' ), $name );
		$body .= sprintf( "%s: %s\n", esc_html__( 'Email', 'wc-hide-price-ask-button' ), $email );

		if ( ! empty( $phone ) ) {
			$body .= sprintf( "%s: %s\n", esc_html__( 'Phone', 'wc-hide-price-ask-button' ), $phone );
		}

		// Build the new Product line string: {ID} {Name} ({SKU}) - {Link}
		$product_line = $product_id ? $product_id . ' ' : '';
		$product_line .= $product_name;

		if ( ! empty( $product_sku ) ) {
			$product_line .= ' (' . $product_sku . ')';
		}

		if ( ! empty( $product_link ) ) {
			$product_line .= "\n" . esc_html__( 'Link:', 'wc-hide-price-ask-button' ) . ' ' . $product_link;
		}

		$body .= sprintf( "\n%s: %s\n", esc_html__( 'Product', 'wc-hide-price-ask-button' ), $product_line );
		$body .= sprintf( "\n%s:\n%s\n", esc_html__( 'Message', 'wc-hide-price-ask-button' ), $message );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $name, $email ),
		);

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			wp_send_json_success(
				esc_html__( 'Thank you! Your message has been sent successfully.', 'wc-hide-price-ask-button' )
			);
		} else {
			wp_send_json_error(
				esc_html__( 'An error occurred while sending your message. Please try again.', 'wc-hide-price-ask-button' )
			);
		}
	}
}
