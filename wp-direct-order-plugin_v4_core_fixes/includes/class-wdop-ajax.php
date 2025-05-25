<?php
/**
 * Handles AJAX requests for the WP Direct Order Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WDOP_Ajax {

    /**
     * Constructor.
     */
    public function __construct() {
        // AJAX action for logged-in users
        add_action( 'wp_ajax_wdop_get_communes', array( $this, 'ajax_get_communes' ) );
        // AJAX action for non-logged-in users
        add_action( 'wp_ajax_nopriv_wdop_get_communes', array( $this, 'ajax_get_communes' ) );
    }

    /**
     * AJAX handler to get communes for a selected wilaya.
     */
    public function ajax_get_communes() {
        // Verify nonce for security
        check_ajax_referer( 'wdop_get_communes_nonce', 'nonce' );

        // Get the selected wilaya code from the AJAX request
        $wilaya_code = isset( $_POST['wilaya_code'] ) ? sanitize_text_field( $_POST['wilaya_code'] ) : '';

        if ( empty( $wilaya_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Wilaya code is missing.', 'wp-direct-order-plugin' ) ) );
            return;
        }

        // Get communes for the selected wilaya
        $communes = wdop_get_communes_by_wilaya( $wilaya_code );

        if ( empty( $communes ) ) {
            wp_send_json_success( array( 'communes' => array() ) ); // Send success with empty array if no communes found
        } else {
            wp_send_json_success( array( 'communes' => $communes ) );
        }
    }
}

// Instantiate the class
new WDOP_Ajax();

