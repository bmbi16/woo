<?php
/**
 * Handles the frontend display of custom fields on the product page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WDOP_Frontend {

    /**
     * Constructor.
     */
    public function __construct() {
        // Add the hook for displaying the form based on settings AFTER setup theme.
        add_action( 'wp', array( $this, 'add_form_display_hook' ) );

        // Hook to hide the default Add to Cart button - using CSS is preferred.
        // add_action( 'woocommerce_single_product_summary', array( $this, 'hide_add_to_cart_button' ), 1 );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add the hook for displaying the form based on settings.
     * Hooked into 'wp' to ensure settings and theme hooks are available.
     */
    public function add_form_display_hook() {
        // Only proceed on single product pages
        if (!is_product()) {
            return;
        }

        // Check if plugin is generally enabled first
        if ( ! WDOP_Admin::get_setting( 'enable_plugin' ) ) {
            return;
        }

        $placement_hook = WDOP_Admin::get_setting( 'form_placement', 'woocommerce_single_product_summary' );
        $priority = 35; // Default priority

        // Adjust priority based on hook
        if ($placement_hook === 'woocommerce_single_product_summary') {
             $priority = 35; // After price, before meta
        } elseif ($placement_hook === 'woocommerce_before_add_to_cart_form') {
             $priority = 10;
        } elseif ($placement_hook === 'woocommerce_after_add_to_cart_form') {
             $priority = 10;
        } elseif ($placement_hook === 'woocommerce_after_single_product_summary') {
             $priority = 15; // After meta, tabs etc.
        }

        // Add the action based on the setting
        if ( is_string($placement_hook) && !empty($placement_hook) ) {
             add_action( $placement_hook, array( $this, 'display_custom_fields_wrapper' ), $priority );
        } else {
            // Fallback if setting is somehow invalid
            add_action( 'woocommerce_single_product_summary', array( $this, 'display_custom_fields_wrapper' ), 35 );
        }
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        // Only load on single product pages if the plugin is enabled
        if ( is_product() && WDOP_Admin::get_setting( 'enable_plugin' ) ) {
            wp_enqueue_style( 'wdop-frontend-css', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/wdop-frontend.css', array(), WDOP_VERSION ); // Use dirname()
            wp_enqueue_script( 'wdop-frontend-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/wdop-frontend.js', array( 'jquery' ), WDOP_VERSION, true ); // Use dirname()

            wp_localize_script( 'wdop-frontend-js', 'wdop_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wdop_get_communes_nonce' ),
                'select_commune_text' => __( 'اختر البلدية', 'wp-direct-order-plugin' ),
                'select_state_first_text' => __( 'اختر الولاية أولاً', 'wp-direct-order-plugin' ),
                'loading_text' => __( 'جاري التحميل...', 'wp-direct-order-plugin' ),
                'no_communes_found_text' => __( 'لم يتم العثور على بلديات', 'wp-direct-order-plugin' ),
                'error_loading_text' => __( 'خطأ في تحميل البلديات', 'wp-direct-order-plugin' ),
                'select_shipping_text' => __( 'الرجاء اختيار طريقة الشحن.', 'wp-direct-order-plugin' )
            ) );
        }
    }

    /**
     * Wrapper function called by the hook. Performs checks before calling the main display function.
     */
    public function display_custom_fields_wrapper() {
        global $product;

        // Double-check plugin enabled status and product validity
        if ( ! WDOP_Admin::get_setting( 'enable_plugin' ) ) return;
        if ( ! $product || ! is_a($product, 'WC_Product') || ! $product->is_purchasable() ) return;

        // Call the actual display function
        $this->display_custom_fields( $product );
    }


    /**
     * Display the custom fields form on the single product page.
     * Now accepts $product as an argument.
     */
    private function display_custom_fields( $product ) {

        // Get all settings using the static method
        $settings = WDOP_Admin::get_setting(null);
        if (empty($settings)) {
            // Optional: Display an admin-only notice if settings fail to load
            if (current_user_can('manage_options')) {
                echo '<p style="color:red;">' . esc_html__('WDOP Error: Failed to load settings.', 'wp-direct-order-plugin') . '</p>';
            }
            return; // Cannot proceed without settings
        }

        // Use helper function to safely get setting values
        $get_setting = function($key) use ($settings) {
            return isset($settings[$key]) ? $settings[$key] : null;
        };

        $is_full_name_enabled = $get_setting('field_enable_full_name');
        $is_full_name_required = $get_setting('field_required_full_name');
        $is_phone_enabled = $get_setting('field_enable_phone_number');
        $is_phone_required = $get_setting('field_required_phone_number');
        $is_wilaya_enabled = $get_setting('field_enable_wilaya');
        $is_wilaya_required = $get_setting('field_required_wilaya');
        $is_commune_enabled = $get_setting('field_enable_commune');
        $is_commune_required = $get_setting('field_required_commune');

        $is_custom1_enabled = $get_setting('field_enable_custom1');
        $is_custom1_required = $get_setting('field_required_custom1');
        $label_custom1 = $get_setting('field_label_custom1');
        $is_custom2_enabled = $get_setting('field_enable_custom2');
        $is_custom2_required = $get_setting('field_required_custom2');
        $label_custom2 = $get_setting('field_label_custom2');

        $wilayas = $is_wilaya_enabled ? wdop_get_wilayas() : array();

        ?>
        <form class="wdop-order-form" method="post">
            <div class="wdop-fields-wrapper">
                <?php wp_nonce_field( 'wdop_place_order_nonce', 'wdop_order_nonce' ); ?>
                <input type="hidden" name="wdop_product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">

                <?php // Core Fields - Check if enabled before rendering ?>
                <?php if ( $is_full_name_enabled ) : ?>
                <p class="form-row form-row-wide wdop-field-fullname">
                    <label for="wdop_full_name"><?php echo esc_html__( 'الاسم الكامل', 'wp-direct-order-plugin' ); ?><?php if ( $is_full_name_required ) echo ' <span class="required">*</span>'; ?></label>
                    <input type="text" class="input-text" name="wdop_full_name" id="wdop_full_name" <?php if ( $is_full_name_required ) echo 'required'; ?>>
                </p>
                <?php else: ?>
                <!-- Debug: Full Name Not Enabled -->
                <?php endif; ?>

                <?php if ( $is_phone_enabled ) : ?>
                <p class="form-row form-row-wide wdop-field-number">
                    <label for="wdop_phone_number"><?php echo esc_html__( 'رقم الهاتف', 'wp-direct-order-plugin' ); ?><?php if ( $is_phone_required ) echo ' <span class="required">*</span>'; ?></label>
                    <input type="tel" class="input-text" name="wdop_phone_number" id="wdop_phone_number" <?php if ( $is_phone_required ) echo 'required'; ?>>
                </p>
                 <?php else: ?>
                <!-- Debug: Phone Not Enabled -->
                <?php endif; ?>

                <?php if ( $is_wilaya_enabled ) : ?>
                <p class="form-row form-row-wide wdop-field-wilaya">
                    <label for="wdop_wilaya"><?php echo esc_html__( 'الولاية', 'wp-direct-order-plugin' ); ?><?php if ( $is_wilaya_required ) echo ' <span class="required">*</span>'; ?></label>
                    <select name="wdop_wilaya" id="wdop_wilaya" class="wdop-select" <?php if ( $is_wilaya_required ) echo 'required'; ?>>
                        <option value=""><?php echo esc_html__( 'اختر الولاية', 'wp-direct-order-plugin' ); ?></option>
                        <?php if (is_array($wilayas)) : // Ensure $wilayas is an array ?>
                            <?php foreach ( $wilayas as $code => $name ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </p>
                 <?php else: ?>
                <!-- Debug: Wilaya Not Enabled -->
                <?php endif; ?>

                <?php if ( $is_commune_enabled && $is_wilaya_enabled ) : // Commune depends on Wilaya ?>
                <p class="form-row form-row-wide wdop-field-commune">
                    <label for="wdop_commune"><?php echo esc_html__( 'البلدية', 'wp-direct-order-plugin' ); ?><?php if ( $is_commune_required ) echo ' <span class="required">*</span>'; ?></label>
                    <select name="wdop_commune" id="wdop_commune" class="wdop-select" <?php if ( $is_commune_required ) echo 'required'; ?> disabled>
                        <option value=""><?php echo esc_html__( 'اختر الولاية أولاً', 'wp-direct-order-plugin' ); ?></option>
                    </select>
                </p>
                 <?php else: ?>
                <!-- Debug: Commune Not Enabled (or Wilaya disabled) -->
                <?php endif; ?>

                <?php // Optional Custom Field 1 ?>
                <?php if ( $is_custom1_enabled && !empty($label_custom1) ) : ?>
                <p class="form-row form-row-wide wdop-field-custom1">
                    <label for="wdop_custom1"><?php echo esc_html( $label_custom1 ); ?><?php if ( $is_custom1_required ) echo ' <span class="required">*</span>'; ?></label>
                    <input type="text" class="input-text" name="wdop_custom1" id="wdop_custom1" <?php if ( $is_custom1_required ) echo 'required'; ?>>
                </p>
                 <?php else: ?>
                <!-- Debug: Custom 1 Not Enabled -->
                <?php endif; ?>

                <?php // Optional Custom Field 2 ?>
                <?php if ( $is_custom2_enabled && !empty($label_custom2) ) : ?>
                <p class="form-row form-row-wide wdop-field-custom2">
                    <label for="wdop_custom2"><?php echo esc_html( $label_custom2 ); ?><?php if ( $is_custom2_required ) echo ' <span class="required">*</span>'; ?></label>
                    <input type="text" class="input-text" name="wdop_custom2" id="wdop_custom2" <?php if ( $is_custom2_required ) echo 'required'; ?>>
                </p>
                 <?php else: ?>
                <!-- Debug: Custom 2 Not Enabled -->
                <?php endif; ?>

                <?php // --- Shipping Method Field --- ?>
                <div class="wdop-shipping-methods">
                    <h2><?php esc_html_e( 'طريقة الشحن', 'wp-direct-order-plugin' ); ?></h2>
                    <?php
                    // Check if shipping is enabled in WooCommerce settings
                    if ( WC()->shipping() && WC()->shipping()->enabled ) {
                        // Ensure cart exists and calculate shipping if needed
                        if ( ! WC()->cart ) {
                            wc_load_cart(); // Load cart if it doesn't exist
                        }
                        // Temporarily set product for calculation? Or rely on existing cart state?
                        // Let's try calculating shipping based on the current product and potentially customer session data
                        WC()->cart->calculate_shipping();
                        $packages = WC()->cart->get_shipping_packages();
                        $shipping_methods_available = false;

                        if ( is_array($packages) && ! empty( $packages ) ) {
                            foreach ( $packages as $i => $package ) {
                                if ( isset( $package['rates'] ) && is_array( $package['rates'] ) && ! empty( $package['rates'] ) ) {
                                    if ( ! $shipping_methods_available ) {
                                        echo '<ul id="wdop-shipping-method-list-' . esc_attr( $i ) . '" class="wdop-shipping-method-list">';
                                    }
                                    foreach ( $package['rates'] as $rate_id => $rate ) {
                                        if ( is_object($rate) && $rate instanceof WC_Shipping_Rate ) {
                                            echo '<li>';
                                            echo '<input type="radio" name="shipping_method[' . $i . ']" data-index="' . $i . '" id="shipping_method_' . $i . '_' . esc_attr( sanitize_title( $rate_id ) ) . '" value="' . esc_attr( $rate_id ) . '" class="shipping_method" required>'; // Added required here
                                            echo '<label for="shipping_method_' . $i . '_' . esc_attr( sanitize_title( $rate_id ) ) . '">' . wp_kses_post( $rate->get_label() ) . '</label>';
                                            echo '</li>';
                                            $shipping_methods_available = true;
                                        }
                                    }
                                    if ( $shipping_methods_available ) {
                                        echo '</ul>';
                                    }
                                }
                            }
                        }

                        if ( ! $shipping_methods_available ) {
                            echo '<p>' . esc_html__( 'لا توجد طرق شحن متاحة حاليًا. يرجى التأكد من تكوين مناطق الشحن في إعدادات WooCommerce.', 'wp-direct-order-plugin' ) . '</p>';
                        }
                    } else {
                        echo '<p>' . esc_html__( 'الشحن غير مفعل في إعدادات WooCommerce.', 'wp-direct-order-plugin' ) . '</p>';
                    }
                    ?>
                </div>
                <?php // --- End Shipping Method Field --- ?>

                <?php // Order Button ?>
                <p class="form-row form-row-wide wdop-submit-button">
                    <?php
                    $button_text = $get_setting('order_button_text');
                    if (empty($button_text)) {
                         $button_text = __( 'اطلب الآن', 'wp-direct-order-plugin' ); // Fallback
                    }
                    ?>
                    <button type="submit" name="wdop_place_order" class="button alt"><?php echo esc_html( $button_text ); ?></button>
                </p>
            </div>
        </form>
        <?php
    }
}

// Instantiate the class
new WDOP_Frontend();

