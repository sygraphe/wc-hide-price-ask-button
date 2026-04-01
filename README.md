# WC Hide Price & Ask Button

A WordPress/WooCommerce plugin that hides product prices and replaces the "Add to Cart" button with a customizable **"Ask for Product"** inquiry button and built-in contact form modal.

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-96588a?logo=woocommerce)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![License](https://img.shields.io/badge/License-GPLv3-green)

---

## Features (Free Version)

- **Hide Categories:** Hide prices for up to 10 product categories.
- **Exclude Products:** Exclude up to 20 specific products from being hidden.
- **Ask for Product Button:** Hide prices from everyone and show a professional AJAX-powered modal contact form.
- **Button Customization:** Customize button text for product pages and archive pages.
- **Built-in contact form modal** — no external form plugins needed.
- **Theme-compatible** — inherits your theme's button and form styling.
- **Fully translatable** — Greek translation included out of the box.

🚀 **[Need more power? Upgrade to the Pro Version!](https://sygraphe.com/wc-hide-price-ask-button)**  
Unlock unlimited hide rules, global overrides, role-based visibility, advanced design customizations (HTML tags, CSS classes, Dashicons), and custom email recipients.

## Installation

1. Download or clone this repository into `wp-content/plugins/`:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/sygraphe/wc-hide-price-ask-button.git
   ```
2. Activate the plugin via **Plugins → Installed Plugins** in WordPress admin.
3. Navigate to **WC Hide Price** in the admin sidebar to configure.

## Configuration

### Hide Category Prices
Search for product categories by **name** or **ID** and add them to the hidden price list (Max 10).

### Exclude Products
Selectively **exclude** individual products from category-wide hiding — they will keep their prices visible (Max 20).

### Button Settings
- **Text:** Customize text for Product Pages and Category/Archive Pages.
- **Theme Integration:** Enable/disable button replacement on product pages or archives independently.

## How It Works

1. Prices are hidden via the `woocommerce_get_price_html` filter
2. Add to Cart buttons are replaced on both archive (`woocommerce_loop_add_to_cart_link`) and single product pages (`woocommerce_single_product_summary`)
3. Clicking the replacement button opens a modal with a contact form
4. Form submissions are sent via AJAX and delivered to the WordPress admin email via `wp_mail()`

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## License

This project is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).
