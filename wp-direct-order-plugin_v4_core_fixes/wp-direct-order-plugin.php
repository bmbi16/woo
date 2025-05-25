<?php
/**
 * Plugin Name:       WP Direct Order for WooCommerce
 * Plugin URI:        https://example.com/plugins/wp-direct-order/
 * Description:       Adds custom fields to the WooCommerce product page for direct cash-on-delivery orders, bypassing the cart/checkout.
 * Version:           1.1.0
 * Author:            Manus AI
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-direct-order-plugin
 * Domain Path:       /languages
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * WC requires at least: 4.0
 * WC tested up to:   9.8.5
 * WC requires HPOS:  true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define constants
define( 'WDOP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WDOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WDOP_VERSION', '1.1.0' ); // Updated version

/**
 * Check if WooCommerce is active.
 */
if ( ! function_exists( 'wdop_is_woocommerce_active' ) ) {
    function wdop_is_woocommerce_active() {
        // Check if the WooCommerce class exists
        if ( class_exists( 'WooCommerce' ) ) {
            return true;
        }
        // Check if the WooCommerce plugin file is active
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
            return true;
        }
        return false;
    }
}

/**
 * Initialize the plugin.
 */
function wdop_init() {
    // Check for WooCommerce
    if ( ! wdop_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'wdop_woocommerce_missing_notice' );
        return;
    }

    // Load plugin text domain for translations
    load_plugin_textdomain( 'wp-direct-order-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    // Include required files
    require_once WDOP_PLUGIN_PATH . 'includes/wdop-functions.php';
    require_once WDOP_PLUGIN_PATH . 'includes/class-wdop-admin.php';
    require_once WDOP_PLUGIN_PATH . 'includes/class-wdop-frontend.php';
    require_once WDOP_PLUGIN_PATH . 'includes/class-wdop-ajax.php';
    require_once WDOP_PLUGIN_PATH . 'includes/class-wdop-order.php';

    // Instantiate classes (they self-instantiate in their files)
}
add_action( 'plugins_loaded', 'wdop_init', 10 ); // Ensure it runs after WC potentially loads

/**
 * Show admin notice if WooCommerce is not active.
 */
function wdop_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e( 'WP Direct Order Plugin requires WooCommerce to be installed and active.', 'wp-direct-order-plugin' ); ?></p>
    </div>
    <?php
}

/**
 * Activation hook.
 */
function wdop_activate() {
    // Ensure defaults are set on activation
    // Get existing options
    $options = get_option( 'wdop_settings' );
    // If options don't exist or are not an array, set defaults
    if ( false === $options || ! is_array( $options ) ) {
        // We need access to the Admin class defaults, but it might not be loaded yet.
        // Define basic defaults here or include the class file carefully.
        $basic_defaults = array(
            'enable_plugin' => 1,
            'field_enable_full_name' => 1,
            'field_required_full_name' => 1,
            'field_enable_phone_number' => 1,
            'field_required_phone_number' => 1,
            'field_enable_wilaya' => 1,
            'field_required_wilaya' => 1,
            'field_enable_commune' => 1,
            'field_required_commune' => 1,
            'form_placement' => 'woocommerce_single_product_summary',
            'order_button_text' => __( 'اطلب الآن', 'wp-direct-order-plugin' ),
        );
        update_option( 'wdop_settings', $basic_defaults );
    }
    // Add a transient to show a welcome/setup notice (optional)
    set_transient( 'wdop_activated', true, 30 );
}
register_activation_hook( __FILE__, 'wdop_activate' );

/**
 * Deactivation hook.
 */
function wdop_deactivate() {
    // Optional: Clean up tasks on deactivation
    // flush_rewrite_rules(); // Example if using custom post types/taxonomies
}
register_deactivation_hook( __FILE__, 'wdop_deactivate' );

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

