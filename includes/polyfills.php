<?php
/**
 * Polyfills for PHP 8.0 string functions, so the plugin runs on PHP 7.4+.
 * Many church shared hosts still run 7.4; without these, str_contains()
 * and str_ends_with() calls would fatal at runtime.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'str_contains' ) ) {
    function str_contains( $haystack, $needle ) {
        return $needle === '' || strpos( $haystack, $needle ) !== false;
    }
}

if ( ! function_exists( 'str_starts_with' ) ) {
    function str_starts_with( $haystack, $needle ) {
        return strncmp( $haystack, $needle, strlen( $needle ) ) === 0;
    }
}

if ( ! function_exists( 'str_ends_with' ) ) {
    function str_ends_with( $haystack, $needle ) {
        if ( $needle === '' ) return true;
        $len = strlen( $needle );
        return substr( $haystack, -$len ) === $needle;
    }
}
