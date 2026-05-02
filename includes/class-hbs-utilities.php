<?php
/**
 * Hall Booking System - Utility Functions
 */

class HBS_Utilities {
    
    /**
     * Format time for display
     */
    public static function format_time($time_str) {
        if (!$time_str) {
            return 'N/A';
        }
        return date('g:i A', strtotime($time_str));
    }
    
    /**
     * Format date for display
     */
    public static function format_date($date_str) {
        if (!$date_str) {
            return 'N/A';
        }
        return date('M d, Y', strtotime($date_str));
    }
    
    /**
     * Format date range
     */
    public static function format_date_range($start_date, $end_date) {
        if ($start_date === $end_date) {
            return self::format_date($start_date);
        }
        return self::format_date($start_date) . ' to ' . self::format_date($end_date);
    }
    
    /**
     * Format duration in readable format
     */
    public static function format_duration($hours) {
        if ($hours >= 8) {
            $days = intval($hours / 8);
            $remaining_hours = $hours % 8;
            
            $result = '';
            if ($days > 0) {
                $result .= $days . ' day' . ($days > 1 ? 's' : '');
            }
            if ($remaining_hours > 0) {
                if ($result) $result .= ', ';
                $result .= $remaining_hours . ' hour' . ($remaining_hours > 1 ? 's' : '');
            }
            return $result;
        }
        
        if ($hours == intval($hours)) {
            return intval($hours) . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        return $hours . ' hours';
    }
    
    /**
     * Calculate end time from start time and duration
     */
    public static function calculate_end_time($start_time, $duration_hours) {
        $start = new DateTime($start_time);
        $start->add(new DateInterval('PT' . intval($duration_hours * 60) . 'M'));
        return $start->format('H:i:s');
    }
    
    /**
     * Check if a booking is within business hours
     */
    public static function is_within_hours($time_str, $business_open = '08:00', $business_close = '22:00') {
        $time = DateTime::createFromFormat('H:i:s', $time_str);
        $open = DateTime::createFromFormat('H:i:s', $business_open . ':00');
        $close = DateTime::createFromFormat('H:i:s', $business_close . ':00');
        
        return $time >= $open && $time <= $close;
    }
    
    /**
     * Get booking status label
     */
    public static function get_status_label($status) {
        $labels = [
            'pending' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'blocked' => 'Internal Block',
        ];
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
    
    /**
     * Get booking status color
     */
    public static function get_status_color($status) {
        $colors = [
            'pending' => '#ffc107',
            'approved' => '#28a745',
            'rejected' => '#dc3545',
            'blocked' => '#6c757d',
        ];
        
        return isset($colors[$status]) ? $colors[$status] : '#999999';
    }
    
    /**
     * Get recurrence type label
     */
    public static function get_recurrence_label($recurrence_type) {
        $labels = [
            'weekly' => 'Every Week',
            'fortnightly' => 'Every 2 Weeks',
            'monthly' => 'Every Month',
        ];
        
        return isset($labels[$recurrence_type]) ? $labels[$recurrence_type] : ucfirst($recurrence_type);
    }
    
    /**
     * Check if email is valid
     */
    public static function is_valid_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Check if date is in past
     */
    public static function is_past_date($date_str) {
        $date = new DateTime($date_str);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        return $date < $today;
    }
    
    /**
     * Get next available date (after any current bookings)
     */
    public static function get_next_available_date($area_id, $from_date = null) {
        global $wpdb;
        
        if (!$from_date) {
            $from_date = date('Y-m-d');
        }
        
        $from = new DateTime($from_date);
        $current = clone $from;
        
        // Check up to 90 days ahead
        for ($i = 0; $i < 90; $i++) {
            $check_date = $current->format('Y-m-d');
            
            $conflicts = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}hbs_bookings
                 WHERE area_id = %d
                 AND start_date <= %s AND end_date >= %s
                 AND status IN ('approved', 'blocked')",
                $area_id, $check_date, $check_date
            ));
            
            if ($conflicts == 0) {
                return $check_date;
            }
            
            $current->modify('+1 day');
        }
        
        return null;
    }
    
    /**
     * Export booking to iCal format
     */
    public static function export_to_ical($booking) {
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Hall Booking System//WordPress//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        
        $ical .= "BEGIN:VEVENT\r\n";
        $ical .= "UID:" . $booking->id . "@" . get_site_url() . "\r\n";
        $ical .= "DTSTART:" . date('Ymd\THis', strtotime($booking->start_date . ' ' . $booking->start_time)) . "Z\r\n";
        $ical .= "DTEND:" . date('Ymd\THis', strtotime($booking->end_date . ' ' . $booking->end_time)) . "Z\r\n";
        $ical .= "SUMMARY:" . sanitize_text_field($booking->renter_name) . " - " . sanitize_text_field($booking->event_type) . "\r\n";
        $ical .= "DESCRIPTION:" . sanitize_text_field($booking->event_details) . "\r\n";
        $ical .= "LOCATION:Hall\r\n";
        $ical .= "STATUS:CONFIRMED\r\n";
        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";
        
        return $ical;
    }
    
    /**
     * Generate booking summary HTML
     */
    public static function get_booking_summary($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return '';
        }
        
        $area = HBS_Database::get_area($booking->area_id);
        
        $html = '<div class="hbs-booking-summary">';
        $html .= '<h3>' . esc_html($booking->renter_name) . '</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Area:</strong> ' . esc_html($area->name) . '</li>';
        $html .= '<li><strong>Date:</strong> ' . self::format_date_range($booking->start_date, $booking->end_date) . '</li>';
        $html .= '<li><strong>Time:</strong> ' . self::format_time($booking->start_time) . ' - ' . self::format_time($booking->end_time) . '</li>';
        $html .= '<li><strong>Duration:</strong> ' . self::format_duration($booking->duration_hours) . '</li>';
        $html .= '<li><strong>Event Type:</strong> ' . esc_html($booking->event_type) . '</li>';
        $html .= '<li><strong>Contact:</strong> ' . esc_html($booking->renter_email) . ' / ' . esc_html($booking->renter_phone) . '</li>';
        $html .= '<li><strong>Status:</strong> ' . self::get_status_label($booking->status) . '</li>';
        
        if ($booking->event_details) {
            $html .= '<li><strong>Details:</strong> ' . nl2br(esc_html($booking->event_details)) . '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get booking statistics for admin dashboard
     */
    public static function get_booking_stats($area_id = null, $month = null) {
        global $wpdb;
        
        $query = "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}hbs_bookings WHERE 1=1";
        $params = [];
        
        if ($area_id) {
            $query .= " AND area_id = %d";
            $params[] = $area_id;
        }
        
        if ($month) {
            $query .= " AND DATE_FORMAT(start_date, '%Y-%m') = %s";
            $params[] = $month;
        }
        
        $query .= " GROUP BY status";
        
        if ($params) {
            $results = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $results = $wpdb->get_results($query);
        }
        
        $stats = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'blocked' => 0,
            'total' => 0,
        ];
        
        foreach ($results as $row) {
            $stats[$row->status] = intval($row->count);
            $stats['total'] += intval($row->count);
        }
        
        return $stats;
    }
}

// Register global utility function
function hbs_format_date($date) {
    return HBS_Utilities::format_date($date);
}

function hbs_format_time($time) {
    return HBS_Utilities::format_time($time);
}

function hbs_format_duration($hours) {
    return HBS_Utilities::format_duration($hours);
}
