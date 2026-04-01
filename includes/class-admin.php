<?php
/**
 * Admin class for the WC Hide Price & Ask Button Pro plugin.
 *
 * Registers admin menus, renders settings pages, enqueues admin assets,
 * and handles AJAX saving of plugin options.
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
 * Class Admin
 *
 * Handles all WordPress admin functionality for the plugin.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Constructor. Registers admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . WCHPAB_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		add_action( 'admin_head', array( $this, 'admin_menu_icon_style' ) );
	}

	/**
	 * Register the admin menu and sub-pages.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			esc_html__( 'WC Hide Price', 'wc-hide-price-ask-button' ),
			esc_html__( 'WC Hide Price', 'wc-hide-price-ask-button' ),
			'manage_woocommerce',
			'wchpab-categories',
			array( $this, 'render_categories_page' ),
			$this->get_menu_icon(),
			56
		);

		add_submenu_page(
			'wchpab-categories',
			esc_html__( 'Hide Category Prices', 'wc-hide-price-ask-button' ),
			esc_html__( 'Hide Category Prices', 'wc-hide-price-ask-button' ),
			'manage_woocommerce',
			'wchpab-categories',
			array( $this, 'render_categories_page' )
		);

		add_submenu_page(
			'wchpab-categories',
			esc_html__( 'Button Settings', 'wc-hide-price-ask-button' ),
			esc_html__( 'Button Settings', 'wc-hide-price-ask-button' ),
			'manage_woocommerce',
			'wchpab-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'wchpab-categories',
			esc_html__( 'About', 'wc-hide-price-ask-button' ),
			esc_html__( 'About', 'wc-hide-price-ask-button' ),
			'manage_woocommerce',
			'wchpab-about',
			array( $this, 'render_about_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles on plugin pages only.
	 *
	 * @since  1.0.0
	 * @param  string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Instead of relying on brittle hook suffixes, check the 'page' parameter directly.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$plugin_pages = array(
			'wchpab-categories',
			'wchpab-settings',
			'wchpab-about',
		);

		if ( ! in_array( $current_page, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wchpab-admin',
			WCHPAB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			filemtime( WCHPAB_PLUGIN_DIR . 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'wchpab-admin',
			WCHPAB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			filemtime( WCHPAB_PLUGIN_DIR . 'assets/js/admin.js' ),
			true
		);

		// Pass excluded product IDs for conflict checking on the products page.
		$excluded_ids = get_option( 'wchpab_excluded_products', array() );

		wp_localize_script( 'wchpab-admin', 'wchpab_admin', array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'wchpab_admin_nonce' ),
			'excluded_products' => array_map( 'absint', $excluded_ids ),
			'hidden_products'   => array_map( 'absint', get_option( 'wchpab_hidden_products', array() ) ),
			'i18n'              => array(
				'searching'        => esc_html__( 'Searching…', 'wc-hide-price-ask-button' ),
				'no_results'       => esc_html__( 'No results found.', 'wc-hide-price-ask-button' ),
				'remove'           => esc_html__( 'Remove', 'wc-hide-price-ask-button' ),
				'saving'           => esc_html__( 'Saving…', 'wc-hide-price-ask-button' ),
				'saved'            => esc_html__( 'Changes saved successfully.', 'wc-hide-price-ask-button' ),
				'save_error'       => esc_html__( 'An error occurred while saving.', 'wc-hide-price-ask-button' ),
				'search'           => esc_html__( 'Search', 'wc-hide-price-ask-button' ),
				'limit_reached'    => esc_html__( 'Maximum limit reached! Upgrade to Pro for unlimited items.', 'wc-hide-price-ask-button' ),
			),
		) );
	}


	/**
	 * Render the Hide Category Prices admin page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_categories_page() {
		$saved_cat_ids     = get_option( 'wchpab_hidden_categories', array() );
		$saved_exclude_ids = get_option( 'wchpab_excluded_products', array() );
		$has_categories    = ! empty( $saved_cat_ids );
		$settings          = get_option( 'wchpab_button_settings', array() );
		$saved_cat_ids     = get_option( 'wchpab_hidden_categories', array() );
		$saved_exclude_ids = get_option( 'wchpab_excluded_products', array() );
		$has_categories    = ! empty( $saved_cat_ids );
		?>
		<div class="wrap wchpab-admin-wrap">
			<div style="background: linear-gradient(90deg, #f0f4f8, #e2e8f0); border-left: 4px solid #2271b1; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div>
					<strong style="font-size: 14px; color: #1e293b;"><span class="dashicons dashicons-star-filled" style="color: #f59e0b; margin-top: -2px;"></span> Unlock the Pro Version</strong>
					<p style="margin: 4px 0 0; color: #475569; font-size: 13px;">Get unlimited hidden categories, exclude unlimited products, hide by specific user roles, and fully customize your buttons!</p>
				</div>
				<a href="https://sygraphe.com/wc-hide-price-ask-button" target="_blank" class="button button-primary" style="background: #2271b1; border-color: #2271b1;">Upgrade to Pro</a>
			</div>
			<h1><?php esc_html_e( 'Hide Category Prices', 'wc-hide-price-ask-button' ); ?></h1>

			<p class="description"><?php esc_html_e( 'Search for product categories by name or ID and add them to the hidden price list. Maximum 10 categories.', 'wc-hide-price-ask-button' ); ?></p>


			<div class="wchpab-search-section">
				<label for="wchpab-category-search"><?php esc_html_e( 'Search Categories', 'wc-hide-price-ask-button' ); ?></label>
				<div class="wchpab-search-row">
					<input
						type="text"
						id="wchpab-category-search"
						class="wchpab-search-input"
						placeholder="<?php esc_attr_e( 'Type a category name or ID…', 'wc-hide-price-ask-button' ); ?>"
						autocomplete="off"
					/>
					<button type="button" id="wchpab-category-search-btn" class="button wchpab-search-btn">
						<span class="dashicons dashicons-search"></span>
						<?php esc_html_e( 'Search', 'wc-hide-price-ask-button' ); ?>
					</button>
				</div>
				<div id="wchpab-category-results" class="wchpab-search-results"></div>
			</div>

			<div class="wchpab-tags-section">
				<h3><?php esc_html_e( 'Categories', 'wc-hide-price-ask-button' ); ?></h3>
				<div id="wchpab-category-tags" class="wchpab-tags-container" data-type="categories" data-max-items="10">
					<?php
					if ( empty( $saved_cat_ids ) ) {
						echo '<span class="wchpab-no-tags-message">' . esc_html__( 'No categories selected yet.', 'wc-hide-price-ask-button' ) . '</span>';
					} else {
						foreach ( $saved_cat_ids as $cat_id ) {
							$term = get_term( $cat_id, 'product_cat' );
							if ( $term && ! is_wp_error( $term ) ) {
								$label = sprintf( '#%d — %s', $cat_id, $term->name );
								printf(
									'<span class="wchpab-tag" data-id="%d">%s<button type="button" class="wchpab-tag-remove" aria-label="%s">&times;</button><input type="hidden" name="wchpab_category_ids[]" value="%d" /></span>',
									esc_attr( $cat_id ),
									esc_html( $label ),
									esc_attr__( 'Remove', 'wc-hide-price-ask-button' ),
									esc_attr( $cat_id )
								);
							}
						}
					}
					?>
				</div>
			</div>

			<div class="wchpab-save-section">
				<button type="button" id="wchpab-save-categories" class="button button-primary" data-save-action="wchpab_save_categories">
					<?php esc_html_e( 'Save Categories', 'wc-hide-price-ask-button' ); ?>
				</button>
				<span class="wchpab-save-notice" id="wchpab-categories-notice"></span>
			</div>

			<!-- Exclude Products Section -->
			<hr class="wchpab-section-divider" />

			<div id="wchpab-exclude-section" class="wchpab-exclude-section" style="<?php echo $has_categories ? '' : 'display:none;'; ?>">
				<h2><?php esc_html_e( 'Exclude Products', 'wc-hide-price-ask-button' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Products listed here will keep their prices visible even if they belong to a hidden category above. Maximum 20 products.', 'wc-hide-price-ask-button' ); ?></p>

				<div class="wchpab-search-section">
					<label for="wchpab-exclude-search"><?php esc_html_e( 'Search Products to Exclude', 'wc-hide-price-ask-button' ); ?></label>
					<div class="wchpab-search-row">
						<input
							type="text"
							id="wchpab-exclude-search"
							class="wchpab-search-input"
							placeholder="<?php esc_attr_e( 'Type a product name, ID, or SKU…', 'wc-hide-price-ask-button' ); ?>"
							autocomplete="off"
						/>
						<button type="button" id="wchpab-exclude-search-btn" class="button wchpab-search-btn">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Search', 'wc-hide-price-ask-button' ); ?>
						</button>
					</div>
					<div id="wchpab-exclude-results" class="wchpab-search-results"></div>
				</div>

				<div class="wchpab-tags-section">
					<h3><?php esc_html_e( 'Excluded Products', 'wc-hide-price-ask-button' ); ?></h3>
					<div id="wchpab-exclude-tags" class="wchpab-tags-container" data-type="excluded" data-max-items="20">
						<?php
						if ( empty( $saved_exclude_ids ) ) {
							echo '<span class="wchpab-no-tags-message">' . esc_html__( 'No products excluded yet.', 'wc-hide-price-ask-button' ) . '</span>';
						} else {
							foreach ( $saved_exclude_ids as $product_id ) {
								$product = wc_get_product( $product_id );
								if ( $product ) {
									$sku   = $product->get_sku();
									$label = $sku
										? sprintf( '#%d — %s (SKU: %s)', $product_id, $product->get_name(), $sku )
										: sprintf( '#%d — %s', $product_id, $product->get_name() );
									printf(
										'<span class="wchpab-tag" data-id="%d">%s<button type="button" class="wchpab-tag-remove" aria-label="%s">&times;</button><input type="hidden" name="wchpab_exclude_ids[]" value="%d" /></span>',
										esc_attr( $product_id ),
										esc_html( $label ),
										esc_attr__( 'Remove', 'wc-hide-price-ask-button' ),
										esc_attr( $product_id )
									);
								}
							}
						}
						?>
					</div>
				</div>

				<div class="wchpab-save-section">
					<button type="button" id="wchpab-save-excluded" class="button button-primary" data-save-action="wchpab_save_excluded">
						<?php esc_html_e( 'Save Excluded Products', 'wc-hide-price-ask-button' ); ?>
					</button>
					<span class="wchpab-save-notice" id="wchpab-excluded-notice"></span>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Button Settings admin page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_settings_page() {
		$settings = get_option( 'wchpab_button_settings', array() );

		$product_text_mode  = isset( $settings['product_text_mode'] ) ? $settings['product_text_mode'] : 'default';
		$product_text       = isset( $settings['product_text'] ) ? $settings['product_text'] : '';
		$category_text_mode = isset( $settings['category_text_mode'] ) ? $settings['category_text_mode'] : 'default';
		$category_text      = isset( $settings['category_text'] ) ? $settings['category_text'] : '';
		$target_product     = isset( $settings['target_product'] ) ? (bool) $settings['target_product'] : true;
		$target_archive     = isset( $settings['target_archive'] ) ? (bool) $settings['target_archive'] : true;

		$default_text = esc_html__( 'Ask for product', 'wc-hide-price-ask-button' );

		?>
		<div class="wrap wchpab-admin-wrap">
			<div style="background: linear-gradient(90deg, #f0f4f8, #e2e8f0); border-left: 4px solid #2271b1; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<div>
					<strong style="font-size: 14px; color: #1e293b;"><span class="dashicons dashicons-star-filled" style="color: #f59e0b; margin-top: -2px;"></span> Unlock the Pro Version</strong>
					<p style="margin: 4px 0 0; color: #475569; font-size: 13px;">Get unlimited hidden categories, exclude unlimited products, hide by specific user roles, and fully customize your buttons!</p>
				</div>
				<a href="https://sygraphe.com/wc-hide-price-ask-button" target="_blank" class="button button-primary" style="background: #2271b1; border-color: #2271b1;">Upgrade to Pro</a>
			</div>
			<h1><?php esc_html_e( 'Button Settings', 'wc-hide-price-ask-button' ); ?></h1>

			<form id="wchpab-settings-form">
				<table class="form-table" role="presentation">
					<!-- THEME INTEGRATION HEADER -->
					<tr>
						<th colspan="2">
							<h2 style="margin-bottom: 0; padding-bottom: 0; "><?php esc_html_e( 'Theme Integration', 'wc-hide-price-ask-button' ); ?></h2>
						</th>
					</tr>

					<!-- Replace Elements Target -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Replace Elements On', 'wc-hide-price-ask-button' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="target_product" value="1" <?php checked( $target_product ); ?> />
									<?php esc_html_e( 'Product Page', 'wc-hide-price-ask-button' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="target_archive" value="1" <?php checked( $target_archive ); ?> />
									<?php esc_html_e( 'Product List / Archive', 'wc-hide-price-ask-button' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>

					<!-- PRODUCT PAGE SETTINGS HEADER -->
					<tr class="wchpab-contact-setting-row">
						<th colspan="2">
							<h2 style="margin-bottom: 0; padding-bottom: 0; border-top: 1px solid #ccd0d4; padding-top: 20px;"><?php esc_html_e( 'Product Page Button Settings', 'wc-hide-price-ask-button' ); ?></h2>
						</th>
					</tr>

					<!-- Product Page Button Text -->
					<tr class="wchpab-contact-setting-row">
						<th scope="row"><?php esc_html_e( 'Button Text', 'wc-hide-price-ask-button' ); ?></th>
						<td>
							<fieldset>
								<label class="wchpab-radio-label">
									<input type="radio" name="product_text_mode" value="default" <?php checked( $product_text_mode, 'default' ); ?> />
									<?php esc_html_e( 'Default:', 'wc-hide-price-ask-button' ); ?>
									<input type="text" value="<?php echo esc_attr( $default_text ); ?>" readonly class="regular-text wchpab-readonly-input" />
								</label>
								<br />
								<label class="wchpab-radio-label">
									<input type="radio" name="product_text_mode" value="custom" <?php checked( $product_text_mode, 'custom' ); ?> />
									<?php esc_html_e( 'Custom:', 'wc-hide-price-ask-button' ); ?>
									<input type="text" name="product_text" value="<?php echo esc_attr( $product_text ); ?>" maxlength="30" class="regular-text wchpab-custom-input" <?php echo 'default' === $product_text_mode ? 'disabled' : ''; ?> />
								</label>
								<p class="description"><?php esc_html_e( 'Maximum 30 characters.', 'wc-hide-price-ask-button' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<!-- CATEGORY PAGE SETTINGS HEADER -->
					<tr class="wchpab-contact-setting-row">
						<th colspan="2">
							<h2 style="margin-bottom: 0; padding-bottom: 0; border-top: 1px solid #ccd0d4; padding-top: 20px;"><?php esc_html_e( 'Category Page Button Settings', 'wc-hide-price-ask-button' ); ?></h2>
						</th>
					</tr>

					<!-- Category Page Button Text -->
					<tr class="wchpab-contact-setting-row">
						<th scope="row"><?php esc_html_e( 'Button Text', 'wc-hide-price-ask-button' ); ?></th>
						<td>
							<fieldset>
								<label class="wchpab-radio-label">
									<input type="radio" name="category_text_mode" value="default" <?php checked( $category_text_mode, 'default' ); ?> />
									<?php esc_html_e( 'Default:', 'wc-hide-price-ask-button' ); ?>
									<input type="text" value="<?php echo esc_attr( $default_text ); ?>" readonly class="regular-text wchpab-readonly-input" />
								</label>
								<br />
								<label class="wchpab-radio-label">
									<input type="radio" name="category_text_mode" value="custom" <?php checked( $category_text_mode, 'custom' ); ?> />
									<?php esc_html_e( 'Custom:', 'wc-hide-price-ask-button' ); ?>
									<input type="text" name="category_text" value="<?php echo esc_attr( $category_text ); ?>" maxlength="30" class="regular-text wchpab-custom-input" <?php echo 'default' === $category_text_mode ? 'disabled' : ''; ?> />
								</label>
								<p class="description"><?php esc_html_e( 'Maximum 30 characters.', 'wc-hide-price-ask-button' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>

				<div class="wchpab-save-section">
					<button type="button" id="wchpab-save-settings" class="button button-primary" data-save-action="wchpab_save_settings">
						<?php esc_html_e( 'Save Settings', 'wc-hide-price-ask-button' ); ?>
					</button>
					<span class="wchpab-save-notice" id="wchpab-settings-notice"></span>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the About admin page.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function render_about_page() {
		?>
		<div class="wrap wchpab-admin-wrap">
			<h1><?php esc_html_e( 'About WC Hide Price & Ask Button', 'wc-hide-price-ask-button' ); ?></h1>

			<div class="wchpab-about-card">
				<h2><?php esc_html_e( 'About This Plugin', 'wc-hide-price-ask-button' ); ?></h2>
				<p>
					<?php esc_html_e( 'WC Hide Price & Ask Button is a powerful WooCommerce extension that transforms your online store into a quote-based or inquiry-driven sales platform. Perfect for B2B stores, wholesale businesses, custom product manufacturers, or any shop that prefers personal communication before sales.', 'wc-hide-price-ask-button' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Instead of displaying prices and "Add to Cart" buttons, this plugin replaces them with customizable inquiry buttons that open a professional contact form.', 'wc-hide-price-ask-button' ); ?>
				</p>
			</div>

			<div class="wchpab-about-card">
				<h2><?php esc_html_e( 'Free Version Features', 'wc-hide-price-ask-button' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Hide prices for up to 10 product categories.', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Exclude up to 20 specific products from being hidden.', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Hide prices from everyone and show a professional AJAX-powered modal contact form.', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Customize button text for product pages and archive pages.', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Automatically include product name, ID, SKU, and link in inquiry emails.', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php
						printf(
							/* translators: %s: Admin email address. */
							esc_html__( 'All inquiries will be sent directly to your admin email address (%s).', 'wc-hide-price-ask-button' ),
							'<strong>' . esc_html( get_option( 'admin_email' ) ) . '</strong>'
						);
					?></li>
				</ul>
			</div>

			<div class="wchpab-about-card" style="border-left: 4px solid #f59e0b; background: #fffbeb;">
				<h2><?php esc_html_e( 'Unlock More with Pro!', 'wc-hide-price-ask-button' ); ?></h2>
				<p><?php esc_html_e( 'Upgrade to the Pro version to get these advanced capabilities:', 'wc-hide-price-ask-button' ); ?></p>
				<ul style="list-style-type: disc; margin-left: 20px;">
					<li><strong><?php esc_html_e( 'Unlimited Hide Rules:', 'wc-hide-price-ask-button' ); ?></strong> <?php esc_html_e( 'Hide unlimited categories and exclude unlimited products.', 'wc-hide-price-ask-button' ); ?></li>
					<li><strong><?php esc_html_e( 'Global Overrides:', 'wc-hide-price-ask-button' ); ?></strong> <?php esc_html_e( 'Hide prices from all products globally with a single click.', 'wc-hide-price-ask-button' ); ?></li>
					<li><strong><?php esc_html_e( 'Role-based Visibility:', 'wc-hide-price-ask-button' ); ?></strong> <?php esc_html_e( 'Hide prices from specific user roles or guests only.', 'wc-hide-price-ask-button' ); ?></li>
					<li><strong><?php esc_html_e( 'Advanced Design Customization:', 'wc-hide-price-ask-button' ); ?></strong> <?php esc_html_e( 'Select HTML tags, alignment, add Dashicons, and inject CSS classes into buttons.', 'wc-hide-price-ask-button' ); ?></li>
					<li><strong><?php esc_html_e( 'Custom Contact Forms & Emails:', 'wc-hide-price-ask-button' ); ?></strong> <?php esc_html_e( 'Send inquiries to a custom email address and customize login prompts.', 'wc-hide-price-ask-button' ); ?></li>
				</ul>
				<br>
				<a href="https://sygraphe.com/wc-hide-price-ask-button" target="_blank" class="button button-primary" style="background: #f59e0b; border-color: #f59e0b; font-size: 14px; padding: 5px 15px; height: auto;">Upgrade to Pro Today</a>
			</div>

			<div class="wchpab-about-card">
				<h2><?php esc_html_e( 'Perfect For', 'wc-hide-price-ask-button' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'B2B and wholesale businesses requiring quote requests', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Custom product manufacturers with variable pricing', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Exclusive or luxury product retailers', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Stores with member-only or role-based pricing', 'wc-hide-price-ask-button' ); ?></li>
					<li><?php esc_html_e( 'Businesses preferring personal consultation before sales', 'wc-hide-price-ask-button' ); ?></li>
				</ul>
			</div>

			<div class="wchpab-about-card" style="border-left: 4px solid #d63638;">
				<h2><?php esc_html_e( 'Important Notice', 'wc-hide-price-ask-button' ); ?></h2>
				<p>
					<?php esc_html_e( 'WordPress themes are built differently, and each theme may have its own unique structure and styling. While this plugin is designed to work with most themes, some features (such as button alignment or custom CSS classes) may not work as intended on certain themes due to theme-specific CSS overrides or layout structures.', 'wc-hide-price-ask-button' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'If you experience any compatibility issues, you may need to add custom CSS to your theme or consult with your theme developer. The plugin provides extensive customization options (HTML tags, CSS classes, alignment controls) to help you achieve the desired integration with your specific theme.', 'wc-hide-price-ask-button' ); ?>
				</p>
			</div>

			<div class="wchpab-about-card">
				<p>
					<?php
					printf(
						/* translators: %s: Plugin version number. */
						esc_html__( 'Version %s', 'wc-hide-price-ask-button' ),
						esc_html( WCHPAB_VERSION )
					);
					?>
				</p>
			</div>
		</div>
	<?php
	}

	/**
	 * Add a Settings link to the plugin list page.
	 *
	 * @since  1.3.0
	 * @param  array $links Array of plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=wchpab-categories' ) . '">' . esc_html__( 'Settings', 'wc-hide-price-ask-button' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get the base64-encoded SVG icon for the admin menu.
	 *
	 * @since  1.3.0
	 * @return string Data URI for the SVG icon.
	 */
	private function get_menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode( '
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<defs>
					<mask id="wchpab-slash-cut">
						<rect width="24" height="24" fill="white" stroke="none" />
						<line x1="22" y1="2" x2="2" y2="22" fill="none" stroke="black" stroke-width="4" stroke-linecap="round" />
					</mask>
				</defs>
				<g mask="url(#wchpab-slash-cut)">
					<g transform="translate(12, 12) scale(1.15) translate(-11.7, -12.2)" stroke-width="1.74">
						<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V5a2 2 0 0 1 2-2h7l8.59 8.59a2 2 0 0 1 0 2.82z"/>
						<circle cx="6" cy="7" r="1.5"/>
					</g>
					<path d="M 14 8 A 4 4 0 1 0 14 16 M 9.5 10 h 4.5 M 9.5 14 h 4.5" />
				</g>
				<line x1="22" y1="2" x2="2" y2="22" />
			</svg>
		' );
	}

	/**
	 * Simplified icon styling for the admin page headers.
	 * The sidebar icon is handled natively by the SVG in add_menu_page.
	 *
	 * @since  1.3.0
	 * @return void
	 */
	public function admin_menu_icon_style() {
		$icon_svg = $this->get_menu_icon();

		echo '<style>
			/* Sidebar Hover Integration: WordPress handles most of this for SVGs, 
			   but we can ensure smooth opacity transitions */
			#toplevel_page_wchpab-categories .wp-menu-image {
				opacity: 0.7;
				transition: opacity 0.1s ease-in-out;
			}
			#toplevel_page_wchpab-categories:hover .wp-menu-image,
			#toplevel_page_wchpab-categories.wp-has-current-submenu .wp-menu-image {
				opacity: 1;
			}

			/* Content Page Header Icon */
			.toplevel_page_wchpab-categories #icon-wchpab-categories,
			.toplevel_page_wchpab-categories .icon32,
			.toplevel_page_wchpab-categories .wp-header-end + h1:before {
				 content: "";
				 display: inline-block;
				 width: 32px;
				 height: 32px;
				 vertical-align: middle;
				 margin-right: 12px;
				 background: url("' . esc_url( $icon_svg ) . '") no-repeat center;
				 background-size: 28px auto;
			}
		</style>';
	}
}
