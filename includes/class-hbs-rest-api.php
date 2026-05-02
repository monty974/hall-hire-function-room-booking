<?php
/**
 * Hall Booking System - REST API
 */

class HBS_REST_API {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // AJAX endpoints are registered in HBS_Bookings class
        // This class can be extended for WordPress REST API v2 integration if needed
    }
}
