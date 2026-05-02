<?php
/**
 * Hall Booking System - Calendar
 */

class HBS_Calendar {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get availability data for a month
     */
    public static function get_month_data($area_id, $month) {
        global $wpdb;
        
        $month_date = new DateTime($month . '-01');
        $start_date = $month_date->format('Y-m-d');
        
        $month_date->modify('last day of this month');
        $end_date = $month_date->format('Y-m-d');
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_bookings
             WHERE area_id = %d
             AND ((start_date BETWEEN %s AND %s) OR (end_date BETWEEN %s AND %s))",
            $area_id, $start_date, $end_date, $start_date, $end_date
        ));
        
        return $bookings;
    }
}
