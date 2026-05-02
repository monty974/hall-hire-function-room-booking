<?php
/**
 * Hall Booking System - Database Setup and Management
 */

class HBS_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Only check version on plugins_loaded after WordPress is fully loaded
        add_action('plugins_loaded', [$this, 'check_version'], 15);
    }
    
    public function check_version() {
        // Safety check - make sure globals are available
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }
        
        $installed_version = get_option('hbs_db_version');
        $current_version = '1.0.0';
        
        if ($installed_version !== $current_version) {
            $this->create_tables();
            update_option('hbs_db_version', $current_version);
        }
    }
    
    /**
     * Create all necessary database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for halls
        $sql_halls = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_halls (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY name (name)
        ) $charset_collate;";
        
        // Table for hall areas/sections
        $sql_areas = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_areas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hall_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT,
            capacity INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY hall_id (hall_id),
            KEY name (name),
            FOREIGN KEY (hall_id) REFERENCES {$wpdb->prefix}hbs_halls(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table for bookings
        $sql_bookings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            area_id BIGINT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            start_time TIME NOT NULL DEFAULT '00:00:00',
            end_date DATE NOT NULL,
            end_time TIME NOT NULL DEFAULT '23:59:59',
            duration_hours DECIMAL(5,2) DEFAULT 0,
            renter_name VARCHAR(255),
            renter_email VARCHAR(255),
            renter_phone VARCHAR(20),
            event_type VARCHAR(100),
            event_details LONGTEXT,
            status ENUM('pending', 'approved', 'rejected', 'blocked') DEFAULT 'pending',
            is_recurring TINYINT(1) DEFAULT 0,
            is_internal_block TINYINT(1) DEFAULT 0,
            created_by BIGINT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY area_id (area_id),
            KEY start_date (start_date),
            KEY end_date (end_date),
            KEY status (status),
            KEY is_recurring (is_recurring),
            KEY is_internal_block (is_internal_block),
            KEY date_range (start_date, end_date),
            FOREIGN KEY (area_id) REFERENCES {$wpdb->prefix}hbs_areas(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table for recurrence rules (iCal RRULE)
        $sql_recurrence = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_recurrence (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            rrule VARCHAR(1000) NOT NULL,
            recurrence_type ENUM('weekly', 'fortnightly', 'monthly') NOT NULL,
            recurrence_until DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY booking_id (booking_id),
            FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}hbs_bookings(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table for generated recurrence instances
        $sql_instances = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_recurrence_instances (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            instance_date DATE NOT NULL,
            instance_start_time TIME NOT NULL DEFAULT '00:00:00',
            instance_end_time TIME NOT NULL DEFAULT '23:59:59',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY booking_id (booking_id),
            KEY instance_date (instance_date),
            FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}hbs_bookings(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table for booking hours by day of week
        $sql_hours = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}hbs_booking_hours (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            day_of_week INT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
            is_open BOOLEAN DEFAULT 1,
            start_time TIME DEFAULT '09:00:00',
            end_time TIME DEFAULT '17:00:00',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY day_unique (day_of_week)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_halls);
        dbDelta($sql_areas);
        dbDelta($sql_bookings);
        dbDelta($sql_recurrence);
        dbDelta($sql_instances);
        dbDelta($sql_hours);
        
        // Create default hall and areas if they don't exist
        $this->create_default_hall();
    }
    
    /**
     * Create default hall with two areas
     */
    private function create_default_hall() {
        global $wpdb;
        
        // Safety checks
        if (!$wpdb || !function_exists('wpdb')) {
            return;
        }
        
        try {
            // Check if table exists
            $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}hbs_halls'");
            if (!$table) {
                return; // Table doesn't exist yet
            }
            
            // Check if default hall exists
            $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hbs_halls");
            
            if ($existing == 0) {
                // Create hall
                $wpdb->insert(
                    $wpdb->prefix . 'hbs_halls',
                    [
                        'name' => 'Main Hall',
                        'description' => 'Main venue hall'
                    ]
                );
                
                $hall_id = $wpdb->insert_id;
                
                // Create two areas
                $wpdb->insert(
                    $wpdb->prefix . 'hbs_areas',
                    [
                        'hall_id' => $hall_id,
                        'name' => 'Area 1',
                        'description' => 'First bookable area'
                    ]
                );
                
                $wpdb->insert(
                    $wpdb->prefix . 'hbs_areas',
                    [
                        'hall_id' => $hall_id,
                        'name' => 'Area 2',
                        'description' => 'Second bookable area'
                    ]
                );
            }
        } catch ( Exception $e ) {
            error_log( 'HBS Default Hall Creation Error: ' . $e->getMessage() );
        }
    }
    
    /**
     * Get all halls
     */
    public static function get_halls() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hbs_halls ORDER BY name");
    }
    
    /**
     * Get all areas for a hall
     */
    public static function get_areas($hall_id = null) {
        global $wpdb;
        
        if ($hall_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}hbs_areas WHERE hall_id = %d ORDER BY name",
                $hall_id
            ));
        }
        
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hbs_areas ORDER BY name");
    }
    
    /**
     * Get a specific area
     */
    public static function get_area($area_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_areas WHERE id = %d",
            $area_id
        ));
    }
    
    /**
     * Get booking hours for all days of week
     */
    public static function get_booking_hours() {
        global $wpdb;
        $hours = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}hbs_booking_hours ORDER BY day_of_week");
        
        // Ensure all 7 days exist
        $days_map = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday'
        ];
        
        $result = [];
        $existing_days = array_column($hours, 'day_of_week');
        
        foreach ($days_map as $day_num => $day_name) {
            if (in_array($day_num, $existing_days)) {
                $hour = array_values(array_filter($hours, function($h) use ($day_num) {
                    return $h->day_of_week == $day_num;
                }))[0];
                $result[$day_num] = $hour;
            } else {
                // Create default entry
                $result[$day_num] = (object)[
                    'day_of_week' => $day_num,
                    'is_open' => 1,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'day_name' => $day_name
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get booking hours for a specific day (0-6)
     */
    public static function get_booking_hours_for_day($day_of_week) {
        $hours = self::get_booking_hours();
        return isset($hours[$day_of_week]) ? $hours[$day_of_week] : null;
    }
    
    /**
     * Save booking hours
     */
    public static function save_booking_hours($day_of_week, $is_open, $start_time, $end_time) {
        global $wpdb;
        
        return $wpdb->replace(
            $wpdb->prefix . 'hbs_booking_hours',
            [
                'day_of_week' => $day_of_week,
                'is_open' => $is_open ? 1 : 0,
                'start_time' => $start_time,
                'end_time' => $end_time
            ],
            ['%d', '%d', '%s', '%s']
        );
    }
    
    /**
     * Get default area ID for calendar/form
     */
    public static function get_default_area() {
        $areas = self::get_areas();
        $default_id = intval(get_option('hbs_default_area_id', 0));
        
        // If no default set or doesn't exist, use first area
        if (!$default_id || !array_filter($areas, function($a) use ($default_id) { return $a->id == $default_id; })) {
            return !empty($areas) ? $areas[0]->id : 1;
        }
        
        return $default_id;
    }
    
    /**
     * Set default area
     */
    public static function set_default_area($area_id) {
        return update_option('hbs_default_area_id', intval($area_id));
    }
}
