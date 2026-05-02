<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Hall Booking System - Booking Management
 */

class HBS_Bookings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('wp_ajax_nopriv_hbs_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_hbs_get_availability', [$this, 'ajax_get_availability']);
        add_action('wp_ajax_nopriv_hbs_submit_booking_request', [$this, 'ajax_submit_booking_request']);
        add_action('wp_ajax_hbs_submit_booking_request', [$this, 'ajax_submit_booking_request']);
        add_action('wp_ajax_hbs_approve_booking', [$this, 'ajax_approve_booking']);
        add_action('wp_ajax_hbs_reject_booking', [$this, 'ajax_reject_booking']);
        add_action('wp_ajax_hbs_delete_booking', [$this, 'ajax_delete_booking']);
        add_action('wp_ajax_hbs_create_internal_block', [$this, 'ajax_create_internal_block']);
        add_action('wp_ajax_hbs_get_bookings_list', [$this, 'ajax_get_bookings_list']);
        add_action('wp_ajax_hbs_create_area', [$this, 'ajax_create_area']);
        add_action('wp_ajax_hbs_update_area', [$this, 'ajax_update_area']);
        add_action('wp_ajax_hbs_delete_area', [$this, 'ajax_delete_area']);
        add_action('wp_ajax_hbs_debug_calendar', [$this, 'ajax_debug_calendar']);
        add_action('wp_ajax_hbs_update_booking', [$this, 'ajax_update_booking']);
        add_action('wp_ajax_hbs_save_default_area', [$this, 'ajax_save_default_area']);
        add_action('wp_ajax_hbs_save_booking_hours', [$this, 'ajax_save_booking_hours']);
    }
    
    /**
     * AJAX: Get availability for a date range and area
     */
    public function ajax_get_availability() {
        check_ajax_referer('hbs_nonce', 'nonce');

        $area_id = intval( $_POST['area_id'] ?? 0 );
        $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? gmdate('Y-m') ) );

        if (!$area_id) {
            wp_send_json_error('Missing area ID');
        }

        $availability = $this->get_month_availability($area_id, $month);
        wp_send_json_success($availability);
    }
    
    /**
     * AJAX: Submit booking request
     */
    public function ajax_submit_booking_request() {
        // Check for either frontend or admin nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'hbs_nonce') && !wp_verify_nonce($nonce, 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $area_id = intval( $_POST['area_id'] ?? 0 );
        $booking_date = sanitize_text_field( wp_unslash( $_POST['booking_date'] ?? '' ) );
        $start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '09:00' ) );
        $duration_hours = floatval($_POST['duration_hours'] ?? 1);
        $renter_name = sanitize_text_field( wp_unslash( $_POST['renter_name'] ?? '' ) );
        $renter_email = sanitize_email( wp_unslash( $_POST['renter_email'] ?? '' ) );
        $renter_phone = sanitize_text_field( wp_unslash( $_POST['renter_phone'] ?? '' ) );
        $event_type = sanitize_text_field( wp_unslash( $_POST['event_type'] ?? '' ) );
        $event_details = sanitize_textarea_field($_POST['event_details'] ?? '');
        $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
        $recurrence_type = sanitize_text_field( wp_unslash( $_POST['recurrence_type'] ?? '' ) );
        $recurrence_until = sanitize_text_field( wp_unslash( $_POST['recurrence_until'] ?? '' ) );

        // Validation
        if (!$area_id || !$booking_date || !$renter_name || !$renter_email) {
            wp_send_json_error('Please fill in all required fields');
        }

        if ($duration_hours <= 0) {
            wp_send_json_error('Duration must be greater than 0 hours');
        }

        // Calculate end time
        $start_datetime = new DateTime($booking_date . ' ' . $start_time);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . intval($duration_hours * 60) . 'M'));

        // Check for conflicts
        if ($this->has_conflicts($area_id, $booking_date, $end_datetime->format('Y-m-d'), $start_time, $end_datetime->format('H:i'))) {
            wp_send_json_error('Selected time has conflicts with existing bookings or blocks');
        }

        // Create booking
        $booking_data = [
            'area_id' => $area_id,
            'start_date' => $booking_date,
            'start_time' => $start_time,
            'end_date' => $end_datetime->format('Y-m-d'),
            'end_time' => $end_datetime->format('H:i:00'),
            'duration_hours' => $duration_hours,
            'renter_name' => $renter_name,
            'renter_email' => $renter_email,
            'renter_phone' => $renter_phone,
            'event_type' => $event_type,
            'event_details' => $event_details,
            'is_recurring' => $is_recurring,
            'recurrence_type' => $recurrence_type,
            'recurrence_until' => $recurrence_until,
        ];

        $booking_id = $this->create_booking($booking_data);

        if ($booking_id) {
            HBS_Email::get_instance()->send_pending_notification($booking_id, $booking_data);
            wp_send_json_success(['booking_id' => $booking_id, 'message' => 'Booking request submitted successfully']);
        } else {
            wp_send_json_error('Failed to create booking');
        }
    }
    
    /**
     * AJAX: Approve booking
     */
    public function ajax_approve_booking() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('hbs_admin_nonce', 'nonce');

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        if (!$booking_id) {
            wp_send_json_error('Invalid booking');
        }

        global $wpdb;
        $updated = $wpdb->upgmdate(
            $wpdb->prefix . 'hbs_bookings',
            ['status' => 'approved'],
            ['id' => $booking_id]
        );

        if ($updated) {
            HBS_Email::get_instance()->send_approval_email($booking_id);
            wp_send_json_success('Booking approved');
        } else {
            wp_send_json_error('Failed to update booking');
        }
    }
    
    /**
     * AJAX: Reject booking
     */
    public function ajax_reject_booking() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('hbs_admin_nonce', 'nonce');

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        if (!$booking_id) {
            wp_send_json_error('Invalid booking');
        }

        global $wpdb;
        $updated = $wpdb->upgmdate(
            $wpdb->prefix . 'hbs_bookings',
            ['status' => 'rejected'],
            ['id' => $booking_id]
        );

        if ($updated) {
            HBS_Email::get_instance()->send_rejection_email($booking_id);
            wp_send_json_success('Booking rejected');
        } else {
            wp_send_json_error('Failed to update booking');
        }
    }
    
    /**
     * AJAX: Delete booking
     */
    public function ajax_delete_booking() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('hbs_admin_nonce', 'nonce');

        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        if (!$booking_id) {
            wp_send_json_error('Invalid booking');
        }

        global $wpdb;

        // Delete recurrence rules
        $wpdb->delete($wpdb->prefix . 'hbs_recurrence', ['booking_id' => $booking_id]);
        
        // Delete instances
        $wpdb->delete($wpdb->prefix . 'hbs_recurrence_instances', ['booking_id' => $booking_id]);
        
        // Delete booking
        $deleted = $wpdb->delete($wpdb->prefix . 'hbs_bookings', ['id' => $booking_id]);

        if ($deleted) {
            wp_send_json_success('Booking deleted');
        } else {
            wp_send_json_error('Failed to delete booking');
        }
    }
    
    /**
     * AJAX: Create internal block
     */
    public function ajax_create_internal_block() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('hbs_admin_nonce', 'nonce');

        $area_id = intval( $_POST['area_id'] ?? 0 );
        $block_date = sanitize_text_field( wp_unslash( $_POST['block_date'] ?? '' ) );
        $start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '00:00' ) );
        $end_time = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '23:59' ) );
        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? 'Internal use' ) );

        if (!$area_id || !$block_date) {
            wp_send_json_error('Missing parameters');
        }

        // Calculate duration in hours
        $start = DateTime::createFromFormat('H:i', $start_time);
        $end = DateTime::createFromFormat('H:i', $end_time);
        $interval = $start->diff($end);
        $duration_hours = ($interval->h + ($interval->i / 60));

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'hbs_bookings',
            [
                'area_id' => $area_id,
                'start_date' => $block_date,
                'start_time' => $start_time,
                'end_date' => $block_date,
                'end_time' => $end_time,
                'duration_hours' => $duration_hours,
                'is_internal_block' => 1,
                'status' => 'blocked',  // Use 'blocked' status for internal blocks
                'renter_name' => $reason,
                'renter_email' => NULL,  // NULL to identify as internal block
                'created_by' => get_current_user_id(),
            ]
        );

        if ($inserted) {
            wp_send_json_success('Internal block created');
        } else {
            wp_send_json_error('Failed to create block');
        }
    }
    
    /**
     * AJAX: Get bookings list for admin
     */
    public function ajax_get_bookings_list() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('hbs_admin_nonce', 'nonce');

        $area_id = intval( $_POST['area_id'] ?? 0 );
        $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
        $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? gmdate('Y-m') ) );

        // Get regular bookings with status filter
        $bookings = $this->get_bookings($area_id, $status, $month);
        
        // ALWAYS get internal blocks (they're separate from regular bookings)
        $internal_blocks = $this->get_internal_blocks($area_id, $month);
        
        // Combine both lists
        $all_items = array_merge($bookings, $internal_blocks);
        
        // Re-sort by date descending
        usort($all_items, function($a, $b) {
            $date_cmp = strcmp($b->start_date, $a->start_date);
            if ($date_cmp !== 0) return $date_cmp;
            return strcmp($b->start_time, $a->start_time);
        });
        
        wp_send_json_success($all_items);
    }
    
    /**
     * Get month availability for calendar
     */
    private function get_month_availability($area_id, $month) {
        global $wpdb;
        
        $month_date = new DateTime($month . '-01');
        $start_date = $month_date->format('Y-m-d');
        
        $month_date->modify('last day of this month');
        $end_date = $month_date->format('Y-m-d');

        // Get main bookings for this month - show single bookings that fall in this month
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.start_date, b.start_time, b.end_date, b.end_time, b.status, b.is_internal_block, b.renter_name, b.is_recurring
             FROM {$wpdb->prefix}hbs_bookings b
             WHERE b.area_id = %d
             AND (b.status = 'approved' OR b.status = 'pending' OR b.status = 'blocked')
             AND b.is_recurring = 0
             AND b.start_date BETWEEN %s AND %s
             ORDER BY b.start_date, b.start_time",
            $area_id, $start_date, $end_date
        ));

        // Build availability array by date
        $availability = [];
        $current = new DateTime($start_date);
        
        while ($current->format('Y-m-d') <= $end_date) {
            $date_key = $current->format('Y-m-d');
            $availability[$date_key] = [
                'date' => $date_key,
                'day_of_week' => $current->format('l'),
                'bookings' => [],
                'is_available' => true,
            ];
            $current->modify('+1 day');
        }

        // Add main bookings to availability (but exclude recurring ones as they're handled separately)
        foreach ($bookings as $booking) {
            // Skip recurring bookings - they're in instances
            if ($booking->is_recurring) {
                continue;
            }
            
            $current = new DateTime($booking->start_date);
            $end = new DateTime($booking->end_date);

            while ($current->format('Y-m-d') <= $end->format('Y-m-d')) {
                $date_key = $current->format('Y-m-d');
                if (isset($availability[$date_key])) {
                    $availability[$date_key]['bookings'][] = [
                        'id' => $booking->id,
                        'start_time' => $booking->start_time,
                        'end_time' => $booking->end_time,
                        'status' => $booking->status,
                        'name' => $booking->is_internal_block ? '(Internal)' : $booking->renter_name,
                        'is_internal' => intval($booking->is_internal_block),
                    ];
                }
                $current->modify('+1 day');
            }
        }

        // Add recurrence instances to availability
        foreach ($instances as $instance) {
            $date_key = $instance->start_date;
            if (isset($availability[$date_key])) {
                $availability[$date_key]['bookings'][] = [
                    'id' => $instance->id,
                    'start_time' => $instance->start_time,
                    'end_time' => $instance->end_time,
                    'status' => $instance->status,
                    'name' => $instance->is_internal_block ? '(Internal)' : $instance->renter_name,
                    'is_internal' => intval($instance->is_internal_block),
                ];
            }
        }
        
        // Check if each day is fully booked
        foreach ($availability as $date => $day_data) {
            $day_of_week = (new DateTime($date))->format('w'); // 0=Sunday, 6=Saturday
            $hours = HBS_Database::get_booking_hours_for_day($day_of_week);
            
            if ($hours && $hours->is_open) {
                // Check if all hours are covered by bookings
                $is_fully_booked = $this->is_day_fully_booked($date, $hours->start_time, $hours->end_time, $day_data['bookings']);
                $availability[$date]['is_fully_booked'] = $is_fully_booked;
            } else {
                // Day is closed
                $availability[$date]['is_closed'] = true;
                $availability[$date]['is_available'] = false;
            }
        }

        return $availability;
    }
    
    /**
     * Check if a day is fully booked (all operating hours are covered by bookings)
     */
    private function is_day_fully_booked($date, $open_time, $close_time, $bookings) {
        if (empty($bookings)) {
            return false; // No bookings = not fully booked
        }
        
        // For simplicity: if there are ANY bookings that aren't internal blocks,
        // we consider it "limited availability" not "fully booked"
        // A day is only "fully booked" if the bookings cover the entire operating hours
        
        $open = new DateTime($date . ' ' . $open_time);
        $close = new DateTime($date . ' ' . $close_time);
        
        // Sort bookings by start time
        usort($bookings, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });
        
        $current_time = $open;
        
        foreach ($bookings as $booking) {
            $booking_start = new DateTime($date . ' ' . $booking['start_time']);
            $booking_end = new DateTime($date . ' ' . $booking['end_time']);
            
            // If there's a gap before this booking, the day isn't fully booked
            if ($booking_start > $current_time) {
                return false;
            }
            
            // Move current time to the end of this booking
            if ($booking_end > $current_time) {
                $current_time = $booking_end;
            }
        }
        
        // If current time hasn't reached close time, there are gaps
        return $current_time >= $close;
    }
    
    /**
     * Calculate detailed availability (check if specific time is available)
     */
    public function calculate_availability($area_id, $start_date, $end_date) {
        global $wpdb;
        
        $availability = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            
            $has_conflict = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hbs_bookings
                 WHERE area_id = %d
                 AND start_date <= %s AND end_date >= %s
                 AND status = 'approved'",
                $area_id, $date_str, $date_str
            )) > 0;
            
            $availability[$date_str] = [
                'available' => !$has_conflict,
                'status' => $has_conflict ? 'booked' : 'available',
            ];

            $current->modify('+1 day');
        }

        return $availability;
    }
    
    /**
     * Check for time conflicts
     */
    private function has_conflicts($area_id, $start_date, $end_date, $start_time, $end_time) {
        global $wpdb;

        $conflicts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hbs_bookings
             WHERE area_id = %d
             AND status IN ('approved', 'blocked')
             AND (
                (start_date < %s AND end_date > %s)
                OR (start_date = %s AND start_time < %s AND end_time > %s)
                OR (end_date = %s AND end_time > %s AND start_time < %s)
                OR (start_date > %s AND start_date < %s)
             )",
            $area_id, $end_date, $start_date,
            $start_date, $end_time, $start_time,
            $end_date, $end_time, $start_time,
            $start_date, $end_date
        ));

        return $conflicts > 0;
    }
    
    /**
     * Create a new booking
     */
    private function create_booking($data) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'hbs_bookings',
            [
                'area_id' => $data['area_id'],
                'start_date' => $data['start_date'],
                'start_time' => $data['start_time'] . ':00',
                'end_date' => $data['end_date'],
                'end_time' => $data['end_time'],
                'duration_hours' => $data['duration_hours'],
                'renter_name' => $data['renter_name'],
                'renter_email' => $data['renter_email'],
                'renter_phone' => $data['renter_phone'],
                'event_type' => $data['event_type'],
                'event_details' => $data['event_details'],
                'status' => 'pending',
                'is_recurring' => $data['is_recurring'],
                'is_internal_block' => 0,  // Explicitly set to 0 for regular bookings
                'created_by' => get_current_user_id(),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d']
        );

        if (!$inserted) {
            return false;
        }

        $booking_id = $wpdb->insert_id;

        // Handle recurring
        if ($data['is_recurring']) {
            $this->create_recurrence($booking_id, $data);
        }

        return $booking_id;
    }
    
    /**
     * Create recurrence rule and instances
     */
    private function create_recurrence($booking_id, $data) {
        global $wpdb;

        // Build RRULE
        $rrule = $this->build_rrule($data['recurrence_type']);

        // Insert recurrence rule
        $wpdb->insert(
            $wpdb->prefix . 'hbs_recurrence',
            [
                'booking_id' => $booking_id,
                'rrule' => $rrule,
                'recurrence_type' => $data['recurrence_type'],
                'recurrence_until' => $data['recurrence_until'],
            ]
        );

        // Generate and insert instances
        $start_date = new DateTime($data['start_date']);
        
        // If no end date provided, default to 1 year from start date
        $recurrence_until = $data['recurrence_until'];
        if (empty($recurrence_until)) {
            $end_date = clone $start_date;
            $end_date->modify('+1 year');
            $recurrence_until = $end_date->format('Y-m-d');
        } else {
            $end_date = new DateTime($recurrence_until);
        }
        
        $current = clone $start_date;

        while ($current <= $end_date) {
            $interval = $this->get_interval($data['recurrence_type']);
            
            if ($this->matches_recurrence($current, $data['recurrence_type'])) {
                $wpdb->insert(
                    $wpdb->prefix . 'hbs_recurrence_instances',
                    [
                        'booking_id' => $booking_id,
                        'instance_date' => $current->format('Y-m-d'),
                        'instance_start_time' => $data['start_time'] . ':00',
                        'instance_end_time' => $data['end_time'],
                    ]
                );
            }

            $current->modify($interval);
        }
    }
    
    /**
     * Build RRULE string
     */
    private function build_rrule($recurrence_type) {
        $rrules = [
            'weekly' => 'FREQ=WEEKLY',
            'fortnightly' => 'FREQ=WEEKLY;INTERVAL=2',
            'monthly' => 'FREQ=MONTHLY',
        ];
        return $rrules[$recurrence_type] ?? 'FREQ=WEEKLY';
    }
    
    /**
     * Get interval for advancing dates
     */
    private function get_interval($recurrence_type) {
        $intervals = [
            'weekly' => '+1 week',
            'fortnightly' => '+2 weeks',
            'monthly' => '+1 month',
        ];
        return $intervals[$recurrence_type] ?? '+1 week';
    }
    
    /**
     * Check if date matches recurrence pattern
     */
    private function matches_recurrence($datetime, $recurrence_type) {
        // For simple weekly/fortnightly/monthly, all dates in sequence match
        return true;
    }
    
    /**
     * Get bookings for admin display
     */
    private function get_bookings($area_id = 0, $status = '', $month = '') {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE is_internal_block = 0";
        $params = [];

        if ($area_id) {
            $query .= " AND area_id = %d";
            $params[] = $area_id;
        }

        if ($status) {
            $query .= " AND status = %s";
            $params[] = $status;
        } else {
            // Show only regular booking statuses (NOT internal blocks)
            $query .= " AND status IN ('pending', 'approved', 'rejected')";
        }

        if ($month) {
            $query .= " AND DATE_FORMAT(start_date, '%Y-%m') = %s";
            $params[] = $month;
        }

        $query .= " ORDER BY start_date DESC, start_time DESC";

        if ($params) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }

        return $wpdb->get_results($wpdb->prepare($query));
    }
    
    /**
     * Get internal blocks for admin display
     */
    private function get_internal_blocks($area_id = 0, $month = '') {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE is_internal_block = 1";
        $params = [];

        if ($area_id) {
            $query .= " AND area_id = %d";
            $params[] = $area_id;
        }

        if ($month) {
            $query .= " AND DATE_FORMAT(start_date, '%Y-%m') = %s";
            $params[] = $month;
        }

        $query .= " ORDER BY start_date DESC, start_time DESC";

        if ($params) {
            return $wpdb->get_results($wpdb->prepare($query, $params));
        }

        return $wpdb->get_results($wpdb->prepare($query));
    }
    
    /**
     * AJAX: Create new area
     */
    public function ajax_create_area() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $area_name = sanitize_text_field( wp_unslash( $_POST['area_name'] ?? '' ) );
        $area_description = sanitize_textarea_field($_POST['area_description'] ?? '');
        $area_capacity = intval( $_POST['area_capacity'] ?? 0 );
        
        if (!$area_name) {
            wp_send_json_error('Area name is required');
        }
        
        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'hbs_areas',
            [
                'hall_id' => 1,  // Default to main hall
                'name' => $area_name,
                'description' => $area_description,
                'capacity' => $area_capacity > 0 ? $area_capacity : NULL,
            ]
        );
        
        if ($inserted) {
            wp_send_json_success(['area_id' => $wpdb->insert_id]);
        } else {
            wp_send_json_error('Failed to create area');
        }
    }
    
    /**
     * AJAX: Update existing area
     */
    public function ajax_update_area() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $area_id = intval( $_POST['area_id'] ?? 0 );
        $area_name = sanitize_text_field( wp_unslash( $_POST['area_name'] ?? '' ) );
        $area_description = sanitize_textarea_field($_POST['area_description'] ?? '');
        $area_capacity = intval( $_POST['area_capacity'] ?? 0 );
        
        if (!$area_id || !$area_name) {
            wp_send_json_error('Area ID and name are required');
        }
        
        global $wpdb;
        $updated = $wpdb->upgmdate(
            $wpdb->prefix . 'hbs_areas',
            [
                'name' => $area_name,
                'description' => $area_description,
                'capacity' => $area_capacity > 0 ? $area_capacity : NULL,
            ],
            ['id' => $area_id]
        );
        
        if ($updated !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update area');
        }
    }
    
    /**
     * AJAX: Delete area
     */
    public function ajax_delete_area() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $area_id = intval( $_POST['area_id'] ?? 0 );
        
        if (!$area_id) {
            wp_send_json_error('Area ID is required');
        }
        
        global $wpdb;
        
        // Check if area has bookings
        $booking_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}hbs_bookings WHERE area_id = %d",
            $area_id
        ));
        
        if ($booking_count > 0) {
            wp_send_json_error('Cannot delete area with existing bookings. Please delete the bookings first.');
        }
        
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'hbs_areas',
            ['id' => $area_id]
        );
        
        if ($deleted) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete area');
        }
    }
    
    /**
     * AJAX: Debug calendar data
     */
    public function ajax_debug_calendar() {
        check_ajax_referer('hbs_nonce', 'nonce');
        
        $area_id = intval( $_POST['area_id'] ?? 1 );
        $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? gmdate('Y-m') ) );
        
        global $wpdb;
        
        // Get availability
        $availability = $this->get_month_availability($area_id, $month);
        
        // Get raw bookings
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE area_id = %d",
            $area_id
        ));
        
        // Get area info
        $area = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_areas WHERE id = %d",
            $area_id
        ));
        
        wp_send_json_success([
            'area' => $area,
            'bookings_count' => count($bookings),
            'bookings_sample' => array_slice($bookings, 0, 3),
            'availability_sample' => array_slice($availability, 0, 10, true),
        ]);
    }
    
    /**
     * AJAX: Update existing booking
     */
    public function ajax_update_booking() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $booking_id = intval( $_POST['booking_id'] ?? 0 );
        $renter_name = sanitize_text_field( wp_unslash( $_POST['renter_name'] ?? '' ) );
        $renter_email = sanitize_email( wp_unslash( $_POST['renter_email'] ?? '' ) );
        $renter_phone = sanitize_text_field( wp_unslash( $_POST['renter_phone'] ?? '' ) );
        $start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
        $start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
        $end_date = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
        $end_time = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
        $event_type = sanitize_text_field( wp_unslash( $_POST['event_type'] ?? '' ) );
        $event_details = sanitize_textarea_field($_POST['event_details'] ?? '');
        
        if (!$booking_id || !$renter_name || !$renter_email) {
            wp_send_json_error('Missing required fields');
        }
        
        global $wpdb;
        
        $updated = $wpdb->upgmdate(
            $wpdb->prefix . 'hbs_bookings',
            [
                'renter_name' => $renter_name,
                'renter_email' => $renter_email,
                'renter_phone' => $renter_phone,
                'start_date' => $start_date,
                'start_time' => $start_time . ':00',
                'end_date' => $end_date,
                'end_time' => $end_time,
                'event_type' => $event_type,
                'event_details' => $event_details,
            ],
            ['id' => $booking_id]
        );
        
        if ($updated !== false) {
            wp_send_json_success('Booking updated successfully');
        } else {
            wp_send_json_error('Failed to update booking');
        }
    }
    
    /**
     * AJAX: Save default area
     */
    public function ajax_save_default_area() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'hbs_admin_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $area_id = intval( $_POST['area_id'] ?? 0 );
        
        if (!$area_id) {
            wp_send_json_error('Invalid area ID');
        }
        
        $result = HBS_Database::set_default_area($area_id);
        
        if ($result) {
            wp_send_json_success('Default area saved');
        } else {
            wp_send_json_error('Failed to save default area');
        }
    }
    
    /**
     * AJAX: Save booking hours
     */
    public function ajax_save_booking_hours() {
        check_ajax_referer('hbs_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $day = intval( $_POST['day_of_week'] ?? -1 );
        $is_open = intval( $_POST['is_open'] ?? 0 );
        $start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '09:00' ) );
        $end_time = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '17:00' ) );
        
        if ($day < 0 || $day > 6) {
            wp_send_json_error('Invalid day of week');
        }
        
        // Add seconds if not present
        if (strlen($start_time) == 5) $start_time .= ':00';
        if (strlen($end_time) == 5) $end_time .= ':00';
        
        $result = HBS_Database::save_booking_hours($day, $is_open, $start_time, $end_time);
        
        if ($result) {
            wp_send_json_success('Hours saved successfully');
        } else {
            wp_send_json_error('Failed to save hours');
        }
    }
}
