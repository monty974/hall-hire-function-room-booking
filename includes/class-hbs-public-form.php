<?php
/**
 * Hall Booking System - Public Form & Calendar
 */

class HBS_Public_Form {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_shortcode('hbs_booking_form', [$this, 'render_booking_form']);
        add_shortcode('hbs_availability_calendar', [$this, 'render_availability_calendar']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        wp_enqueue_style('hbs-frontend', HBS_PLUGIN_URL . 'assets/css/frontend.css', [], HBS_VERSION);
        wp_enqueue_script('hbs-frontend', HBS_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], HBS_VERSION, true);
        
        wp_localize_script('hbs-frontend', 'hbsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hbs_nonce'),
            'defaultAreaId' => HBS_Database::get_default_area(),
        ]);
    }
    
    /**
     * Render booking form shortcode
     */
    public function render_booking_form($atts) {
        $atts = shortcode_atts([
            'area_id' => 1,
        ], $atts);
        
        ob_start();
        ?>
        <div class="hbs-booking-form-wrapper">
            
            <div class="hbs-booking-container">
                
                <!-- Left Column: Calendar -->
                <div class="hbs-calendar-column">
                    <div class="hbs-mini-calendar-section">
                        <h3 class="calendar-title">Select a Date</h3>
                        <div id="hbs-mini-calendar"></div>
                        <div id="hbs-available-times" class="hbs-available-times" style="margin-top: 20px; display: none;">
                            <h4>Available Times for <span id="selected-date-display"></span></h4>
                            <div id="hbs-time-slots"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Booking Form -->
                <div class="hbs-form-column">
                    <form id="hbs-booking-form" class="hbs-booking-form">
                        
                        <h2 class="form-title">Book Your Event</h2>
                        
                        <div class="form-group">
                            <label for="booking-area" class="form-label">Select Area</label>
                            <select id="booking-area" name="area_id" class="form-control" required>
                                <?php $this->render_area_options(); ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="booking-date" class="form-label">Booking Date</label>
                                <input type="date" id="booking-date" name="booking_date" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="booking-start-time" class="form-label">Start Time</label>
                                <input type="time" id="booking-start-time" name="start_time" class="form-control" value="09:00" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking-duration" class="form-label">Duration</label>
                            <div class="duration-selector">
                                <input type="number" id="booking-duration" name="duration_hours" min="0.5" max="24" step="0.5" value="2" class="form-control" required>
                                <div class="duration-presets">
                                    <button type="button" class="duration-btn" data-hours="2">2h</button>
                                    <button type="button" class="duration-btn" data-hours="4">4h</button>
                                    <button type="button" class="duration-btn" data-hours="8">Half Day</button>
                                    <button type="button" class="duration-btn" data-hours="16">Full Day</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="booking-recurring" name="is_recurring">
                                <span>Recurring Booking?</span>
                            </label>
                        </div>
                        
                        <div id="recurring-options" class="recurring-options" style="display:none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="recurrence-type" class="form-label">Recurrence Type</label>
                                    <select id="recurrence-type" name="recurrence_type" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="fortnightly">Fortnightly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="recurrence-until">Until Date:</label>
                            <input type="date" id="recurrence-until" name="recurrence_until" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="renter-name">Full Name *:</label>
                        <input type="text" id="renter-name" name="renter_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="renter-email">Email *:</label>
                        <input type="email" id="renter-email" name="renter_email" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="renter-phone">Phone:</label>
                        <input type="tel" id="renter-phone" name="renter_phone" class="form-control">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="event-type">Event Type *:</label>
                        <input type="text" id="event-type" name="event_type" class="form-control" placeholder="e.g., Wedding, Conference, Party" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="event-details">Event Details:</label>
                    <textarea id="event-details" name="event_details" class="form-control" rows="4" placeholder="Tell us more about your event..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-lg">Submit Booking Request</button>
                    <span class="form-status" style="display:none;"></span>
                </div>
                
            </form>
            
            <div id="hbs-success-message" class="alert alert-success" style="display:none;">
                <h4>Thank you for your booking request!</h4>
                <p>We have received your request and will review it shortly. You will receive an email notification once your booking has been approved or if we need more information.</p>
            </div>
        </div>
        
        <script>
            // Pass booking hours to JavaScript
            window.hbsBookingHours = <?php echo json_encode($this->get_booking_hours_for_js()); ?>;
        </script>
        
        <style>
            /* Main Container */
            .hbs-booking-form-wrapper { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
            .hbs-booking-container { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; align-items: start; }
            
            /* Calendar Column */
            .hbs-calendar-column { position: sticky; top: 20px; }
            .hbs-mini-calendar-section { 
                background: white; 
                padding: 30px; 
                border-radius: 12px; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 10px 13px rgba(0,0,0,0.1);
                border: none;
            }
            .calendar-title { 
                font-size: 20px; 
                font-weight: 700; 
                color: #1a1a1a; 
                margin-top: 0; 
                margin-bottom: 25px;
                letter-spacing: -0.5px;
            }
            
            /* Calendar Grid */
            .hbs-mini-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
            .hbs-mini-calendar-day { 
                padding: 10px; 
                text-align: center; 
                border: 2px solid #e5e5e5;
                border-radius: 8px; 
                cursor: pointer; 
                font-size: 13px; 
                font-weight: 600;
                transition: all 0.2s ease;
                background: white;
            }
            .hbs-mini-calendar-day.other-month { background: #f8f8f8; color: #ccc; cursor: default; border-color: #f0f0f0; }
            .hbs-mini-calendar-day.available { 
                background: #ecf7f0; 
                border-color: #2ecc71; 
                color: #27ae60;
                cursor: pointer; 
            }
            .hbs-mini-calendar-day.available:hover { background: #2ecc71; color: white; }
            .hbs-mini-calendar-day.partial { background: #fff9e6; border-color: #f39c12; color: #d68910; cursor: pointer; }
            .hbs-mini-calendar-day.partial:hover { background: #f39c12; color: white; }
            .hbs-mini-calendar-day.booked { background: #fce4e4; border-color: #e74c3c; color: #c0392b; cursor: default; }
            .hbs-mini-calendar-day.closed { background: #f0f0f0; border-color: #bbb; color: #999; cursor: default; opacity: 0.6; }
            .hbs-mini-calendar-day.selected { border: 2px solid #3498db; background: #ebf5fb; color: #2980b9; font-weight: 700; }
            
            /* Available Times */
            .hbs-available-times { 
                background: linear-gradient(135deg, #f0f7ff 0%, #e8f4f8 100%);
                padding: 20px; 
                border-radius: 8px; 
                border-left: 4px solid #3498db;
                margin-top: 25px;
            }
            .hbs-available-times h4 { 
                margin-top: 0; 
                margin-bottom: 15px;
                color: #2c3e50; 
                font-size: 14px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            #hbs-time-slots { display: flex; flex-wrap: wrap; gap: 8px; }
            .hbs-time-slot { 
                padding: 9px 14px; 
                background: #3498db; 
                color: white; 
                border-radius: 6px; 
                cursor: pointer; 
                font-size: 12px; 
                border: none; 
                font-weight: 600;
                transition: all 0.2s ease;
            }
            .hbs-time-slot:hover { background: #2980b9; transform: translateY(-2px); box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3); }
            .hbs-time-slot.unavailable { background: #ecf0f1; color: #95a5a6; cursor: not-allowed; opacity: 0.6; }
            
            /* Form Column */
            .hbs-form-column { }
            .hbs-booking-form { 
                background: white; 
                padding: 40px; 
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 10px 13px rgba(0,0,0,0.1);
                border: none;
            }
            .form-title { 
                font-size: 28px; 
                font-weight: 700; 
                color: #1a1a1a; 
                margin-top: 0; 
                margin-bottom: 30px;
                letter-spacing: -0.5px;
            }
            
            /* Form Elements */
            .form-group { margin-bottom: 25px; }
            .form-label { 
                display: block; 
                margin-bottom: 10px; 
                font-weight: 600; 
                color: #2c3e50;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .form-control { 
                width: 100%; 
                padding: 12px 16px; 
                border: 2px solid #e5e5e5; 
                border-radius: 8px; 
                font-size: 15px;
                transition: all 0.2s ease;
                background: #fafafa;
                font-family: inherit;
            }
            .form-control:focus { 
                outline: none;
                border-color: #3498db; 
                background: white;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            }
            .form-control:disabled { background: #f5f5f5; color: #95a5a6; cursor: not-allowed; border-color: #ecf0f1; }
            
            /* Form Row */
            .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .form-row .form-group { margin-bottom: 0; }
            
            /* Checkbox */
            .checkbox-group label { margin-bottom: 0; }
            .checkbox-label { 
                display: flex; 
                align-items: center; 
                font-weight: 500;
                cursor: pointer;
                color: #2c3e50;
            }
            .checkbox-label input { margin-right: 10px; cursor: pointer; }
            
            /* Buttons */
            .btn { 
                padding: 14px 32px; 
                border: none; 
                border-radius: 8px; 
                cursor: pointer; 
                font-weight: 600;
                font-size: 15px;
                transition: all 0.2s ease;
                width: 100%;
            }
            .btn-primary { 
                background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                color: white;
            }
            .btn-primary:hover { 
                box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
                transform: translateY(-2px);
            }
            
            /* Duration Selector */
            .duration-selector { }
            .duration-presets { 
                margin-top: 12px; 
                display: grid; 
                grid-template-columns: repeat(4, 1fr);
                gap: 10px; 
            }
            .duration-btn { 
                padding: 10px 12px; 
                background: #ecf0f1; 
                border: 2px solid #e5e5e5; 
                border-radius: 6px; 
                cursor: pointer;
                font-weight: 600;
                font-size: 13px;
                transition: all 0.2s ease;
            }
            .duration-btn:hover { 
                background: #3498db; 
                color: white;
                border-color: #3498db;
            }
            
            /* Recurring Options */
            .recurring-options { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin-top: 20px;
                border-left: 4px solid #9b59b6;
            }
            
            /* Status Messages */
            .form-status { 
                display: inline-block; 
                margin-left: 15px;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 600;
            }
            
            /* Alerts */
            .alert { 
                padding: 16px 20px; 
                border-radius: 8px; 
                margin-bottom: 20px;
                border-left: 4px solid;
                font-size: 14px;
            }
            .alert-success { 
                background: #ecf7f0;
                border-left-color: #2ecc71;
                color: #27ae60; 
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .hbs-booking-container { 
                    grid-template-columns: 1fr; 
                    gap: 30px;
                }
                .hbs-calendar-column { position: static; }
                .form-row { grid-template-columns: 1fr; }
                .duration-presets { grid-template-columns: repeat(2, 1fr); }
            }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render availability calendar shortcode
     */
    public function render_availability_calendar($atts) {
        $atts = shortcode_atts([
            'area_id' => 1,
            'month' => date('Y-m'),
        ], $atts);
        
        ob_start();
        ?>
        <div class="hbs-calendar-wrapper" data-area-id="<?php echo intval($atts['area_id']); ?>">
            <div class="hbs-calendar-controls">
                <div class="hbs-calendar-area-selector">
                    <label for="calendar-area-select">Select Area:</label>
                    <select id="calendar-area-select" class="hbs-area-select">
                        <?php $this->render_area_options(); ?>
                    </select>
                </div>
                <div class="hbs-calendar-nav">
                    <button id="hbs-prev-month" class="btn btn-sm">&lt; Previous</button>
                    <span id="hbs-current-month" class="current-month"></span>
                    <button id="hbs-next-month" class="btn btn-sm">Next &gt;</button>
                </div>
            </div>
            
            <div id="hbs-calendar" class="hbs-calendar">
                <!-- Calendar will be rendered here by JavaScript -->
            </div>
            
            <div class="hbs-legend">
                <div class="legend-item">
                    <span class="legend-color available"></span> Available
                </div>
                <div class="legend-item">
                    <span class="legend-color booked"></span> Booked
                </div>
                <div class="legend-item">
                    <span class="legend-color blocked"></span> Internal Block
                </div>
            </div>
        </div>
        
        <style>
            .hbs-calendar-wrapper { max-width: 800px; margin: 30px auto; }
            .hbs-calendar-controls { margin-bottom: 20px; }
            .hbs-calendar-area-selector { margin-bottom: 20px; text-align: center; }
            .hbs-calendar-area-selector label { font-weight: 600; margin-right: 10px; }
            .hbs-area-select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; min-width: 200px; }
            .hbs-calendar-nav { text-align: center; }
            .current-month { display: inline-block; min-width: 200px; text-align: center; font-weight: 600; }
            .hbs-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
            .hbs-calendar-day { 
                aspect-ratio: 1;
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: center; 
                border-radius: 4px;
                background: white;
            }
            .hbs-calendar-day.other-month { background: #f5f5f5; color: #999; }
            .hbs-calendar-day.available { background: #d4edda; cursor: pointer; }
            .hbs-calendar-day.booked { background: #fff3cd; cursor: not-allowed; border-color: #ffc107; color: #856404; }
            .hbs-calendar-day.blocked { background: #e2e3e5; cursor: not-allowed; }
            .hbs-calendar-day.today { border: 2px solid #007bff; }
            .hbs-legend { display: flex; gap: 20px; justify-content: center; margin-top: 20px; }
            .legend-item { display: flex; align-items: center; gap: 8px; }
            .legend-color { width: 20px; height: 20px; border-radius: 3px; }
            .legend-color.available { background: #d4edda; border: 1px solid #28a745; }
            .legend-color.booked { background: #fff3cd; border: 1px solid #ffc107; }
            .legend-color.blocked { background: #e2e3e5; border: 1px solid #6c757d; }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render area options
     */
    private function render_area_options() {
        $areas = HBS_Database::get_areas();
        foreach ($areas as $area) {
            echo '<option value="' . $area->id . '">' . esc_html($area->name) . '</option>';
        }
    }
    
    /**
     * Get booking hours formatted for JavaScript
     */
    private function get_booking_hours_for_js() {
        $hours = HBS_Database::get_booking_hours();
        $result = [];
        
        foreach ($hours as $day_num => $hour) {
            $result[$day_num] = [
                'day_of_week' => $day_num,
                'is_open' => intval($hour->is_open),
                'start_time' => $hour->start_time,
                'end_time' => $hour->end_time,
            ];
        }
        
        return $result;
    }
}
