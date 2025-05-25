<?php
/**
 * Handles order creation and processing for the WP Direct Order Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WDOP_Order {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'check_for_order_submission' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_custom_fields_in_admin_order' ), 10, 1 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_custom_fields_in_customer_order' ), 10, 1 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'display_custom_fields_in_emails' ), 10, 4 );
    }

    /**
     * Check if the custom order form has been submitted and process it.
     */
    public function check_for_order_submission() {
        // Strict check for POST request and our specific action
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['wdop_place_order'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['wdop_order_nonce'] ) || ! wp_verify_nonce( $_POST['wdop_order_nonce'], 'wdop_place_order_nonce' ) ) {
            wc_add_notice( __( 'Invalid request. Please try again.', 'wp-direct-order-plugin' ), 'error' );
            wp_safe_redirect( remove_query_arg( 'wdop_error' ) ); // Redirect back without error flag
            exit;
        }

        // Check if plugin is enabled
        if ( ! WDOP_Admin::get_setting( 'enable_plugin', 1 ) ) {
            wc_add_notice( __( 'Ordering is currently disabled.', 'wp-direct-order-plugin' ), 'error' );
            wp_safe_redirect( remove_query_arg( 'wdop_error' ) );
            exit;
        }

        // Sanitize and retrieve form data
        $product_id = isset( $_POST['wdop_product_id'] ) ? absint( $_POST['wdop_product_id'] ) : 0;
        $full_name = isset( $_POST['wdop_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_full_name'] ) ) : '';
        $phone_number = isset( $_POST['wdop_phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_phone_number'] ) ) : '';
        $wilaya_code = isset( $_POST['wdop_wilaya'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_wilaya'] ) ) : '';
        $commune_key = isset( $_POST['wdop_commune'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_commune'] ) ) : '';
        $shipping_method_raw = isset( $_POST['shipping_method'] ) ? wc_clean( (array) $_POST['shipping_method'] ) : array();
        $shipping_method_id = !empty($shipping_method_raw) ? current($shipping_method_raw) : ''; // Get the first selected shipping method ID

        // Retrieve Optional Custom Fields
        $custom1_value = isset( $_POST['wdop_custom1'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_custom1'] ) ) : '';
        $custom2_value = isset( $_POST['wdop_custom2'] ) ? sanitize_text_field( wp_unslash( $_POST['wdop_custom2'] ) ) : '';

        // --- Validation --- 
        $errors = new WP_Error();
        $settings = WDOP_Admin::get_setting(null); // Get all settings

        // Helper function to safely get setting values
        $get_setting = function($key) use ($settings) {
            return isset($settings[$key]) ? $settings[$key] : null;
        };

        // Core Fields Validation
        if ( $get_setting('field_enable_full_name') && $get_setting('field_required_full_name') && empty( $full_name ) ) {
            $errors->add( 'validation', __( 'Please enter your full name.', 'wp-direct-order-plugin' ) );
        }
        if ( $get_setting('field_enable_phone_number') ) {
            if ( $get_setting('field_required_phone_number') && empty( $phone_number ) ) {
                $errors->add( 'validation', __( 'Please enter your phone number.', 'wp-direct-order-plugin' ) );
            } elseif ( ! empty( $phone_number ) ) {
                // Basic Algerian phone number validation (05, 06, 07 followed by 8 digits)
                if ( ! preg_match( '/^0[567]\d{8}$/', $phone_number ) ) {
                    $errors->add( 'validation', __( 'Please enter a valid Algerian phone number (e.g., 05xxxxxxxx, 06xxxxxxxx, 07xxxxxxxx).', 'wp-direct-order-plugin' ) );
                }
            }
        }
        if ( $get_setting('field_enable_wilaya') && $get_setting('field_required_wilaya') && empty( $wilaya_code ) ) {
            $errors->add( 'validation', __( 'Please select your Wilaya (State).', 'wp-direct-order-plugin' ) );
        }
        // Only validate commune if wilaya is enabled and commune is enabled/required
        if ( $get_setting('field_enable_wilaya') && $get_setting('field_enable_commune') && $get_setting('field_required_commune') && empty( $commune_key ) ) {
            $errors->add( 'validation', __( 'Please select your Commune.', 'wp-direct-order-plugin' ) );
        }

        // Optional Fields Validation
        if ( $get_setting('field_enable_custom1') && $get_setting('field_required_custom1') && empty( $custom1_value ) ) {
            $errors->add( 'validation', sprintf( __( 'Please fill in the required field: %s.', 'wp-direct-order-plugin' ), esc_html( $get_setting('field_label_custom1') ) ) );
        }
        if ( $get_setting('field_enable_custom2') && $get_setting('field_required_custom2') && empty( $custom2_value ) ) {
            $errors->add( 'validation', sprintf( __( 'Please fill in the required field: %s.', 'wp-direct-order-plugin' ), esc_html( $get_setting('field_label_custom2') ) ) );
        }

        // Shipping Validation
        $shipping_enabled = WC()->shipping() && WC()->shipping()->enabled;
        if ( $shipping_enabled && empty( $shipping_method_id ) ) {
             // Check if there were actually any methods available to select
             // This requires recalculating packages/rates based on potential address
             $temp_customer = new WC_Customer();
             $wilayas = wdop_get_wilayas();
             $wilaya_name = isset( $wilayas[$wilaya_code] ) ? $wilayas[$wilaya_code] : $wilaya_code;
             $temp_customer->set_shipping_state($wilaya_name);
             $temp_customer->set_shipping_country('DZ'); // Assume Algeria
             WC()->customer = $temp_customer; // Temporarily set customer for calculation
             WC()->cart->calculate_shipping();
             $packages = WC()->cart->get_shipping_packages();
             $rates_available = false;
             if (is_array($packages)) {
                 foreach ($packages as $package) {
                     if (!empty($package['rates'])) {
                         $rates_available = true;
                         break;
                     }
                 }
             }
             WC()->customer = new WC_Customer(); // Reset customer

             if ($rates_available) { // Only require selection if methods were available
                $errors->add( 'validation', __( 'Please select a shipping method.', 'wp-direct-order-plugin' ) );
             }
        }

        // Product Validation
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_purchasable() ) {
            $errors->add( 'product', __( 'The selected product cannot be purchased.', 'wp-direct-order-plugin' ) );
        }

        // Allow other plugins to add validation errors
        $errors = apply_filters( 'wdop_validate_order_submission', $errors, $_POST );

        // Handle Errors
        if ( $errors->has_errors() ) {
            foreach ( $errors->get_error_messages() as $message ) {
                wc_add_notice( $message, 'error' );
            }
            // Redirect back to the product page, preserving input if possible (complex)
            wp_safe_redirect( get_permalink( $product_id ) );
            exit;
        }

        // --- Create Order --- 
        try {
            $order = wc_create_order();
            if ( is_wp_error( $order ) ) {
                throw new Exception( $order->get_error_message() );
            }

            // Add product to order (assuming quantity 1 for now)
            $order->add_product( $product, 1 );

            // Get Wilaya and Commune names from codes/keys
            $wilayas = wdop_get_wilayas();
            $wilaya_name = isset( $wilayas[$wilaya_code] ) ? $wilayas[$wilaya_code] : $wilaya_code;
            $communes = wdop_get_communes_by_wilaya( $wilaya_code );
            $commune_name = isset( $communes[$commune_key] ) ? $communes[$commune_key] : $commune_key;

            // Set addresses (use Wilaya/Commune for state/city)
            $name_parts = explode( ' ', $full_name, 2 );
            $first_name = $name_parts[0];
            $last_name = isset( $name_parts[1] ) ? $name_parts[1] : '';

            $address = array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'phone'      => $phone_number,
                'state'      => $wilaya_name, // Use Wilaya name for state
                'city'       => $commune_name, // Use Commune name for city
                'country'    => 'DZ', // Assuming Algeria
                'address_1'  => $commune_name . ', ' . $wilaya_name, // Combine for address line 1
                'postcode'   => '', // Postcode might not be available or needed
            );
            $order->set_address( $address, 'billing' );
            $order->set_address( $address, 'shipping' );

            // Set payment method to Cash on Delivery
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            if ( isset( $payment_gateways['cod'] ) && $payment_gateways['cod']->is_available() ) {
                $order->set_payment_method( $payment_gateways['cod'] );
            } else {
                // Fallback or error if COD is not available
                throw new Exception( __( 'Cash on Delivery payment method is not available.', 'wp-direct-order-plugin' ) );
            }

            // Add shipping line item
            if ( $shipping_enabled && ! empty( $shipping_method_id ) ) {
                // Recalculate shipping for the order with the final address
                WC()->shipping->calculate_shipping_for_order( $order );
                $shipping_rates = $order->get_shipping_methods(); // Get rates calculated for the order
                $rate_found = null;

                // Find the selected rate among those calculated for the order
                // Note: WC()->shipping->calculate_shipping_for_order() doesn't directly return rates.
                // We need a way to match the selected $shipping_method_id to a calculated rate.
                // This might require simulating the cart/checkout process more closely or
                // relying on the previously calculated packages if the address hasn't changed significantly.

                // Let's try using the packages calculated during validation again
                $temp_customer = new WC_Customer();
                $temp_customer->set_shipping_state($wilaya_name);
                $temp_customer->set_shipping_country('DZ');
                WC()->customer = $temp_customer;
                WC()->cart->calculate_shipping();
                $packages = WC()->cart->get_shipping_packages();
                WC()->customer = new WC_Customer(); // Reset customer

                if (is_array($packages)) {
                    foreach ($packages as $package) {
                        if (isset($package['rates'][$shipping_method_id])) {
                            $rate_found = $package['rates'][$shipping_method_id];
                            break;
                        }
                    }
                }

                if ($rate_found instanceof WC_Shipping_Rate) {
                    $item = new WC_Order_Item_Shipping();
                    $item->set_method_title( $rate_found->get_label() );
                    $item->set_method_id( $rate_found->get_id() ); // Use the unique rate ID (e.g., flat_rate:1)
                    $item->set_total( $rate_found->get_cost() );
                    $item->set_taxes( $rate_found->get_taxes() );
                    $order->add_item( $item );
                } else {
                    // Log error if the selected rate couldn't be found/applied
                    wc_get_logger()->error( sprintf('WDOP Order Error: Could not find or apply selected shipping rate ID %s for order %d.', $shipping_method_id, $order->get_id()), array( 'source' => 'wdop-order' ) );
                    // Optionally add a notice to the order
                    $order->add_order_note( sprintf(__( 'Warning: Could not apply selected shipping method (%s). Please verify shipping manually.', 'wp-direct-order-plugin' ), $shipping_method_id) );
                }
            }

            // Calculate totals including shipping
            $order->calculate_totals();

            // Save custom field data as order meta
            if ( $get_setting('field_enable_full_name') && ! empty( $full_name ) ) {
                $order->update_meta_data( '_wdop_full_name', $full_name );
            }
            if ( $get_setting('field_enable_phone_number') && ! empty( $phone_number ) ) {
                $order->update_meta_data( '_wdop_phone_number', $phone_number );
            }
            if ( $get_setting('field_enable_wilaya') && ! empty( $wilaya_name ) ) {
                $order->update_meta_data( '_wdop_wilaya', $wilaya_name );
                $order->update_meta_data( '_wdop_wilaya_code', $wilaya_code );
            }
            if ( $get_setting('field_enable_commune') && ! empty( $commune_name ) ) {
                $order->update_meta_data( '_wdop_commune', $commune_name );
                $order->update_meta_data( '_wdop_commune_key', $commune_key );
            }
            // Save Optional Custom Fields
            if ( $get_setting('field_enable_custom1') && ! empty( $custom1_value ) ) {
                $order->update_meta_data( '_wdop_custom1_label', $get_setting('field_label_custom1') );
                $order->update_meta_data( '_wdop_custom1_value', $custom1_value );
            }
            if ( $get_setting('field_enable_custom2') && ! empty( $custom2_value ) ) {
                $order->update_meta_data( '_wdop_custom2_label', $get_setting('field_label_custom2') );
                $order->update_meta_data( '_wdop_custom2_value', $custom2_value );
            }

            // Allow other plugins to modify the order before saving
            do_action( 'wdop_before_order_save', $order, $_POST );

            // Set order status (e.g., 'on-hold' or 'processing' for COD)
            $order->update_status( apply_filters( 'wdop_default_order_status', 'on-hold' ), __( 'Order placed via direct form.', 'wp-direct-order-plugin' ) );

            // Save the order
            $order_id = $order->save();

            if ( ! $order_id ) {
                 throw new Exception( __( 'Failed to save the order.', 'wp-direct-order-plugin' ) );
            }

            // Allow other plugins to perform actions after order save
            do_action( 'wdop_after_order_save', $order, $_POST );

            // Redirect to the thank you page
            $redirect_url = $order->get_checkout_order_received_url();
            wp_safe_redirect( $redirect_url );
            exit;

        } catch ( Exception $e ) {
            // Log detailed error
            wc_get_logger()->error( 'WDOP Order Creation Error: ' . $e->getMessage(), array( 'source' => 'wdop-order' ) );
            // Add user notice
            wc_add_notice( __( 'There was a problem placing your order. Please try again or contact support.', 'wp-direct-order-plugin' ), 'error' );
            // Redirect back to product page
            wp_safe_redirect( get_permalink( $product_id ) );
            exit;
        }
    }

    /**
     * Display custom fields in the admin order details.
     */
    public function display_custom_fields_in_admin_order( $order ) {
        // Ensure $order is a WC_Order object
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        echo '<div class="wdop-admin-order-fields order_data_column">
'; // Use WC admin styles
        echo '<h4>' . esc_html__( 'Direct Order Details', 'wp-direct-order-plugin' ) . '</h4>
';

        $fields_to_display = array(
            '_wdop_full_name' => __( 'Full Name:', 'wp-direct-order-plugin' ),
            '_wdop_phone_number' => __( 'Phone Number:', 'wp-direct-order-plugin' ),
            '_wdop_wilaya' => __( 'Wilaya:', 'wp-direct-order-plugin' ),
            '_wdop_commune' => __( 'Commune:', 'wp-direct-order-plugin' ),
        );

        foreach ($fields_to_display as $meta_key => $label) {
            $value = $order->get_meta( $meta_key );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $label ) . '</strong> ' . esc_html( $value ) . '</p>
';
            }
        }

        // Display Optional Fields
        for ($i = 1; $i <= 2; $i++) {
            $label_key = '_wdop_custom' . $i . '_label';
            $value_key = '_wdop_custom' . $i . '_value';
            $label = $order->get_meta( $label_key );
            $value = $order->get_meta( $value_key );
            if ( $label && $value ) {
                 echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>
';
            }
        }

        echo '</div>
';
    }

    /**
     * Display custom fields on the customer order details page (Thank You / View Order).
     */
    public function display_custom_fields_in_customer_order( $order ) {
         // Ensure $order is a WC_Order object
        if ( ! $order instanceof WC_Order ) {
            // Maybe get order from global $wp query vars if needed?
            return;
        }

        $has_custom_fields = false;
        ob_start();

        $fields_to_display = array(
            '_wdop_full_name' => __( 'Full Name:', 'wp-direct-order-plugin' ),
            '_wdop_phone_number' => __( 'Phone Number:', 'wp-direct-order-plugin' ),
            '_wdop_wilaya' => __( 'Wilaya:', 'wp-direct-order-plugin' ),
            '_wdop_commune' => __( 'Commune:', 'wp-direct-order-plugin' ),
        );

        foreach ($fields_to_display as $meta_key => $label) {
            $value = $order->get_meta( $meta_key );
            if ( $value ) {
                echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>
';
                $has_custom_fields = true;
            }
        }

        // Display Optional Fields
        for ($i = 1; $i <= 2; $i++) {
            $label_key = '_wdop_custom' . $i . '_label';
            $value_key = '_wdop_custom' . $i . '_value';
            $label = $order->get_meta( $label_key );
            $value = $order->get_meta( $value_key );
            if ( $label && $value ) {
                 echo '<tr><th>' . esc_html( $label ) . ':</th><td>' . esc_html( $value ) . '</td></tr>
';
                 $has_custom_fields = true;
            }
        }

        $output = ob_get_clean();

        if ( $has_custom_fields ) {
            echo '<h2>' . esc_html__( 'Delivery Details', 'wp-direct-order-plugin' ) . '</h2>
';
            echo '<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details">' . $output . '</table>
';
        }
    }

    /**
     * Display custom fields in WooCommerce emails.
     */
    public function display_custom_fields_in_emails( $order, $sent_to_admin, $plain_text, $email ) {
         // Ensure $order is a WC_Order object
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $output = '';
        $has_custom_fields = false;

        $fields_to_display = array(
            '_wdop_full_name' => __( 'Full Name: %s', 'wp-direct-order-plugin' ),
            '_wdop_phone_number' => __( 'Phone Number: %s', 'wp-direct-order-plugin' ),
            '_wdop_wilaya' => __( 'Wilaya: %s', 'wp-direct-order-plugin' ),
            '_wdop_commune' => __( 'Commune: %s', 'wp-direct-order-plugin' ),
        );

        foreach ($fields_to_display as $meta_key => $label_format) {
            $value = $order->get_meta( $meta_key );
            if ( $value ) {
                $output .= sprintf( $label_format, esc_html( $value ) ) . "\n";
                $has_custom_fields = true;
            }
        }

        // Display Optional Fields
        for ($i = 1; $i <= 2; $i++) {
            $label_key = '_wdop_custom' . $i . '_label';
            $value_key = '_wdop_custom' . $i . '_value';
            $label = $order->get_meta( $label_key );
            $value = $order->get_meta( $value_key );
            if ( $label && $value ) {
                 $output .= sprintf( '%s: %s', esc_html( $label ), esc_html( $value ) ) . "\n";
                 $has_custom_fields = true;
            }
        }

        if ( ! $has_custom_fields ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n----------\n" . esc_html__( 'Delivery Details', 'wp-direct-order-plugin' ) . "\n----------\n" . $output;
        } else {
            echo '<h2>' . esc_html__( 'Delivery Details', 'wp-direct-order-plugin' ) . '</h2>
';
            echo '<p style="margin:0 0 16px;">' . nl2br( $output ) . '</p>
';
        }
    }
}

// Instantiate the class
new WDOP_Order();

