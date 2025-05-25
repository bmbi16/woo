<?php
/**
 * Helper functions for the WP Direct Order Plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load and parse the Algeria cities data from JSON file.
 *
 * @return array Parsed data or empty array on failure.
 */
function wdop_load_algeria_cities_data() {
    $file_path = plugin_dir_path( __FILE__ ) . '../data/algeria_cities.json';
    static $cities_data = null;

    if ( $cities_data !== null ) {
        return $cities_data;
    }

    if ( ! file_exists( $file_path ) ) {
        error_log( 'WP Direct Order Plugin: Algeria cities JSON file not found at ' . $file_path );
        $cities_data = array();
        return $cities_data;
    }

    $json_content = file_get_contents( $file_path );
    if ( $json_content === false ) {
        error_log( 'WP Direct Order Plugin: Could not read Algeria cities JSON file.' );
        $cities_data = array();
        return $cities_data;
    }

    $data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'WP Direct Order Plugin: Error decoding Algeria cities JSON: ' . json_last_error_msg() );
        $cities_data = array();
        return $cities_data;
    }

    $cities_data = $data;
    return $cities_data;
}

/**
 * Get a list of unique Wilayas (States).
 *
 * @return array Associative array of [wilaya_code => wilaya_name (Arabic)].
 */
function wdop_get_wilayas() {
    $data = wdop_load_algeria_cities_data();
    $wilayas = array();

    if ( empty( $data ) ) {
        return $wilayas;
    }

    foreach ( $data as $item ) {
        if ( isset( $item['wilaya_code'] ) && isset( $item['wilaya_name'] ) && ! isset( $wilayas[ $item['wilaya_code'] ] ) ) {
            // Use wilaya_code as key and wilaya_name (Arabic) as value
            $wilayas[ $item['wilaya_code'] ] = trim( $item['wilaya_name'] );
        }
    }
    // Sort by wilaya code (numeric key)
    ksort( $wilayas, SORT_NUMERIC );
    return $wilayas;
}

/**
 * Get Communes for a specific Wilaya.
 *
 * @param string $wilaya_code The code of the Wilaya.
 * @return array Associative array of [commune_name_ascii => commune_name (Arabic)].
 */
function wdop_get_communes_by_wilaya( $wilaya_code ) {
    $data = wdop_load_algeria_cities_data();
    $communes = array();

    if ( empty( $data ) || empty( $wilaya_code ) ) {
        return $communes;
    }

    foreach ( $data as $item ) {
        if ( isset( $item['wilaya_code'] ) && $item['wilaya_code'] === $wilaya_code && isset( $item['commune_name'] ) && isset($item['commune_name_ascii']) ) {
            // Use commune_name_ascii as key for uniqueness/consistency, commune_name (Arabic) as value
            $communes[ $item['commune_name_ascii'] ] = trim( $item['commune_name'] );
        }
    }
    // Sort alphabetically by Arabic name
    asort( $communes );
    return $communes;
}


