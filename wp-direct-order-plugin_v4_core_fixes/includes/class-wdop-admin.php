<?php
/**
 * Handles the admin settings page for the WP Direct Order Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WDOP_Admin {

    /**
     * Option group and key for settings.
     */
    private static $option_group = 'wdop_settings_group';
    private static $option_name = 'wdop_settings';

    /**
     * Default settings values.
     * Made static to be accessible by the static get_setting method.
     */
    private static $defaults = array();

    /**
     * Constructor.
     */
    public function __construct() {
        self::set_defaults(); // Use self:: for static properties/methods
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // Output custom CSS in head for logged-in users potentially, or use wp_add_inline_style
        add_action( 'wp_head', array( $this, 'output_custom_css' ) );
    }

    /**
     * Set default values for settings.
     */
    private static function set_defaults() {
        // Ensure defaults are set only once
        if ( empty( self::$defaults ) ) {
            self::$defaults = array(
                'enable_plugin' => 1,
                'field_enable_full_name' => 1,
                'field_required_full_name' => 1,
                'field_enable_phone_number' => 1,
                'field_required_phone_number' => 1,
                'field_enable_wilaya' => 1,
                'field_required_wilaya' => 1,
                'field_enable_commune' => 1,
                'field_required_commune' => 1,
                'custom_css' => '',
                'field_enable_custom1' => 0,
                'field_required_custom1' => 0,
                'field_label_custom1' => __( 'Optional Field 1', 'wp-direct-order-plugin' ),
                'field_enable_custom2' => 0,
                'field_required_custom2' => 0,
                'field_label_custom2' => __( 'Optional Field 2', 'wp-direct-order-plugin' ),
                'form_placement' => 'woocommerce_single_product_summary',
                'order_button_text' => __( 'اطلب الآن', 'wp-direct-order-plugin' ),
            );
        }
    }

    /**
     * Get current settings, merged with defaults.
     * This is primarily for use within the admin class instance.
     */
    private function get_options() {
        // Ensure defaults are set before getting options
        self::set_defaults();
        $saved_options = get_option( self::$option_name, array() );
        // Ensure saved options are treated as an array
        if (!is_array($saved_options)) {
            $saved_options = array();
        }
        return wp_parse_args( $saved_options, self::$defaults );
    }

    /**
     * Add the admin menu item.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Direct Order Settings', 'wp-direct-order-plugin' ),
            __( 'Direct Order', 'wp-direct-order-plugin' ),
            'manage_woocommerce',
            'wdop-settings',
            array( $this, 'create_settings_page' )
        );
    }

    /**
     * Register plugin settings using the Settings API.
     */
    public function register_settings() {
        register_setting( self::$option_group, self::$option_name, array( $this, 'sanitize_settings' ) );

        // --- General Section ---
        add_settings_section(
            'wdop_general_section',
            __( 'General Settings', 'wp-direct-order-plugin' ),
            null,
            self::$option_group
        );
        add_settings_field(
            'wdop_enable_plugin',
            __( 'Enable Direct Order', 'wp-direct-order-plugin' ),
            array( $this, 'render_enable_plugin_field' ),
            self::$option_group,
            'wdop_general_section'
        );

        // --- Core Field Management Section ---
        add_settings_section(
            'wdop_core_fields_section',
            __( 'Core Field Management', 'wp-direct-order-plugin' ),
            array( $this, 'render_core_fields_section_description' ),
            self::$option_group
        );
        $core_fields = array(
            'full_name' => __( 'Full Name', 'wp-direct-order-plugin' ),
            'phone_number' => __( 'Phone Number', 'wp-direct-order-plugin' ),
            'wilaya' => __( 'Wilaya (State)', 'wp-direct-order-plugin' ),
            'commune' => __( 'Commune', 'wp-direct-order-plugin' ),
        );
        foreach ( $core_fields as $key => $label ) {
            add_settings_field(
                'wdop_field_enable_' . $key,
                sprintf( __( 'Enable %s Field', 'wp-direct-order-plugin' ), $label ),
                array( $this, 'render_field_enable_checkbox' ),
                self::$option_group,
                'wdop_core_fields_section',
                array( 'key' => $key, 'label' => $label, 'field_prefix' => 'field' )
            );
            add_settings_field(
                'wdop_field_required_' . $key,
                sprintf( __( '%s Field Required', 'wp-direct-order-plugin' ), $label ),
                array( $this, 'render_field_required_checkbox' ),
                self::$option_group,
                'wdop_core_fields_section',
                array( 'key' => $key, 'label' => $label, 'field_prefix' => 'field' )
            );
        }

        // --- Optional Custom Fields Section ---
        add_settings_section(
            'wdop_optional_fields_section',
            __( 'Optional Custom Fields', 'wp-direct-order-plugin' ),
            array( $this, 'render_optional_fields_section_description' ),
            self::$option_group
        );
        for ( $i = 1; $i <= 2; $i++ ) {
            $key = 'custom' . $i;
            $label_field_key = 'field_label_' . $key;
            $enable_field_key = 'field_enable_' . $key;
            $required_field_key = 'field_required_' . $key;

            add_settings_field(
                'wdop_' . $label_field_key,
                sprintf( __( 'Optional Field %d Label', 'wp-direct-order-plugin' ), $i ),
                array( $this, 'render_text_input_field' ),
                self::$option_group,
                'wdop_optional_fields_section',
                array( 'key' => $key, 'option_key' => $label_field_key )
            );
            add_settings_field(
                'wdop_' . $enable_field_key,
                sprintf( __( 'Enable Optional Field %d', 'wp-direct-order-plugin' ), $i ),
                array( $this, 'render_field_enable_checkbox' ),
                self::$option_group,
                'wdop_optional_fields_section',
                array( 'key' => $key, 'label' => sprintf( __( 'Optional Field %d', 'wp-direct-order-plugin' ), $i ), 'field_prefix' => 'field' )
            );
            add_settings_field(
                'wdop_' . $required_field_key,
                sprintf( __( 'Optional Field %d Required', 'wp-direct-order-plugin' ), $i ),
                array( $this, 'render_field_required_checkbox' ),
                self::$option_group,
                'wdop_optional_fields_section',
                array( 'key' => $key, 'label' => sprintf( __( 'Optional Field %d', 'wp-direct-order-plugin' ), $i ), 'field_prefix' => 'field' )
            );
        }

        // --- Appearance Section ---
        add_settings_section(
            'wdop_appearance_section',
            __( 'Appearance Settings', 'wp-direct-order-plugin' ),
            null,
            self::$option_group
        );
        add_settings_field(
            'wdop_form_placement',
            __( 'Form Placement', 'wp-direct-order-plugin' ),
            array( $this, 'render_form_placement_field' ),
            self::$option_group,
            'wdop_appearance_section'
        );
         add_settings_field(
            'wdop_order_button_text',
            __( 'Order Button Text', 'wp-direct-order-plugin' ),
            array( $this, 'render_text_input_field' ),
            self::$option_group,
            'wdop_appearance_section',
            array( 'option_key' => 'order_button_text', 'description' => __( 'Customize the text for the order submission button.', 'wp-direct-order-plugin' ) )
        );
        add_settings_field(
            'wdop_custom_css',
            __( 'Custom CSS', 'wp-direct-order-plugin' ),
            array( $this, 'render_custom_css_field' ),
            self::$option_group,
            'wdop_appearance_section'
        );

    }

    /**
     * Sanitize settings data before saving.
     */
    public function sanitize_settings( $input ) {
        // Ensure input is an array
        $input = is_array( $input ) ? $input : array();
        $sanitized_input = array();
        self::set_defaults(); // Ensure defaults are loaded

        // Sanitize General
        $sanitized_input['enable_plugin'] = isset( $input['enable_plugin'] ) ? 1 : 0;

        // Sanitize Core Fields
        $core_fields = array( 'full_name', 'phone_number', 'wilaya', 'commune' );
        foreach ( $core_fields as $key ) {
            $enable_key = 'field_enable_' . $key;
            $required_key = 'field_required_' . $key;
            $sanitized_input[$enable_key] = isset( $input[$enable_key] ) ? 1 : 0;
            // Only allow required if enabled
            $sanitized_input[$required_key] = ( $sanitized_input[$enable_key] && isset( $input[$required_key] ) ) ? 1 : 0;
        }

        // Sanitize Optional Custom Fields
        for ( $i = 1; $i <= 2; $i++ ) {
            $key = 'custom' . $i;
            $label_key = 'field_label_' . $key;
            $enable_key = 'field_enable_' . $key;
            $required_key = 'field_required_' . $key;

            $sanitized_input[$label_key] = isset( $input[$label_key] ) ? sanitize_text_field( $input[$label_key] ) : self::$defaults[$label_key];
            $sanitized_input[$enable_key] = isset( $input[$enable_key] ) ? 1 : 0;
            $sanitized_input[$required_key] = ( $sanitized_input[$enable_key] && isset( $input[$required_key] ) ) ? 1 : 0;

            // Ensure label is not empty if field is enabled
            if ( $sanitized_input[$enable_key] && empty( $sanitized_input[$label_key] ) ) {
                 $sanitized_input[$label_key] = sprintf( __( 'Optional Field %d', 'wp-direct-order-plugin' ), $i );
            }
        }

        // Sanitize Appearance
        $sanitized_input['custom_css'] = isset( $input['custom_css'] ) ? trim( $input['custom_css'] ) : '';

        $allowed_hooks = array_keys( $this->get_placement_hooks() );
        if ( isset( $input['form_placement'] ) && in_array( $input['form_placement'], $allowed_hooks ) ) {
            $sanitized_input['form_placement'] = $input['form_placement'];
        } else {
            $sanitized_input['form_placement'] = self::$defaults['form_placement'];
        }

        $sanitized_input['order_button_text'] = isset( $input['order_button_text'] ) ? sanitize_text_field( $input['order_button_text'] ) : self::$defaults['order_button_text'];
        if ( empty( $sanitized_input['order_button_text'] ) ) {
            $sanitized_input['order_button_text'] = self::$defaults['order_button_text']; // Fallback to default if empty
        }

        // Merge with defaults to ensure all keys exist, though sanitize should cover all
        // return wp_parse_args( $sanitized_input, self::$defaults );
        return $sanitized_input; // Return only sanitized values
    }

    /**
     * Get available placement hooks.
     */
    private function get_placement_hooks() {
        // Consider adding more hooks or making this filterable
        return array(
            'woocommerce_single_product_summary' => __( 'Inside Product Summary (Default)', 'wp-direct-order-plugin' ),
            'woocommerce_before_add_to_cart_form' => __( 'Before Add to Cart Form', 'wp-direct-order-plugin' ),
            'woocommerce_after_add_to_cart_form' => __( 'After Add to Cart Form', 'wp-direct-order-plugin' ),
            'woocommerce_after_single_product_summary' => __( 'After Product Summary', 'wp-direct-order-plugin' ),
        );
    }

    /**
     * Render descriptions for sections.
     */
    public function render_core_fields_section_description() {
        echo '<p>' . esc_html__( 'Configure the core fields required for the order.', 'wp-direct-order-plugin' ) . '</p>';
        echo '<p>' . esc_html__( 'Note: Wilaya and Commune fields work together. Disabling Wilaya will also hide Commune.', 'wp-direct-order-plugin' ) . '</p>';
    }
    public function render_optional_fields_section_description() {
        echo '<p>' . esc_html__( 'Add up to two optional custom text fields to collect additional information.', 'wp-direct-order-plugin' ) . '</p>';
    }

    /**
     * Render various field types.
     */
    public function render_enable_plugin_field() {
        $options = $this->get_options();
        $option_key = 'enable_plugin';
        $checked = isset($options[$option_key]) ? $options[$option_key] : 0;
        echo '<input type="checkbox" name="' . esc_attr( self::$option_name ) . '[' . $option_key . ']" value="1" ' . checked( 1, $checked, false ) . ' />';
        echo '<p class="description">' . esc_html__( 'Enable the direct order form on single product pages.', 'wp-direct-order-plugin' ) . '</p>';
    }

    public function render_field_enable_checkbox( $args ) {
        $options = $this->get_options();
        $key = $args['key'];
        $option_key = $args['field_prefix'] . '_enable_' . $key;
        $checked = isset($options[$option_key]) ? $options[$option_key] : 0;
        echo '<input type="checkbox" id="' . esc_attr($option_key) . '" name="' . esc_attr( self::$option_name ) . '[' . $option_key . ']" value="1" ' . checked( 1, $checked, false ) . ' />';
    }

    public function render_field_required_checkbox( $args ) {
        $options = $this->get_options();
        $key = $args['key'];
        $enable_key = $args['field_prefix'] . '_enable_' . $key;
        $required_key = $args['field_prefix'] . '_required_' . $key;
        $checked = isset($options[$required_key]) ? $options[$required_key] : 0;
        $is_enabled = isset($options[$enable_key]) ? $options[$enable_key] : 0;
        $disabled = !$is_enabled ? 'disabled' : '';

        echo '<input type="checkbox" id="' . esc_attr($required_key) . '" name="' . esc_attr( self::$option_name ) . '[' . $required_key . ']" value="1" ' . checked( 1, $checked, false ) . ' ' . $disabled . ' />';
        // Add JS to enable/disable this checkbox based on the enable checkbox
        echo '<script type="text/javascript">
                jQuery(document).ready(function($) {
                    var enableCheckbox = $("#' . esc_js($enable_key) . '");
                    var requiredCheckbox = $("#' . esc_js($required_key) . '");
                    if (!enableCheckbox.prop("checked")) {
                         requiredCheckbox.prop("disabled", true);
                    }
                    enableCheckbox.on("change", function() {
                        requiredCheckbox.prop("disabled", !this.checked);
                        if (!this.checked) {
                            requiredCheckbox.prop("checked", false);
                        }
                    });
                });
              </script>';
        if ($disabled) {
             echo '<p class="description">' . esc_html__( 'Enable the field first to make it required.', 'wp-direct-order-plugin' ) . '</p>';
        }
    }

     public function render_text_input_field( $args ) {
        $options = $this->get_options();
        $option_key = $args['option_key'];
        $value = isset($options[$option_key]) ? $options[$option_key] : '';
        echo '<input type="text" class="regular-text" name="' . esc_attr( self::$option_name ) . '[' . $option_key . ']" value="' . esc_attr( $value ) . '" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function render_custom_css_field() {
        $options = $this->get_options();
        $option_key = 'custom_css';
        $custom_css = isset($options[$option_key]) ? $options[$option_key] : '';
        echo '<textarea name="' . esc_attr( self::$option_name ) . '[' . $option_key . ']" rows="10" cols="50" class="large-text code">' . esc_textarea( $custom_css ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Enter custom CSS rules to style the direct order form. Example: .wdop-order-form { border: 1px solid #ccc; }', 'wp-direct-order-plugin' ) . '</p>';
    }

    public function render_form_placement_field() {
        $options = $this->get_options();
        $option_key = 'form_placement';
        $current_placement = isset($options[$option_key]) ? $options[$option_key] : self::$defaults[$option_key];
        $hooks = $this->get_placement_hooks();

        echo '<select name="' . esc_attr( self::$option_name ) . '[' . $option_key . ']">';
        foreach ( $hooks as $hook => $label ) {
            echo '<option value="' . esc_attr( $hook ) . '" ' . selected( $current_placement, $hook, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Select where the direct order form should appear on the single product page.', 'wp-direct-order-plugin' ) . '</p>';
    }

    /**
     * Output custom CSS in the site header.
     * Consider using wp_add_inline_style attached to the main stylesheet for better performance.
     */
    public function output_custom_css() {
        $options = self::get_setting(null); // Use static method to get all settings
        if ( $options['enable_plugin'] && ! empty( $options['custom_css'] ) ) {
            $custom_css = wp_strip_all_tags( $options['custom_css'] );
            // Using wp_add_inline_style is generally preferred
            // wp_register_style( 'wdop-inline-style', false );
            // wp_enqueue_style( 'wdop-inline-style' );
            // wp_add_inline_style( 'wdop-inline-style', $custom_css );
            // Or simply output in head for now:
            echo '<style type="text/css" id="wdop-custom-styles">' . $custom_css . '</style>';
        }
    }

    /**
     * Create the HTML for the settings page.
     */
    public function create_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::$option_group );
                do_settings_sections( self::$option_group );
                submit_button( __( 'Save Settings', 'wp-direct-order-plugin' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Static helper function to get all settings or a specific setting value, falling back to default.
     * Ensures defaults are loaded.
     * @param string|null $key The specific setting key to retrieve, or null to get all settings.
     * @param mixed $default_override Optional. A default value to use if the setting is not found.
     * @return mixed|array The setting value, or an array of all settings if $key is null.
     */
    public static function get_setting( $key = null, $default_override = null ) {
        self::set_defaults(); // Ensure defaults are loaded

        $options = get_option( self::$option_name, array() );
        // Ensure options are treated as an array
        if (!is_array($options)) {
            $options = array();
        }
        $options = wp_parse_args( $options, self::$defaults );

        // If a specific key is requested
        if ( $key !== null ) {
            // Determine the default value to use
            $default_value = isset(self::$defaults[$key]) ? self::$defaults[$key] : null;
            if ($default_override !== null) {
                $default_value = $default_override;
            }
            // Return the specific option value or the determined default
            return isset( $options[$key] ) ? $options[$key] : $default_value;
        } else {
            // If no key is specified, return all options merged with defaults
            return $options;
        }
    }
}

// Instantiate the class
new WDOP_Admin();

