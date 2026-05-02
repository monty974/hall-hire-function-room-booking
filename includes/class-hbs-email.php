<?php
/**
 * Hall Booking System - Email Notifications
 */

class HBS_Email {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send pending notification to admin
     */
    public function send_pending_notification($booking_id, $data) {
        $admin_email = get_option('admin_email');
        $subject = 'New Booking Request: ' . sanitize_text_field($data['renter_name']);
        
        $message = $this->get_email_header();
        $message .= '<h2>New Booking Request</h2>';
        $message .= '<p><strong>Renter:</strong> ' . esc_html($data['renter_name']) . '</p>';
        $message .= '<p><strong>Email:</strong> ' . esc_html($data['renter_email']) . '</p>';
        $message .= '<p><strong>Phone:</strong> ' . esc_html($data['renter_phone']) . '</p>';
        $message .= '<p><strong>Date:</strong> ' . esc_html($data['start_date']) . '</p>';
        $message .= '<p><strong>Time:</strong> ' . esc_html($data['start_time']) . ' (Duration: ' . esc_html($data['duration_hours']) . ' hours)</p>';
        $message .= '<p><strong>Event Type:</strong> ' . esc_html($data['event_type']) . '</p>';
        $message .= '<p><strong>Details:</strong> ' . wp_kses_post(nl2br($data['event_details'])) . '</p>';
        
        if ($data['is_recurring']) {
            $message .= '<p><strong>Recurring:</strong> ' . esc_html(ucfirst($data['recurrence_type'])) . ' until ' . esc_html($data['recurrence_until']) . '</p>';
        }
        
        $message .= '<p><a href="' . esc_url(admin_url('admin.php?page=hbs-bookings')) . '">Review Booking Request</a></p>';
        $message .= $this->get_email_footer();
        
        $this->send_email($admin_email, $subject, $message);
    }
    
    /**
     * Send approval email to renter
     */
    public function send_approval_email($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return false;
        }
        
        $subject = 'Your Booking Request Has Been Approved';
        $message = $this->get_email_header();
        $message .= '<h2>Booking Approved!</h2>';
        $message .= '<p>Dear ' . esc_html($booking->renter_name) . ',</p>';
        $message .= '<p>We are pleased to confirm that your booking request has been approved.</p>';
        $message .= '<h3>Booking Details:</h3>';
        $message .= '<p><strong>Date:</strong> ' . esc_html($booking->start_date) . '</p>';
        $message .= '<p><strong>Time:</strong> ' . esc_html($booking->start_time) . ' - ' . esc_html($booking->end_time) . '</p>';
        $message .= '<p><strong>Duration:</strong> ' . esc_html($booking->duration_hours) . ' hours</p>';
        
        if ($booking->is_recurring) {
            $recurrence = $wpdb->get_row($wpdb->prepare(
                "SELECT recurrence_type, recurrence_until FROM {$wpdb->prefix}hbs_recurrence WHERE booking_id = %d",
                $booking_id
            ));
            if ($recurrence) {
                $message .= '<p><strong>Recurring:</strong> ' . esc_html(ucfirst($recurrence->recurrence_type)) . ' until ' . esc_html($recurrence->recurrence_until) . '</p>';
            }
        }
        
        $message .= '<p>Thank you for your booking!</p>';
        $message .= $this->get_email_footer();
        
        return $this->send_email($booking->renter_email, $subject, $message);
    }
    
    /**
     * Send rejection email to renter
     */
    public function send_rejection_email($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hbs_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return false;
        }
        
        $subject = 'Your Booking Request Status';
        $message = $this->get_email_header();
        $message .= '<h2>Booking Request Rejected</h2>';
        $message .= '<p>Dear ' . esc_html($booking->renter_name) . ',</p>';
        $message .= '<p>Unfortunately, we are unable to approve your booking request for the following date:</p>';
        $message .= '<p><strong>Date:</strong> ' . esc_html($booking->start_date) . '</p>';
        $message .= '<p><strong>Time:</strong> ' . esc_html($booking->start_time) . ' - ' . esc_html($booking->end_time) . '</p>';
        $message .= '<p>This may be due to a scheduling conflict. Please contact us if you would like to discuss alternative dates.</p>';
        $message .= $this->get_email_footer();
        
        return $this->send_email($booking->renter_email, $subject, $message);
    }
    
    /**
     * Send email with HTML content type
     */
    private function send_email($to, $subject, $message) {
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . get_bloginfo('name') . " <" . get_option('admin_email') . ">\r\n";
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get email header HTML
     */
    private function get_email_header() {
        $site_name = get_bloginfo('name');
        return '<html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    h2 { color: #2c3e50; }
                    p { line-height: 1.6; }
                    a { color: #3498db; text-decoration: none; }
                </style>
            </head>
            <body style="background-color: #f5f5f5; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <h1 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">' . $site_name . '</h1>';
    }
    
    /**
     * Get email footer HTML
     */
    private function get_email_footer() {
        $site_url = get_bloginfo('url');
        return '<hr style="border: 1px solid #ddd; margin: 20px 0;">
                    <p style="color: #999; font-size: 12px; text-align: center;">
                        This is an automated message from ' . get_bloginfo('name') . '. Please do not reply to this email.<br>
                        <a href="' . $site_url . '">Visit our website</a>
                    </p>
                </div>
            </body>
        </html>';
    }
}
